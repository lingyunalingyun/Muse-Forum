<?php
session_start();
require_once __DIR__ . '/../config.php';

// --- 核心修复：动态获取要查看的用户 ID ---
if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $view_uid = intval($_GET['id']);
} elseif (isset($_SESSION['user_id'])) {
    $view_uid = intval($_SESSION['user_id']);
} else {
    header("Location: login.php");
    exit;
}

// 使用 $view_uid 获取资料
$user_res = $conn->query("SELECT * FROM users WHERE id = $view_uid");
$user = $user_res->fetch_assoc();
$user_posts_res = $conn->query("SELECT id, title, content, created_at FROM posts WHERE user_id = $view_uid ORDER BY created_at DESC LIMIT 10");

if (!$user) {
    die("该用户不存在或已被注销。 <a href='../index.php'>返回首页</a>");
}

// 2. 获取该用户的背包物品数据
$inventory = $conn->query("SELECT * FROM user_inventory WHERE user_id = $view_uid");

// 3. 统计该用户的帖子数量
$post_count_res = $conn->query("SELECT count(*) as total FROM posts WHERE user_id = $view_uid");
$post_count = $post_count_res ? $post_count_res->fetch_assoc()['total'] : 0;
$follow_count_res = $conn->query("SELECT count(*) as total FROM follows WHERE follower_id = $view_uid");
$follow_count = $follow_count_res ? $follow_count_res->fetch_assoc()['total'] : 0;
$fans_count_res = $conn->query("SELECT count(*) as total FROM follows WHERE followed_id = $view_uid");
$fans_count = $fans_count_res ? $fans_count_res->fetch_assoc()['total'] : 0;

// 当前访问者是否为页面主人
$is_mine = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_uid);

// --- 检查当前登录用户是否关注了正在查看的用户 ---
$is_following = false;
$my_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

