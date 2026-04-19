<?php
/**
 * comment_top.php — 评论置顶 toggle（管理员）
 *
 * 功能：管理员将指定评论设置为置顶或取消置顶
 * POST 参数：comment_id
 * 读写表：comments
 * 权限：admin 或 owner
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    echo "error";
    exit;
}

$cid = intval($_GET['cid']);
$pid = intval($_GET['pid']);
$my_id = intval($_SESSION['user_id']);
$my_role = $_SESSION['role'] ?? '';

$post_res = $conn->query("SELECT user_id FROM posts WHERE id = $pid");
$post = $post_res ? $post_res->fetch_assoc() : null;
if ($post && ($post['user_id'] == $my_id || $my_role == 'admin')) {
    $current_res = $conn->query("SELECT is_top FROM comments WHERE id = $cid");
    $current = $current_res ? $current_res->fetch_assoc() : null;

    $conn->query("UPDATE comments SET is_top = 0 WHERE post_id = $pid");

    if ($current && $current['is_top'] == 0) {
        $conn->query("UPDATE comments SET is_top = 1 WHERE id = $cid");
    }
    echo "success";
}
