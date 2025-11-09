<?php
// trainer_dashboard.php
session_start(); 

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_trainer"]) || $_SESSION["is_trainer"] !== true) {
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in trainer_dashboard.php.");
     die("Database configuration error. Please contact support."); 
}

// --- Get Logged-in Trainer Info ---
$user_id = $_SESSION["user_id"];
$user_fname = $_SESSION["user_fname"] ?? 'Trainer'; 
$page_error = null;
$trainer_id = null;

$sql_get_trainer_id = "SELECT trainerID FROM tblTrainer WHERE UID = ?";
if($stmt_tid = $mysqli->prepare($sql_get_trainer_id)){
    $stmt_tid->bind_param("i", $user_id);
    if($stmt_tid->execute()){
        $result_tid = $stmt_tid->get_result();
        if($result_tid->num_rows === 1){
            $trainer_data = $result_tid->fetch_assoc();
            $trainer_id = $trainer_data['trainerID'];
        } else { $page_error = "Trainer record not found for this user."; }
        $result_tid->free();
    } else { error_log("Error fetching trainer ID: " . $stmt_tid->error); $page_error = "Error fetching trainer data."; }
    $stmt_tid->close();
} else { error_log("Error preparing trainer ID query: " . $mysqli->error); $page_error = "Database error fetching trainer data."; }

// --- Fetch Data Specific to this Trainer (only if trainer_id was found) ---
$assignedPrograms = [];
$upcomingSessions = [];
$kpi_active_courses = 0;
$kpi_upcoming_sessions = 0;
$kpi_total_trainees = 0; 

