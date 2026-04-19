<?php
/**
 * ban_user.php — 封禁或解封用户
 *
 * 功能：管理员对用户执行封禁/解封操作，支持设置封禁原因和到期时间
 * POST 参数：action(ban|unban), user_id, reason, ban_until
 * 读写表：users
 * 权限：admin 只能封禁 user，owner 可封禁 admin/user
 */

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
$ban_until_raw = trim($_POST['ban_until'] ?? '');

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

if ($my_role === 'admin' && !in_array($target['role'], ['user', 'sponsor'])) {
    echo json_encode(['ok' => false, 'msg' => '无权操作该账号']);
    exit;
}

$my_id = intval($_SESSION['user_id']);

if ($action === 'ban') {
    if (empty($reason)) {
        echo json_encode(['ok' => false, 'msg' => '请填写封禁原因']);
        exit;
    }
    $safe_reason = $conn->real_escape_string($reason);

    
    $ban_until_sql = 'NULL';
    if ($ban_until_raw !== '') {
        $ts = strtotime($ban_until_raw);
        if ($ts && $ts > time()) {
            $ban_until_sql = "'" . date('Y-m-d 23:59:59', $ts) . "'";
        } else {
            echo json_encode(['ok' => false, 'msg' => '截止日期无效，请选择未来的日期']);
            exit;
        }
    }

    $conn->query("UPDATE users
                  SET is_banned=1, ban_reason='$safe_reason',
                      ban_until=$ban_until_sql, banned_by=$my_id
                  WHERE id=$target_id");
} else {
    $conn->query("UPDATE users SET is_banned=0, ban_reason=NULL, ban_until=NULL, banned_by=NULL WHERE id=$target_id");
}

echo json_encode(['ok' => true]);
$conn->close();
?>
