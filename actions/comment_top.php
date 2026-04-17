<?php
/**
 * actions/comment_top.php — 置顶 / 取消置顶评论（toggle）
 *
 * GET 参数：cid（评论 ID）, pid（帖子 ID）
 * 权限：帖子作者本人 或 admin
 *
 * 逻辑：
 *   - 先将该帖所有评论 is_top 清零（保证同时只有一条置顶）
 *   - 若目标评论原本 is_top=0 → 置为 1（置顶）
 *   - 若目标评论原本 is_top=1 → 不再设置（相当于取消置顶）
 *
 * 返回：纯文本 "success"（无权限时无输出）
 * 读写表：comments（is_top）
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
