<?php
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
        body { background: #f4f7f6; font-family: "Microsoft YaHei", sans-serif; margin: 0; padding-bottom: 60px; }

        .notice-page { max-width: 800px; margin: 30px auto; padding: 0 15px; }

        /* 页头 */
        .notice-banner {
            background: linear-gradient(135deg, #f6a623 0%, #e8821a 100%);
            border-radius: 14px;
            padding: 30px 36px;
            color: white;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: 0 6px 24px rgba(232,130,26,0.3);
        }
        .notice-banner-icon { font-size: 52px; line-height: 1; }
        .notice-banner h1 { margin: 0 0 6px; font-size: 24px; }
        .notice-banner p  { margin: 0; font-size: 14px; opacity: 0.88; }

        /* 公告卡片 */
        .notice-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #fde8c4;
            border-left: 5px solid #f6a623;
            padding: 20px 24px;
            margin-bottom: 16px;
            cursor: pointer;
            transition: all 0.25s;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        .notice-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(246,166,35,0.18);
            border-left-color: #e8821a;
        }
        .notice-card-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 10px;
        }
        .notice-icon-sm {
            background: linear-gradient(135deg, #f6a623, #e8821a);
            color: white;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .notice-title {
            font-size: 17px;
            font-weight: bold;
            color: #222;
            line-height: 1.4;
            flex: 1;
        }
        .notice-excerpt {
            color: #777;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 12px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .notice-meta {
            font-size: 12px;
            color: #bbb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notice-meta img {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            object-fit: cover;
            vertical-align: middle;
            margin-right: 5px;
        }

        .empty-tip {
            text-align: center;
            padding: 60px 0;
            color: #ccc;
            font-size: 15px;
        }
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
