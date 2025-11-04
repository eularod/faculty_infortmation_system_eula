<?php
require_once 'database.php';
requireAdmin();

$database = new Database();
$conn = $database->connect();

$success = '';
$errors = [];
$user = null;

// Get user ID from URL
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Prevent editing your own account through this page
if ($user_id === intval($_SESSION['user_id'])) {
    header('Location: manage_users.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    }

    if (empty($errors)) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? ''; // Optional - only update if provided
        $user_type_id = !empty($_POST['user_type_id']) ? intval($_POST['user_type_id']) : 0;
        $faculty_id = !empty($_POST['faculty_id']) ? intval($_POST['faculty_id']) : null;

        // sanitize username
        $username = sanitize($username);

        // Server-side validation
        if ($error = validateRequired($username, 'Username')) $errors[] = $error;
        if ($user_type_id <= 0) $errors[] = "Please select a user type.";

        if ($error = validateLength($username, 3, 50, 'Username')) $errors[] = $error;

        // If password is provided, validate it
        if (!empty($password) && strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        }

        // Check username uniqueness (excluding current user)
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $stmt->execute([$username, $user_id]);

            if ($stmt->fetch()) {
                $errors[] = "Username already exists!";
            }
        }

        if (empty($errors)) {
            try {
                $conn->beginTransaction();

                // Update user information
                if (!empty($password)) {
                    // Update with new password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, user_type_id = ? WHERE user_id = ?");
                    $stmt->execute([$username, $hashed_password, $user_type_id, $user_id]);
                } else {
                    // Update without changing password
                    $stmt = $conn->prepare("UPDATE users SET username = ?, user_type_id = ? WHERE user_id = ?");
                    $stmt->execute([$username, $user_type_id, $user_id]);
                }

                // Handle faculty linking
                // First, remove any existing faculty link
                $stmt = $conn->prepare("UPDATE faculty SET user_id = NULL WHERE user_id = ?");
                $stmt->execute([$user_id]);

                // Then add new link if faculty_id is provided and user type is faculty
                if ($faculty_id) {
                    $stmt = $conn->prepare("SELECT type_name FROM user_types WHERE user_type_id = ?");
                    $stmt->execute([$user_type_id]);
                    $type = $stmt->fetchColumn();
                    
                    if ($type && strtolower(trim($type)) === 'faculty') {
                        $stmt = $conn->prepare("UPDATE faculty SET user_id = ? WHERE faculty_id = ?");
                        $stmt->execute([$user_id, $faculty_id]);
                    }
                }

                $conn->commit();
                $success = "User updated successfully!";
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Error updating user: " . $e->getMessage();
            }
        }
    }
}

// Get user data
try {
    $stmt = $conn->prepare("SELECT u.*, ut.type_name as user_type, f.faculty_id as linked_faculty_id 
                           FROM users u 
                           INNER JOIN user_types ut ON u.user_type_id = ut.user_type_id
                           LEFT JOIN faculty f ON u.user_id = f.user_id 
                           WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: manage_users.php');
        exit;
    }

    // Get available faculty members (either unlinked or linked to this user)
    $stmt = $conn->prepare("SELECT faculty_id, first_name, last_name 
                           FROM faculty 
                           WHERE user_id IS NULL OR user_id = ? 
                           ORDER BY last_name, first_name");
    $stmt->execute([$user_id]);
    $available_faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user types for dropdown
    $userTypes = getUserTypes($conn);

} catch (PDOException $e) {
    $errors[] = "Error retrieving user data: " . $e->getMessage();
}

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link rel="stylesheet" href="CSS/edit_user.css">
</head>
<body>
    <div class="navbar">
        <h1>Edit User</h1>
        <div>
            <a href="manage_users.php">Back to Users</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert-error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Edit User: <?php echo htmlspecialchars($user['username']); ?></h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username <span style="color: red;">*</span></label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars($user['username']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">New Password (leave blank to keep current)</label>
                        <div style="position: relative;">
                            <input type="password" id="password" name="password" minlength="6">
                            <span onclick="togglePassword()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">
                                👁️
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="user_type">User Type <span style="color: red;">*</span></label>
                        <select id="user_type" name="user_type_id" required onchange="toggleFacultySelect(this)">
                            <?php foreach ($userTypes as $type): ?>
                                <?php
                                    $tid = intval($type['user_type_id']);
                                    $tname = $type['type_name'];
                                    $slug = strtolower(trim($tname));
                                    $selected = ($tid === intval($user['user_type_id'])) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $tid; ?>" 
                                        data-slug="<?php echo htmlspecialchars($slug); ?>"
                                        <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($tname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="faculty_select_group" style="display: none;">
                        <label for="faculty_id">Link to Faculty (Optional)</label>
                        <select id="faculty_id" name="faculty_id">
                            <option value="">No Faculty Link</option>
                            <?php foreach ($available_faculty as $faculty): ?>
                                <?php 
                                    $selected = ($faculty['faculty_id'] == $user['linked_faculty_id']) ? 'selected' : '';
                                ?>
                                <option value="<?php echo intval($faculty['faculty_id']); ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-success">Update User</button>
                    <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        }

        function toggleFacultySelect(selectElemOrEvent) {
            let selectElem = selectElemOrEvent;
            if (!(selectElem instanceof HTMLSelectElement)) {
                selectElem = document.getElementById('user_type');
            }
            const facultyGroup = document.getElementById('faculty_select_group');
            const selected = selectElem.options[selectElem.selectedIndex];
            const slug = selected ? (selected.dataset.slug || '').toLowerCase() : '';
            if (slug === 'faculty') {
                facultyGroup.style.display = 'block';
            } else {
                facultyGroup.style.display = 'none';
                const faculty = document.getElementById('faculty_id');
                if (faculty) faculty.value = '';
            }
        }

        // Initialize faculty select visibility
        document.addEventListener('DOMContentLoaded', function() {
            const sel = document.getElementById('user_type');
            if (sel) toggleFacultySelect(sel);
        });
    </script>
</body>
</html>