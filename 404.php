<?php
/**
 * 404.php — HTTP 404 错误页
 *
 * 功能：向客户端发送 404 Not Found 状态码并展示暗色主题页面不存在提示页面。
 * 读写表：无
 * 权限：无
 */
http_response_code(404); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<link rel="shortcut icon" href="assets/logo.svg">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>404 - 页面不存在 · 缪斯 MUSE</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;background:
body{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:24px;position:relative;overflow:hidden;}

body::before{
    content:'';position:fixed;inset:0;
    background-image:
        linear-gradient(rgba(63,185,80,.04) 1px,transparent 1px),
        linear-gradient(90deg,rgba(63,185,80,.04) 1px,transparent 1px);
    background-size:40px 40px;
    pointer-events:none;
}

.code{
    font-size:clamp(100px,20vw,180px);
    font-weight:900;
    line-height:1;
    color:transparent;
    -webkit-text-stroke:2px rgba(63,185,80,.25);
    letter-spacing:-4px;
    position:relative;
    user-select:none;
}
.code::after{
    content:attr(data-code);
    position:absolute;inset:0;
    color:
    -webkit-text-stroke:0;
    opacity:0;
    animation:glitch 4s infinite;
    clip-path:polygon(0 30%,100% 30%,100% 50%,0 50%);
    filter:blur(.5px);
}
@keyframes glitch{
    0%,90%,100%{opacity:0;transform:none;}
    92%{opacity:.7;transform:translate(-4px,0);}
    94%{opacity:.7;transform:translate(4px,2px);}
    96%{opacity:.7;transform:translate(-2px,-1px);}
    98%{opacity:0;}
}

.wrap{text-align:center;position:relative;z-index:1;}

.label{
    font-size:13px;letter-spacing:3px;color:
    text-transform:uppercase;margin:16px 0 24px;
}
.label span{color:

.msg{
    font-size:15px;color:
    max-width:420px;
}
.msg .highlight{color:

.prompt{
    font-size:14px;color:
    display:flex;align-items:center;justify-content:center;gap:2px;
}
.cursor{
    display:inline-block;width:9px;height:16px;
    background:
    animation:blink .8s step-end infinite;
}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0;}}

.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
.btn{
    padding:9px 22px;border-radius:4px;font-size:13px;font-weight:700;
    text-decoration:none;font-family:inherit;transition:.15s;letter-spacing:.5px;
}
.btn-green{background:
.btn-green:hover{background:
.btn-outline{background:transparent;color:
.btn-outline:hover{border-color:

.scanline{
    position:fixed;top:0;left:0;right:0;height:2px;
    background:linear-gradient(90deg,transparent,rgba(63,185,80,.15),transparent);
    animation:scan 6s linear infinite;pointer-events:none;
}
@keyframes scan{0%{top:-2px;}100%{top:100vh;}}
</style>
</head>
<body>
<div class="scanline"></div>
<div class="wrap">
    <div class="code" data-code="404">404</div>
    <div class="label">
    <p class="msg">
        你访问的页面<span class="highlight">不存在</span>或已被删除。<br>
        请检查链接是否正确，或返回首页重新探索。
    </p>
    <div class="prompt">&gt; cd /home<span class="cursor"></span></div>
    <div class="actions">
        <a href="/index.php" class="btn btn-green">返回首页</a>
        <a href="javascript:history.back()" class="btn btn-outline">← 上一页</a>
    </div>
</div>
</body>
</html>
