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
        body { background: #f4f7f6; font-family: "Microsoft YaHei", sans-serif; margin: 0; padding-bottom: 40px; }
        .search-wrap { max-width: 800px; margin: 30px auto; padding: 0 15px; }
        .search-bar { background: white; padding: 20px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; gap: 10px; }
        .search-bar input { flex: 1; padding: 12px 16px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; outline: none; }
        .search-bar input:focus { border-color: #28a745; }
        .search-bar button { background: #28a745; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-size: 15px; white-space: nowrap; }
        .search-bar button:hover { background: #218838; }
        .result-info { font-size: 14px; color: #999; margin-bottom: 15px; padding: 0 5px; }
        .result-info strong { color: #28a745; }
        .post-card { background: white; padding: 20px; border-radius: 8px; border: 1px solid #eee; margin-bottom: 15px; cursor: pointer; transition: all 0.3s; }
        .post-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.08); border-color: #28a745; }
        .post-title { font-size: 17px; font-weight: bold; color: #333; margin-bottom: 8px; }
        .post-title mark { background: #fff3cd; color: #333; border-radius: 2px; padding: 0 2px; }
        .post-excerpt { color: #666; font-size: 14px; line-height: 1.6; margin-bottom: 12px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .post-meta { font-size: 12px; color: #999; display: flex; justify-content: space-between; }
        .author { color: #28a745; font-weight: bold; }
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 10px; }
        .empty-state p { color: #999; margin-top: 10px; }
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
