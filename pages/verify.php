<?php
/**
 * verify.php — 邮箱验证页面
 *
 * 功能：通过 URL 中的 token 验证用户邮箱，激活账号，展示验证成功/失败/已验证等状态
 * 读写表：users（更新 email_verified、清除 verify_token）
 * 权限：公开（需持有有效的邮箱验证 token）
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exp_helper.php';

ensure_user_columns($conn);

$token = trim($_GET['token'] ?? '');
$status = '';
$msg    = '';

if (empty($token)) {
    $status = 'error';
    $msg    = '无效的验证链接。';
} else {
    $safe_token = $conn->real_escape_string($token);
    $r = $conn->query("SELECT id, username, email_verified, verify_token_expires FROM users WHERE verify_token='$safe_token'");

    if (!$r || $r->num_rows === 0) {
        $status = 'error';
        $msg    = '链接无效或已过期，请重新注册或联系管理员。';
    } else {
        $u = $r->fetch_assoc();
        if ((int)$u['email_verified'] === 1) {
            $status = 'already';
            $msg    = '邮箱已验证，请直接登录。';
        } elseif (!empty($u['verify_token_expires']) && strtotime($u['verify_token_expires']) < time()) {
            $status = 'expired';
            $msg    = '验证链接已过期（有效期 24 小时），请重新发送验证邮件。';
        } else {
            $conn->query("UPDATE users SET email_verified=1, verify_token=NULL, verify_token_expires=NULL WHERE id=" . (int)$u['id']);
            $status = 'ok';
            $msg    = '邮箱验证成功！欢迎加入 ' . SITE_NAME . '，现在可以登录了。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="../assets/logo.svg">
    <link rel="shortcut icon" href="../assets/logo.svg">
    <title>邮箱验证 - <?= SITE_NAME ?></title>
    <style>
        @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
        body { background:#0d1117; color:#e6edf3; font-family:"Courier New",monospace; margin:0; }
        .wrap { display:flex; justify-content:center; align-items:center; min-height:100vh; padding:20px;
                background-image: linear-gradient(rgba(63,185,80,.03) 1px,transparent 1px),
                                  linear-gradient(90deg,rgba(63,185,80,.03) 1px,transparent 1px);
                background-size:40px 40px; }
        .card { background:#161b22; border:1px solid #30363d; border-radius:6px; padding:40px 36px;
                max-width:420px; width:100%; text-align:center; animation:fadeUp .4s ease;
                box-shadow:0 0 40px rgba(0,0,0,.4); }
        .icon { font-size:48px; margin-bottom:20px; }
        h2 { margin:0 0 12px; font-size:18px; }
        p  { color:#8b949e; font-size:13px; line-height:1.7; margin:0 0 24px; }
        .btn { display:inline-block; background:#3fb950; color:#fff; text-decoration:none;
               padding:10px 28px; border-radius:4px; font-size:14px; font-weight:700;
               transition:.2s; font-family:inherit; }
        .btn:hover { background:#2ea043; box-shadow:0 0 16px rgba(63,185,80,.4); }
        .btn-gray { background:transparent; color:#8b949e; border:1px solid #30363d; }
        .btn-gray:hover { color:#e6edf3; border-color:#8b949e; box-shadow:none; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="wrap">
    <div class="card">
        <?php if ($status === 'ok'): ?>
            <div class="icon">&#10003;</div>
            <h2 style="color:#3fb950;">&gt; VERIFIED_</h2>
            <p><?= htmlspecialchars($msg) ?></p>
            <a href="login.php" class="btn">前往登录</a>
        <?php elseif ($status === 'already'): ?>
            <div class="icon">&#9432;</div>
            <h2 style="color:#58a6ff;">&gt; ALREADY_VERIFIED_</h2>
            <p><?= htmlspecialchars($msg) ?></p>
            <a href="login.php" class="btn">前往登录</a>
        <?php elseif ($status === 'expired'): ?>
            <div class="icon">&#8987;</div>
            <h2 style="color:#d29922;">&gt; LINK_EXPIRED_</h2>
            <p><?= htmlspecialchars($msg) ?></p>
            <form action="../actions/auth.php" method="POST">
                <input type="hidden" name="action" value="resend_verify">
                <input type="hidden" name="email" value="">
                <button type="submit" style="display:none;"></button>
            </form>
            <a href="register.php" class="btn btn-gray" style="margin-right:8px;">返回注册</a>
            <a href="login.php" class="btn" style="margin-top:12px;">去登录</a>
        <?php else: ?>
            <div class="icon">&#9888;</div>
            <h2 style="color:#f85149;">&gt; ERROR_</h2>
            <p><?= htmlspecialchars($msg) ?></p>
            <a href="register.php" class="btn btn-gray">重新注册</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
