<?php
/**
 * post_delete.php — 删除帖子
 *
 * 功能：根据帖子 ID 删除指定帖子
 * 读写表：posts
 * 权限：需登录；仅帖子作者或 admin 可删
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    echo "error";
    exit;
}

$id = intval($_GET['id']);
$uid = intval($_SESSION['user_id']);
$role = $_SESSION['role'] ?? '';

$result = $conn->query("SELECT user_id FROM posts WHERE id = $id");
$check = $result ? $result->fetch_assoc() : null;
if ($check && ($check['user_id'] == $uid || $role == 'admin')) {
    $conn->query("DELETE FROM posts WHERE id = $id");
    echo "success";
}
