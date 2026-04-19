<?php
/**
 * square.php — 帖子广场页
 *
 * 功能：分页展示全站已发布帖子（每页 20 条），支持通过 ?cat=ID 按分区筛选；
 *       自动过滤当前用户的黑名单用户发布的帖子以及不可见帖子。
 * 读写表：posts、categories、user_blocks
 * 权限：无
 */
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

$latest_date = '';
$ldr = $conn->query("SELECT DATE(p.created_at) d FROM posts p JOIN users u ON p.user_id=u.id WHERE p.status='已发布' $vis_where ORDER BY p.created_at DESC LIMIT 1");
if ($ldr && $row_ld = $ldr->fetch_assoc()) $latest_date = $row_ld['d'];

$daily_seed = (int)date('Ymd');

$total_res = $conn->query("SELECT COUNT(*) c FROM posts p JOIN users u ON p.user_id=u.id WHERE p.status='已发布' $vis_where");
$total     = (int)($total_res->fetch_assoc()['c'] ?? 0);
$total_pages = max(1, ceil($total / $per_page));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>广场 - 缪斯 MUSE</title>
    <style>
        
        .page-layout { max-width:1100px; margin:28px auto; padding:0 16px; display:flex; gap:20px; align-items:flex-start; }
        .main-feed   { flex:1; min-width:0; }
        .sidebar     { width:260px; flex-shrink:0; display:flex; flex-direction:column; gap:12px; }
        @media(max-width:768px){
            .page-layout { display: block; margin: 0; padding: 0; }
            .sidebar {
                display: flex; flex-direction: row; overflow-x: auto;
                gap: 8px; padding: 10px 12px;
                background: 
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

        
        .square-hero {
            background: 
            border-bottom: 1px solid 
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
            font-size: 22px; font-weight: 700; color: 
            font-family: "Courier New", monospace; margin: 0 0 4px;
            position: relative; z-index: 1;
        }
        .square-hero h1 span { color: 
        .square-hero p {
            font-size: 12px; color: 
            font-family: "Courier New", monospace; margin: 0;
            position: relative; z-index: 1;
        }

        
        .section-title {
            font-size:11px; font-weight:700; color:
            letter-spacing:1.5px; text-transform:uppercase;
            font-family:"Courier New",monospace;
            margin-bottom:14px;
            display:flex; align-items:center; gap:8px;
        }
        .section-title::before { content:'//'; color:
        .section-title::after  { content:''; flex:1; height:1px; background:

        
        .post-card {
            background: 
            margin-bottom:8px; padding:16px 18px; cursor:pointer;
            transition: border-color .2s, box-shadow .15s, transform .15s;
        }
        .post-card:hover {
            border-color:
            box-shadow:0 0 0 1px 
            transform:translateY(-1px);
        }
        .post-card.is-recommend { border-left:3px solid 
        .post-card.is-notice    { border-left:3px solid 

        .card-badges  { display:flex; gap:6px; margin-bottom:8px; }
        .card-badge   { font-size:10px; font-weight:700; padding:2px 7px; border-radius:4px; letter-spacing:.5px; }
        .card-badge.recommend { background:rgba(227,179,65,.15); color:
        .card-badge.notice    { background:rgba(240,136,62,.15); color:

        .post-title   { font-size:15px; font-weight:700; color:
        .post-excerpt { color:
                        overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
        .post-meta    { font-size:12px; color:
                        font-family:"Courier New",monospace; }
        .author-info  { color:

        
        .pagination {
            display:flex; gap:6px; justify-content:center;
            margin: 24px 0 8px; flex-wrap:wrap;
        }
        .page-btn {
            padding:5px 12px; border-radius:4px; font-size:13px;
            font-family:"Courier New",monospace; text-decoration:none;
            border:1px solid 
            transition:.15s; background:
        }
        .page-btn:hover { border-color:
        .page-btn.active { border-color:
        .page-btn.disabled { opacity:.4; pointer-events:none; }

        
        .widget { background:
        .widget-title {
            font-size:11px; font-weight:700; color:
            letter-spacing:1.5px; text-transform:uppercase;
            padding:11px 14px; border-bottom:1px solid 
            font-family:"Courier New",monospace;
            display:flex; align-items:center; gap:6px;
        }
        .widget-title::before { content:'//'; color:

        .hot-list { padding:6px 0; }
        .hot-item { display:flex; align-items:center; gap:10px; padding:8px 14px;
                    text-decoration:none; color:
        .hot-item:hover { background:
        .hot-rank { width:18px; height:18px; border-radius:4px; background:
                    font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center;
                    flex-shrink:0; font-family:"Courier New",monospace; }
        .hot-rank.top1 { background:rgba(63,185,80,.2);  color:
        .hot-rank.top2 { background:rgba(88,166,255,.2); color:
        .hot-rank.top3 { background:rgba(240,136,62,.2); color:
        .hot-text  { flex:1; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
        .hot-likes { font-size:11px; color:

        .notice-body { padding:12px 14px; }
        .notice-item { display:flex; align-items:flex-start; gap:8px; margin-bottom:8px; }
        .notice-item:last-child { margin-bottom:0; }
        .notice-dot  { width:5px; height:5px; border-radius:50%; background:
        .notice-item a { color:
        .notice-item a:hover { color:

        
        .back-link {
            display:inline-block; margin-bottom:14px;
            color:
            font-family:"Courier New",monospace; transition:color .2s;
        }
        .back-link:hover { color:

    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="square-hero">
    <h1>
    <p>发现帖子 · 共 <?= $total ?> 篇</p>
</div>

<div class="page-layout">

    <div class="main-feed">
        <a href="index.php" class="back-link">← 返回首页</a>
        <div class="section-title">发现</div>

        <?php
        $safe_latest = $conn->real_escape_string($latest_date);
        $sql = "SELECT p.id, p.title, p.content, p.created_at, p.is_notice, p.is_recommend,
                       u.username,
                       (DATE(p.created_at) = '$safe_latest') as is_latest_day,
                       (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes_count,
                       (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
                FROM posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.status = '已发布' $vis_where
                ORDER BY is_latest_day DESC, RAND($daily_seed)
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
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="square.php?page=<?= $page-1 ?>" class="page-btn">← 上一页</a>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
                <a href="square.php?page=<?= $i ?>" class="page-btn<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="square.php?page=<?= $page+1 ?>" class="page-btn">下一页 →</a>
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
                    <div style="color:#6e7681;font-size:12px;text-align:center;padding:8px 0;font-family:'Courier New',monospace;">
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
