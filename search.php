<?php
/**
 * search.php — 全站搜索页
 *
 * 功能：根据关键词搜索帖子标题/内容和用户名，结果以"帖子"和"用户"两个 Tab 分别展示；
 *       支持通过 ?keyword= 和 ?tab= 参数控制搜索词与当前激活 Tab。
 * 读写表：posts、users
 * 权限：无
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

        .search-bar { background:
        .search-bar form { display:flex; gap:8px; }
        .search-bar input { flex:1; padding:10px 14px; background:
        .search-bar input:focus { border-color:
        .search-bar input::placeholder { color:
        .search-bar button { background:
        .search-bar button:hover { background:

        .tab-bar { display:flex; gap:4px; margin-bottom:14px; }
        .tab-btn {
            padding:7px 20px; border-radius:4px; font-size:13px; font-weight:600;
            border:1px solid 
            text-decoration:none; transition:.15s; font-family:inherit;
        }
        .tab-btn:hover { border-color:
        .tab-btn.active { background:rgba(63,185,80,.12); border-color:

        .result-info { font-size:12px; color:
        .result-info strong { color:

        
        .post-card { background:
        .post-card:hover { border-color:
        .post-title { font-size:15px; font-weight:600; color:
        .post-title mark { background:rgba(63,185,80,.25); color:
        .post-excerpt { color:
        .post-meta { font-size:11px; color:
        .author { color:

        
        .user-card { background:
        .user-card:hover { border-color:
        .user-card img { width:46px; height:46px; border-radius:50%; object-fit:cover; border:1px solid 
        .user-card-info { flex:1; min-width:0; }
        .user-card-top { display:flex; align-items:center; gap:8px; margin-bottom:4px; flex-wrap:wrap; }
        .user-card-name { font-size:15px; font-weight:700; color:
        .role-badge { font-size:11px; font-weight:700; padding:2px 7px; border-radius:3px; letter-spacing:.3px; }
        .user-card-mid { font-size:12px; color:
        .user-card-mid span { color:

        .empty-state { text-align:center; padding:60px 20px; background:
        .empty-state p { color:
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