if ($my_id > 0 && $my_id != $view_uid) {
    $check_f = $conn->query("SELECT id FROM follows WHERE follower_id = $my_id AND followed_id = $view_uid");
    if ($check_f && $check_f->num_rows > 0) {
        $is_following = true;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>个人中心 - <?php echo htmlspecialchars($user['username']); ?></title>
    <style>
        body { background: #f0f2f5; font-family: "Microsoft YaHei", sans-serif; margin: 0; padding-bottom: 40px; }
        .profile-container { max-width: 800px; margin: 20px auto; }

        /* 顶部资料卡 */
        .user-card { background: white; padding: 30px; border-radius: 15px; display: flex; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }

        /* 头像样式修复 */
        .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin-right: 25px;
            border: 4px solid #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            object-fit: cover;
            display: block;
        }

        .user-info h2 { margin: 0 0 10px 0; display: flex; align-items: center; gap: 10px; }
        .role-badge { font-size: 12px; padding: 2px 8px; border-radius: 4px; font-weight: bold; }

        /* 数据统计栏 */
        .stats-bar { display: flex; background: white; margin-top: 15px; padding: 15px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .stat-item { flex: 1; border-right: 1px solid #eee; }
        .stat-item:last-child { border: none; }
        .stat-item strong { font-size: 18px; color: #333; }
        .stat-item small { color: #999; }

        /* 背包区域 */
        .inventory-section { background: white; margin-top: 15px; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-top: 15px; }
        .item-slot { aspect-ratio: 1; background: #f8f9fa; border: 1px solid #eee; border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 12px; transition: 0.3s; }
        .item-slot:hover { border-color: #28a745; background: #fff; }

        /* 按钮美化 */
        .btn-edit { font-size: 12px; color: #28a745; text-decoration: none; border: 1px solid #28a745; padding: 4px 12px; border-radius: 20px; transition: 0.3s; }
        .btn-edit:hover { background: #28a745; color: #fff; }
        .btn-edit.following { background-color: #f0f0f0 !important; color: #888 !important; box-shadow: none !important; }
        .btn-edit.following:hover { background-color: #e5e5e5 !important; color: #ff7675 !important; }

        #image-overlay {
        display: none; display: flex; }
        #full-image { animation: zoomIn 0.2s ease-out; }

        .post-section { background: white; margin-top: 15px; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .post-list { margin-top: 15px; }
        .post-item { display: block; text-decoration: none; color: #333; padding: 12px; border-bottom: 1px solid #f5f5f5; transition: 0.2s; }
        .post-item:last-child { border-bottom: none; }
        .post-item:hover { background: #f9f9f9; transform: translateX(5px); }
        .post-item h4 { margin: 0 0 5px 0; font-size: 15px; color: #2d3436; }
        .post-item .post-meta { font-size: 12px; color: #999; display: flex; justify-content: space-between; }
    @keyframes zoomIn {
        from { transform: scale(0.8); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="profile-container">
    <div class="user-card">
        <img src="../uploads/avatars/<?php echo $user['avatar'] ?: 'default.png'; ?>"
            class="avatar"
            style="cursor: pointer;"
            onclick="showFullImage(this.src)"
            title="查看大图"
            onerror="this.src='../uploads/avatars/default.png'">

        <div class="user-info">
            <h2>
                <?php echo htmlspecialchars($user['username']); ?>
                <?php if(isset($user['role']) && $user['role'] === 'admin'): ?>
                    <span class="role-badge" style="background: #fff0f0; color: #d63031; border: 1px solid #ff7675;">管理员</span>
                <?php else: ?>
                    <span class="role-badge" style="background: #e3f2fd; color: #1976d2; border: 1px solid #bbdefb;">普通用户</span>
                <?php endif; ?>
            </h2>
            <p style="color: #666; margin-bottom: 10px;"><?php echo htmlspecialchars($user['signature'] ?: "这个人很懒，什么都没写~"); ?></p>
            <div style="margin-bottom: 12px;">
                <small style="color: #999;">🎂 生日：<?php echo $user['birthday'] ?: "未设置"; ?></small>
                <small style="color: #999; margin-left: 15px;">💰 积分：<?php echo $user['points']; ?></small>
                <small style="color: #999; margin-left: 15px;">👶 性别：<?php echo $user['gender'] ?: "未设置" ?></small>
            </div>
            <?php if($is_mine): ?>
                <a href="edit_profile.php" class="btn-edit">编辑个人资料</a>
            <?php else: ?>
                <button class="btn-edit <?php echo $is_following ? 'following' : ''; ?>"
                        onclick="toggleFollow(<?php echo $user['id']; ?>)"
                        style="cursor: pointer; border: none; font-family: inherit;">
                    <?php echo $is_following ? '已关注' : '+ 关注'; ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="stats-bar">
        <div class="stat-item">
            <strong><?php echo $post_count; ?></strong><br>
            <small>帖子</small>
        </div>
        <div class="stat-item">
            <strong><?php echo $follow_count; ?></strong><br>
            <small>关注</small>
        </div>
        <div class="stat-item">
            <strong id="fans-count"><?php echo $fans_count; ?></strong><br>
            <small>粉丝</small>
        </div>
    </div>

    <div id="image-overlay" onclick="this.style.display='none'" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; cursor:zoom-out; align-items:center; justify-content:center;">
        <img id="full-image" src="" style="max-width:90%; max-height:90%; border-radius:8px; box-shadow:0 0 20px rgba(0,0,0,0.5); transform: scale(1); transition: transform 0.3s;">
    </div>

    <div class="post-section">
    <h3 style="margin:0;">📝 <?php echo $is_mine ? '我的帖子' : 'TA的帖子'; ?></h3>
    <p style="font-size: 13px; color: #999; margin-top: 5px;">共发布了 <?php echo $post_count; ?> 篇内容</p>

    <div class="post-list">
        <?php if($user_posts_res && $user_posts_res->num_rows > 0): ?>
            <?php while($p = $user_posts_res->fetch_assoc()): ?>
                <a href="post.php?id=<?php echo $p['id']; ?>" class="post-item">
                    <h4><?php echo htmlspecialchars($p['title'] ?: mb_substr(strip_tags($p['content']), 0, 30) . '...'); ?></h4>
                    <div class="post-meta">
                        <span>点击查看详情</span>
                        <span><?php echo date('Y-m-d', strtotime($p['created_at'])); ?></span>
                    </div>
                </a>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align: center; color: #ccc; padding: 30px;">
                这里空空如也，还没有发过帖子哦~
            </div>
        <?php endif; ?>

        <?php if($is_mine): ?>
            <a href="publish.php" class="post-item" style="text-align: center; border-style: dashed; color: #28a745;">
                <strong>+ 发布新帖子</strong>
            </a>
        <?php endif; ?>
    </div>
</div>
</div>

<script>
function toggleFollow(authorId) {
    const myId = <?php echo isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0; ?>;
    if(myId === 0) return alert("请先登录");
    if(myId === authorId) return alert("不能关注自己");

    let btn = event.currentTarget;
    let formData = new FormData();
    formData.append('following_id', authorId);

    fetch('../actions/follow_toggle.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'followed') {
            btn.innerText = '已关注';
            btn.classList.add('following');
        } else if(data.status === 'unfollowed') {
            btn.innerText = '+ 关注';
            btn.classList.remove('following');
        }
    })
    .catch(err => console.error('Error:', err));
}

function showFullImage(src) {
    const overlay = document.getElementById('image-overlay');
    const fullImg = document.getElementById('full-image');

    fullImg.src = src;
    overlay.style.display = 'flex';
}

// 点击遮罩层关闭
document.getElementById('image-overlay').onclick = function() {
    this.style.display = 'none';
};

// 按 ESC 键关闭
document.addEventListener('keydown', function(e) {
    if (e.key === "Escape") {
        document.getElementById('image-overlay').style.display = 'none';
    }
});
</script>
</body>
</html>
<?php
$conn->close();
?>
