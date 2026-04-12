<?php
session_start();
$conn = new mysqli("localhost", "svh_b14f1q", "xrb23va4gs", "svh_b14f1q");
$conn->set_charset("utf8mb4");

$my_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
// 接收前端传来的作者 ID
$author_id = intval($_POST['following_id'] ?? 0); 

if ($my_id && $author_id && $my_id != $author_id) {
    // 1. 检查是否已关注 (注意字段名改为 followed_id)
    $check = $conn->query("SELECT id FROM follows WHERE follower_id = $my_id AND followed_id = $author_id");
    
    if ($check && $check->num_rows > 0) {
        // 2. 取消关注
        $conn->query("DELETE FROM follows WHERE follower_id = $my_id AND followed_id = $author_id");
        $status = 'unfollowed';
    } else {
        // 3. 添加关注
        $conn->query("INSERT INTO follows (follower_id, followed_id) VALUES ($my_id, $author_id)");
        $status = 'followed';
    }
    
    // 4. 获取最新的粉丝总数 (注意字段名改为 followed_id)
    $res = $conn->query("SELECT COUNT(*) as count FROM follows WHERE followed_id = $author_id");
    $new_count = $res ? $res->fetch_assoc()['count'] : 0;
    
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'new_count' => $new_count]);
} else {
    echo json_encode(['status' => 'error', 'msg' => '参数错误或未登录']);
}

$conn->close();
?>