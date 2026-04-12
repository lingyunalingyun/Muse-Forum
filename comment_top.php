<?php
session_start();
$conn = new mysqli("localhost", "svh_b14f1q", "xrb23va4gs", "svh_b14f1q");
$cid = intval($_GET['cid']);
$pid = intval($_GET['pid']);
$my_id = $_SESSION['user_id'];
$my_role = $_SESSION['role'];

// 检查权限：只有帖子作者或管理员能置顶
$post = $conn->query("SELECT user_id FROM posts WHERE id = $pid")->fetch_assoc();
if($post['user_id'] == $my_id || $my_role == 'admin') {
    // 1. 获取当前点击评论的状态
    $current = $conn->query("SELECT is_top FROM comments WHERE id = $cid")->fetch_assoc();
    
    // 2. 先把该帖子下所有评论的置顶取消（保证唯一性）
    $conn->query("UPDATE comments SET is_top = 0 WHERE post_id = $pid");
    
    // 3. 如果之前不是置顶，则设为置顶；如果之前是置顶，步骤2已经取消了
    if($current['is_top'] == 0) {
        $conn->query("UPDATE comments SET is_top = 1 WHERE id = $cid");
    }
    echo "success";
}