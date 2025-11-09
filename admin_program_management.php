<?php
// admin_program_management.php
session_start(); 

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true) {
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in admin_program_management.php.");
     // Display a user-friendly error or redirect
     die("Database configuration error. Please contact support or try again later."); 
}

// --- Get success/error messages from session ---
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']); // Clear after retrieving

// --- Fetch Data ---
$page_error = null; // Initialize page error

// Fetch All Programs
$programs = [];
$sql_programs = "SELECT 
                    tp.trainingProgramID, tp.title, tp.description, 
                    CONCAT(u.fname, ' ', u.lname) AS trainerName,
                    'Active' AS status -- Placeholder status
                 FROM tblTrainingProgram tp
                 LEFT JOIN tblTrainer tr ON tp.TrainerID = tr.trainerID
                 LEFT JOIN tblUser u ON tr.UID = u.UID
                 ORDER BY tp.title"; 

if($result_programs = $mysqli->query($sql_programs)){
    if($result_programs->num_rows > 0){
        while($row = $result_programs->fetch_assoc()){
            // Fetch team count or enrolled count if needed (simplified placeholder)
            $row['enrolledCount'] = 0; // Placeholder - requires more complex query
            $programs[] = $row; 
        }
        $result_programs->free();
    }
    // No error message if no programs found, just show empty table
} else {
    error_log("Error fetching programs for management page: " . $mysqli->error);
    $page_error = "Could not retrieve training program data due to a database error."; 
}

// Fetch trainers for Add Program Modal dropdown
$trainers_for_dropdown = [];
$sql_trainers_dropdown = "SELECT t.trainerID, u.fname, u.lname FROM tblTrainer t JOIN tblUser u ON t.UID = u.UID ORDER BY u.lname, u.fname";
if($result_trainers_dropdown = $mysqli->query($sql_trainers_dropdown)){
    while($trainer_row = $result_trainers_dropdown->fetch_assoc()){
        $trainers_for_dropdown[] = $trainer_row;
    }
    $result_trainers_dropdown->free();
} else {
    error_log("Error fetching trainers for dropdown: " . $mysqli->error);
    // Non-fatal error, modal might just lack trainers
}

