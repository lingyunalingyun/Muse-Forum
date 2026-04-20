<?php
/**
 * follow_toggle.php — 关注 / 取消关注用户
 *
 * 功能：切换当前用户对目标用户的关注状态，并返回目标用户最新粉丝数；
 *       关注时向对方发送通知
 * 读写表：follows、notifications
 * 权限：需登录
 */
session_start();
require_once __DIR__ . '/../config.php';

$my_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$author_id = intval($_POST['following_id'] ?? 0);

if ($my_id && $author_id && $my_id != $author_id) {
    $check = $conn->query("SELECT id FROM follows WHERE follower_id = $my_id AND followed_id = $author_id");

    if ($check && $check->num_rows > 0) {
        $conn->query("DELETE FROM follows WHERE follower_id = $my_id AND followed_id = $author_id");
        $status = 'unfollowed';
    } else {
        $conn->query("INSERT INTO follows (follower_id, followed_id) VALUES ($my_id, $author_id)");
        $conn->query("INSERT INTO notifications (user_id, from_user_id, type)
                      VALUES ($author_id, $my_id, 'follow')");
        $status = 'followed';
    }

    $res = $conn->query("SELECT COUNT(*) as count FROM follows WHERE followed_id = $author_id");
    $new_count = $res ? $res->fetch_assoc()['count'] : 0;

    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'new_count' => $new_count]);
} else {
    echo json_encode(['status' => 'error', 'msg' => '参数错误或未登录']);
}

$conn->close();
?>
