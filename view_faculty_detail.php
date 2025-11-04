<?php
require_once 'database.php';
requireLogin();

$database = new Database();
$conn = $database->connect();

// Validate faculty_id
$faculty_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($faculty_id <= 0) {
    header("Location: view_faculty.php");
    exit();
}

try {
    // Fetch faculty information
    $stmt = $conn->prepare("SELECT f.*, d.department_name FROM faculty f LEFT JOIN departments d ON f.department_id = d.department_id WHERE f.faculty_id = ?");
    $stmt->execute([$faculty_id]);
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$faculty) {
        header("Location: view_faculty.php");
        exit();
    }

    // Fetch education records
    $stmt = $conn->prepare("SELECT * FROM education WHERE faculty_id = ? ORDER BY year_graduated DESC");
    $stmt->execute([$faculty_id]);
    $education = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch research records with lookup tables
    $stmt = $conn->prepare("SELECT r.research_id, r.research_title, r.year, r.description, 
                            rt.type_name as research_type, rs.status_name as status
                            FROM research r
                            INNER JOIN research_types rt ON r.research_type_id = rt.research_type_id
                            INNER JOIN research_statuses rs ON r.status_id = rs.status_id
                            WHERE r.faculty_id = ? 
                            ORDER BY r.year DESC");
    $stmt->execute([$faculty_id]);
    $research = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error in faculty_profile.php: " . $e->getMessage());
    die("An error occurred while loading the profile. Please try again later.");
}

// Function to get badge class based on status
function getStatusBadgeClass($status) {
    $status_lower = strtolower($status);
    switch ($status_lower) {
        case 'published':
            return 'badge-success';
        case 'ongoing':
        case 'in progress':
            return 'badge-warning';
        case 'completed':
            return 'badge-info';
        case 'pending':
            return 'badge-secondary';
        default:
            return 'badge-info';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Profile - <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?></title>
    <link rel="stylesheet" href="CSS/view_faculty_detail.css">
</head>
<body>
    <div class="navbar">
        <h1>Faculty Profile</h1>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="view_faculty.php">Back to List</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="profile-header">
            <?php if (!empty($faculty['photo']) && file_exists($faculty['photo'])): ?>
                <img src="<?php echo htmlspecialchars($faculty['photo']); ?>" alt="Faculty Photo" class="photo-thumb">
            <?php else: ?>
                <div class="profile-photo">
                    <?php echo strtoupper(substr($faculty['first_name'], 0, 1) . substr($faculty['last_name'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?></h2>
                
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($faculty['email'] ?? 'N/A'); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo htmlspecialchars($faculty['phone'] ?? 'N/A'); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Department:</span>
                    <span class="info-value"><?php echo htmlspecialchars($faculty['department_name'] ?? 'N/A'); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value"><?php echo htmlspecialchars($faculty['address'] ?? 'N/A'); ?></span>
                </div>
                
                <div class="action-buttons">
                    <?php
                        $canEdit = canEditFaculty($faculty_id);
                        $canDelete = canDeleteFaculty($faculty_id);
                    ?>

                    <?php if ($canEdit): ?>
                        <a href="edit_faculty.php?id=<?php echo $faculty_id; ?>" class="btn btn-warning">Edit Profile</a>
                    <?php endif; ?>

                    <?php if ($canDelete): ?>
                        <a href="delete_faculty.php?id=<?php echo $faculty_id; ?>" class="btn btn-danger" onclick="return confirm('Delete this faculty?')">Delete</a>
                    <?php endif; ?>
                    <a href="add_education.php?faculty_id=<?php echo $faculty_id; ?>" class="btn btn-success">Add Education</a>
                    <a href="add_research.php?faculty_id=<?php echo $faculty_id; ?>" class="btn btn-success">Add Research</a>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h3>Educational Background</h3>
            <?php if (count($education) > 0): ?>
                <?php foreach ($education as $edu): ?>
                    <div class="record-card">
                        <h4><?php echo htmlspecialchars($edu['degree_title'] ?? 'N/A'); ?></h4>
                        <div class="record-meta">
                            <strong><?php echo htmlspecialchars($edu['school_name'] ?? 'N/A'); ?></strong>
                            <?php if (!empty($edu['field_of_study'])): ?>
                                | <?php echo htmlspecialchars($edu['field_of_study']); ?>
                            <?php endif; ?>
                            | Graduated: <?php echo htmlspecialchars($edu['year_graduated'] ?? 'N/A'); ?>
                            <a href="edit_education.php?id=<?php echo $edu['education_id']; ?>" class="btn" style="padding: 4px 10px; font-size: 12px; margin-left: 10px;">Edit</a>
                            <a href="delete_education.php?id=<?php echo $edu['education_id']; ?>&faculty_id=<?php echo $faculty_id; ?>" class="btn btn-danger" style="padding: 4px 10px; font-size: 12px;" onclick="return confirm('Delete this education record?')">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">No education records found</div>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h3>Research & Publications</h3>
            <?php if (count($research) > 0): ?>
                <?php foreach ($research as $res): ?>
                    <div class="record-card">
                        <h4>
                            <?php echo htmlspecialchars($res['research_title'] ?? 'N/A'); ?>
                            <span class="badge <?php echo getStatusBadgeClass($res['status']); ?>">
                                <?php echo htmlspecialchars($res['status'] ?? 'N/A'); ?>
                            </span>
                        </h4>
                        <div class="record-meta">
                            <strong>Type:</strong> <?php echo htmlspecialchars($res['research_type'] ?? 'N/A'); ?> | 
                            <strong>Year:</strong> <?php echo htmlspecialchars($res['year'] ?? 'N/A'); ?>
                            <a href="edit_research.php?id=<?php echo $res['research_id']; ?>" class="btn" style="padding: 4px 10px; font-size: 12px; margin-left: 10px;">Edit</a>
                            <a href="delete_research.php?id=<?php echo $res['research_id']; ?>&faculty_id=<?php echo $faculty_id; ?>" class="btn btn-danger" style="padding: 4px 10px; font-size: 12px;" onclick="return confirm('Delete this research record?')">Delete</a>
                        </div>
                        <?php if (!empty($res['description'])): ?>
                            <p style="margin-top: 10px; color: #666; font-size: 14px;"><?php echo nl2br(htmlspecialchars($res['description'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">No research records found</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>