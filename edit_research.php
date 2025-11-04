<?php
require_once 'database.php';
requireLogin();

$database = new Database();
$conn = $database->connect();

// Validate research_id
$research_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($research_id <= 0) {
    header("Location: view_faculty.php");
    exit();
}

$error = '';
$success = '';

try {
    // Fetch research record
    $stmt = $conn->prepare("SELECT * FROM research WHERE research_id = ?");
    $stmt->execute([$research_id]);
    $research = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$research) {
        header("Location: view_faculty.php");
        exit();
    }

    $faculty_id = $research['faculty_id'];

    // Fetch research types
    $stmt = $conn->prepare("SELECT * FROM research_types ORDER BY type_name");
    $stmt->execute();
    $research_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch research statuses
    $stmt = $conn->prepare("SELECT * FROM research_statuses ORDER BY status_name");
    $stmt->execute();
    $research_statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Permission check
    if (!canEditFaculty($faculty_id)) {
        $_SESSION['error'] = "You don't have permission to edit this education record.";
        header("Location: view_faculty_detail.php?id=$faculty_id");
        exit();
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $research_title = trim($_POST['research_title'] ?? '');
        $research_type_id = intval($_POST['research_type_id'] ?? 0);
        $status_id = intval($_POST['status_id'] ?? 0);
        $year = trim($_POST['year'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // Validation
        if (empty($research_title)) {
            $error = 'Research title is required';
        } elseif ($research_type_id <= 0) {
            $error = 'Please select a research type';
        } elseif ($status_id <= 0) {
            $error = 'Please select a status';
        } elseif (empty($year)) {
            $error = 'Year is required';
        } elseif (!is_numeric($year) || $year < 1900 || $year > date('Y') + 10) {
            $error = 'Please enter a valid year';
        } else {
            // Update research record
            $stmt = $conn->prepare("UPDATE research SET research_title = ?, research_type_id = ?, status_id = ?, year = ?, description = ? WHERE research_id = ?");
            
            if ($stmt->execute([$research_title, $research_type_id, $status_id, $year, $description, $research_id])) {
                $success = 'Research record updated successfully';
                // Refresh the data
                $stmt = $conn->prepare("SELECT * FROM research WHERE research_id = ?");
                $stmt->execute([$research_id]);
                $research = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Failed to update research record';
            }
        }
    }
} catch (PDOException $e) {
    error_log("Database error in edit_research.php: " . $e->getMessage());
    $error = "An error occurred. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Research Record</title>
    <link rel="stylesheet" href="CSS/edit_research.css">
</head>
<body>
    <div class="navbar">
        <h1>Edit Research Record</h1>
        <div>
            <a href="view_faculty_detail.php?id=<?php echo $faculty_id; ?>">Back to Profile</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="form-card">
            <h2>Edit Research Record</h2>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="research_title">Research Title *</label>
                    <input type="text" id="research_title" name="research_title" 
                           value="<?php echo htmlspecialchars($research['research_title'] ?? ''); ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="research_type_id">Research Type *</label>
                    <select id="research_type_id" name="research_type_id" required>
                        <option value="">-- Select Research Type --</option>
                        <?php foreach ($research_types as $type): ?>
                            <option value="<?php echo $type['research_type_id']; ?>" 
                                    <?php echo ($research['research_type_id'] == $type['research_type_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['type_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status_id">Status *</label>
                    <select id="status_id" name="status_id" required>
                        <option value="">-- Select Status --</option>
                        <?php foreach ($research_statuses as $status): ?>
                            <option value="<?php echo $status['status_id']; ?>" 
                                    <?php echo ($research['status_id'] == $status['status_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status['status_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="year">Year *</label>
                    <input type="number" id="year" name="year" 
                           value="<?php echo htmlspecialchars($research['year'] ?? ''); ?>" 
                           min="1900" max="<?php echo date('Y') + 10; ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?php echo htmlspecialchars($research['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Research</button>
                    <a href="view_faculty_detail.php?id=<?php echo $faculty_id; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>