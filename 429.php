<?php
/**
 * 429.php — HTTP 429 错误页
 *
 * 功能：设置响应码 429 并展示"请求过多/触发频率限制"提示页面
 * 读写表：无
 * 权限：公开
 */
http_response_code(429); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="assets/logo.svg">
<link rel="shortcut icon" href="assets/logo.svg">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>429 - 请求过多 · 缪斯 MUSE</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;background:#0d1117;color:#e6edf3;font-family:"Courier New",monospace;}
body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;position:relative;overflow:hidden;}
:root{--c:#f0883e;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(240,136,62,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(240,136,62,.03) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;}
.code{font-size:clamp(100px,20vw,180px);font-weight:900;line-height:1;color:transparent;-webkit-text-stroke:2px rgba(240,136,62,.2);letter-spacing:-4px;position:relative;user-select:none;}
.code::after{content:attr(data-code);position:absolute;inset:0;color:var(--c);-webkit-text-stroke:0;opacity:0;animation:glitch 3.5s infinite;clip-path:polygon(0 40%,100% 40%,100% 65%,0 65%);filter:blur(.5px);}
@keyframes glitch{0%,88%,100%{opacity:0;transform:none;}90%{opacity:.8;transform:translate(5px,0);}92%{opacity:.8;transform:translate(-4px,1px);}94%{opacity:0;}}
.wrap{text-align:center;position:relative;z-index:1;}
.label{font-size:13px;letter-spacing:3px;color:#6e7681;text-transform:uppercase;margin:16px 0 24px;}.label span{color:var(--c);}
.msg{font-size:15px;color:#8b949e;line-height:1.8;margin-bottom:24px;max-width:440px;}.msg .hl{color:#e6edf3;}
/* 冷却倒计时 */
.cooldown{background:#0d1117;border:1px solid #30363d;border-left:3px solid var(--c);border-radius:4px;padding:14px 18px;margin-bottom:28px;max-width:440px;width:100%;}
.cooldown-label{font-size:11px;color:#6e7681;letter-spacing:1px;margin-bottom:8px;}
.bar-wrap{background:#21262d;border-radius:2px;height:4px;overflow:hidden;margin-bottom:8px;}
.bar{height:100%;background:var(--c);border-radius:2px;animation:drain 30s linear forwards;}
@keyframes drain{0%{width:100%;}100%{width:0%;}}
.countdown{font-size:22px;font-weight:700;color:var(--c);}
.countdown small{font-size:13px;color:#6e7681;font-weight:normal;}
.prompt{font-size:14px;color:var(--c);margin-bottom:28px;display:flex;align-items:center;justify-content:center;gap:2px;}
.cursor{display:inline-block;width:9px;height:16px;background:var(--c);margin-left:2px;animation:blink .8s step-end infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0;}}
.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
.btn{padding:9px 22px;border-radius:4px;font-size:13px;font-weight:700;text-decoration:none;font-family:inherit;transition:.15s;letter-spacing:.5px;}
.btn-orange{background:#f0883e;color:#fff;border:none;}.btn-orange:hover{background:#da6820;box-shadow:0 0 16px rgba(240,136,62,.4);}
.btn-outline{background:transparent;color:#8b949e;border:1px solid #30363d;}.btn-outline:hover{border-color:#6e7681;color:#e6edf3;}
.scanline{position:fixed;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(240,136,62,.12),transparent);animation:scan 6s linear infinite;pointer-events:none;}
@keyframes scan{0%{top:-2px;}100%{top:100vh;}}
</style>
</head>
<body>
<div class="scanline"></div>
<div class="wrap">
    <div class="code" data-code="429">429</div>
    <div class="label">// <span>TOO MANY REQUESTS</span></div>
    <p class="msg">你的请求太频繁了，已触发<span class="hl">速率限制</span>。<br>请稍等片刻后再重试。</p>
    <div class="cooldown">
        <div class="cooldown-label">// RATE LIMIT COOLDOWN</div>
        <div class="bar-wrap"><div class="bar"></div></div>
        <div class="countdown"><span id="timer">30</span> <small>秒后可重试</small></div>
    </div>
    <div class="prompt">&gt; await cooldown()<span class="cursor"></span></div>
    <div class="actions">
        <a href="javascript:location.reload()" class="btn btn-orange" id="retry-btn" style="opacity:.4;pointer-events:none;">重试</a>
        <a href="/index.php" class="btn btn-outline">返回首页</a>
    </div>
</div>
<script>
let s = 30;
const t = document.getElementById('timer');
const b = document.getElementById('retry-btn');
const id = setInterval(() => {
    s--;
    t.textContent = s;
    if (s <= 0) {
        clearInterval(id);
        t.textContent = '0';
        b.style.opacity = '1';
        b.style.pointerEvents = 'auto';
    }
}, 1000);
</script>
</body>
</html>