// Fetch teams for Add Program Modal checkboxes
$teams_for_options = [];
$sql_teams_options = "SELECT teamID, teamName FROM tblTeam ORDER BY teamName";
if($result_teams_options = $mysqli->query($sql_teams_options)){
    while($team_option_row = $result_teams_options->fetch_assoc()){
        $teams_for_options[] = $team_option_row;
    }
    $result_teams_options->free();
} else {
    error_log("Error fetching teams for options: " . $mysqli->error);
     // Non-fatal error, modal might just lack teams
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXCEED - Program Management</title>
    <style>
        /* START: Core Variables & Base Styles (Should match your styles.css) */
        :root { --primary-color: #01c892; --text-color: #213547; --bg-color: #f8faf9; --card-bg: #ffffff; --border-color: #e5e7eb; --hover-color: #535bf2; --danger-color: #ef4444; --warning-color: #f59e0b; --success-color: #10b981; --success-bg-color: #dcfce7; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Inter, system-ui, -apple-system, sans-serif; line-height: 1.5; color: var(--text-color); background-color: var(--bg-color); }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 1rem; }
        .btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6em 1.2em; background-color: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #00b583; }
        .btn-secondary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5em 1em; background-color: #e0e7ff; color: var(--hover-color); border: 1px solid transparent; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: background-color 0.3s; }
        .btn-secondary:hover { background-color: #c7d2fe; }
        .btn-danger { background-color: var(--danger-color); color: white; padding: 0.5em 1em; border:none; border-radius:6px; font-size:0.875rem; font-weight:500; text-decoration:none; }
        .btn-danger:hover { background-color: #dc2626; }
        .btn-sm { padding: 0.4em 0.8em; font-size: 0.8rem; }
        
        .dashboard { min-height: 100vh; background-color: var(--bg-color); }
        .dashboard-nav { background: var(--card-bg); box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1rem 0; margin-bottom: 2rem; }
        .dashboard-main { padding: 0 1rem 2rem 1rem; }
        .card { background: var(--card-bg); padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom:1.5rem; }
        .page-header { margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;} 
        .page-header h1 { margin: 0; font-size: 1.5rem; color: var(--text-color); }
        /* END: Core Styles */

        /* Page Specific Styles */
        .admin-nav-content { width: 100%; display: flex; justify-content: space-between; align-items: center; }
        .admin-nav-content .app-title { font-size: 1.5rem; font-weight: bold; color: var(--primary-color); text-decoration: none; }
        .admin-nav-content .nav-links { display: flex; gap: 1.5rem; }
        .admin-nav-content .nav-links a { text-decoration: none; color: var(--text-color); font-weight: 500;}
        .admin-nav-content .nav-links a:hover { color: var(--primary-color); }
        
        .form-field-group { margin-bottom: 1rem; } 
        .form-field-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.9rem; }
        .form-field-group input[type="text"], .form-field-group input[type="email"], .form-field-group input[type="password"], .form-field-group textarea, .form-field-group select { padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.9rem; width: 100%; background-color: #fff; }
        .form-field-group textarea { min-height: 80px; }
        .checkbox-group label { font-weight: normal; display: inline-flex; align-items: center; gap: 0.5rem; margin-right: 1rem; font-size: 0.9rem;}
        .checkbox-group input[type="checkbox"] { width: auto; margin-right: 0.25rem; }

        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border-color); font-size:0.9rem; white-space: nowrap;}
        th { background: #f9fafb; font-weight: 600; color: #4b5563; }
        td.description-col { white-space: normal; max-width: 300px; overflow: hidden; text-overflow: ellipsis; } /* Allow description to wrap */
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 500; display: inline-block; margin-right: 0.25rem; }
        .status-badge.active { background: #dcfce7; color: #166534; }
        .status-badge.inactive { background: #fee2e2; color: #991b1b; }
        
        .filter-controls { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; align-items: center;}
        .filter-controls input[type="text"], .filter-controls select { padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.9rem;}
        .filter-controls input[type="text"] { flex-grow: 1; min-width: 200px;} 

        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; border: 1px solid transparent; font-size: 0.95rem;}
        .alert-success { background-color: var(--success-bg-color); color: #065f46; border-color: #6ee7b7;}
        .alert-danger { background-color: #fee2e2; color: #991b1b; border-color: #fca5a5;}

        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); display: flex; justify-content: center; align-items: center; visibility: hidden; opacity: 0; transition: opacity 0.3s ease, visibility 0s linear 0.3s; z-index: 1000; padding: 1rem; }
        .modal-overlay:target { visibility: visible; opacity: 1; transition: opacity 0.3s ease; }
        .modal-content { background-color: var(--card-bg); padding: 0; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 100%; max-width: 600px; max-height: 90vh; display: flex; flex-direction: column; transform: scale(0.9); transition: transform 0.3s ease; overflow: hidden; }
        .modal-overlay:target .modal-content { transform: scale(1); }
        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border-color); flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { margin: 0; font-size: 1.5rem; color: var(--text-color); }
        .modal-close-btn { text-decoration: none; color: #888; }
        .modal-close-btn svg { width: 24px; height: 24px; color: #888; display:block; }
        .modal-close-btn:hover svg, .modal-close-btn:hover { color: #333; }
        .modal-body { overflow-y: auto; flex: 1 1 auto; padding: 1.5rem 2rem; } 
        .modal-footer { border-top: 1px solid var(--border-color); padding: 1rem 2rem; margin-top: auto; text-align: right; flex-shrink: 0; }
        .modal-footer .btn-primary { margin-left: 0.5rem; }
        .enrollment-options { max-height: 150px; overflow-y: auto; border: 1px solid var(--border-color); padding: 0.75rem; border-radius: 6px; background-color: #f9fafb; }
        .enrollment-options .checkbox-group { display: block; margin-bottom: 0.5rem;}

        @media (max-width: 992px) { 
             .admin-nav-content .nav-links { display: none; }
        }
        @media (max-width: 768px) {
            .admin-nav-content { flex-direction: column; align-items: flex-start; gap:1rem; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 1rem;}
            .filter-controls { flex-direction: column; align-items: stretch;}
            .modal-content { max-width: 95%; } 
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
                        <a href="admin_schedule_management.php">Schedules</a> 
                        <a href="admin_team_management.php">Teams</a> 
                    </div>
                    <a href="logout.php" class="btn-secondary btn-sm">Logout</a> 
                </div>
            </div>
        </nav>

        <main class="container dashboard-main">
            <div class="page-header">
                <h1>Training Program Management</h1>
                 <a href="#add-program-modal" class="btn-primary">
                     <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add New Program
                </a>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
             <?php if (isset($page_error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($page_error); ?></div>
            <?php endif; ?>


            <div class="card">
                <div class="filter-controls">
                    <input type="text" placeholder="Search programs by title...">
                    <select>
                        <option value="">All Trainers</option>
                         <?php if (!empty($trainers_for_dropdown)): ?>
                            <?php foreach ($trainers_for_dropdown as $trainer_opt): ?>
                                <option value="<?php echo htmlspecialchars($trainer_opt['trainerID']); ?>"><?php echo htmlspecialchars($trainer_opt['fname'] . ' ' . $trainer_opt['lname']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <select>
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <button class="btn-secondary btn-sm">Apply Filters</button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Assigned Trainer</th>
                                <th>Enrolled Count</th> 
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($programs)): ?>
                                <?php foreach ($programs as $program): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($program['title']); ?></td>
                                        <td class="description-col"><?php echo htmlspecialchars($program['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($program['trainerName'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($program['enrolledCount'] ?? 0); ?></td>
                                        <td><span class="status-badge active"><?php echo htmlspecialchars($program['status']); ?></span></td> 
                                        <td>
                                            <a href="admin_edit_program.php?pid=<?php echo $program['trainingProgramID']; ?>" 
                                               class="btn-secondary btn-sm">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6">No training programs found.</td></tr> 
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                 <div style="text-align: center; padding-top: 1rem; font-size:0.9rem; color:#666;">
                    Showing 1-<?php echo count($programs); ?> of <?php echo count($programs); ?> Programs 
                </div>
            </div>
        </main>
    </div>

    <div id="add-program-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header"><h2>Add New Training Program</h2><a href="#" class="modal-close-btn" title="Close"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></a></div>
            <form id="addProgramForm" action="process_add_program.php" method="POST">
                <div class="modal-body">
                    <div class="form-field-group"><label for="programTitle">Program Title:</label><input type="text" id="programTitle" name="programTitle" required></div>
                    <div class="form-field-group"><label for="programDescription">Description:</label><textarea id="programDescription" name="programDescription" required></textarea></div>
                    <div class="form-field-group"><label for="assignInstructor">Assign Instructor:</label>
                        <select id="assignInstructor" name="assignInstructor" required>
                            <option value="">-- Select Instructor --</option>
                            <?php if (!empty($trainers_for_dropdown)): ?>
                                <?php foreach ($trainers_for_dropdown as $trainer_opt): ?>
                                    <option value="<?php echo htmlspecialchars($trainer_opt['trainerID']); ?>"><?php echo htmlspecialchars($trainer_opt['fname'] . ' ' . $trainer_opt['lname']); ?></option>
                                <?php endforeach; ?>    
                            <?php else: ?>
                                <option value="" disabled>No trainers available</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-field-group"><label>Enroll Teams:</label> 
                        <div class="enrollment-options">
                            <?php if (!empty($teams_for_options)): ?>
                                <?php foreach ($teams_for_options as $team_opt): ?>
                                    <div class='checkbox-group'><label><input type='checkbox' name='enroll_teams[]' value='<?php echo htmlspecialchars($team_opt['teamID']); ?>'> <?php echo htmlspecialchars($team_opt['teamName']); ?></label></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="font-size:0.9rem; color:#666;">No teams found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><a href="#" class="btn-secondary modal-close-btn">Cancel</a><button type="submit" class="btn-primary">Save Program</button></div>
            </form>
        </div>
    </div>

    <?php
        // Close the database connection
        if(isset($mysqli) && $mysqli instanceof mysqli) {
            $mysqli->close();
        }
    ?>
</body>
</html>
