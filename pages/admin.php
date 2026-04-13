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
        body { font-family: "Microsoft YaHei", sans-serif; background: #f0f2f5; margin: 0; padding-bottom: 60px; }

        .admin-wrap { max-width: 1100px; margin: 24px auto; padding: 0 16px; }

        /* 顶部标题栏 */
        .admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .admin-header h2 { margin: 0; font-size: 22px; color: #1a1a1a; }
        .admin-header .meta { font-size: 13px; color: #888; }
        .admin-header .meta a { color: #28a745; text-decoration: none; }

        /* 统计卡片 */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 22px; }
        .stat-card { background: white; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 6px rgba(0,0,0,0.06); }
        .stat-card .num { font-size: 32px; font-weight: 700; line-height: 1.1; }
        .stat-card .label { font-size: 13px; color: #888; margin-top: 4px; }
        .stat-card.pending .num { color: #e67e22; }
        .stat-card.pub     .num { color: #27ae60; }
        .stat-card.draft   .num { color: #3498db; }
        .stat-card.total   .num { color: #2c3e50; }

        /* 过滤 Tab */
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; }
        .ftab { padding: 7px 18px; border-radius: 20px; font-size: 13px; cursor: pointer;
                border: 1px solid #ddd; background: white; color: #555; text-decoration: none; transition: 0.2s; }
        .ftab:hover { border-color: #28a745; color: #28a745; }
        .ftab.active { background: #28a745; color: white; border-color: #28a745; font-weight: 600; }
        .ftab .badge { display: inline-block; background: rgba(255,255,255,0.35); border-radius: 9px;
                       padding: 0 6px; font-size: 11px; margin-left: 4px; }
        .ftab:not(.active) .badge { background: #f1f1f1; color: #888; }

        /* 帖子卡片 */
        .post-card { background: white; border-radius: 12px; padding: 18px 20px; margin-bottom: 12px;
                     box-shadow: 0 1px 6px rgba(0,0,0,0.06); display: flex; gap: 16px; align-items: flex-start; }
        .post-card-body { flex: 1; min-width: 0; }
        .post-card-title { font-size: 15px; font-weight: 600; color: #1a1a1a; margin: 0 0 6px;
                           white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .post-card-title a { color: inherit; text-decoration: none; }
        .post-card-title a:hover { color: #28a745; }
        .post-card-preview { font-size: 13px; color: #777; line-height: 1.55; display: -webkit-box;
                              -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 10px; }
        .post-card-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; font-size: 12px; color: #999; }
        .meta-author { color: #27ae60; font-weight: 600; }
        .meta-dot { color: #ddd; }

        .status-tag { padding: 2px 9px; border-radius: 10px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .status-pending  { background: #fff3cd; color: #d9770a; }
        .status-ok       { background: #d4edda; color: #198754; }
        .status-draft    { background: #dbeafe; color: #1d6fcc; }

        .approved-info { font-size: 12px; color: #aaa; }
        .approved-info strong { color: #27ae60; }

        /* 操作按钮区 */
        .post-card-actions { display: flex; flex-direction: column; gap: 7px; flex-shrink: 0; }
        .btn { padding: 6px 14px; border: none; border-radius: 7px; cursor: pointer;
               text-decoration: none; font-size: 13px; display: inline-block; font-weight: 500;
               transition: opacity 0.15s; white-space: nowrap; text-align: center; }
        .btn:hover { opacity: 0.85; }
        .btn-approve { background: #28a745; color: white; }
        .btn-view    { background: #e9ecef; color: #333; }
        .btn-delete  { background: #dc3545; color: white; }

        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 12px;
                       color: #aaa; font-size: 14px; box-shadow: 0 1px 6px rgba(0,0,0,0.06); }
        .empty-state .icon { font-size: 48px; margin-bottom: 10px; }

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
