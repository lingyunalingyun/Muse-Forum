<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    echo "error";
    exit;
}

$id = intval($_GET['id']);
$uid = intval($_SESSION['user_id']);
$role = $_SESSION['role'] ?? '';

$result = $conn->query("SELECT user_id FROM comments WHERE id = $id");
$check = $result ? $result->fetch_assoc() : null;
if ($check && ($check['user_id'] == $uid || $role == 'admin')) {
    $conn->query("DELETE FROM comments WHERE id = $id OR parent_id = $id");
    echo "success";
}
