<?php
ob_start();                      // 缓冲所有输出，防止 notice/warning 混入 JSON
error_reporting(0);              // 屏蔽 PHP 提示，避免污染 JSON
session_start();
require_once __DIR__ . '/../config.php';
ob_clean();                      // 清除 config.php 可能输出的任何空白
header('Content-Type: application/json; charset=utf-8');

$conn->set_charset("utf8mb4");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => '请先登录']);
    exit;
}

$user_id  = (int)$_SESSION['user_id'];
$title    = trim($_POST['title']   ?? '');
$content  = $_POST['content']      ?? '';
$draft_id = (int)($_POST['draft_id'] ?? 0);
$is_draft = ($_POST['is_draft']    ?? '0') === '1';

if (empty($title)) {
    echo json_encode(['status' => 'error', 'msg' => '标题不能为空']);
    exit;
}
if (empty(trim(strip_tags($content)))) {
    echo json_encode(['status' => 'error', 'msg' => '内容不能为空']);
    exit;
}

// 公告标记（仅管理员）
$is_notice = 0;
if (($_SESSION['role'] ?? '') === 'admin' && ($_POST['is_notice'] ?? '0') === '1') {
    $is_notice = 1;
}

// 附件列表（JSON 字符串）
$attachments = $_POST['attachments'] ?? '';
if (!empty($attachments)) {
    json_decode($attachments); // 验证格式
    if (json_last_error() !== JSON_ERROR_NONE) $attachments = '';
}

// 确定状态
if ($is_draft) {
    $status = '草稿';
} elseif ($is_notice) {
    $status = '已发布';
} else {
    $status = '待审核';
}

$safe_title       = $conn->real_escape_string($title);
$safe_content     = $conn->real_escape_string($content);
$safe_attachments = $conn->real_escape_string($attachments);

// 如果有 draft_id 就更新，否则插入
if ($draft_id > 0) {
    // 确认草稿属于当前用户
    $check = $conn->query("SELECT id FROM posts WHERE id = $draft_id AND user_id = $user_id AND status = '草稿'");
    if ($check && $check->num_rows > 0) {
        $sql = "UPDATE posts
                SET title='$safe_title', content='$safe_content', status='$status',
                    is_notice=$is_notice, attachments='$safe_attachments',
                    created_at = IF('$status'='草稿', created_at, NOW())
                WHERE id = $draft_id AND user_id = $user_id";
        $conn->query($sql);
        $new_id = $draft_id;
    } else {
        echo json_encode(['status' => 'error', 'msg' => '草稿不存在或无权限']);
        exit;
    }
} else {
    $sql = "INSERT INTO posts (user_id, title, content, status, is_notice, attachments)
            VALUES ($user_id, '$safe_title', '$safe_content', '$status', $is_notice, '$safe_attachments')";
    $insert_ok = $conn->query($sql);
    $new_id = $conn->insert_id ?: ($insert_ok ? -1 : 0);
}

if ($new_id) {
    if ($is_draft) {
        echo json_encode(['status' => 'ok', 'type' => 'draft', 'id' => $new_id, 'msg' => '草稿已保存']);
    } elseif ($is_notice) {
        echo json_encode(['status' => 'ok', 'type' => 'notice', 'id' => $new_id, 'msg' => '公告已发布']);
    } else {
        echo json_encode(['status' => 'ok', 'type' => 'post', 'id' => $new_id, 'msg' => '帖子已提交，等待审核']);
    }
} else {
    echo json_encode(['status' => 'error', 'msg' => '写入失败：' . $conn->error]);
}

$conn->close();
?>
