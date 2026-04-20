<?php
/**
 * upload_image.php — WangEditor 富文本编辑器图片上传接口
 *
 * 功能：接收图片上传（JPG/PNG/GIF/WEBP），验证 MIME 类型，限制大小 5MB，
 *       保存至 uploads/posts/ 并按 WangEditor 格式返回图片 URL
 * 读写表：无（仅写磁盘）
 * 权限：需登录
 */
// WangEditor 图片上传接口
// 返回格式: {"errno":0,"data":{"url":"..."}}
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

// WangEditor 默认字段名为 wangeditor-uploaded-image，也兼容 image
$file = $_FILES['wangeditor-uploaded-image'] ?? $_FILES['image'] ?? null;

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['errno' => 1, 'message' => '上传失败，错误码：' . ($file['error'] ?? 'no file')]);
    exit;
}

// 验证是图片
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
