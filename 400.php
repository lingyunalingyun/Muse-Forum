<?php
/**
 * 400.php — HTTP 400 错误页
 *
 * 功能：向客户端发送 400 Bad Request 状态码并展示暗色主题错误提示页面。
 * 读写表：无
 * 权限：无
 */
http_response_code(400); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<link rel="shortcut icon" href="assets/logo.svg">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>400 - 请求错误 · 缪斯 MUSE</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;background:
body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;position:relative;overflow:hidden;}
:root{--c:
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(88,166,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(88,166,255,.04) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;}
.code{font-size:clamp(100px,20vw,180px);font-weight:900;line-height:1;color:transparent;-webkit-text-stroke:2px rgba(88,166,255,.2);letter-spacing:-4px;position:relative;user-select:none;}
.code::after{content:attr(data-code);position:absolute;inset:0;color:var(--c);-webkit-text-stroke:0;opacity:0;animation:glitch 4s infinite;clip-path:polygon(0 35%,100% 35%,100% 55%,0 55%);filter:blur(.5px);}
@keyframes glitch{0%,90%,100%{opacity:0;transform:none;}92%{opacity:.7;transform:translate(-4px,0);}94%{opacity:.7;transform:translate(4px,2px);}96%{opacity:0;}}
.wrap{text-align:center;position:relative;z-index:1;}
.label{font-size:13px;letter-spacing:3px;color:
.msg{font-size:15px;color:
.code-block{background:
.code-block .err{color:
.prompt{font-size:14px;color:var(--c);margin-bottom:28px;display:flex;align-items:center;justify-content:center;gap:2px;}
.cursor{display:inline-block;width:9px;height:16px;background:var(--c);margin-left:2px;animation:blink .8s step-end infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0;}}
.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
.btn{padding:9px 22px;border-radius:4px;font-size:13px;font-weight:700;text-decoration:none;font-family:inherit;transition:.15s;letter-spacing:.5px;}
.btn-blue{background:
.btn-outline{background:transparent;color:
.scanline{position:fixed;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(88,166,255,.15),transparent);animation:scan 6s linear infinite;pointer-events:none;}
@keyframes scan{0%{top:-2px;}100%{top:100vh;}}
</style>
</head>
<body>
<div class="scanline"></div>
<div class="wrap">
    <div class="code" data-code="400">400</div>
    <div class="label">
    <p class="msg">服务器无法理解你发送的<span class="hl">请求格式</span>。<br>可能是表单数据有误或请求参数不完整。</p>
    <div class="code-block">
        <div><span class="key">request</span>  : malformed</div>
        <div><span class="key">expected</span> : valid parameters</div>
        <div><span class="err">error</span>    : cannot parse request body</div>
    </div>
    <div class="prompt">&gt; request.validate()<span class="cursor"></span></div>
    <div class="actions">
        <a href="javascript:history.back()" class="btn btn-blue">← 返回重试</a>
        <a href="/index.php" class="btn btn-outline">返回首页</a>
    </div>
</div>
</body>
</html>
