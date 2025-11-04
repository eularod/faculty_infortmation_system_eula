<?php
require_once 'database.php';
requireLogin();

$database = new Database();
$conn = $database->connect();

$success = '';
$error = '';


$stmt = $conn->prepare("SELECT * FROM departments ORDER BY department_name");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper to check whether a table has a given column
function hasColumn(PDO $conn, $table, $column) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $department_id = $_POST['department_id'] ? $_POST['department_id'] : null;
    
    $photo_url = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $upload_dir = 'uploads/photos/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photo_url = $upload_dir . uniqid() . '.' . $file_ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], $photo_url);
    }
    
    try {
        $hasPhotoColumn = hasColumn($conn, 'faculty', 'photo_url');
        if (isset($_POST['create_account']) && $_POST['create_account'] === '1') {
            $username = sanitize($_POST['username']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

            // Get user_type_id for 'faculty'
            $stmt = $conn->prepare("SELECT user_type_id FROM user_types WHERE LOWER(type_name) = 'faculty' LIMIT 1");
            $stmt->execute();
            $faculty_type = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$faculty_type) {
                throw new Exception("Faculty user type not found in user_types table.");
            }
            $faculty_type_id = $faculty_type['user_type_id'];

            $stmt = $conn->prepare("INSERT INTO users (username, password, user_type_id) VALUES (?, ?, ?)");
            $stmt->execute([$username, $password, $faculty_type_id]);
            $user_id = $conn->lastInsertId();

            if ($hasPhotoColumn) {
                $stmt = $conn->prepare("INSERT INTO faculty (user_id, department_id, first_name, last_name, email, phone, address, photo_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $department_id, $first_name, $last_name, $email, $phone, $address, $photo_url]);
            } else {
                $stmt = $conn->prepare("INSERT INTO faculty (user_id, department_id, first_name, last_name, email, phone, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $department_id, $first_name, $last_name, $email, $phone, $address]);
            }

            $success = "Faculty member added successfully with login credentials!";
        } else {
            if ($hasPhotoColumn) {
                $stmt = $conn->prepare("INSERT INTO faculty (department_id, first_name, last_name, email, phone, address, photo_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$department_id, $first_name, $last_name, $email, $phone, $address, $photo_url]);
            } else {
                $stmt = $conn->prepare("INSERT INTO faculty (department_id, first_name, last_name, email, phone, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$department_id, $first_name, $last_name, $email, $phone, $address]);
            }

            $success = "Faculty member added successfully!";
        }
    } catch (PDOException $e) {
        $error = "Error adding faculty: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Faculty</title>
    <link rel="stylesheet" href="CSS/add_faculty.css">
    <script>
        function toggleAccountFields() {
            const checkbox = document.getElementById('create_account');
            const fields = document.getElementById('account_fields');
            fields.style.display = checkbox.checked ? 'block' : 'none';
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            if (checkbox.checked) {
                username.setAttribute('required', 'required');
                password.setAttribute('required', 'required');
            } else {
                username.removeAttribute('required');
                password.removeAttribute('required');
            }
        }
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        }
    </script>
</head>
<body>
    <div class="navbar">
        <h1>Add New Faculty</h1>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="view_faculty.php">View Faculty</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="form-container">
            <h2>Faculty Information</h2>
            
            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="first_name">First Name <span style="color: red;">*</span></label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name <span style="color: red;">*</span></label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email <span style="color: red;">*</span></label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" pattern="[0-9\-\+\s\(\)]*" maxlength="20" autocomplete="tel" placeholder="e.g., 123-456-7890">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>">
                                <?php echo $dept['department_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="photo">Photo</label>
                    <input type="file" id="photo" name="photo" accept="image/*">
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="create_account" name="create_account" value="1" onchange="toggleAccountFields()">
                        <label for="create_account" style="margin: 0;">Create login account for this faculty</label>
                    </div>
                    
                    <div id="account_fields" class="account-fields">
                        <div class="form-group">
                            <label for="username">Username <span style="color: red;">*</span></label>
                            <input type="text" id="username" name="username" autocomplete="username" value="">
                        </div>
                        <div class="form-group">
                            <label for="password">Password <span style="color: red;">*</span></label>
                            <div style="position: relative;">
                                <input type="password" id="password" name="password">
                                <span onclick="togglePassword()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">👁️</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit">Add Faculty</button>
                    <a href="view_faculty.php"><button type="button" class="btn-secondary">Cancel</button></a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>