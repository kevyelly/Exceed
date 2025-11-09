<?php
// select_role.php

// --- FOR DEBUGGING ONLY: REMOVE FOR PRODUCTION ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
// --- END DEBUGGING ---

session_start();

// --- Authentication Check: Ensure user is logged in ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // If not logged in, redirect to login page
    header("location: login.php");
    exit;
}

// --- Role Check ---
// Retrieve role flags from session, defaulting to false if not set
$isTrainer = isset($_SESSION["is_trainer"]) && $_SESSION["is_trainer"] === true;
$isTrainee = isset($_SESSION["is_trainee"]) && $_SESSION["is_trainee"] === true;
$isAdmin = isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] === true;

// 1. If user is an Admin, they should go to the admin dashboard directly.
// This check should ideally be handled by process_login.php before redirecting here.
if ($isAdmin) {
    header("location: admin_dashboard.php");
    exit;
}

// 2. This page is specifically for users who are BOTH a Trainer AND a Trainee.
// If they are not BOTH, they should be redirected to their primary role's dashboard
// or back to login if they somehow have no valid role flags set.
if (!($isTrainer && $isTrainee)) {
    if ($isTrainer) { // Only a Trainer (and not Trainee or Admin)
        header("location: trainer_dashboard.php");
        exit;
    } elseif ($isTrainee) { // Only a Trainee (and not Trainer or Admin)
        header("location: trainee_dashboard.php");
        exit;
    } else {
        // Logged in, but not Admin, and not (Trainer AND Trainee).
        // This indicates an unusual state or that they only have one of these roles.
        error_log("User (ID: ".($_SESSION["user_id"] ?? 'Unknown').") reached select_role.php without the expected dual trainer/trainee roles (and is not admin). isTrainer: " . ($isTrainer?'1':'0') . ", isTrainee: " . ($isTrainee?'1':'0'));
        // Fallback: If they are logged in but don't fit any specific dashboard via the above,
        // sending them to login might be confusing. Consider a generic "My Account" page or index.
        // For now, redirecting to login to ensure they don't get stuck on a blank page.
        $_SESSION['login_error'] = "Your role configuration is unclear. Please contact support.";
        header("location: login.php"); 
        exit;
    }
}

// If we reach here, the user is logged in, is not an admin, AND is both a trainer and a trainee.
$user_fname = $_SESSION["user_fname"] ?? 'User';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXCEED - Select Your Role</title>
    <style>
        /* Basic Styling - You can link your main styles.css if preferred */
        :root { 
            --primary-color: #01c892;
            --text-color: #213547;
            --bg-color: #f8faf9;
            --card-bg: #ffffff;
            --border-color: #e5e7eb;
            --hover-color: #535bf2; /* Added for btn-secondary */
        }
        body { 
            font-family: Inter, system-ui, -apple-system, sans-serif; 
            line-height: 1.6; 
            color: var(--text-color); 
            background-color: var(--bg-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
        }
        .role-selection-container {
            background-color: var(--card-bg);
            padding: 2.5rem 3rem;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .role-selection-container h1 {
            color: var(--primary-color);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .role-selection-container h2 {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            color: var(--text-color);
            font-weight: 600;
        }
        .role-selection-container p {
            font-size: 1rem;
            color: #556a7e;
            margin-bottom: 2rem;
        }
        .role-buttons {
            display: flex;
            flex-direction: column; /* Stack buttons on small screens */
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .role-buttons a.btn {
            display: block;
            padding: 0.85em 1.5em;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s;
            text-align: center;
        }
        .role-buttons a.btn:hover {
            background-color: #00a97a; /* Darker shade */
        }
        .logout-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: var(--text-color);
            font-size: 0.9rem;
            text-decoration: none;
        }
        .logout-link:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }

        @media (min-width: 480px) { /* Side-by-side buttons on slightly larger screens */
            .role-buttons {
                flex-direction: row;
                justify-content: center;
            }
             .role-buttons a.btn {
                flex: 1; /* Allow buttons to share space */
                max-width: 200px; /* Limit button width */
            }
        }

    </style>
</head>
<body>
    <div class="role-selection-container">
        <h1>EXCEED</h1>
        <h2>Welcome, <?php echo htmlspecialchars($user_fname); ?>!</h2>
        <p>You have multiple roles. Please select how you'd like to proceed for this session:</p>

        <div class="role-buttons">
            <a href="trainer_dashboard.php" class="btn">Continue as Trainer</a>
            <a href="trainee_dashboard.php" class="btn">Continue as Trainee</a>
        </div>
        
        <a href="logout.php" class="logout-link">Logout</a>
    </div>
</body>
</html>
