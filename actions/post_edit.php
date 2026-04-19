<?php
/**
 * post_edit.php — 编辑帖子，10分钟内冷却限制
 *
 * 功能：修改已发布帖子的内容，发布后10分钟内可编辑
 * POST 参数：post_id, title, content
 * 读写表：posts
 * 权限：帖子本人，且在发布后10分钟内
 */
ob_start();
error_reporting(0);
session_start();
require_once __DIR__ . '/../config.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => '请先登录']);
    exit;
}

$my_id   = intval($_SESSION['user_id']);
$pid     = intval($_POST['pid']     ?? 0);
$title   = trim($_POST['title']     ?? '');
$content = $_POST['content']        ?? '';

if (!$pid || empty($title) || empty(trim(strip_tags($content)))) {
    echo json_encode(['status' => 'error', 'msg' => '参数错误']);
    exit;
}

$conn->query("ALTER TABLE posts ADD COLUMN edited_at DATETIME DEFAULT NULL");

$res  = $conn->query("SELECT user_id, edited_at FROM posts WHERE id = $pid");
$post = $res ? $res->fetch_assoc() : null;

if (!$post) {
    echo json_encode(['status' => 'error', 'msg' => '帖子不存在']);
    exit;
}
if ((int)$post['user_id'] !== $my_id) {
    echo json_encode(['status' => 'error', 'msg' => '无权限']);
    exit;
}

if (!empty($post['edited_at'])) {
    $remaining = 600 - (time() - strtotime($post['edited_at']));
    if ($remaining > 0) {
        $min = ceil($remaining / 60);
        echo json_encode(['status' => 'cooldown', 'msg' => "编辑冷却中，还需等待 {$min} 分钟"]);
        exit;
    }
}

$safe_title   = $conn->real_escape_string($title);
$safe_content = $conn->real_escape_string($content);
$now          = date('Y-m-d H:i:s');

$conn->query("UPDATE posts SET title='$safe_title', content='$safe_content', edited_at='$now' WHERE id=$pid AND user_id=$my_id");

echo json_encode(['status' => 'ok', 'edited_at' => $now]);
$conn->close();
?>
