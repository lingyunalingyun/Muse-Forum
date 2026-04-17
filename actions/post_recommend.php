<?php
/**
 * actions/post_recommend.php — 推荐 / 取消推荐帖子（toggle，AJAX JSON）
 *
 * POST 参数：pid（帖子 ID）
 * 权限：admin / owner
 * 返回：{"status": "success", "is_recommend": 0|1}
 *
 * is_recommend=1 的帖子显示在首页推荐网格中
 * 读写表：posts（is_recommend）
 */
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'owner'])) {
    echo json_encode(['status' => 'error', 'msg' => '无权限']);
    exit;
}

$pid = intval($_POST['pid'] ?? 0);
if ($pid <= 0) {
    echo json_encode(['status' => 'error', 'msg' => '参数错误']);
    exit;
}

$res = $conn->query("SELECT is_recommend FROM posts WHERE id = $pid");
if (!$res || $res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'msg' => '帖子不存在']);
    exit;
}

$current = (int)$res->fetch_assoc()['is_recommend'];
$new_val  = $current ? 0 : 1;
$conn->query("UPDATE posts SET is_recommend = $new_val WHERE id = $pid");

echo json_encode(['status' => 'success', 'is_recommend' => $new_val]);
$conn->close();
?>
