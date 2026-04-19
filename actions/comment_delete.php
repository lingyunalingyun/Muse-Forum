<?php
/**
 * comment_delete.php — 删除评论（级联删除子回复）
 *
 * 功能：删除指定评论及其所有子回复
 * POST 参数：comment_id
 * 读写表：comments
 * 权限：需登录，本人或管理员可操作
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

$result = $conn->query("SELECT user_id FROM comments WHERE id = $id");
$check = $result ? $result->fetch_assoc() : null;
if ($check && ($check['user_id'] == $uid || $role == 'admin')) {
    $conn->query("DELETE FROM comments WHERE id = $id OR parent_id = $id");
    echo "success";
}
