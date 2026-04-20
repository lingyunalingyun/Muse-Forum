<?php
/**
 * post.php — 帖子详情页
 *
 * 功能：展示帖子正文、作者信息、评论列表，支持点赞、收藏、评论、转发，管理员可删帖/置顶
 * 读写表：posts、users、comments、post_likes、post_favs、follows、notifications
 * 权限：公开（部分操作需登录）
 */
session_start();
// --- 1. 数据库连接与配置 ---
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/text_format.php';
require_once __DIR__ . '/../includes/exp_helper.php';

// --- 2. 获取参数与身份 ---
$my_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$pid = isset($_GET['id']) ? intval($_GET['id']) : 0;
$my_role = $_SESSION['role'] ?? 'user';
$sort = $_GET['sort'] ?? 'new';

// --- 3. 获取帖子主体及作者详细信息 ---
$post_query = "SELECT p.*,
                u.id as author_id, u.username, u.avatar, u.role, u.level, u.is_banned, u.post_visibility,
                (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as post_count,
                (SELECT COUNT(*) FROM follows WHERE followed_id = u.id) as fans_count,
                (CASE WHEN $my_id = 0 THEN 0
                      ELSE (SELECT COUNT(*) FROM follows WHERE follower_id = $my_id AND followed_id = u.id)
                 END) as is_following,
                (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM post_favs WHERE post_id = p.id) as favs_count,
                (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_id = $my_id) as my_like,
                (SELECT COUNT(*) FROM post_favs WHERE post_id = p.id AND user_id = $my_id) as my_fav
                FROM posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.id = $pid";
$post_res = $conn->query($post_query);
$post = ($post_res && $post_res->num_rows > 0) ? $post_res->fetch_assoc() : null;

if (!$post) {
    $conn->close();
    die("帖子不存在或已被删除。 <a href='../index.php'>返回首页</a>");
}

// 拉黑检查
$author_id = (int)$post['author_id'];
if ($my_id && $my_id !== $author_id) {
    // 作者拉黑了我
    $blk = $conn->query("SELECT id FROM user_blocks WHERE blocker_id=$author_id AND blocked_id=$my_id");
    if ($blk && $blk->num_rows > 0) {
        $conn->close();
        die("<!DOCTYPE html><html lang='zh-CN'><head><meta charset='UTF-8'><title>无权访问</title></head><body style='font-family:monospace;background:#0d1117;color:#e6edf3;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;'><div style='text-align:center;padding:32px;background:#161b22;border:1px solid #30363d;border-radius:6px;max-width:360px;'><p style='color:#f85149;margin:0 0 12px;'>无权访问此帖子</p><a href='../index.php' style='color:#3fb950;font-size:13px;'>返回首页</a></div></body></html>");
    }
}

// 可见性检查
require_once __DIR__ . '/../includes/exp_helper.php';
$author_vis = $post['post_visibility'] ?? 'public';
if (!can_see_posts($conn, $author_vis, $author_id, $my_id, $my_role)) {
    $conn->close();
    $vis_label = ['following'=>'我关注的人','followers'=>'关注我的人','mutual'=>'互相关注的人','private'=>'仅自己'];
    $hint = $vis_label[$author_vis] ?? '特定用户';
    die("<!DOCTYPE html><html lang='zh-CN'><head><meta charset='UTF-8'><title>无权访问</title></head><body style='font-family:monospace;background:#0d1117;color:#e6edf3;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;'><div style='text-align:center;padding:32px;background:#161b22;border:1px solid #30363d;border-radius:6px;max-width:360px;'><p style='color:#8b949e;font-size:13px;margin:0 0 6px;'>// 权限不足</p><p style='color:#e6edf3;margin:0 0 12px;'>该帖子仅对「{$hint}」可见</p><a href='../index.php' style='color:#3fb950;font-size:13px;'>返回首页</a></div></body></html>");
}

// --- 3.5 阅读量（同一访客 10 分钟内只计一次）---
ensure_user_columns($conn);
$viewer_key = $my_id ? 'u_' . $my_id : 'ip_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
$vk_safe    = $conn->real_escape_string($viewer_key);
$ten_ago    = date('Y-m-d H:i:s', time() - 600);
$vr = $conn->query("SELECT viewed_at FROM post_view_logs WHERE post_id=$pid AND viewer_key='$vk_safe'");
$vrow = $vr ? $vr->fetch_assoc() : null;
if (!$vrow || strtotime($vrow['viewed_at']) < time() - 600) {
    $now_str = date('Y-m-d H:i:s');
    $conn->query("INSERT INTO post_view_logs (post_id, viewer_key, viewed_at)
                  VALUES ($pid, '$vk_safe', '$now_str')
                  ON DUPLICATE KEY UPDATE viewed_at='$now_str'");
    $conn->query("UPDATE posts SET views = views + 1 WHERE id=$pid");
    $post['views'] = ($post['views'] ?? 0) + 1;
}

// --- 4. 楼层逻辑准备 ---
$floor_map = [];
$floor_res = $conn->query("SELECT id FROM comments WHERE post_id = $pid ORDER BY id ASC");
$f_idx = 1;
if ($floor_res) {
    while ($f_row = $floor_res->fetch_assoc()) {
        $floor_map[$f_row['id']] = $f_idx++;
    }
}

// --- 5. 获取评论列表 ---
$order_sql = ($sort === 'hot') ? "c.likes DESC, c.id DESC" : "c.id DESC";
$comment_sql = "SELECT c.*, u.username as author_name, u.avatar,
                u2.username as target_name,
                (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id AND user_id = $my_id) as my_like
                FROM comments c
                JOIN users u ON c.user_id = u.id
                LEFT JOIN comments c2 ON c.parent_id = c2.id
                LEFT JOIN users u2 ON c2.user_id = u2.id
                WHERE c.post_id = $pid
                ORDER BY c.is_top DESC, $order_sql";
$c_res = $conn->query($comment_sql);
$total_comments = $c_res ? $c_res->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars(!empty($post['title']) ? $post['title'] : $post['username'] . ' 的动态'); ?></title>
    <style>
        /* ── 帖子页布局 ── */
        .container { max-width:1100px; margin:24px auto; display:flex; gap:20px; padding:0 16px; align-items:flex-start; }
        .post-main  { flex:1; background:#161b22; border:1px solid #30363d; border-radius:6px; padding:28px 30px; overflow:hidden; min-width:0; }
        .post-header{ display:flex; justify-content:space-between; align-items:flex-start; border-bottom:1px solid #30363d; padding-bottom:16px; gap:12px; }
        @media(max-width:768px){
            .container { flex-direction:column; margin:12px auto; padding:0 10px; gap:12px; }
            .post-main { padding:18px 16px; }
            .post-sidebar { width:100% !important; position:static !important; }
            .post-header { flex-direction:column; gap:10px; }
            .admin-actions { flex-wrap:wrap; }
            .post-content img { max-width:100%; max-height:none; }
        }
        @media(max-width:480px){
            .post-main { padding:14px 12px; }
            .post-content { font-size:14px; }
        }
        .post-main.is-notice    { border-top:3px solid #f0883e; }
        .post-main.is-recommend { border-top:3px solid #e3b341; }

        /* 公告/推荐 bar */
        .notice-top-bar {
            display:flex; align-items:center; gap:10px;
            background:rgba(240,136,62,.08); border:1px solid rgba(240,136,62,.25);
            border-radius:4px; padding:10px 14px; margin-bottom:20px;
            font-size:13px; color:#f0883e;
        }
        .notice-top-bar .top-badge { color:#fff; font-size:11px; padding:2px 8px; border-radius:4px; font-weight:700; letter-spacing:.5px; flex-shrink:0; }
        .notice-top-bar .top-badge.orange { background:#f0883e; }
        .notice-top-bar .top-badge.gold   { background:#e3b341; }
        .notice-top-bar a { margin-left:auto; color:#f0883e; font-size:12px; }
        .post-main.is-recommend .notice-top-bar { background:rgba(227,179,65,.08); border-color:rgba(227,179,65,.25); color:#e3b341; }
        .post-main.is-recommend .notice-top-bar a { color:#e3b341; }

        /* 帖子标题区 */
        .post-header h2 { color:#e6edf3; margin:0 0 6px; font-size:20px; }
        .post-meta-line { font-size:12px; color:#6e7681; font-family:"Courier New",monospace; }
        .post-meta-line a { color:#3fb950; }
        .edited-at-tag  { font-size:11px; color:#6e7681; margin-top:3px; font-family:"Courier New",monospace; }

        /* 管理操作 */
        .admin-actions { display:flex; gap:8px; align-items:center; flex-shrink:0; }
        .btn-recommend { background:transparent; color:#e3b341; border:1px solid rgba(227,179,65,.4); padding:5px 12px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:700; transition:.2s; }
        .btn-recommend:hover { background:rgba(227,179,65,.1); }
        .btn-recommend.active { background:rgba(227,179,65,.2); border-color:#e3b341; }
        .btn-edit-post { background:transparent; color:#3fb950; border:1px solid rgba(63,185,80,.4); padding:5px 12px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:700; transition:.2s; }
        .btn-edit-post:hover { background:rgba(63,185,80,.1); }
        .btn-edit-post:disabled { color:#6e7681; border-color:#30363d; cursor:not-allowed; }
        .btn-post-del { background:transparent; color:#f85149; border:1px solid rgba(248,81,73,.4); padding:5px 12px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:700; transition:.2s; }
        .btn-post-del:hover { background:rgba(248,81,73,.1); }

        /* 帖子内容 */
        .post-content { font-size:15px; line-height:1.9; margin:24px 0; color:#c9d1d9; min-height:80px; }
        .post-content h1,.post-content h2,.post-content h3 { color:#e6edf3; border-bottom:1px solid #30363d; padding-bottom:8px; }
        .post-content a { color:#58a6ff; }
        .post-content code { background:#1c2128; color:#3fb950; padding:2px 6px; border-radius:4px; font-size:13px; font-family:"Courier New",monospace; }
        .post-content pre { background:#1c2128; border:1px solid #30363d; border-radius:4px; padding:14px; overflow-x:auto; }
        .post-content blockquote { border-left:3px solid #30363d; margin:0; padding:4px 16px; color:#8b949e; }
        .post-content img {
            max-width:240px; max-height:180px; object-fit:cover;
            border-radius:4px; cursor:zoom-in; border:1px solid #30363d;
            transition:.2s; display:inline-block; vertical-align:middle; margin:4px;
        }
        .post-content img:hover { border-color:#3fb950; box-shadow:0 0 12px rgba(63,185,80,.2); }

        /* 灯箱 */
        .img-lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,.88); z-index:9999; align-items:center; justify-content:center; cursor:zoom-out; }
        .img-lightbox.open { display:flex; }
        .img-lightbox img { max-width:92vw; max-height:90vh; object-fit:contain; border-radius:4px; box-shadow:0 8px 40px rgba(0,0,0,.6); animation:lbIn .2s ease-out; }
        @keyframes lbIn { from{transform:scale(.88);opacity:0} to{transform:scale(1);opacity:1} }

        /* 互动按钮 */
        .action-item { font-size:14px; color:#8b949e; transition:.2s; cursor:pointer; display:flex; align-items:center; gap:6px; padding:6px 14px; border:1px solid #30363d; border-radius:4px; background:#1c2128; }
        .action-item:hover { border-color:#3fb950; color:#3fb950; }
        .action-item.active { color:#f85149; border-color:rgba(248,81,73,.4); }
        .action-item.fav.active { color:#e3b341; border-color:rgba(227,179,65,.4); }

        /* 评论区 */
        .comment-section { margin-top:32px; }
        .filter-bar { margin:14px 0; display:flex; gap:12px; font-size:13px; }
        .filter-bar a { text-decoration:none; color:#6e7681; padding-bottom:4px; }
        .filter-bar a.active { color:#3fb950; font-weight:700; border-bottom:2px solid #3fb950; }
        .comment-item { display:flex; gap:12px; padding:16px 0; border-bottom:1px solid #21262d; }
        .comment-item:last-child { border-bottom:none; }
        .is-top-style { background:rgba(227,179,65,.05); border-left:3px solid #e3b341; padding-left:13px; }
        .top-badge { background:rgba(227,179,65,.2); color:#e3b341; padding:1px 6px; border-radius:4px; font-size:10px; margin-right:4px; font-weight:700; font-family:"Courier New",monospace; border:1px solid rgba(227,179,65,.3); }
        .reply-target { color:#58a6ff; margin-left:6px; font-size:12px; }
        .floor-tag  { color:#6e7681; font-size:11px; margin-left:8px; font-family:"Courier New",monospace; }
        .c-avatar   { width:38px; height:38px; border-radius:50%; object-fit:cover; border:1px solid #30363d; }
        .c-body     { flex:1; min-width:0; }
        .c-header   { display:flex; justify-content:space-between; margin-bottom:5px; align-items:center; }
        .c-user     { font-weight:700; color:#3fb950; font-size:13px; text-decoration:none; }
        .c-user:hover { color:#5fdd70; }
        .c-text     { color:#8b949e; font-size:14px; line-height:1.7; margin:4px 0; word-break:break-word; }
        .btn-action { cursor:pointer; font-size:12px; color:#6e7681; margin-left:12px; transition:.15s; }
        .btn-action:hover { color:#3fb950; }
        .like-btn.active { color:#f85149; font-weight:700; }
        .reply-input-box textarea { background:#1c2128; color:#e6edf3; border-color:#30363d; }

        /* 侧边作者卡片 */
        .post-sidebar   { width:260px; position:sticky; top:76px; flex-shrink:0; }
        .author-card    { background:#161b22; border:1px solid #30363d; border-radius:6px; padding:22px 18px; text-align:center; }
        .author-avatar  { width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid #30363d; cursor:pointer; transition:.2s; margin:0 auto; }
        .author-avatar:hover { border-color:#3fb950; box-shadow:0 0 14px rgba(63,185,80,.25); }
        .author-name    { display:block; font-size:16px; font-weight:700; color:#e6edf3; margin:12px 0 8px; text-decoration:none; }
        .author-name:hover { color:#3fb950; }
        .badge-row      { display:flex; justify-content:center; gap:6px; margin-bottom:16px; flex-wrap:wrap; }
        .user-title     { font-size:11px; padding:2px 8px; border-radius:4px; font-weight:700; }
        .user-level     { background:rgba(227,179,65,.15); color:#e3b341; border:1px solid rgba(227,179,65,.3); font-size:11px; padding:2px 8px; border-radius:4px; font-weight:700; font-family:"Courier New",monospace; }
        .stats-row      { display:flex; border-top:1px solid #30363d; padding-top:14px; margin-top:4px; }
        .stat-item      { flex:1; text-align:center; }
        .stat-num       { display:block; font-size:18px; font-weight:700; color:#e6edf3; font-family:"Courier New",monospace; }
        .stat-label     { font-size:11px; color:#6e7681; letter-spacing:.5px; text-transform:uppercase; font-family:"Courier New",monospace; }
        .btn-follow     { width:100%; margin-top:16px; padding:9px; border:none; border-radius:4px; cursor:pointer; font-size:13px; font-weight:700; transition:.2s; }
        .follow-add     { background:#3fb950; color:#fff; }
        .follow-add:hover { background:#2ea043; box-shadow:0 0 12px rgba(63,185,80,.3); }
        .follow-yet     { background:#1c2128; color:#6e7681; border:1px solid #30363d; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <?php
        $main_class = 'post-main';
        if ($post['is_notice'])    $main_class .= ' is-notice';
        if ($post['is_recommend']) $main_class .= ' is-recommend';
    ?>
    <main class="<?= $main_class ?>">

        <?php if ($post['is_notice'] || $post['is_recommend']): ?>
        <div class="notice-top-bar">
            <?php if ($post['is_notice']): ?>
                <span class="top-badge orange">📢 公告</span>
                <span><?= $post['is_recommend'] ? '编辑推荐的重要公告' : '这是一条来自管理员的重要公告，请认真阅读' ?></span>
                <a href="notices.php" style="margin-left:auto; color:#f6a623; text-decoration:none; font-size:12px; font-weight:normal; white-space:nowrap;">查看全部公告 →</a>
            <?php else: ?>
                <span class="top-badge gold">⭐ 编辑推荐</span>
                <span>这篇内容经管理员审核推荐，值得一读</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="post-header">
            <div>
                <?php if(!empty($post['title'])): ?>
                    <h2 style="margin:0;"><?php echo htmlspecialchars($post['title']); ?></h2>
                    <div class="post-meta-line">
                        <a href="profile.php?id=<?php echo $post['author_id']; ?>"><?php echo htmlspecialchars($post['username']); ?></a>
                        · <?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?>
                        · <span style="color:#484f58;">👁 <?= number_format((int)($post['views'] ?? 0)) ?></span>
                    </div>
                <?php else: ?>
                    <h2 style="margin:0;"><?php echo htmlspecialchars($post['username']); ?> 的动态</h2>
                    <div class="post-meta-line"><?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></div>
                <?php endif; ?>
                <?php if (!empty($post['edited_at'])): ?>
                    <div class="edited-at-tag">已编辑 · <?= date('Y-m-d H:i', strtotime($post['edited_at'])) ?></div>
                <?php endif; ?>
            </div>
            <div class="admin-actions">
                <?php if($my_role == 'admin' || $my_role == 'owner'): ?>
                    <button id="rec-btn"
                            class="btn-recommend <?= $post['is_recommend'] ? 'active' : '' ?>"
                            onclick="toggleRecommend(<?= $pid ?>)">
                        <?= $post['is_recommend'] ? '⭐ 已推荐' : '☆ 推荐帖子' ?>
                    </button>
                <?php endif; ?>
                <?php if($post['user_id'] == $my_id): ?>
                    <button id="edit-btn" class="btn-edit-post" onclick="toggleEditMode()">✏️ 编辑</button>
                <?php endif; ?>
                <?php if($post['user_id'] == $my_id || $my_role == 'admin' || $my_role == 'owner'): ?>
                    <button class="btn-post-del" onclick="deletePost(<?php echo $pid; ?>)">删除帖子</button>
                <?php endif; ?>
                <?php if($my_id && $post['user_id'] != $my_id): ?>
                    <button onclick="openReportModal('post',<?= $pid ?>)"
                            style="padding:4px 10px;border-radius:4px;border:1px solid #30363d;background:transparent;
                                   color:#6e7681;font-size:12px;cursor:pointer;font-family:inherit;transition:.15s;"
                            onmouseover="this.style.borderColor='#f85149';this.style.color='#f85149';"
                            onmouseout="this.style.borderColor='#30363d';this.style.color='#6e7681';">
                        举报
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="post-content" id="post-content-view">
            <?php echo format_post_content($post['content'], $conn); ?>
        </div>


        <?php
        // 附件展示
        $att_data = [];
        if (!empty($post['attachments'])) {
            $att_data = json_decode($post['attachments'], true) ?: [];
        }
        if (!empty($att_data)):
        $att_icons = ['pdf'=>'📄','doc'=>'📝','docx'=>'📝','xls'=>'📊','xlsx'=>'📊',
                      'ppt'=>'📊','pptx'=>'📊','zip'=>'🗜️','rar'=>'🗜️','7z'=>'🗜️',
                      'mp4'=>'🎬','mp3'=>'🎵','txt'=>'📃','png'=>'🖼️','jpg'=>'🖼️',
                      'jpeg'=>'🖼️','gif'=>'🖼️','webp'=>'🖼️'];
        ?>
        <div style="margin-top:24px; padding:14px 18px; background:#1c2128; border-radius:4px; border:1px solid #30363d;">
            <div style="font-size:13px; color:#888; font-weight:bold; margin-bottom:12px;">📎 附件 (<?= count($att_data) ?>)</div>
            <?php foreach ($att_data as $att):
                $ext  = strtolower($att['ext'] ?? pathinfo($att['original'] ?? '', PATHINFO_EXTENSION));
                $icon = $att_icons[$ext] ?? '📎';
                $bytes = (int)($att['size'] ?? 0);
                $size_str = $bytes < 1024*1024 ? round($bytes/1024,1).' KB' : round($bytes/1024/1024,1).' MB';
            ?>
            <a href="<?= htmlspecialchars($att['url'] ?? '#') ?>"
               download="<?= htmlspecialchars($att['original'] ?? '') ?>"
               style="display:flex; align-items:center; gap:10px; padding:8px 12px; background:#1c2128; border:1px solid #30363d; border-radius:4px; text-decoration:none; color:#8b949e; margin-bottom:8px; transition:0.2s;"
               onmouseover="this.style.borderColor='#3fb950';this.style.color='#3fb950'"
               onmouseout="this.style.borderColor='#30363d';this.style.color='#8b949e'">
                <span style="font-size:20px;"><?= $icon ?></span>
                <span style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:14px;"><?= htmlspecialchars($att['original'] ?? '') ?></span>
                <span style="font-size:12px; color:#bbb; flex-shrink:0;"><?= $size_str ?></span>
                <span style="font-size:12px; color:#3fb950; flex-shrink:0;">⬇ 下载</span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="post-actions" style="margin-top: 30px; border-top: 1px solid #30363d; padding-top: 20px; display: flex; gap: 12px; flex-wrap: wrap;">
            <div class="action-item <?php echo $post['my_like'] ? 'active' : ''; ?>"
                onclick="togglePostAction('like')" style="cursor:pointer;">
                <span id="like-icon"><?php echo $post['my_like'] ? '❤️' : '🤍'; ?></span> 赞 (<span id="like-count"><?php echo $post['likes_count']; ?></span>)
            </div>

            <div class="action-item <?php echo $post['my_fav'] ? 'active' : ''; ?>"
                onclick="togglePostAction('fav')" style="cursor:pointer;">
                <span id="fav-icon"><?php echo $post['my_fav'] ? '⭐' : '☆'; ?></span> 收藏 (<span id="fav-count"><?php echo $post['favs_count']; ?></span>)
            </div>

            <?php if ($my_id && empty($_SESSION['is_banned'])): ?>
            <div class="action-item" onclick="openRepostModal()" style="cursor:pointer;">
                ↻ 转发
            </div>
            <?php endif; ?>
        </div>

        <div class="comment-section">
            <div style="font-size:11px;font-weight:700;color:#6e7681;letter-spacing:1.5px;text-transform:uppercase;font-family:'Courier New',monospace;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
                <span style="color:#3fb950">//</span> 评论 <span style="color:#3fb950;font-family:'Courier New',monospace;"><?php echo $total_comments; ?></span>
                <span style="flex:1;height:1px;background:#30363d;"></span>
            </div>
            <div style="margin-bottom:20px;">
                <textarea id="main-input" rows="3" placeholder="发表你的看法..."></textarea>
                <div style="text-align:right;margin-top:8px;">
                    <button style="background:#3fb950;color:#fff;border:none;padding:7px 20px;border-radius:4px;cursor:pointer;font-size:13px;font-weight:700;font-family:inherit;transition:.2s;" onmouseover="this.style.background='#2ea043'" onmouseout="this.style.background='#3fb950'" onclick="submitComment(0)">提交评论</button>
                </div>
            </div>

            <div class="filter-bar">
                <a href="?id=<?php echo $pid; ?>&sort=new" class="<?php echo $sort == 'new' ? 'active' : ''; ?>">最新发布</a>
                <a href="?id=<?php echo $pid; ?>&sort=hot" class="<?php echo $sort == 'hot' ? 'active' : ''; ?>">热度优先</a>
            </div>

            <div class="comment-list">
                <?php while($c = $c_res->fetch_assoc()): ?>
                <div class="comment-item <?php echo $c['is_top'] ? 'is-top-style' : ''; ?>" id="comment-<?php echo $c['id']; ?>">
                    <a href="profile.php?id=<?php echo $c['user_id']; ?>">
                        <img src="../uploads/avatars/<?php echo $c['avatar']; ?>" class="c-avatar" onerror="this.onerror=null;this.src='../uploads/avatars/default.png'">
                    </a>
                    <div class="c-body">
                        <div class="c-header">
                            <div>
                                <?php if($c['is_top']): ?><span class="top-badge">置顶</span><?php endif; ?>
                                <a href="profile.php?id=<?php echo $c['user_id']; ?>" class="c-user"><?php echo htmlspecialchars($c['author_name']); ?></a>
                                <?php if($c['target_name']): ?>
                                    <span class="reply-target">回复 @<?php echo htmlspecialchars($c['target_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:12px; color:#bbb; display: flex; align-items: center;">
                                <span>❤️ <?php echo $c['likes']; ?></span>
                                <span class="floor-tag"><?php echo $floor_map[$c['id']]; ?> 楼</span>
                            </div>
                        </div>
                        <p class="c-text"><?php echo nl2br(format_comment($c['content'], $conn)); ?></p>
                        <div class="c-footer">
                            <span style="font-size:12px; color:#999;"><?php echo date('m-d H:i', strtotime($c['created_at'])); ?></span>
                            <span class="btn-action" onclick="toggleReplyBox(<?php echo $c['id']; ?>)">回复</span>
                            <span class="btn-action like-btn <?php echo ($c['my_like'] > 0) ? 'active' : ''; ?>" onclick="likeComment(<?php echo $c['id']; ?>)">点赞</span>
                            <?php if($post['author_id'] == $my_id || $my_role == 'admin'): ?>
                                <span class="btn-action" style="color:#e67e22;" onclick="setTop(<?php echo $c['id']; ?>)"><?php echo $c['is_top'] ? '取消置顶' : '置顶'; ?></span>
                            <?php endif; ?>
                            <?php if($c['user_id'] == $my_id || $my_role == 'admin'): ?>
                                <span class="btn-action" style="color:#ff7675" onclick="deleteComment(<?php echo $c['id']; ?>)">删除</span>
                            <?php endif; ?>
                        </div>
                        <div class="reply-input-box" id="box-<?php echo $c['id']; ?>" style="display:none; margin-top:10px;">
                            <textarea id="input-<?php echo $c['id']; ?>" rows="2" placeholder="回复 @<?php echo htmlspecialchars($c['author_name']); ?>..."></textarea>
                            <div style="text-align:right; margin-top:5px;">
                                <button style="background:#28a745; color:white; border:none; padding:4px 15px; border-radius:4px; cursor:pointer;" onclick="submitComment(<?php echo $c['id']; ?>)">提交</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </main>

    <aside class="post-sidebar">
        <div class="author-card">
            <a href="profile.php?id=<?php echo $post['author_id']; ?>">
                <img src="../uploads/avatars/<?php echo $post['avatar'] ?: 'default.png'; ?>" class="author-avatar">
            </a>
            <a href="profile.php?id=<?php echo $post['author_id']; ?>" class="author-name">
                <?php echo htmlspecialchars($post['username']); ?>
            </a>

            <div class="badge-row">
                <?= get_role_badge($post['role'] ?? 'user', !empty($post['is_banned']), 'font-size:10px;') ?>
                <span class="user-level">Lv.<?php echo $post['level'] ?: 1; ?></span>
            </div>

            <div class="stats-row">
                <div class="stat-item">
                    <span class="stat-num"><?php echo $post['post_count']; ?></span>
                    <span class="stat-label">帖子</span>
                </div>
                <div class="stat-item" style="border-left: 1px solid #eee;">
                    <span class="stat-num" id="fans-count"><?php echo $post['fans_count']; ?></span>
                    <span class="stat-label">粉丝</span>
                </div>
            </div>

            <?php if($post['author_id'] != $my_id): ?>
                <button id="follow-btn"
                        class="btn-follow <?php echo $post['is_following'] ? 'follow-yet' : 'follow-add'; ?>"
                        onclick="toggleFollow(<?php echo $post['author_id']; ?>)">
                    <?php echo $post['is_following'] ? '已关注' : '+ 关注作者'; ?>
                </button>
            <?php else: ?>
                <button class="btn-follow follow-yet" disabled style="cursor: default;">这是你自己</button>
            <?php endif; ?>
        </div>
    </aside>
</div>

<script>
const pid = <?php echo $pid; ?>;
const myId = <?php echo $my_id; ?>;

function toggleFollow(authorId) {
    if(myId === 0) return alert("请先登录");
    let btn = document.getElementById('follow-btn');
    let formData = new FormData();
    formData.append('following_id', authorId);
    fetch('../actions/follow_toggle.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        btn.innerText = (data.status === 'followed') ? '已关注' : '+ 关注作者';
        btn.className = 'btn-follow ' + (data.status === 'followed' ? 'follow-yet' : 'follow-add');
        document.getElementById('fans-count').innerText = data.new_count;
    });
}

function submitComment(parentId) {
    const input = parentId === 0 ? document.getElementById('main-input') : document.getElementById('input-'+parentId);
    const val = input.value.trim();
    if(!val) return alert("内容不能为空");
    let formData = new FormData();
    formData.append('post_id', pid);
    formData.append('parent_id', parentId);
    formData.append('content', val);
    fetch('../actions/comment_save.php', { method: 'POST', body: formData }).then(() => location.reload());
}

function toggleReplyBox(id) {
    const box = document.getElementById('box-' + id);
    box.style.display = (box.style.display === 'block') ? 'none' : 'block';
}

function likeComment(id) {
    let formData = new FormData();
    formData.append('cid', id);
    fetch('../actions/comment_like.php', { method: 'POST', body: formData }).then(() => location.reload());
}

function setTop(cid) {
    fetch('../actions/comment_top.php?cid=' + cid + '&pid=' + pid).then(() => location.reload());
}

function deletePost(id) {
    if(confirm("确定彻底删除该帖子吗？")) {
        fetch('../actions/post_delete.php?id=' + id).then(() => location.href='../index.php');
    }
}

function deleteComment(id) {
    if(confirm("确定删除该评论吗？")) {
        fetch('../actions/comment_delete.php?id=' + id).then(() => location.reload());
    }
}

function toggleRecommend(pid) {
    let formData = new FormData();
    formData.append('pid', pid);
    fetch('../actions/post_recommend.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        }
    });
}

function togglePostAction(type) {
    if(myId === 0) return alert("请先登录");

    let formData = new FormData();
    formData.append('pid', pid);
    formData.append('type', type);

    fetch('../actions/post_action.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            if(type === 'like') {
                document.getElementById('like-icon').innerText = data.active ? '❤️' : '🤍';
                document.getElementById('like-count').innerText = data.new_count;
            } else {
                document.getElementById('fav-icon').innerText = data.active ? '⭐' : '☆';
                document.getElementById('fav-count').innerText = data.new_count;
            }
        }
    });
}

<?php if($post['user_id'] == $my_id):
    $remaining_cooldown = 0;
    if (!empty($post['edited_at'])) {
        $remaining_cooldown = max(0, 600 - (time() - strtotime($post['edited_at'])));
    }
?>
// ── 编辑模式 ──
const postOriginalTitle   = <?= json_encode($post['title'] ?? '') ?>;
const postOriginalContent = <?= json_encode($post['content']) ?>;
let remainingCooldown = <?= $remaining_cooldown ?>;
let editEditor = null;

(function countdown() {
    const btn = document.getElementById('edit-btn');
    if (!btn) return;
    if (remainingCooldown > 0) {
        const m = Math.floor(remainingCooldown / 60);
        const s = remainingCooldown % 60;
        btn.textContent = `冷却 ${m}:${s.toString().padStart(2,'0')}`;
        btn.disabled = true;
        remainingCooldown--;
        setTimeout(countdown, 1000);
    } else {
        btn.textContent = '✏️ 编辑';
        btn.disabled = false;
    }
})();

function loadWangEditor(cb) {
    if (window.wangEditor) { cb(); return; }
    if (!document.querySelector('link[href*="wangeditor"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/@wangeditor/editor@latest/dist/css/style.css';
        document.head.appendChild(link);
    }
    const s = document.createElement('script');
    s.src = 'https://unpkg.com/@wangeditor/editor@latest/dist/index.js';
    s.onload = cb;
    document.head.appendChild(s);
}

function toggleEditMode() {
    const modal = document.getElementById('edit-modal');
    const btn   = document.getElementById('edit-btn');
    if (modal.style.display === 'block') {
        cancelEdit(); return;
    }
    btn.disabled = true;
    btn.textContent = '加载中…';
    loadWangEditor(function() {
        if (!editEditor) {
            const { createEditor, createToolbar } = window.wangEditor;
            editEditor = createEditor({
                selector: '#edit-text-area',
                html: postOriginalContent,
                config: { placeholder: '编辑内容…' },
                mode: 'default'
            });
            createToolbar({ editor: editEditor, selector: '#edit-toolbar', mode: 'default' });
        }
        document.getElementById('edit-title').value = postOriginalTitle;
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        btn.disabled = (remainingCooldown > 0);
        btn.textContent = '✏️ 编辑';
    });
}

function cancelEdit() {
    document.getElementById('edit-modal').style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('edit-btn').textContent = '✏️ 编辑';
}

async function saveEdit() {
    const title   = document.getElementById('edit-title').value.trim();
    const content = editEditor ? editEditor.getHtml() : '';
    if (!title)                              { alert('标题不能为空'); return; }
    if (!editEditor || editEditor.isEmpty()) { alert('内容不能为空'); return; }

    const saveBtn = document.getElementById('save-edit-btn');
    saveBtn.disabled = true; saveBtn.textContent = '保存中…';

    const fd = new FormData();
    fd.append('pid', pid);
    fd.append('title', title);
    fd.append('content', content);

    try {
        const res  = await fetch('../actions/post_edit.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'ok')            { location.reload(); }
        else if (data.status === 'cooldown') { alert(data.msg); cancelEdit(); }
        else                                 { alert('保存失败：' + (data.msg || '未知错误')); saveBtn.disabled = false; saveBtn.textContent = '保存修改'; }
    } catch(e) { alert('网络错误，请重试'); saveBtn.disabled = false; saveBtn.textContent = '保存修改'; }
}
<?php endif; ?>

// ── @提及 自动补全 ──
(function() {
    let dropdown = null, activeTA = null, atPos = -1;

    function getDropdown() {
        if (!dropdown) {
            dropdown = document.createElement('ul');
            dropdown.id = 'mention-dropdown';
            dropdown.style.cssText = 'position:absolute;z-index:9999;background:#fff;border:1px solid #ddd;border-radius:8px;list-style:none;margin:0;padding:4px 0;min-width:180px;box-shadow:0 4px 16px rgba(0,0,0,0.12);display:none;';
            document.body.appendChild(dropdown);
        }
        return dropdown;
    }

    function hideDropdown() {
        if (dropdown) dropdown.style.display = 'none';
        atPos = -1;
    }

    function getCaretCoords(ta) {
        const rect = ta.getBoundingClientRect();
        return { top: rect.bottom + window.scrollY + 4, left: rect.left + window.scrollX };
    }

    function bindTA(ta) {
        if (ta.dataset.mentionBound) return;
        ta.dataset.mentionBound = '1';
        ta.addEventListener('input', onInput);
        ta.addEventListener('keydown', onKeydown);
        ta.addEventListener('blur', function() { setTimeout(hideDropdown, 150); });
    }

    function onInput() {
        activeTA = this;
        const val = this.value, cur = this.selectionStart;
        const seg = val.slice(0, cur);
        const m = seg.match(/@([\w\u4e00-\u9fa5]{0,20})$/u);
        if (!m) { hideDropdown(); return; }
        atPos = seg.lastIndexOf('@');
        const q = m[1];
        fetch('../actions/user_search.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(users => renderDropdown(users));
    }

    function renderDropdown(users) {
        const dd = getDropdown();
        dd.innerHTML = '';
        if (!users.length) { dd.style.display = 'none'; return; }
        users.forEach(u => {
            const li = document.createElement('li');
            li.style.cssText = 'padding:7px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:13px;';
            li.innerHTML = '<img src="../uploads/avatars/' + u.avatar + '" style="width:24px;height:24px;border-radius:50%;object-fit:cover;" onerror="this.src=\'../uploads/avatars/default.png\'">'
                         + '<span>' + u.username + '</span>';
            li.addEventListener('mousedown', function(e) {
                e.preventDefault();
                insertMention(u.username);
            });
            li.addEventListener('mouseover', function() { this.style.background = '#f5f5f5'; });
            li.addEventListener('mouseout',  function() { this.style.background = ''; });
            dd.appendChild(li);
        });
        const coords = getCaretCoords(activeTA);
        dd.style.top  = coords.top  + 'px';
        dd.style.left = coords.left + 'px';
        dd.style.display = 'block';
    }

    function insertMention(username) {
        if (!activeTA || atPos < 0) return;
        const val = activeTA.value;
        const cur = activeTA.selectionStart;
        activeTA.value = val.slice(0, atPos) + '@' + username + ' ' + val.slice(cur);
        const newPos = atPos + username.length + 2;
        activeTA.setSelectionRange(newPos, newPos);
        activeTA.focus();
        hideDropdown();
    }

    function onKeydown(e) {
        if (!dropdown || dropdown.style.display === 'none') return;
        const items = dropdown.querySelectorAll('li');
        const active = dropdown.querySelector('li.hover');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const next = active ? active.nextElementSibling : items[0];
            if (active) active.classList.remove('hover'), active.style.background = '';
            if (next) next.classList.add('hover'), next.style.background = '#f5f5f5';
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prev = active ? active.previousElementSibling : items[items.length - 1];
            if (active) active.classList.remove('hover'), active.style.background = '';
            if (prev) prev.classList.add('hover'), prev.style.background = '#f5f5f5';
        } else if (e.key === 'Enter' && active) {
            e.preventDefault();
            insertMention(active.querySelector('span').textContent);
        } else if (e.key === 'Escape') {
            hideDropdown();
        }
    }

    // 绑定主评论框
    const main = document.getElementById('main-input');
    if (main) bindTA(main);

    // 绑定所有回复框（包括动态生成的）
    document.body.addEventListener('click', function(e) {
        const ta = document.querySelector('textarea:focus');
        if (ta) bindTA(ta);
    });
    document.querySelectorAll('textarea').forEach(bindTA);
})();

// 图片灯箱
(function() {
    const lb = document.createElement('div');
    lb.className = 'img-lightbox';
    lb.innerHTML = '<img id="lb-img" src="">';
    document.body.appendChild(lb);

    document.querySelector('.post-content').addEventListener('click', function(e) {
        if (e.target.tagName === 'IMG') {
            document.getElementById('lb-img').src = e.target.src;
            lb.classList.add('open');
        }
    });

    lb.addEventListener('click', function() { lb.classList.remove('open'); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') lb.classList.remove('open'); });
})();
</script>

<?php if($post['user_id'] == $my_id): ?>
<div id="edit-modal" style="display:none; position:fixed; inset:0; background:#f0f2f5; z-index:2000; overflow-y:auto;">
    <div style="max-width:960px; margin:0 auto; padding:20px 16px 60px;">
        <div style="background:white; border-radius:12px; padding:14px 20px; margin-bottom:16px; display:flex; align-items:center; gap:12px; box-shadow:0 2px 10px rgba(0,0,0,0.06);">
            <button onclick="cancelEdit()" style="background:#f5f5f5;color:#666;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:14px;">← 返回</button>
            <span style="flex:1;font-size:15px;font-weight:bold; color:#333;">编辑帖子</span>
            <button id="save-edit-btn" onclick="saveEdit()" style="background:#28a745;color:white;border:none;padding:8px 22px;border-radius:6px;cursor:pointer;font-size:14px;font-weight:bold;">保存修改</button>
        </div>
        <div style="background:white;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.06);">
            <input type="text" id="edit-title" placeholder="标题"
                   style="width:100%;border:none;outline:none;font-size:22px;font-weight:bold;color:#222;padding:24px 24px 16px;font-family:inherit;border-bottom:1px solid #f5f5f5;box-sizing:border-box;border-radius:12px 12px 0 0;">
            <div id="edit-toolbar"></div>
            <div id="edit-text-area" style="min-height:400px;"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($my_id && $post['user_id'] != $my_id): ?>
<?php include __DIR__ . '/report_modal.php'; ?>
<?php endif; ?>

<?php if ($my_id && empty($_SESSION['is_banned'])): ?>
<?php
require_once __DIR__ . '/../includes/repost_card.php';
$_rc_preview = render_repost_card($conn, $pid, '../');
$_post_title_safe = htmlspecialchars(addslashes($post['title'] ?: ($post['username'] . ' 的分享')), ENT_QUOTES);
// 关注列表（私信只能发给关注的人）
$_dm_following = [];
$_fq = $conn->query("SELECT u.id, u.username, u.avatar FROM follows f JOIN users u ON u.id=f.followed_id WHERE f.follower_id=$my_id ORDER BY u.username ASC");
if ($_fq) while ($_fr = $_fq->fetch_assoc()) $_dm_following[] = $_fr;
?>
<!-- 转发弹窗 -->
<div id="repost-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9400;align-items:center;justify-content:center;">
<div style="background:#161b22;border:1px solid #30363d;border-radius:8px;padding:22px 24px;width:460px;max-width:95vw;font-family:'Microsoft YaHei',sans-serif;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <span style="font-size:14px;font-weight:700;color:#c9d1d9;">↻ 转发帖子</span>
        <button onclick="closeRepostModal()" style="background:none;border:none;color:#6e7681;font-size:18px;cursor:pointer;">✕</button>
    </div>

    <!-- Tab -->
    <div style="display:flex;gap:0;border-bottom:1px solid #30363d;margin-bottom:16px;">
        <button id="rt-tab-moments" onclick="switchRepostTab('moments')"
                style="flex:1;padding:8px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:700;color:#3fb950;border-bottom:2px solid #3fb950;font-family:inherit;">
            发到动态
        </button>
        <button id="rt-tab-dm" onclick="switchRepostTab('dm')"
                style="flex:1;padding:8px;border:none;background:none;cursor:pointer;font-size:13px;color:#6e7681;border-bottom:2px solid transparent;font-family:inherit;">
            发私信
        </button>
    </div>

    <!-- 发到动态 -->
    <div id="rt-panel-moments">
        <textarea id="rt-moments-text" placeholder="说点什么…（选填）" maxlength="500"
                  style="width:100%;box-sizing:border-box;background:#0d1117;border:1px solid #30363d;color:#c9d1d9;
                         padding:9px 12px;border-radius:5px;font-size:13px;font-family:inherit;resize:vertical;
                         min-height:72px;outline:none;margin-bottom:10px;"></textarea>
        <div style="font-size:11px;color:#6e7681;font-family:'Courier New',monospace;margin-bottom:6px;">// 转发内容预览</div>
        <?= $_rc_preview ?>
        <div id="rt-moments-msg" style="font-size:12px;margin-top:8px;min-height:14px;"></div>
        <button onclick="submitRepostMoments()"
                style="width:100%;margin-top:12px;padding:9px;border:none;border-radius:5px;
                       background:#3fb950;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;">
            发布到动态
        </button>
    </div>

    <!-- 发私信 -->
    <div id="rt-panel-dm" style="display:none;">
        <div style="font-size:12px;color:#8b949e;margin-bottom:7px;">选择收件人（我的关注）</div>
        <?php if (empty($_dm_following)): ?>
        <div style="font-size:12px;color:#6e7681;padding:10px 0;">你还没有关注任何人，私信只能发给关注的用户。</div>
        <?php else: ?>
        <div id="rt-dm-list" style="max-height:160px;overflow-y:auto;border:1px solid #30363d;border-radius:5px;margin-bottom:2px;">
            <?php foreach ($_dm_following as $_dmf):
                $dmfAv = htmlspecialchars($_dmf['avatar'] ?: 'default.png');
                $dmfName = htmlspecialchars($_dmf['username']);
                $dmfNameJs = htmlspecialchars(addslashes($_dmf['username']), ENT_QUOTES);
                $dmfAvJs = htmlspecialchars($_dmf['avatar'] ?: 'default.png', ENT_QUOTES);
            ?>
            <div onclick="selectDmUser(<?= (int)$_dmf['id'] ?>,'<?= $dmfNameJs ?>','<?= $dmfAvJs ?>')"
                 style="display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer;transition:.12s;"
                 onmouseover="this.style.background='#21262d'" onmouseout="this.style.background='transparent'">
                <img src="../uploads/avatars/<?= $dmfAv ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover;"
                     onerror="this.onerror=null;this.src='../uploads/avatars/default.png'">
                <span style="font-size:13px;color:#c9d1d9;"><?= $dmfName ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div id="rt-dm-selected" style="display:none;align-items:center;gap:8px;margin-top:8px;
             background:#0f1e12;border:1px solid rgba(63,185,80,.3);border-radius:5px;padding:7px 10px;">
            <img id="rt-dm-sel-av" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">
            <span id="rt-dm-sel-name" style="font-size:13px;color:#3fb950;"></span>
            <span onclick="clearDmUser()" style="margin-left:auto;font-size:11px;color:#6e7681;cursor:pointer;">✕</span>
        </div>
        <textarea id="rt-dm-text" placeholder="附言…（选填）" maxlength="300"
                  style="width:100%;box-sizing:border-box;background:#0d1117;border:1px solid #30363d;color:#c9d1d9;
                         padding:9px 12px;border-radius:5px;font-size:13px;font-family:inherit;resize:vertical;
                         min-height:58px;outline:none;margin-top:10px;margin-bottom:10px;"></textarea>
        <div style="font-size:11px;color:#6e7681;font-family:'Courier New',monospace;margin-bottom:6px;">// 将发送此帖子卡片</div>
        <?= $_rc_preview ?>
        <div id="rt-dm-msg" style="font-size:12px;margin-top:8px;min-height:14px;"></div>
        <button onclick="submitRepostDm()"
                style="width:100%;margin-top:12px;padding:9px;border:none;border-radius:5px;
                       background:#58a6ff;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;">
            发送私信
        </button>
    </div>
</div>
</div>
<script>
const _REPOST_PID = <?= $pid ?>;

function openRepostModal() { document.getElementById('repost-modal').style.display='flex'; }
function closeRepostModal() { document.getElementById('repost-modal').style.display='none'; }
document.getElementById('repost-modal').addEventListener('click', e => { if(e.target===document.getElementById('repost-modal')) closeRepostModal(); });

function switchRepostTab(tab) {
    const isMoments = tab === 'moments';
    document.getElementById('rt-panel-moments').style.display = isMoments ? 'block' : 'none';
    document.getElementById('rt-panel-dm').style.display      = isMoments ? 'none'  : 'block';
    const btnM = document.getElementById('rt-tab-moments');
    const btnD = document.getElementById('rt-tab-dm');
    btnM.style.color = isMoments ? '#3fb950' : '#6e7681';
    btnM.style.borderBottomColor = isMoments ? '#3fb950' : 'transparent';
    btnD.style.color = isMoments ? '#6e7681' : '#58a6ff';
    btnD.style.borderBottomColor = isMoments ? 'transparent' : '#58a6ff';
}

function submitRepostMoments() {
    const text = document.getElementById('rt-moments-text').value.trim();
    const fd = new FormData();
    fd.append('title', '');
    fd.append('content', text);
    fd.append('repost_id', _REPOST_PID);
    fetch('../actions/save.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            const msg = document.getElementById('rt-moments-msg');
            if (d.status === 'ok' || d.status === 'draft') {
                msg.textContent = '已发布到动态！'; msg.style.color = '#3fb950';
                setTimeout(closeRepostModal, 1000);
            } else {
                msg.textContent = d.msg || '发布失败'; msg.style.color = '#f85149';
            }
        });
}

// 私信关注列表
let _dmUid = 0;
function selectDmUser(id, name, av) {
    _dmUid = id;
    const list = document.getElementById('rt-dm-list');
    if (list) list.querySelectorAll('div').forEach(el => el.style.background = 'transparent');
    const sel = document.getElementById('rt-dm-selected');
    sel.style.display = 'flex';
    document.getElementById('rt-dm-sel-av').src = '../uploads/avatars/' + av;
    document.getElementById('rt-dm-sel-name').textContent = name;
}
function clearDmUser() {
    _dmUid = 0;
    document.getElementById('rt-dm-selected').style.display = 'none';
}
function submitRepostDm() {
    if (!_dmUid) { const m=document.getElementById('rt-dm-msg'); m.textContent='请选择收件人'; m.style.color='#f85149'; return; }
    const text = document.getElementById('rt-dm-text').value.trim();
    const fd = new FormData();
    fd.append('to_id', _dmUid);
    fd.append('content', text);
    fd.append('ref_post_id', _REPOST_PID);
    fetch('../actions/message_send.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            const msg = document.getElementById('rt-dm-msg');
            if (d.status === 'success') { msg.textContent='已发送！'; msg.style.color='#3fb950'; setTimeout(closeRepostModal,1000); }
            else { msg.textContent = d.msg||'发送失败'; msg.style.color='#f85149'; }
        });
}
function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
<?php endif; ?>

</body>
</html>
<?php
if ($c_res) $c_res->free();
$conn->close();
?>