if ($trainer_id && !$page_error) {
    // Fetch Assigned Training Programs & Count for KPI
    $sql_programs = "SELECT trainingProgramID, title, description 
                     FROM tblTrainingProgram 
                     WHERE TrainerID = ? 
                     ORDER BY title";
    if($stmt_prog = $mysqli->prepare($sql_programs)){
        $stmt_prog->bind_param("i", $trainer_id);
        if($stmt_prog->execute()){
            $result_prog = $stmt_prog->get_result();
            $kpi_active_courses = $result_prog->num_rows; 
            while($row = $result_prog->fetch_assoc()){
                 $prog_id = $row['trainingProgramID'];
                 // Count distinct trainees for this specific program
                 $sql_trainee_count_for_program = "SELECT COUNT(DISTINCT tr.UID) as count 
                                       FROM tblTrainee tr 
                                       JOIN tblTeam tm ON tr.TeamID = tm.teamID
                                       WHERE tm.TrainingProgramID = ?";
                if($stmt_tc = $mysqli->prepare($sql_trainee_count_for_program)){
                    $stmt_tc->bind_param("i", $prog_id);
                    if($stmt_tc->execute()){
                        $res_tc = $stmt_tc->get_result();
                        $count_data = $res_tc->fetch_assoc();
                        $row['traineeCount'] = $count_data['count'] ?? 0;
                    } else {
                        error_log("Error executing trainee count for program {$prog_id}: " . $stmt_tc->error);
                        $row['traineeCount'] = 0;
                    }
                    $stmt_tc->close();
                } else {
                     error_log("Error preparing trainee count for program {$prog_id}: " . $mysqli->error);
                     $row['traineeCount'] = 0;
                }
                 $assignedPrograms[] = $row;
            }
            $result_prog->free();
        } else { error_log("Error fetching assigned programs: " . $stmt_prog->error); $page_error = ($page_error ? $page_error." | " : "")."Error fetching programs."; }
        $stmt_prog->close();
    } else { error_log("Error preparing assigned programs query: " . $mysqli->error); $page_error = ($page_error ? $page_error." | " : "")."DB error fetching programs."; }

    // Fetch Upcoming Sessions for this Trainer & Count for KPI
    $sql_upcoming = "SELECT 
                        tp.title AS programTitle, s.event_date, s.event_time, sl.status AS sessionStatus,
                        (SELECT COUNT(DISTINCT tr.UID) FROM tblTrainee tr JOIN tblTeam tm ON tr.TeamID = tm.teamID WHERE tm.TrainingProgramID = sl.trainingProgramID) AS enrolledCount
                     FROM tblScheduleLedger sl
                     JOIN tblTrainingProgram tp ON sl.trainingProgramID = tp.trainingProgramID
                     JOIN tblSchedule s ON sl.scheduleID = s.scheduleID
                     WHERE tp.TrainerID = ? AND s.event_date >= CURDATE() 
                     ORDER BY s.event_date, s.event_time
                     LIMIT 10"; 
    if($stmt_upcoming = $mysqli->prepare($sql_upcoming)){
        $stmt_upcoming->bind_param("i", $trainer_id);
        if($stmt_upcoming->execute()){
            $result_upcoming = $stmt_upcoming->get_result();
            $kpi_upcoming_sessions = $result_upcoming->num_rows; 
            while($row = $result_upcoming->fetch_assoc()){ $upcomingSessions[] = $row; }
            $result_upcoming->free();
        } else { error_log("Error fetching trainer upcoming sessions: " . $stmt_upcoming->error); $page_error = ($page_error ? $page_error." | " : "")."Error fetching schedule."; }
        $stmt_upcoming->close();
    } else { error_log("Error preparing trainer upcoming sessions query: " . $mysqli->error); $page_error = ($page_error ? $page_error." | " : "")."DB error fetching schedule."; }
    
    // Calculate Total Distinct Trainees Instructed by this Trainer
    $sql_total_trainees_kpi = "SELECT COUNT(DISTINCT tr.UID) as totalTraineeCount
                               FROM tblTrainee tr
                               JOIN tblTeam tm ON tr.TeamID = tm.teamID
                               JOIN tblTrainingProgram tp ON tm.TrainingProgramID = tp.trainingProgramID
                               WHERE tp.TrainerID = ?";
    if($stmt_kpi_tt = $mysqli->prepare($sql_total_trainees_kpi)){
        $stmt_kpi_tt->bind_param("i", $trainer_id);
        if($stmt_kpi_tt->execute()){
            $res_kpi_tt = $stmt_kpi_tt->get_result();
            $data_kpi_tt = $res_kpi_tt->fetch_assoc();
            $kpi_total_trainees = $data_kpi_tt['totalTraineeCount'] ?? 0;
        } else {
            error_log("Error executing total trainees KPI query: " . $stmt_kpi_tt->error);
        }
        $stmt_kpi_tt->close();
    } else {
        error_log("Error preparing total trainees KPI query: " . $mysqli->error);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXCEED - Trainer Dashboard</title>
    <link rel="stylesheet" href="styles.css"> 
    <style>
        /* START: Core Variables & Base Styles */
        :root { --primary-color: #01c892; --text-color: #213547; --bg-color: #f8faf9; --card-bg: #ffffff; --border-color: #e5e7eb; --hover-color: #535bf2; --danger-color: #ef4444; --warning-color: #f59e0b; --success-color: #10b981; --success-bg-color: #dcfce7; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Inter, system-ui, -apple-system, sans-serif; line-height: 1.5; color: var(--text-color); background-color: var(--bg-color); }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 1rem; }
        .btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6em 1.2em; background-color: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #00b583; }
        .btn-secondary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5em 1em; background-color: #e0e7ff; color: var(--hover-color); border: 1px solid transparent; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: background-color 0.3s; }
        .btn-secondary:hover { background-color: #c7d2fe; }
        .btn-sm { padding: 0.4em 0.8em; font-size: 0.8rem; }
        
        .dashboard { min-height: 100vh; background-color: var(--bg-color); }
        .dashboard-nav { background: var(--card-bg); box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1rem 0; }
        .dashboard-main { padding: 2rem 1rem; }
        .card { background: var(--card-bg); padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 1.5rem; display: flex; flex-direction: column; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color); }
        .card-header h3 { margin: 0; font-size: 1.2rem; color: var(--text-color); display: flex; align-items: center; gap: 0.5rem; }
        .card-header h3 svg { width: 20px; height: 20px; color: var(--primary-color); }
        .card-content { flex-grow: 1; }
        .card-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-color); text-align: right; }
        /* END: Core Styles */

        /* Trainer Dashboard Specific Styles */
        .trainer-nav-content { width: 100%; display: flex; justify-content: space-between; align-items: center; }
        .trainer-nav-content .app-title { font-size: 1.5rem; font-weight: bold; color: var(--primary-color); text-decoration: none; }
        .trainer-nav-content .user-actions { display: flex; align-items: center; gap: 1rem; }
        .trainer-nav-content .user-actions span { font-size: 0.95rem; }
        .trainer-nav-content .user-actions a { color: var(--text-color); text-decoration: none; }
        .trainer-nav-content .user-actions a:hover { color: var(--primary-color); }

        .trainer-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .kpi-card { background: var(--card-bg); padding: 1.25rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center; }
        .kpi-card .kpi-value { font-size: 2rem; font-weight: bold; color: var(--primary-color); margin-bottom: 0.25rem; }
        .kpi-card .kpi-label { font-size: 0.9rem; color: #666; }

        .trainer-main-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; }
        
        .list { list-style: none; padding: 0; }
        .list-item { padding: 0.85rem 0.1rem; border-bottom: 1px solid var(--border-color); }
        .list-item:last-child { border-bottom: none; }
        .list-item-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .item-title { font-weight: 600; font-size: 1rem; color: var(--text-color); margin-bottom: 0.25rem; }
        .item-details { font-size: 0.875rem; color: #555; margin-bottom: 0.5rem; }
        .item-details span { display: block; margin-top: 2px; }
        .item-actions { margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .item-actions .btn-secondary svg { width: 14px; height: 14px; }
        
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 500; display:inline-block; margin-right: 0.25rem;}
        .status-badge.scheduled { background-color: #FEF9C3; color: #854D0E; } 
        .status-badge.completed { background-color: #DCFCE7; color: #166534; } 
        .status-badge.cancelled { background-color: #FEE2E2; color: #991B1B; } 
        .status-badge.inprogress { background-color: #DBEAFE; color: #1E40AF; } 

        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; border: 1px solid transparent; font-size: 0.95rem;}
        .alert-danger { background-color: #fee2e2; color: #991b1b; border-color: #fca5a5;}

        @media (max-width: 992px) { 
             .trainer-nav-content .nav-links { display: none; } /* Example: hide main links if you add them */
        }
        @media (max-width: 768px) {
            .trainer-nav-content { flex-direction: column; align-items: flex-start; gap:1rem; }
            .trainer-nav-content .user-actions { width: 100%; justify-content: space-between; }
            .trainer-kpi-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
            .trainer-main-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <nav class="dashboard-nav">
            <div class="container">
                <div class="trainer-nav-content">
                    <a href="index.html" class="app-title">EXCEED</a>
                    <div class="user-actions">
                        <span>Welcome, Trainer <?php echo htmlspecialchars($user_fname); ?>!</span>
                        <a href="#">My Profile</a>
                        <a href="logout.php">Logout</a>
                    </div>
                </div>
            </div>
        </nav>

        <main class="container dashboard-main">
            
            <?php if ($page_error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($page_error); ?></div>
            <?php endif; ?>

            <div class="trainer-kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo $kpi_active_courses; ?></div>
                    <div class="kpi-label">My Active Courses</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo $kpi_upcoming_sessions; ?></div>
                    <div class="kpi-label">My Upcoming Sessions</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo $kpi_total_trainees; ?></div>
                    <div class="kpi-label">Total Trainees Instructed</div>
                </div>
            </div>

            <div class="trainer-main-grid">
                <div class="card">
                    <div class="card-header">
                        <h3>My Upcoming Sessions</h3>
                        <a href="#" class="btn-secondary btn-sm">View Full Schedule</a>
                    </div>
                    <div class="card-content">
                        <ul class="list">
                            <?php if (!empty($upcomingSessions)): ?>
                                <?php foreach ($upcomingSessions as $session): ?>
                                <li class="list-item">
                                    <div class="list-item-header">
                                        <div class="item-title"><?php echo htmlspecialchars($session['programTitle']); ?></div>
                                        <span class="status-badge <?php echo htmlspecialchars(strtolower(str_replace(' ', '', $session['sessionStatus']))); ?>">
                                            <?php echo htmlspecialchars($session['sessionStatus']); ?>
                                        </span>
                                    </div>
                                    <div class="item-details">
                                        <span>Date: <?php echo date("M d, Y", strtotime($session['event_date'])); ?> | Time: <?php echo date("g:i A", strtotime($session['event_time'])); ?></span>
                                        <span>Enrolled: <?php echo htmlspecialchars($session['enrolledCount'] ?? 0); ?></span>
                                    </div>
                                    <div class="item-actions">
                                        <a href="#" class="btn-secondary btn-sm">View Details</a>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                 <li class="list-item" style="text-align:center; color:#888;">No upcoming sessions found.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>My Training Programs</h3>
                        <a href="#" class="btn-secondary btn-sm">View All My Courses</a>
                    </div>
                    <div class="card-content">
                        <ul class="list">
                             <?php if (!empty($assignedPrograms)): ?>
                                <?php foreach ($assignedPrograms as $program): ?>
                                <li class="list-item">
                                    <div class="item-title"><?php echo htmlspecialchars($program['title']); ?></div>
                                    <div class="item-details">
                                        <?php echo htmlspecialchars(substr($program['description'] ?? '', 0, 100)) . (strlen($program['description'] ?? '') > 100 ? '...' : ''); ?>
                                        <br>Trainees: <?php echo $program['traineeCount']; ?> 
                                    </div>
                                    <div class="item-actions">
                                        <a href="#" class="btn-secondary btn-sm">View Roster & Progress</a>
                                    </div>
                                </li>
                                 <?php endforeach; ?>
                            <?php else: ?>
                                 <li class="list-item" style="text-align:center; color:#888;">No programs assigned to you.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3>Trainee Information</h3></div>
                    <div class="card-content">
                        <div style="margin-top: 1rem;">
                            <label for="trainee-search" style="font-size:0.9rem; display:block; margin-bottom:0.3rem;">Search Trainee (within my courses):</label>
                            <input type="text" id="trainee-search" placeholder="Enter trainee name or ID..." style="width:100%; padding:0.5rem; border:1px solid var(--border-color); border-radius:4px;">
                        </div>
                         <p style="font-size: 0.9rem; color: #666; margin-top: 1rem;">
                            Use the "View Roster & Progress" links on your Training Programs to see detailed trainee information.
                        </p>
                    </div>
                     <div class="card-footer">
                         <a href="#" class="btn-primary btn-sm">Full Trainee Management</a>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header"><h3>Notifications</h3> <a href="#" class="btn-secondary btn-sm">View All</a></div>
                    <div class="card-content">
                        <ul class="list">
                           <li class="list-item" style="text-align:center; color:#888;">No new notifications.</li>
                        </ul>
                    </div>
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
