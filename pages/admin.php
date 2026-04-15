<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Content-Type: text/html; charset=utf-8");
    die("<h3>🚫 权限不足</h3>你不是管理员，无法进入后台。 <a href='../index.php'>返回主页</a>");
}

require_once __DIR__ . '/../config.php';

// 确保 approved_by / approved_at 列存在
$conn->query("ALTER TABLE posts ADD COLUMN approved_by INT DEFAULT NULL");
$conn->query("ALTER TABLE posts ADD COLUMN approved_at DATETIME DEFAULT NULL");

$admin_id   = intval($_SESSION['user_id']);
$admin_name = htmlspecialchars($_SESSION['username'] ?? '管理员');

// ── 处理审核 / 删除操作 ──
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id     = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'approve') {
        $now = date('Y-m-d H:i:s');
        $conn->query("UPDATE posts SET status='已发布', approved_by=$admin_id, approved_at='$now' WHERE id=$id");

        // 通知帖子作者
        $pr = $conn->query("SELECT user_id FROM posts WHERE id=$id");
        if ($pr && $row = $pr->fetch_assoc()) {
            $author_id = intval($row['user_id']);
            if ($author_id !== $admin_id) {
                $conn->query("INSERT INTO notifications (user_id, from_user_id, type, post_id, created_at)
                              VALUES ($author_id, $admin_id, 'post_approved', $id, '$now')");
            }
        }
    } elseif ($action === 'delete') {
        $conn->query("DELETE FROM posts WHERE id=$id");
    }

    $redirect_tab = $_GET['tab'] ?? 'all';
    header("Location: admin.php?tab=" . urlencode($redirect_tab));
    exit();
}

// ── 统计 ──
$total   = (int)$conn->query("SELECT COUNT(*) c FROM posts")->fetch_assoc()['c'];
$pending = (int)$conn->query("SELECT COUNT(*) c FROM posts WHERE status='待审核'")->fetch_assoc()['c'];
$pub     = (int)$conn->query("SELECT COUNT(*) c FROM posts WHERE status='已发布'")->fetch_assoc()['c'];
$draft   = (int)$conn->query("SELECT COUNT(*) c FROM posts WHERE status='草稿'")->fetch_assoc()['c'];

// ── 过滤 ──
$tab = $_GET['tab'] ?? 'all';
if ($tab === 'pending')        $where = "WHERE p.status='待审核'";
elseif ($tab === 'published')  $where = "WHERE p.status='已发布'";
elseif ($tab === 'draft')      $where = "WHERE p.status='草稿'";
else                           $where = '';

$sql = "SELECT p.id, p.title, p.content, p.status, p.created_at, p.approved_at,
               u.username AS author_name, u.userid AS author_uid,
               a.username AS approver_name
        FROM posts p
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN users a ON p.approved_by = a.id
        $where
        ORDER BY FIELD(p.status,'待审核','草稿','已发布'), p.id DESC";
