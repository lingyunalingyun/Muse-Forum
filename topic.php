<?php
/**
 * topic.php — 话题页（#标签聚合）
 *
 * GET 参数：tag（话题名称，如 "原神"）
 * 通过 post_topics 关联表展示使用了该 #标签 的所有已发布帖子
 * 读表：topics, post_topics, posts, users, post_likes, comments
 */
session_start();
require_once __DIR__ . '/config.php';

$tag = trim($_GET['tag'] ?? '');
if ($tag === '') {
    header("Location: index.php");
    exit;
}

$safe_tag = $conn->real_escape_string($tag);

// 话题信息
$topic_res = $conn->query("SELECT id, name, use_count FROM topics WHERE name='$safe_tag'");
$topic = ($topic_res && $topic_res->num_rows > 0) ? $topic_res->fetch_assoc() : null;

// 该话题下的帖子
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
    <title>#<?= htmlspecialchars($tag) ?> - 话题 - 缪斯 MUSE</title>
    <style>
        * { box-sizing: border-box; }

        .topic-hero {
            background: #0d1117;
            border-bottom: 1px solid #30363d;
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
        .topic-hero h1 { font-size: 26px; margin: 0 0 6px; color: #e6edf3; font-family: "Courier New", monospace; position: relative; z-index: 1; }
        .topic-hero .tag-symbol { font-size: 32px; color: #3fb950; line-height: 1; margin-bottom: 4px; position: relative; z-index: 1; text-shadow: 0 0 20px rgba(63,185,80,.5); }
        .topic-hero .use-count { font-size: 12px; color: #6e7681; margin-top: 6px; font-family: "Courier New", monospace; letter-spacing: .5px; position: relative; z-index: 1; }

        .page-layout { max-width: 860px; margin: 24px auto; padding: 0 15px; }
        @media(max-width:600px){
            .page-layout { margin: 12px auto; padding: 0 10px; }
            .topic-hero h1 { font-size: 20px; }
        }

        .section-title {
            font-size: 11px; font-weight: 700; color: #3fb950; letter-spacing: 1.5px;
            text-transform: uppercase; font-family: "Courier New", monospace;
            margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
        }
        .section-title::before { content: '//'; opacity: .6; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: #21262d; }

        .post-card {
            background: #161b22;
            margin-bottom: 10px;
            padding: 18px 20px;
            border-radius: 6px;
            border: 1px solid #30363d;
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        .post-card:hover { border-color: #3fb950; box-shadow: 0 0 0 1px #3fb950; }
        .post-card.is-recommend { border-left: 3px solid #e3b341; }
        .post-card.is-notice    { border-left: 3px solid #f0883e; }

        .card-badges { display: flex; gap: 6px; margin-bottom: 8px; }
        .card-badge { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 3px; font-family: "Courier New", monospace; letter-spacing: .5px; }
        .card-badge.recommend { background: rgba(227,179,65,.15); color: #e3b341; border: 1px solid rgba(227,179,65,.3); }
        .card-badge.notice    { background: rgba(240,136,62,.15); color: #f0883e; border: 1px solid rgba(240,136,62,.3); }

        .post-title   { font-size: 15px; font-weight: 600; color: #c9d1d9; margin-bottom: 8px; }
        .post-excerpt { color: #8b949e; font-size: 13px; line-height: 1.6; margin-bottom: 10px;
                        overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .post-meta    { font-size: 11px; color: #6e7681; display: flex; justify-content: space-between; align-items: center; font-family: "Courier New", monospace; }
        .author-info  { color: #3fb950; font-weight: 600; }

        .empty-tip { text-align: center; padding: 60px 0; color: #6e7681; font-size: 14px; }
        .empty-tip a { color: #3fb950; text-decoration: none; }
        .back-link { display: inline-block; margin-bottom: 16px; color: #6e7681; text-decoration: none; font-size: 12px; font-family: "Courier New", monospace; transition: color .2s; }
        .back-link:hover { color: #e6edf3; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="topic-hero">
    <div class="tag-symbol">#</div>
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
        <a href="pages/publish.php">发布第一篇 #<?= htmlspecialchars($tag) ?> 帖子</a>
    </div>
    <?php else: ?>
    <div class="section-title"># <?= htmlspecialchars($tag) ?> · <?= count($posts) ?> 篇帖子</div>
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
