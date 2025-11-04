<?php
require_once 'database.php';
requireLogin();

$database = new Database();
$conn = $database->connect();

$success = '';
$errors = [];

$faculty_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Helps convert php shorthand size (e.g. "8M") to bytes
function phpSizeToBytes($size) {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
    $number = preg_replace('/[^0-9\.]/', '', $size);
    if ($number === '') return 0;
    $number = (float) $number;
    switch (strtoupper($unit)) {
        case 'Y': $number *= 1024;
        case 'Z': $number *= 1024;
        case 'E': $number *= 1024;
        case 'P': $number *= 1024;
        case 'T': $number *= 1024;
        case 'G': $number *= 1024;
        case 'M': $number *= 1024;
        case 'K': $number *= 1024;
    }
    return (int) $number;
}

$stmt = $conn->prepare("SELECT * FROM faculty WHERE faculty_id = ?");
$stmt->execute([$faculty_id]);
$faculty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$faculty) {
    header("Location: view_faculty.php");
    exit();
}

// Permission check: Admin can edit anyone, Faculty can only edit themselves
if (!canEditFaculty($faculty_id)) {
    $_SESSION['error'] = "You don't have permission to edit this profile.";
    header("Location: view_faculty.php");
    exit();
}

$stmt = $conn->query("SELECT * FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    $postMax = ini_get('post_max_size');
    $postMaxBytes = phpSizeToBytes($postMax);

    if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
        $errors[] = "Uploaded data exceeds server limit (post_max_size = $postMax). Please upload a smaller file or contact the administrator.";
    } else {
        if (!isset($_POST['csrf_token']) || empty($_POST['csrf_token'])) {
            $errors[] = "Missing security token. This often happens when uploaded files exceed server limits or when the form was submitted from a different tab. Please reload the form and try again.";
        } else {
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                $errors[] = "Invalid security token. Please try again.";
            }
        }
    }

    if (empty($errors)) {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        
        if ($error = validateRequired($first_name, 'First name')) $errors[] = $error;
        if ($error = validateRequired($last_name, 'Last name')) $errors[] = $error;
        if ($error = validateRequired($email, 'Email')) $errors[] = $error;
        
        if (empty($errors) && ($error = validateEmail($email))) $errors[] = $error;
        if (!empty($phone) && ($error = validatePhone($phone))) $errors[] = $error;
        
        if ($error = validateLength($first_name, 1, 50, 'First name')) $errors[] = $error;
        if ($error = validateLength($last_name, 1, 50, 'Last name')) $errors[] = $error;
        if ($error = validateLength($email, 1, 100, 'Email')) $errors[] = $error;
        
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT faculty_id FROM faculty WHERE email = ? AND faculty_id != ?");
            $stmt->execute([$email, $faculty_id]);
            if ($stmt->fetch()) {
                $errors[] = "Email address already exists.";
            }
        }
        
        $photo = $faculty['photo'] ?? null;
        if (!empty($_FILES['photo']['name'])) {
            $uploadResult = handleImageUpload($_FILES['photo'], $faculty['photo'] ?? null);
            if (!$uploadResult['success']) {
                $errors = array_merge($errors, $uploadResult['errors']);
            } else {
                $photo = $uploadResult['path'];
            }
        }

        // Handle photo deletion
        if (isset($_POST['delete_photo']) && $_POST['delete_photo'] == '1' && !empty($faculty['photo']) && file_exists($faculty['photo'])) {
            if (unlink($faculty['photo'])) {
                $photo = null; // Set to NULL in DB
                } else {
                    $errors[] = "Failed to delete the current photo file.";
                }
        }
        
        if (empty($errors)) {
            try {
                $first_name = sanitize($first_name);
                $last_name = sanitize($last_name);
                $email = sanitize($email);
                $phone = !empty($phone) ? sanitize($phone) : null;
                $address = !empty($address) ? sanitize($address) : null;
                
                $stmt = $conn->prepare("UPDATE faculty SET department_id = ?, first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, photo = ? WHERE faculty_id = ?");
                $params = [$department_id, $first_name, $last_name, $email, $phone, $address, $photo, $faculty_id];
                
                $stmt->execute($params);
                $success = "Faculty information updated successfully!";
                
                $stmt = $conn->prepare("SELECT * FROM faculty WHERE faculty_id = ?");
                $stmt->execute([$faculty_id]);
                $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $errors[] = "Error updating faculty: " . $e->getMessage();
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
    <title>Edit Faculty</title>
    <link rel="stylesheet" href="CSS/edit_faculty.css">
</head>
<body>
    <div class="navbar">
        <h1>Edit Faculty</h1>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="view_faculty.php">View Faculty</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="form-container">
            <h2>Edit Faculty Information</h2>
            
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label for="first_name">First Name <span style="color: red;">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($faculty['first_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name <span style="color: red;">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($faculty['last_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email <span style="color: red;">*</span></label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($faculty['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($faculty['phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address"><?php echo htmlspecialchars($faculty['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" <?php echo $faculty['department_id'] == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="photo">Photo</label>
                    <input type="file" id="photo" name="photo" accept="image/*">
                    <?php if (!empty($faculty['photo']) && file_exists($faculty['photo'])): ?>
                        <div class="current-photo">
                            <p>Current Photo:</p>
                            <img src="<?php echo htmlspecialchars($faculty['photo']); ?>" alt="Current photo">
                            <label>
                                <input type="checkbox" name="delete_photo" value="1"> Delete current photo
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="btn-group">
                        <button type="submit">Update Faculty</button>
                        <a href="view_faculty.php"><button type="button" class="btn-secondary">Cancel</button></a>
                    </div>
                </form>
            </div>
        </div>
    </body>
</html>