<?php
/**
 * forgot_password.php — 忘记密码页面
 *
 * 功能：用户输入邮箱后发送密码重置链接，展示发送状态与错误提示
 * 读写表：users（写入 reset_token、reset_token_expires）
 * 权限：公开
 */
session_start();
$status    = $_GET['status'] ?? '';
$error_msg = htmlspecialchars($_GET['error'] ?? '');
$sent_email = htmlspecialchars($_GET['email'] ?? '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="../assets/logo.svg">
    <link rel="shortcut icon" href="../assets/logo.svg">
    <title>忘记密码 - <?php require_once __DIR__ . '/../config.php'; echo SITE_NAME; ?></title>
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
    <?php if ($status === 'sent'): ?>
        <h2>&gt; <span class="accent">CHECK MAIL</span><span class="cursor">_</span></h2>
        <div style="text-align:center;padding:8px 0 20px;">
            <div style="font-size:40px;margin-bottom:12px;">&#9993;</div>
            <p style="color:#e6edf3;font-size:14px;line-height:1.7;margin:0 0 10px;">如果该邮箱已注册，重置链接已发送至</p>
            <p style="color:#3fb950;font-size:15px;font-weight:700;margin:0 0 16px;"><?= $sent_email ?></p>
            <p style="color:#8b949e;font-size:12px;line-height:1.8;margin:0;">链接 1 小时内有效。<br>若未收到，请检查垃圾邮件文件夹。</p>
        </div>
        <p><a href="login.php">返回登录</a></p>
    <?php else: ?>
        <h2>&gt; <span class="accent">FORGOT PWD</span><span class="cursor">_</span></h2>
        <p style="color:#8b949e;font-size:13px;margin:0 0 20px;text-align:left;">输入注册邮箱，我们将发送密码重置链接。</p>
        <?php if ($error_msg): ?>
            <div style="background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);border-radius:4px;padding:9px 12px;margin-bottom:14px;color:#f85149;font-size:13px;">
                &#9888; <?= $error_msg ?>
            </div>
        <?php endif; ?>
        <form action="../actions/auth.php" method="POST">
            <input type="hidden" name="action" value="forgot_password">
            <input type="email" name="email" placeholder="注册邮箱" required>
            <button type="submit">发送重置邮件</button>
        </form>
        <p><a href="login.php">返回登录</a></p>
    <?php endif; ?>
    </div>
</div>
</body>
</html>
