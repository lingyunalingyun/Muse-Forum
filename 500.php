<?php
/**
 * 500.php — HTTP 500 错误页
 *
 * 功能：向客户端发送 500 Internal Server Error 状态码并展示暗色主题服务器内部错误提示页面。
 * 读写表：无
 * 权限：无
 */
http_response_code(500); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<link rel="shortcut icon" href="assets/logo.svg">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>500 - 服务器错误 · 缪斯 MUSE</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;background:
body{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:24px;position:relative;overflow:hidden;}
body::before{
    content:'';position:fixed;inset:0;
    background-image:
        linear-gradient(rgba(210,153,34,.03) 1px,transparent 1px),
        linear-gradient(90deg,rgba(210,153,34,.03) 1px,transparent 1px);
    background-size:40px 40px;pointer-events:none;
}
.code{
    font-size:clamp(100px,20vw,180px);font-weight:900;line-height:1;
    color:transparent;-webkit-text-stroke:2px rgba(227,179,65,.2);
    letter-spacing:-4px;position:relative;user-select:none;
}
.code::after{
    content:attr(data-code);position:absolute;inset:0;
    color:
    animation:glitch 3s infinite;
    clip-path:polygon(0 20%,100% 20%,100% 45%,0 45%);filter:blur(.5px);
}
@keyframes glitch{
    0%,85%,100%{opacity:0;transform:none;}
    87%{opacity:.8;transform:translate(-5px,1px);}
    89%{opacity:.8;transform:translate(5px,-1px);}
    91%{opacity:.8;transform:translate(-3px,2px);}
    93%{opacity:0;}
}
.wrap{text-align:center;position:relative;z-index:1;}
.label{font-size:13px;letter-spacing:3px;color:
.label span{color:
.msg{font-size:15px;color:
.msg .highlight{color:

.log-box{
    background:
    padding:14px 18px;margin-bottom:28px;text-align:left;
    max-width:460px;width:100%;font-size:12px;line-height:2;
}
.log-line{color:
.log-line .ts{color:
.log-line .err{color:
.log-line .warn{color:
.log-line .ok{color:

.prompt{font-size:14px;color:
.cursor{display:inline-block;width:9px;height:16px;background:
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0;}}
.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
.btn{padding:9px 22px;border-radius:4px;font-size:13px;font-weight:700;text-decoration:none;font-family:inherit;transition:.15s;letter-spacing:.5px;}
.btn-yellow{background:
.btn-yellow:hover{background:
.btn-outline{background:transparent;color:
.btn-outline:hover{border-color:
.scanline{position:fixed;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(227,179,65,.1),transparent);animation:scan 5s linear infinite;pointer-events:none;}
@keyframes scan{0%{top:-2px;}100%{top:100vh;}}
</style>
</head>
<body>
<div class="scanline"></div>
<div class="wrap">
    <div class="code" data-code="500">500</div>
    <div class="label">
    <p class="msg">服务器遇到了一个<span class="highlight">意外错误</span>，无法完成请求。<br>我们已记录此问题，请稍后重试。</p>

    <div class="log-box">
        <div class="log-line"><span class="ts">[<?= date('H:i:s') ?>]</span> <span class="err">ERROR</span> Unhandled exception caught</div>
        <div class="log-line"><span class="ts">[<?= date('H:i:s') ?>]</span> <span class="warn">WARN </span> Attempting graceful recovery...</div>
        <div class="log-line"><span class="ts">[<?= date('H:i:s') ?>]</span> <span class="ok">INFO </span> Please try again later</div>
    </div>

    <div class="prompt">&gt; system.restart()<span class="cursor"></span></div>
    <div class="actions">
        <a href="javascript:location.reload()" class="btn btn-yellow">重试</a>
        <a href="/index.php" class="btn btn-outline">返回首页</a>
    </div>
</div>
</body>
</html>
