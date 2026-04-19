<?php

session_start();
require_once __DIR__ . '/config.php';

$cats = [];

$has_cat_col = false;
$cc = $conn->query("SHOW COLUMNS FROM posts LIKE 'category_id'");
if ($cc && $cc->num_rows > 0) $has_cat_col = true;

if ($has_cat_col) {
    $cr = $conn->query("
        SELECT c.*, COUNT(p.id) as post_count
        FROM categories c
        LEFT JOIN posts p ON p.category_id = c.id AND p.status = '已发布'
        GROUP BY c.id
        ORDER BY c.sort_order ASC, c.id ASC
    ");
} else {
    $cr = $conn->query("SELECT *, 0 as post_count FROM categories ORDER BY sort_order ASC, id ASC");
}
if ($cr) while ($c = $cr->fetch_assoc()) $cats[] = $c;
$db_err = !$cr ? $conn->error : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>分区 - 缪斯 MUSE</title>
    <style>
        /* ── 页头 ── */
        .cat-hero {
            background: #0d1117;
            border-bottom: 1px solid #30363d;
            padding: 32px 0 26px;
            position: relative; overflow: hidden;
        }
        .cat-hero::before {
            content:''; position:absolute; inset:0;
            background-image:
                linear-gradient(rgba(63,185,80,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(63,185,80,.04) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .cat-hero-inner {
            max-width: 1200px; margin: 0 auto; padding: 0 24px;
            position: relative; z-index: 1;
        }
        .cat-hero h1 {
            font-size: 24px; font-weight: 700; color: #e6edf3;
            font-family: "Courier New", monospace; margin: 0 0 4px;
        }
        .cat-hero h1 span { color: #3fb950; }
        .cat-hero p { font-size: 13px; color: #6e7681; margin: 0; font-family: "Courier New", monospace; }

        /* ── 游戏库网格 ── */
        .lib-wrap {
            max-width: 1200px; margin: 32px auto; padding: 0 24px;
        }
        .lib-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }
        @media(max-width:900px)  { .lib-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; } }
        @media(max-width:500px)  { .lib-grid { grid-template-columns: 1fr; gap: 12px; } .lib-wrap { padding: 0 12px; margin-top: 20px; } }

        /* ── 分区卡片 ── */
        .lib-card {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            aspect-ratio: 16 / 10;
            cursor: pointer;
            text-decoration: none;
            display: block;
            background: #161b22;
            border: 1px solid #30363d;
            transition: transform .25s cubic-bezier(.2,.8,.2,1), box-shadow .25s;
        }
        .lib-card:hover {
            transform: translateY(-6px) scale(1.01);
            box-shadow: 0 16px 48px rgba(0,0,0,.6), 0 0 0 1px var(--cc, #3fb950);
        }

        /* 封面图 */
        .lib-cover {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform .35s cubic-bezier(.2,.8,.2,1);
        }
        .lib-card:hover .lib-cover { transform: scale(1.06); }

        /* 无封面占位 */
        .lib-placeholder {
            position: absolute; inset: 0;
            background: linear-gradient(135deg, #161b22 0%, #1c2128 100%);
            background-image:
                linear-gradient(rgba(63,185,80,.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(63,185,80,.05) 1px, transparent 1px);
            background-size: 28px 28px;
            display: flex; align-items: center; justify-content: center;
        }
        .lib-placeholder-icon {
            font-size: 52px;
            opacity: .35;
            filter: drop-shadow(0 0 16px var(--cc, #3fb950));
        }

        /* 底部渐变遮罩 */
        .lib-card::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(
                to bottom,
                transparent 25%,
                rgba(13,17,23,.55) 55%,
                rgba(13,17,23,.95) 100%
            );
            pointer-events: none;
        }

        /* 左侧彩色竖条 */
        .lib-stripe {
            position: absolute; left: 0; top: 0; bottom: 0;
            width: 4px;
            background: var(--cc, #3fb950);
            z-index: 3;
            opacity: .9;
        }

        /* 底部文字 */
        .lib-body {
            position: absolute; bottom: 0; left: 0; right: 0;
            padding: 18px 18px 16px;
            z-index: 3;
        }
        .lib-name {
            font-size: 20px; font-weight: 700; color: #e6edf3;
            line-height: 1.25; margin-bottom: 5px;
            text-shadow: 0 1px 6px rgba(0,0,0,.8);
            overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
        }
        .lib-desc {
            font-size: 12px; color: rgba(230,237,243,.55);
            margin-bottom: 8px; line-height: 1.5;
            overflow: hidden; display: -webkit-box;
            -webkit-line-clamp: 1; -webkit-box-orient: vertical;
        }
        .lib-footer {
            display: flex; align-items: center; gap: 10px;
        }
        .lib-count {
            font-size: 12px; font-family: "Courier New", monospace;
            color: var(--cc, #3fb950); font-weight: 700;
        }
        .lib-enter {
            margin-left: auto;
            font-size: 11px; font-family: "Courier New", monospace;
            color: rgba(230,237,243,.4);
            opacity: 0; transform: translateX(-6px);
            transition: opacity .2s, transform .2s;
        }
        .lib-card:hover .lib-enter {
            opacity: 1; transform: translateX(0);
        }

        /* 空状态 */
        .empty-tip {
            text-align: center; padding: 80px 0;
            color: #6e7681; font-size: 14px;
            font-family: "Courier New", monospace;
        }
        .empty-tip a { color: #3fb950; text-decoration: none; }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="cat-hero">
    <div class="cat-hero-inner">
        <h1>// <span>分区</span></h1>
        <p>选择一个分区，开始探索</p>
    </div>
</div>

<div class="lib-wrap">
    <?php if ($db_err): ?>
    <div class="empty-tip" style="color:#f85149;">数据库错误：<?= htmlspecialchars($db_err) ?><br><a href="pages/admin_categories.php" style="color:#3fb950;">→ 进入管理页面自动修复</a></div>
    <?php elseif (empty($cats)): ?>
    <div class="empty-tip">
        <p>// 还没有分区</p>
        <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'owner'])): ?>
        <a href="pages/admin_categories.php">→ 创建第一个分区</a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="lib-grid">
        <?php foreach ($cats as $c):
            $color = htmlspecialchars($c['color'] ?: '#3fb950');
            $cover = !empty($c['cover_image']) ? htmlspecialchars($c['cover_image']) : null;
        ?>
        <a href="square.php?cat=<?= $c['id'] ?>" class="lib-card" style="--cc:<?= $color ?>">
            <div class="lib-stripe"></div>

            <?php if ($cover): ?>
                <img class="lib-cover" src="<?= $cover ?>" alt=""
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div class="lib-placeholder" style="display:none;">
                    <span class="lib-placeholder-icon"><?= htmlspecialchars($c['icon'] ?: '#') ?></span>
                </div>
            <?php else: ?>
                <div class="lib-placeholder">
                    <span class="lib-placeholder-icon"><?= htmlspecialchars($c['icon'] ?: '#') ?></span>
                </div>
            <?php endif; ?>

            <div class="lib-body">
                <div class="lib-name"><?= htmlspecialchars($c['name']) ?></div>
                <?php if (!empty($c['description'])): ?>
                <div class="lib-desc"><?= htmlspecialchars($c['description']) ?></div>
                <?php endif; ?>
                <div class="lib-footer">
                    <span class="lib-count"><?= (int)$c['post_count'] ?> 篇帖子</span>
                    <span class="lib-enter">进入分区 →</span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
<?php $conn->close(); ?>
