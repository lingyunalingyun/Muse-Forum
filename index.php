<?php 
// 1. 必须在任何 HTML 输出之前开启 session
session_start(); 

// 2. 连接数据库
require_once __DIR__ . '/config.php';

// 3. 实时身份同步逻辑 - 已将 nickname 统一修改为 username
if (isset($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);
    // 修正了之前代码中 u'username 的语法错误
    $user_check = $conn->query("SELECT role, username FROM users WHERE id = $uid");
    if ($user_data = $user_check->fetch_assoc()) {
        $_SESSION['role'] = $user_data['role']; 
        $_SESSION['username'] = $user_data['username']; 
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>网友分享展示墙</title>
    <style>
        body { background: #f4f7f6; font-family: "Microsoft YaHei", sans-serif; margin: 0; padding-bottom: 20px; }
        
        /* 顶部导航栏样式 */
        .navbar { background: #fff; padding: 10px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .nav-logo { font-weight: bold; font-size: 18px; color: #28a745; text-decoration: none; }
        .nav-user { display: flex; align-items: center; }
        .nav-user a { text-decoration: none; font-size: 14px; margin-left: 15px; }
        
        .admin-link { color: #e67e22 !important; font-weight: bold; border: 1px solid #e67e22; padding: 3px 8px; border-radius: 4px; }
        .btn-reg { background: #28a745; color: white !important; padding: 5px 12px; border-radius: 4px; }

        .main-content { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); max-width: 800px; margin: 30px auto; }
        
        /* 帖子卡片样式 */
        .post-card { background: #fff; margin-bottom: 20px; padding: 20px; border-radius: 8px; border: 1px solid #eee; cursor: pointer; transition: all 0.3s; }
        .post-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-color: #28a745; }
        .post-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 10px; }
        .post-excerpt { color: #666; line-height: 1.6; margin-bottom: 15px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; }
        .post-meta { font-size: 12px; color: #999; display: flex; justify-content: space-between; align-items: center; }
        .author-info { color: #28a745; font-weight: bold; }
        
        .pub-section { margin-bottom: 40px; text-align: center; }
        .goto-pub-btn { background: #28a745; color: white; border: none; padding: 12px 40px; border-radius: 30px; cursor: pointer; font-weight: bold; font-size: 16px; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-logo">🌟 社区展示墙</a>
    <div class="nav-user">
        <?php if(isset($_SESSION['user_id'])): ?>
            <span style="font-size: 14px;">你好，<strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="pages/admin.php" class="admin-link">⚙️ 管理后台</a>
            <?php endif; ?>
            <a href="pages/profile.php" style="color: #007bff;">个人中心</a>
            <a href="pages/logout.php" style="color: #dc3545;">退出</a>
        <?php else: ?>
            <a href="pages/login.php" style="color: #007bff;">登录</a>
            <a href="pages/register.php" class="btn-reg">注册账号</a>
        <?php endif; ?>
    </div>
</nav>

<div class="main-content">
    <div class="pub-section">
        <h2>🌈 分享你的见解</h2>
        <a href="pages/publish.php" class="goto-pub-btn">+ 发布图文帖子</a>
    </div>
    
    <hr style="margin-bottom: 30px; border: 0; border-top: 1px solid #eee;">
    
    <h3>✨ 精选内容</h3>
    <div id="feed">
        <?php
        $sql = "SELECT p.id, p.title, p.content, p.created_at, u.username
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.status = '已发布'
                ORDER BY p.id DESC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                // 生成摘要
                $clean_text = strip_tags($row['content']);
                $excerpt = mb_substr($clean_text, 0, 100) . (mb_strlen($clean_text) > 100 ? '...' : '');
                $display_title = !empty($row['title']) ? $row['title'] : $row['username'] . ' 的分享';

                echo '<div class="post-card" onclick="location.href=\'pages/post.php?id=' . $row['id'] . '\'">';
                echo '    <div class="post-title">' . htmlspecialchars($display_title) . '</div>';
                echo '    <div class="post-excerpt">' . $excerpt . '</div>';
                echo '    <div class="post-meta">';
                echo '        <span>作者：<span class="author-info">' . htmlspecialchars($row['username']) . '</span></span>';
                echo '        <span>' . $row['created_at'] . '</span>';
                echo '    </div>';
                echo '</div>';
            }
        } else {
            echo '<p style="text-align:center; color:#999; margin-top:20px;">暂无精选内容</p>';
        }
        ?>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>