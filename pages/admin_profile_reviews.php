<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exp_helper.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'owner'])) {
    header("Location: ../index.php");
    exit;
}

ensure_user_columns($conn);

$admin_id = intval($_SESSION['user_id']);

// 处理审核操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $req_id = intval($_POST['req_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note   = $conn->real_escape_string(trim($_POST['note'] ?? ''));

    $rq = $conn->query("SELECT * FROM profile_edit_requests WHERE id=$req_id AND status='pending'");
    if ($rq && $rq->num_rows > 0) {
        $req = $rq->fetch_assoc();
        $uid = (int)$req['user_id'];

        if ($action === 'approve') {
            $sets = [];
            if (!empty($req['new_username']))  $sets[] = "username='"  . $conn->real_escape_string($req['new_username'])  . "'";
            if (!empty($req['new_gender']))    $sets[] = "gender='"    . $conn->real_escape_string($req['new_gender'])    . "'";
            if ($req['new_phone']  !== null)   $sets[] = "phone='"     . $conn->real_escape_string($req['new_phone'])     . "'";
            if (!empty($req['new_birthday']))  $sets[] = "birthday='"  . $conn->real_escape_string($req['new_birthday'])  . "'";
            if ($req['new_signature'] !== null) $sets[] = "signature='" . $conn->real_escape_string($req['new_signature']) . "'";

            if (!empty($req['new_avatar'])) {
                $user_r = $conn->query("SELECT mid FROM users WHERE id=$uid");
                $user_row = $user_r ? $user_r->fetch_assoc() : null;
                $ext = pathinfo($req['new_avatar'], PATHINFO_EXTENSION);
                $final_name = ($user_row['mid'] ?? ('u'.$uid)) . '.' . $ext;
                $avatar_dir = __DIR__ . '/../uploads/avatars/';
                $old_pending = $avatar_dir . $req['new_avatar'];
                $new_path    = $avatar_dir . $final_name;
                if (file_exists($old_pending)) {
                    if (file_exists($new_path) && $new_path !== $old_pending) @unlink($new_path);
                    rename($old_pending, $new_path);
                }
                $sets[] = "avatar='" . $conn->real_escape_string($final_name) . "'";
            }

            if ($sets) {
                $conn->query("UPDATE users SET " . implode(',', $sets) . " WHERE id=$uid");
            }
            $conn->query("UPDATE profile_edit_requests SET status='approved', admin_id=$admin_id, reviewed_at=NOW() WHERE id=$req_id");
            $status_msg = 'approved';

        } elseif ($action === 'reject') {
            if (!empty($req['new_avatar'])) {
                $f = __DIR__ . '/../uploads/avatars/' . $req['new_avatar'];
                if (file_exists($f)) @unlink($f);
            }
            $conn->query("UPDATE profile_edit_requests SET status='rejected', admin_id=$admin_id, admin_note='$note', reviewed_at=NOW() WHERE id=$req_id");
            $status_msg = 'rejected';
        }
    }
    header("Location: admin_profile_reviews.php?done=" . ($status_msg ?? ''));
    exit;
}

$filter  = $_GET['filter'] ?? 'pending';
$allowed = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $allowed)) $filter = 'pending';

