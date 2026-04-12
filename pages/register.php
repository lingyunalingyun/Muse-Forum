<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>加入我们 - 注册</title>
    <style>
        body { background: #f4f7f6; font-family: "Microsoft YaHei"; display: flex; justify-content: center; padding-top: 50px; }
        .reg-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 350px; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; background: #28a745; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="reg-card">
        <h2>创建新账号</h2>
        <form action="../actions/auth.php" method="POST">
            <input type="hidden" name="action" value="register">
            <input type="email" name="email" placeholder="注册邮箱" required>
            <input type="text" name="nickname" placeholder="显示昵称" required>
            <input type="password" name="password" placeholder="设置密码" required>
            <button type="submit">立即注册</button>
        </form>
        <p style="font-size: 13px; text-align: center;"><a href="login.php">已有账号？去登录</a></p>
    </div>
</body>
</html>
