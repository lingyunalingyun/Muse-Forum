<?php

session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$my_role = $_SESSION['role'] ?? '';
if (!in_array($my_role, ['admin', 'owner'])) {
    echo json_encode(['ok' => false, 'msg' => '权限不足']);
    exit;
}

$action    = $_POST['action'] ?? '';
$target_id = intval($_POST['user_id'] ?? 0);
$reason    = trim($_POST['reason'] ?? '');

if (!$target_id || !in_array($action, ['ban', 'unban'])) {
    echo json_encode(['ok' => false, 'msg' => '参数错误']);
    exit;
}

if ($target_id === intval($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => '不能操作自己的账号']);
    exit;
}

$tr = $conn->query("SELECT id, role FROM users WHERE id=$target_id");
if (!$tr || $tr->num_rows === 0) {
    echo json_encode(['ok' => false, 'msg' => '用户不存在']);
    exit;
}
$target = $tr->fetch_assoc();

if ($my_role === 'admin' && $target['role'] !== 'user') {
    echo json_encode(['ok' => false, 'msg' => '无权操作该账号']);
    exit;
}

if ($action === 'ban') {
    if (empty($reason)) {
        echo json_encode(['ok' => false, 'msg' => '请填写封禁原因']);
        exit;
    }
    $safe_reason = $conn->real_escape_string($reason);
    $conn->query("UPDATE users SET is_banned=1, ban_reason='$safe_reason' WHERE id=$target_id");
} else {
    $conn->query("UPDATE users SET is_banned=0, ban_reason=NULL WHERE id=$target_id");
}

echo json_encode(['ok' => true]);
