<?php
// admin_edit_program.php
session_start(); 

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true) {
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in admin_edit_program.php.");
     // Provide a user-friendly message or redirect
     die("Database configuration error. Please contact support or try again later."); 
}

// --- Get Program ID from URL ---
$program_id_to_edit = null;
if (isset($_GET['pid']) && filter_var($_GET['pid'], FILTER_VALIDATE_INT)) { // Validate as integer
    $program_id_to_edit = (int)$_GET['pid'];
} 

// Redirect if no valid PID is provided
if ($program_id_to_edit === null) {
    $_SESSION['error_message'] = "Invalid program specified for editing.";
    header("location: admin_program_management.php");
    exit;
}

// --- Fetch Program Data for the specified PID ---
$program_data = null;
$sql_get_program = "SELECT 
                        tp.trainingProgramID, tp.title, tp.description, tp.TrainerID
                    FROM tblTrainingProgram tp
                    WHERE tp.trainingProgramID = ?";

if ($stmt_get_program = $mysqli->prepare($sql_get_program)) {
    $stmt_get_program->bind_param("i", $program_id_to_edit);
    if ($stmt_get_program->execute()) {
        $result = $stmt_get_program->get_result();
        if ($result->num_rows === 1) {
            $program_data = $result->fetch_assoc();
        } else {
            // Program ID doesn't exist in the database
            $_SESSION['error_message'] = "Training program not found.";
            header("location: admin_program_management.php");
            exit;
        }
        $result->free(); // Free result set
    } else {
        error_log("Error executing program fetch query (PID: {$program_id_to_edit}): " . $stmt_get_program->error);
        $_SESSION['error_message'] = "Error retrieving program data.";
        header("location: admin_program_management.php");
        exit;
    }
    $stmt_get_program->close();
} else {
    error_log("Error preparing program fetch query: " . $mysqli->error);
    $_SESSION['error_message'] = "Database error retrieving program.";
    header("location: admin_program_management.php");
    exit;
}

// --- Fetch trainers for dropdown ---
$trainers_for_dropdown = [];
$sql_trainers_dropdown = "SELECT t.trainerID, u.fname, u.lname FROM tblTrainer t JOIN tblUser u ON t.UID = u.UID ORDER BY u.lname, u.fname";
if($result_trainers_dropdown = $mysqli->query($sql_trainers_dropdown)){
    while($trainer_row = $result_trainers_dropdown->fetch_assoc()){
        $trainers_for_dropdown[] = $trainer_row;
    }
    $result_trainers_dropdown->free();
} else {
    error_log("Error fetching trainers for edit program dropdown: " . $mysqli->error);
    // Non-fatal error, dropdown might be empty
}

// --- Get potential errors and form data from session if redirected back ---
$errors = $_SESSION['edit_form_errors'] ?? [];
// Use session data if it exists (validation failed), otherwise use fetched program data
$form_data = $_SESSION['edit_form_data'] ?? $program_data; 
unset($_SESSION['edit_form_errors'], $_SESSION['edit_form_data']); 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXCEED - Edit Training Program</title>
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
        
        .form-container { max-width: 700px; margin: 0 auto; } 
        
        .form-field-group { margin-bottom: 1.25rem; } 
        .form-field-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9rem; }
        .form-field-group input[type="text"], .form-field-group input[type="email"], .form-field-group input[type="password"], .form-field-group textarea, .form-field-group select { padding: 0.6rem 0.85rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; width: 100%; background-color: #fff; }
        .form-field-group textarea { min-height: 100px; }
        .form-field-group input.is-invalid, .form-field-group select.is-invalid, .form-field-group textarea.is-invalid { border-color: var(--danger-color); } 
        .form-field-group .error-text { font-size: 0.8rem; color: var(--danger-color); margin-top: 0.25rem; display: block; } 
        
        .form-actions { margin-top: 2rem; display: flex; justify-content: space-between; gap: 1rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem; }
        .form-actions .right-actions { display: flex; gap: 1rem; }

        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; border: 1px solid transparent; font-size: 0.95rem;}
        .alert-danger { background-color: #fee2e2; color: #991b1b; border-color: #fca5a5;}

        @media (max-width: 992px) { 
             .admin-nav-content .nav-links { display: none; }
        }
        @media (max-width: 768px) {
            .admin-nav-content { flex-direction: column; align-items: flex-start; gap:1rem; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 1rem;}
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
                        <a href="#">Teams</a> 
                        <a href="#">Reports</a> 
                    </div>
                    <a href="logout.php" class="btn-secondary btn-sm">Logout</a> 
                </div>
            </div>
        </nav>

        <main class="container dashboard-main">
            <div class="page-header">
                <h1>Edit Program: <?php echo htmlspecialchars($form_data['title'] ?? 'Program'); ?></h1>
                 <a href="admin_program_management.php" class="btn-secondary">Back to Program List</a>
            </div>

            <div class="card form-container">
                
                 <?php if (isset($errors['db'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db']); ?></div>
                <?php endif; ?>

                <form id="editProgramForm" action="process_edit_program.php" method="POST" novalidate>
                    <input type="hidden" name="programId" value="<?php echo htmlspecialchars($program_id_to_edit); ?>">
                    
                    <div class="form-field-group">
                        <label for="programTitle">Program Title:</label>
                        <input type="text" id="programTitle" name="programTitle" required 
                               value="<?php echo htmlspecialchars($form_data['title'] ?? ''); ?>"
                               class="<?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>">
                        <?php if (isset($errors['title'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['title']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-field-group">
                        <label for="programDescription">Description:</label>
                        <textarea id="programDescription" name="programDescription" required
                                  class="<?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>"><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                        <?php if (isset($errors['description'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['description']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-field-group">
                        <label for="assignInstructor">Assign Instructor:</label>
                        <select id="assignInstructor" name="assignInstructor" 
                                class="<?php echo isset($errors['trainer']) ? 'is-invalid' : ''; ?>">
                            <option value="">-- Select Instructor (Optional) --</option> <?php if (!empty($trainers_for_dropdown)): ?>
                                <?php foreach ($trainers_for_dropdown as $trainer_opt): ?>
                                    <option value="<?php echo htmlspecialchars($trainer_opt['trainerID']); ?>" 
                                            <?php 
                                                // Check if form data exists (from failed validation) OR use fetched data
                                                $selected_trainer = $form_data['assignInstructor'] ?? $form_data['TrainerID'] ?? null;
                                                echo ($selected_trainer == $trainer_opt['trainerID']) ? 'selected' : ''; 
                                            ?>>
                                        <?php echo htmlspecialchars($trainer_opt['fname'] . ' ' . $trainer_opt['lname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No trainers available</option>
                            <?php endif; ?>
                        </select>
                         <?php if (isset($errors['trainer'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['trainer']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
                         <button type="submit" name="delete_program" value="1" class="btn-danger" 
                                 onclick="return confirm('Are you sure you want to permanently delete this program? This may affect schedules and enrollments. This action cannot be undone.');">
                             Delete Program
                         </button>
                         <div class="right-actions">
                            <a href="admin_program_management.php" class="btn-secondary">Cancel</a>
                            <button type="submit" name="save_changes" value="1" class="btn-primary">Save Changes</button>
                         </div>
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
