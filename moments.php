<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exp_helper.php';
require_once __DIR__ . '/../includes/repost_card.php';

ensure_user_columns($conn);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit;
}

$my_id      = intval($_SESSION['user_id']);
$page       = max(1, intval($_GET['page'] ?? 1));
$per_page   = 20;
$filter_uid = intval($_GET['uid'] ?? 0);
$is_ajax    = !empty($_GET['ajax']);

$uid_cond = $filter_uid > 0
    ? "p.user_id = $filter_uid"
    : "(p.user_id = $my_id OR p.user_id IN (SELECT followed_id FROM follows WHERE follower_id=$my_id))";

$tc = $conn->query("SELECT COUNT(*) c FROM posts p WHERE p.status='已发布' AND $uid_cond");
$total = $tc ? (int)$tc->fetch_assoc()['c'] : 0;
$total_pages = max(1, (int)ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$res = $conn->query(
    "SELECT p.id, p.title, p.content, p.created_at, p.repost_id, p.views,
            u.id AS uid, u.username, u.avatar, u.role,
            (SELECT COUNT(*) FROM post_likes WHERE post_id=p.id) AS likes,
            (SELECT COUNT(*) FROM comments      WHERE post_id=p.id) AS cmt_cnt,
            (SELECT COUNT(*) FROM posts         WHERE repost_id=p.id AND status='已发布') AS repost_cnt
     FROM posts p
     JOIN users u ON u.id = p.user_id
     WHERE p.status='已发布' AND $uid_cond
     ORDER BY p.id DESC
     LIMIT $per_page OFFSET $offset"
);
$posts = [];
if ($res) while ($r = $res->fetch_assoc()) $posts[] = $r;

function first_img(string $html): string {
    preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $m);
    return $m[1] ?? '';
}
function time_ago(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)      return '刚刚';
    if ($diff < 3600)    return floor($diff/60).'分钟前';
    if ($diff < 86400)   return floor($diff/3600).'小时前';
    if ($diff < 86400*7) return floor($diff/86400).'天前';
    return date('m-d', strtotime($dt));
}

