<?php
session_start();
require_once __DIR__ . '/config.php';

$keyword = trim($_GET['keyword'] ?? '');
$results = [];
$total = 0;

if ($keyword !== '') {
    $safe_kw = $conn->real_escape_string($keyword);
    $sql = "SELECT p.id, p.title, p.content, p.created_at, u.username
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.status = '已发布'
              AND (p.title LIKE '%$safe_kw%' OR p.content LIKE '%$safe_kw%')
            ORDER BY p.id DESC";
    $res = $conn->query($sql);
    if ($res) {
        $total = $res->num_rows;
        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= $keyword ? htmlspecialchars($keyword) . ' - 搜索结果' : '搜索' ?></title>
    <style>
        .search-wrap { max-width: 800px; margin: 30px auto; padding: 0 15px; }
        @media(max-width:600px){ .search-wrap { margin: 16px auto; padding: 0 10px; } }
        .search-bar { background: #161b22; border: 1px solid #30363d; border-radius: 6px; padding: 16px 20px; margin-bottom: 16px; }
        .search-bar form { display: flex; gap: 8px; }
        .search-bar input { flex: 1; padding: 10px 14px; background: #0d1117; border: 1px solid #30363d; border-radius: 4px; font-size: 14px; color: #e6edf3; outline: none; font-family: "Courier New", monospace; }
        .search-bar input:focus { border-color: #3fb950; box-shadow: 0 0 0 3px rgba(63,185,80,.15); }
        .search-bar input::placeholder { color: #484f58; }
        .search-bar button { background: #3fb950; color: #fff; border: 1px solid rgba(63,185,80,.4); padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 700; white-space: nowrap; font-family: inherit; transition: .2s; }
        .search-bar button:hover { background: #2ea043; box-shadow: 0 0 12px rgba(63,185,80,.3); }
        .result-info { font-size: 12px; color: #6e7681; margin-bottom: 14px; padding: 0 2px; font-family: "Courier New", monospace; }
        .result-info strong { color: #3fb950; }
        .post-card { background: #161b22; padding: 18px 20px; border-radius: 6px; border: 1px solid #30363d; margin-bottom: 10px; cursor: pointer; transition: border-color .2s, box-shadow .2s; }
        .post-card:hover { border-color: #3fb950; box-shadow: 0 0 0 1px #3fb950; }
        .post-title { font-size: 15px; font-weight: 600; color: #c9d1d9; margin-bottom: 8px; }
        .post-title mark { background: rgba(63,185,80,.25); color: #3fb950; border-radius: 2px; padding: 0 2px; }
        .post-excerpt { color: #8b949e; font-size: 13px; line-height: 1.6; margin-bottom: 10px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .post-meta { font-size: 11px; color: #6e7681; display: flex; justify-content: space-between; font-family: "Courier New", monospace; }
        .author { color: #3fb950; font-weight: 600; }
        .empty-state { text-align: center; padding: 60px 20px; background: #161b22; border: 1px solid #30363d; border-radius: 6px; }
        .empty-state p { color: #6e7681; margin-top: 8px; font-size: 13px; }
        .empty-state .icon { font-size: 36px; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="search-wrap">

    <div class="search-bar">
        <form action="search.php" method="GET" style="display:flex; gap:10px; width:100%;">
            <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="输入关键词搜索帖子标题或内容...">
            <button type="submit">🔍 搜索</button>
        </form>
    </div>

    <?php if ($keyword !== ''): ?>
        <div class="result-info">
            关键词 "<strong><?= htmlspecialchars($keyword) ?></strong>" 共找到 <strong><?= $total ?></strong> 条结果
        </div>

        <?php if ($total > 0): ?>
            <?php foreach ($results as $row):
                $clean = strip_tags($row['content']);
                $excerpt = mb_substr($clean, 0, 120) . (mb_strlen($clean) > 120 ? '...' : '');
                $display_title = !empty($row['title']) ? $row['title'] : $row['username'] . ' 的分享';
                // 高亮关键词
                $hl = htmlspecialchars($keyword);
                $safe_title = htmlspecialchars($display_title);
                $hi_title = preg_replace('/(' . preg_quote($hl, '/') . ')/iu', '<mark>$1</mark>', $safe_title);
            ?>
            <div class="post-card" onclick="location.href='pages/post.php?id=<?= $row['id'] ?>'">
                <div class="post-title"><?= $hi_title ?></div>
                <div class="post-excerpt"><?= htmlspecialchars($excerpt) ?></div>
                <div class="post-meta">
                    <span>作者：<span class="author"><?= htmlspecialchars($row['username']) ?></span></span>
                    <span><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size:48px;">🔍</div>
                <p>没有找到与 "<?= htmlspecialchars($keyword) ?>" 相关的帖子</p>
                <p>试试其他关键词？</p>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <div style="font-size:48px;">🔍</div>
            <p>输入关键词开始搜索</p>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
<?php $conn->close(); ?>
