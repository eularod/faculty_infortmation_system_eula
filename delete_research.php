<?php
require_once 'database.php';
requireLogin();

$research_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$faculty_id = isset($_GET['faculty_id']) ? intval($_GET['faculty_id']) : 0;

// Permission check
if (!canEditFaculty($faculty_id)) {
    $_SESSION['error'] = "You don't have permission to delete this research record.";
    header("Location: view_faculty_detail.php?id=$faculty_id");
    exit();
}
?>