<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$my_id = intval($_SESSION['user_id']);
$tab   = $_GET['tab'] ?? 'all'; // all | message | interact

// 标记已读
if ($tab === 'message') {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $my_id AND type = 'message' AND is_read = 0");
} elseif ($tab === 'interact') {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $my_id AND type != 'message' AND is_read = 0");
} else {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $my_id AND is_read = 0");
}

// 查询通知
$where = "WHERE n.user_id = $my_id";
if ($tab === 'message')  $where .= " AND n.type = 'message'";
if ($tab === 'interact') $where .= " AND n.type != 'message'";

$sql = "SELECT n.*,
               u.username AS from_username,
               u.avatar   AS from_avatar,
               p.title    AS post_title,
               (SELECT content FROM messages
                WHERE from_user_id = n.from_user_id AND to_user_id = n.user_id
                ORDER BY created_at DESC LIMIT 1) AS msg_preview
        FROM notifications n
        LEFT JOIN users u ON n.from_user_id = u.id
        LEFT JOIN posts p ON n.post_id = p.id
        $where
        ORDER BY n.created_at DESC
        LIMIT 100";
$res = $conn->query($sql);
$notifications = [];
if ($res) while ($row = $res->fetch_assoc()) $notifications[] = $row;

// 各 tab 未读数
$unread_msg      = (int)$conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$my_id AND type='message'  AND is_read=0")->fetch_assoc()['c'];
$unread_interact = (int)$conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$my_id AND type!='message' AND is_read=0")->fetch_assoc()['c'];

// 通知类型配置
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
        .container { max-width: 700px; margin: 30px auto; padding: 0 15px; }
        h2 { margin: 0 0 20px 0; font-size: 20px; }

        .tabs { display: flex; gap: 8px; margin-bottom: 18px; }
        .tab { padding: 7px 20px; border-radius: 20px; font-size: 14px; cursor: pointer;
               border: 1px solid #ddd; background: white; color: #666; text-decoration: none;
               position: relative; transition: 0.2s; }
        .tab.active, .tab:hover { background: #28a745; color: white; border-color: #28a745; }
        .tab-badge { position: absolute; top: -6px; right: -6px; background: #e74c3c; color: white;
                     border-radius: 10px; min-width: 17px; height: 17px; font-size: 11px;
                     display: flex; align-items: center; justify-content: center; padding: 0 4px; box-sizing: border-box; }

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
        .notif-preview { font-size: 13px; color: #999; margin-top: 3px; white-space: nowrap;
                         overflow: hidden; text-overflow: ellipsis; }
        .notif-time { font-size: 12px; color: #bbb; margin-top: 4px; }
        .post-ref { color: #007bff; }
        .mention-icon { background: #e8f4fd; color: #1565c0; font-weight: bold;
                        border-radius: 3px; padding: 0 4px; font-size: 13px; }
        .empty-state { text-align: center; padding: 70px 20px; background: white; border-radius: 10px; }
        .empty-state p { color: #999; margin-top: 10px; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <h2>🔔 消息中心</h2>

    <div class="tabs">
        <a href="notifications.php?tab=all" class="tab <?= $tab === 'all' ? 'active' : '' ?>">
            全部
            <?php if ($unread_msg + $unread_interact > 0 && $tab !== 'all'): ?>
                <span class="tab-badge"><?= min($unread_msg + $unread_interact, 99) ?></span>
            <?php endif; ?>
        </a>
        <a href="notifications.php?tab=message" class="tab <?= $tab === 'message' ? 'active' : '' ?>">
            💬 私信
            <?php if ($unread_msg > 0): ?>
                <span class="tab-badge"><?= min($unread_msg, 99) ?></span>
            <?php endif; ?>
        </a>
        <a href="notifications.php?tab=interact" class="tab <?= $tab === 'interact' ? 'active' : '' ?>">
            ❤️ 互动
            <?php if ($unread_interact > 0): ?>
                <span class="tab-badge"><?= min($unread_interact, 99) ?></span>
            <?php endif; ?>
        </a>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <div style="font-size:52px;">🔔</div>
            <p>暂时没有消息</p>
        </div>
    <?php else: ?>
        <div class="notif-card">
        <?php foreach ($notifications as $n):
            $cfg = $type_config[$n['type']] ?? ['icon' => '📢', 'text' => '有新消息'];

            if ($n['type'] === 'follow') {
                $link = "profile.php?id=" . intval($n['from_user_id']);
            } elseif ($n['type'] === 'message') {
                $link = "messages.php?user_id=" . intval($n['from_user_id']);
            } elseif ($n['post_id']) {
                $link = "post.php?id=" . intval($n['post_id']);
            } else {
                $link = "#";
            }

            $title_part = $n['post_title']
                ? '《<span class="post-ref">' . htmlspecialchars(mb_substr($n['post_title'], 0, 20)) . '</span>》'
                : '';

            $ts   = strtotime($n['created_at']);
            $diff = time() - $ts;
            if ($diff < 60)        $time_str = '刚刚';
            elseif ($diff < 3600)  $time_str = floor($diff / 60) . ' 分钟前';
            elseif ($diff < 86400) $time_str = floor($diff / 3600) . ' 小时前';
            else                   $time_str = date('m-d H:i', $ts);

            $icon_html = $n['type'] === 'mention'
                ? '<span class="mention-icon">@</span>'
                : $cfg['icon'];
        ?>
        <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" onclick="location.href='<?= $link ?>'">
            <?php if ($n['from_avatar']): ?>
                <img src="../uploads/avatars/<?= htmlspecialchars($n['from_avatar']) ?>"
                     class="notif-avatar"
                     onerror="this.onerror=null;this.src='../uploads/avatars/default.png'">
            <?php else: ?>
                <div class="notif-icon"><?= $icon_html ?></div>
            <?php endif; ?>
            <div class="notif-body">
                <div class="notif-text">
                    <?= $icon_html ?>
                    <strong><?= htmlspecialchars($n['from_username'] ?? '有人') ?></strong>
                    <?= $cfg['text'] ?>
                    <?= $title_part ?>
                </div>
                <?php if ($n['type'] === 'message' && $n['msg_preview']): ?>
                    <div class="notif-preview">"<?= htmlspecialchars(mb_substr($n['msg_preview'], 0, 40)) ?>"</div>
                <?php endif; ?>
                <div class="notif-time"><?= $time_str ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
<?php $conn->close(); ?>
