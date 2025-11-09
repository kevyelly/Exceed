<?php
// process_add_program.php
session_start(); 

// --- Authentication Check (Ensure only logged-in admins can access this) ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true) {
    $_SESSION['error_message'] = "Access denied. Please log in as an administrator.";
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

// Check if the database connection is valid
if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in process_add_program.php.");
     $_SESSION['error_message'] = "Database configuration error. Cannot process request.";
     // Redirect to a relevant page, maybe the dashboard or program list
     header("location: admin_program_management.php"); 
     exit;
}

// --- Define variables and initialize ---
$title = $description = "";
$trainer_id = null; // Use null for potentially empty selection
$errors = []; // Array to hold validation errors

// --- Processing form data when form is submitted ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate Title
    if (empty(trim($_POST["programTitle"]))) { 
        $errors['title'] = "Please enter a program title."; 
    } else { 
        $title = trim($_POST["programTitle"]); 
        // Optional: Check if program title already exists?
        // $sql_check_title = "SELECT trainingProgramID FROM tblTrainingProgram WHERE title = ?"; ...
    }

    // Validate Description
    if (empty(trim($_POST["programDescription"]))) { 
        $errors['description'] = "Please enter a description."; 
    } else { 
        $description = trim($_POST["programDescription"]); 
    }
    
    // Validate Trainer ID
    if (empty($_POST["assignInstructor"])) {
         // Allowing NULL trainer for now based on schema, but could make it required
         // $errors['trainer'] = "Please select an instructor."; 
         $trainer_id = null; 
    } elseif (!is_numeric($_POST["assignInstructor"])) {
         $errors['trainer'] = "Invalid instructor selection.";
         $trainer_id = null;
    } else {
        $trainer_id = (int)$_POST["assignInstructor"];
        // Optional: Check if the selected trainerID actually exists in tblTrainer
        $check_trainer_sql = "SELECT trainerID FROM tblTrainer WHERE trainerID = ?";
        if($stmt_check_t = $mysqli->prepare($check_trainer_sql)){
            $stmt_check_t->bind_param("i", $trainer_id);
            $stmt_check_t->execute();
            $stmt_check_t->store_result();
            if($stmt_check_t->num_rows == 0){
                $errors['trainer'] = "Selected instructor does not exist.";
                $trainer_id = null; // Reset trainer_id if invalid
            }
            $stmt_check_t->close();
        } else {
            $errors['db'] = "Error verifying instructor.";
            error_log("Error preparing trainer check query: " . $mysqli->error);
        }
    }
    
    // Get enrolled teams/trainees (Checkbox arrays)
    $enroll_teams = $_POST['enroll_teams'] ?? []; 
    // Note: Processing enrollment is skipped in this basic script. 
    // You would loop through $enroll_teams and update tblTeam or insert into a linking table.

    // --- If no validation errors, proceed to insert into database ---
    if (empty($errors)) {
        
        // Prepare INSERT statement for the program
        $sql_insert = "INSERT INTO tblTrainingProgram (title, description, TrainerID) VALUES (?, ?, ?)";
        
        if ($stmt_insert = $mysqli->prepare($sql_insert)) {
            // Bind variables (s = string, i = integer)
            // TrainerID can be NULL, bind_param handles this if $trainer_id is null.
            $stmt_insert->bind_param("ssi", $title, $description, $trainer_id);

            if ($stmt_insert->execute()) {
                $new_program_id = $mysqli->insert_id; // Get the ID of the new program

                // --- Placeholder for Enrollment Logic ---
                // If you were handling enrollment here, you'd loop through $enroll_teams
                // and potentially run UPDATE queries on tblTeam or INSERT into a linking table.
                // Example (Conceptual - Needs proper table structure/logic):
                // foreach ($enroll_teams as $team_id_to_enroll) {
                //    $sql_enroll = "UPDATE tblTeam SET TrainingProgramID = ? WHERE teamID = ?";
                //    // Prepare, bind, execute enrollment update...
                // }
                // --- End Placeholder ---

                $_SESSION['success_message'] = "Training program '" . htmlspecialchars($title) . "' created successfully!";
                header("location: admin_program_management.php"); 
                exit;
            } else {
                 $errors['db'] = "Error creating program record.";
                 error_log("Error inserting program: " . $stmt_insert->error);
            }
            $stmt_insert->close();
        } else {
             $errors['db'] = "Error preparing program insert statement.";
             error_log("Error preparing program insert query: " . $mysqli->error);
        }
    } 

    // --- If there were errors, redirect back ---
    // Storing errors in session to display them back on the form page is complex
    // without JavaScript or redirecting to a dedicated add page.
    // For now, we'll just redirect with a generic error message.
    if (!empty($errors)) {
        // Combine errors into a single message for simplicity
        $error_message_string = "Failed to add program: ";
        $error_details = [];
        foreach($errors as $field => $msg) {
            $error_details[] = htmlspecialchars($msg);
        }
        $error_message_string .= implode(" ", $error_details);
        
        $_SESSION['error_message'] = $error_message_string;
        // Redirect back to the page where the modal likely is
        header("location: admin_program_management.php"); 
        exit;
    }

    // Close connection
    $mysqli->close();

} else {
    // If not a POST request, redirect
    $_SESSION['error_message'] = "Invalid request method.";
    header("location: admin_program_management.php");
    exit;
}
?>
