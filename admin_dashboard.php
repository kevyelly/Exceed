<?php
// admin_dashboard.php
session_start(); 

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_admin"]) || $_SESSION["is_admin"] !== true) {
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in admin_dashboard.php.");
     die("Database configuration error. Please contact support."); 
}

// --- PHP Logic to Fetch Data ---
$totalUsers = 0;
$activeTrainers = 0;
$activeTrainees = 0;
$totalPrograms = 0;
$completionRate = 0; 
$sessionsThisWeek = 0; 

// Total Users
$sql_total_users = "SELECT COUNT(UID) as count FROM tblUser";
if($result = $mysqli->query($sql_total_users)) { $totalUsers = $result->fetch_assoc()['count']; $result->free(); } 
else { error_log("Error fetching total users: " . $mysqli->error); }

// Active Trainers
$sql_active_trainers = "SELECT COUNT(UID) as count FROM tblUser WHERE isTrainer = TRUE";
if($result = $mysqli->query($sql_active_trainers)) { $activeTrainers = $result->fetch_assoc()['count']; $result->free(); } 
else { error_log("Error fetching active trainers: " . $mysqli->error); }

// Active Trainees
$sql_active_trainees = "SELECT COUNT(UID) as count FROM tblUser WHERE isTrainee = TRUE";
if($result = $mysqli->query($sql_active_trainees)) { $activeTrainees = $result->fetch_assoc()['count']; $result->free(); } 
else { error_log("Error fetching active trainees: " . $mysqli->error); }

// Total Training Programs
$sql_total_programs = "SELECT COUNT(trainingProgramID) as count FROM tblTrainingProgram";
if($result = $mysqli->query($sql_total_programs)) { $totalPrograms = $result->fetch_assoc()['count']; $result->free(); } 
else { error_log("Error fetching total programs: " . $mysqli->error); }

// Overall Completion Rate
$sql_completion = "SELECT COUNT(*) as total_entries, SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_entries FROM tblScheduleLedger";
if($result = $mysqli->query($sql_completion)) {
    $completion_data = $result->fetch_assoc();
    $total_ledger_entries = $completion_data['total_entries'] ?? 0;
    $completed_ledger_entries = $completion_data['completed_entries'] ?? 0;
    if ($total_ledger_entries > 0) { $completionRate = round(($completed_ledger_entries / $total_ledger_entries) * 100); }
    $result->free();
} else { error_log("Error fetching completion rate data: " . $mysqli->error); }

// Sessions This Week
$today = date('Y-m-d');
$dayOfWeek = date('w'); 
$startOfWeek = date('Y-m-d', strtotime('-' . $dayOfWeek . ' days', strtotime($today)));
$endOfWeek = date('Y-m-d', strtotime('+' . (6 - $dayOfWeek) . ' days', strtotime($today)));
$sql_sessions_week = "SELECT COUNT(sl.ledgerID) as count FROM tblScheduleLedger sl JOIN tblSchedule s ON sl.scheduleID = s.scheduleID WHERE s.event_date BETWEEN ? AND ?";
if($stmt_sw = $mysqli->prepare($sql_sessions_week)) {
    $stmt_sw->bind_param("ss", $startOfWeek, $endOfWeek);
    if ($stmt_sw->execute()) { $result_sw = $stmt_sw->get_result(); $sessionsThisWeek = $result_sw->fetch_assoc()['count']; $result_sw->free(); } 
    else { error_log("Error executing sessions this week query: " . $stmt_sw->error); }
    $stmt_sw->close();
} else { error_log("Error preparing sessions this week query: " . $mysqli->error); }

// Fetch Users for Employee Management Table (Limit 5 for dashboard)
$users = [];
$sql_users = "SELECT UID, fname, lname, email, isTrainee, isTrainer, isAdmin FROM tblUser ORDER BY lname, fname LIMIT 5"; 
if($result_users = $mysqli->query($sql_users)){
    if($result_users->num_rows > 0){ while($row = $result_users->fetch_assoc()){ $users[] = $row; } $result_users->free(); }
} else { error_log("Error fetching users: " . $mysqli->error); }

