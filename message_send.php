<?php

session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => '未登录']);
    exit;
}

$from_id = intval($_SESSION['user_id']);
$to_id   = intval($_POST['to_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if (!$to_id || $from_id === $to_id || empty($content)) {
    echo json_encode(['status' => 'error', 'msg' => '参数错误']);
    exit;
}

$safe_content = $conn->real_escape_string($content);

$conn->query("INSERT INTO messages (from_user_id, to_user_id, content)
              VALUES ($from_id, $to_id, '$safe_content')");
$new_id = $conn->insert_id;

$conn->query("INSERT INTO notifications (user_id, from_user_id, type)
              VALUES ($to_id, $from_id, 'message')");

$msg_res = $conn->query("SELECT m.*, u.username, u.avatar
                          FROM messages m
                          JOIN users u ON u.id = m.from_user_id
                          WHERE m.id = $new_id");
$msg = $msg_res ? $msg_res->fetch_assoc() : null;

echo json_encode(['status' => 'success', 'message' => $msg]);
$conn->close();
?>
