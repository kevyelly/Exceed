<?php
// admin_edit_employee.php
session_start(); 

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true) {
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

// Initialize page error variable
$page_error = null; 

// Check if the database connection is valid
if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in admin_edit_employee.php.");
     $page_error = "Database configuration error. Cannot load page data."; 
}

// --- Get User ID from URL ---
$user_id_to_edit = null;
if (isset($_GET['uid']) && filter_var($_GET['uid'], FILTER_VALIDATE_INT)) { // Validate as integer
    $user_id_to_edit = (int)$_GET['uid'];
} 

// Redirect if no valid UID is provided
if ($user_id_to_edit === null) {
    $_SESSION['error_message'] = "Invalid user specified for editing.";
    header("location: admin_user_management.php");
    exit;
}

// --- Fetch User Data for the specified UID (Only if DB connection is okay) ---
$user_data = null;
if ($mysqli && !$page_error) {
    $sql_get_user = "SELECT 
                        u.UID, u.fname, u.lname, u.email, 
                        u.isTrainee, u.isTrainer, u.isAdmin,
                        tr.position, 
                        t.teamID 
                     FROM tblUser u
                     LEFT JOIN tblTrainee tr ON u.UID = tr.UID
                     LEFT JOIN tblTeam t ON tr.TeamID = t.teamID
                     WHERE u.UID = ?";

    if ($stmt_get_user = $mysqli->prepare($sql_get_user)) {
        $stmt_get_user->bind_param("i", $user_id_to_edit);
        if ($stmt_get_user->execute()) {
            $result = $stmt_get_user->get_result();
            if ($result->num_rows === 1) {
                $user_data = $result->fetch_assoc();
            } else {
                $_SESSION['error_message'] = "User not found.";
                header("location: admin_user_management.php");
                exit;
            }
            $result->free(); // Free result set
        } else {
            error_log("Error executing user fetch query (UID: {$user_id_to_edit}): " . $stmt_get_user->error);
            $page_error = "Error retrieving user data.";
        }
        $stmt_get_user->close();
    } else {
        error_log("Error preparing user fetch query: " . $mysqli->error);
        $page_error = "Database error retrieving user.";
    }
}

// --- Fetch teams for dropdown (Only if DB connection is okay) ---
$teams_for_options = [];
if ($mysqli && !$page_error) {
    $sql_teams_options = "SELECT teamID, teamName FROM tblTeam ORDER BY teamName";
    if($result_teams_options = $mysqli->query($sql_teams_options)){
        while($team_option_row = $result_teams_options->fetch_assoc()){
            $teams_for_options[] = $team_option_row;
        }
        $result_teams_options->free();
    } else {
        error_log("Error fetching teams for edit page options: " . $mysqli->error);
        $page_error = $page_error ? $page_error . " | Error fetching teams." : "Error fetching teams.";
    }
}

// --- Get potential errors and form data from session ---
$errors = $_SESSION['edit_form_errors'] ?? [];
// Use session data if it exists (validation failed), otherwise use fetched user data
$form_data = $_SESSION['edit_form_data'] ?? $user_data ?? []; // Default to empty array if user_data also failed
unset($_SESSION['edit_form_errors'], $_SESSION['edit_form_data']); 

// Also get general DB error from session if set by processing script
$db_error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']); 

