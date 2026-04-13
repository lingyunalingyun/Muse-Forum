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
if ($is_logged_in && isset($conn)) {
    $uid_h = intval($_SESSION['user_id']);
    $n_res = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $uid_h AND is_read = 0");
    if ($n_res) $unread_count = (int)$n_res->fetch_assoc()['cnt'];
}
?>
<style>
    .main-navbar {
        background: #ffffff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        padding: 0 20px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 1000;
        font-family: "Microsoft YaHei", sans-serif;
    }
    .nav-logo {
        font-size: 22px;
        font-weight: bold;
        color: #28a745;
        text-decoration: none;
        display: flex;
        align-items: center;
    }
    .nav-links {
        display: flex;
        gap: 20px;
        align-items: center;
    }
    .nav-item {
        text-decoration: none;
        color: #555;
        font-size: 14px;
        transition: 0.3s;
        display: flex;
        align-items: center;
        gap: 5px;
        position: relative;
    }
    .nav-item:hover { color: #28a745; }
    .admin-tag {
        background: #fff0f0;
        color: #d63031;
        padding: 2px 8px;
        border-radius: 4px;
        border: 1px solid #ff7675;
        font-weight: bold;
    }
    .nav-search {
        display: flex;
        background: #f1f3f4;
        padding: 5px 12px;
        border-radius: 20px;
    }
    .nav-search input {
        border: none;
        background: transparent;
        outline: none;
        font-size: 13px;
        width: 130px;
    }
    .user-avatar-small {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        object-fit: cover;
        background: #eee;
    }
    .notif-badge {
        position: absolute;
        top: -7px;
        right: -10px;
        background: #e74c3c;
        color: white;
        border-radius: 10px;
        min-width: 18px;
        height: 18px;
        font-size: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        padding: 0 4px;
        box-sizing: border-box;
        line-height: 1;
    }
</style>

<nav class="main-navbar">
    <a href="<?= $base ?>index.php" class="nav-logo">
        <span style="margin-right:8px;">🌟</span> 社区论坛
    </a>

    <div class="nav-links">

        <form action="<?= $base ?>search.php" method="GET" class="nav-search">
            <input type="text" name="keyword" placeholder="搜索帖子...">
            <button type="submit" style="border:none; background:none; cursor:pointer;">🔍</button>
        </form>

        <?php if ($current_role === 'admin'): ?>
            <a href="<?= $base ?>pages/admin.php" class="nav-item admin-tag">🛠️ 管理</a>
        <?php endif; ?>

        <?php if ($is_logged_in): ?>
            <a href="<?= $base ?>pages/notifications.php" class="nav-item" title="消息中心" style="font-size:20px;">
                🔔
                <?php if ($unread_count > 0): ?>
                    <span class="notif-badge"><?= $unread_count > 99 ? '99+' : $unread_count ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $base ?>pages/profile.php" class="nav-item" style="font-weight:bold;">
                <img src="<?= $base ?>uploads/avatars/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default.png') ?>"
                     class="user-avatar-small"
                     onerror="this.onerror=null;this.src='uploads/avatars/default.png'">
                <?= htmlspecialchars($_SESSION['username']) ?>
            </a>
            <a href="<?= $base ?>pages/logout.php" class="nav-item" style="color:#999;">退出</a>
        <?php else: ?>
            <a href="<?= $base ?>pages/login.php" class="nav-item">登录</a>
            <a href="<?= $base ?>pages/register.php" class="nav-item">注册</a>
        <?php endif; ?>

    </div>
</nav>
