<?php
/**
 * index.php — 首页
 *
 * 布局：Hero 横幅 + 两栏（主内容 + 右侧栏）
 *
 * 主内容区：
 *   - 仅展示 is_recommend=1 的推荐帖，2 列正方形大图网格
 *   - 提取帖子内容中第一张 <img> 作为封面缩略图
 *   - 底部「前往广场」横幅入口
 *
 * 右侧栏（三个 Widget）：
 *   - 热门帖子（按点赞数降序 Top 5）
 *   - 社区公告（is_notice=1 最新 3 条）
 *   - 活跃用户（按发帖数降序 Top 5）
 *
 * Hero 特效：
 *   - 背景图鼠标 X 轴视差（±24px，平滑插值）
 *   - 滚动 Y 轴视差（系数 0.35）
 *
 * 登录奖励 Toast：
 *   - 登录成功后 $_SESSION['login_reward'] 写入奖励数据
 *   - 首页消费一次后 unset，不会重复显示
 *
 * 读表：posts, users, post_likes, comments
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
    <title>缪斯 MUSE</title>
    <style>
        /* ── 首页专属布局 ── */
        .page-layout { max-width:1100px; margin:28px auto; padding:0 16px; display:flex; gap:20px; align-items:flex-start; }
        .main-feed   { flex:1; min-width:0; }
        .sidebar     { width:260px; flex-shrink:0; display:flex; flex-direction:column; gap:12px; }
        @media(max-width:768px){
            /* 改成 block 让子元素自然堆叠，不再有 flex 高度问题 */
            .page-layout { display: block; margin: 0; padding: 0; }

            .sidebar {
                display: flex;
                flex-direction: row;
                overflow-x: auto;
                gap: 8px;
                padding: 10px 12px;
                background: #0d1117;
                border-bottom: 1px solid #30363d;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                align-items: flex-start;
                width: 100%;
                box-sizing: border-box;
            }
            .sidebar::-webkit-scrollbar { display: none; }
            .sidebar .widget {
                min-width: 180px;
                max-width: 220px;
                flex-shrink: 0;
            }
            /* 每个小部件最多显示 3 条 */
            .sidebar .hot-item:nth-child(n+4),
            .sidebar .notice-item:nth-child(n+4),
            .sidebar .user-item:nth-child(n+4) { display: none; }
            /* 缩紧内部间距 */
            .sidebar .widget-head { padding: 8px 12px; font-size: 10px; }
            .sidebar .hot-item,
            .sidebar .user-item { padding: 6px 10px; font-size: 12px; }
            .sidebar .notice-body { padding: 8px 12px; }
            .sidebar .notice-item { margin-bottom: 6px; }
            .sidebar .notice-item a { font-size: 12px; }

            .main-feed { padding: 12px 12px 0; }
        }

        /* ── Hero 科技横幅 ── */
        .hero {
            width: 100%;
            background-color: #0d1117;
            border-bottom: 1px solid #30363d;
            padding: 0;
            overflow: hidden;
            position: relative;
        }
        /* 背景大图：绝对定位，比容器略大，留出视差移动空间 */
        .hero-bg {
            position: absolute;
            inset: -8% -4%;
            width: calc(100% + 8%);
            height: calc(116%);
            object-fit: cover;
            display: none;           /* 默认隐藏，JS 加载成功后显示 */
            will-change: transform;
        }
        .hero-bg.loaded { display: block; }
        .hero-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 36px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            position: relative;
            z-index: 2;
        }
        .hero-text h1 {
            font-size: 26px;
            font-weight: 700;
            color: #e6edf3;
            margin: 0 0 6px;
            font-family: "Courier New", monospace;
        }
        .hero-text h1 span { color: #3fb950; }
        .hero-text p { color: #8b949e; font-size: 13px; margin: 0; font-family: "Courier New", monospace; }
        .hero-stats { display:flex; gap:24px; }
        .hero-stat  { text-align:center; }
        .hero-stat-num  { font-size:22px; font-weight:700; color:#3fb950; font-family:"Courier New",monospace; display:block; }
        .hero-stat-label{ font-size:11px; color:#6e7681; letter-spacing:.5px; text-transform:uppercase; font-family:"Courier New",monospace; }
        @media(max-width:600px){
            .hero-inner { flex-direction:column; text-align:center; padding:24px 16px; gap:16px; }
            .hero-text h1 { font-size:20px; }
            .hero-stats { gap:28px; }
        }
        /* 暗色遮罩 + 背景网格（z-index:1 确保压在背景图上方） */
        .hero::before {
            content:'';
            position:absolute; inset:0;
            background:
                linear-gradient(rgba(63,185,80,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(63,185,80,.04) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(13,17,23,.88) 0%, rgba(13,17,23,.78) 100%);
            background-size: 40px 40px, 40px 40px, auto;
            pointer-events:none;
            z-index: 1;
        }
        /* 右侧光晕 */
        .hero::after {
            content:'';
            position:absolute; right:-60px; top:50%; transform:translateY(-50%);
            width:400px; height:400px;
            background: radial-gradient(circle, rgba(63,185,80,.15) 0%, transparent 70%);
            pointer-events:none;
            z-index: 1;
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

        /* ── 推荐帖子：图片方格网格 ── */
        .featured-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 14px;
        }
        @media(max-width:480px) {
            .featured-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
        }

        .featured-card {
            position: relative;
            aspect-ratio: 1 / 1;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            background: #161b22;
            border: 1px solid #30363d;
            transition: transform .2s, box-shadow .2s;
        }
        .featured-card:hover {
            transform: translateY(-3px) scale(1.01);
            box-shadow: 0 8px 28px rgba(0,0,0,.5);
        }
        /* 帖子缩略图 */
        .featured-card .fc-img {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform .3s;
        }
        .featured-card:hover .fc-img { transform: scale(1.05); }
        /* 无图时的占位背景 */
        .featured-card .fc-placeholder {
            position: absolute; inset: 0;
            background: #161b22;
            background-image:
                linear-gradient(rgba(63,185,80,.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(63,185,80,.05) 1px, transparent 1px);
            background-size: 24px 24px;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; color: rgba(63,185,80,.25);
            font-family: "Courier New", monospace;
        }
        /* 底部渐变遮罩 */
        .featured-card::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(
                to bottom,
                transparent 30%,
                rgba(13,17,23,.6) 60%,
                rgba(13,17,23,.92) 100%
            );
            pointer-events: none;
        }
        /* 推荐角标 */
        .fc-badge {
            position: absolute; top: 10px; left: 10px;
            background: rgba(227,179,65,.9); color: #0d1117;
            font-size: 10px; font-weight: 700; padding: 2px 7px;
            border-radius: 3px; font-family: "Courier New", monospace;
            letter-spacing: .5px; z-index: 2;
        }
        /* 底部文字区 */
        .fc-body {
            position: absolute; bottom: 0; left: 0; right: 0;
            padding: 16px 14px 12px;
            z-index: 2;
        }
        .fc-title {
            font-size: 16px; font-weight: 700; color: #e6edf3;
            line-height: 1.4; margin-bottom: 8px;
            overflow: hidden; display: -webkit-box;
            -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        }
        .fc-meta {
            font-size: 12px; color: rgba(230,237,243,.65);
            font-family: "Courier New", monospace;
            display: flex; justify-content: space-between; align-items: center;
        }
        .fc-author { color: #3fb950; font-weight: 700; }

        .author-info { color:#3fb950; font-weight:700; font-family:inherit; }

        /* 广场入口横幅 */
        .square-banner {
            display: flex; align-items: center; justify-content: space-between;
            background: #161b22; border: 1px solid #30363d; border-radius:6px;
            padding: 14px 18px; margin-bottom: 8px;
            text-decoration: none; transition: border-color .2s;
        }
        .square-banner:hover { border-color: #3fb950; }
        .square-banner-left { font-size:13px; color:#8b949e; font-family:"Courier New",monospace; }
        .square-banner-left strong { color:#e6edf3; display:block; font-size:14px; margin-bottom:2px; }
        .square-banner-arrow { font-size:18px; color:#3fb950; }

        /* ── 侧栏组件 ── */
        .widget { background:#161b22; border:1px solid #30363d; border-radius:6px; overflow:hidden; margin-bottom:0; }
        .widget-title {
            font-size:11px; font-weight:700; color:#6e7681;
            letter-spacing:1.5px; text-transform:uppercase;
            padding:11px 14px; border-bottom:1px solid #30363d;
            font-family:"Courier New",monospace;
            display:flex; align-items:center; gap:6px;
        }
        .widget-title::before { content:'//'; color:#3fb950; }
        .widget-title a { margin-left:auto; font-size:11px; color:#3fb950; font-weight:normal; font-family:inherit; text-transform:none; letter-spacing:0; }

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

        .user-list { padding:6px 0; }
        .user-item { display:flex; align-items:center; gap:10px; padding:8px 14px;
                     text-decoration:none; color:#8b949e; transition:.15s; }
        .user-item:hover { background:#1c2128; color:#e6edf3; }
        .user-item img { width:30px; height:30px; border-radius:50%; object-fit:cover; border:1px solid #30363d; }
        .user-item-info  { flex:1; min-width:0; }
        .user-item-name  { font-size:13px; font-weight:700; color:#e6edf3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .user-item-count { font-size:11px; color:#6e7681; font-family:"Courier New",monospace; }

        /* ── 登录奖励 Toast ── */
        .reward-toast {
            position:fixed; top:76px; left:50%;
            transform:translateX(-50%) translateY(-20px);
            background:#161b22; border:1px solid #3fb950;
            color:#e6edf3; padding:12px 24px; border-radius:4px;
            box-shadow:0 0 20px rgba(63,185,80,.25);
            font-size:14px; font-weight:700; font-family:"Courier New",monospace;
            z-index:9999; opacity:0; transition:opacity .4s, transform .4s;
            white-space:nowrap; pointer-events:none;
        }
        .reward-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
        .reward-toast .accent { color:#3fb950; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- Hero 科技横幅 -->
<div class="hero" id="site-hero">
    <img class="hero-bg" id="hero-bg" src="uploads/hero-bg.jpg" alt=""
         onerror="this.style.display='none'" onload="this.classList.add('loaded')">
    <div class="hero-inner">
        <div class="hero-text">
            <h1>&gt; 欢迎来到 <span>MUSE</span>_</h1>
            <p>// 创作 · 分享 · 交流 · 探索</p>
        </div>
        <?php
        $stat_posts = $conn->query("SELECT COUNT(*) c FROM posts WHERE status='已发布'")->fetch_assoc()['c'] ?? 0;
        $stat_users = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c'] ?? 0;
        ?>
        <div class="hero-stats">
            <div class="hero-stat">
                <span class="hero-stat-num"><?= $stat_posts ?></span>
                <span class="hero-stat-label">帖子</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat-num"><?= $stat_users ?></span>
                <span class="hero-stat-label">成员</span>
            </div>
        </div>
    </div>
</div>

<!-- 两栏布局 -->
<div class="page-layout">

    <!-- 主帖子列表：仅推荐帖 -->
    <div class="main-feed">
        <div class="section-title">编辑推荐</div>
        <?php
        $sql = "SELECT p.id, p.title, p.content, p.created_at,
                       u.username,
                       (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes_count,
                       (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.status = '已发布' AND p.is_recommend = 1
                ORDER BY p.id DESC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0):
        ?>
        <div class="featured-grid">
        <?php while ($row = $result->fetch_assoc()):
            $display_title = !empty($row['title']) ? $row['title'] : $row['username'] . ' 的分享';
            // 提取内容中的第一张图片
            preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $row['content'], $img_match);
            $thumb = $img_match[1] ?? null;
        ?>
        <div class="featured-card" onclick="location.href='pages/post.php?id=<?= $row['id'] ?>'">
            <?php if ($thumb): ?>
                <img class="fc-img" src="<?= htmlspecialchars($thumb) ?>" alt="" loading="lazy"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div class="fc-placeholder" style="display:none;">#</div>
            <?php else: ?>
                <div class="fc-placeholder">#</div>
            <?php endif; ?>
            <span class="fc-badge">★ 推荐</span>
            <div class="fc-body">
                <div class="fc-title"><?= htmlspecialchars($display_title) ?></div>
                <div class="fc-meta">
                    <span class="fc-author"><?= htmlspecialchars($row['username']) ?></span>
                    <span>❤️ <?= (int)$row['likes_count'] ?> &nbsp;💬 <?= (int)$row['comment_count'] ?></span>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div style="text-align:center;color:#6e7681;padding:40px 0;font-family:'Courier New',monospace;">// 暂无推荐内容</div>
        <?php endif; ?>

        <!-- 广场入口 -->
        <a href="square.php" class="square-banner">
            <div class="square-banner-left">
                <strong>// 前往广场</strong>
                浏览全部帖子
            </div>
            <span class="square-banner-arrow">→</span>
        </a>
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
            <div class="widget-title">
                社区公告
                <a href="pages/notices.php">更多</a>
            </div>
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

        <!-- 活跃用户 -->
        <div class="widget">
            <div class="widget-title">活跃用户</div>
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
                    echo '<div style="padding:16px;color:#6e7681;font-size:12px;text-align:center;font-family:\'Courier New\',monospace;">// 暂无数据</div>';
                }
                ?>
            </div>
        </div>

    </aside>
</div>

<script>
(function() {
    const hero  = document.getElementById('site-hero');
    const img   = document.getElementById('hero-bg');
    if (!img) return;

    // 目标值 / 当前插值
    let targetX  = 0, curX  = 0;  // 鼠标水平偏移 px
    let targetY  = 0, curY  = 0;  // 滚动垂直偏移 px
    let rafId    = null;
    let isMoving = false;

    function lerp(a, b, t) { return a + (b - a) * t; }

    function tick() {
        curX = lerp(curX, targetX, 0.07);
        img.style.transform = 'translateX(' + curX.toFixed(2) + 'px) translateY(' + curY.toFixed(2) + 'px)';

        if (Math.abs(curX - targetX) > 0.05) {
            rafId = requestAnimationFrame(tick);
        } else {
            rafId = null;
        }
    }

    function startTick() {
        if (!rafId) rafId = requestAnimationFrame(tick);
    }

    // 鼠标水平视差（仅 X 轴，幅度 ±24px，带平滑插值）
    hero.addEventListener('mousemove', function(e) {
        const rect = hero.getBoundingClientRect();
        const dx   = (e.clientX - rect.left - rect.width / 2) / rect.width;
        targetX = dx * -24;
        startTick();
    });
    hero.addEventListener('mouseleave', function() {
        targetX = 0;
        startTick();
    });

    // 滚动视差：Y 轴直接跟随，不插值
    window.addEventListener('scroll', function() {
        curY = window.scrollY * 0.35;
        img.style.transform = 'translateX(' + curX.toFixed(2) + 'px) translateY(' + curY.toFixed(2) + 'px)';
    }, { passive: true });
})();
</script>

<!-- 登录奖励 Toast -->
<?php if (isset($_SESSION['login_reward'])): ?>
<?php $reward = $_SESSION['login_reward']; unset($_SESSION['login_reward']); ?>
<div class="reward-toast" id="reward-toast">
    &gt; 登录奖励 <span class="accent">+<?= $reward['exp'] ?> EXP</span> &nbsp;|&nbsp; 连续登录 <span class="accent"><?= $reward['streak'] ?> 天</span>
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
    <div class="fab-tip">发帖</div>
    <a href="pages/publish.php" class="fab" title="发布帖子">+</a>
</div>
<?php endif; ?>

<style>
#_x9k2{display:none;position:fixed;inset:0;background:#000;z-index:99999;align-items:center;justify-content:center;cursor:text;opacity:0;transition:opacity 1.2s ease}
#_m7q1{font-size:7vw;font-weight:bold;letter-spacing:.05em;text-align:center;width:90%;line-height:1.2;word-break:break-all;background:linear-gradient(to bottom,#fff 30%,transparent 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;opacity:0;transform:translateY(-12px);transition:opacity 1s ease .4s,transform 1s ease .4s}
#_x9k2._v{opacity:1}
#_x9k2._v #_m7q1{opacity:1;transform:translateY(0)}
</style>
<div id="_x9k2"><div id="_m7q1">有什么放不下的？</div></div>
<script>function _0x10be(_0x298f05,_0xe99b22){_0x298f05=_0x298f05-0x6d;const _0x5b466e=_0x5b46();let _0x10be78=_0x5b466e[_0x298f05];return _0x10be78;}function _0x5b46(){const _0x2d46c4=['有什么放不下的？','removeEventListener','key','flex','none','opacity\x201s\x20ease,\x20transform\x201s\x20ease','remove','activeElement','display','2iANfgi','817968FdwzDJ','code','2694882ieuflK','getElementById','1076921DzVoov','preventDefault','10pGBlAd','Enter','length','keyup','trim','tagName','Backspace','2154609qJrSZA','1141TxjMzm','classList','transition','TEXTAREA','_x9k2','opacity','addEventListener','transform','style','INPUT','248PxqYwJ','Escape','爱一个人是发自内心的','44504dFPswT','Space','PLX','兰谷自逢欣，有缘再相遇','textContent','84140dvuRvW','add','5753704aRLOyf'];_0x5b46=function(){return _0x2d46c4;};return _0x5b46();}(function(_0xebb072,_0x1e5b92){const _0x13b825=_0x10be,_0x2202c7=_0xebb072();while(!![]){try{const _0x3a4bd9=-parseInt(_0x13b825(0x80))/0x1*(-parseInt(_0x13b825(0x7b))/0x2)+parseInt(_0x13b825(0x7c))/0x3+parseInt(_0x13b825(0x94))/0x4*(-parseInt(_0x13b825(0x6f))/0x5)+parseInt(_0x13b825(0x7e))/0x6+parseInt(_0x13b825(0x8a))/0x7*(-parseInt(_0x13b825(0x97))/0x8)+parseInt(_0x13b825(0x89))/0x9*(parseInt(_0x13b825(0x82))/0xa)+parseInt(_0x13b825(0x71))/0xb;if(_0x3a4bd9===_0x1e5b92)break;else _0x2202c7['push'](_0x2202c7['shift']());}catch(_0x43ca55){_0x2202c7['push'](_0x2202c7['shift']());}}}(_0x5b46,0x9530c),(function(){const _0x3c8c5c=_0x10be;let _0x298c95=null,_0x457d8c=![],_0x282372='';const _0x107277=_0x3c8c5c(0x72);let _0xd01193=![];document[_0x3c8c5c(0x90)]('keydown',function(_0x8bc5dc){const _0xeda7bc=_0x3c8c5c;if(_0xd01193){_0x8bc5dc[_0xeda7bc(0x81)]();return;}if(_0x8bc5dc[_0xeda7bc(0x7d)]!=='Space'||_0x457d8c)return;if(document[_0xeda7bc(0x79)]['tagName']===_0xeda7bc(0x93)||document[_0xeda7bc(0x79)][_0xeda7bc(0x87)]===_0xeda7bc(0x8d))return;_0x8bc5dc[_0xeda7bc(0x81)](),_0x457d8c=!![],_0x298c95=setTimeout(_0x2a6dcc,0x2710);}),document['addEventListener'](_0x3c8c5c(0x85),function(_0x580c77){const _0x52958e=_0x3c8c5c;if(_0x580c77['code']!==_0x52958e(0x98))return;_0x457d8c=![],clearTimeout(_0x298c95);});function _0x2a6dcc(){const _0x28b23c=_0x3c8c5c,_0x16080c=document[_0x28b23c(0x7f)](_0x28b23c(0x8e)),_0x2b87ac=document[_0x28b23c(0x7f)]('_m7q1');_0x282372='',_0x2b87ac['textContent']=_0x107277,_0x16080c[_0x28b23c(0x92)]['display']=_0x28b23c(0x75),requestAnimationFrame(()=>requestAnimationFrame(()=>{const _0x1ac8c8=_0x28b23c;_0x16080c[_0x1ac8c8(0x8b)][_0x1ac8c8(0x70)]('_v');})),_0xd01193=!![];function _0x54667d(_0x40322d){const _0x188304=_0x28b23c;_0x40322d['preventDefault']();if(_0x40322d[_0x188304(0x74)]===_0x188304(0x95)){_0x16080c[_0x188304(0x8b)][_0x188304(0x78)]('_v'),setTimeout(()=>{const _0x44875c=_0x188304;_0x16080c[_0x44875c(0x92)][_0x44875c(0x7a)]=_0x44875c(0x76);},0x4b0),_0x282372='',_0xd01193=![],document[_0x188304(0x73)]('keydown',_0x54667d);return;}if(_0x40322d[_0x188304(0x7d)]===_0x188304(0x98))return;if(_0x40322d['key']===_0x188304(0x83)){const _0x548b81=_0x282372[_0x188304(0x86)]();let _0x59a177='';if(_0x548b81===_0x188304(0x99))_0x59a177=_0x188304(0x6d);else{if(_0x548b81!=='')_0x59a177=_0x188304(0x96);}_0x59a177&&(_0x2b87ac[_0x188304(0x92)]['transition']='none',_0x2b87ac[_0x188304(0x92)][_0x188304(0x8f)]='0',_0x2b87ac[_0x188304(0x92)][_0x188304(0x91)]='translateY(-12px)',setTimeout(()=>{const _0x59c9d8=_0x188304;_0x2b87ac[_0x59c9d8(0x6e)]=_0x59a177,_0x2b87ac['style'][_0x59c9d8(0x8c)]=_0x59c9d8(0x77),_0x2b87ac['style']['opacity']='1',_0x2b87ac['style'][_0x59c9d8(0x91)]='translateY(0)';},0xc8));_0x282372='';return;}if(_0x40322d[_0x188304(0x74)]===_0x188304(0x88))_0x282372=_0x282372['slice'](0x0,-0x1);else{if(_0x40322d[_0x188304(0x74)][_0x188304(0x84)]===0x1)_0x282372+=_0x40322d[_0x188304(0x74)];}_0x2b87ac[_0x188304(0x6e)]=_0x282372||_0x107277;}document[_0x28b23c(0x90)]('keydown',_0x54667d);}}()));</script>

</body>
</html>
<?php $conn->close(); ?>
