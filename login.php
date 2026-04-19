<?php

session_start();
$flash_msg = htmlspecialchars($_GET['msg'] ?? '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>欢迎回来 - 登录</title>
    <style>
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }
        @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
        .login-wrap {
            display:flex; justify-content:center; align-items:center;
            min-height:calc(100vh - 56px); padding:20px;
            background-image:
                linear-gradient(rgba(63,185,80,.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(63,185,80,.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .login-card {
            background:#161b22; border:1px solid #30363d; border-radius:6px;
            padding:36px 32px; width:360px;
            animation: fadeUp .4s ease;
            box-shadow: 0 0 40px rgba(0,0,0,.4);
        }
        .login-card h2 { color:#e6edf3; margin-bottom:24px; font-size:18px; font-family:"Courier New",monospace; }
        .login-card h2 .accent { color:#3fb950; }
        .login-card h2 .cursor { color:#3fb950; animation: blink 1s infinite; }
        .login-card input { margin-bottom:12px; }
        .login-card button { width:100%; background:#3fb950; color:#fff; border:none; padding:11px; border-radius:4px;
                             cursor:pointer; font-size:14px; font-weight:700; transition:.2s; font-family:inherit; margin-top:4px; }
        .login-card button:hover { background:#2ea043; box-shadow:0 0 16px rgba(63,185,80,.4); }
        .footer-links { margin-top:18px; font-size:13px; color:#6e7681; text-align:center; }
        .footer-links a { color:#3fb950; }
        @media(max-width:480px){
            .login-wrap { padding: 12px; align-items: flex-start; padding-top: 40px; }
            .login-card { width: 100%; padding: 28px 20px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="login-wrap">
    <div class="login-card">
        <h2>&gt; <span class="accent">LOGIN</span><span class="cursor">_</span></h2>
        <?php if ($flash_msg): ?>
            <div style="background:rgba(63,185,80,.1);border:1px solid rgba(63,185,80,.3);border-radius:4px;padding:9px 12px;margin-bottom:14px;color:#3fb950;font-size:13px;">
                &#10003; <?= $flash_msg ?>
            </div>
        <?php endif; ?>
        <form action="../actions/auth.php" method="POST">
            <input type="hidden" name="action" value="login">
            <input type="text" name="identity" placeholder="邮箱 / 系统ID / 用户名" required>
            <input type="password" name="password" placeholder="请输入密码" required>
            <button type="submit">进入社区</button>
        </form>
        <div class="footer-links">
            <a href="forgot_password.php">忘记密码？</a>
            &nbsp;·&nbsp;
            还没有账号？ <a href="register.php">立即注册</a>
        </div>
    </div>
</div>
</body>
</html>
