<?php
/**
 * register.php — 注册页
 *
 * 功能：展示注册表单，包含邮箱验证状态提示；表单提交到 actions/auth.php
 * 读写表：无 DB 操作
 * 权限：无
 */
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
    <link rel="icon" type="image/svg+xml" href="../assets/logo.svg">
    <link rel="shortcut icon" href="../assets/logo.svg">
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
            background:
            padding:36px 32px; width:360px;
            animation: fadeUp .4s ease;
            box-shadow: 0 0 40px rgba(0,0,0,.4);
        }
        .reg-card h2 { color:
        .reg-card h2 .accent { color:
        .reg-card h2 .cursor { color:
        .reg-card input { margin-bottom:12px; }
        .reg-card button { width:100%; background:
                           cursor:pointer; font-size:14px; font-weight:700; transition:.2s; font-family:inherit; margin-top:4px; }
        .reg-card button:hover { background:
        .reg-card p { margin-top:16px; font-size:13px; text-align:center; color:
        .reg-card a { color:
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
            <div style="font-size:40px;margin-bottom:12px;">&
            <?php if ($resent): ?>
                <p style="color:#3fb950;font-size:13px;margin:0 0 12px;">&
            <?php endif; ?>
            <?php if ($cooldown > 0): ?>
                <p style="color:#d29922;font-size:13px;margin:0 0 12px;">&
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
                &
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