// Fetch Courses for Course Overview table
$courses = [];
$sql_courses = "SELECT tp.trainingProgramID, tp.title, tp.description, CONCAT(u.fname, ' ', u.lname) AS trainerName, 'Active' AS status FROM tblTrainingProgram tp LEFT JOIN tblTrainer tr ON tp.TrainerID = tr.trainerID LEFT JOIN tblUser u ON tr.UID = u.UID ORDER BY tp.title LIMIT 5";
if($result_courses = $mysqli->query($sql_courses)){
    if($result_courses->num_rows > 0){
        while($row = $result_courses->fetch_assoc()){
             $traineeCountQuery = "SELECT COUNT(DISTINCT tr.UID) as count FROM tblTrainee tr JOIN tblTeam t ON tr.TeamID = t.teamID WHERE t.TrainingProgramID = ?";
             $program_id_for_count = $row['trainingProgramID']; 
             if($stmtCount = $mysqli->prepare($traineeCountQuery)){ 
                 $stmtCount->bind_param("i", $program_id_for_count);
                 if ($stmtCount->execute()) {
                     $resultCount = $stmtCount->get_result();
                     $countData = $resultCount->fetch_assoc();
                     $row['traineesEnrolledCount'] = $countData ? $countData['count'] : 0; 
                 } else { error_log("Error executing trainee count query: " . $stmtCount->error); $row['traineesEnrolledCount'] = 0; }
                 $stmtCount->close();
             } else { error_log("Error preparing trainee count query: " . $mysqli->error); $row['traineesEnrolledCount'] = 0; }
            $courses[] = $row;
        }
        $result_courses->free();
    }
} else { error_log("Error fetching courses: " . $mysqli->error); }

// Fetch Upcoming Sessions for Dashboard Card
$upcomingSessions = [];
$sql_upcoming = "SELECT tp.title AS programTitle, s.event_date, s.event_time, CONCAT(u.fname, ' ', u.lname) AS trainerName, sl.status AS sessionStatus FROM tblScheduleLedger sl JOIN tblTrainingProgram tp ON sl.trainingProgramID = tp.trainingProgramID JOIN tblSchedule s ON sl.scheduleID = s.scheduleID LEFT JOIN tblTrainer tr ON tp.TrainerID = tr.trainerID LEFT JOIN tblUser u ON tr.UID = u.UID WHERE s.event_date >= CURDATE() ORDER BY s.event_date, s.event_time LIMIT 5"; 
if($result_upcoming = $mysqli->query($sql_upcoming)){
    if($result_upcoming->num_rows > 0){ while($row = $result_upcoming->fetch_assoc()){ $upcomingSessions[] = $row; } $result_upcoming->free(); }
} else { error_log("Error fetching upcoming sessions: " . $mysqli->error); }

// Fetch trainers for Add Program Modal dropdown
$trainers_for_dropdown = [];
$sql_trainers_dropdown = "SELECT t.trainerID, u.fname, u.lname FROM tblTrainer t JOIN tblUser u ON t.UID = u.UID ORDER BY u.lname, u.fname";
if($result_trainers_dropdown = $mysqli->query($sql_trainers_dropdown)){ while($trainer_row = $result_trainers_dropdown->fetch_assoc()){ $trainers_for_dropdown[] = $trainer_row; } $result_trainers_dropdown->free(); } 
else { error_log("Error fetching trainers for dropdown: " . $mysqli->error); }

