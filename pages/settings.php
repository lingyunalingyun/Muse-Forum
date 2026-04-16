<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exp_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$my_id = intval($_SESSION['user_id']);

ensure_user_columns($conn);

// 自动建表（兼容旧库没有这两张表的情况）
$conn->query("CREATE TABLE IF NOT EXISTS post_favs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fav (post_id, user_id)
)");
$conn->query("CREATE TABLE IF NOT EXISTS post_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_like (post_id, user_id)
)");

// 处理隐私设置 POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'privacy') {
    $sf  = isset($_POST['show_followers']) ? 1 : 0;
    $sfw = isset($_POST['show_following']) ? 1 : 0;
    $vis_allowed = ['public', 'following', 'followers', 'mutual', 'private'];
    $vis = in_array($_POST['post_visibility'] ?? '', $vis_allowed) ? $_POST['post_visibility'] : 'public';
    $conn->query("UPDATE users SET show_followers=$sf, show_following=$sfw, post_visibility='$vis' WHERE id=$my_id");
    header("Location: settings.php?saved=1");
    exit;
}

// 获取当前用户数据
$user_res = $conn->query("SELECT * FROM users WHERE id = $my_id");
if (!$user_res) { die("数据库错误: " . $conn->error); }
$user = $user_res->fetch_assoc();
if (!$user) { header("Location: login.php"); exit; }

