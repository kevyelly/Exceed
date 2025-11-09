<?php
// admin_edit_schedule.php
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
     error_log("Database connection object (\$mysqli) not available in admin_edit_schedule.php.");
     $page_error = "Database configuration error. Cannot load page data."; 
}

// --- Get Ledger ID from URL ---
$ledger_id_to_edit = null;
if (isset($_GET['lid']) && filter_var($_GET['lid'], FILTER_VALIDATE_INT)) {
    $ledger_id_to_edit = (int)$_GET['lid'];
} 

// Redirect if no valid LID is provided
if ($ledger_id_to_edit === null) {
    $_SESSION['error_message'] = "Invalid schedule entry specified for editing.";
    header("location: admin_schedule_management.php");
    exit;
}

// --- Fetch Schedule Ledger Data (Only if DB connection is okay) ---
$schedule_data = null;
if ($mysqli && !$page_error) {
    $sql_get_schedule = "SELECT 
                            sl.ledgerID, sl.trainingProgramID, sl.scheduleID, sl.status,
                            s.event_date, s.event_time,
                            tp.title as programTitle /* Include program title for display */
                         FROM tblScheduleLedger sl
                         JOIN tblSchedule s ON sl.scheduleID = s.scheduleID
                         JOIN tblTrainingProgram tp ON sl.trainingProgramID = tp.trainingProgramID
                         WHERE sl.ledgerID = ?";

    if ($stmt_get_schedule = $mysqli->prepare($sql_get_schedule)) {
        $stmt_get_schedule->bind_param("i", $ledger_id_to_edit);
        if ($stmt_get_schedule->execute()) {
            $result = $stmt_get_schedule->get_result();
            if ($result->num_rows === 1) {
                $schedule_data = $result->fetch_assoc();
                // Format date and time for input fields
                $schedule_data['event_date_formatted'] = date('Y-m-d', strtotime($schedule_data['event_date']));
                $schedule_data['event_time_formatted'] = date('H:i', strtotime($schedule_data['event_time']));
            } else {
                $_SESSION['error_message'] = "Schedule entry not found.";
                header("location: admin_schedule_management.php");
                exit;
            }
            $result->free();
        } else {
            error_log("Error executing schedule fetch query (LID: {$ledger_id_to_edit}): " . $stmt_get_schedule->error);
            $page_error = "Error retrieving schedule data.";
        }
        $stmt_get_schedule->close();
    } else {
        error_log("Error preparing schedule fetch query: " . $mysqli->error);
        $page_error = "Database error retrieving schedule.";
    }
}

// --- Fetch Programs for Dropdown (Only if DB connection is okay) ---
$programs_for_options = [];
if ($mysqli && !$page_error) {
    $sql_programs_options = "SELECT trainingProgramID, title FROM tblTrainingProgram ORDER BY title";
    if($result_programs_options = $mysqli->query($sql_programs_options)){
        while($program_option_row = $result_programs_options->fetch_assoc()){
            $programs_for_options[] = $program_option_row;
        }
        $result_programs_options->free();
    } else {
        error_log("Error fetching programs for edit schedule options: " . $mysqli->error);
        $page_error = $page_error ? $page_error . " | Error fetching programs." : "Error fetching programs.";
    }
}

// --- Get potential errors and form data from session ---
$errors = $_SESSION['edit_form_errors'] ?? [];
// Use session data if it exists (validation failed), otherwise use fetched schedule data
$form_data = $_SESSION['edit_form_data'] ?? $schedule_data ?? []; 
unset($_SESSION['edit_form_errors'], $_SESSION['edit_form_data']); 