$result = $conn->query($sql);
$posts  = [];
if ($result) while ($r = $result->fetch_assoc()) $posts[] = $r;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>内容管理中心</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        .admin-wrap { max-width: 1100px; margin: 24px auto; padding: 0 16px; }

        /* 顶部标题栏 */
        .admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .admin-header h2 { margin: 0; font-size: 14px; font-weight: 700; color: #3fb950; letter-spacing: 1.5px; text-transform: uppercase; font-family: "Courier New", monospace; }
        .admin-header h2::before { content: '// '; opacity: .6; }
        .admin-header .meta { font-size: 12px; color: #6e7681; font-family: "Courier New", monospace; }
        .admin-header .meta a { color: #3fb950; text-decoration: none; }

        /* 统计卡片 */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
        .stat-card { background: #161b22; border: 1px solid #30363d; border-radius: 6px; padding: 16px 18px; }
        .stat-card .num { font-size: 28px; font-weight: 700; line-height: 1.1; font-family: "Courier New", monospace; }
        .stat-card .label { font-size: 11px; color: #6e7681; margin-top: 4px; letter-spacing: .5px; text-transform: uppercase; font-family: "Courier New", monospace; }
        .stat-card.pending .num { color: #f0883e; }
        .stat-card.pub     .num { color: #3fb950; }
        .stat-card.draft   .num { color: #58a6ff; }
        .stat-card.total   .num { color: #e6edf3; }

        /* 过滤 Tab */
        .filter-tabs { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
        .ftab { padding: 6px 16px; border-radius: 4px; font-size: 12px; cursor: pointer;
                border: 1px solid #30363d; background: #161b22; color: #8b949e; text-decoration: none; transition: .2s; font-weight: 600; }
        .ftab:hover { border-color: #3fb950; color: #3fb950; }
        .ftab.active { background: #238636; color: #fff; border-color: rgba(63,185,80,.4); }
        .ftab .badge { display: inline-block; background: rgba(255,255,255,.12); border-radius: 9px;
                       padding: 0 6px; font-size: 10px; margin-left: 4px; }
        .ftab:not(.active) .badge { background: #21262d; color: #6e7681; }

        /* 帖子卡片 */
        .post-card { background: #161b22; border: 1px solid #30363d; border-radius: 6px; padding: 16px 18px; margin-bottom: 10px;
                     display: flex; gap: 16px; align-items: flex-start; transition: border-color .2s; }
        .post-card:hover { border-color: #3fb950; }
        .post-card-body { flex: 1; min-width: 0; }
        .post-card-title { font-size: 14px; font-weight: 600; color: #c9d1d9; margin: 0 0 6px;
                           white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .post-card-title a { color: inherit; text-decoration: none; }
        .post-card-title a:hover { color: #3fb950; }
        .post-card-preview { font-size: 12px; color: #8b949e; line-height: 1.55; display: -webkit-box;
                              -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 10px; }
        .post-card-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; font-size: 11px; color: #6e7681; font-family: "Courier New", monospace; }
        .meta-author { color: #3fb950; font-weight: 600; }
        .meta-dot { color: #30363d; }

        .status-tag { padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 700; white-space: nowrap; font-family: "Courier New", monospace; }
        .status-pending  { background: rgba(240,136,62,.15); color: #f0883e; border: 1px solid rgba(240,136,62,.3); }
        .status-ok       { background: rgba(63,185,80,.15); color: #3fb950; border: 1px solid rgba(63,185,80,.3); }
        .status-draft    { background: rgba(88,166,255,.15); color: #58a6ff; border: 1px solid rgba(88,166,255,.3); }

        .approved-info { font-size: 11px; color: #6e7681; }
        .approved-info strong { color: #3fb950; }

        /* 操作按钮区 */
        .post-card-actions { display: flex; flex-direction: column; gap: 6px; flex-shrink: 0; }
        .btn { padding: 5px 12px; border: none; border-radius: 4px; cursor: pointer;
               text-decoration: none; font-size: 12px; display: inline-block; font-weight: 600;
               transition: opacity .15s; white-space: nowrap; text-align: center; font-family: inherit; }
        .btn:hover { opacity: 0.85; }
        .btn-approve { background: #238636; color: white; border: 1px solid rgba(63,185,80,.4); }
        .btn-view    { background: #21262d; color: #8b949e; border: 1px solid #30363d; }
        .btn-delete  { background: rgba(248,81,73,.15); color: #f85149; border: 1px solid rgba(248,81,73,.3); }

        .empty-state { text-align: center; padding: 60px 20px; background: #161b22; border: 1px solid #30363d; border-radius: 6px;
                       color: #6e7681; font-size: 13px; }
        .empty-state .icon { font-size: 36px; margin-bottom: 10px; }

        @media (max-width: 640px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .post-card { flex-direction: column; }
            .post-card-actions { flex-direction: row; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="admin-wrap">
    <div class="admin-header">
        <h2>🛠️ 内容管理中心</h2>
        <div class="meta">管理员：<strong><?= $admin_name ?></strong> &nbsp;·&nbsp; <a href="../index.php">← 返回主页</a></div>
    </div>

    <!-- 统计卡片 -->
    <div class="stats-row">
        <div class="stat-card pending">
            <div class="num"><?= $pending ?></div>
            <div class="label">⏳ 待审核</div>
        </div>
        <div class="stat-card pub">
            <div class="num"><?= $pub ?></div>
            <div class="label">✅ 已发布</div>
        </div>
        <div class="stat-card draft">
            <div class="num"><?= $draft ?></div>
            <div class="label">📝 草稿</div>
        </div>
        <div class="stat-card total">
            <div class="num"><?= $total ?></div>
            <div class="label">📊 总计</div>
        </div>
    </div>

    <!-- 过滤 Tab -->
    <div class="filter-tabs">
        <a href="admin.php?tab=all"       class="ftab <?= $tab==='all'       ? 'active' : '' ?>">全部<span class="badge"><?= $total ?></span></a>
        <a href="admin.php?tab=pending"   class="ftab <?= $tab==='pending'   ? 'active' : '' ?>">待审核<span class="badge"><?= $pending ?></span></a>
        <a href="admin.php?tab=published" class="ftab <?= $tab==='published' ? 'active' : '' ?>">已发布<span class="badge"><?= $pub ?></span></a>
        <a href="admin.php?tab=draft"     class="ftab <?= $tab==='draft'     ? 'active' : '' ?>">草稿<span class="badge"><?= $draft ?></span></a>
    </div>

    <!-- 帖子列表 -->
    <?php if (empty($posts)): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <p>暂无帖子</p>
        </div>
    <?php else: ?>
        <?php foreach ($posts as $p):
            if ($p['status'] === '待审核')     $status_cls = 'status-pending';
            elseif ($p['status'] === '已发布') $status_cls = 'status-ok';
            else                               $status_cls = 'status-draft';
            $plain_preview = mb_substr(strip_tags($p['content']), 0, 120);
            $ts = strtotime($p['created_at']);
            $diff = time() - $ts;
            if ($diff < 60)        $time_str = '刚刚';
            elseif ($diff < 3600)  $time_str = floor($diff/60).' 分钟前';
            elseif ($diff < 86400) $time_str = floor($diff/3600).' 小时前';
            else                   $time_str = date('m-d H:i', $ts);
        ?>
        <div class="post-card">
            <div class="post-card-body">
                <div class="post-card-title">
                    <a href="post.php?id=<?= $p['id'] ?>" target="_blank"><?= htmlspecialchars($p['title']) ?></a>
                </div>
                <div class="post-card-preview"><?= htmlspecialchars($plain_preview) ?><?= mb_strlen(strip_tags($p['content'])) > 120 ? '…' : '' ?></div>
                <div class="post-card-meta">
                    <span class="status-tag <?= $status_cls ?>"><?= $p['status'] ?></span>
                    <span class="meta-dot">·</span>
                    <span class="meta-author"><?= htmlspecialchars($p['author_name'] ?? '未知') ?></span>
                    <?php if (!empty($p['author_uid'])): ?>
                        <span style="color:#bbb;">@<?= htmlspecialchars($p['author_uid']) ?></span>
                    <?php endif; ?>
                    <span class="meta-dot">·</span>
                    <span><?= $time_str ?></span>
                    <span class="meta-dot">·</span>
                    <span>ID: <?= $p['id'] ?></span>
                    <?php if ($p['status'] === '已发布' && !empty($p['approver_name'])): ?>
                        <span class="meta-dot">·</span>
                        <span class="approved-info">✅ 由 <strong><?= htmlspecialchars($p['approver_name']) ?></strong> 审核通过
                            <?php if ($p['approved_at']): ?>
                                · <?= date('m-d H:i', strtotime($p['approved_at'])) ?>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="post-card-actions">
                <?php if ($p['status'] === '待审核'): ?>
                    <a href="admin.php?action=approve&id=<?= $p['id'] ?>&tab=<?= urlencode($tab) ?>"
                       class="btn btn-approve"
                       onclick="return confirm('确认通过审核并发布吗？')">✅ 通过</a>
                <?php endif; ?>
                <a href="post.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-view">👁 查看</a>
                <a href="admin.php?action=delete&id=<?= $p['id'] ?>&tab=<?= urlencode($tab) ?>"
                   class="btn btn-delete"
                   onclick="return confirm('确认永久删除这条数据吗？')">🗑 删除</a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
<?php $conn->close(); ?>
