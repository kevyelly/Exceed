<?php
// process_edit_team.php
session_start(); 

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true) {
    $_SESSION['error_message'] = "Access denied. Please log in as an administrator.";
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in process_edit_team.php.");
     $_SESSION['error_message'] = "Database configuration error. Cannot process request.";
     header("location: admin_team_management.php"); 
     exit;
}

// --- Define variables and initialize ---
$team_id_to_edit = null;
$team_name = "";
$program_id = null; 
$errors = []; 
$action = ''; 

// --- Check which button was clicked ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['save_changes'])) {
        $action = 'save';
    } elseif (isset($_POST['delete_team'])) {
        $action = 'delete';
    } else {
         $_SESSION['error_message'] = "Invalid form submission.";
         header("location: admin_team_management.php");
         exit;
    }

    // Validate Team ID 
    if (isset($_POST['teamId']) && is_numeric($_POST['teamId'])) {
        $team_id_to_edit = (int)$_POST['teamId'];
    } else {
        $_SESSION['error_message'] = "Invalid team ID specified.";
        header("location: admin_team_management.php");
        exit;
    }

    // --- Handle DELETE action ---
    if ($action === 'delete') {
        // Before deleting, consider implications:
        // - Trainees assigned to this team will have their TeamID set to NULL (due to ON DELETE SET NULL)
        
        // Start transaction for safety, although cascade might handle it
        $mysqli->begin_transaction();
        try {
            // Optional: Unassign trainees explicitly if ON DELETE SET NULL isn't trusted or needs logging
            // $sql_unassign = "UPDATE tblTrainee SET TeamID = NULL WHERE TeamID = ?";
            // $stmt_unassign = $mysqli->prepare($sql_unassign);
            // $stmt_unassign->bind_param("i", $team_id_to_edit);
            // $stmt_unassign->execute();
            // $stmt_unassign->close();
            
            // Delete the team
            $sql_delete = "DELETE FROM tblTeam WHERE teamID = ?";
            if ($stmt_delete = $mysqli->prepare($sql_delete)) {
                $stmt_delete->bind_param("i", $team_id_to_edit);
                if ($stmt_delete->execute()) {
                    if ($stmt_delete->affected_rows > 0) {
                         $mysqli->commit();
                         $_SESSION['success_message'] = "Team successfully deleted.";
                    } else {
                         $mysqli->rollback(); // Rollback if team wasn't found
                         $_SESSION['error_message'] = "Team not found or already deleted.";
                    }
                } else {
                     $mysqli->rollback();
                     $_SESSION['error_message'] = "Error deleting team.";
                     error_log("Error executing delete team query for TID {$team_id_to_edit}: " . $stmt_delete->error);
                }
                $stmt_delete->close();
            } else {
                 $mysqli->rollback();
                 $_SESSION['error_message'] = "Error preparing delete statement.";
                 error_log("Error preparing delete team query: " . $mysqli->error);
            }
        } catch (Exception $e) {
             $mysqli->rollback();
             $_SESSION['error_message'] = "An error occurred during deletion.";
             error_log("Team Deletion Transaction Error: " . $e->getMessage());
        }
        
        $mysqli->close();
        header("location: admin_team_management.php");
        exit;
    }

    // --- Handle SAVE action (Validation) ---
    elseif ($action === 'save') {
        // Validate Team Name
        if (empty(trim($_POST["teamName"]))) { 
            $errors['name'] = "Please enter a team name."; 
        } else { 
            $team_name = trim($_POST["teamName"]); 
            // Check if team name already exists FOR ANOTHER TEAM
            $sql_check_name = "SELECT teamID FROM tblTeam WHERE teamName = ? AND teamID != ?";
            if ($stmt_check = $mysqli->prepare($sql_check_name)) {
                $stmt_check->bind_param("si", $team_name, $team_id_to_edit);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows > 0) {
                    $errors['name'] = "This team name already exists.";
                }
                $stmt_check->close();
            } else {
                 $errors['db'] = "Database error checking team name.";
                 error_log("Error preparing team name check query: " . $mysqli->error);
            }
        }

        // Get Primary Program ID (optional)
        $program_id_input = trim($_POST["primaryProgram"]);
        if (!empty($program_id_input)) {
            if (filter_var($program_id_input, FILTER_VALIDATE_INT)) {
                 $program_id = (int)$program_id_input;
                 // Optional: Check if program ID exists
            } else {
                $errors['program'] = "Invalid program selected.";
                $program_id = null;
            }
        } else {
            $program_id = null; // Explicitly set to null if empty
        }

        // --- If no validation errors, proceed to update database ---
        if (empty($errors)) {
            
            // Prepare UPDATE statement for the team
            $sql_update = "UPDATE tblTeam SET teamName = ?, TrainingProgramID = ? WHERE teamID = ?";
            
            if ($stmt_update = $mysqli->prepare($sql_update)) {
                // Bind variables (s = string, i = integer)
                $stmt_update->bind_param("sii", $team_name, $program_id, $team_id_to_edit);

                if ($stmt_update->execute()) {
                    if ($stmt_update->affected_rows >= 0) { 
                         $_SESSION['success_message'] = "Team '" . htmlspecialchars($team_name) . "' updated successfully!";
                         header("location: admin_team_management.php"); 
                         exit;
                    } else {
                         $errors['db'] = "No changes were made to the team.";
                    }
                } else {
                     $errors['db'] = "Error updating team record.";
                     error_log("Error updating team (TID: {$team_id_to_edit}): " . $stmt_update->error);
                }
                $stmt_update->close();
            } else {
                 $errors['db'] = "Error preparing team update statement.";
                 error_log("Error preparing team update query: " . $mysqli->error);
            }
        } 

        // --- If there were errors during save, redirect back ---
        if (!empty($errors)) {
            $_SESSION['edit_form_errors'] = $errors;
            $_SESSION['edit_form_data'] = $_POST; // Store submitted data
            header("location: admin_edit_team.php?tid=" . $team_id_to_edit);
            exit;
        }

    } // End elseif ($action === 'save')

    // Close connection
    $mysqli->close();

} else {
    // If not a PsOST request, redirect
    $_SESSION['error_message'] = "Invalid request method.";
    header("location: admin_team_management.php");
    exit;
}
?>
