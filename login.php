<?php
require_once 'database.php';

$error = '';
$rateLimit = false;

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_attempt_time'] = time();
}

if (time() - $_SESSION['login_attempt_time'] > 900) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_attempt_time'] = time();
}

if ($_SESSION['login_attempts'] >= 5) {
    $rateLimit = true;
    $timeRemaining = 900 - (time() - $_SESSION['login_attempt_time']);
    $minutesRemaining = ceil($timeRemaining / 60);
    $error = "Too many login attempts. Please try again in $minutesRemaining minute(s).";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rateLimit) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
        $_SESSION['login_attempts']++;
    } else {
        try {
            $database = new Database();
            $conn = $database->connect();
            
            $stmt = $conn->prepare("SELECT u.user_id, u.username, u.password, ut.type_name as user_type 
                                    FROM users u 
                                    INNER JOIN user_types ut ON u.user_type_id = ut.user_type_id 
                                    WHERE u.username = ? AND u.is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['last_activity'] = time();
                
                $_SESSION['login_attempts'] = 0;
                
                header("Location: dashboard.php");
                exit();
            } else {
                $_SESSION['login_attempts']++;
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "System error. Please try again later.";
        }
    }
}

if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $error = "Your session has expired. Please log in again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Faculty Information System</title>
    <link rel="stylesheet" href="CSS/login.css">
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-container">
                <img src="uploads/logo.png" alt="School Logo" class="school-logo">
            </div>
            
            <div class="school-name">
                Western Mindanao State University<br>
            </div>
            <div class="system-name">
                Faculty Information System
            </div>
        </div>
        
        <h2>Sign In</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="off" placeholder="Enter your username">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="Enter your password">
            </div>
            
            <button type="submit" <?php echo $rateLimit ? 'disabled' : ''; ?>>
                <?php echo $rateLimit ? 'Please Wait...' : 'Login'; ?>
            </button>
        </form>
    </div>
</body>
</html>