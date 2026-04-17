<?php
/**
 * actions/post_delete.php — 删除帖子
 *
 * GET 参数：id（帖子 ID）
 * 权限：帖子作者本人 或 admin
 * 返回：纯文本 "success"（无权限或帖子不存在时无输出）
 *
 * ⚠️ 仅删除 posts 记录，关联的 comments / post_likes / post_favs 不级联删除，
 *    如需清理孤立数据须手动执行 SQL。
 * 读写表：posts
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
