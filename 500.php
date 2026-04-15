<?php http_response_code(500); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>500 - 服务器错误 · 缪斯 MUSE</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;background:#0d1117;color:#e6edf3;font-family:"Courier New",monospace;}
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
    color:#e3b341;-webkit-text-stroke:0;opacity:0;
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
.label{font-size:13px;letter-spacing:3px;color:#6e7681;text-transform:uppercase;margin:16px 0 24px;}
.label span{color:#e3b341;}
.msg{font-size:15px;color:#8b949e;line-height:1.8;margin-bottom:24px;max-width:460px;}
.msg .highlight{color:#e6edf3;}

/* 模拟终端日志 */
.log-box{
    background:#0d1117;border:1px solid #30363d;border-radius:6px;
    padding:14px 18px;margin-bottom:28px;text-align:left;
    max-width:460px;width:100%;font-size:12px;line-height:2;
}
.log-line{color:#6e7681;}
.log-line .ts{color:#484f58;}
.log-line .err{color:#f85149;}
.log-line .warn{color:#e3b341;}
.log-line .ok{color:#3fb950;}

.prompt{font-size:14px;color:#e3b341;margin-bottom:28px;display:flex;align-items:center;justify-content:center;gap:2px;}
.cursor{display:inline-block;width:9px;height:16px;background:#e3b341;margin-left:2px;animation:blink .8s step-end infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0;}}
.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
.btn{padding:9px 22px;border-radius:4px;font-size:13px;font-weight:700;text-decoration:none;font-family:inherit;transition:.15s;letter-spacing:.5px;}
.btn-yellow{background:#e3b341;color:#0d1117;border:none;}
.btn-yellow:hover{background:#d4a017;box-shadow:0 0 16px rgba(227,179,65,.35);}
.btn-outline{background:transparent;color:#8b949e;border:1px solid #30363d;}
.btn-outline:hover{border-color:#6e7681;color:#e6edf3;}
.scanline{position:fixed;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(227,179,65,.1),transparent);animation:scan 5s linear infinite;pointer-events:none;}
@keyframes scan{0%{top:-2px;}100%{top:100vh;}}
</style>
</head>
<body>
<div class="scanline"></div>
<div class="wrap">
    <div class="code" data-code="500">500</div>
    <div class="label">// <span>INTERNAL SERVER ERROR</span></div>
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