$where = $filter === 'all' ? '' : "WHERE r.status='$filter'";
$reqs = $conn->query("
    SELECT r.*, u.username AS cur_username, u.gender AS cur_gender, u.phone AS cur_phone,
           u.birthday AS cur_birthday, u.signature AS cur_signature, u.avatar AS cur_avatar
    FROM profile_edit_requests r
    JOIN users u ON u.id = r.user_id
    $where
    ORDER BY r.created_at DESC
    LIMIT 200
");

$counts = [];
foreach (['pending','approved','rejected'] as $s) {
    $cr = $conn->query("SELECT COUNT(*) c FROM profile_edit_requests WHERE status='$s'");
    $counts[$s] = $cr ? (int)$cr->fetch_assoc()['c'] : 0;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>资料审核 — 管理后台</title>
    <style>
        body { background: #0d1117; color: #e6edf3; font-family: "Microsoft YaHei", monospace; margin: 0; }
        .container { max-width: 960px; margin: 0 auto; padding: 28px 20px; }
        h1 { font-size: 14px; color: #3fb950; font-family: "Courier New", monospace; letter-spacing: 1.5px; text-transform: uppercase; margin: 0 0 24px; }
        h1::before { content: '// '; opacity: .5; }
        .tabs { display: flex; gap: 6px; margin-bottom: 22px; flex-wrap: wrap; }
        .tab { padding: 6px 14px; border-radius: 4px; border: 1px solid #30363d; font-size: 12px; color: #8b949e; text-decoration: none; transition: .15s; }
        .tab:hover { color: #e6edf3; border-color: #58a6ff; }
        .tab.active { background: rgba(63,185,80,.15); border-color: rgba(63,185,80,.4); color: #3fb950; }
        .badge { display: inline-block; background: #f85149; color: #fff; border-radius: 10px; min-width: 16px; height: 16px; font-size: 10px; line-height: 16px; text-align: center; padding: 0 4px; margin-left: 4px; }
        .card { background: #161b22; border: 1px solid #30363d; border-radius: 6px; padding: 18px 20px; margin-bottom: 14px; }
        .card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
        .user-info { font-size: 13px; color: #e6edf3; font-weight: 600; }
        .user-mid  { font-size: 11px; color: #6e7681; font-family: "Courier New", monospace; }
        .req-time  { font-size: 11px; color: #6e7681; margin-left: auto; }
        .status-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; font-family: "Courier New", monospace; }
        .status-pending  { background: rgba(210,153,34,.15); border: 1px solid rgba(210,153,34,.3); color: #d29922; }
        .status-approved { background: rgba(63,185,80,.15);  border: 1px solid rgba(63,185,80,.3);  color: #3fb950; }
        .status-rejected { background: rgba(248,81,73,.15);  border: 1px solid rgba(248,81,73,.3);  color: #f85149; }
        .diff-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 14px; }
        .diff-table th { text-align: left; color: #6e7681; font-size: 11px; font-family: "Courier New", monospace; text-transform: uppercase; padding: 4px 8px; border-bottom: 1px solid #21262d; }
        .diff-table td { padding: 6px 8px; border-bottom: 1px solid #21262d; vertical-align: top; }
        .diff-table tr:last-child td { border-bottom: none; }
        .old-val { color: #8b949e; }
        .new-val { color: #3fb950; font-weight: 600; }
        .same-val { color: #6e7681; font-style: italic; }
        .avatar-pair { display: flex; align-items: center; gap: 10px; }
        .avatar-pair img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #30363d; }
        .action-row { display: flex; gap: 8px; align-items: flex-start; flex-wrap: wrap; }
        .note-input { flex: 1; min-width: 180px; padding: 7px 10px; background: #0d1117; border: 1px solid #30363d; border-radius: 4px; color: #e6edf3; font-size: 13px; font-family: inherit; outline: none; }
        .note-input:focus { border-color: #3fb950; }
        .btn-approve { padding: 7px 18px; background: #3fb950; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-family: inherit; transition: .15s; }
        .btn-approve:hover { background: #2ea043; }
        .btn-reject  { padding: 7px 18px; background: transparent; color: #f85149; border: 1px solid rgba(248,81,73,.4); border-radius: 4px; cursor: pointer; font-size: 13px; font-family: inherit; transition: .15s; }
        .btn-reject:hover  { background: rgba(248,81,73,.1); }
        .admin-note { font-size: 12px; color: #d29922; margin-top: 6px; }
        .empty { text-align: center; padding: 40px; color: #6e7681; font-size: 13px; }
        .done-msg { background: rgba(63,185,80,.12); border: 1px solid rgba(63,185,80,.3); color: #3fb950; padding: 10px 14px; border-radius: 4px; font-size: 13px; margin-bottom: 18px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="container">
    <h1>资料修改审核</h1>

    <?php if (isset($_GET['done'])): ?>
    <div class="done-msg"><?= $_GET['done']==='approved' ? '✓ 已通过审核，资料已更新。' : '✗ 已拒绝申请。' ?></div>
    <?php endif; ?>

    <div class="tabs">
        <a href="?filter=pending"  class="tab <?= $filter==='pending' ?'active':'' ?>">待审核<?php if($counts['pending']>0): ?><span class="badge"><?= $counts['pending'] ?></span><?php endif; ?></a>
        <a href="?filter=approved" class="tab <?= $filter==='approved'?'active':'' ?>">已通过 (<?= $counts['approved'] ?>)</a>
        <a href="?filter=rejected" class="tab <?= $filter==='rejected'?'active':'' ?>">已拒绝 (<?= $counts['rejected'] ?>)</a>
        <a href="?filter=all"      class="tab <?= $filter==='all'     ?'active':'' ?>">全部</a>
    </div>

    <?php if (!$reqs || $reqs->num_rows === 0): ?>
    <div class="empty">暂无<?= $filter==='pending'?'待审核':'' ?>记录</div>
    <?php else: while ($r = $reqs->fetch_assoc()): ?>
    <div class="card">
        <div class="card-header">
            <span class="user-info"><?= htmlspecialchars($r['cur_username']) ?></span>
            <span class="user-mid">ID <?= $r['user_id'] ?></span>
            <span class="status-badge status-<?= $r['status'] ?>"><?= ['pending'=>'待审核','approved'=>'已通过','rejected'=>'已拒绝'][$r['status']] ?></span>
            <span class="req-time"><?= $r['created_at'] ?></span>
        </div>

        <table class="diff-table">
            <tr><th>字段</th><th>当前值</th><th></th><th>申请改为</th></tr>
            <?php
            $fields = ['username'=>'用户名','gender'=>'性别','phone'=>'电话','birthday'=>'生日','signature'=>'签名'];
            foreach ($fields as $key => $label) {
                $old = $r['cur_'.$key] ?? '';
                $new = $r['new_'.$key];
                if ($new === null) continue;
                $changed = (string)$new !== (string)$old;
                echo "<tr>";
                echo "<td style='color:#8b949e;font-size:12px;'>{$label}</td>";
                echo "<td class='old-val'>" . htmlspecialchars($old ?: '—') . "</td>";
                echo "<td style='color:#3fb950;padding:6px 4px;'>→</td>";
                echo "<td class='" . ($changed ? 'new-val' : 'same-val') . "'>" . htmlspecialchars($new ?: '—') . ($changed ? '' : ' (未变)') . "</td>";
                echo "</tr>";
            }
            if ($r['new_avatar']): ?>
            <tr>
                <td style="color:#8b949e;font-size:12px;">头像</td>
                <td><div class="avatar-pair"><img src="../uploads/avatars/<?= htmlspecialchars($r['cur_avatar'] ?: 'default.png') ?>" onerror="this.src='../uploads/avatars/default.png'"></div></td>
                <td style="color:#3fb950;padding:6px 4px;">→</td>
                <td><div class="avatar-pair"><img src="../uploads/avatars/<?= htmlspecialchars($r['new_avatar']) ?>" onerror="this.src='../uploads/avatars/default.png'"><span class="new-val">新头像</span></div></td>
            </tr>
            <?php endif; ?>
        </table>

        <?php if ($r['status'] === 'pending'): ?>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
            <div class="action-row">
                <input type="text" name="note" class="note-input" placeholder="拒绝原因（可选）">
                <button type="submit" name="action" value="approve" class="btn-approve">通过</button>
                <button type="submit" name="action" value="reject"  class="btn-reject">拒绝</button>
            </div>
        </form>
        <?php elseif ($r['admin_note']): ?>
        <div class="admin-note">拒绝原因：<?= htmlspecialchars($r['admin_note']) ?></div>
        <?php endif; ?>
    </div>
    <?php endwhile; endif; ?>
</div>
</body>
</html>
