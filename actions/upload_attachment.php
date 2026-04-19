<?php
/**
 * upload_attachment.php — 附件上传，返回 JSON
 *
 * 功能：接收上传的附件文件并保存到服务器，返回文件访问路径
 * POST 参数：file（multipart/form-data）
 * 读写表：写入 uploads/attachments/ 目录
 * 权限：需登录
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
