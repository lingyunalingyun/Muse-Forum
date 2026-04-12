<?php
session_start();
require_once __DIR__ . '/../config.php';

// 注册示例
if ($_POST['action'] == 'register') {
    $email = $_POST['email'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nickname = $_POST['nickname'];

    $sql = "INSERT INTO users (email, password, nickname) VALUES ('$email', '$pass', '$nickname')";
    if ($conn->query($sql)) {
        echo "注册成功！";
    }
}

// 获取个人中心数据（包括背包）
if ($_GET['action'] == 'get_profile') {
    $uid = $_SESSION['user_id'];
    $user = $conn->query("SELECT * FROM users WHERE id = $uid")->fetch_assoc();
    $inventory = $conn->query("SELECT * FROM user_inventory WHERE user_id = $uid");

    $items = [];
    while($row = $inventory->fetch_assoc()) { $items[] = $row; }

    echo json_encode(['user' => $user, 'inventory' => $items]);
}
?>
