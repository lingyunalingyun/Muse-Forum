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
    'comment'      => ['icon' => '💬', 'text' => '评论了你的帖子'],
    'reply'        => ['icon' => '↩️',  'text' => '回复了你的评论'],
    'mention'      => ['icon' => '@',   'text' => '在评论中 @ 了你'],
    'like_post'    => ['icon' => '❤️',  'text' => '赞了你的帖子'],
    'fav_post'     => ['icon' => '⭐',  'text' => '收藏了你的帖子'],
    'like_comment' => ['icon' => '👍',  'text' => '赞了你的评论'],
    'follow'       => ['icon' => '👤',  'text' => '关注了你'],
    'message'      => ['icon' => '✉️',  'text' => '给你发了一条私信'],
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>消息中心</title>
    <style>
        body { background: #f4f7f6; font-family: "Microsoft YaHei", sans-serif; margin: 0; padding-bottom: 40px; }

        /* ── 通知布局 ── */
        .notif-wrap { max-width: 700px; margin: 30px auto; padding: 0 15px; }

        /* ── 私信布局 ── */
        .msg-layout { max-width: 1000px; margin: 0 auto; padding: 0 15px; display: flex; gap: 15px; height: calc(100vh - 270px); }
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

        /* ── 公共 Tab 栏 ── */
        .tabs { display: flex; gap: 8px; margin-bottom: 18px; }
        .tab { padding: 7px 20px; border-radius: 20px; font-size: 14px; cursor: pointer;
               border: 1px solid #ddd; background: white; color: #666; text-decoration: none;
               position: relative; transition: 0.2s; }
        .tab.active, .tab:hover { background: #28a745; color: white; border-color: #28a745; }
        .tab-badge { position: absolute; top: -6px; right: -6px; background: #e74c3c; color: white;
                     border-radius: 10px; min-width: 17px; height: 17px; font-size: 11px;
                     display: flex; align-items: center; justify-content: center; padding: 0 4px; box-sizing: border-box; }

        /* ── 通知列表 ── */
        .notif-card { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .notif-item { display: flex; align-items: center; gap: 14px; padding: 16px 20px;
                      border-bottom: 1px solid #f5f5f5; cursor: pointer; transition: background 0.2s; }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: #f9fafb; }
        .notif-item.unread { background: #f0fdf4; }
        .notif-item.unread:hover { background: #e6f9ed; }
        .notif-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
        .notif-icon { width: 44px; height: 44px; border-radius: 50%; background: #f1f3f4;
                      display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .notif-body { flex: 1; min-width: 0; }
        .notif-text { font-size: 14px; color: #333; line-height: 1.5; }
        .notif-text strong { color: #28a745; }
        .notif-preview { font-size: 13px; color: #999; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .notif-time { font-size: 12px; color: #bbb; margin-top: 4px; }
        .post-ref { color: #007bff; }
        .mention-icon { background: #e8f4fd; color: #1565c0; font-weight: bold; border-radius: 3px; padding: 0 4px; font-size: 13px; }
        .empty-state { text-align: center; padding: 70px 20px; background: white; border-radius: 10px; }
        .empty-state p { color: #999; margin-top: 10px; }
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
