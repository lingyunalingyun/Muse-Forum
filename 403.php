<?php
/**
 * 403.php — HTTP 403 错误页
 *
 * 功能：设置响应码 403 并展示"无权访问"提示页面
 * 读写表：无
 * 权限：公开
 */
http_response_code(403); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<link rel="shortcut icon" href="assets/logo.svg">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>403 - 无权访问 · 缪斯 MUSE</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;background:#0d1117;color:#e6edf3;font-family:"Courier New",monospace;}
body{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:24px;position:relative;overflow:hidden;}
body::before{
    content:'';position:fixed;inset:0;
    background-image:
        linear-gradient(rgba(248,81,73,.03) 1px,transparent 1px),
        linear-gradient(90deg,rgba(248,81,73,.03) 1px,transparent 1px);
    background-size:40px 40px;
    pointer-events:none;
}
.code{
    font-size:clamp(100px,20vw,180px);font-weight:900;line-height:1;
    color:transparent;-webkit-text-stroke:2px rgba(248,81,73,.2);
    letter-spacing:-4px;position:relative;user-select:none;
}
.code::after{
    content:attr(data-code);position:absolute;inset:0;
    color:#f85149;-webkit-text-stroke:0;opacity:0;
    animation:glitch 4s infinite;
    clip-path:polygon(0 40%,100% 40%,100% 60%,0 60%);filter:blur(.5px);
}
@keyframes glitch{
    0%,90%,100%{opacity:0;transform:none;}
    92%{opacity:.7;transform:translate(4px,0);}
    94%{opacity:.7;transform:translate(-3px,2px);}
    96%{opacity:.7;transform:translate(2px,-1px);}
    98%{opacity:0;}
}
.wrap{text-align:center;position:relative;z-index:1;}
.label{font-size:13px;letter-spacing:3px;color:#6e7681;text-transform:uppercase;margin:16px 0 24px;}
.label span{color:#f85149;}
.msg{font-size:15px;color:#8b949e;line-height:1.8;margin-bottom:32px;max-width:420px;}
.msg .highlight{color:#e6edf3;}
.prompt{font-size:14px;color:#f85149;margin-bottom:32px;display:flex;align-items:center;justify-content:center;gap:2px;}
.cursor{display:inline-block;width:9px;height:16px;background:#f85149;margin-left:2px;animation:blink .8s step-end infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0;}}
.lock{font-size:48px;margin-bottom:8px;filter:drop-shadow(0 0 12px rgba(248,81,73,.4));animation:pulse 2s ease-in-out infinite;}
@keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.08);}}
.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
.btn{padding:9px 22px;border-radius:4px;font-size:13px;font-weight:700;text-decoration:none;font-family:inherit;transition:.15s;letter-spacing:.5px;}
.btn-red{background:#f85149;color:#fff;border:none;}
.btn-red:hover{background:#da3633;box-shadow:0 0 16px rgba(248,81,73,.4);}
.btn-outline{background:transparent;color:#8b949e;border:1px solid #30363d;}
.btn-outline:hover{border-color:#6e7681;color:#e6edf3;}
.scanline{position:fixed;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(248,81,73,.12),transparent);animation:scan 6s linear infinite;pointer-events:none;}
@keyframes scan{0%{top:-2px;}100%{top:100vh;}}
</style>
</head>
<body>
<div class="scanline"></div>
<div class="wrap">
    <div class="lock">🔒</div>
    <div class="code" data-code="403">403</div>
    <div class="label">// <span>ACCESS DENIED</span></div>
    <p class="msg">
        你没有权限访问此页面。<br>
        请先<span class="highlight">登录</span>，或确认你的账户拥有相应权限。
    </p>
    <div class="prompt">&gt; permission denied<span class="cursor"></span></div>
    <div class="actions">
        <a href="/pages/login.php" class="btn btn-red">去登录</a>
        <a href="/index.php" class="btn btn-outline">返回首页</a>
    </div>
</div>
</body>
</html>
