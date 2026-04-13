<?php
// auth.php
session_start();
require_once __DIR__ . '/../config.php';

$action = $_POST['action'] ?? '';

// --- 注册逻辑 ---
if ($action == 'register') {
    $email = trim($conn->real_escape_string($_POST['email'] ?? ''));
    $username = trim($conn->real_escape_string($_POST['username'] ?? ''));
    $password_raw = $_POST['password'] ?? '';

    if (empty($email) || empty($username) || empty($password_raw)) {
        die("注册失败：所有字段均为必填项。");
    }

    $check_sql = "SELECT id FROM users WHERE email = '$email' OR username = '$username'";
    $check_res = $conn->query($check_sql);
    if ($check_res && $check_res->num_rows > 0) {
        die("注册失败：该邮箱或用户名已被占用！");
    }

    $is_unique = false;
    $new_userid = 0;
    while (!$is_unique) {
        $new_userid = mt_rand(100000, 999999);
        $uid_check = $conn->query("SELECT id FROM users WHERE userid = '$new_userid'");
        if ($uid_check->num_rows == 0) {
            $is_unique = true;
        }
    }

    $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (userid, email, password, username, points, role, avatar, level)
            VALUES ('$new_userid', '$email', '$password_hash', '$username', 100, 'user', 'default.png', 1)";

    if ($conn->query($sql)) {
        echo "注册成功！您的系统ID为: <b>$new_userid</b> 。 <a href='../pages/login.php'>去登录</a>";
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
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['system_userid'] = $user['userid'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['avatar'] = $user['avatar'];

            // ── 每日登录奖励 ──
            $uid_db = (int)$user['id'];
            $today  = date('Y-m-d');
            $last   = $user['last_login_date'] ?? null;

            if ($last !== $today) {
                $yesterday   = date('Y-m-d', strtotime('-1 day'));
                $cur_streak  = (int)($user['login_streak'] ?? 0);
                $new_streak  = ($last === $yesterday) ? $cur_streak + 1 : 1;

                $conn->query("UPDATE users
                              SET points = points + 50,
                                  last_login_date = '$today',
                                  login_streak = $new_streak
                              WHERE id = $uid_db");

                $_SESSION['login_reward'] = [
                    'points' => 50,
                    'streak' => $new_streak
                ];
            }

            header("Location: ../index.php");
            exit;
        } else {
            echo "密码错误！";
        }
    } else {
        echo "用户不存在！";
    }
}
?>
