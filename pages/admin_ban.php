<?php
/**
 * admin_ban.php — 封禁管理后台
 *
 * 功能：展示被封用户列表，支持快速解封，筛选限时/永久封禁
 * 读写表：读写 users
 * 权限：admin / owner
 */

session_start();

$my_role = $_SESSION['role'] ?? '';
if (!in_array($my_role, ['admin', 'owner'])) {
    header('Content-Type: text/html; charset=utf-8');
    die('<h3>🚫 权限不足</h3> <a href="../index.php">返回主页</a>');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exp_helper.php';

ensure_user_columns($conn);

$my_id   = intval($_SESSION['user_id']);
$my_name = htmlspecialchars($_SESSION['username'] ?? '');
$now     = date('Y-m-d H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unban') {
    header('Content-Type: application/json');
    $tid = intval($_POST['user_id'] ?? 0);
    if (!$tid) { echo json_encode(['ok' => false, 'msg' => '参数错误']); exit; }
    $tr = $conn->query("SELECT role FROM users WHERE id=$tid");
    if (!$tr || $tr->num_rows === 0) { echo json_encode(['ok' => false, 'msg' => '用户不存在']); exit; }
    $t = $tr->fetch_assoc();
    if ($my_role === 'admin' && !in_array($t['role'], ['user', 'sponsor'])) {
        echo json_encode(['ok' => false, 'msg' => '无权操作该账号']);
        exit;
    }
    $conn->query("UPDATE users SET is_banned=0, ban_reason=NULL, ban_until=NULL, banned_by=NULL WHERE id=$tid");
    echo json_encode(['ok' => true]);
    exit;
}

$conn->query("UPDATE users SET is_banned=0, ban_reason=NULL, ban_until=NULL, banned_by=NULL
              WHERE is_banned=1 AND ban_until IS NOT NULL AND ban_until <= '$now'");

$filter = $_GET['filter'] ?? 'all';  
$where  = "WHERE u.is_banned = 1";
if ($filter === 'timed') $where .= " AND u.ban_until IS NOT NULL";
if ($filter === 'perm')  $where .= " AND u.ban_until IS NULL";

$res = $conn->query(
    "SELECT u.id, u.username, u.mid, u.avatar, u.role, u.ban_reason, u.ban_until,
            b.username AS banned_by_name
     FROM users u
     LEFT JOIN users b ON b.id = u.banned_by
     $where
     ORDER BY u.id DESC"
);
$banned = [];
if ($res) while ($r = $res->fetch_assoc()) $banned[] = $r;
$total = count($banned);

function ban_label(array $r): string {
    if ($r['ban_until'] === null) return '<span style="color:#f85149;font-weight:700;">永久</span>';
    return '<span style="color:#f0883e;">' . date('Y-m-d', strtotime($r['ban_until'])) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>封禁管理 — 后台</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { background: 

        .wrap { max-width: 1000px; margin: 28px auto; padding: 0 16px; }

        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; flex-wrap: wrap; gap: 8px; }
        .page-title { margin: 0; font-size: 13px; font-weight: 700; color: 
        .page-title::before { content: '// '; opacity: .5; }
        .page-meta { font-size: 12px; color: 
        .page-meta a { color: 

        
        .stats { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
        .stat-pill { padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 700;
                     font-family: "Courier New", monospace; border: 1px solid; cursor: pointer; text-decoration: none; transition: .15s; }
        .stat-pill.all    { color: 
        .stat-pill.timed  { color: 
        .stat-pill.perm   { color: 
        .stat-pill.active, .stat-pill:hover { filter: brightness(1.2); }
        .stat-pill.active { outline: 1px solid currentColor; }

        
        .ban-list { display: flex; flex-direction: column; gap: 8px; }
        .ban-card {
            background: 
            padding: 14px 16px; display: flex; align-items: center; gap: 14px;
            transition: border-color .15s;
        }
        .ban-card:hover { border-color: rgba(248,81,73,.4); }
        .ban-avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 1px solid 
        .ban-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
        .ban-name { font-size: 14px; font-weight: 700; color: 
        .ban-mid  { font-size: 11px; color: 
        .ban-reason { font-size: 12px; color: 
        .ban-reason strong { color: 
        .ban-footer { display: flex; align-items: center; gap: 12px; font-size: 11px; color: 
                      font-family: "Courier New", monospace; flex-wrap: wrap; margin-top: 3px; }

        .until-tag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 3px;
                     font-size: 11px; font-weight: 700; font-family: "Courier New", monospace; }
        .until-perm  { background: rgba(248,81,73,.1); color: 
        .until-timed { background: rgba(240,136,62,.1); color: 

        .btn-unban {
            padding: 6px 16px; border-radius: 4px; border: 1px solid rgba(63,185,80,.4);
            background: rgba(63,185,80,.08); color: 
            cursor: pointer; font-family: inherit; white-space: nowrap; transition: .15s; flex-shrink: 0;
        }
        .btn-unban:hover { background: rgba(63,185,80,.18); }

        .profile-link { color: 
        .profile-link:hover { text-decoration: underline; }

        .empty-state { text-align: center; padding: 60px 20px; background: 
                       border-radius: 8px; color: 
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="wrap">
    <div class="page-header">
        <h2 class="page-title">封禁管理</h2>
        <div class="page-meta"><?= $my_name ?> &nbsp;·&nbsp; <a href="../index.php">← 返回主页</a></div>
    </div>

    <!-- 筛选 -->
    <div class="stats">
        <a href="admin_ban.php?filter=all"   class="stat-pill all   <?= $filter==='all'   ? 'active' : '' ?>">全部 <?= $total ?></a>
        <a href="admin_ban.php?filter=timed" class="stat-pill timed <?= $filter==='timed' ? 'active' : '' ?>">限时封禁</a>
        <a href="admin_ban.php?filter=perm"  class="stat-pill perm  <?= $filter==='perm'  ? 'active' : '' ?>">永久封禁</a>
    </div>

    <?php if (empty($banned)): ?>
    <div class="empty-state">当前没有被封禁的用户 ✓</div>
    <?php else: ?>
    <div class="ban-list">
        <?php foreach ($banned as $u): ?>
        <?php
        $isPerm = $u['ban_until'] === null;
        $until_label = $isPerm
            ? '<span class="until-tag until-perm">永久封禁</span>'
            : '<span class="until-tag until-timed">⏱ 至 ' . date('Y-m-d', strtotime($u['ban_until'])) . '</span>';
        ?>
        <div class="ban-card" id="card-<?= $u['id'] ?>">
            <img class="ban-avatar"
                 src="../uploads/<?= htmlspecialchars($u['avatar'] ?: 'default.png') ?>"
                 onerror="this.onerror=null;this.src='../uploads/default.png'" alt="">
            <div class="ban-info">
                <div class="ban-name">
                    <a href="profile.php?id=<?= $u['id'] ?>" class="profile-link" style="color:#e6edf3;">
                        <?= htmlspecialchars($u['username']) ?>
                    </a>
                    <?= get_role_badge($u['role']) ?>
                    <?= $until_label ?>
                </div>
                <div class="ban-mid">MID: <?= htmlspecialchars($u['mid'] ?? '—') ?></div>
                <div class="ban-reason">封禁原因：<strong><?= htmlspecialchars($u['ban_reason'] ?: '未填写') ?></strong></div>
                <div class="ban-footer">
                    <?php if ($u['banned_by_name']): ?>
                    <span>操作人：<?= htmlspecialchars($u['banned_by_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $can_unban = $my_role === 'owner' || in_array($u['role'], ['user', 'sponsor']);
            if ($can_unban): ?>
            <button class="btn-unban" onclick="doUnban(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>')">
                解除封禁
            </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function doUnban(uid, name) {
    if (!confirm('确认解除「' + name + '」的封禁？')) return;
    const fd = new FormData();
    fd.append('action', 'unban');
    fd.append('user_id', uid);
    fetch('admin_ban.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                const card = document.getElementById('card-' + uid);
                if (card) card.style.transition = 'opacity .3s', card.style.opacity = '0',
                    setTimeout(() => card.remove(), 300);
            } else {
                alert(d.msg || '操作失败');
            }
        });
}
</script>
</body>
</html>
