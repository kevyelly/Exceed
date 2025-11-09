<?php
// trainee_dashboard.php
session_start(); 

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["is_trainee"]) || $_SESSION["is_trainee"] !== true) {
    header("location: login.php");
    exit;
}

require_once 'db_config.php'; 

if (!isset($mysqli) || !$mysqli instanceof mysqli) {
     error_log("Database connection object (\$mysqli) not available in trainee_dashboard.php.");
     die("Database configuration error. Please contact support."); 
}

// --- Get Logged-in Trainee Info ---
$user_id = $_SESSION["user_id"];
$user_fname = $_SESSION["user_fname"] ?? 'Trainee'; 
$page_error = null;
$trainee_info = null;
$team_id = null;
$program_id = null; // Primary program ID from team

// Fetch trainee-specific details (like team)
$sql_trainee_details = "SELECT traineeID, TeamID FROM tblTrainee WHERE UID = ?";
if($stmt_td = $mysqli->prepare($sql_trainee_details)){
    $stmt_td->bind_param("i", $user_id);
    if($stmt_td->execute()){
        $result_td = $stmt_td->get_result();
        if($result_td->num_rows === 1){
            $trainee_info = $result_td->fetch_assoc();
            $team_id = $trainee_info['TeamID'];
        } else { $page_error = "Trainee record not found."; }
        $result_td->free();
    } else { error_log("Error fetching trainee details: " . $stmt_td->error); $page_error = "Error fetching trainee data."; }
    $stmt_td->close();
} else { error_log("Error preparing trainee details query: " . $mysqli->error); $page_error = "Database error fetching trainee data."; }

// Fetch associated program ID from the team (if team found)
if ($team_id && !$page_error) {
     $sql_team_program = "SELECT TrainingProgramID FROM tblTeam WHERE teamID = ?";
     if($stmt_tp = $mysqli->prepare($sql_team_program)){
         $stmt_tp->bind_param("i", $team_id);
         if($stmt_tp->execute()){
             $result_tp = $stmt_tp->get_result();
             if($result_tp->num_rows === 1){
                 $team_info = $result_tp->fetch_assoc();
                 $program_id = $team_info['TrainingProgramID'];
             } // else: Team might not be linked to a primary program
             $result_tp->free();
         } else { error_log("Error fetching team program: " . $stmt_tp->error); $page_error = $page_error ? $page_error." | Error fetching team program." : "Error fetching team program."; }
         $stmt_tp->close();
     } else { error_log("Error preparing team program query: " . $mysqli->error); $page_error = $page_error ? $page_error." | DB error fetching team program." : "DB error fetching team program."; }
}

// --- Fetch Upcoming Sessions for Dashboard (Limit 5) ---
$upcomingSessionsDashboard = [];
if ($program_id && !$page_error) {
    $sql_upcoming_dash = "SELECT tp.title AS programTitle, s.event_date, s.event_time, CONCAT(u.fname, ' ', u.lname) AS trainerName, sl.status AS sessionStatus
                     FROM tblScheduleLedger sl
                     JOIN tblTrainingProgram tp ON sl.trainingProgramID = tp.trainingProgramID
                     JOIN tblSchedule s ON sl.scheduleID = s.scheduleID
                     LEFT JOIN tblTrainer tr ON tp.TrainerID = tr.trainerID
                     LEFT JOIN tblUser u ON tr.UID = u.UID
                     WHERE sl.trainingProgramID = ? AND s.event_date >= CURDATE() 
                     ORDER BY s.event_date, s.event_time
                     LIMIT 5"; 
    if($stmt_upcoming_dash = $mysqli->prepare($sql_upcoming_dash)){
        $stmt_upcoming_dash->bind_param("i", $program_id);
        if($stmt_upcoming_dash->execute()){
            $result_upcoming_dash = $stmt_upcoming_dash->get_result();
            while($row = $result_upcoming_dash->fetch_assoc()){ $upcomingSessionsDashboard[] = $row; }
            $result_upcoming_dash->free();
        } else { error_log("Error fetching dashboard upcoming sessions: " . $stmt_upcoming_dash->error); }
        $stmt_upcoming_dash->close();
    } else { error_log("Error preparing dashboard upcoming sessions query: " . $mysqli->error); }
}

