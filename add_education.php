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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    }
    
    if (empty($errors)) {
        $school_name = trim($_POST['school_name'] ?? '');
        $degree_title = trim($_POST['degree_title'] ?? '');
        $field_of_study = trim($_POST['field_of_study'] ?? '');
        $year_graduated = trim($_POST['year_graduated'] ?? '');
        
        if ($error = validateRequired($school_name, 'School name')) $errors[] = $error;
        if ($error = validateRequired($degree_title, 'Degree title')) $errors[] = $error;
        if ($error = validateRequired($year_graduated, 'Year graduated')) $errors[] = $error;
        
        if (empty($errors) && ($error = validateYear($year_graduated))) $errors[] = $error;
        
        if ($error = validateLength($school_name, 1, 200, 'School name')) $errors[] = $error;
        if ($error = validateLength($degree_title, 1, 100, 'Degree title')) $errors[] = $error;
        if (!empty($field_of_study) && ($error = validateLength($field_of_study, 1, 100, 'Field of study'))) $errors[] = $error;
        
        if (empty($errors)) {
            try {
                $school_name = sanitize($school_name);
                $degree_title = sanitize($degree_title);
                $field_of_study = !empty($field_of_study) ? sanitize($field_of_study) : null;
                $year_graduated = intval($year_graduated);
                
                $stmt = $conn->prepare("INSERT INTO education (faculty_id, school_name, degree_title, field_of_study, year_graduated) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$faculty_id, $school_name, $degree_title, $field_of_study, $year_graduated]);
                
                header("Location: view_faculty_detail.php?id=$faculty_id");
                exit();
            } catch (PDOException $e) {
                $errors[] = "Error adding education record: " . $e->getMessage();
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
    <title>Add Education</title>
    <link rel="stylesheet" href="CSS/add_education.css">
</head>
<body>
    <div class="navbar">
        <h1>Add Education Record</h1>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="view_faculty_detail.php?id=<?php echo $faculty_id; ?>">Back to Profile</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="form-container">
            <h2>Add Education Record</h2>
            
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
                    <label for="degree_title">Degree Title *</label>
                    <input type="text" id="degree_title" name="degree_title" 
                           value="<?php echo htmlspecialchars($_POST['degree_title'] ?? ''); ?>"
                           placeholder="e.g., Bachelor of Science, Master of Arts, PhD" 
                           maxlength="100" required>
                </div>
                
                <div class="form-group">
                    <label for="school_name">School/University Name *</label>
                    <input type="text" id="school_name" name="school_name" 
                           value="<?php echo htmlspecialchars($_POST['school_name'] ?? ''); ?>"
                           maxlength="200" required>
                </div>
                
                <div class="form-group">
                    <label for="field_of_study">Field of Study</label>
                    <input type="text" id="field_of_study" name="field_of_study" 
                           value="<?php echo htmlspecialchars($_POST['field_of_study'] ?? ''); ?>"
                           placeholder="e.g., Computer Science, Mathematics" maxlength="100">
                </div>
                
                <div class="form-group">
                    <label for="year_graduated">Year Graduated *</label>
                    <input type="number" id="year_graduated" name="year_graduated" 
                           value="<?php echo htmlspecialchars($_POST['year_graduated'] ?? ''); ?>"
                           min="1950" max="<?php echo date('Y') + 10; ?>" required>
                </div>
                
                <div class="btn-group">
                    <button type="submit">Add Education</button>
                    <a href="view_faculty_detail.php?id=<?php echo $faculty_id; ?>"><button type="button" class="btn-secondary">Cancel</button></a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>