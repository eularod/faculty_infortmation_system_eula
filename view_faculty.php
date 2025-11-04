<?php
require_once 'database.php';
requireLogin();

$database = new Database();
$conn = $database->connect();

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? sanitize($_GET['department']) : '';

$query = "SELECT f.*, d.department_name FROM faculty f LEFT JOIN departments d ON f.department_id = d.department_id WHERE 1=1";

$params = [];

if ($search) {
    $query .= " AND (f.first_name LIKE ? OR f.last_name LIKE ? OR f.email LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($department_filter) {
    $query .= " AND f.department_id = ?";
    $params[] = $department_filter;
}

$query .= " ORDER BY f.last_name, f.first_name";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$faculty_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_dept = $conn->query("SELECT * FROM departments ORDER BY department_name");
$departments = $stmt_dept->fetchAll(PDO::FETCH_ASSOC);

// Get current user's faculty_id if they are faculty
$currentUserFacultyId = null;
if (!isAdmin()) {
    $currentUserFacultyId = getUserFacultyId($_SESSION['user_id'], $conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Faculty</title>
    <link rel="stylesheet" href="CSS/view_faculty.css">
</head>
<body>
    <div class="navbar">
        <h1>Faculty List</h1>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="controls">
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['department_id']; ?>" <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="view_faculty.php" class="btn btn-warning">Clear</a>
                <?php if (isAdmin()): ?>
                <a href="add_faculty.php" class="btn btn-success">Add New Faculty</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Department</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($faculty_list) > 0): ?>
                        <?php foreach ($faculty_list as $row): ?>
                        <?php
                            $isOwnProfile = !isAdmin() && $currentUserFacultyId == $row['faculty_id'];
                            $canEdit = isAdmin() || $isOwnProfile;
                            $canDelete = isAdmin();
                        ?>
                        <tr>
                            <td>
                                <?php if (!empty($row['photo']) && file_exists($row['photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($row['photo']); ?>" alt="Photo" class="photo-thumb">
                                <?php else: ?>
                                    <div class="photo-thumb" style="background: #ddd; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #666;">
                                        <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                <?php if ($isOwnProfile): ?>
                                    <span class="badge">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view_faculty_detail.php?id=<?php echo $row['faculty_id']; ?>" class="btn btn-primary">View</a>
                                    
                                    <?php if ($canEdit): ?>
                                    <a href="edit_faculty.php?id=<?php echo $row['faculty_id']; ?>" class="btn btn-warning">Edit</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($canDelete): ?>
                                    <a href="delete_faculty.php?id=<?php echo $row['faculty_id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this faculty member?')">Delete</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #999;">No faculty members found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>