// --- Fetch ALL Sessions (Upcoming & Past) for Modal ---
$allSessionsModal = [];
$eventDates = []; // Array to store dates with events for the calendar
if ($program_id && !$page_error) {
    $sql_all_sessions = "SELECT 
                            tp.title AS programTitle, 
                            s.event_date, 
                            s.event_time, 
                            CONCAT(u.fname, ' ', u.lname) AS trainerName,
                            sl.status AS sessionStatus
                         FROM tblScheduleLedger sl
                         JOIN tblTrainingProgram tp ON sl.trainingProgramID = tp.trainingProgramID
                         JOIN tblSchedule s ON sl.scheduleID = s.scheduleID
                         LEFT JOIN tblTrainer tr ON tp.TrainerID = tr.trainerID
                         LEFT JOIN tblUser u ON tr.UID = u.UID
                         WHERE sl.trainingProgramID = ? 
                         ORDER BY s.event_date DESC, s.event_time DESC"; 

    if($stmt_all = $mysqli->prepare($sql_all_sessions)){
        $stmt_all->bind_param("i", $program_id);
        if($stmt_all->execute()){
            $result_all = $stmt_all->get_result();
            while($row = $result_all->fetch_assoc()){
                $allSessionsModal[] = $row;
                // Store the date part (Y-m-d) for calendar marking
                $eventDateOnly = date('Y-m-d', strtotime($row['event_date']));
                if (!in_array($eventDateOnly, $eventDates)) {
                    $eventDates[] = $eventDateOnly;
                }
            }
            $result_all->free();
        } else {
            error_log("Error fetching all sessions for modal: " . $stmt_all->error);
            $page_error = $page_error ? $page_error." | Error fetching session history." : "Error fetching session history.";
        }
        $stmt_all->close();
    } else {
         error_log("Error preparing all sessions query: " . $mysqli->error);
         $page_error = $page_error ? $page_error." | DB error fetching session history." : "DB error fetching session history.";
    }
}

// --- Calculate Progress (Simplified Placeholder) ---
$progressPercentage = 0;
$completedModules = 0;
$totalModules = 0;
if ($program_id && !$page_error) {
     $sql_progress = "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed FROM tblScheduleLedger WHERE trainingProgramID = ?";
     if($stmt_prog = $mysqli->prepare($sql_progress)){
         $stmt_prog->bind_param("i", $program_id);
         if($stmt_prog->execute()){
             $result_prog = $stmt_prog->get_result();
             $progress_data = $result_prog->fetch_assoc();
             $totalModules = $progress_data['total'] ?? 0;
             $completedModules = $progress_data['completed'] ?? 0;
             if ($totalModules > 0) { $progressPercentage = round(($completedModules / $totalModules) * 100); }
             $result_prog->free();
         } else { error_log("Error fetching progress data: " . $stmt_prog->error); }
         $stmt_prog->close();
     } else { error_log("Error preparing progress query: " . $mysqli->error); }
}