if ($is_ajax) {
    header('Content-Type: text/html; charset=utf-8');

    if ($filter_uid > 0) {
        $fu_res = $conn->query("SELECT username FROM users WHERE id=$filter_uid");
        $fu_row = $fu_res ? $fu_res->fetch_assoc() : null;
        echo '<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">';
        echo '<span style="font-size:12px;color:#3fb950;cursor:pointer;" onclick="loadFeed(0,1)">← 全部动态</span>';
        if ($fu_row) echo '<span style="font-size:13px;color:#8b949e;">/ '.htmlspecialchars($fu_row['username']).' 的动态</span>';
        echo '</div>';
    }

    if (empty($posts)) {
        echo '<div class="empty">';
        if ($filter_uid > 0) {
            echo '该用户暂无动态。<span style="color:#3fb950;cursor:pointer;" onclick="loadFeed(0,1)">查看全部动态</span>';
        } else {
            echo '还没有动态。去<a href="square.php">广场</a>逛逛，关注一些用户吧。';
        }
        echo '</div>';
    } else {
        foreach ($posts as $p) {
            $is_repost = !empty($p['repost_id']);
            $av    = htmlspecialchars($p['avatar'] ?: 'default.png');
            $thumb = $is_repost ? '' : first_img($p['content']);
            $plain = mb_substr(strip_tags($p['content']), 0, 150);
            $title = $p['title'] ?: '';
            $post_link = 'post.php?id=' . $p['id'];
            ?>
<div class="mc">
    <div class="mc-header">
        <span class="fs-av-link" data-uid="<?= $p['uid'] ?>">
            <img class="mc-av" src="../uploads/avatars/<?= $av ?>" onerror="this.onerror=null;this.src='../uploads/avatars/default.png'">
        </span>
        <div class="mc-info">
            <div class="mc-name">
                <span class="fs-av-link" data-uid="<?= $p['uid'] ?>" style="color:#e6edf3;"><?= htmlspecialchars($p['username']) ?></span>
                <?= get_role_badge($p['role']) ?>
                <span class="mc-action"><?= $is_repost ? '转发了动态' : '发布了帖子' ?></span>
            </div>
            <div class="mc-time"><?= time_ago($p['created_at']) ?></div>
        </div>
        <a href="<?= $post_link ?>" class="mc-more" title="查看详情">···</a>
    </div>
    <div class="mc-body">
        <?php if ($is_repost): ?>
            <?php if ($p['content']): ?><div class="mc-repost-comment"><?= htmlspecialchars(mb_substr($p['content'],0,200)) ?></div><?php endif; ?>
            <?= render_repost_card($conn, (int)$p['repost_id'], '../') ?>
        <?php elseif ($thumb): ?>
            <div class="mc-with-thumb">
                <img class="mc-thumb" src="<?= htmlspecialchars($thumb) ?>" onerror="this.onerror=null;this.style.display='none'" alt="">
                <div class="mc-text-side">
                    <?php if ($title): ?><div class="mc-title"><a href="<?= $post_link ?>"><?= htmlspecialchars($title) ?></a></div><?php endif; ?>
                    <?php if ($plain): ?><div class="mc-preview"><?= htmlspecialchars($plain) ?></div><?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="mc-text-only">
                <?php if ($title): ?><div class="mc-title" style="margin-bottom:6px;"><a href="<?= $post_link ?>"><?= htmlspecialchars($title) ?></a></div><?php endif; ?>
                <?php if ($plain): ?><div class="mc-preview" style="-webkit-line-clamp:3;"><?= htmlspecialchars($plain) ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="mc-actions">
        <a href="<?= $post_link ?>#comments" class="mc-act">
            <svg viewBox="0 0 16 16"><path d="M2.678 11.894a1 1 0 0 1 .287.801 10.97 10.97 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8.06 8.06 0 0 0 8 14c3.996 0 7-2.807 7-6 0-3.192-3.004-6-7-6S1 4.808 1 8c0 1.468.617 2.83 1.678 3.894z"/></svg>
            <?= $p['cmt_cnt'] ?>
        </a>
        <a href="<?= $post_link ?>" class="mc-act">
            <svg viewBox="0 0 16 16"><path d="M11 5.466V4H5a4 4 0 0 0-3.584 5.777.5.5 0 1 1-.896.446A5 5 0 0 1 5 3h6V1.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384l-2.36 1.966a.25.25 0 0 1-.41-.192zm3.81 6.745A5 5 0 0 1 11 13H5v1.466a.25.25 0 0 1-.41.192l-2.36-1.966a.25.25 0 0 1 0-.384l2.36-1.966a.25.25 0 0 1 .41.192V12h6a4 4 0 0 0 3.585-5.777.5.5 0 0 1 .895-.446z"/></svg>
            <?= $p['repost_cnt'] ?>
        </a>
        <a href="<?= $post_link ?>" class="mc-act">
            <svg viewBox="0 0 16 16"><path d="M8 1.314C12.438-3.248 23.534 4.735 8 15-7.534 4.736 3.562-3.248 8 1.314z"/></svg>
            <?= $p['likes'] ?>
        </a>
        <a href="<?= $post_link ?>" class="mc-act mc-act-view">
            <svg viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/></svg>
            <?= $p['views'] ?>
        </a>
    </div>
</div>
            <?php
        }
    }

    
    if ($total_pages > 1) {
        echo '<div class="pagination">';
        echo '<span class="pag-btn '.($page<=1?'disabled':'').'" onclick="loadFeed('.$filter_uid.','.($page-1).')">← 上页</span>';
        for ($i = max(1,$page-3); $i <= min($total_pages,$page+3); $i++) {
            echo '<span class="pag-btn '.($i===$page?'active':'').'" onclick="loadFeed('.$filter_uid.','.$i.')">'.$i.'</span>';
        }
        echo '<span class="pag-btn '.($page>=$total_pages?'disabled':'').'" onclick="loadFeed('.$filter_uid.','.($page+1).')">下页 →</span>';
        echo '</div>';
    }
    exit;
}

