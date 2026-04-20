<?php
/**
 * message_send.php — 发送私信
 *
 * 功能：向指定用户发送私信（支持附带转发帖子），并写入消息通知；
 *       返回新消息的完整数据供前端即时渲染
 * 读写表：messages、notifications
 * 权限：需登录；封禁账号不可发送
 */
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => '未登录']);
    exit;
}
if (!empty($_SESSION['is_banned'])) {
    echo json_encode(['status' => 'error', 'msg' => '账号已被限制，无法发送私信']);
    exit;
}

$from_id     = intval($_SESSION['user_id']);
$to_id       = intval($_POST['to_id'] ?? 0);
$content     = trim($_POST['content'] ?? '');
$ref_post_id = intval($_POST['ref_post_id'] ?? 0);

if (!$to_id || $from_id === $to_id) {
    echo json_encode(['status' => 'error', 'msg' => '参数错误']);
    exit;
}
// 内容或转发帖子至少有一个
if (empty($content) && !$ref_post_id) {
    echo json_encode(['status' => 'error', 'msg' => '参数错误']);
    exit;
}

$safe_content = $conn->real_escape_string($content);
$ref_col      = $ref_post_id ? ", ref_post_id" : '';
$ref_val      = $ref_post_id ? ", $ref_post_id" : '';

// 写入消息
$conn->query("INSERT INTO messages (from_user_id, to_user_id, content$ref_col)
              VALUES ($from_id, $to_id, '$safe_content'$ref_val)");
$new_id = $conn->insert_id;

// 写入通知
$conn->query("INSERT INTO notifications (user_id, from_user_id, type)
              VALUES ($to_id, $from_id, 'message')");

// 返回新消息数据（供前端即时渲染）
$msg_res = $conn->query("SELECT m.*, u.username, u.avatar
                          FROM messages m
                          JOIN users u ON u.id = m.from_user_id
                          WHERE m.id = $new_id");
$msg = $msg_res ? $msg_res->fetch_assoc() : null;

echo json_encode(['status' => 'success', 'message' => $msg]);
$conn->close();
?>