// --- Calendar Generation Logic ---
$currentYear = date('Y');
$currentMonth = date('m');
$currentDay = date('d');
$monthName = date('F', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDayOfMonth = date('w', mktime(0, 0, 0, $currentMonth, 1, $currentYear)); // 0 (Sun) to 6 (Sat)

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXCEED - Trainee Dashboard</title>
    <link rel="stylesheet" href="styles.css"> 
    <style>
        /* Paste the specific employee/trainee dashboard styles here */
        :root { --primary-color: #01c892; --text-color: #213547; --bg-color: #f8faf9; --card-bg: #ffffff; --border-color: #e5e7eb; --hover-color: #535bf2; --danger-color: #ef4444; --danger-bg-color: #fee2e2; --danger-border-color: #fca5a5; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Inter, system-ui, -apple-system, sans-serif; line-height: 1.5; color: var(--text-color); background-color: var(--bg-color); }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 1rem; }
        .btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6em 1.2em; background-color: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #00b583; }
        .btn-secondary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5em 1em; background-color: #e0e7ff; color: var(--hover-color); border: 1px solid transparent; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; text-decoration: none; transition: background-color 0.3s; }
        .btn-secondary:hover { background-color: #c7d2fe; }
        .dashboard { min-height: 100vh; background-color: var(--bg-color); }
        .dashboard-nav { background: var(--card-bg); box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 1rem 0; }
        .nav-content { width: 100%; display: flex; justify-content: space-between; align-items: center; }
        .dashboard-main { padding: 2rem 1rem; }
        .card { background: var(--card-bg); padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
        .trainee-dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 2rem; }
        .card h3 { font-size: 1.25rem; margin-bottom: 1rem; color: var(--primary-color); }
        .training-list ul, .notification-list ul, .modal-list ul { list-style: none; padding: 0; margin: 0; } 
        .training-list li, .notification-list li, .modal-list li { padding: 0.75rem 0; border-bottom: 1px solid var(--border-color); display: flex; flex-direction: column; }
        .training-list li:last-child, .notification-list li:last-child, .modal-list li:last-child { border-bottom: none; }
        .training-item-title, .notification-item-title { font-weight: 600; font-size: 1rem; }
        .training-item-details, .notification-item-details { font-size: 0.875rem; color: #666; margin-top: 0.25rem; }
        .progress-bar-container { width: 100%; background-color: #e0e0e0; border-radius: 8px; overflow: hidden; margin-top: 0.5rem; }
        .progress-bar { height: 20px; background-color: var(--primary-color); text-align: center; line-height: 20px; color: white; font-size: 0.8rem; border-radius: 8px 0 0 8px; transition: width 0.5s ease-in-out; }
        .progress-percentage { font-size: 1.5rem; font-weight: bold; color: var(--primary-color); margin-top: 0.5rem; text-align: center; }
        .compact-calendar { padding: 1rem; background-color: #f9f9f9; border-radius: 8px; text-align: center; color: #666; }
        .compact-calendar p { margin-bottom: 0.5rem; }
        .compact-calendar .event { font-size: 0.85rem; padding: 0.25rem; background-color: var(--primary-color); color: white; border-radius: 4px; margin-top: 0.3rem; display: block; }
        .dashboard-header { width: 100%; display: flex; justify-content: space-between; align-items: center; }
        .app-logo a { text-decoration: none; color: var(--primary-color); font-size: 1.8rem; font-weight: bold; }
        .user-actions { display: flex; align-items: center; gap: 1rem; } 
        .user-actions span { font-size: 0.95rem; }
        .user-actions a { color: var(--text-color); text-decoration: none; }
        .user-actions a:hover { color: var(--primary-color); }
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; border: 1px solid transparent; font-size: 0.95rem;}
        .alert-danger { background-color: var(--danger-bg-color); color: #991b1b; border-color: var(--danger-border-color);}
        .alert-warning { background-color: #fffbeb; color: #b45309; border-color: #fcd34d;}
        @media (min-width: 620px) { .trainee-dashboard-grid > .card:nth-child(4) { grid-column: 1 / -1; } }
        @media (max-width: 768px) { .trainee-dashboard-grid { grid-template-columns: 1fr; } .trainee-dashboard-grid > .card:nth-child(4) { grid-column: auto; } .dashboard-header { flex-direction: column; gap: 1rem; align-items: flex-start; } .user-actions { width: 100%; justify-content: space-between; } }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); display: flex; justify-content: center; align-items: center; visibility: hidden; opacity: 0; transition: opacity 0.3s ease, visibility 0s linear 0.3s; z-index: 1000; padding: 1rem; }
        .modal-overlay:target { visibility: visible; opacity: 1; transition: opacity 0.3s ease; }
        .modal-content { background-color: var(--card-bg); padding: 0; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 90%; max-width: 800px; max-height: 90vh; display: flex; flex-direction: column; transform: scale(0.9); transition: transform 0.3s ease; overflow: hidden; }
        .modal-overlay:target .modal-content { transform: scale(1); }
        .modal-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border-color); flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { margin: 0; font-size: 1.5rem; color: var(--text-color); }
        .modal-close-btn { text-decoration: none; color: #888; }
        .modal-close-btn svg { width: 24px; height: 24px; color: #888; display:block; }
        .modal-close-btn:hover svg, .modal-close-btn:hover { color: #333; }
        .modal-body { overflow-y: auto; flex: 1 1 auto; padding: 1.5rem 2rem; } 
        .modal-footer { border-top: 1px solid var(--border-color); padding: 1rem 2rem; margin-top: auto; text-align: right; flex-shrink: 0; }
        .modal-footer .btn-primary { margin-left: 0.5rem; }
        .modal-list li strong { color: var(--text-color); }
        .modal-list .item-link { font-size: 0.875rem; color: var(--primary-color); text-decoration: none; font-weight: 500; display: inline-block; margin-top: 0.25rem; }
        .modal-list .item-link:hover { text-decoration: underline; }
        .modal-divider { border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0; }
        .status-badge { padding: 0.25em 0.6em; font-size: 0.8rem; font-weight: 600; border-radius: 20px; display: inline-block; line-height: 1; text-transform: capitalize; }
        .status-badge.upcoming, .status-badge.scheduled { background-color: #e0e7ff; color: #3730a3; }
        .status-badge.active, .status-badge.completed { background-color: #dcfce7; color: #166534; }
        .status-badge.attended { background-color: #ffedd5; color: #9a3412; }
        .status-badge.cancelled { background-color: #FEE2E2; color: #991B1B; } 
        .status-badge.inprogress { background-color: #DBEAFE; color: #1E40AF; } 
        /* Calendar Styles */
        .calendar-container { margin-bottom: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; background-color: #fdfdfd; }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color); }
        .calendar-month-year { font-size: 1.2rem; font-weight: 600; color: var(--text-color); }
        .calendar-prev-month, .calendar-next-month { font-size: 0.9rem; color: #aaa; cursor: default; padding: 0.25rem 0.5rem; border-radius: 4px; } /* Disabled look */
        /* .calendar-prev-month:hover, .calendar-next-month:hover { background-color: #e0f7f2; } */ /* Remove hover for disabled */
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; }
        .calendar-day-name { font-size: 0.8rem; font-weight: 600; color: #666; text-align: center; padding-bottom: 0.5rem; }
        .calendar-day { font-size: 0.9rem; text-align: center; padding: 0.6rem 0.2rem; border-radius: 6px; min-height: 40px; display: flex; align-items: center; justify-content: center; border: 1px solid transparent; position: relative; /* Needed for event dot */ }
        .calendar-day.empty { visibility: hidden; }
        .calendar-day:not(.empty):not(.today):not(.event):hover { background-color: #f0f8f6; } /* Hover only on non-special days */
        .calendar-day.today { background-color: var(--primary-color); color: white; font-weight: bold; border-color: var(--primary-color); }
        .calendar-day.today:hover { background-color: #00a97a; }
        .calendar-day.event { background-color: #ffedd5; color: #9a3412; font-weight: 600; border: 1px solid #fed7aa; }
        .calendar-day.event.today { background-color: var(--primary-hover-color); border-color: var(--primary-hover-color); } /* Style if today is also an event */
        .calendar-day.event:hover { background-color: #fed7aa; }
        /* Optional: Event dot indicator
        .calendar-day.event::after { content: ''; position: absolute; bottom: 4px; left: 50%; transform: translateX(-50%); width: 6px; height: 6px; border-radius: 50%; background-color: var(--primary-color); }
        .calendar-day.today.event::after { background-color: white; } */

    </style>
</head>
<body>
    <div class="dashboard">
        <nav class="dashboard-nav">
            <div class="container">
                <div class="nav-content dashboard-header">
                    <div class="app-logo">
                        <a href="index.html">EXCEED</a>
                    </div>
                    <div class="user-actions">
                        <span>Welcome, <?php echo htmlspecialchars($user_fname); ?>!</span>
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

            <div class="trainee-dashboard-grid">
                <div class="card upcoming-trainings">
                    <h3>Upcoming Training Sessions</h3>
                    <div class="training-list">
                        <?php if (!empty($upcomingSessionsDashboard)): ?>
                            <ul>
                                <?php foreach ($upcomingSessionsDashboard as $session): ?>
                                <li>
                                    <span class="training-item-title"><?php echo htmlspecialchars($session['programTitle']); ?></span>
                                    <span class="training-item-details">
                                        Date: <?php echo date("M d, Y", strtotime($session['event_date'])); ?> | 
                                        Time: <?php echo date("g:i A", strtotime($session['event_time'])); ?> | 
                                        Trainer: <?php echo htmlspecialchars($session['trainerName'] ?? 'N/A'); ?>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php elseif (!$program_id && !$page_error): ?>
                             <p style="text-align:center; color:#666; margin-top: 1rem;">You are not currently assigned to a training program.</p>
                        <?php else: ?>
                            <p style="text-align:center; color:#666; margin-top: 1rem;">No upcoming sessions scheduled for your program.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card training-progress">
                    <h3>Your Overall Training Progress</h3>
                    <p class="progress-percentage"><?php echo $progressPercentage; ?>%</p>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?php echo $progressPercentage; ?>%;"><?php echo $progressPercentage; ?>%</div>
                    </div>
                    <p style="font-size: 0.9rem; color: #666; text-align: center; margin-top: 0.5rem;">
                        <?php echo $completedModules; ?> out of <?php echo $totalModules; ?> assigned modules completed.
                    </p>
                </div>

                <div class="card schedule-view">
                    <h3>Your Schedule (Upcoming)</h3>
                     <div class="compact-calendar">
                        <p><strong><?php echo date("F Y"); ?></strong></p>
                        <?php if (!empty($upcomingSessionsDashboard)): ?>
                            <?php foreach ($upcomingSessionsDashboard as $session): ?>
                                <div><?php echo date("M d", strtotime($session['event_date'])); ?>: <span class="event"><?php echo htmlspecialchars($session['programTitle']); ?></span></div>
                            <?php endforeach; ?>
                        <?php else: ?>
                             <p style="margin-top:1rem; font-size:0.9rem;">No sessions this month.</p>
                        <?php endif; ?>
                        <p style="margin-top:1rem; font-size:0.9rem;">(Full calendar view available in "All Sessions")</p>
                    </div>
                </div>

                <div class="card notifications-panel"> <h3>Notifications</h3>
                    <div class="notification-list">
                        <ul>
                            <li style="text-align:center; color:#888;">No new notifications.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 2.5rem; margin-bottom: 2rem;">
                <a href="#history-modal" class="btn-primary">View All Sessions & Training History</a>
            </div>
        </main>
    </div>

    <div id="history-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>All Sessions & Training History</h2>
                <a href="#" class="modal-close-btn" title="Close"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></a>
            </div>
            <div class="modal-body">
                <div class="calendar-container">
                    <div class="calendar-header">
                        <span class="calendar-prev-month">&lt; Prev</span> <span class="calendar-month-year"><?php echo $monthName . ' ' . $currentYear; ?></span>
                        <span class="calendar-next-month">Next &gt;</span> </div>
                    <div class="calendar-grid">
                        <div class="calendar-day-name">Sun</div>
                        <div class="calendar-day-name">Mon</div>
                        <div class="calendar-day-name">Tue</div>
                        <div class="calendar-day-name">Wed</div>
                        <div class="calendar-day-name">Thu</div>
                        <div class="calendar-day-name">Fri</div>
                        <div class="calendar-day-name">Sat</div>

                        <?php
                        // Add empty cells before the first day
                        for ($i = 0; $i < $firstDayOfMonth; $i++) {
                            echo '<div class="calendar-day empty"></div>';
                        }

                        // Loop through days of the month
                        for ($day = 1; $day <= $daysInMonth; $day++) {
                            $currentDate = sprintf('%s-%s-%02d', $currentYear, $currentMonth, $day);
                            $classes = 'calendar-day';
                            if ($day == $currentDay) {
                                $classes .= ' today'; // Highlight today
                            }
                            if (in_array($currentDate, $eventDates)) {
                                $classes .= ' event'; // Mark event date
                            }
                            echo "<div class=\"{$classes}\">{$day}</div>";
                        }

                        // Add empty cells after the last day
                        $totalCells = $firstDayOfMonth + $daysInMonth;
                        $remainingCells = (7 - ($totalCells % 7)) % 7;
                        for ($i = 0; $i < $remainingCells; $i++) {
                             echo '<div class="calendar-day empty"></div>';
                        }
                        ?>
                    </div>
                </div>
                <hr class="modal-divider">
                
                <div class="modal-list">
                    <h4>All Assigned Sessions</h4>
                    <?php if (!empty($allSessionsModal)): ?>
                        <ul>
                            <?php foreach ($allSessionsModal as $session): ?>
                                <?php
                                    $session_datetime_str = $session['event_date'] . ' ' . $session['event_time'];
                                    $session_timestamp = strtotime($session_datetime_str);
                                    $status_class = htmlspecialchars(strtolower(str_replace(' ', '', $session['sessionStatus'])));
                                ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($session['programTitle']); ?></strong> - 
                                    <?php echo date("M d, Y, g:i A", $session_timestamp); ?> 
                                    (Trainer: <?php echo htmlspecialchars($session['trainerName'] ?? 'N/A'); ?>) - 
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($session['sessionStatus']); ?>
                                    </span>
                                    <?php if ($session['sessionStatus'] == 'Completed'): ?>
                                         <br><a href="#" class="item-link">View Details/Certificate</a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php elseif (!$program_id && !$page_error): ?>
                         <p style="text-align:center; color:#666;">You are not currently assigned to a training program.</p>
                    <?php else: ?>
                        <p style="text-align:center; color:#666;">No sessions found for your assigned program.</p>
                    <?php endif; ?>
                </div>

            </div>
            <div class="modal-footer">
                <a href="#" class="btn-secondary modal-close-btn">Close</a>
            </div>
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
