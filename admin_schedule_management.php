<?php
// admin_schedule_management.php
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
     error_log("Database connection object (\$mysqli) not available in admin_schedule_management.php.");
     // Set page error instead of dying immediately, so the page structure still loads
     $page_error = "Database configuration error. Cannot load page data."; 
}

// --- Get success/error messages from session ---
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']); // Clear after retrieving

// --- Fetch Data (Only if DB connection is okay) ---
$schedules = [];
if ($mysqli && !$page_error) {
    $sql_schedules = "SELECT 
                        sl.ledgerID, 
                        tp.title AS programTitle, 
                        s.event_date, 
                        s.event_time, 
                        CONCAT(u.fname, ' ', u.lname) AS trainerName,
                        sl.status AS sessionStatus,
                        tp.trainingProgramID, 
                        s.scheduleID,
                        tr.trainerID
                     FROM tblScheduleLedger sl
                     JOIN tblTrainingProgram tp ON sl.trainingProgramID = tp.trainingProgramID
                     JOIN tblSchedule s ON sl.scheduleID = s.scheduleID
                     LEFT JOIN tblTrainer tr ON tp.TrainerID = tr.trainerID
                     LEFT JOIN tblUser u ON tr.UID = u.UID
                     ORDER BY s.event_date DESC, s.event_time DESC"; 

    if($result_schedules = $mysqli->query($sql_schedules)){
        if($result_schedules->num_rows > 0){
            while($row = $result_schedules->fetch_assoc()){
                $schedules[] = $row; 
            }
        }
        $result_schedules->free(); // Free result set even if no rows
    } else {
        error_log("Error fetching schedules for management page: " . $mysqli->error);
        $page_error = "Could not retrieve schedule data due to a database error."; 
    }
}

// Fetch trainers for filter dropdown (Only if DB connection is okay)
$trainers_for_dropdown = [];
if ($mysqli && !$page_error) {
    $sql_trainers_dropdown = "SELECT t.trainerID, u.fname, u.lname FROM tblTrainer t JOIN tblUser u ON t.UID = u.UID ORDER BY u.lname, u.fname";
    if($result_trainers_dropdown = $mysqli->query($sql_trainers_dropdown)){
        while($trainer_row = $result_trainers_dropdown->fetch_assoc()){
            $trainers_for_dropdown[] = $trainer_row;
        }
        $result_trainers_dropdown->free();
    } else {
        error_log("Error fetching trainers for dropdown: " . $mysqli->error);
        // Non-fatal error for filter
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXCEED - Schedule Management</title>
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
        
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 500; display: inline-block; margin-right: 0.25rem; text-transform: capitalize; }
        .status-badge.scheduled { background-color: #FEF9C3; color: #854D0E; } 
        .status-badge.completed { background-color: #DCFCE7; color: #166534; } 
        .status-badge.cancelled { background-color: #FEE2E2; color: #991B1B; } 
        .status-badge.inprogress { background-color: #DBEAFE; color: #1E40AF; } 
        .status-badge.unknown { background-color: #E5E7EB; color: #4B5563; } /* Fallback */

        .filter-controls { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; align-items: center;}
        .filter-controls input[type="date"], .filter-controls select { padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.9rem;}
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
                <h1>Schedule Management</h1>
                 <a href="admin_add_schedule.php" class="btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add New Schedule Entry
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
                    <input type="text" placeholder="Search by program title...">
                    <input type="date" name="filter_date_from" title="Filter from date">
                    <input type="date" name="filter_date_to" title="Filter to date">
                    <select name="filter_status">
                        <option value="">All Statuses</option>
                        <option value="Scheduled">Scheduled</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="InProgress">In Progress</option>
                    </select>
                    <button class="btn-secondary btn-sm">Apply Filters</button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Program Title</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Trainer</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($schedules)): ?>
                                <?php foreach ($schedules as $schedule): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($schedule['programTitle']); ?></td>
                                        <td><?php echo date("M d, Y", strtotime($schedule['event_date'])); ?></td>
                                        <td><?php echo date("g:i A", strtotime($schedule['event_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($schedule['trainerName'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php 
                                                // Generate CSS class based on status
                                                $status_class = 'unknown'; // Default
                                                if (!empty($schedule['sessionStatus'])) {
                                                    $status_class = htmlspecialchars(strtolower(str_replace(' ', '', $schedule['sessionStatus'])));
                                                }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo htmlspecialchars($schedule['sessionStatus']); ?>
                                            </span>
                                        </td> 
                                        <td>
                                            <a href="admin_edit_schedule.php?lid=<?php echo $schedule['ledgerID']; ?>" 
                                               class="btn-secondary btn-sm">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6">No scheduled sessions found.</td></tr> 
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                 <div style="text-align: center; padding-top: 1rem; font-size:0.9rem; color:#666;">
                    Showing 1-<?php echo count($schedules); ?> of <?php echo count($schedules); ?> Schedules 
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