$following = [];
$fr = $conn->query(
    "SELECT u.id, u.username, u.avatar
     FROM follows f JOIN users u ON u.id = f.followed_id
     WHERE f.follower_id = $my_id ORDER BY f.id DESC LIMIT 30"
);
if ($fr) while ($r = $fr->fetch_assoc()) $following[] = $r;

$trending = [];
$tr = $conn->query(
    "SELECT t.name, COUNT(pt.post_id) AS cnt
     FROM topics t
     JOIN post_topics pt ON pt.topic_id = t.id
     JOIN posts p ON p.id = pt.post_id
     WHERE p.status='已发布' AND p.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY t.id ORDER BY cnt DESC LIMIT 10"
);
if ($tr) while ($r = $tr->fetch_assoc()) $trending[] = $r;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>动态 — MUSE</title>
<link rel="stylesheet" href="../style.css">
<style>
body { background: #0d1117; }

.follow-strip {
    background: #161b22;
    border-bottom: 1px solid #21262d;
    padding: 12px 0;
    overflow-x: auto;
    scrollbar-width: none;
}
.follow-strip::-webkit-scrollbar { display: none; }
.follow-strip-inner {
    display: flex;
    gap: 20px;
    padding: 0 16px;
    max-width: 1100px;
    margin: 0 auto;
    align-items: flex-start;
    width: max-content;
}
.fs-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    flex-shrink: 0;
    width: 62px;
    user-select: none;
}
.fs-av-wrap {
    width: 52px; height: 52px; border-radius: 50%;
    border: 2px solid transparent;
    box-sizing: border-box;
    transition: border-color .15s;
    flex-shrink: 0;
}
.fs-item.active .fs-av-wrap,
.fs-item:hover .fs-av-wrap { border-color: #3fb950; }
.fs-av {
    width: 100%; height: 100%; border-radius: 50%; object-fit: cover; display: block;
}
.fs-name {
    font-size: 11px; color: #8b949e;
    max-width: 62px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    line-height: 1.3;
}
.fs-item.active .fs-name { color: #3fb950; }
.fs-all-av {
    width: 100%; height: 100%; border-radius: 50%;
    background: #21262d;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
}

.moments-layout {
    max-width: 1100px;
    margin: 20px auto;
    padding: 0 16px;
    display: flex;
    gap: 20px;
    align-items: flex-start;
}
.moments-main { flex: 1; min-width: 0; }
.moments-side { width: 280px; flex-shrink: 0; }

.mc {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 10px;
    transition: border-color .15s;
}
.mc:hover { border-color: #58a6ff; }
.mc-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.mc-av {
    width: 44px; height: 44px; border-radius: 50%; object-fit: cover;
    border: 1px solid #30363d;
}
.mc-info { flex: 1; min-width: 0; }
.mc-name { font-size: 14px; font-weight: 700; color: #e6edf3; }
.mc-action { font-size: 12px; color: #8b949e; }
.mc-time { font-size: 11px; color: #6e7681; }
.mc-more { color: #8b949e; }
.mc-more:hover { color: #e6edf3; }
.mc-body { margin-bottom: 12px; }
.mc-text-only { font-size: 13px; color: #c9d1d9; }
.mc-with-thumb { display: flex; gap: 12px; align-items: flex-start; }
.mc-thumb { width: 120px; height: 80px; border-radius: 4px; object-fit: cover; flex-shrink: 0; border: 1px solid #30363d; }
.mc-text-side { flex: 1; min-width: 0; }
.mc-title { font-size: 14px; font-weight: 700; color: #e6edf3; }
.mc-title a { color: inherit; text-decoration: none; }
.mc-title a:hover { color: #58a6ff; }
.mc-preview { font-size: 12px; color: #8b949e; }
.mc-repost-comment { font-size: 13px; color: #c9d1d9; }
.mc-actions { display: flex; gap: 20px; align-items: center; font-size: 13px; color: #8b949e; }
.mc-act { display: flex; align-items: center; gap: 5px; text-decoration: none; color: #8b949e; }
.mc-act:hover { color: #3fb950; }
.mc-act svg { width: 15px; height: 15px; fill: currentColor; flex-shrink: 0; }
.mc-act-view { margin-left: auto; font-size: 12px; }
.fs-av-link { cursor: pointer; }
.fs-av-link:hover .mc-av { border-color: #3fb950; }

.side-card { background: #161b22; border: 1px solid #30363d; border-radius: 8px; overflow: hidden; }
.side-card-head { padding: 12px 16px 10px; font-size: 16px; font-weight: 700; color: #e6edf3; }
.trend-item { display: flex; align-items: flex-start; gap: 10px; padding: 9px 16px; border-bottom: 1px solid #21262d; text-decoration: none; }
.trend-item:last-child { border-bottom: none; }
.trend-item:hover { background: #1c2128; }
.trend-num { font-size: 14px; font-weight: 700; min-width: 18px; margin-top: 1px; font-family: "Courier New", monospace; flex-shrink: 0; }
.trend-num.hot1 { color: #f85149; }
.trend-num.hot2 { color: #f0883e; }
.trend-num.hot3 { color: #d29922; }
.trend-num.hots { color: #8b949e; }
.trend-text { font-size: 13px; color: #e6edf3; }
.trend-meta { font-size: 11px; color: #6e7681; }

.empty { text-align: center; padding: 60px 20px; color: #8b949e; }
.empty a { color: #58a6ff; }
.pagination { display: flex; gap: 6px; justify-content: center; margin-top: 16px; flex-wrap: wrap; }
.pag-btn { padding: 5px 13px; border-radius: 4px; font-size: 12px; cursor: pointer;
           border: 1px solid #30363d; color: #c9d1d9; background: transparent; }
.pag-btn:hover:not(.disabled):not(.active) { border-color: #58a6ff; }
.pag-btn.active { background: #3fb950; border-color: #3fb950; color: #0d1117; font-weight: 700; }
.pag-btn.disabled { opacity: .35; cursor: default; }

@media (max-width: 768px) {
    .moments-side { display: none; }
    .mc-with-thumb { flex-direction: column; }
    .mc-thumb { width: 100%; height: 160px; }
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<!-- 关注用户头像条 -->
<div class="follow-strip">
    <div class="follow-strip-inner">
        <div class="fs-item <?= $filter_uid===0?'active':'' ?>" data-uid="0" onclick="loadFeed(0,1)">
            <div class="fs-av-wrap">
                <div class="fs-all-av">🌐</div>
            </div>
            <span class="fs-name">全部动态</span>
        </div>
        <?php foreach ($following as $fu):
            $fav = htmlspecialchars($fu['avatar'] ?: 'default.png');
        ?>
        <div class="fs-item <?= $filter_uid===$fu['id']?'active':'' ?>" data-uid="<?= $fu['id'] ?>" onclick="loadFeed(<?= $fu['id'] ?>,1)">
            <div class="fs-av-wrap">
                <img class="fs-av" src="../uploads/avatars/<?= $fav ?>" onerror="this.onerror=null;this.src='../uploads/avatars/default.png'" alt="">
            </div>
            <span class="fs-name"><?= htmlspecialchars($fu['username']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($following)): ?>
        <span style="font-size:12px;color:#484f58;align-self:center;padding-left:8px;">还没有关注任何人</span>
        <?php endif; ?>
    </div>
</div>

<div class="moments-layout">
    <!-- 主内容 -->
    <div class="moments-main">
        <div id="moments-feed">
            <?php
            
            if ($filter_uid > 0) {
                $fu_res = $conn->query("SELECT username FROM users WHERE id=$filter_uid");
                $fu_row = $fu_res ? $fu_res->fetch_assoc() : null;
                echo '<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">';
                echo '<span style="font-size:12px;color:#3fb950;cursor:pointer;" onclick="loadFeed(0,1)">← 全部动态</span>';
                if ($fu_row) echo '<span style="font-size:13px;color:#8b949e;">/ '.htmlspecialchars($fu_row['username']).' 的动态</span>';
                echo '</div>';
            }
            if (empty($posts)) {
                echo '<div class="empty">';
                if ($filter_uid > 0) {
                    echo '该用户暂无动态。<span style="color:#3fb950;cursor:pointer;" onclick="loadFeed(0,1)">查看全部动态</span>';
                } else {
                    echo '还没有动态。去<a href="square.php">广场</a>逛逛，关注一些用户吧。';
                }
                echo '</div>';
            } else {
                foreach ($posts as $p) {
                    $is_repost = !empty($p['repost_id']);
                    $av    = htmlspecialchars($p['avatar'] ?: 'default.png');
                    $thumb = $is_repost ? '' : first_img($p['content']);
                    $plain = mb_substr(strip_tags($p['content']), 0, 150);
                    $title = $p['title'] ?: '';
                    $post_link = 'post.php?id=' . $p['id'];
                    ?>
<div class="mc">
    <div class="mc-header">
        <span class="fs-av-link" data-uid="<?= $p['uid'] ?>" onclick="loadFeed(<?= $p['uid'] ?>,1)">
            <img class="mc-av" src="../uploads/avatars/<?= $av ?>" onerror="this.onerror=null;this.src='../uploads/avatars/default.png'">
        </span>
        <div class="mc-info">
            <div class="mc-name">
                <span class="fs-av-link" data-uid="<?= $p['uid'] ?>" onclick="loadFeed(<?= $p['uid'] ?>,1)" style="color:#e6edf3;"><?= htmlspecialchars($p['username']) ?></span>
                <?= get_role_badge($p['role']) ?>
                <span class="mc-action"><?= $is_repost ? '转发了动态' : '发布了帖子' ?></span>
            </div>
            <div class="mc-time"><?= time_ago($p['created_at']) ?></div>
        </div>
        <a href="<?= $post_link ?>" class="mc-more" title="查看详情">···</a>
    </div>
    <div class="mc-body">
        <?php if ($is_repost): ?>
            <?php if ($p['content']): ?><div class="mc-repost-comment"><?= htmlspecialchars(mb_substr($p['content'],0,200)) ?></div><?php endif; ?>
            <?= render_repost_card($conn, (int)$p['repost_id'], '../') ?>
        <?php elseif ($thumb): ?>
            <div class="mc-with-thumb">
                <img class="mc-thumb" src="<?= htmlspecialchars($thumb) ?>" onerror="this.onerror=null;this.style.display='none'" alt="">
                <div class="mc-text-side">
                    <?php if ($title): ?><div class="mc-title"><a href="<?= $post_link ?>"><?= htmlspecialchars($title) ?></a></div><?php endif; ?>
                    <?php if ($plain): ?><div class="mc-preview"><?= htmlspecialchars($plain) ?></div><?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="mc-text-only">
                <?php if ($title): ?><div class="mc-title" style="margin-bottom:6px;"><a href="<?= $post_link ?>"><?= htmlspecialchars($title) ?></a></div><?php endif; ?>
                <?php if ($plain): ?><div class="mc-preview" style="-webkit-line-clamp:3;"><?= htmlspecialchars($plain) ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="mc-actions">
        <a href="<?= $post_link ?>#comments" class="mc-act">
            <svg viewBox="0 0 16 16"><path d="M2.678 11.894a1 1 0 0 1 .287.801 10.97 10.97 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8.06 8.06 0 0 0 8 14c3.996 0 7-2.807 7-6 0-3.192-3.004-6-7-6S1 4.808 1 8c0 1.468.617 2.83 1.678 3.894z"/></svg>
            <?= $p['cmt_cnt'] ?>
        </a>
        <a href="<?= $post_link ?>" class="mc-act">
            <svg viewBox="0 0 16 16"><path d="M11 5.466V4H5a4 4 0 0 0-3.584 5.777.5.5 0 1 1-.896.446A5 5 0 0 1 5 3h6V1.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384l-2.36 1.966a.25.25 0 0 1-.41-.192zm3.81 6.745A5 5 0 0 1 11 13H5v1.466a.25.25 0 0 1-.41.192l-2.36-1.966a.25.25 0 0 1 0-.384l2.36-1.966a.25.25 0 0 1 .41.192V12h6a4 4 0 0 0 3.585-5.777.5.5 0 0 1 .895-.446z"/></svg>
            <?= $p['repost_cnt'] ?>
        </a>
        <a href="<?= $post_link ?>" class="mc-act">
            <svg viewBox="0 0 16 16"><path d="M8 1.314C12.438-3.248 23.534 4.735 8 15-7.534 4.736 3.562-3.248 8 1.314z"/></svg>
            <?= $p['likes'] ?>
        </a>
        <a href="<?= $post_link ?>" class="mc-act mc-act-view">
            <svg viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/></svg>
            <?= $p['views'] ?>
        </a>
    </div>
</div>
                    <?php
                }
            }
            
            if ($total_pages > 1) {
                echo '<div class="pagination">';
                echo '<span class="pag-btn '.($page<=1?'disabled':'').'" onclick="loadFeed('.$filter_uid.','.($page-1).')">← 上页</span>';
                for ($i = max(1,$page-3); $i <= min($total_pages,$page+3); $i++) {
                    echo '<span class="pag-btn '.($i===$page?'active':'').'" onclick="loadFeed('.$filter_uid.','.$i.')">'.$i.'</span>';
                }
                echo '<span class="pag-btn '.($page>=$total_pages?'disabled':'').'" onclick="loadFeed('.$filter_uid.','.($page+1).')">下页 →</span>';
                echo '</div>';
            }
            ?>
        </div><!-- 
    </div>

    <!-- 右侧边栏 -->
    <div class="moments-side">
        <?php if (!empty($trending)): ?>
        <div class="side-card">
            <div class="side-card-head">🔥 热搜话题</div>
            <?php foreach ($trending as $i => $t):
                $nc = ['hot1','hot2','hot3'][$i] ?? 'hots';
            ?>
            <a href="../search.php?tab=posts&keyword=<?= urlencode('#'.$t['name']) ?>" class="trend-item">
                <span class="trend-num <?= $nc ?>"><?= $i+1 ?></span>
                <div>
                    <div class="trend-text">
                    <div class="trend-meta"><?= $t['cnt'] ?> 条帖子</div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
var _curUid  = <?= $filter_uid ?>;
var _curPage = <?= $page ?>;

function loadFeed(uid, page) {
    _curUid  = uid;
    _curPage = page;

    
    document.querySelectorAll('.fs-item').forEach(function(el) {
        el.classList.toggle('active', parseInt(el.dataset.uid) === uid);
    });

    var feed = document.getElementById('moments-feed');
    feed.classList.add('loading');

    var url = 'moments.php?ajax=1&uid=' + uid + '&page=' + page;
    fetch(url)
        .then(function(r){ return r.text(); })
        .then(function(html) {
            feed.innerHTML = html;
            feed.classList.remove('loading');
            feed.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        })
        .catch(function() { feed.classList.remove('loading'); });
}
</script>
</body>
</html>
