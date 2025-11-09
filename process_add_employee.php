<?php
// process_add_employee.php
session_start(); 

// --- Authentication Check (Ensure only logged-in admins can access this) ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true) {
    // Store an error message and redirect to login if not authorized
    $_SESSION['error_message'] = "Access denied. Please log in as an administrator.";
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

// Check if the database connection is valid
if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in process_add_employee.php.");
     // Set a generic error message for the user
     $_SESSION['error_message'] = "Database configuration error. Cannot process request.";
     // It's better to redirect back to the form page or a user management page
     // header("location: admin_add_employee.php"); 
     header("location: admin_user_management.php"); 
     exit;
}

// --- Define variables and initialize with empty values ---
$fname = $lname = $email = $password = $confirm_password = $position = "";
$team_id = null; // Use null for potentially empty selection
$isTrainee = $isTrainer = $isAdmin = false;
$errors = []; // Array to hold validation errors

// --- Processing form data when form is submitted ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate First Name
    if (empty(trim($_POST["empFirstName"]))) {
        $errors['fname'] = "Please enter a first name.";
    } else {
        $fname = trim($_POST["empFirstName"]);
    }

    // Validate Last Name
    if (empty(trim($_POST["empLastName"]))) {
        $errors['lname'] = "Please enter a last name.";
    } else {
        $lname = trim($_POST["empLastName"]);
    }

    // Validate Email
    if (empty(trim($_POST["empEmail"]))) {
        $errors['email'] = "Please enter an email address.";
    } elseif (!filter_var(trim($_POST["empEmail"]), FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address.";
    } else {
        $email = trim($_POST["empEmail"]);
        // Check if email already exists
        $sql_check_email = "SELECT UID FROM tblUser WHERE email = ?";
        if ($stmt_check = $mysqli->prepare($sql_check_email)) {
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors['email'] = "This email address is already registered.";
            }
            $stmt_check->close();
        } else {
             $errors['db'] = "Database error checking email.";
             error_log("Error preparing email check query: " . $mysqli->error);
        }
    }

    // Validate Password
    if (empty(trim($_POST["empPassword"]))) {
        $errors['password'] = "Please enter a password.";
    } elseif (strlen(trim($_POST["empPassword"])) < 8) {
        $errors['password'] = "Password must have at least 8 characters.";
    } else {
        $password = trim($_POST["empPassword"]);
    }

    // Validate Confirm Password
    if (empty(trim($_POST["empConfirmPassword"]))) {
        $errors['confirm_password'] = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["empConfirmPassword"]);
        if (empty($errors['password']) && ($password != $confirm_password)) {
            $errors['confirm_password'] = "Passwords did not match.";
        }
    }

    // Get Position (optional)
    $position = trim($_POST["empPosition"]);

    // Get Team ID (optional)
    // Ensure empty string selection becomes NULL for the database
    $team_id_input = trim($_POST["empTeam"]);
    $team_id = !empty($team_id_input) ? (int)$team_id_input : null; 

    // Get Roles
    $isTrainee = isset($_POST['isTraineeFlag']) && $_POST['isTraineeFlag'] == '1';
    $isTrainer = isset($_POST['isTrainerFlag']) && $_POST['isTrainerFlag'] == '1';
    $isAdmin = isset($_POST['isAdminFlag']) && $_POST['isAdminFlag'] == '1';

    // --- If no validation errors, proceed to insert into database ---
    if (empty($errors)) {
        
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Start transaction
        $mysqli->begin_transaction();

        try {
            // 1. Insert into tblUser
            $sql_user = "INSERT INTO tblUser (fname, lname, email, password_hash, isTrainee, isTrainer, isAdmin) VALUES (?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_user = $mysqli->prepare($sql_user)) {
                $stmt_user->bind_param("ssssiii", $fname, $lname, $email, $hashed_password, $param_isTrainee, $param_isTrainer, $param_isAdmin);
                
                // Convert booleans to integers for binding
                $param_isTrainee = $isTrainee ? 1 : 0;
                $param_isTrainer = $isTrainer ? 1 : 0;
                $param_isAdmin = $isAdmin ? 1 : 0;

                if ($stmt_user->execute()) {
                    $new_user_id = $mysqli->insert_id; // Get the UID of the newly inserted user
                    
                    $success = true; // Flag to track subsequent inserts

                    // 2. Insert into tblTrainee if selected
                    if ($isTrainee) {
                        $sql_trainee = "INSERT INTO tblTrainee (UID, position, TeamID) VALUES (?, ?, ?)";
                        if ($stmt_trainee = $mysqli->prepare($sql_trainee)) {
                            // Bind parameters including potentially NULL team_id
                            $stmt_trainee->bind_param("isi", $new_user_id, $position, $team_id); 
                            if (!$stmt_trainee->execute()) {
                                $success = false;
                                $errors['db'] = "Error creating trainee record.";
                                error_log("Error inserting trainee: " . $stmt_trainee->error);
                            }
                            $stmt_trainee->close();
                        } else {
                            $success = false;
                            $errors['db'] = "Error preparing trainee statement.";
                            error_log("Error preparing trainee query: " . $mysqli->error);
                        }
                    }

                    // 3. Insert into tblTrainer if selected (and if previous steps succeeded)
                    if ($isTrainer && $success) {
                        $sql_trainer = "INSERT INTO tblTrainer (UID) VALUES (?)"; 
                        if ($stmt_trainer = $mysqli->prepare($sql_trainer)) {
                            $stmt_trainer->bind_param("i", $new_user_id);
                             if (!$stmt_trainer->execute()) {
                                $success = false;
                                $errors['db'] = "Error creating trainer record.";
                                error_log("Error inserting trainer: " . $stmt_trainer->error);
                            }
                            $stmt_trainer->close();
                        } else {
                            $success = false;
                            $errors['db'] = "Error preparing trainer statement.";
                             error_log("Error preparing trainer query: " . $mysqli->error);
                        }
                    }

                    // Commit or Rollback Transaction
                    if ($success) {
                        $mysqli->commit();
                        $_SESSION['success_message'] = "Employee '" . htmlspecialchars($fname . ' ' . $lname) . "' created successfully!";
                        header("location: admin_user_management.php"); // Redirect to user list
                        exit;
                    } else {
                        $mysqli->rollback();
                        // Error message already set in $errors['db']
                    }

                } else {
                     $errors['db'] = "Error creating user record.";
                     error_log("Error inserting user: " . $stmt_user->error);
                     $mysqli->rollback(); // Rollback on user insert failure
                }
                $stmt_user->close();
            } else {
                 $errors['db'] = "Error preparing user statement.";
                 error_log("Error preparing user query: " . $mysqli->error);
                 $mysqli->rollback(); // Rollback if prepare fails
            }

        } catch (Exception $e) {
            $mysqli->rollback();
            $errors['db'] = "An unexpected error occurred during the transaction.";
            error_log("Transaction Error: " . $e->getMessage());
        }
    }

    // --- If there were errors, redirect back to the form ---
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        // Store submitted values to repopulate form (be careful with password)
        $_SESSION['form_data'] = $_POST; 
        unset($_SESSION['form_data']['empPassword'], $_SESSION['form_data']['empConfirmPassword']); // Don't send password back

        header("location: admin_add_employee.php");
        exit;
    }

    // Close connection
    $mysqli->close();

} else {
    // If not a POST request, redirect to dashboard or login
    $_SESSION['error_message'] = "Invalid request method.";
    header("location: admin_dashboard.php");
    exit;
}
?>
