<?php
session_start();
// --- 1. 数据库连接与配置 ---
require_once __DIR__ . '/../config.php';

// --- 2. 获取参数与身份 ---
$my_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$pid = isset($_GET['id']) ? intval($_GET['id']) : 0;
$my_role = $_SESSION['role'] ?? 'user';
$sort = $_GET['sort'] ?? 'new';

// --- 3. 获取帖子主体及作者详细信息 ---
$post_query = "SELECT p.*,
                u.id as author_id, u.username, u.avatar, u.role, u.level,
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
        body { background: #f4f7f6; font-family: "Microsoft YaHei", sans-serif; margin: 0; color: #333; }
        .container { max-width: 1100px; margin: 20px auto; display: flex; gap: 20px; padding: 0 15px; align-items: flex-start; }
        .post-main { flex: 1; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .post-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; }

        /* ── 公告帖特殊样式 ── */
        .post-main.is-notice {
            border-top: 4px solid #f6a623;
            box-shadow: 0 2px 16px rgba(246,166,35,0.18);
        }
        .notice-top-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #fff8ee, #fff3e0);
            border: 1px solid #fde8c4;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #a0621a;
            font-weight: bold;
        }
        .notice-top-bar .top-badge {
            color: white;
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: bold;
            letter-spacing: 1px;
            flex-shrink: 0;
        }
        .notice-top-bar .top-badge.orange { background: linear-gradient(135deg, #f6a623, #e8821a); }
        .notice-top-bar .top-badge.gold   { background: linear-gradient(135deg, #f6c90e, #e6a817); }
        .post-main.is-notice .post-header { border-bottom-color: #fde8c4; }
        .post-main.is-notice h2           { color: #c47010; }

        /* ── 推荐帖特殊样式 ── */
        .post-main.is-recommend {
            border-top: 4px solid #f6c90e;
            box-shadow: 0 2px 16px rgba(246,201,14,0.2);
        }
        .post-main.is-recommend .post-header { border-bottom-color: #ffe69c; }
        .post-main.is-recommend h2           { color: #8a6d00; }

        /* ── 管理员操作按钮 ── */
        .admin-actions { display: flex; gap: 10px; align-items: center; }
        .btn-recommend {
            background: white;
            color: #b8860b;
            border: 1px solid #f6c90e;
            padding: 6px 14px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: 0.2s;
        }
        .btn-recommend:hover { background: #fffbea; }
        .btn-recommend.active { background: #f6c90e; color: white; border-color: #f6c90e; }
        .btn-post-del { background: #ff7675; color: white; border: none; padding: 6px 15px; border-radius: 4px; cursor: pointer; }
        .post-content { font-size: 16px; line-height: 1.8; margin: 25px 0; min-height: 100px; }
        .post-content img {
            max-width: 240px;
            max-height: 180px;
            object-fit: cover;
            border-radius: 6px;
            cursor: zoom-in;
            border: 1px solid #eee;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-block;
            vertical-align: middle;
            margin: 4px;
        }
        .post-content img:hover { transform: scale(1.03); box-shadow: 0 4px 14px rgba(0,0,0,0.15); }
        .img-lightbox {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.82);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            cursor: zoom-out;
        }
        .img-lightbox.open { display: flex; }
        .img-lightbox img {
            max-width: 92vw;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 6px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.5);
            animation: lbIn 0.2s ease-out;
        }
        @keyframes lbIn { from { transform: scale(0.85); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .btn-edit-post { background: white; color: #28a745; border: 1px solid #28a745; padding: 6px 14px; border-radius: 4px; cursor: pointer; font-size: 13px; transition: 0.2s; }
        .btn-edit-post:hover { background: #f0fff4; }
        .btn-edit-post:disabled { color: #bbb; border-color: #ddd; cursor: not-allowed; background: white; }
        .edited-at-tag { font-size: 12px; color: #bbb; margin-top: 3px; font-style: italic; }
        .post-sidebar { width: 280px; position: sticky; top: 80px; }
        .author-card { background: white; padding: 25px 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; }
        .author-avatar { width: 85px; height: 85px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); cursor: pointer; transition: 0.3s; }
        .author-avatar:hover { transform: scale(1.05); }
        .author-name { display: block; font-size: 18px; font-weight: bold; color: #333; margin: 12px 0 8px; text-decoration: none; }
        .author-name:hover { color: #28a745; }
        .badge-row { display: flex; justify-content: center; gap: 8px; margin-bottom: 20px; }
        .user-title { background: #e3f2fd; color: #1976d2; font-size: 12px; padding: 2px 10px; border-radius: 4px; }
        .user-level { background: #fff3e0; color: #ef6c00; font-size: 12px; padding: 2px 10px; border-radius: 4px; font-weight: bold; }
        .stats-row { display: flex; border-top: 1px solid #f8f8f8; padding-top: 15px; margin-top: 5px; }
        .stat-item { flex: 1; text-align: center; }
        .stat-num { display: block; font-size: 16px; font-weight: bold; color: #333; }
        .stat-label { font-size: 12px; color: #999; }
        .btn-follow { width: 100%; margin-top: 20px; padding: 10px; border: none; border-radius: 25px; cursor: pointer; font-size: 14px; font-weight: bold; transition: 0.3s; }
        .follow-add { background: #28a745; color: white; }
        .follow-add:hover { background: #218838; }
        .follow-yet { background: #f0f0f0; color: #888; }
        .comment-section { margin-top: 40px; }
        .filter-bar { margin: 15px 0; display: flex; gap: 15px; font-size: 14px; }
        .filter-bar a { text-decoration: none; color: #999; }
        .filter-bar a.active { color: #28a745; font-weight: bold; border-bottom: 2px solid #28a745; }
        textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; resize: none; font-family: inherit; }
        .comment-item { display: flex; gap: 12px; padding: 20px 15px; border-bottom: 1px solid #f2f2f2; }
        .is-top-style { background: #fffdf5; border-left: 4px solid #f1c40f; }
        .top-badge { background: #f1c40f; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-right: 5px; }
        .reply-target { color: #1976d2; margin-left: 5px; font-size: 13px; font-weight: normal; }
        .floor-tag { color: #bbb; font-size: 12px; margin-left: 10px; }
        .c-avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; }
        .c-body { flex: 1; }
        .c-header { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .c-user { font-weight: bold; color: #444; font-size: 14px; text-decoration: none; }
        .c-user:hover { color: #28a745; }
        .btn-action { cursor: pointer; font-size: 12px; color: #999; margin-left: 15px; transition: 0.2s; }
        .btn-action:hover { color: #28a745; }
        .like-btn.active { color: #ff7675; font-weight: bold; }
        .action-item { font-size: 16px; color: #666; transition: 0.3s; }
        .action-item.active { color: #ff7675; font-weight: bold; }
        .action-item.active #fav-icon { color: #f1c40f; }
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
                    <div style="font-size: 13px; color: #999; margin-top: 6px;">
                        <a href="profile.php?id=<?php echo $post['author_id']; ?>" style="color:#28a745; text-decoration:none;"><?php echo htmlspecialchars($post['username']); ?></a>
                        · 发布于：<?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?>
                    </div>
                <?php else: ?>
                    <h2 style="margin:0;"><?php echo htmlspecialchars($post['username']); ?> 的动态</h2>
                    <div style="font-size: 13px; color: #999; margin-top: 8px;">
                        发布于：<?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($post['edited_at'])): ?>
                    <div class="edited-at-tag">已编辑 · <?= date('Y-m-d H:i', strtotime($post['edited_at'])) ?></div>
                <?php endif; ?>
            </div>
            <div class="admin-actions">
                <?php if($my_role == 'admin'): ?>
                    <button id="rec-btn"
                            class="btn-recommend <?= $post['is_recommend'] ? 'active' : '' ?>"
                            onclick="toggleRecommend(<?= $pid ?>)">
                        <?= $post['is_recommend'] ? '⭐ 已推荐' : '☆ 推荐帖子' ?>
                    </button>
                <?php endif; ?>
                <?php if($post['user_id'] == $my_id): ?>
                    <button id="edit-btn" class="btn-edit-post" onclick="toggleEditMode()">✏️ 编辑</button>
                <?php endif; ?>
                <?php if($post['user_id'] == $my_id || $my_role == 'admin'): ?>
                    <button class="btn-post-del" onclick="deletePost(<?php echo $pid; ?>)">删除帖子</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="post-content" id="post-content-view">
            <?php echo $post['content']; ?>
        </div>

        <?php if($post['user_id'] == $my_id): ?>
        <div id="edit-container" style="display:none; margin-top:16px; border:1px solid #eee; border-radius:8px; overflow:hidden;">
            <input type="text" id="edit-title" value="<?= htmlspecialchars($post['title'] ?? '') ?>"
                   style="width:100%; border:none; outline:none; font-size:20px; font-weight:bold; color:#222; padding:18px 20px 14px; font-family:inherit; border-bottom:1px solid #f0f0f0; box-sizing:border-box;">
            <div id="edit-toolbar" style="border-bottom:1px solid #f0f0f0; padding:0 8px;"></div>
            <div id="edit-text-area" style="min-height:280px; padding:4px 12px;"></div>
            <div style="display:flex; gap:10px; padding:14px 20px; justify-content:flex-end; border-top:1px solid #f0f0f0; background:#fafafa;">
                <button onclick="cancelEdit()" style="background:#eee;color:#666;border:none;padding:8px 22px;border-radius:6px;cursor:pointer;font-size:14px;">取消</button>
                <button onclick="saveEdit()" style="background:#28a745;color:white;border:none;padding:8px 22px;border-radius:6px;cursor:pointer;font-size:14px;font-weight:bold;">保存修改</button>
            </div>
        </div>
        <?php endif; ?>

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
        <div style="margin-top:24px; padding:16px 20px; background:#fafafa; border-radius:8px; border:1px solid #eee;">
            <div style="font-size:13px; color:#888; font-weight:bold; margin-bottom:12px;">📎 附件 (<?= count($att_data) ?>)</div>
            <?php foreach ($att_data as $att):
                $ext  = strtolower($att['ext'] ?? pathinfo($att['original'] ?? '', PATHINFO_EXTENSION));
                $icon = $att_icons[$ext] ?? '📎';
                $bytes = (int)($att['size'] ?? 0);
                $size_str = $bytes < 1024*1024 ? round($bytes/1024,1).' KB' : round($bytes/1024/1024,1).' MB';
            ?>
            <a href="<?= htmlspecialchars($att['url'] ?? '#') ?>"
               download="<?= htmlspecialchars($att['original'] ?? '') ?>"
               style="display:flex; align-items:center; gap:10px; padding:8px 12px; background:white; border:1px solid #eee; border-radius:6px; text-decoration:none; color:#333; margin-bottom:8px; transition:0.2s;"
               onmouseover="this.style.borderColor='#28a745';this.style.color='#28a745'"
               onmouseout="this.style.borderColor='#eee';this.style.color='#333'">
                <span style="font-size:20px;"><?= $icon ?></span>
                <span style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:14px;"><?= htmlspecialchars($att['original'] ?? '') ?></span>
                <span style="font-size:12px; color:#bbb; flex-shrink:0;"><?= $size_str ?></span>
                <span style="font-size:12px; color:#28a745; flex-shrink:0;">⬇ 下载</span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="post-actions" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; display: flex; gap: 20px;">
            <div class="action-item <?php echo $post['my_like'] ? 'active' : ''; ?>"
                onclick="togglePostAction('like')" style="cursor:pointer;">
                <span id="like-icon"><?php echo $post['my_like'] ? '❤️' : '🤍'; ?></span> 赞 (<span id="like-count"><?php echo $post['likes_count']; ?></span>)
            </div>

            <div class="action-item <?php echo $post['my_fav'] ? 'active' : ''; ?>"
                onclick="togglePostAction('fav')" style="cursor:pointer;">
                <span id="fav-icon"><?php echo $post['my_fav'] ? '⭐' : '☆'; ?></span> 收藏 (<span id="fav-count"><?php echo $post['favs_count']; ?></span>)
            </div>
        </div>

        <div class="comment-section">
            <h3 style="margin-bottom:10px;">评论互动 (<?php echo $total_comments; ?>)</h3>
            <div style="margin-bottom: 20px;">
                <textarea id="main-input" rows="3" placeholder="友善评论，文明发言..."></textarea>
                <div style="text-align:right; margin-top:10px;">
                    <button style="background:#28a745; color:white; border:none; padding:8px 25px; border-radius:20px; cursor:pointer;" onclick="submitComment(0)">发表评论</button>
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
                        <p class="c-text"><?php echo nl2br(htmlspecialchars($c['content'])); ?></p>
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
                <?php if ($post['role'] === 'admin'): ?>
                    <span class="user-title" style="background: #fff0f0; color: #d63031; border: 1px solid #ff7675;">管理员</span>
                <?php else: ?>
                    <span class="user-title" style="background: #e3f2fd; color: #1976d2;">普通用户</span>
                <?php endif; ?>
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

<?php if($post['user_id'] == $my_id): ?>
<script src="https://unpkg.com/@wangeditor/editor@latest/dist/index.js"></script>
<?php endif; ?>
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

function toggleEditMode() {
    const container = document.getElementById('edit-container');
    const view      = document.getElementById('post-content-view');
    if (container.style.display === 'block') {
        cancelEdit();
    } else {
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
        container.style.display = 'block';
        view.style.display = 'none';
        document.getElementById('edit-btn').textContent = '← 取消编辑';
    }
}

function cancelEdit() {
    document.getElementById('edit-container').style.display = 'none';
    document.getElementById('post-content-view').style.display = '';
    document.getElementById('edit-btn').textContent = '✏️ 编辑';
}

async function saveEdit() {
    const title   = document.getElementById('edit-title').value.trim();
    const content = editEditor ? editEditor.getHtml() : '';
    if (!title)                              { alert('标题不能为空'); return; }
    if (!editEditor || editEditor.isEmpty()) { alert('内容不能为空'); return; }

    const fd = new FormData();
    fd.append('pid', pid);
    fd.append('title', title);
    fd.append('content', content);

    try {
        const res  = await fetch('../actions/post_edit.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'ok')       { location.reload(); }
        else if (data.status === 'cooldown') { alert(data.msg); cancelEdit(); }
        else                            { alert('保存失败：' + (data.msg || '未知错误')); }
    } catch(e) { alert('网络错误，请重试'); }
}
<?php endif; ?>

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
</body>
</html>
<?php
if ($c_res) $c_res->free();
$conn->close();
?>
