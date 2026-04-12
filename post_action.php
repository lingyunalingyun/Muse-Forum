<?php
session_start();
$conn = new mysqli("localhost", "svh_b14f1q", "xrb23va4gs", "svh_b14f1q");
$my_id = $_SESSION['user_id'] ?? 0;
$pid = intval($_POST['pid'] ?? 0);
$type = $_POST['type'] ?? ''; // 'like' 或 'fav'

if ($my_id && $pid && ($type == 'like' || $type == 'fav')) {
    $table = ($type == 'like') ? 'post_likes' : 'post_favs';
    
    // 检查是否已操作
    $check = $conn->query("SELECT id FROM $table WHERE post_id = $pid AND user_id = $my_id");
    
    if ($check->num_rows > 0) {
        $conn->query("DELETE FROM $table WHERE post_id = $pid AND user_id = $my_id");
        $active = false;
    } else {
        $conn->query("INSERT INTO $table (post_id, user_id) VALUES ($pid, $my_id)");
        $active = true;
    }
    
    // 获取新总数
    $res = $conn->query("SELECT COUNT(*) as count FROM $table WHERE post_id = $pid");
    $new_count = $res->fetch_assoc()['count'];
    
    echo json_encode(['status' => 'success', 'active' => $active, 'new_count' => $new_count]);
}
$conn->close();