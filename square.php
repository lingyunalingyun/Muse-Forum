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

$hot_posts = $conn->query("
    SELECT p.id, p.title, p.content, COUNT(pl.post_id) as likes
    FROM posts p
    LEFT JOIN post_likes pl ON p.id = pl.post_id
    WHERE p.status = '已发布'
    GROUP BY p.id
    ORDER BY likes DESC, p.id DESC
    LIMIT 5
");

$notice_posts = $conn->query("
    SELECT id, title FROM posts
    WHERE is_notice = 1 AND status = '已发布'
    ORDER BY id DESC
    LIMIT 3
");

$cats = [];
$cr = $conn->query("SELECT id, name, icon, color FROM categories ORDER BY sort_order ASC, id ASC");
if ($cr) while ($c = $cr->fetch_assoc()) $cats[] = $c;

$cur_cat = (int)($_GET['cat'] ?? 0);
$cat_where = $cur_cat > 0 ? "AND p.category_id = $cur_cat" : '';

$page     = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$my_role_sq = $_SESSION['role'] ?? 'user';
$bypass_vis  = in_array($my_role_sq, ['admin', 'owner']);
$uid = intval($_SESSION['user_id'] ?? 0);

if ($bypass_vis) {
    $vis_where = '';
} elseif ($uid > 0) {
    $vis_where = "AND (
        u.post_visibility = 'public'
        OR u.id = $uid
        OR (u.post_visibility = 'followers' AND EXISTS (SELECT 1 FROM follows WHERE follower_id=$uid AND followed_id=u.id))
        OR (u.post_visibility = 'following' AND EXISTS (SELECT 1 FROM follows WHERE follower_id=u.id AND followed_id=$uid))
        OR (u.post_visibility = 'mutual'
            AND EXISTS (SELECT 1 FROM follows WHERE follower_id=$uid AND followed_id=u.id)
            AND EXISTS (SELECT 1 FROM follows WHERE follower_id=u.id AND followed_id=$uid))
    )
    AND NOT EXISTS (SELECT 1 FROM user_blocks WHERE blocker_id=u.id AND blocked_id=$uid)
    AND NOT EXISTS (SELECT 1 FROM user_blocks WHERE blocker_id=$uid AND blocked_id=u.id)";
} else {
    $vis_where = "AND u.post_visibility = 'public'";
}

$total_res = $conn->query("SELECT COUNT(*) c FROM posts p JOIN users u ON p.user_id=u.id WHERE p.status='已发布' $cat_where $vis_where");
$total     = (int)($total_res->fetch_assoc()['c'] ?? 0);
$total_pages = max(1, ceil($total / $per_page));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>广场 - 缪斯 MUSE</title>
    <style>
        /* ── 广场布局 ── */
        .page-layout { max-width:1100px; margin:28px auto; padding:0 16px; display:flex; gap:20px; align-items:flex-start; }
        .main-feed   { flex:1; min-width:0; }
        .sidebar     { width:260px; flex-shrink:0; display:flex; flex-direction:column; gap:12px; }
        @media(max-width:768px){
            .page-layout { display: block; margin: 0; padding: 0; }
            .sidebar {
                display: flex; flex-direction: row; overflow-x: auto;
                gap: 8px; padding: 10px 12px;
                background: #0d1117; border-bottom: 1px solid #30363d;
                -webkit-overflow-scrolling: touch; scrollbar-width: none;
                align-items: flex-start; width: 100%; box-sizing: border-box;
            }
            .sidebar::-webkit-scrollbar { display: none; }
            .sidebar .widget { min-width: 180px; max-width: 220px; flex-shrink: 0; }
            .sidebar .hot-item:nth-child(n+4),
            .sidebar .notice-item:nth-child(n+4) { display: none; }
            .sidebar .widget-head { padding: 8px 12px; font-size: 10px; }
            .sidebar .hot-item { padding: 6px 10px; font-size: 12px; }
            .sidebar .notice-body { padding: 8px 12px; }
            .main-feed { padding: 12px 12px 0; }
        }

        /* ── 广场页头 ── */
        .square-hero {
            background: #0d1117;
            border-bottom: 1px solid #30363d;
            padding: 28px 0 22px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .square-hero::before {
            content:'';
            position:absolute; inset:0;
            background-image:
                linear-gradient(rgba(63,185,80,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(63,185,80,.04) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events:none;
        }
        .square-hero h1 {
            font-size: 22px; font-weight: 700; color: #e6edf3;
            font-family: "Courier New", monospace; margin: 0 0 4px;
            position: relative; z-index: 1;
        }
        .square-hero h1 span { color: #3fb950; }
        .square-hero p {
            font-size: 12px; color: #6e7681;
            font-family: "Courier New", monospace; margin: 0;
            position: relative; z-index: 1;
        }

        /* ── 节标题 ── */
        .section-title {
            font-size:11px; font-weight:700; color:#6e7681;
            letter-spacing:1.5px; text-transform:uppercase;
            font-family:"Courier New",monospace;
            margin-bottom:14px;
            display:flex; align-items:center; gap:8px;
        }
        .section-title::before { content:'//'; color:#3fb950; }
        .section-title::after  { content:''; flex:1; height:1px; background:#30363d; }

        /* ── 帖子卡片 ── */
        .post-card {
            background: #161b22; border:1px solid #30363d; border-radius:6px;
            margin-bottom:8px; padding:16px 18px; cursor:pointer;
            transition: border-color .2s, box-shadow .15s, transform .15s;
        }
        .post-card:hover {
            border-color:#3fb950;
            box-shadow:0 0 0 1px #3fb950, 0 4px 20px rgba(63,185,80,.08);
            transform:translateY(-1px);
        }
        .post-card.is-recommend { border-left:3px solid #e3b341; }
        .post-card.is-notice    { border-left:3px solid #f0883e; }

        .card-badges  { display:flex; gap:6px; margin-bottom:8px; }
        .card-badge   { font-size:10px; font-weight:700; padding:2px 7px; border-radius:4px; letter-spacing:.5px; }
        .card-badge.recommend { background:rgba(227,179,65,.15); color:#e3b341; border:1px solid rgba(227,179,65,.3); }
        .card-badge.notice    { background:rgba(240,136,62,.15); color:#f0883e; border:1px solid rgba(240,136,62,.3); }

        .post-title   { font-size:15px; font-weight:700; color:#e6edf3; margin-bottom:6px; line-height:1.4; }
        .post-excerpt { color:#8b949e; font-size:13px; line-height:1.6; margin-bottom:10px;
                        overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
        .post-meta    { font-size:12px; color:#6e7681; display:flex; justify-content:space-between; align-items:center;
                        font-family:"Courier New",monospace; }
        .author-info  { color:#3fb950; font-weight:700; }

        /* ── 分页 ── */
        .pagination {
            display:flex; gap:6px; justify-content:center;
            margin: 24px 0 8px; flex-wrap:wrap;
        }
        .page-btn {
            padding:5px 12px; border-radius:4px; font-size:13px;
            font-family:"Courier New",monospace; text-decoration:none;
            border:1px solid #30363d; color:#8b949e;
            transition:.15s; background:#161b22;
        }
        .page-btn:hover { border-color:#3fb950; color:#3fb950; }
        .page-btn.active { border-color:#3fb950; color:#3fb950; background:rgba(63,185,80,.1); font-weight:700; }
        .page-btn.disabled { opacity:.4; pointer-events:none; }

        /* ── 侧栏组件 ── */
        .widget { background:#161b22; border:1px solid #30363d; border-radius:6px; overflow:hidden; }
        .widget-title {
            font-size:11px; font-weight:700; color:#6e7681;
            letter-spacing:1.5px; text-transform:uppercase;
            padding:11px 14px; border-bottom:1px solid #30363d;
            font-family:"Courier New",monospace;
            display:flex; align-items:center; gap:6px;
        }
        .widget-title::before { content:'//'; color:#3fb950; }

        .hot-list { padding:6px 0; }
        .hot-item { display:flex; align-items:center; gap:10px; padding:8px 14px;
                    text-decoration:none; color:#8b949e; font-size:13px; transition:.15s; line-height:1.4; }
        .hot-item:hover { background:#1c2128; color:#e6edf3; }
        .hot-rank { width:18px; height:18px; border-radius:4px; background:#1c2128; color:#6e7681;
                    font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center;
                    flex-shrink:0; font-family:"Courier New",monospace; }
        .hot-rank.top1 { background:rgba(63,185,80,.2);  color:#3fb950; }
        .hot-rank.top2 { background:rgba(88,166,255,.2); color:#58a6ff; }
        .hot-rank.top3 { background:rgba(240,136,62,.2); color:#f0883e; }
        .hot-text  { flex:1; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
        .hot-likes { font-size:11px; color:#6e7681; flex-shrink:0; font-family:"Courier New",monospace; }

        .notice-body { padding:12px 14px; }
        .notice-item { display:flex; align-items:flex-start; gap:8px; margin-bottom:8px; }
        .notice-item:last-child { margin-bottom:0; }
        .notice-dot  { width:5px; height:5px; border-radius:50%; background:#3fb950; margin-top:8px; flex-shrink:0; }
        .notice-item a { color:#8b949e; font-size:13px; line-height:1.5; transition:color .15s; }
        .notice-item a:hover { color:#3fb950; }

        /* 返回首页链接 */
        .back-link {
            display:inline-block; margin-bottom:14px;
            color:#6e7681; text-decoration:none; font-size:12px;
            font-family:"Courier New",monospace; transition:color .2s;
        }
        .back-link:hover { color:#e6edf3; }

        /* ── 分区标签栏 ── */
        .cat-bar {
            background: #0d1117;
            border-bottom: 1px solid #30363d;
            overflow-x: auto; scrollbar-width: none;
        }
        .cat-bar::-webkit-scrollbar { display: none; }
        .cat-bar-inner {
            max-width: 1100px; margin: 0 auto; padding: 0 16px;
            display: flex; gap: 0; align-items: stretch;
        }
        .cat-tab {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 12px 18px; text-decoration: none;
            font-size: 13px; color: #6e7681; white-space: nowrap;
            border-bottom: 2px solid transparent;
            transition: color .15s, border-color .15s;
        }
        .cat-tab:hover { color: #e6edf3; }
        .cat-tab.active { color: #e6edf3; border-bottom-color: var(--tc, #3fb950); font-weight: 600; }
        .cat-tab .cat-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--tc, #3fb950); flex-shrink: 0; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="square-hero">
    <h1>// <span>广场</span></h1>
    <p>全部帖子 · 共 <?= $total ?> 篇</p>
</div>

<?php if (!empty($cats)): ?>
<div class="cat-bar">
    <div class="cat-bar-inner">
        <a href="square.php" class="cat-tab<?= $cur_cat === 0 ? ' active' : '' ?>" style="--tc:#3fb950">全部</a>
        <?php foreach ($cats as $c): ?>
        <a href="square.php?cat=<?= $c['id'] ?>"
           class="cat-tab<?= $cur_cat === (int)$c['id'] ? ' active' : '' ?>"
           style="--tc:<?= htmlspecialchars($c['color']) ?>">
            <span class="cat-dot"></span>
            <?= htmlspecialchars($c['icon']) ?> <?= htmlspecialchars($c['name']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="page-layout">

    <div class="main-feed">
        <a href="index.php" class="back-link">← 返回首页</a>
        <div class="section-title">全部帖子</div>

        <?php
        $sql = "SELECT p.id, p.title, p.content, p.created_at, p.is_notice, p.is_recommend,
                       u.username,
                       (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes_count,
                       (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
                FROM posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.status = '已发布' $cat_where $vis_where
                ORDER BY p.id DESC
                LIMIT $per_page OFFSET $offset";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $clean_text    = strip_tags($row['content']);
                $excerpt       = mb_substr($clean_text, 0, 100) . (mb_strlen($clean_text) > 100 ? '...' : '');
                $display_title = !empty($row['title']) ? $row['title'] : $row['username'] . ' 的分享';

                $card_class = 'post-card';
                if ($row['is_notice'])    $card_class .= ' is-notice';
                if ($row['is_recommend']) $card_class .= ' is-recommend';

                $badges = '';
                if ($row['is_notice'])    $badges .= '<span class="card-badge notice">📢 公告</span>';
                if ($row['is_recommend']) $badges .= '<span class="card-badge recommend">⭐ 推荐</span>';

                echo '<div class="' . $card_class . '" onclick="location.href=\'pages/post.php?id=' . $row['id'] . '\'">';
                if ($badges) echo '  <div class="card-badges">' . $badges . '</div>';
                echo '  <div class="post-title">'   . htmlspecialchars($display_title) . '</div>';
                if ($excerpt) echo '  <div class="post-excerpt">' . htmlspecialchars($excerpt) . '</div>';
                echo '  <div class="post-meta">';
                echo '    <span>作者：<span class="author-info">' . htmlspecialchars($row['username']) . '</span></span>';
                echo '    <span>❤️ ' . (int)$row['likes_count'] . ' &nbsp; 💬 ' . (int)$row['comment_count'] . ' &nbsp; ' . date('m-d', strtotime($row['created_at'])) . '</span>';
                echo '  </div>';
                echo '</div>';
            }
        } else {
            echo '<div style="text-align:center;color:#6e7681;padding:40px 0;font-family:\'Courier New\',monospace;">// 暂无内容</div>';
        }
        ?>

        <!-- 分页 -->
        <?php if ($total_pages > 1):
            $cat_qs = $cur_cat > 0 ? "&cat=$cur_cat" : '';
        ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="square.php?page=<?= $page-1 ?><?= $cat_qs ?>" class="page-btn">← 上一页</a>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
                <a href="square.php?page=<?= $i ?><?= $cat_qs ?>" class="page-btn<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="square.php?page=<?= $page+1 ?><?= $cat_qs ?>" class="page-btn">下一页 →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 右侧栏 -->
    <aside class="sidebar">
        <!-- 热搜 -->
        <div class="widget">
            <div class="widget-title">热门帖子</div>
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
                    echo '<div style="padding:16px;color:#6e7681;font-size:12px;text-align:center;font-family:\'Courier New\',monospace;">// 暂无数据</div>';
                }
                ?>
            </div>
        </div>

        <!-- 公告 -->
        <div class="widget">
            <div class="widget-title">社区公告</div>
            <div class="notice-body">
                <?php if ($notice_posts && $notice_posts->num_rows > 0): ?>
                    <?php while ($np = $notice_posts->fetch_assoc()): ?>
                    <div class="notice-item">
                        <span class="notice-dot"></span>
                        <a href="pages/post.php?id=<?= $np['id'] ?>"><?= htmlspecialchars($np['title']) ?></a>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="color:#6e7681;font-size:12px;text-align:center;padding:8px 0;font-family:'Courier New',monospace;">// 暂无公告</div>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>

<!-- 悬浮发布按钮 -->
<?php if (isset($_SESSION['user_id'])): ?>
<div class="fab-wrap">
    <div class="fab-tip">发帖</div>
    <a href="pages/publish.php" class="fab" title="发布帖子">+</a>
</div>
<?php endif; ?>

</body>
</html>
<?php $conn->close(); ?>