// Fetch teams for Add Program Modal checkboxes AND for Team Sizes card
$teams_for_options = []; // For modal
$teams_with_counts = []; // For Team Sizes card
$max_team_members = 5; // To normalize bar height
$team_colors = ['#01c892', '#535bf2', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#ec4899']; // Predefined colors
$color_index = 0;

$sql_teams_data = "SELECT t.teamID, t.teamName, COUNT(tr.traineeID) as memberCount 
                   FROM tblTeam t 
                   LEFT JOIN tblTrainee tr ON t.teamID = tr.TeamID 
                   GROUP BY t.teamID, t.teamName
                   ORDER BY t.teamName";
if($result_teams_data = $mysqli->query($sql_teams_data)){ 
    while($team_row = $result_teams_data->fetch_assoc()){ 
        $teams_for_options[] = $team_row; 
        // Assign color for the chart
        $team_row['chartColor'] = $team_colors[$color_index % count($team_colors)];
        $teams_with_counts[] = $team_row;
        if ($team_row['memberCount'] > $max_team_members) {
            $max_team_members = $team_row['memberCount'];
        }
        $color_index++;
    } 
    $result_teams_data->free(); 
} else { error_log("Error fetching teams for options/counts: " . $mysqli->error); }
if ($max_team_members == 0) $max_team_members = 1; 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXCEED - Admin Dashboard</title>
    <style>
        /* START: Core Variables & Base Styles */
        :root { --primary-color: #01c892; --text-color: #213547; --bg-color: #f8faf9; --card-bg: #ffffff; --border-color: #e5e7eb; --hover-color: #535bf2; --danger-color: #ef4444; --warning-color: #f59e0b; --pie-background: #f0f0f0; /* Background for pie chart */ }
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
        .dashboard-nav { background: var(--card-bg); box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1rem 0; }
        .dashboard-main { padding: 2rem 1rem; }
        .card { background: var(--card-bg); padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom:1.5rem; }
        .card-header { display: flex; justify-content: center; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color); }
        .card-header h3 { margin: 0; font-size: 1.2rem; color: var(--text-color); display: flex; align-items: center; gap: 0.5rem; }
        .card-header h3 svg { width: 20px; height: 20px; color: var(--primary-color); }
        .employee-management .card-header, .course-overview .card-header { justify-content: space-between; }
        .card-content { flex-grow: 1; }
        /* END: Core Styles */

        /* Admin Dashboard Specific Styles */
        .admin-nav-content { width: 100%; display: flex; justify-content: space-between; align-items: center; }
        .admin-nav-content .app-title { font-size: 1.5rem; font-weight: bold; color: var(--primary-color); text-decoration: none; flex-shrink: 0; }
        .admin-nav-content .nav-links { display: flex; gap: 1.5rem; justify-content: flex-start; flex-grow: 0; margin: 0 auto; } 
        .admin-nav-content .nav-links a { text-decoration: none; color: var(--text-color); font-weight: 500; white-space: nowrap; }
        .admin-nav-content .nav-links a:hover { color: var(--primary-color); }
        .admin-nav-content .logout-link { flex-shrink: 0; }
        .date-filter { display: none; }
        .form-field-group { margin-bottom: 1rem; } 
        .form-field-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.9rem; }
        .form-field-group input[type="text"], .form-field-group input[type="email"], .form-field-group input[type="password"], .form-field-group textarea, .form-field-group select { padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.9rem; width: 100%; background-color: #fff; }
        .form-field-group textarea { min-height: 80px; }
        .checkbox-group label { font-weight: normal; display: inline-flex; align-items: center; gap: 0.5rem; margin-right: 1rem; font-size: 0.9rem;}
        .checkbox-group input[type="checkbox"] { width: auto; margin-right: 0.25rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: var(--card-bg); padding: 1.25rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .stat-card svg { color: var(--primary-color); margin-bottom: 0.75rem; width:24px; height:24px; }
        .stat-card h4 { font-size: 0.9rem; color: #666; margin-bottom: 0.25rem; font-weight:500; }
        .stat-card p { font-size: 1.75rem; font-weight: bold; color: var(--primary-color); }
        .admin-main-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
        .admin-main-grid .notifications-alerts { grid-column: 1 / -1; }
        .list { list-style: none; padding: 0; }
        .list-item { padding: 0.75rem 0; border-bottom: 1px solid var(--border-color); }
        .list-item:last-child { border-bottom: none; }
        .list-item strong { color: var(--text-color); }
        .list-item .item-meta { font-size:0.85rem; color: #666; display:block; margin-top:2px;}
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border-color); font-size:0.9rem; }
        th { background: #f9fafb; font-weight: 600; color: #4b5563; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 500; display:inline-block; margin-right: 0.25rem;}
        .status-badge.active { background: #dcfce7; color: #166534; }
        .status-badge.inactive { background: #fee2e2; color: #991b1b; }
        .status-badge.upcoming { background-color: #e0e7ff; color: #3730a3;}
        .status-badge.trainee { background-color: #e0f2fe; color: #075985; }
        .status-badge.trainer { background-color: #d1fae5; color: #047857; }
        .status-badge.admin { background-color: #ede9fe; color: #5b21b6; }
        .status-badge.scheduled { background-color: #FEF9C3; color: #854D0E; } 
        .status-badge.completed { background-color: #DCFCE7; color: #166534; } 
        .status-badge.cancelled { background-color: #FEE2E2; color: #991B1B; } 
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
        .modal-body form { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1.5rem; row-gap: 1rem; }
        .modal-body .form-field-group { margin-bottom: 0; grid-column: span 2; }
        .modal-body .form-field-group.grid-col-1 { grid-column: span 1; }
        .form-field-group small { font-size:0.8rem; color:#666; display: block; margin-top: 0.25rem; }
        .roles-group { grid-column: span 2; border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 0.5rem; }
        .roles-group label:first-of-type { margin-bottom: 0.75rem; }
        .enrollment-options { max-height: 150px; overflow-y: auto; border: 1px solid var(--border-color); padding: 0.75rem; border-radius: 6px; background-color: #f9fafb; }
        .enrollment-options .checkbox-group { display: block; margin-bottom: 0.5rem;}
        
        /* --- Styles for Progress Snapshot & Team Sizes (v7 - Pie Chart) --- */
        .progress-snapshot .card-content { display: flex; flex-direction: column; align-items: center; justify-content: center; padding-top: 1rem; }
        .pie-chart-container { width: 150px; height: 150px; position: relative; margin-bottom: 1rem; }
        .pie-chart { width: 100%; height: 100%; border-radius: 50%; background-image: conic-gradient( var(--primary-color) calc(var(--progress-value) * 1%), var(--pie-background) 0 ); display: flex; align-items: center; justify-content: center; }
        .pie-chart::before { /* Creates the "hole" for the donut */ content: ""; position: absolute; width: 70%; height: 70%; background: var(--card-bg); border-radius: 50%; }
        .pie-chart-text { position: absolute; font-size: 1.8rem; /* Larger font size */ font-weight: bold; color: var(--primary-color); }
        .progress-snapshot p.completion-label { font-size: 1rem; color: var(--text-color); margin-top: 0.5rem; } 
        
        .team-chart-container { display: flex; align-items: flex-end; height: 150px; border-left: 2px solid var(--border-color); border-bottom: 2px solid var(--border-color); padding: 10px 5px 0 10px; gap: 8px; margin-top: 1rem; overflow-x: hidden; justify-content: flex-start; }
        .team-bar { flex: 0 0 20px; max-width: 30px; text-align: center; color: white; font-size: 0.8rem; position: relative; transition: height 0.3s ease-out; border-radius: 3px 3px 0 0; }
        .team-bar .member-count { position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 0.85rem; color: var(--text-color); font-weight: bold;}
        .team-legend { margin-top: 1.5rem; display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center; }
        .legend-item { display: flex; align-items: center; font-size: 0.85rem; }
        .legend-color-swatch { width: 15px; height: 15px; border-radius: 3px; margin-right: 0.5rem; border: 1px solid var(--border-color); }
        /* --- End Styles --- */

        @media (max-width: 992px) { .admin-nav-content .nav-links { display: none; } }
        @media (max-width: 768px) { .admin-nav-content { flex-direction: column; align-items: flex-start; gap:1rem; } .date-filter { width:100%; margin-left:0; display: block; } .filter-form, .date-inputs { flex-direction: column; width:100%; } .input-group { width:100%; } .input-group input[type="date"] { width:100%;} .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); } .admin-main-grid { grid-template-columns: 1fr; } .admin-main-grid .notifications-alerts { grid-column: span 1; } .modal-body form { grid-template-columns: 1fr; } .modal-body .form-field-group.grid-col-1 { grid-column: span 1; } .modal-content { height: auto; max-height: 90vh; } .team-chart-container { justify-content: flex-start; overflow-x: auto; height: 120px; } .team-bar { flex-basis: 20px; } }
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
                    <a href="logout.php" class="btn-secondary btn-sm logout-link">Logout</a> 
                </div>
            </div>
        </nav>

        <main class="container dashboard-main">
            <div class="stats-grid">
                <div class="stat-card"><h4>Total Users</h4><p><?php echo $totalUsers; ?></p></div>
                <div class="stat-card"><h4>Active Trainers</h4><p><?php echo $activeTrainers; ?></p></div>
                <div class="stat-card"><h4>Active Trainees</h4><p><?php echo $activeTrainees; ?></p></div>
                <div class="stat-card"><h4>Training Programs</h4><p><?php echo $totalPrograms; ?></p></div>
                <div class="stat-card"><h4>Completion Rate</h4><p><?php echo $completionRate; ?>%</p></div>
                <div class="stat-card"><h4>Sessions This Week</h4><p><?php echo $sessionsThisWeek; ?></p></div>
            </div>

            <div class="admin-main-grid">
                <div class="card upcoming-sessions">
                    <div class="card-header"><h3>Upcoming Training Sessions</h3></div>
                    <ul class="list">
                        <?php if (!empty($upcomingSessions)): ?>
                            <?php foreach ($upcomingSessions as $session): ?>
                                <li class="list-item">
                                    <strong><?php echo htmlspecialchars($session['programTitle']); ?></strong> - 
                                    <?php echo date("M d, Y", strtotime($session['event_date'])); ?>, <?php echo date("g:i A", strtotime($session['event_time'])); ?>
                                    <span class="item-meta">Trainer: <?php echo htmlspecialchars($session['trainerName'] ?? 'N/A'); ?> | Status: <span class="status-badge scheduled"><?php echo htmlspecialchars($session['sessionStatus']); ?></span></span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-item" style="text-align:center; color:#888;">No upcoming sessions found.</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="card progress-snapshot">
                    <div class="card-header"><h3>Employee Progress Snapshot</h3></div>
                    <div class="card-content"> 
                        <div class="pie-chart-container">
                            <div class="pie-chart" style="--progress-value: <?php echo $completionRate; ?>">
                                <span class="pie-chart-text"><?php echo $completionRate; ?>%</span>
                            </div>
                        </div>
                        <p class="completion-label">Overall Completion</p> 
                    </div>
                </div>
                
                <div class="card team-sizes">
                    <div class="card-header"><h3>Team Sizes</h3></div>
                    <div class="card-content"> 
                        <div class="team-chart-container">
                            <?php if(!empty($teams_with_counts)): ?>
                                <?php foreach($teams_with_counts as $team): 
                                    $bar_height_percentage = ($max_team_members > 0) ? ($team['memberCount'] / $max_team_members) * 100 : 0;
                                    $bar_height = max(8, $bar_height_percentage); 
                                ?>
                                    <div class="team-bar" 
                                         style="height: <?php echo $bar_height; ?>%; background-color: <?php echo $team['chartColor']; ?>;" 
                                         title="<?php echo htmlspecialchars($team['teamName']) . ': ' . htmlspecialchars($team['memberCount']) . ' members'; ?>">
                                        <div class="member-count"><?php echo htmlspecialchars($team['memberCount']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align:center; color:#888; width:100%;">No teams found.</p>
                            <?php endif; ?>
                        </div>
                        <div class="team-legend">
                             <?php if(!empty($teams_with_counts)): ?>
                                <?php foreach($teams_with_counts as $team): ?>
                                    <div class="legend-item">
                                        <span class="legend-color-swatch" style="background-color: <?php echo $team['chartColor']; ?>;"></span>
                                        <?php echo htmlspecialchars($team['teamName']); ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                 
                <div class="card notifications-alerts"> 
                    <div class="card-header"><h3>Notifications & Alerts</h3></div>
                    <ul class="list">
                        <li class="list-item" style="text-align:center; color:#888;">No new notifications.</li>
                    </ul>
                </div>
            </div>

            <div class="card employee-management">
                <div class="card-header">
                    <h3>Employee Management (Recent Users)</h3> 
                    <a href="admin_add_employee.php" class="btn-primary">Add New Employee</a> 
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role(s)</th>
                                <th>Status</th> 
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php $userCount = 0; ?>
                                <?php foreach ($users as $user): ?>
                                    <?php if ($userCount >= 5) break; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['fname']) . ' ' . htmlspecialchars($user['lname']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php 
                                            $roles = [];
                                            if ($user['isAdmin']) { $roles[] = '<span class="status-badge admin">Admin</span>'; }
                                            if ($user['isTrainer']) { $roles[] = '<span class="status-badge trainer">Trainer</span>'; }
                                            if ($user['isTrainee']) { $roles[] = '<span class="status-badge trainee">Trainee</span>'; }
                                            echo !empty($roles) ? implode(' ', $roles) : 'N/A';
                                            ?>
                                        </td>
                                        <td><span class="status-badge active">Active</span></td> 
                                        <td><a href="admin_edit_employee.php?uid=<?php echo $user['UID']; ?>" class="btn-secondary btn-sm">Edit</a></td> 
                                    </tr>
                                    <?php $userCount++; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5">No users found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                     <div style="text-align: right; padding-top: 0.5rem;">
                        <a href="admin_user_management.php" class="btn-secondary btn-sm">View All Users</a>
                    </div>
                </div>
            </div>

            <div class="card course-overview"> 
                <div class="card-header">
                    <h3>Course Overview</h3>
                    <a href="#add-program-modal" class="btn-primary">Add New Program</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead><tr><th>Program Title</th><th>Description</th><th>Trainer</th><th>Trainees Enrolled</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if (!empty($courses)): ?>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['title']); ?></td>
                                        <td><?php echo htmlspecialchars($course['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($course['trainerName'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($course['traineesEnrolledCount'] ?? 0); ?></td> <td><span class="status-badge active"><?php echo htmlspecialchars($course['status']); ?></span></td>
                                        <td><a href="admin_edit_program.php?pid=<?php echo $course['trainingProgramID']; ?>" class="btn-secondary btn-sm">Edit</a></td> 
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6">No courses found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
    
    <div id="edit-user-modal" class="modal-overlay"><div class="modal-content"><div class="modal-header"><h2>Edit User (Placeholder)</h2><a href="#" class="modal-close-btn" title="Close">X</a></div><div class="modal-body"><p>Edit user form will go here.</p></div><div class="modal-footer"><a href="#" class="btn-secondary modal-close-btn">Cancel</a><button type="submit" class="btn-primary">Save Changes</button></div></div></div>
    <div id="edit-program-modal" class="modal-overlay"><div class="modal-content"><div class="modal-header"><h2>Edit Program (Placeholder)</h2><a href="#" class="modal-close-btn" title="Close">X</a></div><div class="modal-body"><p>Edit program form will go here.</p></div><div class="modal-footer"><a href="#" class="btn-secondary modal-close-btn">Cancel</a><button type="submit" class="btn-primary">Save Changes</button></div></div></div>

    <?php
        // Close the database connection
        if(isset($mysqli) && $mysqli instanceof mysqli) {
            $mysqli->close();
        }
    ?>
</body>
</html>
