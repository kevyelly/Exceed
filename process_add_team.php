<?php
// process_add_team.php
session_start(); 

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true) {
    $_SESSION['error_message'] = "Access denied. Please log in as an administrator.";
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in process_add_team.php.");
     $_SESSION['error_message'] = "Database configuration error. Cannot process request.";
     header("location: admin_team_management.php"); 
     exit;
}

// --- Define variables and initialize ---
$team_name = "";
$program_id = null; 
$errors = []; 

// --- Processing form data when form is submitted ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate Team Name
    if (empty(trim($_POST["teamName"]))) { 
        $errors['name'] = "Please enter a team name."; 
    } else { 
        $team_name = trim($_POST["teamName"]); 
        // Check if team name already exists
        $sql_check_name = "SELECT teamID FROM tblTeam WHERE teamName = ?";
        if ($stmt_check = $mysqli->prepare($sql_check_name)) {
            $stmt_check->bind_param("s", $team_name);
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
             // $sql_check_prog = "SELECT trainingProgramID FROM tblTrainingProgram WHERE trainingProgramID = ?"; ...
        } else {
            $errors['program'] = "Invalid program selected.";
            $program_id = null;
        }
    } else {
        $program_id = null; // Explicitly set to null if empty
    }
    
    // --- If no validation errors, proceed to insert into database ---
    if (empty($errors)) {
        
        // Prepare INSERT statement for the team
        $sql_insert = "INSERT INTO tblTeam (teamName, TrainingProgramID) VALUES (?, ?)";
        
        if ($stmt_insert = $mysqli->prepare($sql_insert)) {
            // Bind variables (s = string, i = integer)
            // TrainingProgramID can be NULL, bind_param handles this if $program_id is null.
            $stmt_insert->bind_param("si", $team_name, $program_id);

            if ($stmt_insert->execute()) {
                $new_team_id = $mysqli->insert_id; 

                // --- Placeholder for Member Assignment Logic ---
                // If you had checkboxes for members (e.g., name="assign_members[]")
                // $assigned_members = $_POST['assign_members'] ?? [];
                // foreach ($assigned_members as $member_uid) {
                //     $sql_assign = "UPDATE tblTrainee SET TeamID = ? WHERE UID = ?";
                //     // Prepare, bind, execute assignment...
                // }
                // --- End Placeholder ---

                $_SESSION['success_message'] = "Team '" . htmlspecialchars($team_name) . "' created successfully!";
                header("location: admin_team_management.php"); 
                exit;
            } else {
                 $errors['db'] = "Error creating team record.";
                 error_log("Error inserting team: " . $stmt_insert->error);
            }
            $stmt_insert->close();
        } else {
             $errors['db'] = "Error preparing team insert statement.";
             error_log("Error preparing team insert query: " . $mysqli->error);
        }
    } 

    // --- If there were errors, redirect back ---
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $_POST; 
        header("location: admin_add_team.php"); 
        exit;
    }

    // Close connection
    $mysqli->close();

} else {
    // If not a POST request, redirect
    $_SESSION['error_message'] = "Invalid request method.";
    header("location: admin_team_management.php");
    exit;
}
?>
