<?php
require_once 'database.php';
requireLogin();

$faculty_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Permission check: Only admins can delete
if (!canDeleteFaculty($faculty_id)) {
    $_SESSION['error'] = "You don't have permission to delete faculty members.";
    header("Location: view_faculty.php");
    exit();
}
?>