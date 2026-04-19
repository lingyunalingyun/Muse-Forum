<?php http_response_code(502); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>502 - 网关错误 · 缪斯 MUSE</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;background:#0d1117;color:#e6edf3;font-family:"Courier New",monospace;}
body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;position:relative;overflow:hidden;}
:root{--c:#79c0ff;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(121,192,255,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(121,192,255,.03) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;}
.code{font-size:clamp(100px,20vw,180px);font-weight:900;line-height:1;color:transparent;-webkit-text-stroke:2px rgba(121,192,255,.2);letter-spacing:-4px;position:relative;user-select:none;}
.code::after{content:attr(data-code);position:absolute;inset:0;color:var(--c);-webkit-text-stroke:0;opacity:0;animation:glitch 3s infinite;clip-path:polygon(0 30%,100% 30%,100% 55%,0 55%);filter:blur(.5px);}
@keyframes glitch{0%,85%,100%{opacity:0;transform:none;}87%{opacity:.8;transform:translate(-6px,0);}89%{opacity:.8;transform:translate(5px,1px);}91%{opacity:0;}}
.wrap{text-align:center;position:relative;z-index:1;}
.label{font-size:13px;letter-spacing:3px;color:#6e7681;text-transform:uppercase;margin:16px 0 24px;}.label span{color:var(--c);}
.msg{font-size:15px;color:#8b949e;line-height:1.8;margin-bottom:24px;max-width:440px;}.msg .hl{color:#e6edf3;}
/* 连接状态 */
.status-box{background:#0d1117;border:1px solid #30363d;border-radius:4px;padding:14px 18px;margin-bottom:28px;text-align:left;max-width:440px;width:100%;}
.status-row{display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid #21262d;font-size:12px;}
.status-row:last-child{border-bottom:none;}
.status-row .key{color:#6e7681;}
.dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:6px;}
.dot-red{background:#f85149;box-shadow:0 0 6px #f85149;animation:blink2 1s ease-in-out infinite;}
.dot-green{background:#3fb950;box-shadow:0 0 6px #3fb950;}
.dot-yellow{background:#e3b341;animation:blink2 1.5s ease-in-out infinite;}
@keyframes blink2{0%,100%{opacity:1;}50%{opacity:.3;}}
.prompt{font-size:14px;color:var(--c);margin-bottom:28px;display:flex;align-items:center;justify-content:center;gap:2px;}
.cursor{display:inline-block;width:9px;height:16px;background:var(--c);margin-left:2px;animation:blink .8s step-end infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0;}}
.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
.btn{padding:9px 22px;border-radius:4px;font-size:13px;font-weight:700;text-decoration:none;font-family:inherit;transition:.15s;letter-spacing:.5px;}
.btn-sky{background:#79c0ff;color:#0d1117;border:none;}.btn-sky:hover{background:#58a6ff;box-shadow:0 0 16px rgba(121,192,255,.35);}
.btn-outline{background:transparent;color:#8b949e;border:1px solid #30363d;}.btn-outline:hover{border-color:#6e7681;color:#e6edf3;}
.scanline{position:fixed;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(121,192,255,.12),transparent);animation:scan 5s linear infinite;pointer-events:none;}
@keyframes scan{0%{top:-2px;}100%{top:100vh;}}
</style>
</head>
<body>
<div class="scanline"></div>
<div class="wrap">
    <div class="code" data-code="502">502</div>
    <div class="label">// <span>BAD GATEWAY</span></div>
    <p class="msg">服务器网关收到了一个<span class="hl">无效响应</span>。<br>后端服务可能正在重启，请稍后重试。</p>
    <div class="status-box">
        <div class="status-row"><span class="key">// nginx</span>     <span><span class="dot dot-green"></span>running</span></div>
        <div class="status-row"><span class="key">// php-fpm</span>   <span><span class="dot dot-red"></span>error</span></div>
        <div class="status-row"><span class="key">// database</span> <span><span class="dot dot-yellow"></span>checking</span></div>
        <div class="status-row"><span class="key">// uptime</span>    <span style="color:#6e7681;"><?= date('Y-m-d H:i:s') ?></span></div>
    </div>
    <div class="prompt">&gt; service.restart()<span class="cursor"></span></div>
    <div class="actions">
        <a href="javascript:location.reload()" class="btn btn-sky">重试</a>
        <a href="/index.php" class="btn btn-outline">返回首页</a>
    </div>
</div>
</body>
</html>
