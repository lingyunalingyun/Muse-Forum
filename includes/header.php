<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_role = $_SESSION['role'] ?? 'user';
$is_logged_in = isset($_SESSION['user_id']);

// 自动识别路径深度
$in_subdir = (strpos($_SERVER['PHP_SELF'], '/pages/') !== false);
$base = $in_subdir ? '../' : '';

// 未读消息数（只在有数据库连接时查询）
$unread_count = 0;
$pending_posts = 0;
if ($is_logged_in && isset($conn)) {
    $uid_h = intval($_SESSION['user_id']);

    // 中途封禁检测
    $ban_res = $conn->query("SELECT is_banned, ban_reason FROM users WHERE id=$uid_h");
    if ($ban_res && $ban_row = $ban_res->fetch_assoc()) {
        if (!empty($ban_row['is_banned'])) {
            session_destroy();
            $reason = htmlspecialchars($ban_row['ban_reason'] ?: '违反社区规范');
            echo "<!DOCTYPE html><html lang='zh-CN'><head><meta charset='UTF-8'><title>账号已封禁</title></head>
            <body style='font-family:monospace;background:#0d1117;color:#e6edf3;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;'>
            <div style='text-align:center;max-width:420px;padding:32px;background:#161b22;border:1px solid #30363d;border-radius:6px;'>
            <p style='color:#f85149;font-size:18px;margin:0 0 12px;'>&#128683; 账号已封禁</p>
            <p style='color:#8b949e;font-size:13px;line-height:1.7;'>封禁原因：<b style='color:#e6edf3;'>{$reason}</b></p>
            <p style='color:#6e7681;font-size:12px;margin-top:16px;'>如有异议，请联系管理员。</p>
            </div></body></html>";
            exit;
        }
    }

    $n_res = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $uid_h AND is_read = 0");
    if ($n_res) $unread_count = (int)$n_res->fetch_assoc()['cnt'];
    if ($current_role === 'admin' || $current_role === 'owner') {
        $p_res = $conn->query("SELECT COUNT(*) as cnt FROM posts WHERE status='待审核'");
        if ($p_res) $pending_posts = (int)$p_res->fetch_assoc()['cnt'];
    }
}
?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= $base ?>style.css">
<style>
    .main-navbar {
        background: #0d1117;
        border-bottom: 1px solid #30363d;
        padding: 0 20px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 1000;
        font-family: "Microsoft YaHei", sans-serif;
    }
    .nav-logo {
        font-size: 18px;
        font-weight: 700;
        color: #3fb950;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: .5px;
        font-family: "Courier New", monospace;
    }
    .nav-logo:hover { color: #5fdd70; }
    .nav-logo-icon {
        width: 28px; height: 28px;
        background: rgba(63,185,80,.15);
        border: 1px solid rgba(63,185,80,.3);
        border-radius: 4px;
        display: flex; align-items: center; justify-content: center;
        font-size: 14px;
    }
    .nav-links {
        display: flex;
        gap: 16px;
        align-items: center;
    }
    .nav-item {
        text-decoration: none;
        color: #8b949e;
        font-size: 13px;
        transition: color .15s;
        display: flex;
        align-items: center;
        gap: 4px;
        position: relative;
    }
    .nav-item:hover { color: #e6edf3; }
    .admin-tag {
        background: rgba(248,81,73,.12);
        color: #f85149;
        padding: 3px 10px;
        border-radius: 4px;
        border: 1px solid rgba(248,81,73,.3);
        font-weight: 700;
        font-size: 12px;
    }
    .admin-tag:hover { color: #f85149; background: rgba(248,81,73,.2); }
    .nav-search {
        display: flex;
        align-items: center;
        background: #161b22;
        border: 1px solid #30363d;
        padding: 4px 12px;
        border-radius: 4px;
        gap: 6px;
        transition: border-color .2s;
    }
    .nav-search:focus-within { border-color: #3fb950; }
    .nav-search input {
        border: none;
        background: transparent;
        outline: none;
        font-size: 13px;
        width: 140px;
        color: #e6edf3;
        font-family: inherit;
        padding: 0;
    }
    .nav-search button {
        border: none; background: none; cursor: pointer;
        color: #6e7681; font-size: 13px; padding: 0; line-height: 1;
        transition: color .15s;
    }
    .nav-search button:hover { color: #3fb950; }
    .user-avatar-small {
        width: 26px; height: 26px;
        border-radius: 50%; object-fit: cover;
        border: 1px solid #30363d;
    }
    .notif-badge {
        position: absolute;
        top: -6px; right: -8px;
        background: #f85149;
        color: #fff;
        border-radius: 10px;
        min-width: 16px; height: 16px;
        font-size: 10px;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700;
        padding: 0 3px;
        font-family: "Courier New", monospace;
    }
    .nav-divider { width: 1px; height: 20px; background: #30363d; }

    /* ── 汉堡按钮（仅移动端） ── */
    .nav-hamburger {
        display: none;
        flex-direction: column;
        gap: 5px;
        cursor: pointer;
        padding: 6px;
        border-radius: 4px;
        border: 1px solid #30363d;
        background: transparent;
        transition: border-color .15s;
    }
    .nav-hamburger:hover { border-color: #3fb950; }
    .nav-hamburger span {
        display: block; width: 20px; height: 2px;
        background: #8b949e; border-radius: 2px; transition: .2s;
    }
    .nav-hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); background: #3fb950; }
    .nav-hamburger.open span:nth-child(2) { opacity: 0; }
    .nav-hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); background: #3fb950; }

    /* 搜索图标按钮（移动端直接可见） */
    .nav-search-icon {
        display: none;
        align-items: center;
        justify-content: center;
        width: 34px; height: 34px;
        color: #8b949e; font-size: 17px;
        border: 1px solid #30363d; border-radius: 4px;
        text-decoration: none; transition: .15s;
    }
    .nav-search-icon:hover { border-color: #3fb950; color: #3fb950; }

    @media (max-width: 768px) {
        .nav-hamburger { display: flex; }
        .nav-search-icon { display: flex; }
        .nav-search { display: none !important; }
        .nav-divider { display: none; }
        .nav-links {
            display: none;
            position: absolute;
            top: 56px; left: 0; right: 0;
            background: #0d1117;
            border-bottom: 1px solid #30363d;
            padding: 12px 20px 16px;
            flex-direction: column;
            gap: 4px;
            z-index: 999;
        }
        .nav-links.open { display: flex; }
        .nav-item { padding: 10px 0; font-size: 14px; border-bottom: 1px solid #21262d; }
        .nav-item:last-child { border-bottom: none; }
        .btn-publish-nav { align-self: flex-start; margin: 6px 0; }
        .admin-tag { align-self: flex-start; }
    }
</style>

<nav class="main-navbar" style="position:relative;">
    <a href="<?= $base ?>index.php" class="nav-logo">
        <span class="nav-logo-icon">♪</span> MUSE
    </a>

    <!-- 移动端右侧按钮组 -->
    <div style="display:flex;align-items:center;gap:6px;">
        <a href="<?= $base ?>search.php" class="nav-search-icon" title="搜索">⌕</a>
        <button class="nav-hamburger" id="nav-hamburger" aria-label="菜单" onclick="toggleNavMenu()">
            <span></span><span></span><span></span>
        </button>
    </div>

    <div class="nav-links" id="nav-links">
        <form action="<?= $base ?>search.php" method="GET" class="nav-search">
            <input type="text" name="keyword" placeholder="搜索...">
            <button type="submit">⌕</button>
        </form>

        <a href="<?= $base ?>categories.php" class="nav-item">分区</a>
        <a href="<?= $base ?>square.php" class="nav-item">广场</a>
        <div class="nav-divider"></div>

        <?php if (in_array($current_role, ['admin', 'owner'])): ?>
            <a href="<?= $base ?>pages/admin_categories.php" class="nav-item" style="font-size:12px;color:#6e7681;">分区</a>
            <a href="<?= $base ?>pages/admin.php" class="nav-item admin-tag">管理后台
                <?php if ($pending_posts > 0): ?>
                    <span class="notif-badge"><?= $pending_posts > 99 ? '99+' : $pending_posts ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        <?php if (in_array($current_role, ['admin', 'owner']) || !empty($_SESSION['is_cs'])): ?>
            <a href="<?= $base ?>pages/cs_panel.php" class="nav-item" style="font-size:12px;color:#a78bfa;">客服后台</a>
        <?php endif; ?>

        <div class="nav-divider"></div>

        <?php if ($is_logged_in): ?>
            <a href="<?= $base ?>pages/publish.php" class="btn-publish-nav">+ 发帖</a>
            <a href="<?= $base ?>pages/notifications.php" class="nav-item" title="消息中心" style="position:relative;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <?php if ($unread_count > 0): ?>
                    <span class="notif-badge"><?= $unread_count > 99 ? '99+' : $unread_count ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $base ?>pages/profile.php" class="nav-item">
                <img src="<?= $base ?>uploads/avatars/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default.png') ?>"
                     class="user-avatar-small"
                     onerror="this.onerror=null;this.src='<?= $base ?>uploads/avatars/default.png'">
                <span style="color:#e6edf3; font-size:13px;"><?= htmlspecialchars($_SESSION['username']) ?></span>
                <?php if (!empty($_SESSION['mid'])): ?>
                <span style="font-size:10px;color:#6e7681;font-family:'Courier New',monospace;letter-spacing:.5px;">·<?= htmlspecialchars($_SESSION['mid']) ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $base ?>pages/settings.php" class="nav-item" title="设置" style="font-size:12px;">设置</a>
            <a href="<?= $base ?>pages/logout.php" class="nav-item" style="font-size:12px;">退出</a>
        <?php else: ?>
            <a href="<?= $base ?>pages/login.php" class="nav-item">登录</a>
            <a href="<?= $base ?>pages/register.php" class="btn btn-green" style="padding:5px 14px; font-size:13px;">注册</a>
        <?php endif; ?>
    </div>
</nav>
<script>
function toggleNavMenu() {
    const btn   = document.getElementById('nav-hamburger');
    const links = document.getElementById('nav-links');
    btn.classList.toggle('open');
    links.classList.toggle('open');
}
// 点击菜单外部关闭
document.addEventListener('click', function(e) {
    const btn   = document.getElementById('nav-hamburger');
    const links = document.getElementById('nav-links');
    if (btn && links && !btn.contains(e.target) && !links.contains(e.target)) {
        btn.classList.remove('open');
        links.classList.remove('open');
    }
});
</script>
<?php if ($is_logged_in): ?>
<style>
.cs-fab {
    position: fixed;
    bottom: 24px;
    left: 20px;
    z-index: 900;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #161b22;
    border: 1px solid #30363d;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #6e7681;
    font-size: 18px;
    transition: .2s;
    box-shadow: 0 2px 12px rgba(0,0,0,.4);
}
.cs-fab:hover {
    border-color: #3fb950;
    color: #3fb950;
    box-shadow: 0 0 14px rgba(63,185,80,.2);
}
.cs-fab-tip {
    position: absolute;
    left: 50px;
    bottom: 50%;
    transform: translateY(50%);
    background: #161b22;
    border: 1px solid #30363d;
    color: #8b949e;
    font-size: 11px;
    font-family: "Courier New", monospace;
    padding: 4px 10px;
    border-radius: 4px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity .15s;
}
.cs-fab:hover .cs-fab-tip { opacity: 1; }
</style>
<a href="<?= $base ?>pages/cs_chat.php" class="cs-fab" title="联系客服">
    💬
    <span class="cs-fab-tip">联系客服</span>
</a>
<?php endif; ?>
