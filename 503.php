<?php http_response_code(503); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>503 - 服务维护中 · 缪斯 MUSE</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;background:#0d1117;color:#e6edf3;font-family:"Courier New",monospace;}
body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;position:relative;overflow:hidden;}
:root{--c:#3fb950;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(63,185,80,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(63,185,80,.04) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;}
.code{font-size:clamp(100px,20vw,180px);font-weight:900;line-height:1;color:transparent;-webkit-text-stroke:2px rgba(63,185,80,.15);letter-spacing:-4px;position:relative;user-select:none;}
.wrap{text-align:center;position:relative;z-index:1;}
/* 齿轮动画 */
.gears{font-size:52px;margin-bottom:8px;display:flex;gap:0;justify-content:center;align-items:center;}
.gear1{display:inline-block;animation:spin 3s linear infinite;}
.gear2{display:inline-block;animation:spin 3s linear infinite reverse;margin-left:-6px;}
@keyframes spin{from{transform:rotate(0deg);}to{transform:rotate(360deg);}}
.label{font-size:13px;letter-spacing:3px;color:#6e7681;text-transform:uppercase;margin:16px 0 24px;}.label span{color:var(--c);}
.msg{font-size:15px;color:#8b949e;line-height:1.8;margin-bottom:24px;max-width:440px;}.msg .hl{color:#e6edf3;}
/* 维护进度 */
.progress-box{background:#0d1117;border:1px solid #30363d;border-radius:4px;padding:16px 18px;margin-bottom:28px;max-width:440px;width:100%;}
.progress-label{font-size:11px;color:#6e7681;letter-spacing:1px;margin-bottom:10px;display:flex;justify-content:space-between;}
.progress-label span{color:var(--c);}
.bar-wrap{background:#21262d;border-radius:2px;height:6px;overflow:hidden;margin-bottom:10px;}
.bar{height:100%;width:73%;background:linear-gradient(90deg,#3fb950,#2ea043);border-radius:2px;position:relative;overflow:hidden;}
.bar::after{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);animation:shimmer 1.5s infinite;}
@keyframes shimmer{0%{transform:translateX(-100%);}100%{transform:translateX(100%);}}
.tasks{display:flex;flex-direction:column;gap:4px;}
.task{font-size:12px;display:flex;align-items:center;gap:8px;color:#6e7681;}
.task .ok{color:var(--c);}.task .spin{animation:spin2 1s linear infinite;display:inline-block;color:#e3b341;}
@keyframes spin2{from{transform:rotate(0);}to{transform:rotate(360deg);}}
.task .wait{color:#484f58;}
.prompt{font-size:14px;color:var(--c);margin-bottom:28px;display:flex;align-items:center;justify-content:center;gap:2px;}
.cursor{display:inline-block;width:9px;height:16px;background:var(--c);margin-left:2px;animation:blink .8s step-end infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0;}}
.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
.btn{padding:9px 22px;border-radius:4px;font-size:13px;font-weight:700;text-decoration:none;font-family:inherit;transition:.15s;letter-spacing:.5px;}
.btn-green{background:#3fb950;color:#fff;border:none;}.btn-green:hover{background:#2ea043;box-shadow:0 0 16px rgba(63,185,80,.4);}
.btn-outline{background:transparent;color:#8b949e;border:1px solid #30363d;}.btn-outline:hover{border-color:#6e7681;color:#e6edf3;}
.scanline{position:fixed;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(63,185,80,.15),transparent);animation:scan 6s linear infinite;pointer-events:none;}
@keyframes scan{0%{top:-2px;}100%{top:100vh;}}
</style>
</head>
<body>
<div class="scanline"></div>
<div class="wrap">
    <div class="gears"><span class="gear1">⚙️</span><span class="gear2">⚙️</span></div>
    <div class="code" data-code="503">503</div>
    <div class="label">// <span>SERVICE UNAVAILABLE</span></div>
    <p class="msg">缪斯 MUSE 正在进行<span class="hl">系统维护</span>。<br>我们将尽快恢复服务，感谢你的耐心等待。</p>
    <div class="progress-box">
        <div class="progress-label"><span>// 维护进度</span><span>73%</span></div>
        <div class="bar-wrap"><div class="bar"></div></div>
        <div class="tasks">
            <div class="task"><span class="ok">✓</span> 数据备份完成</div>
            <div class="task"><span class="ok">✓</span> 依赖更新完成</div>
            <div class="task"><span class="spin">◌</span> 数据库迁移中...</div>
            <div class="task"><span class="wait">○</span> 服务重启</div>
        </div>
    </div>
    <div class="prompt">&gt; maintenance.progress()<span class="cursor"></span></div>
    <div class="actions">
        <a href="javascript:location.reload()" class="btn btn-green">刷新页面</a>
        <a href="javascript:history.back()" class="btn btn-outline">← 返回</a>
    </div>
</div>
</body>
</html>
