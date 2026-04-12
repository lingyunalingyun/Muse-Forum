<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$my_id = intval($_SESSION['user_id']);

// 标记全部已读
$conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $my_id AND is_read = 0");

// 读取通知列表
$sql = "SELECT n.*,
               u.username AS from_username,
               u.avatar   AS from_avatar,
               p.title    AS post_title
        FROM notifications n
        LEFT JOIN users u ON n.from_user_id = u.id
        LEFT JOIN posts p ON n.post_id = p.id
        WHERE n.user_id = $my_id
        ORDER BY n.created_at DESC
        LIMIT 100";
$res = $conn->query($sql);
$notifications = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// 通知类型配置
$type_config = [
    'comment'      => ['icon' => '💬', 'text' => '评论了你的帖子'],
    'reply'        => ['icon' => '↩️', 'text' => '回复了你的评论'],
    'like_post'    => ['icon' => '❤️', 'text' => '赞了你的帖子'],
    'fav_post'     => ['icon' => '⭐', 'text' => '收藏了你的帖子'],
    'like_comment' => ['icon' => '👍', 'text' => '赞了你的评论'],
    'follow'       => ['icon' => '👤', 'text' => '关注了你'],
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>消息通知</title>
    <style>
        body { background: #f4f7f6; font-family: "Microsoft YaHei", sans-serif; margin: 0; padding-bottom: 40px; }
        .notif-container { max-width: 700px; margin: 30px auto; padding: 0 15px; }
        .notif-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .notif-header h2 { margin: 0; font-size: 20px; }
        .notif-card { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .notif-item { display: flex; align-items: center; gap: 14px; padding: 16px 20px; border-bottom: 1px solid #f5f5f5; transition: background 0.2s; cursor: pointer; }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: #f9fafb; }
        .notif-item.unread { background: #f0fdf4; }
        .notif-item.unread:hover { background: #e6f9ed; }
        .notif-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
        .notif-icon { width: 44px; height: 44px; border-radius: 50%; background: #f1f3f4; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .notif-body { flex: 1; min-width: 0; }
        .notif-text { font-size: 14px; color: #333; line-height: 1.5; }
        .notif-text strong { color: #28a745; }
        .notif-text .post-link { color: #007bff; }
        .notif-time { font-size: 12px; color: #bbb; margin-top: 4px; }
        .empty-state { text-align: center; padding: 70px 20px; background: white; border-radius: 10px; }
        .empty-state p { color: #999; margin-top: 10px; }
        .tab-bar { display: flex; gap: 5px; margin-bottom: 15px; }
        .tab-btn { padding: 6px 16px; border-radius: 20px; font-size: 13px; cursor: pointer; border: 1px solid #ddd; background: white; color: #666; transition: 0.2s; }
        .tab-btn.active, .tab-btn:hover { background: #28a745; color: white; border-color: #28a745; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="notif-container">
    <div class="notif-header">
        <h2>🔔 消息通知</h2>
        <span style="font-size:13px; color:#999;">最近 100 条</span>
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

            // 构造跳转链接
            if ($n['type'] === 'follow') {
                $link = "profile.php?id=" . intval($n['from_user_id']);
            } elseif ($n['post_id']) {
                $link = "post.php?id=" . intval($n['post_id']);
            } else {
                $link = "#";
            }

            $title_part = $n['post_title']
                ? '《' . htmlspecialchars(mb_substr($n['post_title'], 0, 20)) . '》'
                : '';

            // 时间格式
            $ts = strtotime($n['created_at']);
            $diff = time() - $ts;
            if ($diff < 60) $time_str = '刚刚';
            elseif ($diff < 3600) $time_str = floor($diff / 60) . ' 分钟前';
            elseif ($diff < 86400) $time_str = floor($diff / 3600) . ' 小时前';
            else $time_str = date('m-d H:i', $ts);
        ?>
        <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" onclick="location.href='<?= $link ?>'">
            <?php if ($n['from_avatar']): ?>
                <img src="../uploads/avatars/<?= htmlspecialchars($n['from_avatar']) ?>"
                     class="notif-avatar"
                     onerror="this.src='https://via.placeholder.com/44'">
            <?php else: ?>
                <div class="notif-icon"><?= $cfg['icon'] ?></div>
            <?php endif; ?>
            <div class="notif-body">
                <div class="notif-text">
                    <?= $cfg['icon'] ?>
                    <strong><?= htmlspecialchars($n['from_username'] ?? '有人') ?></strong>
                    <?= $cfg['text'] ?>
                    <?php if ($title_part): ?>
                        <span class="post-link"><?= $title_part ?></span>
                    <?php endif; ?>
                </div>
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
