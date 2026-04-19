<?php
/**
 * 502.php — HTTP 502 错误页
 *
 * 功能：向客户端发送 502 Bad Gateway 状态码并展示暗色主题网关错误提示页面。
 * 读写表：无
 * 权限：无
 */
http_response_code(502); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<link rel="shortcut icon" href="assets/logo.svg">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>502 - 网关错误 · 缪斯 MUSE</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;background:
body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;position:relative;overflow:hidden;}
:root{--c:
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(121,192,255,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(121,192,255,.03) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;}
.code{font-size:clamp(100px,20vw,180px);font-weight:900;line-height:1;color:transparent;-webkit-text-stroke:2px rgba(121,192,255,.2);letter-spacing:-4px;position:relative;user-select:none;}
.code::after{content:attr(data-code);position:absolute;inset:0;color:var(--c);-webkit-text-stroke:0;opacity:0;animation:glitch 3s infinite;clip-path:polygon(0 30%,100% 30%,100% 55%,0 55%);filter:blur(.5px);}
@keyframes glitch{0%,85%,100%{opacity:0;transform:none;}87%{opacity:.8;transform:translate(-6px,0);}89%{opacity:.8;transform:translate(5px,1px);}91%{opacity:0;}}
.wrap{text-align:center;position:relative;z-index:1;}
.label{font-size:13px;letter-spacing:3px;color:
.msg{font-size:15px;color:

.status-box{background:
.status-row{display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid 
.status-row:last-child{border-bottom:none;}
.status-row .key{color:
.dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:6px;}
.dot-red{background:
.dot-green{background:
.dot-yellow{background:
@keyframes blink2{0%,100%{opacity:1;}50%{opacity:.3;}}
.prompt{font-size:14px;color:var(--c);margin-bottom:28px;display:flex;align-items:center;justify-content:center;gap:2px;}
.cursor{display:inline-block;width:9px;height:16px;background:var(--c);margin-left:2px;animation:blink .8s step-end infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0;}}
.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
.btn{padding:9px 22px;border-radius:4px;font-size:13px;font-weight:700;text-decoration:none;font-family:inherit;transition:.15s;letter-spacing:.5px;}
.btn-sky{background:
.btn-outline{background:transparent;color:
.scanline{position:fixed;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(121,192,255,.12),transparent);animation:scan 5s linear infinite;pointer-events:none;}
@keyframes scan{0%{top:-2px;}100%{top:100vh;}}
</style>
</head>
<body>
<div class="scanline"></div>
<div class="wrap">
    <div class="code" data-code="502">502</div>
    <div class="label">
    <p class="msg">服务器网关收到了一个<span class="hl">无效响应</span>。<br>后端服务可能正在重启，请稍后重试。</p>
    <div class="status-box">
        <div class="status-row"><span class="key">
        <div class="status-row"><span class="key">
        <div class="status-row"><span class="key">
        <div class="status-row"><span class="key">
    </div>
    <div class="prompt">&gt; service.restart()<span class="cursor"></span></div>
    <div class="actions">
        <a href="javascript:location.reload()" class="btn btn-sky">重试</a>
        <a href="/index.php" class="btn btn-outline">返回首页</a>
    </div>
</div>
</body>
</html>
