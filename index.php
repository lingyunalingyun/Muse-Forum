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

// 侧栏：热搜帖子
$hot_posts = $conn->query("
    SELECT p.id, p.title, p.content, COUNT(pl.post_id) as likes
    FROM posts p
    LEFT JOIN post_likes pl ON p.id = pl.post_id
    WHERE p.status = '已发布'
    GROUP BY p.id
    ORDER BY likes DESC, p.id DESC
    LIMIT 5
");

// 侧栏：公告帖子（只取最新3条）
$notice_posts = $conn->query("
    SELECT id, title FROM posts
    WHERE is_notice = 1 AND status = '已发布'
    ORDER BY id DESC
    LIMIT 3
");

// 侧栏：活跃用户
$active_users = $conn->query("
    SELECT u.id, u.username, u.avatar, COUNT(p.id) as post_count
    FROM users u
    LEFT JOIN posts p ON u.id = p.user_id AND p.status = '已发布'
    GROUP BY u.id
    ORDER BY post_count DESC
    LIMIT 5
");
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

        /* ── 两栏布局 ── */
        .page-layout {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 15px;
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        /* ── 主内容区 ── */
        .main-feed {
            flex: 1;
            min-width: 0;
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

        /* 推荐帖 */
        .post-card.is-recommend {
            border-color: #ffe69c;
            border-left: 4px solid #f6c90e;
            background: linear-gradient(to bottom right, #fffef5, #fff);
        }
        .post-card.is-recommend:hover { border-color: #f6c90e; box-shadow: 0 6px 18px rgba(246,201,14,0.18); }

        /* 公告帖 */
        .post-card.is-notice {
            border-color: #fde8c4;
            border-left: 4px solid #f6a623;
            background: linear-gradient(to bottom right, #fffaf3, #fff);
        }
        .post-card.is-notice:hover { border-color: #f6a623; box-shadow: 0 6px 18px rgba(246,166,35,0.18); }

        /* 卡片 badge */
        .card-badges { display: flex; gap: 6px; margin-bottom: 8px; }
        .card-badge {
            font-size: 11px;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .card-badge.recommend { background: #fff8dc; color: #b8860b; border: 1px solid #f6c90e; }
        .card-badge.notice    { background: #fff3e0; color: #a0621a; border: 1px solid #f6a623; }

        .post-title   { font-size: 17px; font-weight: bold; color: #222; margin-bottom: 8px; }
        .post-excerpt { color: #666; font-size: 14px; line-height: 1.6; margin-bottom: 12px;
                        overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .post-meta    { font-size: 12px; color: #999; display: flex; justify-content: space-between; align-items: center; }
        .author-info  { color: #28a745; font-weight: bold; }

        /* ── 侧栏 ── */
        .sidebar {
            width: 260px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .widget {
            background: white;
            border-radius: 10px;
            border: 1px solid #eee;
            overflow: hidden;
        }
        .widget-title {
            font-size: 13px;
            font-weight: bold;
            color: #555;
            padding: 12px 16px;
            border-bottom: 1px solid #f5f5f5;
            background: #fafafa;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* 热搜列表 */
        .hot-list { padding: 8px 0; }
        .hot-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            text-decoration: none;
            color: #333;
            font-size: 13px;
            transition: 0.2s;
            line-height: 1.4;
        }
        .hot-item:hover { background: #f9f9f9; color: #28a745; }
        .hot-rank {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            background: #eee;
            color: #999;
            font-size: 11px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .hot-rank.top1 { background: #ff4757; color: white; }
        .hot-rank.top2 { background: #ff6b81; color: white; }
        .hot-rank.top3 { background: #ffa502; color: white; }
        .hot-text { flex: 1; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .hot-likes { font-size: 11px; color: #bbb; flex-shrink: 0; }

        /* 公告 */
        .notice-body { padding: 14px 16px; font-size: 13px; color: #555; line-height: 1.8; }
        .notice-item { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px; }
        .notice-item:last-child { margin-bottom: 0; }
        .notice-dot { width: 6px; height: 6px; border-radius: 50%; background: #28a745; margin-top: 7px; flex-shrink: 0; }

        /* 活跃用户 */
        .user-list { padding: 8px 0; }
        .user-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            text-decoration: none;
            color: #333;
            transition: 0.2s;
        }
        .user-item:hover { background: #f9f9f9; }
        .user-item img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #eee;
        }
        .user-item-info { flex: 1; min-width: 0; }
        .user-item-name { font-size: 13px; font-weight: bold; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-item-count { font-size: 11px; color: #999; }

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

        /* ── 响应式 ── */
        @media (max-width: 768px) {
            .page-layout { flex-direction: column; }
            .sidebar { width: 100%; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- Hero 横幅 -->
<div class="hero">
    <img src="uploads/banner/banner.jpg" alt="banner">
</div>

<!-- 两栏布局 -->
<div class="page-layout">

    <!-- 主帖子列表 -->
    <div class="main-feed">
        <div class="section-title">✨ 精选内容</div>
        <?php
        $sql = "SELECT p.id, p.title, p.content, p.created_at, p.is_notice, p.is_recommend, u.username
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

                // 卡片样式类
                $card_class = 'post-card';
                if ($row['is_notice'])    $card_class .= ' is-notice';
                if ($row['is_recommend']) $card_class .= ' is-recommend';

                // badge 标签
                $badges = '';
                if ($row['is_notice'])    $badges .= '<span class="card-badge notice">📢 公告</span>';
                if ($row['is_recommend']) $badges .= '<span class="card-badge recommend">⭐ 编辑推荐</span>';

                echo '<div class="' . $card_class . '" onclick="location.href=\'pages/post.php?id=' . $row['id'] . '\'">';
                if ($badges) echo '  <div class="card-badges">' . $badges . '</div>';
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

    <!-- 右侧栏 -->
    <aside class="sidebar">

        <!-- 热搜 -->
        <div class="widget">
            <div class="widget-title">🔥 热门帖子</div>
            <div class="hot-list">
                <?php
                $rank_classes = ['top1', 'top2', 'top3', '', ''];
                $i = 1;
                if ($hot_posts && $hot_posts->num_rows > 0) {
                    while ($hp = $hot_posts->fetch_assoc()) {
                        $rc    = $rank_classes[$i - 1] ?? '';
                        $title = !empty($hp['title']) ? $hp['title'] : mb_substr(strip_tags($hp['content']), 0, 20) . '...';
                        echo '<a href="pages/post.php?id=' . $hp['id'] . '" class="hot-item">';
                        echo '  <span class="hot-rank ' . $rc . '">' . $i . '</span>';
                        echo '  <span class="hot-text">' . htmlspecialchars($title) . '</span>';
                        echo '  <span class="hot-likes">❤️ ' . $hp['likes'] . '</span>';
                        echo '</a>';
                        $i++;
                    }
                } else {
                    echo '<div style="padding:16px;color:#ccc;font-size:13px;text-align:center;">暂无数据</div>';
                }
                ?>
            </div>
        </div>

        <!-- 公告 -->
        <div class="widget">
            <div class="widget-title">
                📢 社区公告
                <a href="pages/notices.php" style="margin-left:auto; font-size:12px; color:#28a745; font-weight:normal; text-decoration:none; white-space:nowrap;">更多 →</a>
            </div>
            <div class="notice-body">
                <?php if ($notice_posts && $notice_posts->num_rows > 0): ?>
                    <?php while ($np = $notice_posts->fetch_assoc()): ?>
                    <div class="notice-item">
                        <span class="notice-dot"></span>
                        <a href="pages/post.php?id=<?= $np['id'] ?>"
                           style="color:#444; text-decoration:none; font-size:13px; line-height:1.5;"
                           onmouseover="this.style.color='#28a745'"
                           onmouseout="this.style.color='#444'">
                            <?= htmlspecialchars($np['title']) ?>
                        </a>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="color:#ccc; font-size:13px; text-align:center; padding:8px 0;">暂无公告</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 活跃用户 -->
        <div class="widget">
            <div class="widget-title">🏆 活跃用户</div>
            <div class="user-list">
                <?php
                if ($active_users && $active_users->num_rows > 0) {
                    while ($au = $active_users->fetch_assoc()) {
                        $avatar = $au['avatar'] ?: 'default.png';
                        echo '<a href="pages/profile.php?id=' . $au['id'] . '" class="user-item">';
                        echo '  <img src="uploads/avatars/' . htmlspecialchars($avatar) . '" onerror="this.onerror=null;this.src=\'uploads/avatars/default.png\'">';
                        echo '  <div class="user-item-info">';
                        echo '    <div class="user-item-name">' . htmlspecialchars($au['username']) . '</div>';
                        echo '    <div class="user-item-count">' . $au['post_count'] . ' 篇帖子</div>';
                        echo '  </div>';
                        echo '</a>';
                    }
                } else {
                    echo '<div style="padding:16px;color:#ccc;font-size:13px;text-align:center;">暂无数据</div>';
                }
                ?>
            </div>
        </div>

    </aside>
</div>

<script>
(function() {
    const hero = document.querySelector('.hero');
    const img  = hero && hero.querySelector('img');
    if (!img) return;

    hero.addEventListener('mousemove', function(e) {
        const rect   = hero.getBoundingClientRect();
        const cx     = rect.width  / 2;
        const dx     = (e.clientX - rect.left - cx) / cx; // -1 ~ 1
        const moveX  = dx * 12;
        img.style.transform = `scale(1.08) translateX(${moveX}px)`;
    });

    hero.addEventListener('mouseleave', function() {
        img.style.transform = 'scale(1.08) translateX(0)';
    });
})();
</script>

<!-- 登录奖励 Toast -->
<?php if (isset($_SESSION['login_reward'])): ?>
<?php $reward = $_SESSION['login_reward']; unset($_SESSION['login_reward']); ?>
<style>
    .reward-toast {
        position: fixed;
        top: 80px;
        left: 50%;
        transform: translateX(-50%) translateY(-20px);
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        padding: 14px 28px;
        border-radius: 40px;
        box-shadow: 0 6px 24px rgba(40,167,69,0.4);
        font-size: 15px;
        font-weight: bold;
        z-index: 9999;
        opacity: 0;
        transition: opacity 0.4s ease, transform 0.4s ease;
        white-space: nowrap;
        pointer-events: none;
    }
    .reward-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
</style>
<div class="reward-toast" id="reward-toast">
    🎁 每日登录奖励 +<?= $reward['points'] ?> 积分 &nbsp;|&nbsp;
    🔥 已连续登录 <?= $reward['streak'] ?> 天
</div>
<script>
(function(){
    const t = document.getElementById('reward-toast');
    setTimeout(() => t.classList.add('show'), 300);
    setTimeout(() => t.classList.remove('show'), 4000);
})();
</script>
<?php endif; ?>

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
