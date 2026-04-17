<?php
/**
 * actions/upload_attachment.php — 附件上传（AJAX JSON）
 *
 * POST FILES：file
 * 限制：
 *   - 禁止 php/php3/php4/php5/phtml/phar/exe/sh/bat/cmd/msi/vbs/ps1 等可执行扩展
 *   - 最大 20MB
 * 文件命名：日期_uniqid_原始文件名（特殊字符替换为 _）
 * 文件存放：uploads/attachments/
 *
 * 返回：{"status":"ok","filename":...,"original":...,"size":...,"ext":...,"url":...}
 *       或 {"status":"error","msg":"..."}
 */
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => '请先登录']);
    exit;
}

$upload_dir = __DIR__ . '/../uploads/attachments/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$file = $_FILES['file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'msg' => '上传失败，错误码：' . ($file['error'] ?? 'no file')]);
    exit;
}

// 禁止危险扩展
$blocked = ['php','php3','php4','php5','phtml','phar','exe','sh','bat','cmd','msi','vbs','ps1'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (in_array($ext, $blocked)) {
    echo json_encode(['status' => 'error', 'msg' => '不允许上传此类型文件']);
    exit;
}

if ($file['size'] > 20 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'msg' => '附件不能超过 20MB']);
    exit;
}

$original = $file['name'];
$safe_original = preg_replace('/[^a-zA-Z0-9\u4e00-\u9fa5._-]/u', '_', $original);
$filename = date('Ymd') . '_' . uniqid() . '_' . $safe_original;
$dest = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode([
        'status'   => 'ok',
        'filename' => $filename,
        'original' => $original,
        'size'     => $file['size'],
        'ext'      => $ext,
        'url'      => '../uploads/attachments/' . rawurlencode($filename)
    ]);
} else {
    echo json_encode(['status' => 'error', 'msg' => '文件保存失败，请检查目录权限']);
}
?>
