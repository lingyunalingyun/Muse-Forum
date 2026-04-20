<?php
/**
 * profile.php — 用户主页（个人资料页）
 *
 * 功能：展示指定用户的基本信息、帖子列表、背包物品、粉丝/关注数，支持关注/取消关注操作
 * 读写表：users、posts、user_inventory、follows
 * 权限：公开（查看他人）/ 需登录（查看自己或执行关注操作）
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exp_helper.php';
ensure_user_columns($conn);

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

// 拉黑状态（双向）
$i_blocked_them = false;
$they_blocked_me = false;
if ($my_id > 0 && !$is_mine) {
    $r = $conn->query("SELECT id FROM user_blocks WHERE blocker_id=$my_id AND blocked_id=$view_uid");
    $i_blocked_them = $r && $r->num_rows > 0;
    $r2 = $conn->query("SELECT id FROM user_blocks WHERE blocker_id=$view_uid AND blocked_id=$my_id");
    $they_blocked_me = $r2 && $r2->num_rows > 0;
}

// 收藏夹 & 点赞夹（仅自己可见）
$favs_list  = [];
$likes_list = [];
if ($is_mine) {
    $r = $conn->query("SELECT p.id, p.title, p.created_at, u.username
        FROM post_favs pf
        JOIN posts p ON p.id = pf.post_id
        JOIN users u ON u.id = p.user_id
        WHERE pf.user_id = $view_uid ORDER BY pf.id DESC LIMIT 50");
    if ($r) $favs_list = $r->fetch_all(MYSQLI_ASSOC);

    $r = $conn->query("SELECT p.id, p.title, p.created_at, u.username
        FROM post_likes pl
        JOIN posts p ON p.id = pl.post_id
        JOIN users u ON u.id = p.user_id
        WHERE pl.user_id = $view_uid ORDER BY pl.id DESC LIMIT 50");
    if ($r) $likes_list = $r->fetch_all(MYSQLI_ASSOC);
}

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
        .mid-copy { cursor: pointer; color: #3fb950 !important; letter-spacing: 1px; border-bottom: 1px dashed rgba(63,185,80,.4); transition: opacity .15s; }
        .mid-copy:hover { opacity: .75; }
        .mid-copied { color: #58a6ff !important; border-bottom-color: rgba(88,166,255,.4) !important; }
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
            onerror=\"this.onerror=null;this.src='../uploads/avatars/default.png'\">

        <div class="user-info">
            <h2>
                <?php echo htmlspecialchars($user['username']); ?>
                <?= get_role_badge($user['role'] ?? 'user', !empty($user['is_banned'])) ?>
                <?php
                $lv = get_level_by_exp((int)($user['exp'] ?? 0));
                $lc = get_level_color($lv);
                $ln = get_level_name($lv);
                ?>
                <span class="role-badge" style="background:<?= $lc ?>22;color:<?= $lc ?>;border:1px solid <?= $lc ?>55;">Lv.<?= $lv ?> <?= $ln ?></span>
            </h2>
            <p><?php echo htmlspecialchars($user['signature'] ?: "// 这个人很懒，什么都没留下"); ?></p>
            <div class="user-meta-row">
                <span class="user-meta-item">MID <span class="mid-copy" id="mid-val" onclick="copyMid()" title="点击复制"><?= htmlspecialchars($user['mid'] ?? '—') ?></span></span>
                <span class="user-meta-item">生日 <span><?php echo $user['birthday'] ?: "未设置"; ?></span></span>
                <span class="user-meta-item">积分 <span style="color:#3fb950;"><?php echo $user['points']; ?></span></span>
                <span class="user-meta-item">性别 <span><?php echo $user['gender'] ?: "未设置"; ?></span></span>
            </div>

            <?php
            // 经验条
            $u_exp    = (int)($user['exp'] ?? 0);
            $u_level  = get_level_by_exp($u_exp);
            $u_color  = get_level_color($u_level);
            $u_name   = get_level_name($u_level);
            $prev_exp = get_prev_level_exp($u_level);
            $next_exp = get_next_level_exp($u_level);
            if ($next_exp !== null) {
                $bar_pct   = ($u_exp - $prev_exp) / ($next_exp - $prev_exp) * 100;
                $bar_pct   = max(0, min(100, $bar_pct));
                $exp_label = $u_exp . ' / ' . $next_exp . ' EXP';
                $exp_left  = '还差 ' . ($next_exp - $u_exp) . ' EXP 升级';
            } else {
                $bar_pct   = 100;
                $exp_label = $u_exp . ' EXP（满级）';
                $exp_left  = '';
            }
            ?>
            <div style="margin:10px 0 12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
                    <span style="font-size:12px;color:#8b949e;font-family:'Courier New',monospace;"><?= $exp_label ?></span>
                    <?php if ($exp_left): ?>
                    <span style="font-size:11px;color:#6e7681;"><?= $exp_left ?></span>
                    <?php endif; ?>
                </div>
                <div style="height:5px;background:#21262d;border-radius:3px;overflow:hidden;">
                    <div style="height:100%;width:<?= $bar_pct ?>%;background:<?= $u_color ?>;border-radius:3px;
                                box-shadow:0 0 6px <?= $u_color ?>88;transition:width .6s ease;"></div>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <?php if($is_mine): ?>
                <a href="settings.php" class="btn-edit">编辑个人资料</a>
            <?php else: ?>
                <?php if (!$they_blocked_me): ?>
                <button class="btn-edit <?php echo $is_following ? 'following' : ''; ?>"
                        id="follow-btn-profile"
                        onclick="toggleFollow(<?php echo $user['id']; ?>)"
                        style="cursor: pointer; border: none; font-family: inherit;">
                    <?php echo $is_following ? '已关注' : '+ 关注'; ?>
                </button>
                <?php endif; ?>
                <?php if($my_id > 0 && !$they_blocked_me): ?>
                    <a href="notifications.php?tab=message&user_id=<?php echo $user['id']; ?>" class="btn-edit">私信</a>
                <?php endif; ?>
                <?php if($my_id > 0): ?>
                <button id="block-btn"
                        onclick="toggleBlock(<?= $user['id'] ?>)"
                        style="cursor:pointer;border:1px solid <?= $i_blocked_them ? 'rgba(248,81,73,.4)' : '#30363d' ?>;
                               background:none;color:<?= $i_blocked_them ? '#f85149' : '#6e7681' ?>;
                               font-size:12px;padding:5px 12px;border-radius:4px;font-family:inherit;transition:.2s;">
                    <?= $i_blocked_them ? '已拉黑' : '拉黑' ?>
                </button>
                <?php if (!$is_mine): ?>
                <button onclick="openReportModal('user',<?= $user['id'] ?>)"
                        style="cursor:pointer;border:1px solid #30363d;background:none;color:#6e7681;
                               font-size:12px;padding:5px 12px;border-radius:4px;font-family:inherit;transition:.2s;"
                        onmouseover="this.style.borderColor='#f85149';this.style.color='#f85149';"
                        onmouseout="this.style.borderColor='#30363d';this.style.color='#6e7681';">
                    举报
                </button>
                <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            // 封禁按钮：admin/owner 可见，但不能操作同级或更高级
            $my_role     = $_SESSION['role'] ?? '';
            $target_role = $user['role'] ?? 'user';
            $can_ban = false;
            if (!$is_mine) {
                if ($my_role === 'owner') $can_ban = true;
                elseif ($my_role === 'admin' && $target_role === 'user') $can_ban = true;
            }
            $is_target_banned = !empty($user['is_banned']);
            if ($can_ban):
            ?>
                <button onclick="openBanModal(<?= $user['id'] ?>, <?= $is_target_banned ? 'true' : 'false' ?>, '<?= htmlspecialchars(addslashes($user['ban_reason'] ?? ''), ENT_QUOTES) ?>')"
                        style="cursor:pointer;border:1px solid <?= $is_target_banned ? 'rgba(63,185,80,.4)' : 'rgba(248,81,73,.4)' ?>;
                               background:transparent;color:<?= $is_target_banned ? '#3fb950' : '#f85149' ?>;
                               padding:5px 14px;border-radius:4px;font-size:12px;font-weight:600;font-family:inherit;transition:.2s;">
                    <?= $is_target_banned ? '解除封禁' : '封禁账号' ?>
                </button>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    $follows_res = $conn->query("SELECT u.id, u.username, u.avatar, u.role, u.exp, u.is_banned
        FROM follows f JOIN users u ON u.id = f.followed_id
        WHERE f.follower_id = $view_uid ORDER BY f.id DESC");
    $fans_res = $conn->query("SELECT u.id, u.username, u.avatar, u.role, u.exp, u.is_banned
        FROM follows f JOIN users u ON u.id = f.follower_id
        WHERE f.followed_id = $view_uid ORDER BY f.id DESC");
    $follows_list = $follows_res ? $follows_res->fetch_all(MYSQLI_ASSOC) : [];
    $fans_list    = $fans_res    ? $fans_res->fetch_all(MYSQLI_ASSOC)    : [];
    ?>
    <div class="stats-bar">
        <div class="stat-item">
            <strong><?php echo $post_count; ?></strong><br>
            <small>帖子</small>
        </div>
        <div class="stat-item" onclick="openUserList('follows')" style="cursor:pointer;" title="查看关注列表">
            <strong><?php echo $follow_count; ?></strong><br>
            <small>关注 &#9654;</small>
        </div>
        <div class="stat-item" onclick="openUserList('fans')" style="cursor:pointer;" title="查看粉丝列表">
            <strong id="fans-count"><?php echo $fans_count; ?></strong><br>
            <small>粉丝 &#9654;</small>
        </div>
    </div>

    <?php if (true): ?>
    <!-- 关注/粉丝列表弹窗 -->
    <div id="userlist-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9000;align-items:center;justify-content:center;">
        <div style="background:#161b22;border:1px solid #30363d;border-radius:6px;width:360px;max-width:92vw;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;">
            <div style="padding:16px 18px 12px;border-bottom:1px solid #21262d;display:flex;align-items:center;justify-content:space-between;">
                <span id="userlist-title" style="font-size:14px;font-weight:700;color:#e6edf3;font-family:'Courier New',monospace;"></span>
                <button onclick="closeUserList()" style="background:none;border:none;color:#6e7681;font-size:18px;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <div id="userlist-body" style="overflow-y:auto;padding:10px 0;"></div>
        </div>
    </div>
    <script>
    const _followsData = <?= json_encode($follows_list) ?>;
    const _fansData    = <?= json_encode($fans_list) ?>;
    const _levelNames  = {1:'新手',2:'学徒',3:'成员',4:'精英',5:'大师',6:'传奇'};
    const _levelColors = {1:'#8b949e',2:'#58a6ff',3:'#3fb950',4:'#d29922',5:'#f0883e',6:'#f85149'};
    const _roleMap = {
        owner:   {label:'★ 站长', color:'#f85149', border:'rgba(248,81,73,.4)'},
        admin:   {label:'管理员', color:'#a78bfa', border:'rgba(167,139,250,.4)'},
        sponsor: {label:'赞助者', color:'#3fb950', border:'rgba(63,185,80,.4)'},
        user:    {label:'成员',   color:'#58a6ff', border:'rgba(88,166,255,.4)'},
    };
    function getLevelByExp(exp) {
        if (exp >= 50000) return 6; if (exp >= 30000) return 5;
        if (exp >= 15000) return 4; if (exp >= 5000)  return 3;
        if (exp >= 1000)  return 2; return 1;
    }
    function roleTag(u) {
        const banned = parseInt(u.is_banned||0);
        const r = banned ? {label:'已封禁',color:'#6e7681',border:'rgba(110,118,129,.35)'}
                         : (_roleMap[u.role] || _roleMap.user);
        return `<span style="font-size:10px;color:${r.color};border:1px solid ${r.border};padding:1px 5px;border-radius:3px;">${r.label}</span>`;
    }
    function openUserList(type) {
        const data = type === 'follows' ? _followsData : _fansData;
        document.getElementById('userlist-title').textContent = type === 'follows' ? '关注列表' : '粉丝列表';
        const body = document.getElementById('userlist-body');
        if (!data.length) {
            body.innerHTML = '<p style="text-align:center;color:#6e7681;font-size:13px;padding:24px 0;">暂无数据</p>';
        } else {
            body.innerHTML = data.map(u => {
                const lv = getLevelByExp(parseInt(u.exp)||0);
                const lc = _levelColors[lv], ln = _levelNames[lv];
                return `<a href="profile.php?id=${u.id}" style="display:flex;align-items:center;gap:12px;padding:9px 18px;text-decoration:none;transition:.15s;" onmouseover="this.style.background='#21262d'" onmouseout="this.style.background='transparent'">
                    <img src="../uploads/avatars/${u.avatar||'default.png'}" onerror=\"this.onerror=null;this.src='../uploads/avatars/default.png'\"
                         style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1px solid #30363d;flex-shrink:0;">
                    <div style="min-width:0;">
                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                            <span style="color:#e6edf3;font-size:13px;font-weight:600;">${u.username}</span>
                            ${roleTag(u)}
                            <span style="font-size:10px;color:${lc};border:1px solid ${lc}55;padding:1px 5px;border-radius:3px;">Lv.${lv} ${ln}</span>
                        </div>
                    </div>
                </a>`;
            }).join('');
        }
        document.getElementById('userlist-modal').style.display = 'flex';
    }
    function closeUserList() {
        document.getElementById('userlist-modal').style.display = 'none';
    }
    document.getElementById('userlist-modal').addEventListener('click', function(e) {
        if (e.target === this) closeUserList();
    });
    </script>
    <?php endif; ?>

    <div id="image-overlay" onclick="this.style.display='none'" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; cursor:zoom-out; align-items:center; justify-content:center;">
        <img id="full-image" src="" style="max-width:90%; max-height:90%; border-radius:8px; box-shadow:0 0 20px rgba(0,0,0,0.5); transform: scale(1); transition: transform 0.3s;">
    </div>

    <!-- Tab 区域 -->
    <?php if ($they_blocked_me): ?>
    <div class="post-section" style="padding:24px;text-align:center;color:#6e7681;font-family:'Courier New',monospace;font-size:13px;">
        // 该用户已限制你查看其内容
    </div>
    <?php else: ?>
    <div class="post-section" style="padding:0;overflow:hidden;">

        <!-- Tab 头 -->
        <div style="display:flex;border-bottom:1px solid #21262d;">
            <button class="prof-tab active" onclick="switchTab('posts')" id="tab-posts">
                <?php echo $is_mine ? '我的帖子' : 'TA的帖子'; ?>
                <span style="font-size:11px;color:#6e7681;margin-left:4px;"><?php echo $post_count; ?></span>
            </button>
            <?php if ($is_mine): ?>
            <button class="prof-tab" onclick="switchTab('favs')" id="tab-favs">
                收藏夹
                <span style="font-size:11px;color:#6e7681;margin-left:4px;"><?php echo count($favs_list); ?></span>
            </button>
            <button class="prof-tab" onclick="switchTab('likes')" id="tab-likes">
                点赞夹
                <span style="font-size:11px;color:#6e7681;margin-left:4px;"><?php echo count($likes_list); ?></span>
            </button>
            <?php endif; ?>
        </div>

        <!-- 帖子 -->
        <div id="panel-posts" class="prof-panel" style="padding:18px 20px;">
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
                    <div class="empty-panel">// 暂无帖子</div>
                <?php endif; ?>
                <?php if($is_mine): ?>
                    <a href="publish.php" class="post-item" style="text-align:center;border-style:dashed;color:#3fb950;">
                        <strong>+ 发布新帖子</strong>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_mine): ?>
        <!-- 收藏夹 -->
        <div id="panel-favs" class="prof-panel" style="display:none;padding:18px 20px;">
            <?php if (empty($favs_list)): ?>
                <div class="empty-panel">// 还没有收藏过帖子</div>
            <?php else: ?>
                <?php foreach ($favs_list as $p): ?>
                <a href="post.php?id=<?php echo $p['id']; ?>" class="post-item">
                    <h4><?php echo htmlspecialchars($p['title']); ?></h4>
                    <div class="post-meta">
                        <span><?php echo htmlspecialchars($p['username']); ?></span>
                        <span><?php echo date('Y-m-d', strtotime($p['created_at'])); ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 点赞夹 -->
        <div id="panel-likes" class="prof-panel" style="display:none;padding:18px 20px;">
            <?php if (empty($likes_list)): ?>
                <div class="empty-panel">// 还没有点赞过帖子</div>
            <?php else: ?>
                <?php foreach ($likes_list as $p): ?>
                <a href="post.php?id=<?php echo $p['id']; ?>" class="post-item">
                    <h4><?php echo htmlspecialchars($p['title']); ?></h4>
                    <div class="post-meta">
                        <span><?php echo htmlspecialchars($p['username']); ?></span>
                        <span><?php echo date('Y-m-d', strtotime($p['created_at'])); ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<style>
.prof-tab {
    background: none; border: none; cursor: pointer;
    padding: 12px 20px; font-size: 13px; font-family: inherit;
    color: #6e7681; border-bottom: 2px solid transparent;
    transition: .15s; font-weight: 600;
}
.prof-tab:hover { color: #c9d1d9; }
.prof-tab.active { color: #3fb950; border-bottom-color: #3fb950; }
.prof-panel { }
.empty-panel { text-align:center; color:#6e7681; font-size:13px; font-family:"Courier New",monospace; padding:28px 0; }
.empty-panel::before { content:'// '; }
</style>
<script>
function switchTab(name) {
    ['posts','favs','likes'].forEach(function(t) {
        var tab = document.getElementById('tab-' + t);
        var panel = document.getElementById('panel-' + t);
        if (tab)   tab.classList.toggle('active', t === name);
        if (panel) panel.style.display = (t === name) ? '' : 'none';
    });
}
function toggleBlock(uid) {
    var btn = document.getElementById('block-btn');
    var blocked = btn.textContent.trim() === '已拉黑';
    if (!blocked && !confirm('确定要拉黑该用户吗？拉黑后将自动解除互相关注。')) return;
    var fd = new FormData(); fd.append('target_id', uid);
    fetch('../actions/block_user.php', { method:'POST', body:fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.status === 'blocked') {
            btn.textContent = '已拉黑';
            btn.style.color = '#f85149';
            btn.style.borderColor = 'rgba(248,81,73,.4)';
            var fb = document.getElementById('follow-btn-profile');
            if (fb) fb.style.display = 'none';
        } else {
            btn.textContent = '拉黑';
            btn.style.color = '#6e7681';
            btn.style.borderColor = '#30363d';
        }
    });
}
</script>
<?php endif; // they_blocked_me ?>

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

<?php if ($my_id && !$is_mine): include __DIR__ . '/report_modal.php'; endif; ?>

<!-- 封禁弹窗 -->
<div id="ban-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9000;align-items:center;justify-content:center;">
    <div style="background:#161b22;border:1px solid #30363d;border-radius:6px;padding:28px 28px 24px;width:380px;max-width:92vw;font-family:'Courier New',monospace;">
        <p id="ban-modal-title" style="color:#f85149;font-size:15px;margin:0 0 16px;font-weight:700;"></p>
        <div id="ban-reason-wrap">
            <label style="font-size:12px;color:#8b949e;display:block;margin-bottom:6px;">封禁原因 <span style="color:#f85149;">*</span></label>
            <input id="ban-reason-input" type="text" placeholder="请输入封禁原因（必填）" maxlength="200"
                   style="width:100%;box-sizing:border-box;background:#0d1117;border:1px solid #30363d;color:#e6edf3;
                          padding:8px 10px;border-radius:4px;font-size:13px;font-family:inherit;outline:none;margin-bottom:14px;">

            <label style="font-size:12px;color:#8b949e;display:block;margin-bottom:6px;">封禁时长</label>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:5px;font-size:13px;color:#c9d1d9;cursor:pointer;">
                    <input type="radio" name="ban_type" id="ban-type-timed" value="timed" checked
                           style="accent-color:#f85149;" onchange="toggleBanDate()"> 到期解封
                </label>
                <label style="display:flex;align-items:center;gap:5px;font-size:13px;color:#c9d1d9;cursor:pointer;">
                    <input type="radio" name="ban_type" id="ban-type-perm" value="perm"
                           style="accent-color:#f85149;" onchange="toggleBanDate()"> 永久封禁
                </label>
            </div>
            <div id="ban-date-wrap" style="margin-top:10px;">
                <input id="ban-until-input" type="date"
                       style="background:#0d1117;border:1px solid #30363d;color:#e6edf3;
                              padding:7px 10px;border-radius:4px;font-size:13px;font-family:inherit;outline:none;width:100%;box-sizing:border-box;"
                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:18px;">
            <button id="ban-confirm-btn"
                    style="flex:1;padding:9px;border-radius:4px;border:none;cursor:pointer;font-size:13px;font-weight:700;font-family:inherit;transition:.2s;"></button>
            <button onclick="closeBanModal()"
                    style="flex:1;padding:9px;border-radius:4px;border:1px solid #30363d;background:transparent;
                           color:#8b949e;cursor:pointer;font-size:13px;font-family:inherit;">取消</button>
        </div>
    </div>
</div>
<script>
let _banTargetId = 0, _banIsCurrentlyBanned = false;
function openBanModal(uid, isBanned, curReason) {
    _banTargetId = uid;
    _banIsCurrentlyBanned = isBanned;
    const modal = document.getElementById('ban-modal');
    const title = document.getElementById('ban-modal-title');
    const reasonWrap = document.getElementById('ban-reason-wrap');
    const confirmBtn = document.getElementById('ban-confirm-btn');
    const input = document.getElementById('ban-reason-input');
    if (isBanned) {
        title.textContent = '确认解除该账号的封禁？';
        title.style.color = '#3fb950';
        reasonWrap.style.display = 'none';
        confirmBtn.textContent = '确认解封';
        confirmBtn.style.cssText = 'flex:1;padding:9px;border-radius:4px;border:none;cursor:pointer;font-size:13px;font-weight:700;font-family:inherit;background:#3fb950;color:#fff;';
    } else {
        title.textContent = '封禁该账号？';
        title.style.color = '#f85149';
        reasonWrap.style.display = 'block';
        input.value = '';
        document.getElementById('ban-type-timed').checked = true;
        document.getElementById('ban-until-input').value = '';
        document.getElementById('ban-date-wrap').style.display = 'block';
        confirmBtn.textContent = '确认封禁';
        confirmBtn.style.cssText = 'flex:1;padding:9px;border-radius:4px;border:none;cursor:pointer;font-size:13px;font-weight:700;font-family:inherit;background:#f85149;color:#fff;';
    }
    confirmBtn.onclick = submitBan;
    modal.style.display = 'flex';
}
function toggleBanDate() {
    const isPerm = document.getElementById('ban-type-perm').checked;
    document.getElementById('ban-date-wrap').style.display = isPerm ? 'none' : 'block';
}
function closeBanModal() {
    document.getElementById('ban-modal').style.display = 'none';
}
function submitBan() {
    const reason = document.getElementById('ban-reason-input').value.trim();
    if (!_banIsCurrentlyBanned && !reason) { alert('请填写封禁原因'); return; }
    const fd = new FormData();
    fd.append('user_id', _banTargetId);
    fd.append('action', _banIsCurrentlyBanned ? 'unban' : 'ban');
    fd.append('reason', reason);
    if (!_banIsCurrentlyBanned) {
        const isPerm = document.getElementById('ban-type-perm').checked;
        if (!isPerm) {
            const until = document.getElementById('ban-until-input').value;
            if (!until) { alert('请选择截止日期，或选择永久封禁'); return; }
            fd.append('ban_until', until);
        }
    }
    fetch('../actions/ban_user.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) location.reload();
            else alert(d.msg || '操作失败');
        });
}
document.getElementById('ban-modal').addEventListener('click', function(e) {
    if (e.target === this) closeBanModal();
});

function copyMid() {
    const el = document.getElementById('mid-val');
    const mid = el.textContent.trim();
    if (!mid || mid === '—') return;
    navigator.clipboard.writeText(mid).then(function() {
        el.classList.add('mid-copied');
        const orig = el.textContent;
        el.textContent = '已复制!';
        setTimeout(function() { el.textContent = orig; el.classList.remove('mid-copied'); }, 1500);
    });
}
</script>
</body>
</html>
<?php
$conn->close();
?>
