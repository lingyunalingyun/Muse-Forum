<?php
/**
 * search.php — 全站搜索页
 *
 * 功能：按关键词搜索帖子或用户（支持按 MID 精确查找）
 * 读写表：posts、users（只读）
 * 权限：公开
 */
session_start();
require_once __DIR__ . '/config.php';

$keyword = trim($_GET['keyword'] ?? '');
$tab     = in_array($_GET['tab'] ?? '', ['users']) ? 'users' : 'posts';

$post_results = [];
$user_results = [];
$total = 0;

if ($keyword !== '') {
    $safe_kw = $conn->real_escape_string($keyword);

    if ($tab === 'users') {
        $is_mid = preg_match('/^\d{8}$/', $keyword);
        if ($is_mid) {
            $sql = "SELECT id, username, avatar, role, level FROM users WHERE mid='$safe_kw' OR username LIKE '%$safe_kw%' ORDER BY id ASC LIMIT 30";
        } else {
            $sql = "SELECT id, username, avatar, role, level FROM users WHERE username LIKE '%$safe_kw%' ORDER BY id ASC LIMIT 30";
        }
        $res = $conn->query($sql);
        if ($res) {
            $total = $res->num_rows;
            while ($row = $res->fetch_assoc()) $user_results[] = $row;
        }
    } else {
        $sql = "SELECT p.id, p.title, p.content, p.created_at, u.username
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.status = '已发布'
                  AND (p.title LIKE '%$safe_kw%' OR p.content LIKE '%$safe_kw%')
                ORDER BY p.id DESC";
        $res = $conn->query($sql);
        if ($res) {
            $total = $res->num_rows;
            while ($row = $res->fetch_assoc()) $post_results[] = $row;
        }
    }
}

