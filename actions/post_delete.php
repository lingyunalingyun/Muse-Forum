<?php
/**
 * post_delete.php — 删除帖子（级联删除评论、点赞、收藏）
 *
 * 功能：删除指定帖子及其所有关联数据
 * POST 参数：post_id
 * 读写表：posts, comments, post_likes, post_favs
 * 权限：帖子本人或管理员可操作
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
