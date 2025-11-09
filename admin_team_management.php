<?php
// admin_team_management.php
session_start(); 

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true) {
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in admin_team_management.php.");
     die("Database configuration error. Please contact support."); 
}

// --- Get success/error messages from session ---
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']); // Clear after retrieving

// --- Fetch Data ---
$page_error = null; 

// Fetch All Teams (Joining to get associated program title and member count)
$teams = [];
$sql_teams = "SELECT 
                t.teamID, t.teamName,
                tp.title AS primaryProgramTitle,
                (SELECT COUNT(tr.traineeID) FROM tblTrainee tr WHERE tr.TeamID = t.teamID) AS memberCount
              FROM tblTeam t
              LEFT JOIN tblTrainingProgram tp ON t.TrainingProgramID = tp.trainingProgramID
              ORDER BY t.teamName"; 

if($result_teams = $mysqli->query($sql_teams)){
    if($result_teams->num_rows > 0){
        while($row = $result_teams->fetch_assoc()){
            $teams[] = $row; 
        }
        $result_teams->free();
    }
    // No error message if no teams found, just show empty table
} else {
    error_log("Error fetching teams for management page: " . $mysqli->error);
    $page_error = "Could not retrieve team data due to a database error."; 
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXCEED - Team Management</title>
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
        
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border-color); font-size:0.9rem; white-space: nowrap;}
        th { background: #f9fafb; font-weight: 600; color: #4b5563; }
        
        .filter-controls { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; align-items: center;}
        .filter-controls input[type="text"] { padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.9rem; flex-grow: 1; min-width: 200px;} 

        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; border: 1px solid transparent; font-size: 0.95rem;}
        .alert-success { background-color: var(--success-bg-color); color: #065f46; border-color: #6ee7b7;}
        .alert-danger { background-color: #fee2e2; color: #991b1b; border-color: #fca5a5;}

        @media (max-width: 992px) { 
             .admin-nav-content .nav-links { display: none; }
        }
        @media (max-width: 768px) {
            .admin-nav-content { flex-direction: column; align-items: flex-start; gap:1rem; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 1rem;}
            .filter-controls { flex-direction: column; align-items: stretch;}
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
                <h1>Team Management</h1>
                 <a href="admin_add_team.php" class="btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add New Team
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
                    <input type="text" placeholder="Search teams by name...">
                    <button class="btn-secondary btn-sm">Apply Filters</button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Team Name</th>
                                <th>Primary Program</th>
                                <th>Member Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($teams)): ?>
                                <?php foreach ($teams as $team): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($team['teamName']); ?></td>
                                        <td><?php echo htmlspecialchars($team['primaryProgramTitle'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($team['memberCount']); ?></td>
                                        <td>
                                            <a href="admin_edit_team.php?tid=<?php echo $team['teamID']; ?>" 
                                               class="btn-secondary btn-sm">Edit</a>
                                            </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4">No teams found.</td></tr> 
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                 <div style="text-align: center; padding-top: 1rem; font-size:0.9rem; color:#666;">
                    Showing 1-<?php echo count($teams); ?> of <?php echo count($teams); ?> Teams 
                </div>
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
