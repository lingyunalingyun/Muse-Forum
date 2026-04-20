<?php
/**
 * 举报查看后台
 * 权限：admin、owner
 * 功能：查看举报列表，快速处理（删帖/封号/驳回/标记已处理）
 */
session_start();

$my_role = $_SESSION['role'] ?? '';
if (!in_array($my_role, ['admin', 'owner'])) {
    header('Content-Type: text/html; charset=utf-8');
    die('<h3>🚫 权限不足</h3> <a href="../index.php">返回主页</a>');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exp_helper.php';

$conn->set_charset('utf8mb4');
ensure_user_columns($conn);

$my_id   = intval($_SESSION['user_id']);
$my_name = htmlspecialchars($_SESSION['username'] ?? '');

// ── POST 操作（AJAX）──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $rid    = intval($_POST['report_id'] ?? 0);
    $now    = date('Y-m-d H:i:s');

    if ($action === 'handle' || $action === 'dismiss') {
        $status = $action === 'handle' ? 'handled' : 'dismissed';
        $conn->query("UPDATE reports SET status='$status', handler_id=$my_id, handled_at='$now' WHERE id=$rid");
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($action === 'delete_post') {
        $pid = intval($_POST['target_id'] ?? 0);
        $conn->query("DELETE FROM posts WHERE id=$pid");
        $conn->query("UPDATE reports SET status='handled', handler_id=$my_id, handled_at='$now' WHERE id=$rid");
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($action === 'ban_user') {
        $uid    = intval($_POST['target_id'] ?? 0);
        $reason = $conn->real_escape_string(trim($_POST['ban_reason'] ?? '违规内容'));
        // 权限检查
        $tr = $conn->query("SELECT role FROM users WHERE id=$uid");
        $t  = $tr ? $tr->fetch_assoc() : null;
        if ($t && !($my_role === 'admin' && !in_array($t['role'], ['user','sponsor']))) {
            $conn->query("UPDATE users SET is_banned=1, ban_reason='$reason', banned_by=$my_id WHERE id=$uid");
        }
        $conn->query("UPDATE reports SET status='handled', handler_id=$my_id, handled_at='$now' WHERE id=$rid");
        echo json_encode(['ok' => true]);
        exit;
    }
    echo json_encode(['ok' => false, 'msg' => '未知操作']);
    exit;
}

// ── 列表查询 ──
$filter = $_GET['filter'] ?? 'pending';
$type_f = $_GET['type']   ?? 'all';

$where = [];
if ($filter !== 'all') $where[] = "r.status='$filter'";
if ($type_f !== 'all')  $where[] = "r.type='$type_f'";
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$res = $conn->query(
    "SELECT r.*,
            u.username AS reporter_name,
            h.username AS handler_name
     FROM reports r
     LEFT JOIN users u ON u.id = r.reporter_id
     LEFT JOIN users h ON h.id = r.handler_id
     $where_sql
     ORDER BY r.id DESC
     LIMIT 200"
);
$reports = [];
if ($res) while ($row = $res->fetch_assoc()) $reports[] = $row;

// 统计
$cnt = [];
foreach (['pending','handled','dismissed'] as $s) {
    $c = $conn->query("SELECT COUNT(*) c FROM reports WHERE status='$s'");
    $cnt[$s] = $c ? (int)$c->fetch_assoc()['c'] : 0;
}
$cnt['all'] = array_sum($cnt);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>举报管理 — 后台</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { background: #0d1117; color: #c9d1d9; font-family: "Microsoft YaHei", sans-serif; margin: 0; }
        .wrap { max-width: 1080px; margin: 28px auto; padding: 0 16px; }

        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 8px; }
        .page-title { margin: 0; font-size: 13px; font-weight: 700; color: #f0883e; letter-spacing: 1.5px; text-transform: uppercase; font-family: "Courier New", monospace; }
        .page-title::before { content: '// '; opacity: .5; }
        .page-meta { font-size: 12px; color: #6e7681; font-family: "Courier New", monospace; }
        .page-meta a { color: #3fb950; text-decoration: none; }

        /* 筛选栏 */
        .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; align-items: center; }
        .ftab { padding: 5px 14px; border-radius: 20px; font-size: 12px; text-decoration: none; border: 1px solid #30363d;
                background: #161b22; color: #8b949e; transition: .15s; font-weight: 600; white-space: nowrap; }
        .ftab:hover { border-color: #f0883e; color: #f0883e; }
        .ftab.active { background: rgba(240,136,62,.15); color: #f0883e; border-color: rgba(240,136,62,.4); }
        .ftab .n { display: inline-block; background: #21262d; border-radius: 9px; padding: 0 6px;
                   font-size: 10px; margin-left: 4px; color: #6e7681; }
        .ftab.active .n { background: rgba(240,136,62,.2); color: #f0883e; }
        .filter-sep { width: 1px; height: 20px; background: #30363d; }

        /* 举报卡片 */
        .report-card { background: #161b22; border: 1px solid #30363d; border-radius: 7px; padding: 14px 16px;
                       margin-bottom: 8px; transition: border-color .15s; }
        .report-card:hover { border-color: rgba(240,136,62,.3); }
        .report-card.handled  { opacity: .65; }
        .report-card.dismissed { opacity: .5; }

        .rc-top { display: flex; align-items: flex-start; gap: 12px; }
        .rc-badge { padding: 2px 9px; border-radius: 3px; font-size: 11px; font-weight: 700; white-space: nowrap;
                    font-family: "Courier New", monospace; flex-shrink: 0; margin-top: 2px; }
        .badge-post { background: rgba(88,166,255,.12); color: #58a6ff; border: 1px solid rgba(88,166,255,.3); }
        .badge-user { background: rgba(167,139,250,.12); color: #a78bfa; border: 1px solid rgba(167,139,250,.3); }

        .rc-body { flex: 1; min-width: 0; }
        .rc-reason { font-size: 14px; font-weight: 700; color: #e6edf3; margin: 0 0 5px; }
        .rc-detail { font-size: 12px; color: #8b949e; margin: 0 0 8px; line-height: 1.5; }
        .rc-meta { display: flex; flex-wrap: wrap; gap: 10px; font-size: 11px; color: #6e7681;
                   font-family: "Courier New", monospace; }
        .rc-meta a { color: #58a6ff; text-decoration: none; }
        .rc-meta a:hover { text-decoration: underline; }

        .rc-actions { display: flex; gap: 7px; flex-wrap: wrap; margin-top: 12px; }
        .act-btn { padding: 5px 13px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer;
                   border: 1px solid; font-family: inherit; transition: opacity .15s; white-space: nowrap; }
        .act-btn:hover { opacity: .8; }
        .btn-del-post { background: rgba(248,81,73,.1); color: #f85149; border-color: rgba(248,81,73,.3); }
        .btn-ban      { background: rgba(248,81,73,.08); color: #f0883e; border-color: rgba(248,81,73,.25); }
        .btn-view     { background: #21262d; color: #8b949e; border-color: #30363d; }
        .btn-handled  { background: rgba(63,185,80,.08); color: #3fb950; border-color: rgba(63,185,80,.3); }
        .btn-dismiss  { background: transparent; color: #6e7681; border-color: #30363d; }

        .status-tag { padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 700;
                      font-family: "Courier New", monospace; }
        .st-pending   { background: rgba(240,136,62,.12); color: #f0883e; border: 1px solid rgba(240,136,62,.3); }
        .st-handled   { background: rgba(63,185,80,.1);  color: #3fb950; border: 1px solid rgba(63,185,80,.3); }
        .st-dismissed { background: rgba(110,118,129,.1); color: #6e7681; border: 1px solid rgba(110,118,129,.3); }

        .empty-state { text-align: center; padding: 60px 20px; background: #161b22; border: 1px solid #30363d;
                       border-radius: 8px; color: #6e7681; font-size: 13px; }

        /* 快速封禁弹窗 */
        #quick-ban-modal { display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="wrap">
    <div class="page-header">
        <h2 class="page-title">举报管理</h2>
        <div class="page-meta"><?= $my_name ?> &nbsp;·&nbsp; <a href="../index.php">← 返回主页</a></div>
    </div>

    <div class="filter-bar">
        <?php
        $fl = ['pending'=>'待处理', 'handled'=>'已处理', 'dismissed'=>'已驳回', 'all'=>'全部'];
        foreach ($fl as $k => $label):
            $active = $filter === $k;
        ?>
        <a href="?filter=<?= $k ?>&type=<?= $type_f ?>" class="ftab <?= $active ? 'active' : '' ?>">
            <?= $label ?> <span class="n"><?= $cnt[$k] ?></span>
        </a>
        <?php endforeach; ?>
        <div class="filter-sep"></div>
        <a href="?filter=<?= $filter ?>&type=all"  class="ftab <?= $type_f==='all'  ? 'active' : '' ?>">全部类型</a>
        <a href="?filter=<?= $filter ?>&type=post" class="ftab <?= $type_f==='post' ? 'active' : '' ?>">帖子举报</a>
        <a href="?filter=<?= $filter ?>&type=user" class="ftab <?= $type_f==='user' ? 'active' : '' ?>">用户举报</a>
    </div>

    <?php if (empty($reports)): ?>
    <div class="empty-state">暂无举报记录</div>
    <?php else: ?>

    <?php foreach ($reports as $r): ?>
    <?php
    $st_cls = ['pending'=>'st-pending','handled'=>'st-handled','dismissed'=>'st-dismissed'][$r['status']] ?? '';
    $card_cls = in_array($r['status'], ['handled','dismissed']) ? $r['status'] : '';
    $is_post = $r['type'] === 'post';
    ?>
    <div class="report-card <?= $card_cls ?>" id="rcard-<?= $r['id'] ?>">
        <div class="rc-top">
            <span class="rc-badge <?= $is_post ? 'badge-post' : 'badge-user' ?>">
                <?= $is_post ? '帖子' : '用户' ?>
            </span>
            <div class="rc-body">
                <div class="rc-reason">
                    <?= htmlspecialchars($r['reason']) ?>
                    <span class="status-tag <?= $st_cls ?>" style="margin-left:8px;">
                        <?= ['pending'=>'待处理','handled'=>'已处理','dismissed'=>'已驳回'][$r['status']] ?? $r['status'] ?>
                    </span>
                </div>
                <?php if ($r['detail']): ?>
                <div class="rc-detail"><?= htmlspecialchars($r['detail']) ?></div>
                <?php endif; ?>
                <div class="rc-meta">
                    <span>举报人：<strong style="color:#c9d1d9;"><?= htmlspecialchars($r['reporter_name'] ?? '—') ?></strong></span>
                    <span>目标 ID：
                        <?php if ($is_post): ?>
                        <a href="post.php?id=<?= $r['target_id'] ?>" target="_blank">#<?= $r['target_id'] ?></a>
                        <?php else: ?>
                        <a href="profile.php?id=<?= $r['target_id'] ?>" target="_blank">UID <?= $r['target_id'] ?></a>
                        <?php endif; ?>
                    </span>
                    <span><?= date('m-d H:i', strtotime($r['created_at'])) ?></span>
                    <?php if ($r['handler_name']): ?>
                    <span>处理人：<?= htmlspecialchars($r['handler_name']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($r['status'] === 'pending'): ?>
                <div class="rc-actions">
                    <?php if ($is_post): ?>
                    <a href="post.php?id=<?= $r['target_id'] ?>" target="_blank" class="act-btn btn-view">查看帖子</a>
                    <button class="act-btn btn-del-post"
                            onclick="quickDeletePost(<?= $r['id'] ?>, <?= $r['target_id'] ?>)">删除帖子</button>
                    <?php else: ?>
                    <a href="profile.php?id=<?= $r['target_id'] ?>" target="_blank" class="act-btn btn-view">查看主页</a>
                    <button class="act-btn btn-ban"
                            onclick="quickBanUser(<?= $r['id'] ?>, <?= $r['target_id'] ?>)">封禁用户</button>
                    <?php endif; ?>
                    <button class="act-btn btn-handled" onclick="handleReport(<?= $r['id'] ?>, 'handle')">✓ 已处理</button>
                    <button class="act-btn btn-dismiss" onclick="handleReport(<?= $r['id'] ?>, 'dismiss')">驳回</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- 快速封禁弹窗 -->
<div id="quick-ban-modal">
    <div style="background:#161b22;border:1px solid #30363d;border-radius:8px;padding:24px;width:360px;max-width:92vw;font-family:'Courier New',monospace;">
        <p style="margin:0 0 14px;color:#f85149;font-weight:700;font-size:14px;">快速封禁用户</p>
        <input id="qban-reason" type="text" placeholder="封禁原因（必填）" maxlength="200"
               style="width:100%;box-sizing:border-box;background:#0d1117;border:1px solid #30363d;color:#e6edf3;
                      padding:8px 10px;border-radius:4px;font-size:13px;font-family:inherit;outline:none;">
        <div style="display:flex;gap:8px;margin-top:14px;">
            <button onclick="confirmQuickBan()"
                    style="flex:1;padding:9px;border:none;border-radius:4px;background:#f85149;color:#fff;
                           font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;">封禁</button>
            <button onclick="document.getElementById('quick-ban-modal').style.display='none'"
                    style="flex:1;padding:9px;border:1px solid #30363d;border-radius:4px;background:transparent;
                           color:#8b949e;font-size:13px;cursor:pointer;font-family:inherit;">取消</button>
        </div>
    </div>
</div>

<script>
let _qbRid = 0, _qbUid = 0;

function handleReport(rid, action) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('report_id', rid);
    fetch('admin_reports.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                const card = document.getElementById('rcard-' + rid);
                if (card) card.style.transition = 'opacity .3s', card.style.opacity = '0',
                    setTimeout(() => card.remove(), 300);
            }
        });
}

function quickDeletePost(rid, pid) {
    if (!confirm('确认删除该帖子并标记举报为已处理？')) return;
    const fd = new FormData();
    fd.append('action', 'delete_post');
    fd.append('report_id', rid);
    fd.append('target_id', pid);
    fetch('admin_reports.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                const card = document.getElementById('rcard-' + rid);
                if (card) card.style.transition='opacity .3s',card.style.opacity='0',setTimeout(()=>card.remove(),300);
            }
        });
}

function quickBanUser(rid, uid) {
    _qbRid = rid; _qbUid = uid;
    document.getElementById('qban-reason').value = '';
    document.getElementById('quick-ban-modal').style.display = 'flex';
}

function confirmQuickBan() {
    const reason = document.getElementById('qban-reason').value.trim();
    if (!reason) { alert('请填写封禁原因'); return; }
    const fd = new FormData();
    fd.append('action', 'ban_user');
    fd.append('report_id', _qbRid);
    fd.append('target_id', _qbUid);
    fd.append('ban_reason', reason);
    fetch('admin_reports.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            document.getElementById('quick-ban-modal').style.display = 'none';
            if (d.ok) {
                const card = document.getElementById('rcard-' + _qbRid);
                if (card) card.style.transition='opacity .3s',card.style.opacity='0',setTimeout(()=>card.remove(),300);
            }
        });
}

document.getElementById('quick-ban-modal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>
</body>
</html>
