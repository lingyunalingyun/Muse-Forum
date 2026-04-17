<?php
/**
 * actions/comment_delete.php — 删除评论
 *
 * GET 参数：id（评论 ID）
 * 权限：评论作者本人 或 admin 可删除
 * 副作用：级联删除所有子回复（WHERE parent_id = id）
 * 返回：纯文本 "success"（无权限或不存在时无输出）
 * 读写表：comments
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
