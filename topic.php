<?php
/**
 * topic.php — 话题（标签）聚合页
 *
 * 功能：根据 ?tag= 参数展示指定话题标签下的所有关联帖子；
 *       话题不存在或参数为空时重定向至首页。
 * 读写表：topics、posts、post_topics
 * 权限：无
 */
session_start();
require_once __DIR__ . '/config.php';

$tag = trim($_GET['tag'] ?? '');
if ($tag === '') {
    header("Location: index.php");
    exit;
}

$safe_tag = $conn->real_escape_string($tag);

$topic_res = $conn->query("SELECT id, name, use_count FROM topics WHERE name='$safe_tag'");
$topic = ($topic_res && $topic_res->num_rows > 0) ? $topic_res->fetch_assoc() : null;

$posts = [];
if ($topic) {
    $tid = (int)$topic['id'];
    $pr = $conn->query("
        SELECT p.id, p.title, p.content, p.created_at, p.is_recommend, p.is_notice,
               u.id as author_id, u.username, u.avatar,
               (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes_count,
               (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
        FROM post_topics pt
        JOIN posts p ON pt.post_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE pt.topic_id = $tid AND p.status = '已发布'
        ORDER BY p.id DESC
    ");
    if ($pr) while ($r = $pr->fetch_assoc()) $posts[] = $r;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>
    <style>
        * { box-sizing: border-box; }

        .topic-hero {
            background: 
            border-bottom: 1px solid 
            padding: 40px 0 32px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .topic-hero::before {
            content:'';
            position:absolute; inset:0;
            background-image:
                linear-gradient(rgba(63,185,80,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(63,185,80,.04) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events:none;
        }
        .topic-hero::after {
            content:'';
            position:absolute; left:50%; top:50%; transform:translate(-50%,-50%);
            width:400px; height:200px;
            background: radial-gradient(ellipse, rgba(63,185,80,.08) 0%, transparent 70%);
            pointer-events:none;
        }
        .topic-hero h1 { font-size: 26px; margin: 0 0 6px; color: 
        .topic-hero .tag-symbol { font-size: 32px; color: 
        .topic-hero .use-count { font-size: 12px; color: 

        .page-layout { max-width: 860px; margin: 24px auto; padding: 0 15px; }
        @media(max-width:600px){
            .page-layout { margin: 12px auto; padding: 0 10px; }
            .topic-hero h1 { font-size: 20px; }
        }

        .section-title {
            font-size: 11px; font-weight: 700; color: 
            text-transform: uppercase; font-family: "Courier New", monospace;
            margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
        }
        .section-title::before { content: '//'; opacity: .6; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: 

        .post-card {
            background: 
            margin-bottom: 10px;
            padding: 18px 20px;
            border-radius: 6px;
            border: 1px solid 
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        .post-card:hover { border-color: 
        .post-card.is-recommend { border-left: 3px solid 
        .post-card.is-notice    { border-left: 3px solid 

        .card-badges { display: flex; gap: 6px; margin-bottom: 8px; }
        .card-badge { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 3px; font-family: "Courier New", monospace; letter-spacing: .5px; }
        .card-badge.recommend { background: rgba(227,179,65,.15); color: 
        .card-badge.notice    { background: rgba(240,136,62,.15); color: 

        .post-title   { font-size: 15px; font-weight: 600; color: 
        .post-excerpt { color: 
                        overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .post-meta    { font-size: 11px; color: 
        .author-info  { color: 

        .empty-tip { text-align: center; padding: 60px 0; color: 
        .empty-tip a { color: 
        .back-link { display: inline-block; margin-bottom: 16px; color: 
        .back-link:hover { color: 
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="topic-hero">
    <div class="tag-symbol">
    <h1><?= htmlspecialchars($tag) ?></h1>
    <?php if ($topic): ?>
    <div class="use-count"><?= (int)$topic['use_count'] ?> 篇相关帖子</div>
    <?php endif; ?>
</div>

<div class="page-layout">
    <a href="index.php" class="back-link">← 返回首页</a>

    <?php if (empty($posts)): ?>
    <div class="empty-tip">
        <p>该话题下还没有帖子</p>
        <a href="pages/publish.php">发布第一篇 
    </div>
    <?php else: ?>
    <div class="section-title">
    <?php foreach ($posts as $p):
        $excerpt = mb_substr(strip_tags($p['content']), 0, 100);
    ?>
    <a href="pages/post.php?id=<?= $p['id'] ?>" class="post-card<?= $p['is_recommend'] ? ' is-recommend' : '' ?><?= $p['is_notice'] ? ' is-notice' : '' ?>">
        <?php if ($p['is_recommend'] || $p['is_notice']): ?>
        <div class="card-badges">
            <?php if ($p['is_notice']): ?>
            <span class="card-badge notice">📢 公告</span>
            <?php endif; ?>
            <?php if ($p['is_recommend']): ?>
            <span class="card-badge recommend">⭐ 推荐</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="post-title"><?= htmlspecialchars($p['title']) ?></div>
        <?php if ($excerpt): ?>
        <div class="post-excerpt"><?= htmlspecialchars($excerpt) ?></div>
        <?php endif; ?>
        <div class="post-meta">
            <span class="author-info"><?= htmlspecialchars($p['username']) ?></span>
            <span>❤️ <?= (int)$p['likes_count'] ?> &nbsp; 💬 <?= (int)$p['comment_count'] ?> &nbsp; <?= date('m-d', strtotime($p['created_at'])) ?></span>
        </div>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
<?php $conn->close(); ?>
