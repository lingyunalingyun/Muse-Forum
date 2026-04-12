<?php
// auth.php
session_start();
require_once __DIR__ . '/config.php';

$action = $_POST['action'] ?? '';

// --- 注册逻辑 ---
if ($action == 'register') {
    // 1. 获取并清理输入数据
    $email = trim($conn->real_escape_string($_POST['email'] ?? ''));
    $username = trim($conn->real_escape_string($_POST['nickname'] ?? '')); // 保持表单兼容，映射到 username
    $password_raw = $_POST['password'] ?? '';

    // 2. 基础合法性检查
    if (empty($email) || empty($username) || empty($password_raw)) {
        die("注册失败：所有字段均为必填项。");
    }

    // 3. 唯一性预检
    $check_sql = "SELECT id FROM users WHERE email = '$email' OR username = '$username'";
    $check_res = $conn->query($check_sql);
    if ($check_res && $check_res->num_rows > 0) {
        die("注册失败：该邮箱或用户名已被占用！");
    }

    // 4. 生成 6 位随机 userid
    $is_unique = false;
    $new_userid = 0;
    while (!$is_unique) {
        $new_userid = mt_rand(100000, 999999); 
        $uid_check = $conn->query("SELECT id FROM users WHERE userid = '$new_userid'");
        if ($uid_check->num_rows == 0) {
            $is_unique = true;
        }
    }

    // 5. 密码加密存储
    $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);

    // 6. 执行插入 (添加 avatar 字段，默认值为 default.png)
    // 初始积分 100, 初始等级 1, 角色 user, 默认头像 default.png
    $sql = "INSERT INTO users (userid, email, password, username, points, role, avatar, level) 
            VALUES ('$new_userid', '$email', '$password_hash', '$username', 100, 'user', 'default.png', 1)";
    
    if ($conn->query($sql)) {
        echo "注册成功！您的系统ID为: <b>$new_userid</b> 。 <a href='login.php'>去登录</a>";
    } else {
        echo "注册失败：" . $conn->error;
    }
}

// --- 登录逻辑 ---
if ($action == 'login') {
    $identity = $conn->real_escape_string($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identity) || empty($password)) {
        die("请填写登录信息");
    }

    $sql = "SELECT * FROM users WHERE email = '$identity' OR userid = '$identity' OR username = '$identity'";
    $result = $conn->query($sql);

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            // 登录成功
            $_SESSION['user_id'] = $user['id'];           // 数据库自增主键
            $_SESSION['system_userid'] = $user['userid']; // 6位数字ID
            $_SESSION['username'] = $user['username'];    // 统一后的用户名
            $_SESSION['role'] = $user['role']; 
            $_SESSION['avatar'] = $user['avatar'];       // 记录头像到 Session，方便 header.php 实时调用
            
            header("Location: index.php");
            exit;
        } else {
            echo "密码错误！";
        }
    } else {
        echo "用户不存在！";
    }
}
?>