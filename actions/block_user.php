<?php
/**
 * block_user.php — 拉黑或取消拉黑用户
 *
 * 功能：对目标用户执行拉黑 toggle，拉黑时同步解除双向关注关系
 * POST 参数：target_id
 * 读写表：user_blocks, follows
 * 权限：需登录
 */
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$my_id     = intval($_SESSION['user_id'] ?? 0);
$target_id = intval($_POST['target_id'] ?? 0);

if (!$my_id || !$target_id || $my_id === $target_id) {
    echo json_encode(['status' => 'error', 'msg' => '无效请求']);
    exit;
}

$check = $conn->query("SELECT id FROM user_blocks WHERE blocker_id=$my_id AND blocked_id=$target_id");
if ($check && $check->num_rows > 0) {
    $conn->query("DELETE FROM user_blocks WHERE blocker_id=$my_id AND blocked_id=$target_id");
    echo json_encode(['status' => 'unblocked']);
} else {
    $conn->query("INSERT INTO user_blocks (blocker_id, blocked_id) VALUES ($my_id, $target_id)");
    
    $conn->query("DELETE FROM follows WHERE (follower_id=$my_id AND followed_id=$target_id) OR (follower_id=$target_id AND followed_id=$my_id)");
    echo json_encode(['status' => 'blocked']);
}
$conn->close();