// 黑名单列表
$block_res = $conn->query("SELECT u.id, u.username, u.avatar, u.role
    FROM user_blocks b JOIN users u ON u.id = b.blocked_id
    WHERE b.blocker_id = $my_id ORDER BY b.id DESC");
$block_list = ($block_res) ? $block_res->fetch_all(MYSQLI_ASSOC) : [];

$saved = isset($_GET['saved']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>设置 - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <style>
        .settings-wrap { max-width: 760px; margin: 28px auto; padding: 0 16px 60px; }

        .settings-section {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 6px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .section-header {
            padding: 14px 20px;
            border-bottom: 1px solid #21262d;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .section-header h2 {
            margin: 0;
            font-size: 11px;
            font-weight: 700;
            color: #6e7681;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-family: "Courier New", monospace;
        }
        .section-header h2::before { content: '// '; opacity: .6; }
        .section-body { padding: 18px 20px; }

        /* 个人资料入口 */
        .profile-entry {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .profile-entry img {
            width: 54px; height: 54px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #30363d;
        }
        .profile-entry .info { flex: 1; }
        .profile-entry .info strong { display: block; font-size: 15px; color: #e6edf3; margin-bottom: 3px; }
        .profile-entry .info span { font-size: 12px; color: #6e7681; font-family: "Courier New", monospace; }
        .btn-edit-profile {
            font-size: 12px; color: #3fb950;
            text-decoration: none;
            border: 1px solid rgba(63,185,80,.4);
            padding: 6px 16px;
            border-radius: 4px;
            font-weight: 600;
            transition: .2s;
            white-space: nowrap;
        }
        .btn-edit-profile:hover { background: rgba(63,185,80,.1); }

        /* 隐私开关 */
        .privacy-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #21262d;
        }
        .privacy-row:last-of-type { border-bottom: none; }
        .privacy-label { font-size: 13px; color: #c9d1d9; }
        .privacy-label small { display: block; font-size: 11px; color: #6e7681; margin-top: 2px; font-family: "Courier New", monospace; }

        .toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .toggle-track {
            position: absolute; inset: 0;
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 24px;
            cursor: pointer;
            transition: .2s;
        }
        .toggle-track::after {
            content: '';
            position: absolute;
            width: 18px; height: 18px;
            border-radius: 50%;
            background: #6e7681;
            top: 2px; left: 2px;
            transition: .2s;
        }
        .toggle-switch input:checked + .toggle-track { background: rgba(63,185,80,.2); border-color: #3fb950; }
        .toggle-switch input:checked + .toggle-track::after { background: #3fb950; transform: translateX(20px); }

        .btn-save-privacy {
            margin-top: 16px;
            background: #3fb950; color: #fff;
            border: none; border-radius: 4px;
            padding: 9px 24px;
            font-size: 13px; font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: .2s;
        }
        .btn-save-privacy:hover { background: #2ea043; box-shadow: 0 0 12px rgba(63,185,80,.25); }

        /* 可见性下拉 */
        .vis-select {
            background: #0d1117; border: 1px solid #30363d; color: #e6edf3;
            padding: 7px 10px; border-radius: 4px; font-size: 13px;
            font-family: inherit; cursor: pointer; outline: none; transition: .2s;
            min-width: 160px;
        }
        .vis-select:focus { border-color: #3fb950; }
        .vis-select option { background: #161b22; }

        /* 黑名单 */
        .block-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 0; border-bottom: 1px solid #21262d;
        }
        .block-item:last-child { border-bottom: none; }
        .block-item img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 1px solid #30363d; flex-shrink: 0; }
        .block-item .bi-name { flex: 1; font-size: 13px; color: #c9d1d9; font-weight: 600; }
        .btn-unblock {
            font-size: 12px; color: #f85149;
            border: 1px solid rgba(248,81,73,.4);
            background: none; border-radius: 4px;
            padding: 5px 12px; cursor: pointer;
            font-family: inherit; transition: .2s;
        }
        .btn-unblock:hover { background: rgba(248,81,73,.1); }
        .empty-block { font-size: 13px; color: #6e7681; font-family: "Courier New", monospace; padding: 18px 0; text-align: center; }
        .empty-block::before { content: '// '; }

        /* Toast */
        .toast {
            position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%);
            background: #1c2128; border: 1px solid #3fb950;
            color: #3fb950; padding: 10px 24px;
            border-radius: 6px; font-family: "Courier New", monospace;
            font-size: 13px; z-index: 999;
            animation: fadeUp .3s ease both;
        }
        @keyframes fadeUp { from { opacity:0; transform: translateX(-50%) translateY(10px); } to { opacity:1; transform: translateX(-50%) translateY(0); } }

        @media(max-width:600px){
            .settings-wrap { padding: 0 10px 40px; margin: 14px auto; }
            .section-body { padding: 14px 14px; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<?php if ($saved): ?>
<div class="toast" id="saveToast">// 设置已保存</div>
<script>setTimeout(function(){ var t=document.getElementById('saveToast'); if(t) t.style.opacity='0'; }, 2500);</script>
<?php endif; ?>

<div class="settings-wrap">

    <!-- 个人资料 -->
    <div class="settings-section">
        <div class="section-header">
            <h2>个人资料</h2>
        </div>
        <div class="section-body">
            <div class="profile-entry">
                <img src="../uploads/avatars/<?php echo htmlspecialchars($user['avatar'] ?: 'default.png'); ?>"
                     onerror="this.src='../uploads/avatars/default.png'"
                     alt="avatar">
                <div class="info">
                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                    <span><?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
                </div>
                <a href="edit_profile.php" class="btn-edit-profile">编辑资料</a>
            </div>
        </div>
    </div>

    <!-- 隐私设置 -->
    <div class="settings-section">
        <div class="section-header">
            <h2>隐私设置</h2>
        </div>
        <div class="section-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="privacy">
                <div class="privacy-row">
                    <div class="privacy-label">
                        谁能看我的帖子
                        <small>控制帖子在广场和主页的可见范围</small>
                    </div>
                    <select name="post_visibility" class="vis-select">
                        <?php
                        $vis_cur = $user['post_visibility'] ?? 'public';
                        $vis_opts = [
                            'public'    => '所有人',
                            'followers' => '关注我的人',
                            'following' => '我关注的人',
                            'mutual'    => '互相关注的人',
                            'private'   => '仅自己',
                        ];
                        foreach ($vis_opts as $v => $label):
                        ?>
                        <option value="<?= $v ?>" <?= $vis_cur === $v ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="privacy-row">
                    <div class="privacy-label">
                        公开粉丝列表
                        <small>允许其他人查看关注你的人</small>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="show_followers" value="1"
                            <?php echo ($user['show_followers'] ?? 1) ? 'checked' : ''; ?>>
                        <div class="toggle-track"></div>
                    </label>
                </div>
                <div class="privacy-row">
                    <div class="privacy-label">
                        公开关注列表
                        <small>允许其他人查看你关注的人</small>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="show_following" value="1"
                            <?php echo ($user['show_following'] ?? 1) ? 'checked' : ''; ?>>
                        <div class="toggle-track"></div>
                    </label>
                </div>
                <button type="submit" class="btn-save-privacy">保存设置</button>
            </form>
        </div>
    </div>

    <!-- 黑名单 -->
    <div class="settings-section">
        <div class="section-header">
            <h2>黑名单</h2>
            <span style="font-size:12px;color:#6e7681;font-family:'Courier New',monospace;"><?= count($block_list) ?> 人</span>
        </div>
        <div class="section-body" style="padding-top:8px;padding-bottom:8px;">
            <?php if (empty($block_list)): ?>
                <div class="empty-block">暂无拉黑用户</div>
            <?php else: ?>
                <?php foreach ($block_list as $bu): ?>
                <div class="block-item" id="bi-<?= $bu['id'] ?>">
                    <img src="../uploads/avatars/<?= htmlspecialchars($bu['avatar'] ?: 'default.png') ?>"
                         onerror="this.src='../uploads/avatars/default.png'">
                    <span class="bi-name"><?= htmlspecialchars($bu['username']) ?></span>
                    <button class="btn-unblock" onclick="unblock(<?= $bu['id'] ?>, this)">解除拉黑</button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function unblock(uid, btn) {
    var fd = new FormData();
    fd.append('target_id', uid);
    fetch('../actions/block_user.php', { method: 'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.status === 'unblocked') {
            var row = document.getElementById('bi-' + uid);
            if (row) row.remove();
        }
    });
}
</script>
</body>
</html>
