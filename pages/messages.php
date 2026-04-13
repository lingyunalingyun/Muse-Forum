<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$my_id     = intval($_SESSION['user_id']);
$other_id  = intval($_GET['user_id'] ?? 0);

// ── 收件箱模式：获取所有对话对象 ──
$inbox = [];
$inbox_res = $conn->query("
    SELECT u.id, u.username, u.avatar,
           last_msg.content  AS last_content,
           last_msg.created_at AS last_time,
           (SELECT COUNT(*) FROM messages
            WHERE to_user_id = $my_id AND from_user_id = u.id AND is_read = 0) AS unread
    FROM (
        SELECT DISTINCT
            IF(from_user_id = $my_id, to_user_id, from_user_id) AS other_id
        FROM messages
        WHERE from_user_id = $my_id OR to_user_id = $my_id
    ) contacts
    JOIN users u ON u.id = contacts.other_id
    JOIN messages last_msg ON last_msg.id = (
        SELECT id FROM messages
        WHERE (from_user_id = $my_id AND to_user_id = u.id)
           OR (from_user_id = u.id AND to_user_id = $my_id)
        ORDER BY created_at DESC LIMIT 1
    )
    ORDER BY last_msg.created_at DESC
");
if ($inbox_res) {
    while ($r = $inbox_res->fetch_assoc()) $inbox[] = $r;
}

// ── 会话模式：获取与某人的消息 ──
$other_user = null;
$messages   = [];
if ($other_id > 0) {
    $ou_res = $conn->query("SELECT id, username, avatar FROM users WHERE id = $other_id");
    $other_user = $ou_res ? $ou_res->fetch_assoc() : null;

    if ($other_user) {
        // 标记对方消息已读
        $conn->query("UPDATE messages SET is_read = 1
                      WHERE to_user_id = $my_id AND from_user_id = $other_id AND is_read = 0");
        // 同时标记该对话的通知已读
        $conn->query("UPDATE notifications SET is_read = 1
                      WHERE user_id = $my_id AND from_user_id = $other_id AND type = 'message'");

        $msg_res = $conn->query("
            SELECT m.*, u.username, u.avatar
            FROM messages m
            JOIN users u ON u.id = m.from_user_id
            WHERE (m.from_user_id = $my_id AND m.to_user_id = $other_id)
               OR (m.from_user_id = $other_id AND m.to_user_id = $my_id)
            ORDER BY m.created_at ASC
            LIMIT 200
        ");
        if ($msg_res) {
            while ($r = $msg_res->fetch_assoc()) $messages[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= $other_user ? '与 ' . htmlspecialchars($other_user['username']) . ' 的对话' : '私信' ?></title>
    <style>
        body { background: #f4f7f6; font-family: "Microsoft YaHei", sans-serif; margin: 0; padding-bottom: 0; }
        .msg-layout { max-width: 1000px; margin: 20px auto; padding: 0 15px; display: flex; gap: 15px; height: calc(100vh - 100px); }

        /* 左侧收件箱 */
        .inbox { width: 280px; background: white; border-radius: 12px; overflow-y: auto; box-shadow: 0 2px 10px rgba(0,0,0,0.05); flex-shrink: 0; }
        .inbox-title { padding: 16px 20px; font-weight: bold; font-size: 15px; border-bottom: 1px solid #f0f0f0; }
        .inbox-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; cursor: pointer; transition: background 0.2s; border-bottom: 1px solid #f9f9f9; text-decoration: none; color: #333; }
        .inbox-item:hover, .inbox-item.active { background: #f0fdf4; }
        .inbox-avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
        .inbox-info { flex: 1; min-width: 0; }
        .inbox-name { font-weight: bold; font-size: 14px; display: flex; justify-content: space-between; }
        .inbox-preview { font-size: 12px; color: #999; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 3px; }
        .unread-dot { background: #e74c3c; color: white; border-radius: 10px; padding: 1px 6px; font-size: 11px; }
        .inbox-empty { padding: 30px 20px; text-align: center; color: #bbb; font-size: 13px; }

        /* 右侧会话区 */
        .chat-area { flex: 1; display: flex; flex-direction: column; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .chat-header { padding: 16px 20px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 12px; }
        .chat-header img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        .chat-header strong { font-size: 15px; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 14px; }
        .msg-row { display: flex; align-items: flex-end; gap: 10px; }
        .msg-row.sent { flex-direction: row-reverse; }
        .msg-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
        .msg-bubble { max-width: 65%; padding: 10px 14px; border-radius: 18px; font-size: 14px; line-height: 1.5; word-break: break-word; }
        .msg-row.received .msg-bubble { background: #f1f3f4; color: #333; border-bottom-left-radius: 4px; }
        .msg-row.sent .msg-bubble { background: #28a745; color: white; border-bottom-right-radius: 4px; }
        .msg-time { font-size: 11px; color: #bbb; margin-top: 4px; text-align: right; }
        .msg-row.received .msg-time { text-align: left; }
        .chat-input { border-top: 1px solid #f0f0f0; padding: 14px 16px; display: flex; gap: 10px; }
        .chat-input textarea { flex: 1; border: 1px solid #ddd; border-radius: 8px; padding: 10px 14px; font-size: 14px; font-family: inherit; resize: none; outline: none; height: 44px; line-height: 1.4; }
        .chat-input textarea:focus { border-color: #28a745; }
        .send-btn { background: #28a745; color: white; border: none; border-radius: 8px; padding: 0 20px; cursor: pointer; font-size: 14px; transition: 0.2s; }
        .send-btn:hover { background: #218838; }
        .no-chat { flex: 1; display: flex; align-items: center; justify-content: center; color: #bbb; flex-direction: column; gap: 10px; font-size: 14px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="msg-layout">

    <!-- 左：收件箱 -->
    <div class="inbox">
        <div class="inbox-title">💬 私信</div>
        <?php if (empty($inbox)): ?>
            <div class="inbox-empty">暂无对话</div>
        <?php else: ?>
            <?php foreach ($inbox as $item): ?>
                <a href="messages.php?user_id=<?= $item['id'] ?>"
                   class="inbox-item <?= $item['id'] == $other_id ? 'active' : '' ?>">
                    <img src="../uploads/avatars/<?= htmlspecialchars($item['avatar'] ?: 'default.png') ?>"
                         class="inbox-avatar"
                         onerror="this.onerror=null;this.src='../uploads/avatars/default.png'">
                    <div class="inbox-info">
                        <div class="inbox-name">
                            <span><?= htmlspecialchars($item['username']) ?></span>
                            <?php if ($item['unread'] > 0): ?>
                                <span class="unread-dot"><?= $item['unread'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="inbox-preview"><?= htmlspecialchars(mb_substr($item['last_content'], 0, 25)) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 右：会话 -->
    <div class="chat-area">
        <?php if ($other_user): ?>
            <div class="chat-header">
                <a href="profile.php?id=<?= $other_user['id'] ?>">
                    <img src="../uploads/avatars/<?= htmlspecialchars($other_user['avatar'] ?: 'default.png') ?>"
                         onerror="this.onerror=null;this.src='../uploads/avatars/default.png'">
                </a>
                <strong><?= htmlspecialchars($other_user['username']) ?></strong>
            </div>

            <div class="chat-messages" id="msg-list">
                <?php foreach ($messages as $m):
                    $is_sent = ($m['from_user_id'] == $my_id);
                    $ts = strtotime($m['created_at']);
                    $time_str = (date('Y-m-d', $ts) === date('Y-m-d')) ? date('H:i', $ts) : date('m-d H:i', $ts);
                ?>
                <div class="msg-row <?= $is_sent ? 'sent' : 'received' ?>" id="msg-<?= $m['id'] ?>">
                    <?php if (!$is_sent): ?>
                        <img src="../uploads/avatars/<?= htmlspecialchars($m['avatar'] ?: 'default.png') ?>"
                             class="msg-avatar" onerror="this.onerror=null;this.src='../uploads/avatars/default.png'">
                    <?php endif; ?>
                    <div>
                        <div class="msg-bubble"><?= htmlspecialchars($m['content']) ?></div>
                        <div class="msg-time"><?= $time_str ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="chat-input">
                <textarea id="msg-input" placeholder="输入消息，回车发送..."
                          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg();}"></textarea>
                <button class="send-btn" onclick="sendMsg()">发送</button>
            </div>

        <?php else: ?>
            <div class="no-chat">
                <div style="font-size:48px;">💬</div>
                <span>选择一个对话开始聊天</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const toId = <?= $other_id ?>;
const myAvatar = '<?= htmlspecialchars($_SESSION['avatar'] ?? 'default.png') ?>';

function sendMsg() {
    const input = document.getElementById('msg-input');
    const content = input.value.trim();
    if (!content || !toId) return;

    const fd = new FormData();
    fd.append('to_id', toId);
    fd.append('content', content);
    input.value = '';

    fetch('../actions/message_send.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success' && data.message) {
            appendMsg(data.message, true);
        }
    });
}

function appendMsg(m, isSent) {
    const list = document.getElementById('msg-list');
    const now = new Date();
    const timeStr = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');

    const div = document.createElement('div');
    div.className = 'msg-row ' + (isSent ? 'sent' : 'received');
    div.innerHTML = `
        <div>
            <div class="msg-bubble">${escHtml(m.content)}</div>
            <div class="msg-time">${timeStr}</div>
        </div>`;
    list.appendChild(div);
    list.scrollTop = list.scrollHeight;
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// 进入会话自动滚到底
window.onload = () => {
    const list = document.getElementById('msg-list');
    if (list) list.scrollTop = list.scrollHeight;
};
</script>
</body>
</html>
<?php $conn->close(); ?>
