<?php
// process_edit_schedule.php
session_start(); 

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true) {
    $_SESSION['error_message'] = "Access denied. Please log in as an administrator.";
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in process_edit_schedule.php.");
     $_SESSION['error_message'] = "Database configuration error. Cannot process request.";
     header("location: admin_schedule_management.php"); 
     exit;
}

// --- Define variables and initialize ---
$ledger_id_to_edit = null;
$program_id = null; 
$event_date = $event_time = $status = "";
$errors = []; 
$action = ''; 

// --- Check which button was clicked ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['save_changes'])) {
        $action = 'save';
    } elseif (isset($_POST['delete_schedule'])) {
        $action = 'delete';
    } else {
         $_SESSION['error_message'] = "Invalid form submission.";
         header("location: admin_schedule_management.php");
         exit;
    }

    // Validate Ledger ID (needed for both save and delete)
    if (isset($_POST['ledgerId']) && filter_var($_POST['ledgerId'], FILTER_VALIDATE_INT)) {
        $ledger_id_to_edit = (int)$_POST['ledgerId'];
    } else {
        $_SESSION['error_message'] = "Invalid schedule entry ID specified.";
        header("location: admin_schedule_management.php");
        exit;
    }

    // --- Handle DELETE action ---
    if ($action === 'delete') {
        // Note: Deleting from tblScheduleLedger doesn't automatically delete the tblSchedule entry.
        // You might want cleanup logic later to remove unused schedule slots.
        
        $sql_delete = "DELETE FROM tblScheduleLedger WHERE ledgerID = ?";
        if ($stmt_delete = $mysqli->prepare($sql_delete)) {
            $stmt_delete->bind_param("i", $ledger_id_to_edit);
            if ($stmt_delete->execute()) {
                if ($stmt_delete->affected_rows > 0) {
                     $_SESSION['success_message'] = "Schedule entry successfully deleted.";
                } else {
                     $_SESSION['error_message'] = "Schedule entry not found or already deleted.";
                }
            } else {
                 $_SESSION['error_message'] = "Error deleting schedule entry.";
                 error_log("Error executing delete schedule query for LID {$ledger_id_to_edit}: " . $stmt_delete->error);
            }
            $stmt_delete->close();
        } else {
             $_SESSION['error_message'] = "Error preparing delete statement.";
             error_log("Error preparing delete schedule query: " . $mysqli->error);
        }
        $mysqli->close();
        header("location: admin_schedule_management.php");
        exit;
    }

    // --- Handle SAVE action (Validation) ---
    elseif ($action === 'save') {
        // Validate Program ID
        if (empty($_POST["programId"])) { $errors['program'] = "Please select a training program."; } 
        elseif (!filter_var($_POST["programId"], FILTER_VALIDATE_INT)) { $errors['program'] = "Invalid program selected."; } 
        else { $program_id = (int)$_POST["programId"]; }

        // Validate Date
        if (empty(trim($_POST["eventDate"]))) { $errors['date'] = "Please select a date."; } 
        elseif (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", trim($_POST["eventDate"]))) { $errors['date'] = "Invalid date format."; } 
        else { $event_date = trim($_POST["eventDate"]); }

        // Validate Time
        if (empty(trim($_POST["eventTime"]))) { $errors['time'] = "Please select a time."; } 
        elseif (!preg_match("/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/", trim($_POST["eventTime"]))) { $errors['time'] = "Invalid time format."; } 
        else { $event_time = trim($_POST["eventTime"]); }
        
        // Validate Status
        if (empty(trim($_POST["status"]))) { $errors['status'] = "Please select a status."; } 
        else { 
            $status = trim($_POST["status"]); 
            $allowed_statuses = ['Scheduled', 'Cancelled', 'Completed', 'InProgress']; 
            if (!in_array($status, $allowed_statuses)) { $errors['status'] = "Invalid status selected."; }
        }

        // --- If no validation errors, proceed to update database ---
        if (empty($errors)) {
            
            $mysqli->begin_transaction();
            $success = true;
            $new_schedule_id = null;

            try {
                // 1. Find or Create Schedule Slot in tblSchedule for the NEW date/time
                $sql_find_schedule = "SELECT scheduleID FROM tblSchedule WHERE event_date = ? AND event_time = ?";
                if($stmt_find = $mysqli->prepare($sql_find_schedule)) {
                    $stmt_find->bind_param("ss", $event_date, $event_time);
                    $stmt_find->execute();
                    $result_find = $stmt_find->get_result();
                    if($result_find->num_rows > 0) {
                        $new_schedule_id = $result_find->fetch_assoc()['scheduleID'];
                    } else {
                        $sql_insert_schedule = "INSERT INTO tblSchedule (event_date, event_time) VALUES (?, ?)";
                        if($stmt_insert_s = $mysqli->prepare($sql_insert_schedule)) {
                            $stmt_insert_s->bind_param("ss", $event_date, $event_time);
                            if($stmt_insert_s->execute()) {
                                $new_schedule_id = $mysqli->insert_id;
                            } else { $success = false; $errors['db'] = "Error creating new schedule slot."; error_log("Error inserting schedule: " . $stmt_insert_s->error); }
                            $stmt_insert_s->close();
                        } else { $success = false; $errors['db'] = "Error preparing schedule insert statement."; error_log("Error preparing schedule insert query: " . $mysqli->error); }
                    }
                    $stmt_find->close();
                } else { $success = false; $errors['db'] = "Error preparing schedule find statement."; error_log("Error preparing schedule find query: " . $mysqli->error); }

                // 2. Update tblScheduleLedger (if schedule slot was found/created)
                if ($success && $new_schedule_id !== null) {
                    // Check if this specific program is already scheduled for the NEW slot (excluding the current entry being edited)
                     $sql_check_ledger = "SELECT ledgerID FROM tblScheduleLedger WHERE trainingProgramID = ? AND scheduleID = ? AND ledgerID != ?";
                     if($stmt_check_l = $mysqli->prepare($sql_check_ledger)){
                         $stmt_check_l->bind_param("iii", $program_id, $new_schedule_id, $ledger_id_to_edit);
                         $stmt_check_l->execute();
                         $stmt_check_l->store_result();
                         if($stmt_check_l->num_rows > 0){
                             $success = false;
                             $errors['db'] = "This program is already scheduled for this exact date and time in another entry.";
                         }
                         $stmt_check_l->close();
                     } else { $success = false; $errors['db'] = "Error checking schedule ledger."; error_log("Error preparing schedule ledger check query: " . $mysqli->error); }

                     // Proceed with update only if no conflict found
                     if($success) {
                        $sql_update_ledger = "UPDATE tblScheduleLedger SET trainingProgramID = ?, scheduleID = ?, status = ? WHERE ledgerID = ?";
                        if ($stmt_update_l = $mysqli->prepare($sql_update_ledger)) {
                            $stmt_update_l->bind_param("iisi", $program_id, $new_schedule_id, $status, $ledger_id_to_edit);
                            if (!$stmt_update_l->execute()) {
                                $success = false;
                                $errors['db'] = "Error updating schedule ledger entry.";
                                error_log("Error updating schedule ledger (LID: {$ledger_id_to_edit}): " . $stmt_update_l->error);
                            }
                            $stmt_update_l->close();
                        } else {
                            $success = false;
                            $errors['db'] = "Error preparing schedule ledger update statement.";
                            error_log("Error preparing schedule ledger update query: " . $mysqli->error);
                        }
                     }
                } else if ($success && $new_schedule_id === null) {
                     $success = false;
                     $errors['db'] = "Failed to obtain a valid schedule slot ID.";
                }

                // Commit or Rollback
                if ($success) {
                    $mysqli->commit();
                    $_SESSION['success_message'] = "Schedule entry updated successfully!";
                    header("location: admin_schedule_management.php"); 
                    exit;
                } else {
                    $mysqli->rollback();
                    // Error message should be set in $errors['db']
                }

            } catch (Exception $e) {
                $mysqli->rollback();
                $errors['db'] = "An unexpected error occurred during scheduling update.";
                error_log("Schedule Update Transaction Error: " . $e->getMessage());
            }
        } 

        // --- If there were errors during save, redirect back ---
        if (!empty($errors)) {
            $_SESSION['edit_form_errors'] = $errors;
            $_SESSION['edit_form_data'] = $_POST; 
            header("location: admin_edit_schedule.php?lid=" . $ledger_id_to_edit);
            exit;
        }

    } // End elseif ($action === 'save')

    // Close connection
    $mysqli->close();

} else {
    // If not a POST request, redirect
    $_SESSION['error_message'] = "Invalid request method.";
    header("location: admin_schedule_management.php");
    exit;
}
?>
