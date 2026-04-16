<?php
// auth.php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exp_helper.php';

// 自动补全新字段（仅首次运行时执行 ALTER TABLE）
ensure_user_columns($conn);

$action = $_POST['action'] ?? '';

// ─────────────────────────────────────────────
// 注册逻辑
// ─────────────────────────────────────────────
if ($action === 'register') {
    $email            = trim($_POST['email']            ?? '');
    $username_raw     = trim($_POST['username']         ?? '');
    $password_raw     = $_POST['password']              ?? '';
    $password_confirm = $_POST['password_confirm']      ?? '';

    $redir = '../pages/register.php';

    if (empty($email) || empty($username_raw) || empty($password_raw) || empty($password_confirm)) {
        header("Location: {$redir}?error=" . urlencode("所有字段均为必填项"));
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: {$redir}?error=" . urlencode("邮箱格式不正确"));
        exit;
    }
    if (strlen($password_raw) < 8) {
        header("Location: {$redir}?error=" . urlencode("密码长度至少为 8 位"));
        exit;
    }
    if ($password_raw !== $password_confirm) {
        header("Location: {$redir}?error=" . urlencode("两次输入的密码不一致"));
        exit;
    }

    $safe_email    = $conn->real_escape_string($email);
    $safe_username = $conn->real_escape_string($username_raw);

    $check = $conn->query("SELECT id FROM users WHERE email='$safe_email' OR username='$safe_username'");
    if ($check && $check->num_rows > 0) {
        header("Location: {$redir}?error=" . urlencode("该邮箱或用户名已被占用"));
        exit;
    }

    // 生成唯一数字 ID
    $new_userid = 0;
    do {
        $new_userid = mt_rand(100000, 999999);
        $uid_check  = $conn->query("SELECT id FROM users WHERE userid='$new_userid'");
    } while ($uid_check && $uid_check->num_rows > 0);

    $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);
    $token         = bin2hex(random_bytes(32));

    $verified = EMAIL_VERIFY_REQUIRED ? 0 : 1;
    $sql = "INSERT INTO users (userid, email, password, username, points, role, avatar, level, exp, email_verified, verify_token, verify_token_expires)
            VALUES ('$new_userid', '$safe_email', '$password_hash', '$safe_username',
                    100, 'user', 'default.png', 1, 0, $verified, '$token', DATE_ADD(NOW(), INTERVAL 24 HOUR))";

    if ($conn->query($sql)) {
        if (EMAIL_VERIFY_REQUIRED) {
            send_verify_email($email, $username_raw, $token);
            header("Location: ../pages/register.php?status=pending&email=" . urlencode($email));
        } else {
            header("Location: ../pages/login.php?msg=" . urlencode("注册成功，请登录"));
        }
        exit;
    } else {
        error_log("注册写入失败: " . $conn->error);
        header("Location: {$redir}?error=" . urlencode("注册失败，请稍后重试"));
        exit;
    }
}

