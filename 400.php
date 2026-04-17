<?php http_response_code(400); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>400 - 请求错误 · 缪斯 MUSE</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;background:#0d1117;color:#e6edf3;font-family:"Courier New",monospace;}
body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;position:relative;overflow:hidden;}
:root{--c:#58a6ff;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(88,166,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(88,166,255,.04) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;}
.code{font-size:clamp(100px,20vw,180px);font-weight:900;line-height:1;color:transparent;-webkit-text-stroke:2px rgba(88,166,255,.2);letter-spacing:-4px;position:relative;user-select:none;}
.code::after{content:attr(data-code);position:absolute;inset:0;color:var(--c);-webkit-text-stroke:0;opacity:0;animation:glitch 4s infinite;clip-path:polygon(0 35%,100% 35%,100% 55%,0 55%);filter:blur(.5px);}
@keyframes glitch{0%,90%,100%{opacity:0;transform:none;}92%{opacity:.7;transform:translate(-4px,0);}94%{opacity:.7;transform:translate(4px,2px);}96%{opacity:0;}}
.wrap{text-align:center;position:relative;z-index:1;}
.label{font-size:13px;letter-spacing:3px;color:#6e7681;text-transform:uppercase;margin:16px 0 24px;}.label span{color:var(--c);}
.msg{font-size:15px;color:#8b949e;line-height:1.8;margin-bottom:24px;max-width:440px;}.msg .hl{color:#e6edf3;}
.code-block{background:#0d1117;border:1px solid #30363d;border-left:3px solid var(--c);border-radius:4px;padding:12px 16px;margin-bottom:28px;text-align:left;font-size:12px;color:#6e7681;max-width:440px;width:100%;}
.code-block .err{color:#f85149;}.code-block .key{color:var(--c);}
.prompt{font-size:14px;color:var(--c);margin-bottom:28px;display:flex;align-items:center;justify-content:center;gap:2px;}
.cursor{display:inline-block;width:9px;height:16px;background:var(--c);margin-left:2px;animation:blink .8s step-end infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0;}}
.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
.btn{padding:9px 22px;border-radius:4px;font-size:13px;font-weight:700;text-decoration:none;font-family:inherit;transition:.15s;letter-spacing:.5px;}
.btn-blue{background:#58a6ff;color:#0d1117;border:none;}.btn-blue:hover{background:#388bfd;box-shadow:0 0 16px rgba(88,166,255,.4);}
.btn-outline{background:transparent;color:#8b949e;border:1px solid #30363d;}.btn-outline:hover{border-color:#6e7681;color:#e6edf3;}
.scanline{position:fixed;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(88,166,255,.15),transparent);animation:scan 6s linear infinite;pointer-events:none;}
@keyframes scan{0%{top:-2px;}100%{top:100vh;}}
</style>
</head>
<body>
<div class="scanline"></div>
<div class="wrap">
    <div class="code" data-code="400">400</div>
    <div class="label">// <span>BAD REQUEST</span></div>
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
