<?php
// 如果没有开启 session，在这里开启，确保能读到用户信息
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_role = $_SESSION['role'] ?? 'user';
$is_logged_in = isset($_SESSION['user_id']);
?>
<style>
    /* 导航条基础样式 */
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

    /* 左侧 Logo */
    .nav-logo {
        font-size: 22px;
        font-weight: bold;
        color: #28a745;
        text-decoration: none;
        display: flex;
        align-items: center;
    }

    /* 右侧功能区 */
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
    }

    .nav-item:hover {
        color: #28a745;
    }

    /* 管理员专属标签样式 */
    .admin-tag {
        background: #fff0f0;
        color: #d63031;
        padding: 2px 8px;
        border-radius: 4px;
        border: 1px solid #ff7675;
        font-weight: bold;
    }

    /* 搜索框简易样式 */
    .nav-search {
        display: flex;
        background: #f1f3f4;
        padding: 5px 12px;
        border-radius: 20px;
        margin-left: 10px;
    }
    .nav-search input {
        border: none;
        background: transparent;
        outline: none;
        font-size: 13px;
        width: 120px;
    }

    /* 用户头像简易缩略图 */
    .user-avatar-small {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        object-fit: cover;
        background: #eee;
    }
</style>

<nav class="main-navbar">
    <a href="../index.php" class="nav-logo">
        <span style="margin-right:8px;">🌟</span> 社区论坛
    </a>

    <div class="nav-links">

        <form action="../search.php" method="GET" class="nav-search">
            <input type="text" name="keyword" placeholder="搜索帖子...">
            <button type="submit" style="border:none; background:none; cursor:pointer;">🔍</button>
        </form>

        <a href="../chat.php" class="nav-item">💬 聊天</a>

        <a href="../inventory.php" class="nav-item">🎒 背包</a>

        <?php if ($current_role === 'admin'): ?>
            <a href="admin.php" class="nav-item admin-tag">🛠️ 帖子管理</a>
        <?php endif; ?>

        <?php if ($is_logged_in): ?>
            <a href="profile.php" class="nav-item" style="font-weight: bold;">
                <img src="../uploads/avatars/<?php echo $_SESSION['avatar'] ?? 'default.png'; ?>"
                     class="user-avatar-small"
                     onerror="this.src='https://via.placeholder.com/28'">
                <?php echo htmlspecialchars($_SESSION['username']); ?>
            </a>
            <a href="logout.php" class="nav-item" style="color:#999;">退出</a>
        <?php else: ?>
            <a href="login.php" class="nav-item">登录</a>
            <a href="register.php" class="nav-item">注册</a>
        <?php endif; ?>

    </div>
</nav>
