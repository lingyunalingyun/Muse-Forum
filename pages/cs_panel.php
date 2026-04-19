<?php
/**
 * cs_panel.php — 客服工作台（客服/管理端）
 *
 * 功能：管理多用户工单，查看并回复用户发起的客服会话
 * 读写表：读写 messages（或专属客服表）
 * 权限：is_cs=1 或 admin / owner
 */
session_start();
require_once __DIR__ . '/../config.php';

$cs_role     = $_SESSION['role'] ?? 'user';
$is_owner    = ($cs_role === 'owner');
$has_cs_access = in_array($cs_role, ['admin', 'owner']) || !empty($_SESSION['is_cs']);

if (!isset($_SESSION['user_id']) || !$has_cs_access) {
    header("Location: login.php");
    exit;
}

$cs_id = (int)$_SESSION['user_id'];

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

$active_tid = isset($_GET['tid']) ? (int)$_GET['tid'] : 0;
$active_ticket = null;
$active_messages = [];
$last_msg_id = 0;

if ($active_tid) {
    $tr = $conn->query("SELECT * FROM cs_tickets WHERE id=$active_tid AND cs_id=$cs_id AND status='active'");
    if ($tr) $active_ticket = $tr->fetch_assoc();
    if ($active_ticket) {
        $mr = $conn->query("SELECT * FROM cs_messages WHERE ticket_id=$active_tid ORDER BY id ASC");
        while ($m = $mr->fetch_assoc()) {
            $active_messages[] = $m;
            $last_msg_id = max($last_msg_id, (int)$m['id']);
        }
    }
}

$pending = [];
$pr = $conn->query("SELECT id, type, description, created_at FROM cs_tickets WHERE status='pending' ORDER BY id ASC");
while ($row = $pr->fetch_assoc()) $pending[] = $row;

$my_active = [];
$ar = $conn->query("SELECT id, type, description, created_at FROM cs_tickets WHERE cs_id=$cs_id AND status='active' ORDER BY id ASC");
while ($row = $ar->fetch_assoc()) $my_active[] = $row;

$cs_agents = [];
if ($is_owner) {
    $agr = $conn->query("SELECT id, username, mid, role FROM users WHERE is_cs=1 ORDER BY id ASC");
    while ($row = $agr->fetch_assoc()) $cs_agents[] = $row;
}

