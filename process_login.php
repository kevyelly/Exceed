<?php
// process_login.php
session_start(); // Start the session at the very beginning

// Include the database configuration file
require_once 'db_config.php'; // Make sure this path is correct

// Check if the database connection was successful
if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in process_login.php.");
     $_SESSION['login_error'] = "Database configuration error. Please contact support.";
     header("location: login.php");
     exit;
}

// Initialize an error message variable
$login_error = "";

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get email and password from the form
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // --- SECURITY WARNING ---
    // The following block implements a hardcoded admin login.
    // This is generally INSECURE and NOT RECOMMENDED for production environments.
    // Anyone with access to this code can log in as admin.
    // Use database authentication with hashed passwords for better security.
    // -----------------------
    if ($email === 'admin@exceed.com' && $password === '123') {
        // Hardcoded credentials match - Log in as Admin directly
        session_regenerate_id(true); // Regenerate session ID

        // Store minimal admin data in session variables
        $_SESSION["loggedin"] = true;
        $_SESSION["user_id"] = 0; // Placeholder ID for hardcoded admin
        $_SESSION["user_fname"] = "Admin"; // Default name for hardcoded admin
        $_SESSION["user_lname"] = "User";  // Default name for hardcoded admin
        $_SESSION["user_email"] = $email;
        $_SESSION["is_admin"] = true;
        $_SESSION["is_trainer"] = false; 
        $_SESSION["is_trainee"] = false;

        // Redirect directly to admin dashboard
        header("location: admin_dashboard.php");
        exit; // Stop script execution here
    }
    // --- End of Hardcoded Admin Check ---


    // If not the hardcoded admin, proceed with database authentication
    if (empty($email)) {
        $login_error = "Email is required.";
    } elseif (empty($password)) {
        $login_error = "Password is required.";
    } else {
        // Prepare SQL statement
        $sql = "SELECT UID, fname, lname, email, password_hash, isTrainee, isTrainer, isAdmin FROM tblUser WHERE email = ?";

        if ($stmt = $mysqli->prepare($sql)) {
            // Bind variables
            $stmt->bind_param("s", $param_email);
            $param_email = $email;

            // Execute
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();

                // Check if email exists
                if ($stmt->num_rows == 1) {
                    // Bind result variables
                    $stmt->bind_result($uid, $fname, $lname, $db_email, $hashed_password, $isTrainee, $isTrainer, $isAdmin);
                    if ($stmt->fetch()) {
                        // Verify password against the hash from the database
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct
                            session_regenerate_id(true); 

                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $uid;
                            $_SESSION["user_fname"] = $fname;
                            $_SESSION["user_lname"] = $lname;
                            $_SESSION["user_email"] = $db_email;
                            $_SESSION["is_admin"] = (bool)$isAdmin;
                            $_SESSION["is_trainer"] = (bool)$isTrainer;
                            $_SESSION["is_trainee"] = (bool)$isTrainee;

                            // Redirect user based on role
                            if ($_SESSION["is_admin"]) { // Admin takes precedence
                                header("location: admin_dashboard.php");
                                exit;
                            } elseif ($_SESSION["is_trainer"] && $_SESSION["is_trainee"]) {
                                // User is BOTH Trainer and Trainee (and not Admin)
                                header("location: select_role.php"); // Redirect to role selection
                                exit;
                            } elseif ($_SESSION["is_trainer"]) {
                                header("location: trainer_dashboard.php"); 
                                exit;
                            } elseif ($_SESSION["is_trainee"]) {
                                header("location: trainee_dashboard.php"); 
                                exit;
                            } else {
                                // No specific role, or an unexpected state
                                $login_error = "User role not recognized or no dashboard assigned.";
                                error_log("User UID: {$uid} logged in but has no recognized role flags set (is_admin, is_trainer, is_trainee).");
                            }
                        } else {
                            // Password is not valid
                            $login_error = "Invalid email or password.";
                        }
                    } else {
                         // Should not happen if num_rows is 1, but good practice
                         $login_error = "Failed to fetch user data after finding user.";
                         error_log("Login error: Failed to fetch user data for email {$email} after num_rows was 1.");
                    }
                } else {
                    // Email doesn't exist in DB
                    $login_error = "Invalid email or password.";
                }
            } else {
                $login_error = "Oops! Something went wrong executing the query.";
                error_log("Login query execution error: " . $stmt->error); 
            }
            // Close statement
            $stmt->close();
        } else {
            $login_error = "Oops! Something went wrong preparing the query.";
             error_log("Login query preparation error: " . $mysqli->error); 
        }
    }

    // If there was a login error (from DB check or other validation)
    if (!empty($login_error)) {
        $_SESSION['login_error'] = $login_error;
        header("location: login.php"); // Redirect back to login page
        exit;
    }

    // Close connection
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }

} else {
    // If not a POST request, redirect to login page
    $_SESSION['error_message'] = "Invalid request method."; // Optional: set an error for direct access
    header("location: login.php");
    exit;
}
?>