// Prepare display name safely
$display_name = "User"; // Default
if (!empty($form_data['fname']) && !empty($form_data['lname'])) {
    $display_name = htmlspecialchars($form_data['fname'] . ' ' . $form_data['lname']);
} elseif (!empty($form_data['fname'])) {
    $display_name = htmlspecialchars($form_data['fname']);
} elseif (!empty($form_data['lname'])) {
    $display_name = htmlspecialchars($form_data['lname']);
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXCEED - Edit Employee</title>
    <style>
        /* START: Core Variables & Base Styles (Should match your styles.css) */
        :root { --primary-color: #01c892; --text-color: #213547; --bg-color: #f8faf9; --card-bg: #ffffff; --border-color: #e5e7eb; --hover-color: #535bf2; --danger-color: #ef4444; --danger-hover-color: #dc2626; --warning-color: #f59e0b; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Inter, system-ui, -apple-system, sans-serif; line-height: 1.5; color: var(--text-color); background-color: var(--bg-color); }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 1rem; }
        .btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6em 1.2em; background-color: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #00b583; }
        .btn-secondary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5em 1em; background-color: #e0e7ff; color: var(--hover-color); border: 1px solid transparent; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: background-color 0.3s; }
        .btn-secondary:hover { background-color: #c7d2fe; }
        .btn-danger { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6em 1.2em; background-color: var(--danger-color); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: background-color 0.3s; }
        .btn-danger:hover { background-color: var(--danger-hover-color); }
        .btn-sm { padding: 0.4em 0.8em; font-size: 0.8rem; }
        
        .dashboard { min-height: 100vh; background-color: var(--bg-color); }
        .dashboard-nav { background: var(--card-bg); box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1rem 0; margin-bottom: 2rem; }
        .dashboard-main { padding: 0 1rem 2rem 1rem; }
        .card { background: var(--card-bg); padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom:1.5rem; }
        .page-header { margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .page-header h1 { margin: 0; font-size: 1.5rem; color: var(--text-color); }
        /* END: Core Styles */

        /* Page Specific Styles */
        .admin-nav-content { width: 100%; display: flex; justify-content: space-between; align-items: center; }
        .admin-nav-content .app-title { font-size: 1.5rem; font-weight: bold; color: var(--primary-color); text-decoration: none; }
        .admin-nav-content .nav-links { display: flex; gap: 1.5rem; }
        .admin-nav-content .nav-links a { text-decoration: none; color: var(--text-color); font-weight: 500;}
        .admin-nav-content .nav-links a:hover { color: var(--primary-color); }
        
        .form-container { max-width: 800px; margin: 0 auto; } 
        
        .form-field-group { margin-bottom: 1.25rem; } 
        .form-field-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9rem; }
        .form-field-group input[type="text"], .form-field-group input[type="email"], .form-field-group input[type="password"], .form-field-group textarea, .form-field-group select { padding: 0.6rem 0.85rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; width: 100%; background-color: #fff; }
        .form-field-group input.is-invalid, .form-field-group select.is-invalid, .form-field-group textarea.is-invalid { border-color: var(--danger-color); } 
        .form-field-group .error-text { font-size: 0.8rem; color: var(--danger-color); margin-top: 0.25rem; display: block; } 
        .checkbox-group label { font-weight: normal; display: inline-flex; align-items: center; gap: 0.5rem; margin-right: 1rem; font-size: 0.9rem;}
        .checkbox-group input[type="checkbox"] { width: auto; margin-right: 0.25rem; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1.5rem; row-gap: 0.5rem; } 
        .form-grid .form-field-group { margin-bottom: 0; } 
        .form-grid .span-2 { grid-column: span 2; } 
        .form-grid hr { grid-column: span 2; margin: 1.5rem 0; border: 0; border-top: 1px solid var(--border-color);}
        
        .form-actions { margin-top: 2rem; display: flex; justify-content: space-between; gap: 1rem; grid-column: span 2; border-top: 1px solid var(--border-color); padding-top: 1.5rem; }
        .form-actions .right-actions { display: flex; gap: 1rem; }
        
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; border: 1px solid transparent; font-size: 0.95rem;}
        .alert-danger { background-color: #fee2e2; color: #991b1b; border-color: #fca5a5;}

        @media (max-width: 992px) { 
             .admin-nav-content .nav-links { display: none; }
        }
        @media (max-width: 768px) {
            .admin-nav-content { flex-direction: column; align-items: flex-start; gap:1rem; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 1rem;}
            .form-grid { grid-template-columns: 1fr; } 
            .form-grid .span-2 { grid-column: span 1; }
            .roles-status-group .form-field-group { grid-column: span 1; }
            .form-actions { flex-direction: column; align-items: stretch; }
            .form-actions .right-actions { justify-content: flex-end; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <nav class="dashboard-nav">
            <div class="container">
                 <div class="admin-nav-content">
                    <a href="admin_dashboard.php" class="app-title">EXCEED Admin</a>
                    <div class="nav-links">
                        <a href="admin_dashboard.php">Dashboard</a>
                        <a href="admin_user_management.php">Users</a>
                        <a href="admin_program_management.php">Programs</a> 
                        <a href="#">Schedules</a> 
                        <a href="admin_team_management.php">Teams</a> 
                        <a href="#">Reports</a> 
                    </div>
                    <a href="logout.php" class="btn-secondary btn-sm">Logout</a> 
                </div>
            </div>
        </nav>

        <main class="container dashboard-main">
            <div class="page-header">
                <h1>Edit Employee: <?php echo $display_name; ?></h1>
                 <a href="admin_user_management.php" class="btn-secondary">Back to User List</a>
            </div>

            <div class="card form-container">
                
                 <?php if (isset($errors['db'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db']); ?></div>
                <?php endif; ?>
                <?php if ($db_error_message): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($db_error_message); ?></div>
                <?php endif; ?>
                 <?php if ($page_error && !isset($errors['db'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($page_error); ?></div>
                <?php endif; ?>


                <form id="editEmployeeForm" action="process_edit_employee.php" method="POST" novalidate>
                    <input type="hidden" name="userId" value="<?php echo htmlspecialchars($user_id_to_edit); ?>">
                    
                    <div class="form-grid">
                        <div class="form-field-group">
                            <label for="empFirstName">First Name:</label>
                            <input type="text" id="empFirstName" name="empFirstName" required 
                                   value="<?php echo htmlspecialchars($form_data['fname'] ?? ''); ?>"
                                   class="<?php echo isset($errors['fname']) ? 'is-invalid' : ''; ?>">
                            <?php if (isset($errors['fname'])): ?>
                                <span class="error-text"><?php echo htmlspecialchars($errors['fname']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-field-group">
                            <label for="empLastName">Last Name:</label>
                            <input type="text" id="empLastName" name="empLastName" required
                                   value="<?php echo htmlspecialchars($form_data['lname'] ?? ''); ?>"
                                   class="<?php echo isset($errors['lname']) ? 'is-invalid' : ''; ?>">
                            <?php if (isset($errors['lname'])): ?>
                                <span class="error-text"><?php echo htmlspecialchars($errors['lname']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-field-group span-2">
                            <label for="empEmail">Email:</label>
                            <input type="email" id="empEmail" name="empEmail" required
                                   value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                                   class="<?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>">
                             <?php if (isset($errors['email'])): ?>
                                <span class="error-text"><?php echo htmlspecialchars($errors['email']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <hr class="span-2">

                        <div class="form-field-group">
                            <label for="empPosition">Position:</label>
                            <input type="text" id="empPosition" name="empPosition"
                                   value="<?php echo htmlspecialchars($form_data['position'] ?? ''); ?>">
                        </div>

                        <div class="form-field-group">
                            <label for="empTeam">Assign to Team:</label>
                            <select id="empTeam" name="empTeam">
                                <option value="">-- Select Team --</option>
                                 <?php if (!empty($teams_for_options)): ?>
                                    <?php foreach ($teams_for_options as $team_opt): ?>
                                        <option value="<?php echo htmlspecialchars($team_opt['teamID']); ?>" 
                                                <?php 
                                                    // Check form_data first (from failed validation), then fetched data
                                                    $selected_team = $form_data['empTeam'] ?? $form_data['teamID'] ?? null;
                                                    echo ($selected_team == $team_opt['teamID']) ? 'selected' : ''; 
                                                ?>>
                                            <?php echo htmlspecialchars($team_opt['teamName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No teams available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <hr class="span-2">

                        <div class="form-field-group span-2">
                             <label>Roles:</label>
                             <div class="checkbox-group">
                                <label><input type="checkbox" name="isTraineeFlag" value="1" 
                                    <?php echo (isset($form_data['isTrainee']) && $form_data['isTrainee']) ? 'checked' : ''; ?>> Trainee</label>
                            </div>
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="isTrainerFlag" value="1"
                                    <?php echo (isset($form_data['isTrainer']) && $form_data['isTrainer']) ? 'checked' : ''; ?>> Trainer</label>
                            </div>
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="isAdminFlag" value="1"
                                    <?php echo (isset($form_data['isAdmin']) && $form_data['isAdmin']) ? 'checked' : ''; ?>> Admin</label>
                            </div>
                        </div>

                        <div class="form-field-group span-2">
                            <label for="userStatus">Status:</label>
                             <select id="userStatus" name="userStatus">
                                <option value="active" <?php echo (isset($form_data['status']) && strtolower($form_data['status']) === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (isset($form_data['status']) && strtolower($form_data['status']) === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <hr class="span-2">

                        <div class="form-field-group span-2">
                            <label for="resetPassword">Reset Password (optional):</label>
                            <input type="password" id="resetPassword" name="resetPassword" placeholder="Enter new password to change">
                            <small style="font-size:0.8rem; color:#666;">Leave blank to keep current password.</small>
                              <?php if (isset($errors['password'])): ?>
                                <span class="error-text"><?php echo htmlspecialchars($errors['password']); ?></span>
                             <?php endif; ?>
                        </div>

                        <div class="form-actions span-2">
                             <button type="submit" name="delete_employee" value="1" class="btn-danger" 
                                     onclick="return confirm('Are you sure you want to permanently delete this employee? This action cannot be undone.');">
                                 Delete Employee
                             </button>
                             <div class="right-actions">
                                <a href="admin_user_management.php" class="btn-secondary">Cancel</a>
                                <button type="submit" name="save_changes" value="1" class="btn-primary">Save Changes</button>
                             </div>
                        </div>
                    </div> </form>
            </div>
        </main>
    </div>

    <?php   
        // Close the database connection
        if(isset($mysqli) && $mysqli instanceof mysqli) {
            $mysqli->close();
        }
    ?>
</body>
</html>
