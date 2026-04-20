<?php
/**
 * 私信查询后台
 * 权限：owner（站长）、reviewer（特别审核员）
 * 功能：输入两个用户的 MID，查看双方之间的完整私信记录
 */
session_start();

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['owner', 'reviewer'])) {
    header('Content-Type: text/html; charset=utf-8');
    die('<h3>🚫 权限不足</h3>仅站长与特别审核员可访问此页面。 <a href="../index.php">返回主页</a>');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exp_helper.php';

$viewer_name = htmlspecialchars($_SESSION['username'] ?? '');

$page     = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;

$mid_a = strtoupper(trim($_GET['mid_a'] ?? ''));
$mid_b = strtoupper(trim($_GET['mid_b'] ?? ''));

$user_a = $user_b = null;
$messages = [];
$total_count = 0;
$total_pages = 1;
$errors = [];

if ($mid_a !== '' || $mid_b !== '') {
    if ($mid_a === '') $errors[] = '请填写用户 A 的 MID';
    if ($mid_b === '') $errors[] = '请填写用户 B 的 MID';

    if (empty($errors)) {
        $sa = $conn->real_escape_string($mid_a);
        $sb = $conn->real_escape_string($mid_b);
        $ra = $conn->query("SELECT id, username, avatar, role FROM users WHERE mid='$sa'");
        $rb = $conn->query("SELECT id, username, avatar, role FROM users WHERE mid='$sb'");
        $user_a = $ra ? $ra->fetch_assoc() : null;
        $user_b = $rb ? $rb->fetch_assoc() : null;

        if (!$user_a) $errors[] = "MID「{$mid_a}」不存在";
        if (!$user_b) $errors[] = "MID「{$mid_b}」不存在";

        if (empty($errors)) {
            if ($user_a['id'] === $user_b['id']) {
                $errors[] = '请输入两个不同用户的 MID';
            } else {
                $uid_a = (int)$user_a['id'];
                $uid_b = (int)$user_b['id'];

                $tc = $conn->query(
                    "SELECT COUNT(*) c FROM messages
                     WHERE (from_user_id=$uid_a AND to_user_id=$uid_b)
                        OR (from_user_id=$uid_b AND to_user_id=$uid_a)"
                );
                $total_count = $tc ? (int)$tc->fetch_assoc()['c'] : 0;
                $total_pages = max(1, (int)ceil($total_count / $per_page));
                $page   = min($page, $total_pages);
                $offset = ($page - 1) * $per_page;

                $res = $conn->query(
                    "SELECT m.id, m.from_user_id, m.content, m.created_at,
                            u.username AS sender_name, u.avatar AS sender_avatar
                     FROM messages m
                     JOIN users u ON u.id = m.from_user_id
                     WHERE (m.from_user_id=$uid_a AND m.to_user_id=$uid_b)
                        OR (m.from_user_id=$uid_b AND m.to_user_id=$uid_a)
                     ORDER BY m.id ASC
                     LIMIT $per_page OFFSET $offset"
                );
                if ($res) while ($r = $res->fetch_assoc()) $messages[] = $r;
            }
        }
    }
}

function fmt_time(string $t): string {
    return date('Y-m-d H:i', strtotime($t));
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>私信查询 — 后台</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { background: #0d1117; color: #c9d1d9; font-family: "Courier New", monospace; margin: 0; }

        .wrap { max-width: 960px; margin: 28px auto; padding: 0 16px; }

        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 8px; }
        .page-title { margin: 0; font-size: 13px; font-weight: 700; color: #e3b341; letter-spacing: 1.5px; text-transform: uppercase; }
        .page-title::before { content: '// '; opacity: .5; }
        .page-meta { font-size: 12px; color: #6e7681; }
        .page-meta a { color: #3fb950; text-decoration: none; }

        .search-card { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 20px 22px; margin-bottom: 20px; }
        .search-card h3 { margin: 0 0 14px; font-size: 12px; color: #8b949e; }
        .search-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }

        .search-field { flex: 1; min-width: 180px; }
        .search-field label { display: block; font-size: 11px; color: #6e7681; margin-bottom: 5px; letter-spacing: .3px; }
        .mid-input {
            width: 100%; padding: 9px 12px; background: #0d1117; border: 1px solid #30363d;
            border-radius: 5px; color: #e3b341; font-size: 14px; font-family: "Courier New", monospace;
            outline: none; letter-spacing: 1px; text-transform: uppercase;
        }
        .mid-input:focus { border-color: #e3b341; }
        .mid-input::placeholder { color: #484f58; text-transform: none; letter-spacing: 0; }

        .btn-query {
            padding: 9px 22px; background: #e3b341; color: #0d1117; border: none; border-radius: 5px;
            font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit; white-space: nowrap; transition: opacity .15s;
        }
        .btn-query:hover { opacity: .85; }

        .alert { border-radius: 6px; padding: 10px 16px; font-size: 13px; margin-bottom: 14px; }
        .alert-error { background: rgba(248,81,73,.1); border: 1px solid rgba(248,81,73,.3); color: #f85149; }

        /* 用户对比卡 */
        .user-pair { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; flex-wrap: wrap; }
        .user-card {
            display: flex; align-items: center; gap: 10px; flex: 1; min-width: 200px;
            background: #161b22; border: 1px solid #30363d; border-radius: 7px; padding: 10px 14px;
        }
        .user-card img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid #30363d; }
        .user-card-info { display: flex; flex-direction: column; gap: 3px; }
        .user-card-name { font-size: 14px; font-weight: 700; color: #e6edf3; }
        .user-card-mid { font-size: 11px; color: #6e7681; }
        .pair-sep { font-size: 18px; color: #30363d; flex-shrink: 0; }

        /* 结果区 */
        .result-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 6px; }
        .result-meta span { font-size: 12px; color: #6e7681; }
        .result-meta strong { color: #e3b341; }

        .chat-log { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 16px; display: flex; flex-direction: column; gap: 10px; }

        .date-sep { display: flex; align-items: center; gap: 10px; font-size: 11px; color: #484f58; margin: 2px 0; }
        .date-sep::before, .date-sep::after { content: ''; flex: 1; height: 1px; background: #21262d; }

        .msg-row { display: flex; gap: 10px; max-width: 85%; }
        .msg-row.right { flex-direction: row-reverse; align-self: flex-end; }
        .msg-avatar { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 1px solid #30363d; flex-shrink: 0; }
        .msg-body { display: flex; flex-direction: column; gap: 3px; }
        .msg-row.right .msg-body { align-items: flex-end; }
        .msg-meta { font-size: 10px; color: #6e7681; }
        .msg-meta .sender { color: #8b949e; font-weight: 600; margin-right: 5px; }
        .msg-bubble { padding: 7px 12px; border-radius: 6px; font-size: 13px; line-height: 1.55; word-break: break-word; font-family: "Microsoft YaHei", sans-serif; }
        .msg-row.left  .msg-bubble { background: #21262d; border: 1px solid #30363d; border-top-left-radius: 2px; }
        .msg-row.right .msg-bubble { background: rgba(227,179,65,.1); border: 1px solid rgba(227,179,65,.2); border-top-right-radius: 2px; }

        .empty-state { text-align: center; padding: 50px 20px; background: #161b22; border: 1px solid #30363d; border-radius: 8px; color: #6e7681; font-size: 13px; }

        .pagination { display: flex; gap: 6px; justify-content: center; margin-top: 16px; flex-wrap: wrap; }
        .pag-btn { padding: 5px 13px; border-radius: 4px; font-size: 12px; text-decoration: none; border: 1px solid #30363d; background: #161b22; color: #8b949e; transition: .15s; }
        .pag-btn:hover { border-color: #e3b341; color: #e3b341; }
        .pag-btn.active { background: #e3b341; color: #0d1117; border-color: #e3b341; font-weight: 700; }
        .pag-btn.disabled { opacity: .35; pointer-events: none; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="wrap">
    <div class="page-header">
        <h2 class="page-title">私信查询</h2>
        <div class="page-meta"><?= $viewer_name ?> &nbsp;·&nbsp; <a href="../index.php">← 返回主页</a></div>
    </div>

    <div class="search-card">
        <h3>&gt; 输入两位用户的 MID 查看对话记录</h3>
        <form method="GET">
            <div class="search-row">
                <div class="search-field">
                    <label>用户 A 的 MID</label>
                    <input class="mid-input" type="text" name="mid_a"
                           value="<?= htmlspecialchars($mid_a) ?>" placeholder="例：AB12CD34" maxlength="8">
                </div>
                <div class="search-field">
                    <label>用户 B 的 MID</label>
                    <input class="mid-input" type="text" name="mid_b"
                           value="<?= htmlspecialchars($mid_b) ?>" placeholder="例：EF56GH78" maxlength="8">
                </div>
                <button type="submit" class="btn-query">查询记录</button>
            </div>
        </form>
    </div>

    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $e): ?>
        <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($user_a && $user_b && empty($errors)): ?>
    <!-- 用户对比卡 -->
    <div class="user-pair">
        <div class="user-card">
            <img src="../uploads/<?= htmlspecialchars($user_a['avatar'] ?: 'default.png') ?>" alt=""
                 onerror="this.src='../uploads/default.png'">
            <div class="user-card-info">
                <div class="user-card-name"><?= htmlspecialchars($user_a['username']) ?></div>
                <div class="user-card-mid">MID: <?= htmlspecialchars($mid_a) ?></div>
                <?= get_role_badge($user_a['role']) ?>
            </div>
        </div>
        <div class="pair-sep">↔</div>
        <div class="user-card">
            <img src="../uploads/<?= htmlspecialchars($user_b['avatar'] ?: 'default.png') ?>" alt=""
                 onerror="this.src='../uploads/default.png'">
            <div class="user-card-info">
                <div class="user-card-name"><?= htmlspecialchars($user_b['username']) ?></div>
                <div class="user-card-mid">MID: <?= htmlspecialchars($mid_b) ?></div>
                <?= get_role_badge($user_b['role']) ?>
            </div>
        </div>
    </div>

    <!-- 对话记录 -->
    <div class="result-meta">
        <span>共 <strong><?= $total_count ?></strong> 条消息</span>
        <span>第 <?= $page ?>/<?= $total_pages ?> 页</span>
    </div>

    <?php if (empty($messages)): ?>
    <div class="empty-state">双方之间暂无私信记录</div>
    <?php else: ?>
    <div class="chat-log">
        <?php
        $last_date = '';
        $uid_a_int = (int)$user_a['id'];
        foreach ($messages as $m):
            $msg_date = date('Y-m-d', strtotime($m['created_at']));
            if ($msg_date !== $last_date): $last_date = $msg_date; ?>
        <div class="date-sep"><?= htmlspecialchars($msg_date) ?></div>
        <?php endif;
            $is_right = ((int)$m['from_user_id'] !== $uid_a_int);
            $av = htmlspecialchars($m['sender_avatar'] ?: 'default.png');
        ?>
        <div class="msg-row <?= $is_right ? 'right' : 'left' ?>">
            <img class="msg-avatar" src="../uploads/<?= $av ?>" alt="" onerror="this.src='../uploads/default.png'">
            <div class="msg-body">
                <div class="msg-meta">
                    <span class="sender"><?= htmlspecialchars($m['sender_name']) ?></span>
                    <?= fmt_time($m['created_at']) ?>
                </div>
                <div class="msg-bubble"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php
        $base_url = "admin_messages.php?mid_a=" . urlencode($mid_a) . "&mid_b=" . urlencode($mid_b);
        ?>
        <a href="<?= $base_url ?>&page=<?= $page-1 ?>" class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>">← 上页</a>
        <?php for ($i = max(1,$page-3); $i <= min($total_pages,$page+3); $i++): ?>
        <a href="<?= $base_url ?>&page=<?= $i ?>" class="pag-btn <?= $i===$page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $base_url ?>&page=<?= $page+1 ?>" class="pag-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">下页 →</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
