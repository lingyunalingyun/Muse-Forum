<?php
/**
 * dating.php — 社交广场（交友）页
 *
 * 功能：用户发布/浏览/互动交友卡片，支持筛选性别和年龄段
 * 读写表：social_cards、users
 * 权限：需登录（浏览公开，发卡需登录）
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/exp_helper.php';

$my_id   = (int)($_SESSION['user_id'] ?? 0);
$my_name = $_SESSION['username'] ?? '';

// 建表
$conn->query("CREATE TABLE IF NOT EXISTS social_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gender VARCHAR(10) NOT NULL DEFAULT '',
    age_range VARCHAR(20) NOT NULL DEFAULT '',
    intro TEXT NOT NULL,
    tags VARCHAR(200) DEFAULT '',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user (user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// 补列（老数据库兼容）
$gc = $conn->query("SHOW COLUMNS FROM social_cards LIKE 'gender'");
if ($gc && $gc->num_rows === 0) {
    $conn->query("ALTER TABLE social_cards ADD COLUMN gender VARCHAR(10) NOT NULL DEFAULT '' AFTER user_id");
}
$ac = $conn->query("SHOW COLUMNS FROM social_cards LIKE 'age_range'");
if ($ac && $ac->num_rows === 0) {
    $conn->query("ALTER TABLE social_cards ADD COLUMN age_range VARCHAR(20) NOT NULL DEFAULT '' AFTER gender");
}

$my_role = $_SESSION['role'] ?? 'user';
$is_moderator = in_array($my_role, ['admin', 'owner']);

// 拉黑过滤条件
$block_where = '';
if ($my_id > 0) {
    $block_where = "AND NOT EXISTS (SELECT 1 FROM user_blocks WHERE blocker_id=$my_id AND blocked_id=sc.user_id)
                    AND NOT EXISTS (SELECT 1 FROM user_blocks WHERE blocker_id=sc.user_id AND blocked_id=$my_id)";
}

// 拉取所有激活卡片（随机排序，过滤封禁+拉黑）
$cards = [];
$cr = $conn->query("
    SELECT sc.id, sc.user_id, sc.gender, sc.age_range, sc.intro, sc.tags,
           u.username, u.avatar, u.role, u.level, u.signature
    FROM social_cards sc
    JOIN users u ON u.id = sc.user_id
    WHERE sc.is_active = 1 AND u.is_banned = 0 $block_where
    ORDER BY RAND()
");
if ($cr) while ($row = $cr->fetch_assoc()) $cards[] = $row;

// 我的卡片
$my_card = null;
if ($my_id > 0) {
    $mcr = $conn->query("SELECT * FROM social_cards WHERE user_id=$my_id");
    if ($mcr) $my_card = $mcr->fetch_assoc();
}

// 随机分配到列（6列）
$col_count = 6;
$columns   = array_fill(0, $col_count, []);
foreach ($cards as $card) {
    $columns[array_rand($columns)][] = $card;
}

// 每列动画时长（整体放慢，错开，有机感）
$durations = [70, 58, 80, 64, 74, 62];

// 辅助：计算年龄
function calc_age($birthday) {
    if (!$birthday) return null;
    $b = new DateTime($birthday);
    return (new DateTime())->diff($b)->y ?: null;
}

// 渲染真实卡片
function render_card($c) {
    $av      = htmlspecialchars($c['avatar'] ?: 'default.png');
    $name    = htmlspecialchars($c['username']);
    $tags_raw = $c['tags'] ?? '';
    $tags    = array_filter(array_map('trim', explode(',', $tags_raw)));
    $age     = calc_age($c['birthday'] ?? null);
    $gender  = $c['gender'] ?? '';
    $lv      = (int)($c['level'] ?? 1);
    $lc      = get_level_color($lv);

    // 性别光晕（上下左右均匀散发）
    if ($gender === '女') {
        $glow = 'box-shadow:0 0 28px 10px rgba(255,105,180,.55),inset 0 0 40px rgba(255,105,180,.1);';
    } elseif ($gender === '男') {
        $glow = 'box-shadow:0 0 28px 10px rgba(88,166,255,.55),inset 0 0 40px rgba(88,166,255,.1);';
    } else {
        $glow = '';
    }

    $tags_html = '';
    foreach (array_slice($tags, 0, 4) as $t) {
        $tags_html .= '<span class="s-tag">' . htmlspecialchars($t) . '</span>';
    }
    $age_range  = htmlspecialchars($c['age_range'] ?? '');
    $meta_parts = [];
    if ($gender)    $meta_parts[] = htmlspecialchars($gender);
    if ($age_range) $meta_parts[] = $age_range;
    $meta_html = $meta_parts ? '<div class="s-meta">' . implode(' · ', $meta_parts) . '</div>' : '';

    $data = 'data-uid="'    . (int)$c['user_id'] . '"'
          . ' data-name="'  . $name . '"'
          . ' data-av="'    . $av . '"'
          . ' data-role="'  . htmlspecialchars($c['role']) . '"'
          . ' data-lv="'    . $lv . '"'
          . ' data-lc="'    . htmlspecialchars($lc) . '"'
          . ' data-intro="' . htmlspecialchars($c['intro'], ENT_QUOTES) . '"'
          . ' data-tags="'  . htmlspecialchars($tags_raw, ENT_QUOTES) . '"'
          . ' data-gender="'   . htmlspecialchars($gender, ENT_QUOTES) . '"'
          . ' data-age-range="' . htmlspecialchars($c['age_range'] ?? '', ENT_QUOTES) . '"'
          . ' data-sig="'   . htmlspecialchars($c['signature'] ?? '', ENT_QUOTES) . '"';

    $av_url = 'uploads/avatars/' . $av;

    return '<div class="s-card" ' . $data . ' style="' . $glow . '">'
         . '  <div class="s-card-bg" style="background-image:url(\'' . $av_url . '\')"></div>'
         . '  <div class="s-card-overlay"></div>'
         . '  <div class="s-card-body">'
         . '    <div class="s-card-header">'
         . '      <span class="s-name">' . $name . '</span>'
         .          get_role_badge($c['role'])
         . '      <span class="s-lv" style="color:' . $lc . ';background:' . $lc . '22;border:1px solid ' . $lc . '44;">Lv.' . $lv . '</span>'
         . '    </div>'
         . '    <div class="s-intro-wrap"><div class="s-intro">' . htmlspecialchars($c['intro']) . '</div></div>'
         . '    <div class="s-tags-area">'
         . $tags_html
         . $meta_html
         . '    </div>'
         . '  </div>'
         . '</div>';
}

// 渲染占位卡片
function render_placeholder($index) {
    $hints = ['虚位以待', '等你来', '期待相遇', '一张空卡', '投放卡片'];
    $hint  = $hints[$index % count($hints)];
    return '<div class="s-card s-placeholder">'
         . '  <div class="s-card-body" style="align-items:center;justify-content:center;gap:10px;">'
         . '    <div style="font-size:28px;opacity:.3;">?</div>'
         . '    <div style="font-size:11px;color:rgba(255,255,255,.2);font-family:\'Courier New\',monospace;">// ' . $hint . '</div>'
         . '  </div>'
         . '</div>';
}

// 每列最少填满 8 张（保证动画流畅），不足用占位符补
$min_per_col = 8;
foreach ($columns as $ci => $col_cards) {
    $need = $min_per_col - count($col_cards);
    for ($i = 0; $i < $need; $i++) {
        $columns[$ci][] = ['__placeholder' => true, '__index' => $i + $ci * 10];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>交友 - <?= SITE_NAME ?></title>
<style>
* { box-sizing: border-box; }

/* ── 顶部浮层 ── */
.dating-topbar {
    position: relative; z-index: 10;
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 16px;
    background: linear-gradient(#0d1117, transparent);
    pointer-events: none;
}
.dating-topbar > * { pointer-events: auto; }
.dating-title {
    font-size: 14px; font-weight: 700; color: #e6edf3;
    font-family: "Courier New", monospace;
}
.dating-title span { color: #3fb950; }
.dating-count { font-size: 11px; color: #6e7681; font-family: "Courier New", monospace; }

/* ── 卡片墙 ── */
.wall-wrap {
    display: flex; gap: 8px;
    padding: 0 8px 8px;
    overflow: hidden;
    height: calc(100vh - 56px);
    margin-top: -42px;
    user-select: none;
    -webkit-user-select: none;
}

.wall-col {
    flex: 1; min-width: 0;
    overflow: hidden;
}

.col-inner {
    display: flex; flex-direction: column; gap: 8px;
}
.wall-col:nth-child(odd)  .col-inner { animation: scrollUp   var(--dur,30s) linear infinite; }
.wall-col:nth-child(even) .col-inner { animation: scrollDown var(--dur,28s) linear infinite; animation-delay: calc(var(--dur,28s) * -.5); }

@keyframes scrollUp   { from{transform:translateY(0)}   to{transform:translateY(-50%)} }
@keyframes scrollDown { from{transform:translateY(-50%)} to{transform:translateY(0)}   }

/* ── 卡片（扑克牌比例 5:7） ── */
.s-card {
    position: relative; overflow: hidden;
    border-radius: 12px; cursor: pointer; flex-shrink: 0;
    aspect-ratio: 5 / 7;
    width: 100%;
    transition: transform .2s, box-shadow .2s;
}
.s-card:hover {
    transform: scale(1.03);
    box-shadow: 0 8px 32px rgba(0,0,0,.7);
    z-index: 2;
}
/* 占位卡片 */
.s-placeholder {
    background: #161b22;
    border: 1px dashed #30363d;
    cursor: default;
}
.s-placeholder:hover { transform: none; box-shadow: none; }
/* 模糊背景层 */
.s-card-bg {
    position: absolute; inset: -12px;
    background-size: cover; background-position: center;
    filter: blur(14px) brightness(.55) saturate(1.2);
    transform: scale(1.05);
    pointer-events: none;
}
/* 渐变遮罩 */
.s-card-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(
        to bottom,
        rgba(0,0,0,.1) 0%,
        rgba(0,0,0,.15) 40%,
        rgba(0,0,0,.65) 100%
    );
    pointer-events: none;
}
/* 内容层：三段式 header / intro(3/4) / tags(1/4) */
.s-card-body {
    position: relative; z-index: 1;
    height: 100%; display: flex; flex-direction: column;
}
/* 顶部用户信息条（小） */
.s-card-header {
    padding: 10px 12px 4px;
    flex-shrink: 0;
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
}
.s-name { font-size:14px; font-weight:700; color:rgba(255,255,255,.9); text-shadow:0 1px 3px rgba(0,0,0,.6); }
.s-lv   { font-size:10px; font-weight:700; padding:1px 5px; border-radius:3px; }
/* 介绍区（占 3/4） */
.s-intro-wrap {
    flex: 3;
    padding: 6px 14px 8px;
    overflow: hidden;
    display: flex; align-items: center;
}
.s-intro {
    font-size:15px; color:rgba(255,255,255,.9); line-height:1.65;
    overflow:hidden; display:-webkit-box; -webkit-line-clamp:6; -webkit-box-orient:vertical;
    text-shadow:0 1px 4px rgba(0,0,0,.5);
}
/* 标签区（占 1/4） */
.s-tags-area {
    flex: 1;
    padding: 6px 12px 10px;
    border-top: 1px solid rgba(255,255,255,.1);
    display: flex; flex-wrap: wrap; gap: 4px; align-content: flex-start;
    overflow: hidden;
}
.s-tag {
    font-size:12px; padding:2px 9px; border-radius:10px;
    background:rgba(255,255,255,.15); color:rgba(255,255,255,.9);
    border:1px solid rgba(255,255,255,.2);
}
.s-meta { font-size:12px; color:rgba(255,255,255,.4); font-family:"Courier New",monospace; width:100%; margin-top:2px; }

/* ── 详情弹窗 ── */
.modal-mask {
    display:none; position:fixed; inset:0; z-index:1000;
    background:rgba(0,0,0,.65); backdrop-filter:blur(4px);
    align-items:center; justify-content:center;
}
.modal-mask.open { display:flex; }
.modal-box {
    background:#161b22; border:1px solid #30363d; border-radius:10px;
    width:440px; max-width:calc(100vw - 32px); max-height:90vh;
    overflow-y:auto; padding:28px 28px 24px; position:relative;
    animation:fadeUp .25s ease;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
.modal-close {
    position:absolute; top:16px; right:16px; width:28px; height:28px;
    background:#1c2128; border:1px solid #30363d; border-radius:50%;
    color:#8b949e; cursor:pointer; font-size:16px; display:flex;
    align-items:center; justify-content:center; transition:.15s;
}
.modal-close:hover { background:#21262d; color:#e6edf3; }

.modal-profile { display:flex; align-items:flex-start; gap:16px; margin-bottom:18px; }
.modal-avatar { width:72px; height:72px; border-radius:50%; object-fit:cover; border:2px solid #30363d; flex-shrink:0; }
.modal-info { flex:1; min-width:0; }
.modal-name { font-size:17px; font-weight:700; color:#e6edf3; margin-bottom:5px; display:flex; align-items:center; gap:7px; flex-wrap:wrap; }
.modal-badges { display:flex; flex-wrap:wrap; gap:5px; align-items:center; margin-bottom:6px; }
.modal-lv { font-size:11px; font-weight:700; padding:2px 7px; border-radius:3px; }
.modal-sub { font-size:12px; color:#6e7681; font-family:"Courier New",monospace; display:flex; gap:10px; flex-wrap:wrap; }

.modal-section { margin-bottom:16px; }
.modal-section-label {
    font-size:10px; font-weight:700; color:#6e7681;
    letter-spacing:1.5px; text-transform:uppercase; font-family:"Courier New",monospace;
    margin-bottom:7px;
}
.modal-section-label::before { content:'// '; color:#3fb950; }
.modal-intro-text { font-size:13px; color:#c9d1d9; line-height:1.7; white-space:pre-wrap; word-break:break-word; }
.modal-tags { display:flex; flex-wrap:wrap; gap:6px; }
.modal-tag {
    font-size:12px; padding:3px 10px; border-radius:10px;
    background:rgba(63,185,80,.1); color:#3fb950; border:1px solid rgba(63,185,80,.3);
}

.modal-actions { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
.btn-profile {
    flex:1; padding:8px 16px; border-radius:5px; font-size:13px; font-weight:600;
    text-align:center; text-decoration:none; border:1px solid #30363d;
    color:#8b949e; background:#1c2128; transition:.15s; cursor:pointer;
}
.btn-profile:hover { border-color:#58a6ff; color:#58a6ff; }
.btn-dm {
    flex:1; padding:8px 16px; border-radius:5px; font-size:13px; font-weight:600;
    border:1px solid rgba(63,185,80,.4); color:#3fb950; background:rgba(63,185,80,.08);
    transition:.15s; cursor:pointer;
}
.btn-dm:hover { background:rgba(63,185,80,.15); }

.dm-area { display:none; border-top:1px solid #21262d; padding-top:14px; }
.dm-area.show { display:block; }
.dm-textarea {
    width:100%; background:#0d1117; border:1px solid #30363d; border-radius:5px;
    color:#e6edf3; font-size:13px; padding:10px 12px; resize:none; outline:none;
    font-family:inherit; line-height:1.5; height:80px;
}
.dm-textarea:focus { border-color:#3fb950; }
.dm-send-row { display:flex; justify-content:flex-end; gap:8px; margin-top:8px; }
.btn-send {
    padding:7px 20px; border-radius:4px; font-size:13px; font-weight:700;
    background:#3fb950; border:none; color:#fff; cursor:pointer; transition:.2s;
}
.btn-send:hover { background:#2ea043; }
.btn-cancel-dm { padding:7px 14px; border-radius:4px; font-size:13px; background:transparent; border:1px solid #30363d; color:#6e7681; cursor:pointer; transition:.15s; }
.btn-cancel-dm:hover { border-color:#8b949e; color:#e6edf3; }
.dm-sent-tip { font-size:12px; color:#3fb950; font-family:"Courier New",monospace; text-align:center; padding:8px 0; display:none; }


/* ── 发布卡片弹窗 ── */
.pub-modal-box {
    background:#161b22; border:1px solid #30363d; border-radius:10px;
    width:480px; max-width:calc(100vw - 32px); padding:28px;
    position:relative; animation:fadeUp .25s ease;
}
.pub-modal-title {
    font-size:13px; font-weight:700; color:#6e7681;
    letter-spacing:1.5px; text-transform:uppercase; font-family:"Courier New",monospace;
    margin:0 0 18px;
}
.pub-modal-title::before { content:'// '; color:#3fb950; }
.pub-field { margin-bottom:14px; }
.pub-label { font-size:12px; color:#6e7681; margin-bottom:6px; display:block; font-family:"Courier New",monospace; }
.pub-textarea {
    width:100%; background:#0d1117; border:1px solid #30363d; border-radius:5px;
    color:#e6edf3; font-size:13px; padding:10px 12px; resize:vertical; outline:none;
    font-family:inherit; line-height:1.6; min-height:100px;
}
.pub-textarea:focus { border-color:#3fb950; box-shadow:0 0 0 3px rgba(63,185,80,.12); }
.pub-input {
    width:100%; background:#0d1117; border:1px solid #30363d; border-radius:5px;
    color:#e6edf3; font-size:13px; padding:9px 12px; outline:none; font-family:inherit;
}
.pub-input:focus { border-color:#3fb950; }
.pub-hint { font-size:11px; color:#484f58; margin-top:4px; font-family:"Courier New",monospace; }
.pub-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:6px; }
.btn-pub-cancel { padding:8px 18px; border-radius:4px; font-size:13px; background:transparent; border:1px solid #30363d; color:#6e7681; cursor:pointer; transition:.15s; }
.btn-pub-cancel:hover { border-color:#8b949e; color:#e6edf3; }
.btn-pub-submit { padding:8px 22px; border-radius:4px; font-size:13px; font-weight:700; background:#3fb950; border:none; color:#fff; cursor:pointer; transition:.2s; }
.btn-pub-submit:hover { background:#2ea043; }
.btn-pub-delete { padding:8px 16px; border-radius:4px; font-size:13px; border:1px solid rgba(248,81,73,.4); color:#f85149; background:transparent; cursor:pointer; transition:.15s; }
.btn-pub-delete:hover { background:rgba(248,81,73,.1); }

/* ── 空状态 ── */
.wall-empty {
    flex:1; display:flex; flex-direction:column; align-items:center;
    justify-content:center; color:#6e7681; font-family:"Courier New",monospace;
    font-size:13px; gap:8px;
}

@media(max-width:1100px) {
    .wall-col:nth-child(n+6) { display:none; }
}
@media(max-width:900px) {
    .wall-col:nth-child(n+4) { display:none; }
}
@media(max-width:600px) {
    .wall-col:nth-child(n+3) { display:none; }
    .wall-wrap { padding: 10px 8px; gap: 6px; }
}
</style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- 顶部浮层 -->
<div class="dating-topbar">
    <div>
        <span class="dating-title">// <span>交友</span></span>
        <span class="dating-count" style="margin-left:10px;"><?= count($cards) ?> 张卡片</span>
    </div>
    <?php if ($my_id > 0): ?>
        <button onclick="openPub()" style="
            background:<?= $my_card ? 'transparent' : '#3fb950' ?>;
            color:<?= $my_card ? '#3fb950' : '#fff' ?>;
            border:1px solid <?= $my_card ? 'rgba(63,185,80,.5)' : '#3fb950' ?>;
            padding:6px 18px; border-radius:20px; font-size:12px; font-weight:700;
            cursor:pointer; font-family:inherit; transition:.2s;
        "><?= $my_card ? '✎ 编辑卡片' : '+ 投放卡片' ?></button>
    <?php else: ?>
        <a href="pages/login.php" style="
            background:#3fb950; color:#fff; border:none;
            padding:6px 18px; border-radius:20px; font-size:12px; font-weight:700;
            text-decoration:none;
        ">+ 投放卡片</a>
    <?php endif; ?>
</div>

<!-- 卡片墙 -->
<div class="wall-wrap" id="wall-wrap">
<?php if (empty($cards)): ?>
    <div class="wall-empty">
        <div style="font-size:36px;">📭</div>
        <div>// 还没有人投放卡片</div>
        <div style="font-size:12px;margin-top:4px;">成为第一个！</div>
    </div>
<?php else: ?>
    <?php foreach ($columns as $ci => $col_cards): ?>
    <?php if (empty($col_cards)) continue; ?>
    <div class="wall-col" style="--dur:<?= $durations[$ci] ?>s">
        <div class="col-inner">
            <?php
            // 渲染两遍，保证无缝循环
            for ($rep = 0; $rep < 2; $rep++) {
                foreach ($col_cards as $c) {
                    if (!empty($c['__placeholder'])) {
                        echo render_placeholder((int)$c['__index']);
                    } else {
                        echo render_card($c);
                    }
                }
            }
            ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- 详情弹窗 -->
<div class="modal-mask" id="detail-modal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeDetail()">×</button>

        <div class="modal-profile">
            <img id="m-avatar" class="modal-avatar" src="" onerror="this.onerror=null;this.src='uploads/avatars/default.png'">
            <div class="modal-info">
                <div class="modal-name" id="m-name"></div>
                <div class="modal-badges" id="m-badges"></div>
                <div class="modal-sub" id="m-sub"></div>
            </div>
        </div>

        <div class="modal-section">
            <div class="modal-section-label">自我介绍</div>
            <div class="modal-intro-text" id="m-intro"></div>
        </div>

        <div class="modal-section" id="m-tags-section">
            <div class="modal-section-label">标签</div>
            <div class="modal-tags" id="m-tags"></div>
        </div>

        <div class="modal-actions" id="m-actions"></div>

        <div class="dm-area" id="dm-area">
            <textarea class="dm-textarea" id="dm-text" placeholder="输入消息…"></textarea>
            <div class="dm-send-row">
                <button class="btn-cancel-dm" onclick="toggleDm(false)">取消</button>
                <button class="btn-send" onclick="sendDm()">发送</button>
            </div>
            <div class="dm-sent-tip" id="dm-sent-tip">// 消息已发送 ✓</div>
        </div>
    </div>
</div>

<!-- 投放卡片弹窗 -->
<div class="modal-mask" id="pub-modal" onclick="if(event.target===this)closePub()">
    <div class="pub-modal-box">
        <button class="modal-close" onclick="closePub()">×</button>
        <div class="pub-modal-title" id="pub-modal-title">投放我的卡片</div>

        <div class="pub-field">
            <label class="pub-label">性别 *</label>
            <div style="display:flex;gap:12px;">
                <?php foreach(['男','女'] as $g): ?>
                <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:13px;color:#8b949e;">
                    <input type="radio" name="pub-gender" value="<?= $g ?>"
                           <?= ($my_card && ($my_card['gender'] ?? '') === $g) ? 'checked' : '' ?>
                           style="accent-color:#3fb950;">
                    <?= $g ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="pub-field">
            <label class="pub-label">年龄段 *</label>
            <input class="pub-input" type="text" id="pub-age-range" maxlength="10"
                   placeholder="如：20~25、25~30"
                   value="<?= $my_card ? htmlspecialchars($my_card['age_range'] ?? '') : '' ?>">
            <div class="pub-hint">填写大概范围，最小年龄不能低于 18 岁</div>
        </div>
        <div class="pub-field">
            <label class="pub-label">自我介绍 *</label>
            <textarea class="pub-textarea" id="pub-intro" maxlength="300" placeholder="介绍一下自己吧，兴趣爱好、想找什么样的朋友都可以…"><?= $my_card ? htmlspecialchars($my_card['intro']) : '' ?></textarea>
            <div class="pub-hint">最多 300 字</div>
        </div>
        <div class="pub-field">
            <label class="pub-label">标签（最多 4 个）</label>
            <?php
            $existing_tags = $my_card ? array_pad(array_map('trim', explode(',', $my_card['tags'])), 4, '') : ['','','',''];
            for ($ti = 0; $ti < 4; $ti++):
            ?>
            <input class="pub-input pub-tag-input" type="text" maxlength="10"
                   placeholder="标签 <?= $ti+1 ?>"
                   value="<?= htmlspecialchars($existing_tags[$ti] ?? '') ?>"
                   style="margin-bottom:6px;">
            <?php endfor; ?>
            <div class="pub-hint">每个最多 10 字</div>
        </div>

        <div class="pub-actions">
            <?php if ($my_card): ?>
            <button class="btn-pub-delete" onclick="deleteCard()">撤回卡片</button>
            <?php endif; ?>
            <button class="btn-pub-cancel" onclick="closePub()">取消</button>
            <button class="btn-pub-submit" onclick="submitCard()"><?= $my_card ? '更新卡片' : '投放卡片' ?></button>
        </div>
    </div>
</div>


<script>
const MY_ID   = <?= $my_id ?>;
const MY_ROLE = '<?= htmlspecialchars($my_role) ?>';
let   dmUid   = 0;

// ── 详情弹窗 ──
function openCard(el) {
    const d = el.dataset;
    document.getElementById('m-avatar').src = 'uploads/avatars/' + d.av;
    document.getElementById('m-name').textContent = d.name;

    // 角色徽章 + 等级
    const badges = document.getElementById('m-badges');
    badges.innerHTML = '';
    const roleMap = {
        owner:   ['★ 站长', '#f85149','rgba(248,81,73,.15)',  'rgba(248,81,73,.4)'],
        admin:   ['管理员',  '#a78bfa','rgba(167,139,250,.15)','rgba(167,139,250,.4)'],
        sponsor: ['赞助者',  '#3fb950','rgba(63,185,80,.15)',  'rgba(63,185,80,.4)'],
        user:    ['成员',    '#58a6ff','rgba(88,166,255,.15)', 'rgba(88,166,255,.4)'],
    };
    const rs = roleMap[d.role] || roleMap.user;
    const rb = document.createElement('span');
    rb.style.cssText = `font-size:11px;font-weight:700;padding:2px 7px;border-radius:3px;color:${rs[1]};background:${rs[2]};border:1px solid ${rs[3]}`;
    rb.textContent = rs[0];
    badges.appendChild(rb);

    const lv = document.createElement('span');
    lv.className = 'modal-lv';
    lv.style.cssText = `color:${d.lc};background:${d.lc}22;border:1px solid ${d.lc}44;`;
    lv.textContent = 'Lv.' + d.lv;
    badges.appendChild(lv);

    // 副信息
    const sub = document.getElementById('m-sub');
    sub.innerHTML = '';
    if (d.gender)   sub.innerHTML += '<span>' + escHtml(d.gender) + '</span>';
    if (d.ageRange) sub.innerHTML += '<span>' + escHtml(d.ageRange) + ' 岁</span>';

    // 签名
    if (d.sig) sub.innerHTML += '<span style="color:#484f58">// ' + escHtml(d.sig) + '</span>';

    // 介绍
    document.getElementById('m-intro').textContent = d.intro;

    // 标签
    const tagsEl = document.getElementById('m-tags');
    const tagsSec = document.getElementById('m-tags-section');
    tagsEl.innerHTML = '';
    const tArr = d.tags.split(',').map(t => t.trim()).filter(Boolean);
    if (tArr.length) {
        tArr.forEach(t => {
            const sp = document.createElement('span');
            sp.className = 'modal-tag';
            sp.textContent = t;
            tagsEl.appendChild(sp);
        });
        tagsSec.style.display = '';
    } else {
        tagsSec.style.display = 'none';
    }

    // 操作按钮
    dmUid = parseInt(d.uid);
    const acts = document.getElementById('m-actions');
    acts.innerHTML = '';
    const btnProfile = document.createElement('a');
    btnProfile.className = 'btn-profile';
    btnProfile.href = 'pages/profile.php?id=' + d.uid;
    btnProfile.textContent = '查看主页';
    acts.appendChild(btnProfile);

    if (MY_ID > 0 && MY_ID !== dmUid) {
        const btnDm = document.createElement('button');
        btnDm.className = 'btn-dm';
        btnDm.textContent = '发私信';
        btnDm.onclick = () => toggleDm(true);
        acts.appendChild(btnDm);
    }

    if (MY_ROLE === 'admin' || MY_ROLE === 'owner') {
        const btnAdminDel = document.createElement('button');
        btnAdminDel.className = 'btn-pub-delete';
        btnAdminDel.style.cssText = 'flex:none;padding:8px 14px;font-size:12px;';
        btnAdminDel.textContent = '删除卡片';
        btnAdminDel.onclick = () => adminDeleteCard(dmUid);
        acts.appendChild(btnAdminDel);
    }

    // 重置私信区
    toggleDm(false);
    document.getElementById('dm-text').value = '';
    document.getElementById('dm-sent-tip').style.display = 'none';

    document.getElementById('detail-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeDetail() {
    document.getElementById('detail-modal').classList.remove('open');
    document.body.style.overflow = '';
}

function toggleDm(show) {
    const el = document.getElementById('dm-area');
    el.classList.toggle('show', show);
}

function sendDm() {
    if (!MY_ID) { location.href = 'pages/login.php'; return; }
    const content = document.getElementById('dm-text').value.trim();
    if (!content) return;

    const fd = new FormData();
    fd.append('to_id', dmUid);
    fd.append('content', content);

    fetch('actions/message_send.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'success') {
            document.getElementById('dm-text').value = '';
            toggleDm(false);
            const tip = document.getElementById('dm-sent-tip');
            tip.style.display = 'block';
            setTimeout(() => tip.style.display = 'none', 3000);
        } else {
            alert(d.msg || '发送失败');
        }
    })
    .catch(() => alert('网络错误'));
}

// ── 投放卡片弹窗 ──
function openPub() {
    if (!MY_ID) { location.href = 'pages/login.php'; return; }
    document.getElementById('pub-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closePub() {
    document.getElementById('pub-modal').classList.remove('open');
    document.body.style.overflow = '';
}

function submitCard() {
    const genderInput = document.querySelector('input[name="pub-gender"]:checked');
    if (!genderInput) { alert('请选择性别'); return; }

    const ageRange = document.getElementById('pub-age-range').value.trim();
    if (!ageRange) { alert('请填写年龄段'); return; }
    // 检查最小年龄 >= 18
    const minAge = parseInt(ageRange.replace(/[~\-\s].*/,''));
    if (!isNaN(minAge) && minAge < 18) { alert('最小年龄不能低于 18 岁'); return; }

    const intro = document.getElementById('pub-intro').value.trim();
    if (!intro) { alert('自我介绍不能为空'); return; }

    const tagInputs = document.querySelectorAll('.pub-tag-input');
    const tags = [...tagInputs]
        .map(i => i.value.trim().slice(0, 10))
        .filter(Boolean)
        .join(',');

    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('gender', genderInput.value);
    fd.append('age_range', ageRange);
    fd.append('intro', intro);
    fd.append('tags', tags);

    fetch('actions/social_card_save.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'ok') {
            closePub();
            location.reload();
        } else {
            alert(d.msg || '操作失败');
        }
    });
}

function deleteCard() {
    if (!confirm('确定撤回卡片吗？')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fetch('actions/social_card_save.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'ok') { closePub(); location.reload(); }
        else alert(d.msg || '操作失败');
    });
}

function adminDeleteCard(uid) {
    if (!confirm('确定删除此用户的卡片？')) return;
    const fd = new FormData();
    fd.append('action', 'admin_delete');
    fd.append('target_uid', uid);
    fetch('actions/social_card_save.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'ok') { closeDetail(); location.reload(); }
        else alert(d.msg || '操作失败');
    });
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// 事件委托：点击墙内任意元素，找到最近的 .s-card 触发详情
document.getElementById('wall-wrap') && document.getElementById('wall-wrap').addEventListener('click', function(e) {
    const card = e.target.closest('.s-card:not(.s-placeholder)');
    if (card) openCard(card);
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeDetail(); closePub(); }
});
</script>
</body>
</html>
<?php $conn->close(); ?>
