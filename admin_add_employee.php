<?php
// admin_add_employee.php
session_start(); 

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true) {
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in admin_add_employee.php.");
     die("Database configuration error. Please contact support."); 
}

// --- Pre-fetch data for dropdowns ---
$teams_for_options = [];
$sql_teams_options = "SELECT teamID, teamName FROM tblTeam ORDER BY teamName";
if($result_teams_options = $mysqli->query($sql_teams_options)){
    while($team_option_row = $result_teams_options->fetch_assoc()){
        $teams_for_options[] = $team_option_row;
    }
    $result_teams_options->free();
} else {
    error_log("Error fetching teams for options: " . $mysqli->error);
}

// --- Get potential errors and form data from session if redirected back ---
$errors = $_SESSION['form_errors'] ?? [];
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']); // Clear after retrieving

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXCEED - Add New Employee</title>
    <style>
        /* START: Core Variables & Base Styles (Should match your styles.css) */
        :root { --primary-color: #01c892; --text-color: #213547; --bg-color: #f8faf9; --card-bg: #ffffff; --border-color: #e5e7eb; --hover-color: #535bf2; --danger-color: #ef4444; --warning-color: #f59e0b; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Inter, system-ui, -apple-system, sans-serif; line-height: 1.5; color: var(--text-color); background-color: var(--bg-color); }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 1rem; }
        .btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6em 1.2em; background-color: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #00b583; }
        .btn-secondary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5em 1em; background-color: #e0e7ff; color: var(--hover-color); border: 1px solid transparent; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: background-color 0.3s; }
        .btn-secondary:hover { background-color: #c7d2fe; }
        .btn-sm { padding: 0.4em 0.8em; font-size: 0.8rem; }
        
        .dashboard { min-height: 100vh; background-color: var(--bg-color); }
        .dashboard-nav { background: var(--card-bg); box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1rem 0; margin-bottom: 2rem; }
        .dashboard-main { padding: 0 1rem 2rem 1rem; }
        .card { background: var(--card-bg); padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom:1.5rem; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color); }
        .card-header h3, .page-header h1 { margin: 0; font-size: 1.5rem; color: var(--text-color); display: flex; align-items: center; gap: 0.5rem; }
        .card-header h3 svg, .page-header h1 svg { width: 24px; height: 24px; color: var(--primary-color); }
        .page-header { margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        /* END: Core Styles */

        /* Page Specific Styles */
        .admin-nav-content { width: 100%; display: flex; justify-content: space-between; align-items: center; }
        .admin-nav-content .app-title { font-size: 1.5rem; font-weight: bold; color: var(--primary-color); text-decoration: none; }
        .admin-nav-content .nav-links a { margin-left: 1.5rem; text-decoration: none; color: var(--text-color); font-weight: 500;}
        .admin-nav-content .nav-links a:hover { color: var(--primary-color); }
        
        .form-container { max-width: 700px; margin: 0 auto; } /* Center the form card */
        
        .form-field-group { margin-bottom: 1.25rem; } /* Increased spacing */
        .form-field-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9rem; }
        .form-field-group input[type="text"], .form-field-group input[type="email"], .form-field-group input[type="password"], .form-field-group textarea, .form-field-group select { padding: 0.6rem 0.85rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; width: 100%; background-color: #fff; }
        .form-field-group input.is-invalid, .form-field-group select.is-invalid { border-color: var(--danger-color); } /* Style for invalid fields */
        .form-field-group .error-text { font-size: 0.8rem; color: var(--danger-color); margin-top: 0.25rem; display: block; } /* Error text style */
        .checkbox-group label { font-weight: normal; display: inline-flex; align-items: center; gap: 0.5rem; margin-right: 1rem; font-size: 0.9rem;}
        .checkbox-group input[type="checkbox"] { width: auto; margin-right: 0.25rem; }
        
        .form-actions { margin-top: 2rem; display: flex; justify-content: flex-end; gap: 1rem; }
        
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; border: 1px solid transparent; font-size: 0.95rem;}
        .alert-danger { background-color: #fee2e2; color: #991b1b; border-color: #fca5a5;}


        @media (max-width: 768px) {
            .admin-nav-content { flex-direction: column; align-items: flex-start; gap:1rem; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 1rem;}
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
                        <a href="#">Programs</a> 
                        <a href="#">Schedules</a> 
                        <a href="#">Teams</a> 
                        <a href="#">Reports</a> 
                    </div>
                    <a href="logout.php" class="btn-secondary btn-sm">Logout</a> 
                </div>
            </div>
        </nav>

        <main class="container dashboard-main">
            <div class="page-header">
                <h1>Add New Employee</h1>
                 <a href="admin_user_management.php" class="btn-secondary">Back to User List</a>
            </div>

            <div class="card form-container">
                
                <?php if (isset($errors['db'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db']); ?></div>
                <?php endif; ?>

                <form id="addEmployeeForm" action="process_add_employee.php" method="POST" novalidate> <div class="form-field-group">
                        <label for="empFirstName">First Name:</label>
                        <input type="text" id="empFirstName" name="empFirstName" required 
                               value="<?php echo htmlspecialchars($form_data['empFirstName'] ?? ''); ?>"
                               class="<?php echo isset($errors['fname']) ? 'is-invalid' : ''; ?>">
                        <?php if (isset($errors['fname'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['fname']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-field-group">
                        <label for="empLastName">Last Name:</label>
                        <input type="text" id="empLastName" name="empLastName" required
                               value="<?php echo htmlspecialchars($form_data['empLastName'] ?? ''); ?>"
                               class="<?php echo isset($errors['lname']) ? 'is-invalid' : ''; ?>">
                        <?php if (isset($errors['lname'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['lname']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-field-group">
                        <label for="empEmail">Email:</label>
                        <input type="email" id="empEmail" name="empEmail" required
                               value="<?php echo htmlspecialchars($form_data['empEmail'] ?? ''); ?>"
                               class="<?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>">
                         <?php if (isset($errors['email'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['email']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-field-group">
                        <label for="empPassword">Password:</label>
                        <input type="password" id="empPassword" name="empPassword" required
                               class="<?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>">
                        <small style="font-size:0.8rem; color:#666;">Min. 8 characters.</small>
                        <?php if (isset($errors['password'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['password']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-field-group">
                        <label for="empConfirmPassword">Confirm Password:</label>
                        <input type="password" id="empConfirmPassword" name="empConfirmPassword" required
                               class="<?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>">
                        <?php if (isset($errors['confirm_password'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['confirm_password']); ?></span>
                        <?php endif; ?>
                    </div>

                    <hr style="margin: 1.5rem 0;">

                    <div class="form-field-group">
                        <label for="empPosition">Position:</label>
                        <input type="text" id="empPosition" name="empPosition"
                               value="<?php echo htmlspecialchars($form_data['empPosition'] ?? ''); ?>">
                         </div>

                    <div class="form-field-group">
                        <label for="empTeam">Assign to Team:</label>
                        <select id="empTeam" name="empTeam">
                            <option value="">-- Select Team --</option>
                             <?php if (!empty($teams_for_options)): ?>
                                <?php foreach ($teams_for_options as $team_opt): ?>
                                    <option value="<?php echo htmlspecialchars($team_opt['teamID']); ?>" 
                                            <?php echo (isset($form_data['empTeam']) && $form_data['empTeam'] == $team_opt['teamID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team_opt['teamName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No teams available</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <hr style="margin: 1.5rem 0;">

                    <div class="form-field-group">
                        <label>Assign Roles:</label>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="isTraineeFlag" value="1" 
                                <?php echo (isset($form_data['isTraineeFlag']) && $form_data['isTraineeFlag'] == '1') ? 'checked' : ''; ?>> Trainee</label>
                        </div>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="isTrainerFlag" value="1"
                                <?php echo (isset($form_data['isTrainerFlag']) && $form_data['isTrainerFlag'] == '1') ? 'checked' : ''; ?>> Trainer</label>
                        </div>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="isAdminFlag" value="1"
                                <?php echo (isset($form_data['isAdminFlag']) && $form_data['isAdminFlag'] == '1') ? 'checked' : ''; ?>> Admin</label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="admin_user_management.php" class="btn-secondary">Cancel</a>
                        <button type="submit" class="btn-primary">Create Employee</button>
                    </div>
                </form>
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
