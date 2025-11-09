<?php
// process_edit_program.php
session_start(); 

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true) {
    $_SESSION['error_message'] = "Access denied. Please log in as an administrator.";
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in process_edit_program.php.");
     $_SESSION['error_message'] = "Database configuration error. Cannot process request.";
     header("location: admin_program_management.php"); 
     exit;
}

// --- Define variables and initialize ---
$program_id_to_edit = null;
$title = $description = "";
$trainer_id = null;
$errors = []; 
$action = ''; 

// --- Check which button was clicked ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['save_changes'])) {
        $action = 'save';
    } elseif (isset($_POST['delete_program'])) {
        $action = 'delete';
    } else {
         $_SESSION['error_message'] = "Invalid form submission.";
         header("location: admin_program_management.php");
         exit;
    }

    // Validate Program ID (needed for both save and delete)
    if (isset($_POST['programId']) && is_numeric($_POST['programId'])) {
        $program_id_to_edit = (int)$_POST['programId'];
    } else {
        $_SESSION['error_message'] = "Invalid program ID specified.";
        header("location: admin_program_management.php");
        exit;
    }

    // --- Handle DELETE action ---
    if ($action === 'delete') {
        // Before deleting, consider implications:
        // - What happens to tblScheduleLedger entries linked to this program? (ON DELETE CASCADE is set, they will be deleted)
        // - What happens to tblTeam entries linked? (ON DELETE SET NULL, Team.TrainingProgramID becomes NULL)
        // - What happens to tblTrainer entries linked? (ON DELETE SET NULL, Trainer.TrainingProgramID becomes NULL)
        // Add checks if deletion should be prevented under certain conditions (e.g., active schedules exist).
        
        $sql_delete = "DELETE FROM tblTrainingProgram WHERE trainingProgramID = ?";
        if ($stmt_delete = $mysqli->prepare($sql_delete)) {
            $stmt_delete->bind_param("i", $program_id_to_edit);
            if ($stmt_delete->execute()) {
                if ($stmt_delete->affected_rows > 0) {
                     $_SESSION['success_message'] = "Training program successfully deleted.";
                } else {
                     $_SESSION['error_message'] = "Program not found or already deleted.";
                }
            } else {
                 $_SESSION['error_message'] = "Error deleting program.";
                 error_log("Error executing delete program query for PID {$program_id_to_edit}: " . $stmt_delete->error);
            }
            $stmt_delete->close();
        } else {
             $_SESSION['error_message'] = "Error preparing delete statement.";
             error_log("Error preparing delete program query: " . $mysqli->error);
        }
        $mysqli->close();
        header("location: admin_program_management.php");
        exit;
    }

    // --- Handle SAVE action (Validation) ---
    elseif ($action === 'save') {
        // Validate Title
        if (empty(trim($_POST["programTitle"]))) { 
            $errors['title'] = "Please enter a program title."; 
        } else { 
            $title = trim($_POST["programTitle"]); 
        }

        // Validate Description
        if (empty(trim($_POST["programDescription"]))) { 
            $errors['description'] = "Please enter a description."; 
        } else { 
            $description = trim($_POST["programDescription"]); 
        }
        
        // Validate Trainer ID
        if (empty($_POST["assignInstructor"])) {
             $errors['trainer'] = "Please select an instructor.";
             $trainer_id = null; // Ensure it's null if empty
        } elseif (!is_numeric($_POST["assignInstructor"])) {
             $errors['trainer'] = "Invalid instructor selection.";
             $trainer_id = null;
        } else {
            $trainer_id = (int)$_POST["assignInstructor"];
            // Optional: Check if the selected trainerID actually exists in tblTrainer
            // $check_trainer_sql = "SELECT trainerID FROM tblTrainer WHERE trainerID = ?"; ...
        }

        // --- If no validation errors, proceed to update database ---
        if (empty($errors)) {
            
            // Prepare UPDATE statement
            $sql_update = "UPDATE tblTrainingProgram SET title = ?, description = ?, TrainerID = ? WHERE trainingProgramID = ?";
            
            if ($stmt_update = $mysqli->prepare($sql_update)) {
                // Bind variables (s = string, i = integer)
                // Note: TrainerID can be NULL in the DB, bind_param handles this correctly if $trainer_id is null.
                $stmt_update->bind_param("ssii", $title, $description, $trainer_id, $program_id_to_edit);

                if ($stmt_update->execute()) {
                    // Check if any row was actually updated
                    if ($stmt_update->affected_rows >= 0) { // >= 0 because update might not change anything if data is same
                         $_SESSION['success_message'] = "Training program '" . htmlspecialchars($title) . "' updated successfully!";
                         header("location: admin_program_management.php"); 
                         exit;
                    } else {
                         // This case might not be reached if execute() fails, but included for completeness
                         $errors['db'] = "No changes were made to the program.";
                    }
                } else {
                     $errors['db'] = "Error updating program record.";
                     error_log("Error updating program (PID: {$program_id_to_edit}): " . $stmt_update->error);
                }
                $stmt_update->close();
            } else {
                 $errors['db'] = "Error preparing program update statement.";
                 error_log("Error preparing program update query: " . $mysqli->error);
            }
        } // End if(empty($errors))

        // --- If there were errors during save, redirect back to the edit form ---
        if (!empty($errors)) {
            $_SESSION['edit_form_errors'] = $errors;
            $_SESSION['edit_form_data'] = $_POST; // Store submitted data
            header("location: admin_edit_program.php?pid=" . $program_id_to_edit);
            exit;
        }

    } // End elseif ($action === 'save')

    // Close connection if still open
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }

} else {
    // If not a POST request, redirect to program list
    $_SESSION['error_message'] = "Invalid request method.";
    header("location: admin_program_management.php");
    exit;
}
?>
