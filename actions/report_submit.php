<?php
/**
 * 举报提交
 * POST: type(post|user), target_id, reason, detail
 * 同一用户 24h 内对同一目标只能举报一次
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exp_helper.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => '请先登录后再举报']);
    exit;
}

ensure_user_columns($conn);

$reporter_id = intval($_SESSION['user_id']);
$type        = trim($_POST['type'] ?? '');
$target_id   = intval($_POST['target_id'] ?? 0);
$reason      = trim($_POST['reason'] ?? '');
$detail      = mb_substr(trim($_POST['detail'] ?? ''), 0, 500);

if (!in_array($type, ['post', 'user']) || !$target_id || !$reason) {
    echo json_encode(['ok' => false, 'msg' => '参数错误']);
    exit;
}
if ($type === 'user' && $target_id === $reporter_id) {
    echo json_encode(['ok' => false, 'msg' => '不能举报自己']);
    exit;
}

// 验证目标存在
if ($type === 'post') {
    $chk = $conn->query("SELECT id FROM posts WHERE id=$target_id");
} else {
    $chk = $conn->query("SELECT id FROM users WHERE id=$target_id");
}
if (!$chk || $chk->num_rows === 0) {
    echo json_encode(['ok' => false, 'msg' => '举报目标不存在']);
    exit;
}

// 24h 内重复举报检查
$dup = $conn->query(
    "SELECT id FROM reports
     WHERE reporter_id=$reporter_id AND type='$type' AND target_id=$target_id
       AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
);
if ($dup && $dup->num_rows > 0) {
    echo json_encode(['ok' => false, 'msg' => '你已举报过该内容，请勿重复提交']);
    exit;
}

$sr = $conn->real_escape_string($reason);
$sd = $conn->real_escape_string($detail);
$conn->query(
    "INSERT INTO reports (reporter_id, type, target_id, reason, detail)
     VALUES ($reporter_id, '$type', $target_id, '$sr', '$sd')"
);

echo json_encode(['ok' => true, 'msg' => '举报已提交，感谢你的反馈']);
$conn->close();
?>
