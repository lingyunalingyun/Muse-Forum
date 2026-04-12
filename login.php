<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>欢迎回来 - 登录</title>
    <style>
        body { background: #f4f7f6; font-family: "Microsoft YaHei", sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); width: 350px; text-align: center; }
        h2 { color: #333; margin-bottom: 25px; }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        button { width: 100%; background: #007bff; color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer; font-size: 16px; margin-top: 15px; transition: 0.3s; }
        button:hover { background: #0056b3; }
        .footer-links { margin-top: 20px; font-size: 13px; color: #666; }
        .footer-links a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>用户登录</h2>
        <form action="auth.php" method="POST">
            <input type="hidden" name="action" value="login">
            <input type="text" name="identity" placeholder="邮箱 / 系统ID / 用户名" required>
            <input type="password" name="password" placeholder="请输入密码" required>
            <button type="submit">进入社区</button>
        </form>
        <div class="footer-links">
            还没有账号？ <a href="register.php">立即注册</a>
        </div>
    </div>
</body>
</html>