<?php
/**
 * pages/notices.php — 社区公告列表
 *
 * 显示所有 is_notice=1 且 status='已发布' 的帖子，按 id DESC 排序。
 * 点击标题跳转到 post.php?id=...
 * 读表：posts, users
 */
session_start();
require_once __DIR__ . '/../config.php';

$notices = $conn->query("
    SELECT p.id, p.title, p.content, p.created_at, u.username, u.avatar
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.is_notice = 1 AND p.status = '已发布'
    ORDER BY p.id DESC
");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>社区公告</title>
    <style>
        * { box-sizing: border-box; }

        .notice-page { max-width: 800px; margin: 24px auto; padding: 0 15px; }

        /* 页头 */
        .notice-banner {
            background: #161b22;
            border: 1px solid #30363d;
            border-left: 3px solid #f0883e;
            border-radius: 6px;
            padding: 24px 28px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .notice-banner-icon { font-size: 36px; line-height: 1; }
        .notice-banner h1 { margin: 0 0 4px; font-size: 18px; color: #e6edf3; font-family: "Courier New", monospace; }
        .notice-banner p  { margin: 0; font-size: 13px; color: #8b949e; }

        /* 公告卡片 */
        .notice-card {
            background: #161b22;
            border-radius: 6px;
            border: 1px solid #30363d;
            border-left: 3px solid #f0883e;
            padding: 18px 22px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        .notice-card:hover { border-color: #f0883e; box-shadow: 0 0 0 1px rgba(240,136,62,.4); }

        .notice-card-header {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
        }
        .notice-icon-sm {
            background: rgba(240,136,62,.15);
            color: #f0883e;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            border: 1px solid rgba(240,136,62,.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .notice-title {
            font-size: 15px;
            font-weight: 600;
            color: #c9d1d9;
            line-height: 1.4;
            flex: 1;
        }
        .notice-excerpt {
            color: #8b949e;
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 10px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .notice-meta {
            font-size: 11px;
            color: #6e7681;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: "Courier New", monospace;
        }
        .notice-meta img {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            object-fit: cover;
            vertical-align: middle;
            margin-right: 5px;
            border: 1px solid #30363d;
        }

        .empty-tip { text-align: center; padding: 60px 0; color: #6e7681; font-size: 14px; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="notice-page">

    <div class="notice-banner">
        <div class="notice-banner-icon">📢</div>
        <div>
            <h1>社区公告</h1>
            <p>来自管理员的重要通知与公告，请及时查阅</p>
        </div>
    </div>

    <?php if ($notices && $notices->num_rows > 0): ?>
        <?php while ($n = $notices->fetch_assoc()):
            $excerpt = mb_substr(strip_tags($n['content']), 0, 120);
            if (mb_strlen(strip_tags($n['content'])) > 120) $excerpt .= '...';
        ?>
        <a href="post.php?id=<?= $n['id'] ?>" class="notice-card">
            <div class="notice-card-header">
                <div class="notice-icon-sm">📌</div>
                <div class="notice-title"><?= htmlspecialchars($n['title']) ?></div>
            </div>
            <?php if ($excerpt): ?>
            <div class="notice-excerpt"><?= htmlspecialchars($excerpt) ?></div>
            <?php endif; ?>
            <div class="notice-meta">
                <span>
                    <img src="../uploads/avatars/<?= htmlspecialchars($n['avatar'] ?: 'default.png') ?>"
                         onerror="this.onerror=null;this.src='../uploads/avatars/default.png'">
                    <?= htmlspecialchars($n['username']) ?>
                </span>
                <span><?= date('Y-m-d H:i', strtotime($n['created_at'])) ?></span>
            </div>
        </a>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-tip">📭 暂无公告</div>
    <?php endif; ?>

</div>

</body>
</html>
<?php $conn->close(); ?>
