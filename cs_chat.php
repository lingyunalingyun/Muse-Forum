<?php

session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

$conn->query("CREATE TABLE IF NOT EXISTS cs_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(80) NOT NULL,
    description TEXT,
    status ENUM('pending','active','resolved') DEFAULT 'pending',
    cs_id INT DEFAULT NULL,
    last_cs_reply_at DATETIME DEFAULT NULL,
    next_comp_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL
)");
$conn->query("CREATE TABLE IF NOT EXISTS cs_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    sender_id INT NOT NULL,
    is_cs TINYINT(1) DEFAULT 0,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$ticket = null;
$r = $conn->query("SELECT * FROM cs_tickets WHERE user_id=$uid AND status IN ('pending','active') ORDER BY id DESC LIMIT 1");
if ($r) $ticket = $r->fetch_assoc();

$messages   = [];
$last_msg_id = 0;
if ($ticket && $ticket['status'] === 'active') {
    $tid = (int)$ticket['id'];
    $mr  = $conn->query("SELECT * FROM cs_messages WHERE ticket_id=$tid ORDER BY id ASC");
    while ($m = $mr->fetch_assoc()) {
        $messages[]  = $m;
        $last_msg_id = max($last_msg_id, (int)$m['id']);
    }
}

$issue_types = [
    'account'    => '账号问题',
    'content'    => '内容投诉',
    'technical'  => '技术故障',
    'punishment' => '处罚申诉',
    'suggestion' => '功能建议',
    'other'      => '其他问题',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>客服中心 - <?= SITE_NAME ?></title>
    <style>
        @keyframes fadeUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
        .cs-wrap { max-width:680px; margin:32px auto; padding:0 16px 60px; }
        .cs-card  { background:#161b22; border:1px solid #30363d; border-radius:6px; overflow:hidden; animation:fadeUp .3s ease; }
        .cs-head  { padding:14px 20px; border-bottom:1px solid #21262d; display:flex; align-items:center; justify-content:space-between; }
        .cs-head h2 { margin:0; font-size:11px; font-weight:700; color:#6e7681; letter-spacing:1.5px; text-transform:uppercase; font-family:"Courier New",monospace; }
        .cs-head h2::before { content:'// '; opacity:.6; }
        .cs-body  { padding:24px 20px; }

        .type-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:18px; }
        .type-btn  {
            background:#0d1117; border:1px solid #30363d; border-radius:4px;
            padding:12px 14px; cursor:pointer; text-align:left;
            color:#8b949e; font-size:13px; font-family:inherit; transition:.2s;
        }
        .type-btn:hover, .type-btn.selected { border-color:#3fb950; color:#e6edf3; background:rgba(63,185,80,.07); }
        .type-btn.selected { color:#3fb950; }
        .type-btn .type-icon { font-size:18px; display:block; margin-bottom:6px; }

        .cs-textarea {
            width:100%; box-sizing:border-box;
            background:#0d1117; border:1px solid #30363d; border-radius:4px;
            color:#e6edf3; font-size:13px; font-family:inherit;
            padding:10px 12px; resize:vertical; min-height:80px;
            outline:none; transition:.2s;
        }
        .cs-textarea:focus { border-color:#3fb950; }
        .btn-submit {
            margin-top:14px; width:100%;
            background:#3fb950; color:#fff; border:none; border-radius:4px;
            padding:11px; font-size:14px; font-weight:700; cursor:pointer;
            font-family:inherit; transition:.2s;
        }
        .btn-submit:hover { background:#2ea043; box-shadow:0 0 14px rgba(63,185,80,.3); }
        .btn-submit:disabled { background:#21262d; color:#6e7681; cursor:not-allowed; box-shadow:none; }

        .pending-state { text-align:center; padding:40px 20px; }
        .pending-state .big-icon { font-size:44px; margin-bottom:18px; }
        .pending-state h3 { color:#d29922; margin:0 0 10px; font-size:16px; }
        .pending-state p  { color:#8b949e; font-size:13px; line-height:1.8; margin:0; }
        .ticket-meta { display:inline-block; margin-top:14px; font-family:"Courier New",monospace; font-size:12px; color:#6e7681; background:#0d1117; border:1px solid #21262d; border-radius:4px; padding:6px 14px; }

        .chat-messages {
            height:380px; overflow-y:auto; padding:16px;
            display:flex; flex-direction:column; gap:10px;
            background:#0d1117; border-radius:4px; margin-bottom:14px;
        }
        .chat-messages::-webkit-scrollbar { width:4px; }
        .chat-messages::-webkit-scrollbar-thumb { background:#30363d; border-radius:2px; }
        .msg-row { display:flex; align-items:flex-end; gap:8px; }
        .msg-row.mine { flex-direction:row-reverse; }
        .msg-bubble {
            max-width:70%; padding:9px 13px; border-radius:6px;
            font-size:13px; line-height:1.6; word-break:break-word;
        }
        .msg-row.mine  .msg-bubble { background:rgba(63,185,80,.15); border:1px solid rgba(63,185,80,.25); color:#e6edf3; border-radius:6px 6px 0 6px; }
        .msg-row.theirs .msg-bubble { background:#161b22; border:1px solid #30363d; color:#c9d1d9; border-radius:6px 6px 6px 0; }
        .msg-label { font-size:10px; color:#6e7681; margin-bottom:4px; font-family:"Courier New",monospace; }
        .msg-row.mine .msg-label { text-align:right; }
        .msg-col { display:flex; flex-direction:column; }
        .msg-time { font-size:10px; color:#6e7681; margin-top:3px; font-family:"Courier New",monospace; }
        .msg-row.mine .msg-time { text-align:right; }

        .chat-input-row { display:flex; gap:8px; }
        .chat-input {
            flex:1; background:#0d1117; border:1px solid #30363d; border-radius:4px;
            color:#e6edf3; font-size:13px; font-family:inherit;
            padding:9px 12px; outline:none; transition:.2s;
        }
        .chat-input:focus { border-color:#3fb950; }
        .btn-send {
            background:#3fb950; color:#fff; border:none; border-radius:4px;
            padding:0 20px; font-size:13px; font-weight:700; cursor:pointer;
            font-family:inherit; transition:.2s; white-space:nowrap;
        }
        .btn-send:hover { background:#2ea043; }
        .btn-send:disabled { background:#21262d; color:#6e7681; cursor:not-allowed; }

        .resolved-state { text-align:center; padding:40px 20px; }
        .resolved-state .big-icon { font-size:44px; margin-bottom:18px; }
        .resolved-state h3 { color:#3fb950; margin:0 0 10px; font-size:16px; }
        .resolved-state p  { color:#8b949e; font-size:13px; line-height:1.8; margin:0 0 18px; }
        .btn-new { display:inline-block; background:transparent; border:1px solid rgba(63,185,80,.4); color:#3fb950; border-radius:4px; padding:8px 22px; font-size:13px; cursor:pointer; font-family:inherit; transition:.2s; }
        .btn-new:hover { background:rgba(63,185,80,.1); }

        .cs-status-bar { display:flex; align-items:center; gap:8px; padding:8px 14px; background:rgba(63,185,80,.06); border-bottom:1px solid #21262d; font-size:12px; color:#3fb950; font-family:"Courier New",monospace; }
        .cs-dot { width:7px; height:7px; border-radius:50%; background:#3fb950; box-shadow:0 0 6px #3fb950; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

        .err-msg { background:rgba(248,81,73,.1); border:1px solid rgba(248,81,73,.3); border-radius:4px; padding:9px 12px; color:#f85149; font-size:13px; margin-bottom:14px; }

        @media(max-width:560px){
            .type-grid { grid-template-columns:1fr 1fr; gap:8px; }
            .cs-wrap { margin:14px auto; }
            .chat-messages { height:280px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="cs-wrap">

<?php if (!$ticket): ?>
    <div class="cs-card" id="createCard">
        <div class="cs-head"><h2>联系客服</h2></div>
        <div class="cs-body">
            <p style="color:#8b949e;font-size:13px;margin:0 0 18px;">请选择您遇到的问题类型，我们会尽快安排客服为您处理。</p>
            <div id="errBox" style="display:none;" class="err-msg"></div>
            <div class="type-grid">
                <?php
                $type_icons = ['account'=>'👤','content'=>'🚩','technical'=>'⚙️','punishment'=>'⚖️','suggestion'=>'💡','other'=>'💬'];
                foreach ($issue_types as $k => $v):
                ?>
                <button class="type-btn" data-type="<?= $k ?>" onclick="selectType(this)">
                    <span class="type-icon"><?= $type_icons[$k] ?></span><?= $v ?>
                </button>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="selectedType" value="">
            <textarea class="cs-textarea" id="desc" placeholder="请简单描述您遇到的问题（选填）"></textarea>
            <button class="btn-submit" id="submitBtn" onclick="submitTicket()" disabled>提交工单</button>
        </div>
    </div>

<?php elseif ($ticket['status'] === 'pending'): ?>
    <div class="cs-card" id="pendingCard">
        <div class="cs-head"><h2>工单等待中</h2></div>
        <div class="cs-body">
            <div class="pending-state">
                <div class="big-icon">⏳</div>
                <h3>&gt; WAITING_FOR_CS_</h3>
                <p>您的工单已提交，正在等待客服接入。<br>客服接入后将在此页面与您对话。<br>若 12 小时内无回应，将自动补偿 <b style="color:#3fb950;">100 积分</b>。</p>
                <span class="ticket-meta">
                    工单 #<?= $ticket['id'] ?> &nbsp;·&nbsp;
                    <?= htmlspecialchars($issue_types[$ticket['type']] ?? $ticket['type']) ?> &nbsp;·&nbsp;
                    <?= date('m-d H:i', strtotime($ticket['created_at'])) ?>
                </span>
            </div>
        </div>
    </div>

<?php elseif ($ticket['status'] === 'active'): ?>
    <?php $tid = (int)$ticket['id']; ?>
    <div class="cs-card">
        <div class="cs-status-bar">
            <span class="cs-dot"></span>
            客服已接入 &nbsp;·&nbsp; 工单 #<?= $tid ?> &nbsp;·&nbsp; <?= htmlspecialchars($issue_types[$ticket['type']] ?? $ticket['type']) ?>
        </div>
        <div class="cs-head"><h2>客服对话</h2></div>
        <div class="cs-body" style="padding-top:14px;">
            <div class="chat-messages" id="chatBox">
                <?php foreach ($messages as $m): ?>
                <?php $is_mine = !(bool)$m['is_cs']; ?>
                <div class="msg-row <?= $is_mine ? 'mine' : 'theirs' ?>">
                    <div class="msg-col">
                        <div class="msg-label"><?= $m['is_cs'] ? '客服' : '我' ?></div>
                        <div class="msg-bubble"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
                        <div class="msg-time"><?= date('H:i', strtotime($m['created_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="resolvedNotice" style="display:none;" class="resolved-state" style="padding:20px 0 0;">
                <div class="big-icon">✅</div>
                <h3>&gt; SESSION_CLOSED_</h3>
                <p>客服已结束本次会话，问题已标记为解决。</p>
                <button class="btn-new" onclick="location.reload()">提交新工单</button>
            </div>
            <div id="inputArea" class="chat-input-row">
                <input class="chat-input" id="msgInput" type="text" placeholder="输入消息…" onkeydown="if(event.key==='Enter')sendMsg()">
                <button class="btn-send" id="sendBtn" onclick="sendMsg()">发送</button>
            </div>
        </div>
    </div>

<?php endif; ?>

</div>

<script>
var ticketId  = <?= $ticket ? (int)$ticket['id'] : 0 ?>;
var lastId    = <?= $last_msg_id ?>;
var myUid     = <?= $uid ?>;
var isActive  = <?= ($ticket && $ticket['status'] === 'active') ? 'true' : 'false' ?>;
var isPending = <?= ($ticket && $ticket['status'] === 'pending') ? 'true' : 'false' ?>;

function selectType(el) {
    document.querySelectorAll('.type-btn').forEach(function(b){ b.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('selectedType').value = el.dataset.type;
    document.getElementById('submitBtn').disabled = false;
}

function submitTicket() {
    var type = document.getElementById('selectedType').value;
    var desc = document.getElementById('desc').value;
    if (!type) return;
    var btn = document.getElementById('submitBtn');
    btn.disabled = true; btn.textContent = '提交中…';
    var fd = new FormData();
    fd.append('action', 'create_ticket');
    fd.append('type', type);
    fd.append('description', desc);
    fetch('../actions/cs_action.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.status === 'ok') {
            location.reload();
        } else {
            var e = document.getElementById('errBox');
            e.textContent = d.msg || '提交失败，请重试';
            e.style.display = 'block';
            btn.disabled = false; btn.textContent = '提交工单';
        }
    });
}

function sendMsg() {
    var input = document.getElementById('msgInput');
    var content = input.value.trim();
    if (!content || !ticketId) return;
    var btn = document.getElementById('sendBtn');
    btn.disabled = true;
    var fd = new FormData();
    fd.append('action', 'send_message');
    fd.append('ticket_id', ticketId);
    fd.append('content', content);
    fetch('../actions/cs_action.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.status === 'ok') {
            input.value = '';
            pollMessages();
        }
        btn.disabled = false;
    });
}

function appendMessage(m) {
    var box = document.getElementById('chatBox');
    if (!box) return;
    var isMine = m.is_cs == 0;
    var t = new Date(m.created_at.replace(' ','T'));
    var hm = (t.getHours()<10?'0':'')+t.getHours()+':'+(t.getMinutes()<10?'0':'')+t.getMinutes();
    var div = document.createElement('div');
    div.className = 'msg-row ' + (isMine ? 'mine' : 'theirs');
    div.innerHTML = '<div class="msg-col"><div class="msg-label">' + (m.is_cs ? '客服' : '我') + '</div>'
        + '<div class="msg-bubble">' + escHtml(m.content).replace(/\n/g,'<br>') + '</div>'
        + '<div class="msg-time">' + hm + '</div></div>';
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
    lastId = Math.max(lastId, parseInt(m.id));
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function pollMessages() {
    if (!ticketId) return;
    fetch('../actions/cs_action.php?action=poll_messages&ticket_id='+ticketId+'&last_id='+lastId)
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.status !== 'ok') return;
        d.messages.forEach(appendMessage);
        if (d.ticket_status === 'resolved') showResolved();
    });
}

function showResolved() {
    var ia = document.getElementById('inputArea');
    var rn = document.getElementById('resolvedNotice');
    if (ia) ia.style.display = 'none';
    if (rn) rn.style.display = 'block';
}

function pollPending() {
    fetch('../actions/cs_action.php?action=get_my_ticket')
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.status === 'ok' && d.ticket && d.ticket.status === 'active') {
            location.reload();
        }
    });
}

if (isActive) {
    var box = document.getElementById('chatBox');
    if (box) box.scrollTop = box.scrollHeight;
    setInterval(pollMessages, 3000);
}
if (isPending) {
    setInterval(pollPending, 5000);
}
</script>
</body>
</html>
