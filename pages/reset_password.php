<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exp_helper.php';

ensure_user_columns($conn);

$token     = trim($_GET['token'] ?? '');
$error_msg = htmlspecialchars($_GET['error'] ?? '');
$valid     = false;

if (!empty($token)) {
    $safe_token = $conn->real_escape_string($token);
    $r = $conn->query("SELECT id FROM users WHERE reset_token='$safe_token' AND reset_token_expires > NOW()");
    $valid = ($r && $r->num_rows > 0);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>重置密码 - <?= SITE_NAME ?></title>
    <style>
        @keyframes blink  { 0%,100%{opacity:1} 50%{opacity:0} }
        @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
        .wrap {
            display:flex; justify-content:center; align-items:center;
            min-height:calc(100vh - 56px); padding:20px;
            background-image:
                linear-gradient(rgba(63,185,80,.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(63,185,80,.03) 1px, transparent 1px);
            background-size:40px 40px;
        }
        .card {
            background:#161b22; border:1px solid #30363d; border-radius:6px;
            padding:36px 32px; width:360px;
            animation:fadeUp .4s ease;
            box-shadow:0 0 40px rgba(0,0,0,.4);
        }
        .card h2 { color:#e6edf3; margin-bottom:20px; font-size:18px; font-family:"Courier New",monospace; }
        .card h2 .accent { color:#3fb950; }
        .card h2 .cursor { color:#3fb950; animation:blink 1s infinite; }
        .card input { margin-bottom:12px; }
        .card button { width:100%; background:#3fb950; color:#fff; border:none; padding:11px; border-radius:4px;
                       cursor:pointer; font-size:14px; font-weight:700; transition:.2s; font-family:inherit; margin-top:4px; }
        .card button:hover { background:#2ea043; box-shadow:0 0 16px rgba(63,185,80,.4); }
        .card p { margin-top:16px; font-size:13px; text-align:center; color:#6e7681; }
        .card a { color:#3fb950; }
        @media(max-width:480px){
            .wrap { padding:12px; align-items:flex-start; padding-top:40px; }
            .card { width:100%; padding:28px 20px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="wrap">
    <div class="card">
    <?php if (!$valid): ?>
        <h2>&gt; <span class="accent" style="color:#f85149;">LINK INVALID</span><span class="cursor">_</span></h2>
        <div style="text-align:center;padding:8px 0 16px;">
            <div style="font-size:40px;margin-bottom:12px;">&#9888;</div>
            <p style="color:#8b949e;font-size:13px;line-height:1.7;margin:0 0 20px;">
                <?php if ($error_msg): ?>
                    <?= $error_msg ?>
                <?php else: ?>
                    链接无效或已过期（有效期 1 小时）。<br>请重新申请重置邮件。
                <?php endif; ?>
            </p>
            <a href="forgot_password.php" style="display:inline-block;background:#3fb950;color:#fff;text-decoration:none;padding:10px 24px;border-radius:4px;font-size:14px;font-weight:700;font-family:inherit;">重新申请</a>
        </div>
        <p><a href="login.php">返回登录</a></p>
    <?php else: ?>
        <h2>&gt; <span class="accent">RESET PWD</span><span class="cursor">_</span></h2>
        <?php if ($error_msg): ?>
            <div style="background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);border-radius:4px;padding:9px 12px;margin-bottom:14px;color:#f85149;font-size:13px;">
                &#9888; <?= $error_msg ?>
            </div>
        <?php endif; ?>
        <form action="../actions/auth.php" method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <input type="password" name="password" placeholder="新密码（至少8位）" required>
            <input type="password" name="password_confirm" placeholder="确认新密码" required>
            <button type="submit">确认重置</button>
        </form>
        <p><a href="login.php">返回登录</a></p>
    <?php endif; ?>
    </div>
</div>
</body>
</html>
