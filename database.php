<?php
class Database {
    private $host = "localhost";
    private $user = "root";
    private $password = "";
    private $dbname = "faculty_info_system";
    
    protected $conn;   

    public function connect() {
        try {
            $this->conn = new PDO(
                "mysql:host=$this->host;dbname=$this->dbname;charset=utf8mb4", 
                $this->user, 
                $this->password
            );
            
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            return $this->conn;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Unable to connect to database. Please contact the administrator.");
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout after 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_type']) && strtolower($_SESSION['user_type']) === 'administrator';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: dashboard.php");
        exit();
    }
}

// Get faculty_id for a given user_id
function getUserFacultyId($user_id, $conn) {
    try {
        $stmt = $conn->prepare("SELECT faculty_id FROM faculty WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['faculty_id'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

// Check if current user can edit a faculty profile
function canEditFaculty($faculty_id) {
    if (isAdmin()) {
        return true; // Admin can edit anyone
    }
    
    if (!isLoggedIn()) {
        return false;
    }
    
    // Faculty can only edit their own profile
    $database = new Database();
    $conn = $database->connect();
    $userFacultyId = getUserFacultyId($_SESSION['user_id'], $conn);
    
    return $userFacultyId && $userFacultyId == $faculty_id;
}

// Check if current user can delete a faculty profile
function canDeleteFaculty($faculty_id) {
    // Only admins can delete
    return isAdmin();
}

function sanitize($data) {
    if (is_null($data)) return null;
    $data = trim($data);
    $data = stripslashes($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function validateRequired($value, $fieldName) {
    if (empty(trim($value))) {
        return "$fieldName is required.";
    }
    return null;
}

function validateEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format.";
    }
    return null;
}

function validateYear($year) {
    $currentYear = date('Y');
    if (!is_numeric($year) || $year < 1950 || $year > ($currentYear + 10)) {
        return "Year must be between 1950 and " . ($currentYear + 10) . ".";
    }
    return null;
}

function validatePhone($phone) {
    if (empty(trim($phone))) return null;
    if (!preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
        return "Invalid phone number format.";
    }
    return null;
}

function validateLength($value, $min, $max, $fieldName) {
    $length = strlen($value);
    if ($length < $min || $length > $max) {
        return "$fieldName must be between $min and $max characters.";
    }
    return null;
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

function validateImageUpload($file) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "File upload error code: " . $file['error'];
        return $errors;
    }
    
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return $errors;
    }
    
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        $errors[] = "File size must not exceed 5MB.";
    }
    
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $mimeType = null;
    if (isset($file['tmp_name']) && file_exists($file['tmp_name'])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
    if (!$mimeType || !in_array($mimeType, $allowedMimeTypes)) {
        $errors[] = "Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.";
    }
    
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        $errors[] = "Invalid file extension.";
    }
    
    return $errors;
}

function handleImageUpload($file, $oldPath = null) {
    $errors = validateImageUpload($file);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => $oldPath];
    }
    
    $upload_dir = 'uploads/photos/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    if ($oldPath && file_exists($oldPath)) {
        unlink($oldPath);
    }
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $newFileName = uniqid('faculty_', true) . '.' . $fileExtension;
    $targetPath = $upload_dir . $newFileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'path' => $targetPath];
    } else {
        return ['success' => false, 'errors' => ['Failed to save uploaded file.']];
    }
}

function getResearchTypes($conn) {
    $stmt = $conn->query("SELECT research_type_id, type_name FROM research_types ORDER BY type_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getResearchStatuses($conn) {
    $stmt = $conn->query("SELECT status_id, status_name FROM research_statuses ORDER BY status_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserTypes($conn) {
    $stmt = $conn->query("SELECT user_type_id, type_name FROM user_types ORDER BY type_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>