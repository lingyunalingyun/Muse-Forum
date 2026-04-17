<?php http_response_code(401); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>401 - 未登录 · 缪斯 MUSE</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;background:#0d1117;color:#e6edf3;font-family:"Courier New",monospace;}
body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;position:relative;overflow:hidden;}
:root{--c:#d2a8ff;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(210,168,255,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(210,168,255,.03) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;}
.code{font-size:clamp(100px,20vw,180px);font-weight:900;line-height:1;color:transparent;-webkit-text-stroke:2px rgba(210,168,255,.2);letter-spacing:-4px;position:relative;user-select:none;}
.code::after{content:attr(data-code);position:absolute;inset:0;color:var(--c);-webkit-text-stroke:0;opacity:0;animation:glitch 4s infinite;clip-path:polygon(0 25%,100% 25%,100% 50%,0 50%);filter:blur(.5px);}
@keyframes glitch{0%,90%,100%{opacity:0;transform:none;}92%{opacity:.7;transform:translate(3px,-1px);}94%{opacity:.7;transform:translate(-4px,2px);}96%{opacity:0;}}
.wrap{text-align:center;position:relative;z-index:1;}
.icon{font-size:44px;margin-bottom:8px;filter:drop-shadow(0 0 14px rgba(210,168,255,.4));animation:pulse 2.5s ease-in-out infinite;}
@keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.06);}}
.label{font-size:13px;letter-spacing:3px;color:#6e7681;text-transform:uppercase;margin:16px 0 24px;}.label span{color:var(--c);}
.msg{font-size:15px;color:#8b949e;line-height:1.8;margin-bottom:32px;max-width:420px;}.msg .hl{color:#e6edf3;}
.prompt{font-size:14px;color:var(--c);margin-bottom:28px;display:flex;align-items:center;justify-content:center;gap:2px;}
.cursor{display:inline-block;width:9px;height:16px;background:var(--c);margin-left:2px;animation:blink .8s step-end infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0;}}
.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
.btn{padding:9px 22px;border-radius:4px;font-size:13px;font-weight:700;text-decoration:none;font-family:inherit;transition:.15s;letter-spacing:.5px;}
.btn-purple{background:#d2a8ff;color:#0d1117;border:none;}.btn-purple:hover{background:#bf87fb;box-shadow:0 0 16px rgba(210,168,255,.4);}
.btn-outline{background:transparent;color:#8b949e;border:1px solid #30363d;}.btn-outline:hover{border-color:#6e7681;color:#e6edf3;}
.scanline{position:fixed;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(210,168,255,.12),transparent);animation:scan 6s linear infinite;pointer-events:none;}
@keyframes scan{0%{top:-2px;}100%{top:100vh;}}
</style>
</head>
<body>
<div class="scanline"></div>
<div class="wrap">
    <div class="icon">👤</div>
    <div class="code" data-code="401">401</div>
    <div class="label">// <span>UNAUTHORIZED</span></div>
    <p class="msg">此页面需要<span class="hl">登录</span>后才能访问。<br>请先登录你的账号，再继续操作。</p>
    <div class="prompt">&gt; auth.login()<span class="cursor"></span></div>
    <div class="actions">
        <a href="/pages/login.php" class="btn btn-purple">立即登录</a>
        <a href="/pages/register.php" class="btn btn-outline">注册账号</a>
    </div>
</div>
</body>
</html>
