<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$my_id    = intval($_SESSION['user_id']);
$tab      = $_GET['tab'] ?? 'all';       // all | message | interact
$other_id = intval($_GET['user_id'] ?? 0);

// ── 私信 Tab：加载收件箱和会话数据 ──
$inbox      = [];
$messages   = [];
$other_user = null;

if ($tab === 'message') {
    // 收件箱
    $inbox_res = $conn->query("
        SELECT u.id, u.username, u.avatar,
               last_msg.content AS last_content,
               (SELECT COUNT(*) FROM messages
                WHERE to_user_id = $my_id AND from_user_id = u.id AND is_read = 0) AS unread
        FROM (
            SELECT DISTINCT IF(from_user_id=$my_id, to_user_id, from_user_id) AS other_id
            FROM messages WHERE from_user_id=$my_id OR to_user_id=$my_id
        ) contacts
        JOIN users u ON u.id = contacts.other_id
        JOIN messages last_msg ON last_msg.id = (
            SELECT id FROM messages
            WHERE (from_user_id=$my_id AND to_user_id=u.id)
               OR (from_user_id=u.id AND to_user_id=$my_id)
            ORDER BY created_at DESC LIMIT 1
        )
        ORDER BY last_msg.created_at DESC
    ");
    if ($inbox_res) while ($r = $inbox_res->fetch_assoc()) $inbox[] = $r;

    // 当前会话
    if ($other_id > 0) {
        $ou_res = $conn->query("SELECT id, username, avatar FROM users WHERE id = $other_id");
        $other_user = $ou_res ? $ou_res->fetch_assoc() : null;
        if ($other_user) {
            $conn->query("UPDATE messages SET is_read=1 WHERE to_user_id=$my_id AND from_user_id=$other_id AND is_read=0");
            $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$my_id AND from_user_id=$other_id AND type='message'");
            $msg_res = $conn->query("
                SELECT m.*, u.username, u.avatar FROM messages m
                JOIN users u ON u.id = m.from_user_id
                WHERE (m.from_user_id=$my_id AND m.to_user_id=$other_id)
                   OR (m.from_user_id=$other_id AND m.to_user_id=$my_id)
                ORDER BY m.created_at ASC LIMIT 200
            ");
            if ($msg_res) while ($r = $msg_res->fetch_assoc()) $messages[] = $r;
        }
    }
} else {
    // ── 通知 Tab：标记已读并查询 ──
    if ($tab === 'interact') {
        $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$my_id AND type!='message' AND is_read=0");
    } else {
        $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$my_id AND is_read=0");
    }

    $where = "WHERE n.user_id=$my_id";
    if ($tab === 'interact') $where .= " AND n.type != 'message'";

    $sql = "SELECT n.*, u.username AS from_username, u.avatar AS from_avatar, p.title AS post_title,
                   (SELECT content FROM messages WHERE from_user_id=n.from_user_id AND to_user_id=n.user_id
                    ORDER BY created_at DESC LIMIT 1) AS msg_preview
            FROM notifications n
            LEFT JOIN users u ON n.from_user_id = u.id
            LEFT JOIN posts p ON n.post_id = p.id
            $where ORDER BY n.created_at DESC LIMIT 100";
    $res = $conn->query($sql);
    $notifications = [];
    if ($res) while ($row = $res->fetch_assoc()) $notifications[] = $row;
}

// Tab 未读数
$unread_msg      = (int)$conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$my_id AND type='message'  AND is_read=0")->fetch_assoc()['c'];
$unread_interact = (int)$conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$my_id AND type!='message' AND is_read=0")->fetch_assoc()['c'];

$type_config = [
    'comment'       => ['icon' => '💬', 'text' => '评论了你的帖子'],
    'reply'         => ['icon' => '↩️',  'text' => '回复了你的评论'],
    'mention'       => ['icon' => '@',   'text' => '在评论中 @ 了你'],
    'like_post'     => ['icon' => '❤️',  'text' => '赞了你的帖子'],
    'fav_post'      => ['icon' => '⭐',  'text' => '收藏了你的帖子'],
    'like_comment'  => ['icon' => '👍',  'text' => '赞了你的评论'],
    'follow'        => ['icon' => '👤',  'text' => '关注了你'],
    'message'       => ['icon' => '✉️',  'text' => '给你发了一条私信'],
    'post_review'   => ['icon' => '📋',  'text' => '提交了帖子，等待审核'],
    'post_approved' => ['icon' => '✅',  'text' => '的帖子已通过审核'],
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>消息中心</title>
    <style>
        /* ── 通知布局 ── */
        .notif-wrap { max-width: 700px; margin: 24px auto; padding: 0 15px; }
        .notif-wrap h2 { font-size: 13px; font-weight: 700; color: #3fb950; letter-spacing: 1.5px; text-transform: uppercase; font-family: "Courier New", monospace; margin: 0 0 18px; }
        .notif-wrap h2::before { content: '// '; opacity: .6; }

        /* ── 私信布局 ── */
        .msg-layout { max-width: 1000px; margin: 0 auto; padding: 0 15px; display: flex; gap: 12px; height: calc(100vh - 260px); }
        .inbox { width: 270px; background: #161b22; border: 1px solid #30363d; border-radius: 6px; overflow-y: auto; flex-shrink: 0; }
        @media(max-width:700px){
            .msg-layout { flex-direction: column; height: auto; padding: 0 10px; }
            .inbox { width: 100%; max-height: 220px; }
            .chat-area { height: 420px; }
            .notif-wrap { padding: 0 10px; }
        }
        .inbox-title { padding: 14px 18px; font-weight: 700; font-size: 12px; color: #3fb950; border-bottom: 1px solid #30363d; letter-spacing: 1px; text-transform: uppercase; font-family: "Courier New", monospace; }
        .inbox-title::before { content: '// '; opacity: .6; }
        .inbox-item { display: flex; align-items: center; gap: 10px; padding: 12px 16px; cursor: pointer; transition: background .2s; border-bottom: 1px solid #21262d; text-decoration: none; color: #c9d1d9; }
        .inbox-item:hover, .inbox-item.active { background: rgba(63,185,80,.06); }
        .inbox-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 1px solid #30363d; }
        .inbox-info { flex: 1; min-width: 0; }
        .inbox-name { font-weight: 600; font-size: 13px; display: flex; justify-content: space-between; color: #e6edf3; }
        .inbox-preview { font-size: 11px; color: #6e7681; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 3px; }
        .unread-dot { background: #f85149; color: white; border-radius: 8px; padding: 1px 6px; font-size: 10px; font-family: "Courier New", monospace; }
        .inbox-empty { padding: 30px 20px; text-align: center; color: #6e7681; font-size: 13px; }
        .chat-area { flex: 1; display: flex; flex-direction: column; background: #161b22; border: 1px solid #30363d; border-radius: 6px; overflow: hidden; }
        .chat-header { padding: 14px 18px; border-bottom: 1px solid #30363d; display: flex; align-items: center; gap: 12px; }
        .chat-header img { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 1px solid #30363d; }
        .chat-header strong { font-size: 14px; color: #e6edf3; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 18px; display: flex; flex-direction: column; gap: 12px; background: #0d1117; }
        .msg-row { display: flex; align-items: flex-end; gap: 8px; }
        .msg-row.sent { flex-direction: row-reverse; }
        .msg-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 1px solid #30363d; }
        .msg-bubble { max-width: 65%; padding: 9px 13px; border-radius: 4px; font-size: 13px; line-height: 1.5; word-break: break-word; }
        .msg-row.received .msg-bubble { background: #161b22; color: #c9d1d9; border: 1px solid #30363d; border-bottom-left-radius: 2px; }
        .msg-row.sent .msg-bubble { background: rgba(63,185,80,.2); color: #e6edf3; border: 1px solid rgba(63,185,80,.4); border-bottom-right-radius: 2px; }
        .msg-time { font-size: 10px; color: #484f58; margin-top: 3px; text-align: right; font-family: "Courier New", monospace; }
        .msg-row.received .msg-time { text-align: left; }
        .chat-input { border-top: 1px solid #30363d; padding: 12px 14px; display: flex; gap: 8px; background: #161b22; }
        .chat-input textarea { flex: 1; background: #0d1117; border: 1px solid #30363d; border-radius: 4px; padding: 9px 12px; font-size: 13px; font-family: inherit; color: #e6edf3; resize: none; outline: none; height: 40px; line-height: 1.4; }
        .chat-input textarea:focus { border-color: #3fb950; }
        .chat-input textarea::placeholder { color: #484f58; }
        .send-btn { background: #3fb950; color: white; border: 1px solid rgba(63,185,80,.4); border-radius: 4px; padding: 0 18px; cursor: pointer; font-size: 13px; font-weight: 700; font-family: inherit; transition: .2s; }
        .send-btn:hover { background: #2ea043; box-shadow: 0 0 12px rgba(63,185,80,.3); }
        .no-chat { flex: 1; display: flex; align-items: center; justify-content: center; color: #6e7681; flex-direction: column; gap: 10px; font-size: 13px; background: #0d1117; }

        /* ── 公共 Tab 栏 ── */
        .tabs { display: flex; gap: 6px; margin-bottom: 16px; }
        .tab { padding: 6px 16px; border-radius: 4px; font-size: 12px; cursor: pointer; font-weight: 600;
               border: 1px solid #30363d; background: #161b22; color: #8b949e; text-decoration: none;
               position: relative; transition: .2s; }
        .tab.active, .tab:hover { background: #3fb950; color: white; border-color: rgba(63,185,80,.4); }
        .tab-badge { position: absolute; top: -6px; right: -6px; background: #f85149; color: white;
                     border-radius: 8px; min-width: 16px; height: 16px; font-size: 10px;
                     display: flex; align-items: center; justify-content: center; padding: 0 4px; box-sizing: border-box; }

        /* ── 通知列表 ── */
        .notif-card { background: #161b22; border: 1px solid #30363d; border-radius: 6px; overflow: hidden; }
        .notif-item { display: flex; align-items: center; gap: 12px; padding: 14px 18px;
                      border-bottom: 1px solid #21262d; cursor: pointer; transition: background .2s; }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: rgba(63,185,80,.04); }
        .notif-item.unread { background: rgba(63,185,80,.06); }
        .notif-item.unread:hover { background: rgba(63,185,80,.09); }
        .notif-avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 1px solid #30363d; }
        .notif-icon { width: 42px; height: 42px; border-radius: 4px; background: #1c2128; border: 1px solid #30363d;
                      display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .notif-body { flex: 1; min-width: 0; }
        .notif-text { font-size: 13px; color: #c9d1d9; line-height: 1.5; }
        .notif-text strong { color: #3fb950; }
        .notif-preview { font-size: 12px; color: #6e7681; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .notif-time { font-size: 11px; color: #484f58; margin-top: 3px; font-family: "Courier New", monospace; }
        .post-ref { color: #58a6ff; }
        .mention-icon { background: rgba(88,166,255,.15); color: #58a6ff; font-weight: bold; border-radius: 3px; padding: 0 4px; font-size: 12px; border: 1px solid rgba(88,166,255,.3); }
        .empty-state { text-align: center; padding: 70px 20px; background: #161b22; border: 1px solid #30363d; border-radius: 6px; }
        .empty-state p { color: #6e7681; margin-top: 8px; font-size: 13px; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<?php
// Tab 栏（两种布局都需要）
$tab_html = '
<div class="tabs">
    <a href="notifications.php?tab=all" class="tab ' . ($tab==='all'?'active':'') . '">全部'
    . ($unread_msg+$unread_interact > 0 && $tab!=='all' ? '<span class="tab-badge">'.min($unread_msg+$unread_interact,99).'</span>' : '')
    . '</a>
    <a href="notifications.php?tab=message" class="tab ' . ($tab==='message'?'active':'') . '">💬 私信'
    . ($unread_msg > 0 ? '<span class="tab-badge">'.min($unread_msg,99).'</span>' : '')
    . '</a>
    <a href="notifications.php?tab=interact" class="tab ' . ($tab==='interact'?'active':'') . '">❤️ 互动'
    . ($unread_interact > 0 ? '<span class="tab-badge">'.min($unread_interact,99).'</span>' : '')
    . '</a>
</div>';
?>

<?php if ($tab === 'message'): ?>
<!-- ══ 私信界面 ══ -->
<div class="notif-wrap" style="max-width:1000px;">
    <h2 style="margin:0 0 18px;">🔔 消息中心</h2>
    <?= $tab_html ?>
</div>
<div class="msg-layout">
    <div class="inbox">
        <div class="inbox-title">💬 私信</div>
        <?php if (empty($inbox)): ?>
            <div class="inbox-empty">暂无对话</div>
        <?php else: ?>
            <?php foreach ($inbox as $item): ?>
                <a href="notifications.php?tab=message&user_id=<?= $item['id'] ?>"
                   class="inbox-item <?= $item['id']==$other_id ? 'active' : '' ?>">
                    <img src="../uploads/avatars/<?= htmlspecialchars($item['avatar'] ?: 'default.png') ?>"
                         class="inbox-avatar" onerror="this.onerror=null;this.src='../uploads/avatars/default.png'">
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
                    $time_str = (date('Y-m-d',$ts)===date('Y-m-d')) ? date('H:i',$ts) : date('m-d H:i',$ts);
                ?>
                <div class="msg-row <?= $is_sent ? 'sent' : 'received' ?>">
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
    .then(data => { if (data.status==='success' && data.message) appendMsg(data.message); });
}
function appendMsg(m) {
    const list = document.getElementById('msg-list');
    const now = new Date();
    const t = now.getHours().toString().padStart(2,'0')+':'+now.getMinutes().toString().padStart(2,'0');
    const div = document.createElement('div');
    div.className = 'msg-row sent';
    div.innerHTML = `<div><div class="msg-bubble">${escHtml(m.content)}</div><div class="msg-time">${t}</div></div>`;
    list.appendChild(div);
    list.scrollTop = list.scrollHeight;
}
function escHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
window.onload = () => { const l = document.getElementById('msg-list'); if(l) l.scrollTop=l.scrollHeight; };
</script>

<?php else: ?>
<!-- ══ 通知列表 ══ -->
<div class="notif-wrap">
    <h2 style="margin:0 0 18px;">🔔 消息中心</h2>
    <?= $tab_html ?>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <div style="font-size:52px;">🔔</div>
            <p>暂时没有消息</p>
        </div>
    <?php else: ?>
        <div class="notif-card">
        <?php foreach ($notifications as $n):
            $cfg = $type_config[$n['type']] ?? ['icon' => '📢', 'text' => '有新消息'];
            if ($n['type']==='follow')        $link = "profile.php?id=".intval($n['from_user_id']);
            elseif ($n['type']==='message')   $link = "notifications.php?tab=message&user_id=".intval($n['from_user_id']);
            elseif ($n['post_id'])            $link = "post.php?id=".intval($n['post_id']);
            else                              $link = "#";

            $title_part = $n['post_title']
                ? '《<span class="post-ref">'.htmlspecialchars(mb_substr($n['post_title'],0,20)).'</span>》' : '';

            $ts = strtotime($n['created_at']); $diff = time()-$ts;
            if ($diff<60)        $time_str='刚刚';
            elseif ($diff<3600)  $time_str=floor($diff/60).' 分钟前';
            elseif ($diff<86400) $time_str=floor($diff/3600).' 小时前';
            else                 $time_str=date('m-d H:i',$ts);

            $icon_html = $n['type']==='mention' ? '<span class="mention-icon">@</span>' : $cfg['icon'];
        ?>
        <div class="notif-item <?= $n['is_read']?'':'unread' ?>" onclick="location.href='<?= $link ?>'">
            <?php if ($n['from_avatar']): ?>
                <img src="../uploads/avatars/<?= htmlspecialchars($n['from_avatar']) ?>"
                     class="notif-avatar" onerror="this.onerror=null;this.src='../uploads/avatars/default.png'">
            <?php else: ?>
                <div class="notif-icon"><?= $icon_html ?></div>
            <?php endif; ?>
            <div class="notif-body">
                <div class="notif-text">
                    <?= $icon_html ?>
                    <strong><?= htmlspecialchars($n['from_username'] ?? '有人') ?></strong>
                    <?= $cfg['text'] ?> <?= $title_part ?>
                </div>
                <?php if ($n['type']==='message' && $n['msg_preview']): ?>
                    <div class="notif-preview">"<?= htmlspecialchars(mb_substr($n['msg_preview'],0,40)) ?>"</div>
                <?php endif; ?>
                <div class="notif-time"><?= $time_str ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

</body>
</html>
<?php $conn->close(); ?>
