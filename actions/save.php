<?php
// save.php - 处理数据写入（用户关联版）
header('Content-Type: text/plain; charset=utf-8');

session_start();
require_once __DIR__ . '/../config.php';

if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

$title = $conn->real_escape_string(trim($_POST['title'] ?? ''));
$content = $_POST['content'] ?? '';
$safe_content = $conn->real_escape_string($content);

if (!isset($_SESSION['user_id'])) {
    die("【发布失败】请先登录账号后再操作！");
}

if (empty($title)) {
    die("【发布失败】标题不能为空！");
}

$user_id = $_SESSION['user_id'];

if (!empty($safe_content)) {
    $sql = "INSERT INTO posts (user_id, title, content, status) VALUES ('$user_id', '$title', '$safe_content', '待审核')";

    if ($conn->query($sql) === TRUE) {
        echo "【发布成功】" . $_SESSION['username'] . "，您的内容已提交，请等待审核！";
    } else {
        echo "写入失败，请检查数据库 posts 表字段: " . $conn->error;
    }
} else {
    echo "内容不能为空";
}

$conn->close();
?>
