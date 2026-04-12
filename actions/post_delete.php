<?php
session_start();
require_once __DIR__ . '/../config.php';

$id = intval($_GET['id']);
$uid = $_SESSION['user_id'];
$role = $_SESSION['role'];

$check = $conn->query("SELECT user_id FROM posts WHERE id = $id")->fetch_assoc();
if($check['user_id'] == $uid || $role == 'admin') {
    $conn->query("DELETE FROM posts WHERE id = $id");
    echo "success";
}
