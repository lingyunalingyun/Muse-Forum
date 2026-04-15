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
        .profile-container { max-width: 800px; margin: 24px auto; padding: 0 16px; }
        .user-card { background: #161b22; border: 1px solid #30363d; border-radius: 6px; padding: 24px; display: flex; align-items: center; gap: 24px; }
        @media(max-width:600px){
            .profile-container { padding: 0 10px; margin: 12px auto; }
            .user-card { flex-direction: column; align-items: center; text-align: center; padding: 20px 16px; gap: 16px; }
            .user-meta-row { justify-content: center; }
            .grid { grid-template-columns: repeat(3, 1fr) !important; }
            .stats-bar .stat-item strong { font-size: 16px; }
        }
        .avatar { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 2px solid #30363d; cursor: pointer; transition: .2s; flex-shrink: 0; display: block; }
        .avatar:hover { border-color: #3fb950; box-shadow: 0 0 16px rgba(63,185,80,.25); }
        .user-info h2 { margin: 0 0 6px; display: flex; align-items: center; gap: 10px; color: #e6edf3; font-size: 18px; }
        .role-badge { font-size: 11px; padding: 2px 8px; border-radius: 4px; font-weight: 700; }
        .user-info p { color: #8b949e; margin: 0 0 10px; font-size: 13px; }
        .user-meta-row { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 12px; }
        .user-meta-item { font-size: 12px; color: #6e7681; font-family: "Courier New", monospace; }
        .user-meta-item span { color: #8b949e; }
        .btn-edit { font-size: 12px; color: #3fb950; text-decoration: none; border: 1px solid rgba(63,185,80,.4); padding: 5px 14px; border-radius: 4px; transition: .2s; font-weight: 600; display: inline-flex; align-items: center; }
        .btn-edit:hover { background: rgba(63,185,80,.1); color: #3fb950; }
        .btn-edit.following { color: #6e7681 !important; border-color: #30363d !important; }
        .btn-edit.following:hover { color: #f85149 !important; border-color: rgba(248,81,73,.4) !important; }

        .stats-bar { display: flex; background: #161b22; border: 1px solid #30363d; border-radius: 6px; margin-top: 12px; }
        .stat-item { flex: 1; text-align: center; padding: 14px; border-right: 1px solid #30363d; }
        .stat-item:last-child { border: none; }
        .stat-item strong { font-size: 20px; color: #e6edf3; display: block; font-family: "Courier New", monospace; }
        .stat-item small { color: #6e7681; font-size: 11px; letter-spacing: .5px; text-transform: uppercase; font-family: "Courier New", monospace; }

        .inventory-section { background: #161b22; border: 1px solid #30363d; border-radius: 6px; margin-top: 12px; padding: 18px 20px; }
        .inventory-section h3 { font-size: 11px; font-weight: 700; color: #6e7681; letter-spacing: 1.5px; text-transform: uppercase; font-family: "Courier New", monospace; margin: 0 0 14px; }
        .grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
        .item-slot { aspect-ratio: 1; background: #1c2128; border: 1px solid #30363d; border-radius: 4px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 11px; color: #6e7681; transition: .2s; }
        .item-slot:hover { border-color: #3fb950; color: #3fb950; }

        .post-section { background: #161b22; border: 1px solid #30363d; border-radius: 6px; margin-top: 12px; padding: 18px 20px; }
        .post-section h3 { font-size: 11px; font-weight: 700; color: #6e7681; letter-spacing: 1.5px; text-transform: uppercase; font-family: "Courier New", monospace; margin: 0 0 12px; }
        .post-list { margin-top: 0; }
        .post-item { display: block; text-decoration: none; color: #8b949e; padding: 10px 0; border-bottom: 1px solid #21262d; transition: .15s; }
        .post-item:last-child { border-bottom: none; }
        .post-item:hover { color: #e6edf3; padding-left: 6px; }
        .post-item h4 { margin: 0 0 4px; font-size: 14px; color: #c9d1d9; font-weight: 600; }
        .post-item:hover h4 { color: #3fb950; }
        .post-item .post-meta { font-size: 11px; color: #6e7681; display: flex; justify-content: space-between; font-family: "Courier New", monospace; }
        @keyframes zoomIn { from{transform:scale(.8);opacity:0} to{transform:scale(1);opacity:1} }
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
                    <span class="role-badge" style="background: rgba(248,81,73,.15); color: #f85149; border: 1px solid rgba(248,81,73,.3);">管理员</span>
                <?php else: ?>
                    <span class="role-badge" style="background: rgba(88,166,255,.15); color: #58a6ff; border: 1px solid rgba(88,166,255,.3);">普通用户</span>
                <?php endif; ?>
            </h2>
            <p><?php echo htmlspecialchars($user['signature'] ?: "// 这个人很懒，什么都没留下"); ?></p>
            <div class="user-meta-row">
                <span class="user-meta-item">生日 <span><?php echo $user['birthday'] ?: "未设置"; ?></span></span>
                <span class="user-meta-item">积分 <span style="color:#3fb950;"><?php echo $user['points']; ?></span></span>
                <span class="user-meta-item">性别 <span><?php echo $user['gender'] ?: "未设置"; ?></span></span>
            </div>
            <?php if($is_mine): ?>
                <a href="edit_profile.php" class="btn-edit">编辑个人资料</a>
            <?php else: ?>
                <button class="btn-edit <?php echo $is_following ? 'following' : ''; ?>"
                        id="follow-btn-profile"
                        onclick="toggleFollow(<?php echo $user['id']; ?>)"
                        style="cursor: pointer; border: none; font-family: inherit;">
                    <?php echo $is_following ? '已关注' : '+ 关注'; ?>
                </button>
                <?php if($my_id > 0): ?>
                    <a href="notifications.php?tab=message&user_id=<?php echo $user['id']; ?>" class="btn-edit"
                       style="margin-left: 8px;">私信</a>
                <?php endif; ?>
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
            <div style="text-align: center; color: #6e7681; padding: 30px; font-family: 'Courier New', monospace; font-size: 13px;">
                // 暂无帖子
            </div>
        <?php endif; ?>

        <?php if($is_mine): ?>
            <a href="publish.php" class="post-item" style="text-align: center; border-style: dashed; color: #3fb950;">
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
