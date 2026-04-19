<?php

ob_start();
error_reporting(0);
session_start();
require_once __DIR__ . '/../config.php';
ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['errno' => 1, 'message' => '请先登录']);
    exit;
}

$upload_dir = __DIR__ . '/../uploads/posts/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$file = $_FILES['wangeditor-uploaded-image'] ?? $_FILES['image'] ?? null;

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['errno' => 1, 'message' => '上传失败，错误码：' . ($file['error'] ?? 'no file')]);
    exit;
}

$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed_types)) {
    echo json_encode(['errno' => 1, 'message' => '只允许上传 JPG/PNG/GIF/WEBP 图片']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['errno' => 1, 'message' => '图片不能超过 5MB']);
    exit;
}

$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
$filename = date('Ymd') . '_' . uniqid() . '.' . $ext;
$dest     = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode([
        'errno' => 0,
        'data'  => ['url' => '../uploads/posts/' . $filename]
    ]);
} else {
    echo json_encode(['errno' => 1, 'message' => '文件保存失败，请检查目录权限']);
}
?>
