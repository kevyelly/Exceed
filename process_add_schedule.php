<?php
// process_add_schedule.php
session_start(); 

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true) {
    $_SESSION['error_message'] = "Access denied. Please log in as an administrator.";
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in process_add_schedule.php.");
     $_SESSION['error_message'] = "Database configuration error. Cannot process request.";
     header("location: admin_schedule_management.php"); 
     exit;
}

// --- Define variables and initialize ---
$program_id = null; 
$event_date = $event_time = $status = "";
$errors = []; 

// --- Processing form data when form is submitted ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate Program ID
    if (empty($_POST["programId"])) {
        $errors['program'] = "Please select a training program.";
    } elseif (!filter_var($_POST["programId"], FILTER_VALIDATE_INT)) {
        $errors['program'] = "Invalid program selected.";
    } else {
        $program_id = (int)$_POST["programId"];
        // Optional: Check if program ID exists in tblTrainingProgram
    }

    // Validate Date
    if (empty(trim($_POST["eventDate"]))) { 
        $errors['date'] = "Please select a date."; 
    } else { 
        // Basic check for valid date format (YYYY-MM-DD)
        if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", trim($_POST["eventDate"]))) {
             $event_date = trim($_POST["eventDate"]);
        } else {
             $errors['date'] = "Invalid date format.";
        }
    }

    // Validate Time
    if (empty(trim($_POST["eventTime"]))) { 
        $errors['time'] = "Please select a time."; 
    } else { 
         // Basic check for valid time format (HH:MM or HH:MM:SS)
        if (preg_match("/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/", trim($_POST["eventTime"]))) {
            $event_time = trim($_POST["eventTime"]);
        } else {
             $errors['time'] = "Invalid time format.";
        }
    }
    
    // Validate Status
    if (empty(trim($_POST["status"]))) { 
        $errors['status'] = "Please select a status."; 
    } else { 
        $status = trim($_POST["status"]); 
        // Optional: Validate against a predefined list of allowed statuses
        $allowed_statuses = ['Scheduled', 'Cancelled', 'Completed', 'InProgress']; // Example
        if (!in_array($status, $allowed_statuses)) {
             $errors['status'] = "Invalid status selected.";
        }
    }

    // --- If no validation errors, proceed to insert into database ---
    if (empty($errors)) {
        
        $mysqli->begin_transaction();
        $success = true;
        $new_schedule_id = null;

        try {
            // 1. Find or Create Schedule Slot in tblSchedule
            $sql_find_schedule = "SELECT scheduleID FROM tblSchedule WHERE event_date = ? AND event_time = ?";
            if($stmt_find = $mysqli->prepare($sql_find_schedule)) {
                $stmt_find->bind_param("ss", $event_date, $event_time);
                $stmt_find->execute();
                $result_find = $stmt_find->get_result();
                if($result_find->num_rows > 0) {
                    // Existing schedule slot found
                    $new_schedule_id = $result_find->fetch_assoc()['scheduleID'];
                } else {
                    // Schedule slot not found, create it
                    $sql_insert_schedule = "INSERT INTO tblSchedule (event_date, event_time) VALUES (?, ?)";
                    if($stmt_insert_s = $mysqli->prepare($sql_insert_schedule)) {
                        $stmt_insert_s->bind_param("ss", $event_date, $event_time);
                        if($stmt_insert_s->execute()) {
                            $new_schedule_id = $mysqli->insert_id;
                        } else {
                            $success = false;
                            $errors['db'] = "Error creating schedule slot.";
                            error_log("Error inserting schedule: " . $stmt_insert_s->error);
                        }
                        $stmt_insert_s->close();
                    } else {
                         $success = false;
                         $errors['db'] = "Error preparing schedule insert statement.";
                         error_log("Error preparing schedule insert query: " . $mysqli->error);
                    }
                }
                $stmt_find->close();
            } else {
                 $success = false;
                 $errors['db'] = "Error preparing schedule find statement.";
                 error_log("Error preparing schedule find query: " . $mysqli->error);
            }

            // 2. Insert into tblScheduleLedger (if schedule slot was found/created)
            if ($success && $new_schedule_id !== null) {
                 // Check if this specific program is already scheduled for this exact slot
                 $sql_check_ledger = "SELECT ledgerID FROM tblScheduleLedger WHERE trainingProgramID = ? AND scheduleID = ?";
                 if($stmt_check_l = $mysqli->prepare($sql_check_ledger)){
                     $stmt_check_l->bind_param("ii", $program_id, $new_schedule_id);
                     $stmt_check_l->execute();
                     $stmt_check_l->store_result();
                     if($stmt_check_l->num_rows > 0){
                         $success = false;
                         $errors['db'] = "This program is already scheduled for this exact date and time.";
                     }
                     $stmt_check_l->close();
                 } else {
                      $success = false;
                      $errors['db'] = "Error checking schedule ledger.";
                      error_log("Error preparing schedule ledger check query: " . $mysqli->error);
                 }

                 // Proceed with insert only if no conflict found
                 if($success) {
                    $sql_insert_ledger = "INSERT INTO tblScheduleLedger (trainingProgramID, scheduleID, status) VALUES (?, ?, ?)";
                    if ($stmt_insert_l = $mysqli->prepare($sql_insert_ledger)) {
                        $stmt_insert_l->bind_param("iis", $program_id, $new_schedule_id, $status);
                        if (!$stmt_insert_l->execute()) {
                            $success = false;
                            $errors['db'] = "Error creating schedule ledger entry.";
                            error_log("Error inserting schedule ledger: " . $stmt_insert_l->error);
                        }
                        $stmt_insert_l->close();
                    } else {
                        $success = false;
                        $errors['db'] = "Error preparing schedule ledger insert statement.";
                        error_log("Error preparing schedule ledger insert query: " . $mysqli->error);
                    }
                 }
            } else if ($success && $new_schedule_id === null) {
                 // This case should ideally not happen if the previous block worked, but as a safeguard
                 $success = false;
                 $errors['db'] = "Failed to obtain a valid schedule slot ID.";
            }

            // Commit or Rollback
            if ($success) {
                $mysqli->commit();
                $_SESSION['success_message'] = "Schedule entry added successfully!";
                header("location: admin_schedule_management.php"); 
                exit;
            } else {
                $mysqli->rollback();
                // Error message should be set in $errors['db']
            }

        } catch (Exception $e) {
            $mysqli->rollback();
            $errors['db'] = "An unexpected error occurred during scheduling.";
            error_log("Schedule Transaction Error: " . $e->getMessage());
        }
    } 

    // --- If there were errors, redirect back ---
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST; 
        header("location: admin_add_schedule.php"); 
        exit;
    }

    // Close connection
    $mysqli->close();

} else {
    // If not a POST request, redirect
    $_SESSION['error_message'] = "Invalid request method.";
    header("location: admin_schedule_management.php");
    exit;
}
?>
