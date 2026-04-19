<?php
/**
 * user_handler.php — 旧版用户处理接口（已废弃，勿调用）
 *
 * 功能：原用于处理用户注册等操作，现已由 auth.php 替代
 * POST 参数：无效
 * 读写表：无实际操作
 * 权限：无
 * @deprecated 此文件已废弃，请勿调用
 */
session_start();
require_once __DIR__ . '/../config.php';

if (isset($_POST['action']) && $_POST['action'] == 'register') {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $pass = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $username = $conn->real_escape_string($_POST['username'] ?? '');

    $sql = "INSERT INTO users (email, password, username) VALUES ('$email', '$pass', '$username')";
    if ($conn->query($sql)) {
        echo "注册成功！";
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'get_profile') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => '未登录']);
        exit;
    }
    $uid = intval($_SESSION['user_id']);
    $user = $conn->query("SELECT * FROM users WHERE id = $uid")->fetch_assoc();
    $inventory = $conn->query("SELECT * FROM user_inventory WHERE user_id = $uid");

    $items = [];
    while($row = $inventory->fetch_assoc()) { $items[] = $row; }

    echo json_encode(['user' => $user, 'inventory' => $items]);
}
?>