// Also get general DB error from session if set by processing script
$db_error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']); 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXCEED - Edit Schedule Entry</title>
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
        .form-field-group input[type="date"], .form-field-group input[type="time"], .form-field-group select { padding: 0.6rem 0.85rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; width: 100%; background-color: #fff; }
        .form-field-group input.is-invalid, .form-field-group select.is-invalid { border-color: var(--danger-color); } 
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
                        <a href="admin_schedule_management.php">Schedules</a> 
                        <a href="admin_team_management.php">Teams</a> 
                        <a href="#">Reports</a> 
                    </div>
                    <a href="logout.php" class="btn-secondary btn-sm">Logout</a> 
                </div>
            </div>
        </nav>

        <main class="container dashboard-main">
            <div class="page-header">
                <h1>Edit Schedule Entry: <?php echo htmlspecialchars($form_data['programTitle'] ?? 'Entry'); ?></h1>
                 <a href="admin_schedule_management.php" class="btn-secondary">Back to Schedule List</a>
            </div>

            <div class="card form-container">
                
                 <?php // Display general DB error from session if it exists
                    if ($db_error_message): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($db_error_message); ?></div>
                <?php endif; ?>
                 <?php // Display page-level error if connection failed before form processing
                    if ($page_error && !isset($errors['db'])): 
                ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($page_error); ?></div>
                <?php endif; ?>
                 <?php // Display specific DB error from form processing
                    if (isset($errors['db'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db']); ?></div>
                <?php endif; ?>

                <form id="editScheduleForm" action="process_edit_schedule.php" method="POST" novalidate>
                    <input type="hidden" name="ledgerId" value="<?php echo htmlspecialchars($ledger_id_to_edit); ?>">
                    
                    <div class="form-field-group">
                        <label for="programId">Training Program:</label>
                        <select id="programId" name="programId" required class="<?php echo isset($errors['program']) ? 'is-invalid' : ''; ?>">
                            <option value="">-- Select Program --</option>
                             <?php if (!empty($programs_for_options)): ?>
                                <?php foreach ($programs_for_options as $program_opt): ?>
                                    <option value="<?php echo htmlspecialchars($program_opt['trainingProgramID']); ?>"
                                            <?php 
                                                $selected_program = $form_data['programId'] ?? $form_data['trainingProgramID'] ?? null;
                                                echo ($selected_program == $program_opt['trainingProgramID']) ? 'selected' : ''; 
                                            ?>>
                                        <?php echo htmlspecialchars($program_opt['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No programs available</option>
                            <?php endif; ?>
                        </select>
                         <?php if (isset($errors['program'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['program']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-field-group">
                        <label for="eventDate">Date:</label>
                        <input type="date" id="eventDate" name="eventDate" required 
                               value="<?php echo htmlspecialchars($form_data['event_date_formatted'] ?? $form_data['eventDate'] ?? ''); ?>"
                               class="<?php echo isset($errors['date']) ? 'is-invalid' : ''; ?>">
                         <?php if (isset($errors['date'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['date']); ?></span>
                        <?php endif; ?>
                    </div>

                     <div class="form-field-group">
                        <label for="eventTime">Time:</label>
                        <input type="time" id="eventTime" name="eventTime" required
                               value="<?php echo htmlspecialchars($form_data['event_time_formatted'] ?? $form_data['eventTime'] ?? ''); ?>" 
                               class="<?php echo isset($errors['time']) ? 'is-invalid' : ''; ?>">
                         <?php if (isset($errors['time'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['time']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                     <div class="form-field-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status" required class="<?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>">
                            <?php 
                                $current_status = $form_data['status'] ?? $form_data['sessionStatus'] ?? 'Scheduled';
                                $allowed_statuses = ['Scheduled', 'Completed', 'Cancelled', 'InProgress']; // Example
                                foreach ($allowed_statuses as $stat) {
                                    $selected = (strcasecmp($current_status, $stat) == 0) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($stat) . "' $selected>" . htmlspecialchars($stat) . "</option>";
                                }
                            ?>
                        </select>
                         <?php if (isset($errors['status'])): ?>
                            <span class="error-text"><?php echo htmlspecialchars($errors['status']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                         <button type="submit" name="delete_schedule" value="1" class="btn-danger" 
                                 onclick="return confirm('Are you sure you want to delete this schedule entry?');">
                             Delete Entry
                         </button>
                         <div class="right-actions">
                            <a href="admin_schedule_management.php" class="btn-secondary">Cancel</a>
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