$issue_types = [
    'account'    => '账号问题',
    'content'    => '内容投诉',
    'technical'  => '技术故障',
    'punishment' => '处罚申诉',
    'suggestion' => '功能建议',
    'other'      => '其他问题',
];
$type_colors = [
    'account' => '#58a6ff','content' => '#f85149','technical' => '#d29922',
    'punishment' => '#a78bfa','suggestion' => '#3fb950','other' => '#8b949e',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>客服后台 - <?= SITE_NAME ?></title>
    <style>
        @keyframes fadeUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
        .panel-wrap { max-width:1100px; margin:28px auto; padding:0 16px 60px; display:grid; grid-template-columns:340px 1fr; gap:16px; align-items:start; }
        .panel-left { display:flex; flex-direction:column; gap:12px; }

        .cs-section { background:
        .cs-sec-head { padding:12px 16px; border-bottom:1px solid 
        .cs-sec-head h3 { margin:0; font-size:11px; font-weight:700; color:
        .cs-sec-head h3::before { content:'// '; opacity:.6; }
        .cs-count { font-size:12px; color:
        .cs-sec-body { padding:0; }

        .ticket-item {
            padding:12px 16px; border-bottom:1px solid 
            transition:.15s; display:flex; align-items:center; gap:10px;
        }
        .ticket-item:last-child { border-bottom:none; }
        .ticket-item:hover { background:rgba(255,255,255,.03); }
        .ticket-item.active-item { background:rgba(63,185,80,.05); border-left:2px solid 
        .ti-type { font-size:11px; font-weight:700; padding:2px 8px; border-radius:3px; letter-spacing:.3px; white-space:nowrap; }
        .ti-info { flex:1; min-width:0; }
        .ti-id   { font-size:11px; color:
        .ti-desc { font-size:12px; color:
        .ti-time { font-size:11px; color:
        .btn-join {
            font-size:12px; color:
            background:none; border-radius:3px; padding:4px 12px; cursor:pointer;
            font-family:inherit; transition:.2s; white-space:nowrap;
        }
        .btn-join:hover { background:rgba(63,185,80,.1); }
        .empty-list { padding:20px 16px; font-size:12px; color:
        .empty-list::before { content:'// '; }

        .cs-mgmt-input { display:flex; gap:8px; padding:12px 14px; border-bottom:1px solid 
        .mid-input {
            flex:1; background:
            color:
            padding:7px 10px; outline:none; transition:.2s; letter-spacing:1px;
        }
        .mid-input:focus { border-color:
        .btn-add-cs {
            background:
            padding:0 14px; font-size:12px; font-weight:700; cursor:pointer;
            font-family:inherit; transition:.2s; white-space:nowrap;
        }
        .btn-add-cs:hover { background:
        .agent-item { display:flex; align-items:center; gap:10px; padding:9px 14px; border-bottom:1px solid 
        .agent-item:last-child { border-bottom:none; }
        .agent-name { flex:1; font-size:13px; color:
        .agent-mid  { font-size:11px; color:
        .btn-remove-cs {
            font-size:11px; color:
            background:none; border-radius:3px; padding:3px 10px; cursor:pointer;
            font-family:inherit; transition:.2s;
        }
        .btn-remove-cs:hover { background:rgba(248,81,73,.1); }
        .cs-mgmt-msg { padding:8px 14px; font-size:12px; font-family:"Courier New",monospace; }

        .chat-panel { background:
        .chat-panel-head { padding:12px 16px; border-bottom:1px solid 
        .chat-panel-head h3 { margin:0; font-size:11px; font-weight:700; color:
        .chat-panel-head h3::before { content:'// '; opacity:.6; }
        .btn-resolve {
            font-size:12px; color:
            background:none; border-radius:3px; padding:5px 14px; cursor:pointer;
            font-family:inherit; transition:.2s; white-space:nowrap;
        }
        .btn-resolve:hover { background:rgba(248,81,73,.1); }

        .chat-messages {
            height:420px; overflow-y:auto; padding:14px;
            display:flex; flex-direction:column; gap:10px;
            background:
        }
        .chat-messages::-webkit-scrollbar { width:4px; }
        .chat-messages::-webkit-scrollbar-thumb { background:
        .msg-row { display:flex; align-items:flex-end; gap:8px; }
        .msg-row.mine { flex-direction:row-reverse; }
        .msg-col { display:flex; flex-direction:column; max-width:72%; }
        .msg-bubble { max-width:100%; padding:9px 13px; border-radius:6px; font-size:13px; line-height:1.6; word-break:break-word; }
        .msg-row.mine   .msg-bubble { background:rgba(167,139,250,.15); border:1px solid rgba(167,139,250,.3); color:
        .msg-row.theirs .msg-bubble { background:
        .msg-label { font-size:10px; color:
        .msg-row.mine .msg-label { text-align:right; }
        .msg-time { font-size:10px; color:
        .msg-row.mine .msg-time { text-align:right; }

        .chat-input-area { padding:12px 14px; border-top:1px solid 
        .chat-input {
            flex:1; background:
            color:
            padding:9px 12px; outline:none; transition:.2s;
        }
        .chat-input:focus { border-color:
        .btn-send {
            background:
            padding:0 18px; font-size:13px; font-weight:700; cursor:pointer;
            font-family:inherit; transition:.2s; white-space:nowrap;
        }
        .btn-send:hover { background:
        .btn-send:disabled { background:

        .privacy-notice { padding:10px 14px; background:rgba(167,139,250,.06); border-bottom:1px solid 
        .privacy-notice::before { content:'🔒 '; }

        .no-chat { display:flex; align-items:center; justify-content:center; height:300px; flex-direction:column; gap:12px; color:
        .no-chat::before { content:'// '; }

        .resolved-overlay { padding:60px 20px; text-align:center; }
        .resolved-overlay h3 { color:
        .resolved-overlay p { color:

        @media(max-width:900px){
            .panel-wrap { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="panel-wrap">

    <div class="panel-left">

        <?php if ($is_owner): ?>
        <!-- CS team management (owner only) -->
        <div class="cs-section">
            <div class="cs-sec-head">
                <h3>客服团队管理</h3>
                <span class="cs-count" id="agentCount"><?= count($cs_agents) ?> 人</span>
            </div>
            <div class="cs-mgmt-input">
                <input class="mid-input" id="addMidInput" type="text" maxlength="8" placeholder="输入用户 MID（8位）">
                <button class="btn-add-cs" onclick="addCsAgent()">添加客服</button>
            </div>
            <div id="agentMsg" class="cs-mgmt-msg" style="display:none;"></div>
            <div id="agentList">
                <?php if (empty($cs_agents)): ?>
                    <div class="empty-list" id="agentEmpty">暂无客服人员</div>
                <?php else: foreach ($cs_agents as $ag): ?>
                    <div class="agent-item" id="ag-<?= $ag['id'] ?>">
                        <div>
                            <div class="agent-name"><?= htmlspecialchars($ag['username']) ?></div>
                            <div class="agent-mid">MID <?= htmlspecialchars($ag['mid'] ?? '—') ?></div>
                        </div>
                        <button class="btn-remove-cs" onclick="removeCsAgent(<?= $ag['id'] ?>, this)">移除</button>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pending tickets -->
        <div class="cs-section">
            <div class="cs-sec-head">
                <h3>待接入工单</h3>
                <span class="cs-count" id="pendingCount"><?= count($pending) ?> 个</span>
            </div>
            <div class="cs-sec-body" id="pendingList">
                <?php if (empty($pending)): ?>
                    <div class="empty-list">暂无待处理工单</div>
                <?php else: foreach ($pending as $t): ?>
                    <?php
                        $tc = $type_colors[$t['type']] ?? '#8b949e';
                        $tl = $issue_types[$t['type']] ?? $t['type'];
                    ?>
                    <div class="ticket-item" id="pt-<?= $t['id'] ?>">
                        <span class="ti-type" style="color:<?= $tc ?>;border:1px solid <?= $tc ?>30;background:<?= $tc ?>15;"><?= $tl ?></span>
                        <div class="ti-info">
                            <div class="ti-id">
                            <?php if ($t['description']): ?>
                            <div class="ti-desc"><?= htmlspecialchars(mb_substr($t['description'], 0, 40)) ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
                            <span class="ti-time"><?= date('H:i', strtotime($t['created_at'])) ?></span>
                            <button class="btn-join" onclick="joinTicket(<?= $t['id'] ?>, this)">接入</button>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- My active tickets -->
        <div class="cs-section">
            <div class="cs-sec-head">
                <h3>我的进行中</h3>
                <span class="cs-count"><?= count($my_active) ?> 个</span>
            </div>
            <div class="cs-sec-body" id="myActiveList">
                <?php if (empty($my_active)): ?>
                    <div class="empty-list">暂无进行中会话</div>
                <?php else: foreach ($my_active as $t): ?>
                    <?php
                        $tc = $type_colors[$t['type']] ?? '#8b949e';
                        $tl = $issue_types[$t['type']] ?? $t['type'];
                        $is_cur = ($active_tid === (int)$t['id']);
                    ?>
                    <div class="ticket-item <?= $is_cur ? 'active-item' : '' ?>" onclick="openChat(<?= $t['id'] ?>)">
                        <span class="ti-type" style="color:<?= $tc ?>;border:1px solid <?= $tc ?>30;background:<?= $tc ?>15;"><?= $tl ?></span>
                        <div class="ti-info">
                            <div class="ti-id">
                            <?php if ($t['description']): ?>
                            <div class="ti-desc"><?= htmlspecialchars(mb_substr($t['description'], 0, 40)) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="ti-time"><?= date('H:i', strtotime($t['created_at'])) ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Chat panel -->
    <div class="chat-panel">
        <?php if ($active_ticket): ?>
            <div class="cs-sec-head chat-panel-head">
                <h3>
                    工单 
                    <?php $tc = $type_colors[$active_ticket['type']] ?? '#8b949e'; $tl = $issue_types[$active_ticket['type']] ?? $active_ticket['type']; ?>
                    <span style="color:<?= $tc ?>;"><?= $tl ?></span>
                </h3>
                <button class="btn-resolve" onclick="resolveTicket(<?= $active_ticket['id'] ?>)">✓ 标记解决</button>
            </div>
            <div class="privacy-notice">隐私保护模式：用户身份已匿名，请勿索取个人信息</div>
            <div class="chat-messages" id="chatBox">
                <?php foreach ($active_messages as $m): ?>
                <?php $is_mine = (bool)$m['is_cs']; ?>
                <div class="msg-row <?= $is_mine ? 'mine' : 'theirs' ?>">
                    <div class="msg-col">
                        <div class="msg-label"><?= $m['is_cs'] ? '客服（我）' : '用户 #' . ((int)$active_ticket['id'] * 7 % 9973) ?></div>
                        <div class="msg-bubble"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
                        <div class="msg-time"><?= date('H:i', strtotime($m['created_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="chat-input-area" id="inputArea">
                <input class="chat-input" id="msgInput" type="text" placeholder="回复用户…" onkeydown="if(event.key==='Enter')sendMsg()">
                <button class="btn-send" id="sendBtn" onclick="sendMsg()">发送</button>
            </div>
        <?php else: ?>
            <div class="no-chat">选择一个进行中的工单开始对话</div>
        <?php endif; ?>
    </div>

</div>

<script>
var ticketId  = <?= $active_tid ?>;
var lastId    = <?= $last_msg_id ?>;
var anonId    = <?= $active_ticket ? ((int)$active_ticket['id'] * 7 % 9973) : 0 ?>;
var isOwner   = <?= $is_owner ? 'true' : 'false' ?>;

function addCsAgent() {
    var mid = document.getElementById('addMidInput').value.trim();
    if (!/^\d{8}$/.test(mid)) { showAgentMsg('MID 必须是 8 位数字', '#d29922'); return; }
    var fd = new FormData();
    fd.append('action', 'add_cs_agent');
    fd.append('mid', mid);
    fetch('../actions/cs_action.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.status === 'ok') {
            document.getElementById('addMidInput').value = '';
            var empty = document.getElementById('agentEmpty');
            if (empty) { empty.remove(); }
            var list = document.getElementById('agentList');
            var div = document.createElement('div');
            div.className = 'agent-item'; div.id = 'ag-' + d.user_id;
            div.innerHTML = '<div><div class="agent-name">' + escHtml(d.username) + '</div>'
                + '<div class="agent-mid">MID ' + escHtml(mid) + '</div></div>'
                + '<button class="btn-remove-cs" onclick="removeCsAgent(' + d.user_id + ', this)">移除</button>';
            list.appendChild(div);
            var cnt = document.getElementById('agentCount');
            if (cnt) cnt.textContent = (parseInt(cnt.textContent) + 1) + ' 人';
            showAgentMsg('已添加客服：' + escHtml(d.username), '#3fb950');
        } else {
            showAgentMsg(d.msg || '添加失败', '#f85149');
        }
    });
}

function removeCsAgent(uid, btn) {
    if (!confirm('确认移除该客服权限？')) return;
    btn.disabled = true;
    var fd = new FormData();
    fd.append('action', 'remove_cs_agent');
    fd.append('target_id', uid);
    fetch('../actions/cs_action.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.status === 'ok') {
            var row = document.getElementById('ag-' + uid);
            if (row) row.remove();
            var cnt = document.getElementById('agentCount');
            if (cnt) { var n = parseInt(cnt.textContent) - 1; cnt.textContent = n + ' 人'; }
            var list = document.getElementById('agentList');
            if (list && list.children.length === 0) {
                list.innerHTML = '<div class="empty-list" id="agentEmpty">暂无客服人员</div>';
            }
        } else {
            btn.disabled = false;
        }
    });
}

function showAgentMsg(msg, color) {
    var el = document.getElementById('agentMsg');
    if (!el) return;
    el.style.color = color; el.textContent = msg; el.style.display = 'block';
    setTimeout(function(){ el.style.display = 'none'; }, 3000);
}

function joinTicket(tid, btn) {
    btn.disabled = true; btn.textContent = '接入中…';
    var fd = new FormData();
    fd.append('action', 'join_ticket');
    fd.append('ticket_id', tid);
    fetch('../actions/cs_action.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.status === 'ok') {
            location.href = 'cs_panel.php?tid=' + tid;
        } else {
            btn.disabled = false; btn.textContent = '接入';
            alert(d.msg || '接入失败');
        }
    });
}

function openChat(tid) {
    location.href = 'cs_panel.php?tid=' + tid;
}

function resolveTicket(tid) {
    if (!confirm('确认将此工单标记为已解决？对话将结束，不再触发积分补偿。')) return;
    var fd = new FormData();
    fd.append('action', 'resolve_ticket');
    fd.append('ticket_id', tid);
    fetch('../actions/cs_action.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.status === 'ok') {
            var box = document.getElementById('chatBox');
            var ia  = document.getElementById('inputArea');
            if (ia) ia.style.display = 'none';
            if (box) {
                var div = document.createElement('div');
                div.className = 'resolved-overlay';
                div.innerHTML = '<h3>&gt; SESSION_RESOLVED_</h3><p>会话已结束，已通知用户。</p>';
                box.appendChild(div);
                box.scrollTop = box.scrollHeight;
            }
        }
    });
}

function sendMsg() {
    if (!ticketId) return;
    var input = document.getElementById('msgInput');
    var content = input.value.trim();
    if (!content) return;
    var btn = document.getElementById('sendBtn');
    btn.disabled = true;
    var fd = new FormData();
    fd.append('action', 'send_message');
    fd.append('ticket_id', ticketId);
    fd.append('content', content);
    fetch('../actions/cs_action.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.status === 'ok') { input.value = ''; pollMessages(); }
        btn.disabled = false;
    });
}

function appendMessage(m) {
    var box = document.getElementById('chatBox');
    if (!box) return;
    var isMine = m.is_cs == 1;
    var t = new Date(m.created_at.replace(' ','T'));
    var hm = (t.getHours()<10?'0':'')+t.getHours()+':'+(t.getMinutes()<10?'0':'')+t.getMinutes();
    var label = isMine ? '客服（我）' : '用户 #' + anonId;
    var div = document.createElement('div');
    div.className = 'msg-row ' + (isMine ? 'mine' : 'theirs');
    div.innerHTML = '<div class="msg-col"><div class="msg-label">' + label + '</div>'
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
        if (d.ticket_status === 'resolved') {
            var ia = document.getElementById('inputArea');
            if (ia) ia.style.display = 'none';
        }
    });
}

function pollPending() {
    fetch('../actions/cs_action.php?action=list_pending')
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.status !== 'ok') return;
        var el = document.getElementById('pendingCount');
        if (el) el.textContent = d.tickets.length + ' 个';
    });
}

if (ticketId) {
    var box = document.getElementById('chatBox');
    if (box) box.scrollTop = box.scrollHeight;
    setInterval(pollMessages, 3000);
}
setInterval(pollPending, 8000);
</script>
</body>
</html>
