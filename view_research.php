<?php
require_once 'database.php';
requireLogin();

$database = new Database();
$conn = $database->connect();

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? intval($_GET['type']) : 0;
$status_filter = isset($_GET['status']) ? intval($_GET['status']) : 0;
$faculty_filter = isset($_GET['faculty']) ? intval($_GET['faculty']) : 0;

// Updated query to use lookup tables
$query = "SELECT r.research_id, r.research_title, r.year, r.description, 
          rt.type_name as research_type, rs.status_name as status,
          f.first_name, f.last_name, f.faculty_id 
          FROM research r 
          INNER JOIN faculty f ON r.faculty_id = f.faculty_id 
          INNER JOIN research_types rt ON r.research_type_id = rt.research_type_id
          INNER JOIN research_statuses rs ON r.status_id = rs.status_id
          WHERE 1=1";

$params = [];

if ($search) {
    $query .= " AND r.research_title LIKE ?";
    $params[] = "%$search%";
}

if ($type_filter > 0) {
    $query .= " AND r.research_type_id = ?";
    $params[] = $type_filter;
}

if ($status_filter > 0) {
    $query .= " AND r.status_id = ?";
    $params[] = $status_filter;
}

if ($faculty_filter > 0 && isAdmin()) {
    $query .= " AND r.faculty_id = ?";
    $params[] = $faculty_filter;
}

if (!isAdmin()) {
    $query .= " AND f.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

$query .= " ORDER BY r.year DESC, r.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$research_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get lookup data for filters
$researchTypes = getResearchTypes($conn);
$researchStatuses = getResearchStatuses($conn);

$faculty_list = [];
if (isAdmin()) {
    $stmt = $conn->query("SELECT faculty_id, first_name, last_name FROM faculty ORDER BY last_name, first_name");
    $faculty_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Research</title>
    <link rel="stylesheet" href="CSS/view_research.css">
</head>
<body>
    <div class="navbar">
        <h1>Research & Publications</h1>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="view_faculty.php">Faculty</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo count($research_list); ?></h3>
                <p><?php echo $search || $type_filter || $status_filter || $faculty_filter ? 'Filtered' : 'Total'; ?> Research</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($research_list, function($r) { return strtolower($r['status']) === 'published'; })); ?></h3>
                <p>Published</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($research_list, function($r) { return strtolower($r['status']) === 'ongoing'; })); ?></h3>
                <p>Ongoing</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($research_list, function($r) { return strtolower($r['status']) === 'completed'; })); ?></h3>
                <p>Completed</p>
            </div>
        </div>

        <div class="controls">
            <form method="GET">
                <div class="filter-row">
                    <input type="text" name="search" placeholder="Search by title..." value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="type">
                        <option value="">All Types</option>
                        <?php foreach ($researchTypes as $type): ?>
                            <option value="<?php echo $type['research_type_id']; ?>" <?php echo $type_filter == $type['research_type_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($type['type_name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status">
                        <option value="">All Status</option>
                        <?php foreach ($researchStatuses as $status): ?>
                            <option value="<?php echo $status['status_id']; ?>" <?php echo $status_filter == $status['status_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($status['status_name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <?php if (isAdmin()): ?>
                    <select name="faculty">
                        <option value="">All Faculty</option>
                        <?php foreach ($faculty_list as $fac): ?>
                            <option value="<?php echo $fac['faculty_id']; ?>" <?php echo $faculty_filter == $fac['faculty_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($fac['first_name'] . ' ' . $fac['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="view_research.php" class="btn btn-warning">Clear</a>
                </div>
            </form>
        </div>

        <?php if (count($research_list) > 0): ?>
            <div class="research-grid">
                <?php foreach ($research_list as $research): ?>
                    <?php 
                    // Make status comparison case-insensitive
                    $status_lower = strtolower($research['status']);
                    $status_badge = $status_lower === 'published' ? 'badge-success' : 
                                   ($status_lower === 'ongoing' ? 'badge-warning' : 'badge-info');
                    
                    // Make type comparison case-insensitive
                    $type_lower = strtolower($research['research_type']);
                    $type_badge = 'badge-' . $type_lower;
                    ?>
                    <div class="research-card">
                        <div class="research-faculty">
                            <?php echo htmlspecialchars($research['first_name'] . ' ' . $research['last_name']); ?>
                        </div>
                        
                        <h3><?php echo htmlspecialchars($research['research_title']); ?></h3>
                        
                        <div class="research-meta">
                            <span class="badge <?php echo $type_badge; ?>"><?php echo htmlspecialchars(ucfirst($research['research_type'])); ?></span>
                            <span class="badge <?php echo $status_badge; ?>"><?php echo htmlspecialchars(ucfirst($research['status'])); ?></span>
                        </div>
                        
                        <div class="research-meta">
                            <strong>Year:</strong> <?php echo htmlspecialchars($research['year']); ?>
                        </div>
                        
                        <?php if ($research['description']): ?>
                            <div class="research-description">
                                <?php echo htmlspecialchars(substr($research['description'], 0, 150)); ?>
                                <?php echo strlen($research['description']) > 150 ? '...' : ''; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="research-actions">
                            <a href="view_faculty_detail.php?id=<?php echo $research['faculty_id']; ?>" class="btn btn-primary">View Faculty</a>
                            <?php
                            // Admin can edit/delete all research
                            // Faculty can only edit/delete their own research
                            $canEdit = isAdmin() || (isset($_SESSION['user_id']) && $research['faculty_id'] == getUserFacultyId($_SESSION['user_id'], $conn));
                            ?>
                            <?php if ($canEdit): ?>
                            <a href="edit_research.php?id=<?php echo $research['research_id']; ?>" class="btn btn-warning">Edit</a>
                            <a href="delete_research.php?id=<?php echo $research['research_id']; ?>&return=view_research" class="btn btn-danger" onclick="return confirm('Delete this research?')">Delete</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h2>No Research Found</h2>
                <p style="color: #999; margin-top: 10px;">Try adjusting your search filters or add new research records.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>