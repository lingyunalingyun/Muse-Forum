<?php

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
