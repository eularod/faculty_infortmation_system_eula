<?php
require_once 'database.php';
requireLogin();

$database = new Database();
$conn = $database->connect();

$success = '';
$errors = [];

$faculty_id = isset($_GET['faculty_id']) ? intval($_GET['faculty_id']) : 0;

if (!canEditFaculty($faculty_id)) {
    $_SESSION['error'] = "You don't have permission to add records for this faculty.";
    header("Location: view_faculty.php");
    exit();
}

$stmt = $conn->prepare("SELECT first_name, last_name FROM faculty WHERE faculty_id = ?");
$stmt->execute([$faculty_id]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$faculty) {
    header("Location: view_faculty.php");
    exit();
}

$researchTypes = getResearchTypes($conn);
$researchStatuses = getResearchStatuses($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    }
    
    if (empty($errors)) {
        $research_title = trim($_POST['research_title'] ?? '');
        $research_type_id = !empty($_POST['research_type_id']) ? intval($_POST['research_type_id']) : 0;
        $status_id = !empty($_POST['status_id']) ? intval($_POST['status_id']) : 0;
        $year = trim($_POST['year'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($error = validateRequired($research_title, 'Research title')) $errors[] = $error;
        if ($research_type_id <= 0) $errors[] = "Please select a research type.";
        if ($status_id <= 0) $errors[] = "Please select a status.";
        if ($error = validateRequired($year, 'Year')) $errors[] = $error;
        
        if (empty($errors) && ($error = validateYear($year))) $errors[] = $error;
        if ($error = validateLength($research_title, 1, 255, 'Research title')) $errors[] = $error;
        
        if (empty($errors)) {
            try {
                $research_title = sanitize($research_title);
                $description = !empty($description) ? sanitize($description) : null;
                $year = intval($year);
                
                $stmt = $conn->prepare("INSERT INTO research (faculty_id, research_title, research_type_id, status_id, year, description) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$faculty_id, $research_title, $research_type_id, $status_id, $year, $description]);
                
                header("Location: view_faculty_detail.php?id=$faculty_id");
                exit();
            } catch (PDOException $e) {
                $errors[] = "Error adding research record: " . $e->getMessage();
            }
        }
    }
}

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Research</title>
    <link rel="stylesheet" href="CSS/add_research.css">
</head>
<body>
    <div class="navbar">
        <h1>Add Research Record</h1>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="view_faculty_detail.php?id=<?php echo $faculty_id; ?>">Back to Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="form-container">
            <h2>Add Research/Publication</h2>
            
            <div class="faculty-info">
                <strong>Faculty:</strong> <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <strong>Please fix the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="research_title">Research Title *</label>
                    <input type="text" id="research_title" name="research_title" 
                           value="<?php echo htmlspecialchars($_POST['research_title'] ?? ''); ?>"
                           maxlength="255" required>
                </div>
                
                <div class="form-group">
                    <label for="research_type_id">Type *</label>
                    <select id="research_type_id" name="research_type_id" required>
                        <option value="">Select Type</option>
                        <?php foreach ($researchTypes as $type): ?>
                            <option value="<?php echo $type['research_type_id']; ?>"
                                    <?php echo (isset($_POST['research_type_id']) && $_POST['research_type_id'] == $type['research_type_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($type['type_name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="year">Year *</label>
                    <input type="number" id="year" name="year" 
                           value="<?php echo htmlspecialchars($_POST['year'] ?? ''); ?>"
                           min="1950" max="<?php echo date('Y') + 10; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="status_id">Status *</label>
                    <select id="status_id" name="status_id" required>
                        <option value="">Select Status</option>
                        <?php foreach ($researchStatuses as $status): ?>
                            <option value="<?php echo $status['status_id']; ?>"
                                    <?php echo (isset($_POST['status_id']) && $_POST['status_id'] == $status['status_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($status['status_name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" 
                              placeholder="Brief description of the research..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit">Add Research</button>
                    <a href="view_faculty_detail.php?id=<?php echo $faculty_id; ?>"><button type="button" class="btn-secondary">Cancel</button></a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>