<?php
/**
 * login.php — 登录页
 *
 * 功能：展示登录表单，表单 POST 到 actions/auth.php 处理
 * 读写表：无 DB 操作
 * 权限：无
 */
session_start();
$flash_msg = htmlspecialchars($_GET['msg'] ?? '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="../assets/logo.svg">
    <link rel="shortcut icon" href="../assets/logo.svg">
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
            background:
            padding:36px 32px; width:360px;
            animation: fadeUp .4s ease;
            box-shadow: 0 0 40px rgba(0,0,0,.4);
        }
        .login-card h2 { color:
        .login-card h2 .accent { color:
        .login-card h2 .cursor { color:
        .login-card input { margin-bottom:12px; }
        .login-card button { width:100%; background:
                             cursor:pointer; font-size:14px; font-weight:700; transition:.2s; font-family:inherit; margin-top:4px; }
        .login-card button:hover { background:
        .footer-links { margin-top:18px; font-size:13px; color:
        .footer-links a { color:
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
                &
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
