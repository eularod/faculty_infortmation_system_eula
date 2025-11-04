<?php
require_once 'database.php';
requireLogin();

$database = new Database();
$conn = $database->connect();

$total_faculty_stmt = $conn->query("SELECT COUNT(*) AS count FROM faculty");
$total_faculty = $total_faculty_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$total_research_stmt = $conn->query("SELECT COUNT(*) AS count FROM research");
$total_research = $total_research_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$total_departments_stmt = $conn->query("SELECT COUNT(*) AS count FROM departments");
$total_departments = $total_departments_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$recent_faculty = $conn->query("SELECT f.*, d.department_name FROM faculty f LEFT JOIN departments d ON f.department_id = d.department_id");

$conn = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="CSS/dashboard.css">
</head>
<body>
        <div class="navbar">
            <h1>Faculty Information System</h1>
            <div class="user-info">
                <span>Welcome, <?php echo $_SESSION['username']; ?> (<?php echo ucfirst($_SESSION['user_type']); ?>)</span>
                <a href="logout.php">Logout</a>
            </div>
        </div>
        
        <div class="container">
            <div class="stats">
                <div class="stat-card">
                    <h3><?php echo $total_faculty; ?></h3>
                    <p>Total Faculty</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $total_research; ?></h3>
                    <p>Total Research</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $total_departments; ?></h3>
                    <p>Departments</p>
                </div>
            </div>
            
            <div class="menu">
                <div class="menu-item">
                    <a href="view_faculty.php">View Faculty</a>
                </div>
                <div class="menu-item">
                    <a href="add_faculty.php">Add Faculty</a>
                </div>
                <div class="menu-item">
                    <a href="view_research.php">View Research</a>
                </div>
                
                 <?php if (isAdmin()): ?>
                <div class="menu-item">
                    <a href="manage_users.php">Manage Users</a>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="recent-section">
                <h2>Recent Faculty Members</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $recent_faculty->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                            <td><?php echo $row['email']; ?></td>
                            <td><?php echo $row['department_name'] ?? 'N/A'; ?></td>
                            <td><a href="view_faculty_detail.php?id=<?php echo $row['faculty_id']; ?>" class="btn">View</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
</body>
</html>
