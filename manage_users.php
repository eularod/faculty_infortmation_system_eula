<?php
require_once 'database.php';
requireAdmin();

$database = new Database();
$conn = $database->connect();

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    // CSRF validation
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    }

    if (empty($errors)) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $user_type_id = !empty($_POST['user_type_id']) ? intval($_POST['user_type_id']) : 0;
        $faculty_id = !empty($_POST['faculty_id']) ? intval($_POST['faculty_id']) : null;

        // sanitize early so uniqueness check uses the cleaned value
        $username = sanitize($username);

        // Server-side validation
        if ($error = validateRequired($username, 'Username')) $errors[] = $error;
        if ($error = validateRequired($password, 'Password')) $errors[] = $error;
        if ($user_type_id <= 0) $errors[] = "Please select a user type.";

        if (empty($errors) && strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        }

        if ($error = validateLength($username, 3, 50, 'Username')) $errors[] = $error;

        // Check username uniqueness
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->execute([$username]);

            if ($stmt->fetch()) {
                $errors[] = "Username already exists!";
            }
        }

        if (empty($errors)) {
            try {
                $conn->beginTransaction();

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users (username, password, user_type_id) VALUES (?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $user_type_id]);
                $user_id = $conn->lastInsertId();

                // Link to faculty only when a faculty id was provided and the selected type is faculty
                if ($faculty_id) {
                    // verify the selected user_type is actually 'faculty' (case-insensitive)
                    $stmt = $conn->prepare("SELECT type_name FROM user_types WHERE user_type_id = ?");
                    $stmt->execute([$user_type_id]);
                    $type = $stmt->fetchColumn();
                    if ($type && strtolower(trim($type)) === 'faculty') {
                        $stmt = $conn->prepare("UPDATE faculty SET user_id = ? WHERE faculty_id = ?");
                        $stmt->execute([$user_id, $faculty_id]);
                    }
                }

                $conn->commit();
                $success = "User created successfully!";
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Error creating user: " . $e->getMessage();
            }
        }
    }
}

// Handle deletion via POST to include CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        $user_id = intval($_POST['user_id'] ?? 0);

        if ($user_id === intval($_SESSION['user_id'])) {
            $errors[] = "You cannot delete your own account!";
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $success = "User deleted successfully!";
            } catch (PDOException $e) {
                $errors[] = "Error deleting user: " . $e->getMessage();
            }
        }
    }
}

// Updated query to use user_types lookup table
$stmt = $conn->query("SELECT u.user_id, u.username, u.created_at, ut.type_name as user_type,
                      f.first_name, f.last_name, f.faculty_id 
                      FROM users u 
                      INNER JOIN user_types ut ON u.user_type_id = ut.user_type_id
                      LEFT JOIN faculty f ON u.user_id = f.user_id 
                      ORDER BY u.created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("SELECT faculty_id, first_name, last_name FROM faculty WHERE user_id IS NULL ORDER BY last_name, first_name");
$unlinked_faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user types for dropdown
$userTypes = getUserTypes($conn);

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="CSS/manage_users.css">
</head>
<body>
    <div class="navbar">
        <h1>Manage Users</h1>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="view_faculty.php">Faculty</a>
            <a href="view_research.php">Research</a>
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

        <div class="card stats" style="display:flex; gap:10px; margin-bottom:20px;">
            <div class="stat-card card" style="flex:1; text-align:center;">
                <h3><?php echo count($users); ?></h3>
                <p>Total Users</p>
            </div>
            <div class="stat-card card" style="flex:1; text-align:center;">
                <h3><?php echo count(array_filter($users, function($u) { return strtolower($u['user_type']) === 'administrator' || strtolower($u['user_type']) === 'admin'; })); ?></h3>
                <p>Administrators</p>
            </div>
            <div class="stat-card card" style="flex:1; text-align:center;">
                <h3><?php echo count(array_filter($users, function($u) { return strtolower($u['user_type']) === 'faculty'; })); ?></h3>
                <p>Faculty Users</p>
            </div>
            <div class="stat-card card" style="flex:1; text-align:center;">
                <h3><?php echo count($unlinked_faculty); ?></h3>
                <p>Faculty Without Login</p>
            </div>
        </div>

        <div class="card">
            <h2>Create New User</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username <span style="color: red;">*</span></label>
                        <input type="text" id="username" name="username" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password <span style="color: red;">*</span></label>
                        <div style="position: relative;">
                            <input type="password" id="password" name="password" required minlength="6">
                            <span onclick="togglePassword()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">
                                👁️
                            </span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="user_type">User Type <span style="color: red;">*</span></label>
                        <select id="user_type" name="user_type_id" required onchange="toggleFacultySelect(this)">
                            <option value="">Select Type</option>
                            <?php foreach ($userTypes as $type): ?>
                                <?php
                                    $tid = intval($type['user_type_id']);
                                    $tname = $type['type_name'];
                                    $slug = strtolower(trim($tname));
                                ?>
                                <option value="<?php echo $tid; ?>" data-slug="<?php echo htmlspecialchars($slug); ?>">
                                    <?php echo htmlspecialchars($tname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="faculty_select_group" style="display: none;">
                        <label for="faculty_id">Link to Faculty (Optional)</label>
                        <select id="faculty_id" name="faculty_id">
                            <option value="">No Faculty Link</option>
                            <?php foreach ($unlinked_faculty as $faculty): ?>
                                <option value="<?php echo intval($faculty['faculty_id']); ?>">
                                    <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-success">Create User</button>
            </form>
        </div>

        <div class="card">
            <h2>System Users</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Type</th>
                        <th>Linked Faculty</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <?php
                        $typeSlug = strtolower(preg_replace('/\s+/', '_', $user['user_type']));
                    ?>
                    <tr>
                        <td><?php echo intval($user['user_id']); ?></td>
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                        <td>
                            <span class="badge badge-<?php echo htmlspecialchars($typeSlug); ?>">
                                <?php echo htmlspecialchars(ucfirst($user['user_type'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($user['faculty_id'])): ?>
                                <a href="view_faculty_detail.php?id=<?php echo intval($user['faculty_id']); ?>">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </a>
                            <?php else: ?>
                                <span style="color: #999;">No faculty link</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($user['created_at']))); ?></td>
                        <td>
                            <div class="actions">
                                <?php if (intval($user['user_id']) !== intval($_SESSION['user_id'])): ?>
                                    <a href="edit_user.php?id=<?php echo intval($user['user_id']); ?>" class="btn btn-primary">Edit</a>
                                    <form method="POST" onsubmit="return confirm('Delete user <?php echo htmlspecialchars($user['username']); ?>?')" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo intval($user['user_id']); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <button type="submit" class="btn btn-danger">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;">Current User</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

        // Initialize visibility if a default is selected
        document.addEventListener('DOMContentLoaded', function() {
            const sel = document.getElementById('user_type');
            if (sel) toggleFacultySelect(sel);
        });
    </script>
</body>
</html>
