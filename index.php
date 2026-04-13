<?php
session_start();
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);
    $user_check = $conn->query("SELECT role, username FROM users WHERE id = $uid");
    if ($user_data = $user_check->fetch_assoc()) {
        $_SESSION['role']     = $user_data['role'];
        $_SESSION['username'] = $user_data['username'];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>社区论坛</title>
    <style>
        * { box-sizing: border-box; }
        body { background: #f4f7f6; font-family: "Microsoft YaHei", sans-serif; margin: 0; padding-bottom: 80px; }

        /* ── Hero 横幅 ── */
        .hero {
            width: 100%;
            height: 220px;
            overflow: hidden;
            background: #d8e8d8;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transform: scale(1.08);
            transition: transform 0.1s ease-out;
            will-change: transform;
        }
        .hero-placeholder {
            color: #aaa;
            font-size: 14px;
            text-align: center;
        }

        /* ── 内容区 ── */
        .main-content {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 15px;
        }
        .section-title {
            font-size: 16px;
            color: #666;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #eee;
        }

        /* ── 帖子卡片 ── */
        .post-card {
            background: white;
            margin-bottom: 15px;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #eee;
            cursor: pointer;
            transition: all 0.25s;
        }
        .post-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); border-color: #28a745; }
        .post-title   { font-size: 17px; font-weight: bold; color: #222; margin-bottom: 8px; }
        .post-excerpt { color: #666; font-size: 14px; line-height: 1.6; margin-bottom: 12px;
                        overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .post-meta    { font-size: 12px; color: #999; display: flex; justify-content: space-between; align-items: center; }
        .author-info  { color: #28a745; font-weight: bold; }

        /* ── 悬浮发布按钮 ── */
        .fab {
            position: fixed;
            right: 32px;
            bottom: 36px;
            width: 54px;
            height: 54px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            text-decoration: none;
            box-shadow: 0 4px 16px rgba(40,167,69,0.45);
            transition: 0.25s;
            z-index: 999;
        }
        .fab:hover { background: #218838; transform: scale(1.1); box-shadow: 0 6px 20px rgba(40,167,69,0.55); }
        .fab-tip {
            position: fixed;
            right: 94px;
            bottom: 47px;
            background: rgba(0,0,0,0.65);
            color: white;
            font-size: 13px;
            padding: 4px 10px;
            border-radius: 6px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s;
            white-space: nowrap;
            z-index: 999;
        }
        .fab:hover + .fab-tip,
        .fab-wrap:hover .fab-tip { opacity: 1; }
        .fab-wrap { position: fixed; right: 32px; bottom: 36px; z-index: 999; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- Hero 横幅 -->
<div class="hero">
    <?php if (file_exists(__DIR__ . '/uploads/banner/banner.jpg')): ?>
        <img src="uploads/banner/banner.jpg" alt="banner">
    <?php elseif (file_exists(__DIR__ . '/uploads/banner/banner.png')): ?>
        <img src="uploads/banner/banner.png" alt="banner">
    <?php else: ?>
        <div class="hero-placeholder">📷 横幅图片占位 — 将图片放入 uploads/banner/ 并命名为 banner.jpg</div>
    <?php endif; ?>
</div>

<!-- 帖子列表 -->
<div class="main-content">
    <div class="section-title">✨ 精选内容</div>
    <?php
    $sql = "SELECT p.id, p.title, p.content, p.created_at, u.username
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.status = '已发布'
            ORDER BY p.id DESC";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $clean_text    = strip_tags($row['content']);
            $excerpt       = mb_substr($clean_text, 0, 100) . (mb_strlen($clean_text) > 100 ? '...' : '');
            $display_title = !empty($row['title']) ? $row['title'] : $row['username'] . ' 的分享';
            echo '<div class="post-card" onclick="location.href=\'pages/post.php?id=' . $row['id'] . '\'">';
            echo '  <div class="post-title">'   . htmlspecialchars($display_title) . '</div>';
            echo '  <div class="post-excerpt">' . htmlspecialchars($excerpt) . '</div>';
            echo '  <div class="post-meta">';
            echo '    <span>作者：<span class="author-info">' . htmlspecialchars($row['username']) . '</span></span>';
            echo '    <span>' . date('Y-m-d H:i', strtotime($row['created_at'])) . '</span>';
            echo '  </div>';
            echo '</div>';
        }
    } else {
        echo '<p style="text-align:center;color:#bbb;padding:40px 0;">暂无内容，快来发第一篇吧～</p>';
    }
    ?>
</div>

<script>
(function() {
    const hero = document.querySelector('.hero');
    const img  = hero && hero.querySelector('img');
    if (!img) return;

    hero.addEventListener('mousemove', function(e) {
        const rect   = hero.getBoundingClientRect();
        const cx     = rect.width  / 2;
        const cy     = rect.height / 2;
        const dx     = (e.clientX - rect.left - cx) / cx; // -1 ~ 1
        const dy     = (e.clientY - rect.top  - cy) / cy;
        const moveX  = dx * 12; // 最大偏移 12px
        const moveY  = dy * 6;
        img.style.transform = `scale(1.08) translate(${moveX}px, ${moveY}px)`;
    });

    hero.addEventListener('mouseleave', function() {
        img.style.transform = 'scale(1.08) translate(0, 0)';
    });
})();
</script>

<!-- 悬浮发布按钮 -->
<?php if (isset($_SESSION['user_id'])): ?>
<div class="fab-wrap">
    <a href="pages/publish.php" class="fab" title="发布帖子">✍️</a>
    <div class="fab-tip">发布帖子</div>
</div>
<?php endif; ?>

</body>
</html>
<?php $conn->close(); ?>
