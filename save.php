<?php
// save.php - 处理数据写入（用户关联版）
header('Content-Type: text/plain; charset=utf-8');

// 1. 开启 Session 以获取登录用户信息
session_start();

// 2. 建立数据库连接
require_once __DIR__ . '/config.php';

if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 3. 强制编码防止乱码
$conn->set_charset("utf8mb4");

// 4. 获取数据并安全过滤
$content = $_POST['content'] ?? '';
$safe_content = $conn->real_escape_string($content); 

// 5. 权限检查：必须登录才能发帖
if (!isset($_SESSION['user_id'])) {
    die("【发布失败】请先登录账号后再操作！");
}

// 这里获取的是数据库自增的唯一标识 ID，用于帖子关联
$user_id = $_SESSION['user_id']; 

if (!empty($safe_content)) {
    // 6. 执行写入：关联当前登录的 user_id
    // 状态设为 '待审核'，确保合规性
    $sql = "INSERT INTO posts (user_id, content, status) VALUES ('$user_id', '$safe_content', '待审核')";
    
    if ($conn->query($sql) === TRUE) {
        // 提示信息中可以加入当前用户的统一称呼 username
        echo "【发布成功】" . $_SESSION['username'] . "，您的内容已提交，请等待审核！";
    } else {
        // 如果报错，输出具体的数据库错误，方便你排查字段是否匹配
        echo "写入失败，请检查数据库 posts 表字段: " . $conn->error;
    }
} else {
    echo "内容不能为空";
}

$conn->close();
?>