$role_styles = [
    'owner'   => ['label'=>'★ 站长', 'color'=>'#f85149','bg'=>'rgba(248,81,73,.15)',  'border'=>'rgba(248,81,73,.4)'],
    'admin'   => ['label'=>'管理员',  'color'=>'#a78bfa','bg'=>'rgba(167,139,250,.15)','border'=>'rgba(167,139,250,.4)'],
    'sponsor' => ['label'=>'赞助者',  'color'=>'#3fb950','bg'=>'rgba(63,185,80,.15)',  'border'=>'rgba(63,185,80,.4)'],
    'user'    => ['label'=>'成员',    'color'=>'#58a6ff','bg'=>'rgba(88,166,255,.15)', 'border'=>'rgba(88,166,255,.4)'],
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= $keyword ? htmlspecialchars($keyword) . ' - 搜索' : '搜索' ?> - <?= SITE_NAME ?></title>
    <style>
        .search-wrap { max-width:800px; margin:30px auto; padding:0 16px; }
        @media(max-width:600px){ .search-wrap { margin:16px auto; padding:0 10px; } }

        .search-bar { background:#161b22; border:1px solid #30363d; border-radius:6px; padding:16px 20px; margin-bottom:12px; }
        .search-bar form { display:flex; gap:8px; }
        .search-bar input { flex:1; padding:10px 14px; background:#0d1117; border:1px solid #30363d; border-radius:4px; font-size:14px; color:#e6edf3; outline:none; font-family:"Courier New",monospace; }
        .search-bar input:focus { border-color:#3fb950; box-shadow:0 0 0 3px rgba(63,185,80,.15); }
        .search-bar input::placeholder { color:#484f58; }
        .search-bar button { background:#3fb950; color:#fff; border:1px solid rgba(63,185,80,.4); padding:10px 20px; border-radius:4px; cursor:pointer; font-size:13px; font-weight:700; white-space:nowrap; font-family:inherit; transition:.2s; }
        .search-bar button:hover { background:#2ea043; box-shadow:0 0 12px rgba(63,185,80,.3); }

        .tab-bar { display:flex; gap:4px; margin-bottom:14px; }
        .tab-btn {
            padding:7px 20px; border-radius:4px; font-size:13px; font-weight:600;
            border:1px solid #30363d; background:#161b22; color:#8b949e;
            text-decoration:none; transition:.15s; font-family:inherit;
        }
        .tab-btn:hover { border-color:#3fb950; color:#3fb950; }
        .tab-btn.active { background:rgba(63,185,80,.12); border-color:#3fb950; color:#3fb950; }

        .result-info { font-size:12px; color:#6e7681; margin-bottom:14px; padding:0 2px; font-family:"Courier New",monospace; }
        .result-info strong { color:#3fb950; }

        /* 帖子卡片 */
        .post-card { background:#161b22; padding:18px 20px; border-radius:6px; border:1px solid #30363d; margin-bottom:10px; cursor:pointer; transition:border-color .2s; }
        .post-card:hover { border-color:#3fb950; }
        .post-title { font-size:15px; font-weight:600; color:#c9d1d9; margin-bottom:8px; }
        .post-title mark { background:rgba(63,185,80,.25); color:#3fb950; border-radius:2px; padding:0 2px; }
        .post-excerpt { color:#8b949e; font-size:13px; line-height:1.6; margin-bottom:10px; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
        .post-meta { font-size:11px; color:#6e7681; display:flex; justify-content:space-between; font-family:"Courier New",monospace; }
        .author { color:#3fb950; font-weight:600; }

        /* 用户卡片 */
        .user-card { background:#161b22; border:1px solid #30363d; border-radius:6px; padding:14px 18px; margin-bottom:10px; display:flex; align-items:center; gap:14px; text-decoration:none; transition:border-color .2s; }
        .user-card:hover { border-color:#3fb950; }
        .user-card img { width:46px; height:46px; border-radius:50%; object-fit:cover; border:1px solid #30363d; flex-shrink:0; }
        .user-card-info { flex:1; min-width:0; }
        .user-card-top { display:flex; align-items:center; gap:8px; margin-bottom:4px; flex-wrap:wrap; }
        .user-card-name { font-size:15px; font-weight:700; color:#e6edf3; }
        .role-badge { font-size:11px; font-weight:700; padding:2px 7px; border-radius:3px; letter-spacing:.3px; }
        .user-card-mid { font-size:12px; color:#6e7681; font-family:"Courier New",monospace; }
        .user-card-mid span { color:#3fb950; letter-spacing:1px; }

        .empty-state { text-align:center; padding:60px 20px; background:#161b22; border:1px solid #30363d; border-radius:6px; }
        .empty-state p { color:#6e7681; margin-top:8px; font-size:13px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="search-wrap">

    <div class="search-bar">
        <form action="search.php" method="GET" style="display:flex;gap:8px;width:100%;">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>"
                   placeholder="<?= $tab === 'users' ? '搜索用户名或输入 8 位 MID…' : '搜索帖子标题或内容…' ?>">
            <button type="submit">搜索</button>
        </form>
    </div>

    <div class="tab-bar">
        <a href="search.php?tab=posts<?= $keyword ? '&keyword='.urlencode($keyword) : '' ?>" class="tab-btn <?= $tab==='posts' ? 'active' : '' ?>">帖子</a>
        <a href="search.php?tab=users<?= $keyword ? '&keyword='.urlencode($keyword) : '' ?>" class="tab-btn <?= $tab==='users' ? 'active' : '' ?>">用户</a>
    </div>

    <?php if ($keyword !== ''): ?>
        <div class="result-info">
            <?= $tab === 'users' ? '用户' : '帖子' ?> 关键词 "<strong><?= htmlspecialchars($keyword) ?></strong>" 找到 <strong><?= $total ?></strong> 条结果
        </div>

        <?php if ($tab === 'posts'): ?>
            <?php if ($post_results): foreach ($post_results as $row):
                $clean   = strip_tags($row['content']);
                $excerpt = mb_substr($clean, 0, 120) . (mb_strlen($clean) > 120 ? '…' : '');
                $title   = !empty($row['title']) ? $row['title'] : $row['username'] . ' 的分享';
                $hl      = htmlspecialchars($keyword);
                $hi_title = preg_replace('/(' . preg_quote($hl, '/') . ')/iu', '<mark>$1</mark>', htmlspecialchars($title));
            ?>
            <div class="post-card" onclick="location.href='pages/post.php?id=<?= $row['id'] ?>'">
                <div class="post-title"><?= $hi_title ?></div>
                <div class="post-excerpt"><?= htmlspecialchars($excerpt) ?></div>
                <div class="post-meta">
                    <span>作者：<span class="author"><?= htmlspecialchars($row['username']) ?></span></span>
                    <span><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></span>
                </div>
            </div>
            <?php endforeach; else: ?>
                <div class="empty-state"><div style="font-size:48px;">🔍</div><p>没有找到相关帖子</p></div>
            <?php endif; ?>

        <?php else: ?>
            <?php if ($user_results): foreach ($user_results as $u):
                $rs  = $role_styles[$u['role']] ?? $role_styles['user'];
                $av  = htmlspecialchars($u['avatar'] ?: 'default.png');
                $lvl = (int)($u['level'] ?? 1);
                $level_names = [1=>'新手',2=>'学徒',3=>'成员',4=>'精英',5=>'大师',6=>'传奇'];
            ?>
            <a class="user-card" href="pages/profile.php?id=<?= $u['id'] ?>">
                <img src="uploads/avatars/<?= $av ?>" onerror="this.onerror=null;this.src='uploads/avatars/default.png'">
                <div class="user-card-info">
                    <div class="user-card-top">
                        <span class="user-card-name"><?= htmlspecialchars($u['username']) ?></span>
                        <span class="role-badge" style="color:<?= $rs['color'] ?>;background:<?= $rs['bg'] ?>;border:1px solid <?= $rs['border'] ?>;"><?= $rs['label'] ?></span>
                        <span style="font-size:11px;color:#6e7681;font-family:'Courier New',monospace;">Lv.<?= $lvl ?> <?= $level_names[$lvl] ?? '' ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; else: ?>
                <div class="empty-state"><div style="font-size:48px;">👤</div><p>没有找到相关用户</p><?php if (preg_match('/^\d{8}$/', $keyword)): ?><p style="font-size:12px;margin-top:4px;">MID <?= htmlspecialchars($keyword) ?> 不存在</p><?php endif; ?></div>
            <?php endif; ?>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <div style="font-size:48px;">🔍</div>
            <p><?= $tab === 'users' ? '输入用户名或 8 位 MID 搜索用户' : '输入关键词搜索帖子' ?></p>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
<?php $conn->close(); ?>
