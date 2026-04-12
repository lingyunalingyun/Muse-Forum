<?php
// comment_save.php - 处理评论与回复
header('Content-Type: text/plain; charset=utf-8');
session_start();

if (!isset($_SESSION['user_id'])) {
    die("请先登录");
}

require_once __DIR__ . '/../config.php';

$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
$user_id = $_SESSION['user_id'];
$content = isset($_POST['content']) ? htmlspecialchars(trim($_POST['content'])) : '';

if ($post_id > 0 && !empty($content)) {
    $stmt = $conn->prepare("INSERT INTO comments (post_id, parent_id, user_id, content) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $post_id, $parent_id, $user_id, $content);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "错误: " . $conn->error;
    }
    $stmt->close();
} else {
    echo "内容不能为空";
}
$conn->close();
?>
