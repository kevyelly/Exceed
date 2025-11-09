<?php

$servername = "localhost";
$username = "root";  
$password = ""; 
$dbname = "trainingmanagementsystem";

$conn = new mysqli($servername, $username, $password, $dbname);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');


$userQuery = "SELECT COUNT(*) AS TotalUsers FROM tblUser";
$userResult = $conn->query($userQuery);
$userRow = $userResult->fetch_assoc();
$totalUsers = $userRow['TotalUsers'];

$trainerQuery = "SELECT COUNT(*) AS ActiveTrainers FROM tblTrainer 
                 JOIN tblTrainingProgram ON tblTrainer.TrainerID = tblTrainingProgram.TrainerID
                 JOIN tblScheduleLedger ON tblTrainingProgram.TrainingProgramID = tblScheduleLedger.TrainingProgramID
                 WHERE tblScheduleLedger.Status = 'Scheduled'
                 AND tblScheduleLedger.ScheduleID IN (
                    SELECT ScheduleID FROM tblSchedule 
                    WHERE Date BETWEEN '$startDate' AND '$endDate'
                 )";
$trainerResult = $conn->query($trainerQuery);
$trainerRow = $trainerResult->fetch_assoc();
$activeTrainers = $trainerRow['ActiveTrainers'];

$traineeQuery = "SELECT COUNT(DISTINCT tblTrainee.TraineeID) AS ActiveTrainees 
                 FROM tblTrainee
                 JOIN tblTeam ON tblTrainee.TeamID = tblTeam.TeamID
                 JOIN tblTrainingProgram ON tblTeam.TrainingProgramID = tblTrainingProgram.TrainingProgramID
                 JOIN tblScheduleLedger ON tblTrainingProgram.TrainingProgramID = tblScheduleLedger.TrainingProgramID
                 WHERE tblScheduleLedger.Status = 'Scheduled'
                 AND tblScheduleLedger.ScheduleID IN (
                    SELECT ScheduleID FROM tblSchedule 
                    WHERE Date BETWEEN '$startDate' AND '$endDate'
                 )";
$traineeResult = $conn->query($traineeQuery);
$traineeRow = $traineeResult->fetch_assoc();
$activeTrainees = $traineeRow['ActiveTrainees'];

$programQuery = "SELECT COUNT(*) AS TotalPrograms FROM tblTrainingProgram";
$programResult = $conn->query($programQuery);
$programRow = $programResult->fetch_assoc();
$totalPrograms = $programRow['TotalPrograms'];

$courseQuery = "SELECT 
                tp.Title AS ProgramTitle,
                tp.Description,
                CONCAT(u.FName, ' ', u.LName) AS TrainerName,
                (SELECT COUNT(*) FROM tblTeam WHERE TrainingProgramID = tp.TrainingProgramID) AS EnrolledTeams,
                CASE 
                    WHEN EXISTS (SELECT 1 FROM tblScheduleLedger sl 
                                WHERE sl.TrainingProgramID = tp.TrainingProgramID 
                                AND sl.Status = 'Scheduled'
                                AND sl.ScheduleID IN (
                                    SELECT ScheduleID FROM tblSchedule 
                                    WHERE Date BETWEEN '$startDate' AND '$endDate'
                                )) 
                    THEN 'Active' 
                    ELSE 'Inactive' 
                END AS Status
              FROM tblTrainingProgram tp
              JOIN tblTrainer t ON tp.TrainerID = t.TrainerID
              JOIN tblUser u ON t.UID = u.UserID
              ORDER BY ProgramTitle";
$courseResult = $conn->query($courseQuery);

$teamQuery = "SELECT 
              t.TeamName,
              COUNT(tr.TraineeID) AS NumberOfTrainees
              FROM tblTeam t
              LEFT JOIN tblTrainee tr ON t.TeamID = tr.TeamID
              GROUP BY t.TeamName
              ORDER BY t.TeamName";
$teamResult = $conn->query($teamQuery);

$teamNames = [];
$teamSizes = [];
while($teamRow = $teamResult->fetch_assoc()) {
    $teamNames[] = $teamRow['TeamName'];
    $teamSizes[] = $teamRow['NumberOfTrainees'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EXCEED - Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="dashboard">
        <nav class="dashboard-nav">
            <div class="container">
                <div class="nav-content">
                    <div class="nav-left">
                        <a href="index.php" class="btn-back">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                            Back
                        </a>
                        <h1>EXCEED Dashboard</h1>
                    </div>
                    <div class="date-filter">
                        <form class="filter-form" action="" method="get">
                            <div class="date-inputs">
                                <div class="input-group">
                                    <label for="start-date">From:</label>
                                    <input type="date" id="start-date" name="start_date" value="<?php echo $startDate; ?>">
                                </div>
                                <div class="input-group">
                                    <label for="end-date">To:</label>
                                    <input type="date" id="end-date" name="end_date" value="<?php echo $endDate; ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn-primary">Apply Filter</button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <main class="container dashboard-main">
            <div class="stats-grid">
                <div class="stat-card">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <h3>Total Users</h3>
                    <p><?php echo $totalUsers; ?></p>
                </div>
                <div class="stat-card">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/></svg>
                    <h3>Active Trainers</h3>
                    <p><?php echo $activeTrainers; ?></p>
                </div>
                <div class="stat-card">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    <h3>Active Trainees</h3>
                    <p><?php echo $activeTrainees; ?></p>
                </div>
                <div class="stat-card">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    <h3>Training Programs</h3>
                    <p><?php echo $totalPrograms; ?></p>
                </div>
            </div>

            <div class="overview-grid">
                <div class="card">
                    <h2>Project Overview</h2>
                    <div class="overview-content">
                        <div>
                            <h3>Problem Statement</h3>
                            <p>Manually assigning employees to training sessions and managing their
                                schedules is a cumbersome and error-prone process in a corporate
                                context. This often results in mismanaged team allocations,
                                overlapping training schedules, and difficulty tracking progress.</p>
                        </div>
                        <div>
                            <h3>Solution</h3>
                            <p>A centralized training management system designed to streamline team
                                assignments and optimize scheduling for improved coordination. It
                                enables administrators to efficiently organize training sessions, track
                                employee progress in real-time, and manage team allocations based on
                                availability and skill levels.</p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2>Team Sizes</h2>
                    <div class="chart-placeholder">
                        <div class="bar-chart">
                            <?php
                            $maxValue = max($teamSizes) > 0 ? max($teamSizes) : 1;
                            foreach ($teamSizes as $index => $size) {
                                $percentage = ($size / $maxValue) * 100;
                                echo "<div class='bar' style='height: {$percentage}%'><span>{$teamNames[$index]}</span></div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Course Overview</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Program Title</th>
                                <th>Description</th>
                                <th>Trainer</th>
                                <th>Teams Enrolled</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($courseResult->num_rows > 0) {
                                while($row = $courseResult->fetch_assoc()) {
                                    $statusClass = ($row['Status'] == 'Active') ? 'active' : 'inactive';
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['ProgramTitle']) . "</td>";
                                    echo "<td>" . htmlspecialchars(substr($row['Description'], 0, 100)) . (strlen($row['Description']) > 100 ? '...' : '') . "</td>";
                                    echo "<td>" . htmlspecialchars($row['TrainerName']) . "</td>";
                                    echo "<td>" . $row['EnrolledTeams'] . "</td>";
                                    echo "<td><span class='status-badge {$statusClass}'>" . $row['Status'] . "</span></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5'>No courses found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

<?php
// Close connection
$conn->close();
?>