// ─────────────────────────────────────────────
// 登录逻辑
// ─────────────────────────────────────────────
if ($action === 'login') {
    $identity = $conn->real_escape_string($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identity) || empty($password)) {
        die("请填写登录信息");
    }

    $sql    = "SELECT * FROM users WHERE email='$identity' OR userid='$identity' OR username='$identity'";
    $result = $conn->query($sql);

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {

            // 账号封禁检查
            if (!empty($user['is_banned'])) {
                $reason = htmlspecialchars($user['ban_reason'] ?: '违反社区规范');
                echo "<!DOCTYPE html><html lang='zh-CN'><head><meta charset='UTF-8'><title>账号已封禁</title></head>
                <body style='font-family:monospace;background:#0d1117;color:#e6edf3;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;'>
                <div style='text-align:center;max-width:420px;padding:32px;background:#161b22;border:1px solid #30363d;border-radius:6px;'>
                <p style='color:#f85149;font-size:18px;margin:0 0 12px;'>&#128683; 账号已封禁</p>
                <p style='color:#8b949e;font-size:13px;line-height:1.7;'>封禁原因：<b style='color:#e6edf3;'>{$reason}</b></p>
                <p style='color:#6e7681;font-size:12px;margin-top:16px;'>如有异议，请联系管理员。</p>
                </div></body></html>";
                exit;
            }

            // 邮箱未验证
            if (EMAIL_VERIFY_REQUIRED && isset($user['email_verified']) && (int)$user['email_verified'] === 0) {
                $safe_email = htmlspecialchars($user['email']);
                echo "<!DOCTYPE html><html lang='zh-CN'><head><meta charset='UTF-8'><title>需要验证邮箱</title></head><body style='font-family:monospace;background:#0d1117;color:#e6edf3;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;'>";
                echo "<div style='text-align:center;max-width:400px;padding:32px;background:#161b22;border:1px solid #30363d;border-radius:6px;'>";
                echo "<p style='color:#d29922;font-size:15px;'>&#9888; 邮箱尚未验证</p>";
                echo "<p style='color:#8b949e;font-size:13px;'>请检查 <b style='color:#e6edf3;'>{$safe_email}</b> 收件箱，点击验证链接后再登录。</p>";
                echo "<form method='POST' action='auth.php' style='margin-top:16px;'><input type='hidden' name='action' value='resend_verify'><input type='hidden' name='email' value='" . htmlspecialchars($user['email']) . "'><button style='background:#3fb950;color:#fff;border:none;padding:8px 20px;border-radius:4px;cursor:pointer;font-family:monospace;'>重新发送验证邮件</button></form>";
                echo "<p style='margin-top:12px;'><a href='../pages/login.php' style='color:#3fb950;font-size:13px;'>返回登录</a></p>";
                echo "</div></body></html>";
                exit;
            }

            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['system_userid'] = $user['userid'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['avatar']        = $user['avatar'];

            // ── 每日登录经验奖励 ──
            $uid_db = (int)$user['id'];
            $today  = date('Y-m-d');
            $last   = $user['last_login_date'] ?? null;

            if ($last !== $today) {
                $yesterday  = date('Y-m-d', strtotime('-1 day'));
                $cur_streak = (int)($user['login_streak'] ?? 0);
                $new_streak = ($last === $yesterday) ? $cur_streak + 1 : 1;
                $exp_gain   = calc_login_exp($new_streak);   // min(streak*10, 100)

                $conn->query("UPDATE users
                              SET last_login_date='$today', login_streak=$new_streak
                              WHERE id=$uid_db");
                $result_exp = add_exp($conn, $uid_db, $exp_gain);

                $_SESSION['login_reward'] = [
                    'exp'    => $exp_gain,
                    'streak' => $new_streak,
                    'level'  => $result_exp['level'],
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

// ─────────────────────────────────────────────
// 重新发送验证邮件
// ─────────────────────────────────────────────
if ($action === 'resend_verify') {
    $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
    if (empty($email)) {
        header("Location: ../pages/login.php");
        exit;
    }

    $r = $conn->query("SELECT id, username, email_verified, verify_resend_at FROM users WHERE email='$email'");
    if (!$r || $r->num_rows === 0) {
        header("Location: ../pages/register.php?status=pending&email=" . urlencode($_POST['email'] ?? ''));
        exit;
    }
    $u = $r->fetch_assoc();

    if ((int)$u['email_verified'] === 1) {
        header("Location: ../pages/login.php?msg=" . urlencode("该邮箱已验证，请直接登录"));
        exit;
    }

    // 冷却检查：60 秒内不能重发
    if (!empty($u['verify_resend_at'])) {
        $last_send = strtotime($u['verify_resend_at']);
        if (time() - $last_send < 60) {
            $wait = 60 - (time() - $last_send);
            header("Location: ../pages/register.php?status=pending&email=" . urlencode($_POST['email'] ?? '') . "&cooldown=" . $wait);
            exit;
        }
    }

    $token = bin2hex(random_bytes(32));
    $uid   = (int)$u['id'];
    $conn->query("UPDATE users SET verify_token='$token', verify_token_expires=DATE_ADD(NOW(), INTERVAL 24 HOUR), verify_resend_at=NOW() WHERE id=$uid");
    send_verify_email($_POST['email'], $u['username'] ?? '', $token);

    header("Location: ../pages/register.php?status=pending&email=" . urlencode($_POST['email'] ?? '') . "&resent=1");
    exit;
}

// ─────────────────────────────────────────────
// 忘记密码
// ─────────────────────────────────────────────
if ($action === 'forgot_password') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../pages/forgot_password.php?error=" . urlencode("邮箱格式不正确"));
        exit;
    }

    $safe_email = $conn->real_escape_string($email);
    $r = $conn->query("SELECT id, username FROM users WHERE email='$safe_email' AND email_verified=1");

    // 无论账号是否存在都显示发送成功（防止枚举）
    if ($r && $r->num_rows > 0) {
        $u           = $r->fetch_assoc();
        $reset_token = bin2hex(random_bytes(32));
        $uid         = (int)$u['id'];
        $conn->query("UPDATE users SET reset_token='$reset_token', reset_token_expires=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id=$uid");
        send_reset_email($email, $u['username'], $reset_token);
    }

    header("Location: ../pages/forgot_password.php?status=sent&email=" . urlencode($email));
    exit;
}

// ─────────────────────────────────────────────
// 重置密码
// ─────────────────────────────────────────────
if ($action === 'reset_password') {
    $token            = trim($_POST['token']            ?? '');
    $password_raw     = $_POST['password']              ?? '';
    $password_confirm = $_POST['password_confirm']      ?? '';

    $redir_base = '../pages/reset_password.php?token=' . urlencode($token);

    if (empty($token)) {
        header("Location: ../pages/forgot_password.php");
        exit;
    }
    if (strlen($password_raw) < 8) {
        header("Location: {$redir_base}&error=" . urlencode("密码至少 8 位"));
        exit;
    }
    if ($password_raw !== $password_confirm) {
        header("Location: {$redir_base}&error=" . urlencode("两次密码不一致"));
        exit;
    }

    $safe_token = $conn->real_escape_string($token);
    $r = $conn->query("SELECT id FROM users WHERE reset_token='$safe_token' AND reset_token_expires > NOW()");

    if (!$r || $r->num_rows === 0) {
        header("Location: ../pages/forgot_password.php?error=" . urlencode("链接已过期，请重新申请"));
        exit;
    }

    $uid  = (int)$r->fetch_assoc()['id'];
    $hash = password_hash($password_raw, PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET password='$hash', reset_token=NULL, reset_token_expires=NULL WHERE id=$uid");

    header("Location: ../pages/login.php?msg=" . urlencode("密码已重置，请登录"));
    exit;
}
