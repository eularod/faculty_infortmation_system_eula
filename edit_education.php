<?php
require_once 'database.php';
requireLogin();

$database = new Database();
$conn = $database->connect();

$education_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($education_id <= 0) {
    header("Location: view_faculty.php");
    exit();
}

$error = '';
$success = '';

try {
    // Fetch education record
    $stmt = $conn->prepare("SELECT * FROM education WHERE education_id = ?");
    $stmt->execute([$education_id]);
    $education = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$education) {
        header("Location: view_faculty.php");
        exit();
    }

    $faculty_id = $education['faculty_id'];

    // Permission check
    if (!canEditFaculty($faculty_id)) {
        $_SESSION['error'] = "You don't have permission to edit this education record.";
        header("Location: view_faculty_detail.php?id=$faculty_id");
        exit();
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $degree_title = trim($_POST['degree_title'] ?? '');
        $school_name = trim($_POST['school_name'] ?? '');
        $field_of_study = trim($_POST['field_of_study'] ?? '');
        $year_graduated = trim($_POST['year_graduated'] ?? '');

        // Validation
        if (empty($degree_title)) {
            $error = 'Degree title is required';
        } elseif (empty($school_name)) {
            $error = 'School name is required';
        } elseif (empty($year_graduated)) {
            $error = 'Year graduated is required';
        } else {
            // Update education record
            $stmt = $conn->prepare("UPDATE education SET degree_title = ?, school_name = ?, field_of_study = ?, year_graduated = ? WHERE education_id = ?");
            
            if ($stmt->execute([$degree_title, $school_name, $field_of_study, $year_graduated, $education_id])) {
                $success = 'Education record updated successfully';
                // Refresh data
                $stmt = $conn->prepare("SELECT * FROM education WHERE education_id = ?");
                $stmt->execute([$education_id]);
                $education = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Failed to update education record';
            }
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "An error occurred. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Education Record</title>
    <link rel="stylesheet" href="CSS/edit_education.css">
</head>
<body>
    <div class="navbar">
        <h1>Edit Education Record</h1>
        <div>
            <a href="view_faculty_detail.php?id=<?php echo $faculty_id; ?>">Back to Profile</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="form-card">
            <h2>Edit Education Record</h2>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="degree_title">Degree Title *</label>
                    <input type="text" id="degree_title" name="degree_title" 
                           value="<?php echo htmlspecialchars($education['degree_title'] ?? ''); ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="school_name">School/University Name *</label>
                    <input type="text" id="school_name" name="school_name" 
                           value="<?php echo htmlspecialchars($education['school_name'] ?? ''); ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="field_of_study">Field of Study</label>
                    <input type="text" id="field_of_study" name="field_of_study" 
                           value="<?php echo htmlspecialchars($education['field_of_study'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="year_graduated">Year Graduated *</label>
                    <input type="number" id="year_graduated" name="year_graduated" 
                           value="<?php echo htmlspecialchars($education['year_graduated'] ?? ''); ?>" 
                           min="1900" max="<?php echo date('Y') + 10; ?>" 
                           required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Education</button>
                    <a href="view_faculty_detail.php?id=<?php echo $faculty_id; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>