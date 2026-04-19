<?php

session_start();
$status        = $_GET['status']   ?? '';
$pending_email = htmlspecialchars($_GET['email']    ?? '');
$error_msg     = htmlspecialchars($_GET['error']    ?? '');
$resent        = !empty($_GET['resent']);
$cooldown      = (int)($_GET['cooldown'] ?? 0);
?>
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
    <?php if ($status === 'pending'): ?>
        <h2>&gt; <span class="accent">CHECK MAIL</span><span class="cursor">_</span></h2>
        <div style="text-align:center;padding:8px 0 20px;">
            <div style="font-size:40px;margin-bottom:12px;">&#9993;</div>
            <?php if ($resent): ?>
                <p style="color:#3fb950;font-size:13px;margin:0 0 12px;">&#10003; 验证邮件已重新发送</p>
            <?php endif; ?>
            <?php if ($cooldown > 0): ?>
                <p style="color:#d29922;font-size:13px;margin:0 0 12px;">&#9888; 请等待 <?= $cooldown ?> 秒后再重新发送</p>
            <?php endif; ?>
            <p style="color:#e6edf3;font-size:14px;line-height:1.7;margin:0 0 10px;">验证邮件已发送至</p>
            <p style="color:#3fb950;font-size:15px;font-weight:700;margin:0 0 16px;"><?= $pending_email ?></p>
            <p style="color:#8b949e;font-size:12px;line-height:1.8;margin:0 0 20px;">请点击邮件中的链接完成验证后再登录。<br>若未收到，请检查垃圾邮件文件夹。</p>
            <form action="../actions/auth.php" method="POST">
                <input type="hidden" name="action" value="resend_verify">
                <input type="hidden" name="email" value="<?= $pending_email ?>">
                <button type="submit" <?= $cooldown > 0 ? 'disabled' : '' ?> style="background:transparent;color:#6e7681;border:1px solid #30363d;padding:7px 18px;border-radius:4px;cursor:pointer;font-size:12px;font-family:inherit;transition:.2s;">重新发送验证邮件</button>
            </form>
        </div>
        <p style="font-size:13px;text-align:center;margin-top:4px;"><a href="login.php">已验证？去登录</a></p>
    <?php else: ?>
        <h2>&gt; <span class="accent">REGISTER</span><span class="cursor">_</span></h2>
        <?php if ($error_msg): ?>
            <div style="background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);border-radius:4px;padding:9px 12px;margin-bottom:14px;color:#f85149;font-size:13px;">
                &#9888; <?= $error_msg ?>
            </div>
        <?php endif; ?>
        <form action="../actions/auth.php" method="POST">
            <input type="hidden" name="action" value="register">
            <input type="email" name="email" placeholder="注册邮箱" required>
            <input type="text" name="username" placeholder="显示昵称" required>
            <input type="password" name="password" placeholder="设置密码（至少8位）" required>
            <input type="password" name="password_confirm" placeholder="确认密码" required>
            <button type="submit">立即注册</button>
        </form>
        <p style="font-size:13px;text-align:center;margin-top:12px;"><a href="login.php">已有账号？去登录</a></p>
    <?php endif; ?>
    </div>
</div>
</body>
</html>
