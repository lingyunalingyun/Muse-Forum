<?php
session_start();
require_once __DIR__ . '/../config.php';

$cid = intval($_GET['cid']);
$pid = intval($_GET['pid']);
$my_id = $_SESSION['user_id'];
$my_role = $_SESSION['role'];

$post = $conn->query("SELECT user_id FROM posts WHERE id = $pid")->fetch_assoc();
if($post['user_id'] == $my_id || $my_role == 'admin') {
    $current = $conn->query("SELECT is_top FROM comments WHERE id = $cid")->fetch_assoc();

    $conn->query("UPDATE comments SET is_top = 0 WHERE post_id = $pid");

    if($current['is_top'] == 0) {
        $conn->query("UPDATE comments SET is_top = 1 WHERE id = $cid");
    }
    echo "success";
}
