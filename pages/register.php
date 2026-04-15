<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>加入我们 - 注册</title>
    <style>
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }
        @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
        .reg-wrap {
            display:flex; justify-content:center; align-items:center;
            min-height:calc(100vh - 56px); padding:20px;
            background-image:
                linear-gradient(rgba(63,185,80,.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(63,185,80,.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .reg-card {
            background:#161b22; border:1px solid #30363d; border-radius:6px;
            padding:36px 32px; width:360px;
            animation: fadeUp .4s ease;
            box-shadow: 0 0 40px rgba(0,0,0,.4);
        }
        .reg-card h2 { color:#e6edf3; margin-bottom:24px; font-size:18px; font-family:"Courier New",monospace; }
        .reg-card h2 .accent { color:#3fb950; }
        .reg-card h2 .cursor { color:#3fb950; animation: blink 1s infinite; }
        .reg-card input { margin-bottom:12px; }
        .reg-card button { width:100%; background:#3fb950; color:#fff; border:none; padding:11px; border-radius:4px;
                           cursor:pointer; font-size:14px; font-weight:700; transition:.2s; font-family:inherit; margin-top:4px; }
        .reg-card button:hover { background:#2ea043; box-shadow:0 0 16px rgba(63,185,80,.4); }
        .reg-card p { margin-top:16px; font-size:13px; text-align:center; color:#6e7681; }
        .reg-card a { color:#3fb950; }
        @media(max-width:480px){
            .reg-wrap { padding: 12px; align-items: flex-start; padding-top: 40px; }
            .reg-card { width: 100%; padding: 28px 20px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="reg-wrap">
    <div class="reg-card">
        <h2>&gt; <span class="accent">REGISTER</span><span class="cursor">_</span></h2>
        <form action="../actions/auth.php" method="POST">
            <input type="hidden" name="action" value="register">
            <input type="email" name="email" placeholder="注册邮箱" required>
            <input type="text" name="username" placeholder="显示昵称" required>
            <input type="password" name="password" placeholder="设置密码" required>
            <button type="submit">立即注册</button>
        </form>
        <p style="font-size: 13px; text-align: center;"><a href="login.php">已有账号？去登录</a></p>
    </div>
</div>
</body>
</html>
