<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exp_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

ensure_user_columns($conn);

$my_id = intval($_SESSION['user_id']);

$res = $conn->query("SELECT * FROM users WHERE id = $my_id");
$user = $res->fetch_assoc();

$pending = null;
$pr = $conn->query("SELECT * FROM profile_edit_requests WHERE user_id=$my_id AND status='pending' ORDER BY created_at DESC LIMIT 1");
if ($pr && $pr->num_rows > 0) $pending = $pr->fetch_assoc();

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($pending) {
        $msg = '你已有一条待审核的申请，请等待管理员审核后再提交新申请。';
        $msg_type = 'warn';
    } else {
        $new_username  = $conn->real_escape_string(trim($_POST['username'] ?? ''));
        $new_gender    = $conn->real_escape_string($_POST['gender'] ?? '保密');
        $new_phone     = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
        $new_birthday  = $conn->real_escape_string($_POST['birthday'] ?? '');
        $new_signature = $conn->real_escape_string(trim($_POST['signature'] ?? ''));

        if (empty($new_username)) {
            $msg = '用户名不能为空。';
            $msg_type = 'error';
        } else {
            $dup = $conn->query("SELECT id FROM users WHERE username='{$new_username}' AND id != $my_id");
            if ($dup && $dup->num_rows > 0) {
                $msg = '该用户名已被占用，请换一个。';
                $msg_type = 'error';
            }
        }

        if (!$msg) {
            $pending_avatar = null;
            if (!empty($_FILES['avatar']['name'])) {
                if ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
                    $msg = '图片大小不能超过 5MB。';
                    $msg_type = 'error';
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime  = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
                    finfo_close($finfo);
                    $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($mime, $allowed_mime)) {
                        $msg = '不支持的文件格式，仅允许 jpg/png/gif/webp。';
                        $msg_type = 'error';
                    } elseif (!getimagesize($_FILES['avatar']['tmp_name'])) {
                        $msg = '无效的图片文件。';
                        $msg_type = 'error';
                    } else {
                        $ext_map = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
                        $ext = $ext_map[$mime];
                        $pending_avatar = 'pending_' . $my_id . '.' . $ext;
                        move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . '/../uploads/avatars/' . $pending_avatar);
                    }
                }
            }
        }

        if (!$msg) {
            $av_val = $pending_avatar ? "'" . $conn->real_escape_string($pending_avatar) . "'" : "NULL";
            $bd_val = $new_birthday ? "'" . $new_birthday . "'" : "NULL";
            $conn->query("INSERT INTO profile_edit_requests
                (user_id, new_username, new_gender, new_phone, new_birthday, new_signature, new_avatar)
                VALUES ($my_id, '$new_username', '$new_gender', '$new_phone', $bd_val, '$new_signature', $av_val)");

            $pr2 = $conn->query("SELECT * FROM profile_edit_requests WHERE user_id=$my_id AND status='pending' ORDER BY created_at DESC LIMIT 1");
            if ($pr2) $pending = $pr2->fetch_assoc();
            $msg = '申请已提交，等待管理员审核。';
            $msg_type = 'ok';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>编辑个人资料</title>
    <style>
        .edit-container { max-width: 580px; margin: 30px auto; background: #161b22; border: 1px solid #30363d; border-radius: 6px; padding: 36px 32px; }
        @media(max-width:600px){ .edit-container { margin: 12px 10px; padding: 24px 18px; border-radius: 4px; } }
        .edit-container h2 { font-size: 13px; font-weight: 700; color: #3fb950; letter-spacing: 1.5px; text-transform: uppercase; font-family: "Courier New", monospace; margin: 0 0 28px; }
        .edit-container h2::before { content: '// '; opacity: .6; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #8b949e; font-size: 12px; letter-spacing: .5px; text-transform: uppercase; font-family: "Courier New", monospace; }
        input[type="text"], input[type="date"], select, textarea {
            width: 100%; padding: 10px 12px; background: #0d1117; border: 1px solid #30363d; border-radius: 4px;
            box-sizing: border-box; font-size: 14px; color: #e6edf3; font-family: inherit; outline: none; transition: border-color .2s;
        }
        input[type="text"]:focus, input[type="date"]:focus, select:focus, textarea:focus { border-color: #3fb950; box-shadow: 0 0 0 3px rgba(63,185,80,.15); }
        input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(.6); }
        select option { background: #161b22; }
        textarea { height: 80px; resize: vertical; }
        .avatar-section { text-align: center; margin-bottom: 28px; }
        .current-avatar { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 2px solid #30363d; cursor: pointer; transition: border-color .2s; }
        .current-avatar:hover { border-color: #3fb950; }
        .btn-group { display: flex; gap: 10px; margin-top: 28px; }
        .btn-save { flex: 2; background: #3fb950; color: #fff; border: 1px solid rgba(63,185,80,.4); padding: 11px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 700; font-family: inherit; transition: .2s; }
        .btn-save:hover { background: #2ea043; box-shadow: 0 0 12px rgba(63,185,80,.3); }
        .btn-cancel { flex: 1; background: transparent; color: #8b949e; text-align: center; padding: 11px; border-radius: 4px; text-decoration: none; border: 1px solid #30363d; font-size: 14px; transition: .2s; display: flex; align-items: center; justify-content: center; }
        .btn-cancel:hover { color: #e6edf3; border-color: #8b949e; }
        .msg-box { padding: 10px 14px; border-radius: 4px; font-size: 13px; margin-bottom: 18px; }
        .msg-ok   { background: rgba(63,185,80,.12); border: 1px solid rgba(63,185,80,.3); color: #3fb950; }
        .msg-warn { background: rgba(210,153,34,.12); border: 1px solid rgba(210,153,34,.3); color: #d29922; }
        .msg-error{ background: rgba(248,81,73,.12); border: 1px solid rgba(248,81,73,.3); color: #f85149; }
        .pending-box { background: #0d1117; border: 1px solid #30363d; border-radius: 4px; padding: 14px 16px; margin-bottom: 22px; font-size: 13px; }
        .pending-box .label { font-size: 11px; color: #6e7681; font-family: "Courier New", monospace; text-transform: uppercase; margin-bottom: 8px; }
        .diff-row { display: flex; gap: 8px; margin: 4px 0; font-size: 13px; }
        .diff-key  { color: #6e7681; width: 70px; flex-shrink: 0; }
        .diff-old  { color: #8b949e; text-decoration: line-through; }
        .diff-arrow { color: #3fb950; }
        .diff-new  { color: #e6edf3; }
        input:disabled, select:disabled, textarea:disabled { opacity: .5; cursor: not-allowed; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="edit-container">
    <h2>编辑个人资料</h2>

    <?php if ($msg): ?>
    <div class="msg-box msg-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($pending): ?>
    <div class="pending-box">
        <div class="label">// 待审核申请 · <?= htmlspecialchars($pending['created_at']) ?></div>
        <?php
        $fields = ['username'=>'用户名','gender'=>'性别','phone'=>'电话','birthday'=>'生日','signature'=>'签名'];
        foreach ($fields as $key => $label) {
            $old = $user[$key] ?? '';
            $new = $pending['new_'.$key] ?? '';
            if ($new !== null && (string)$new !== (string)$old): ?>
            <div class="diff-row">
                <span class="diff-key"><?= $label ?></span>
                <span class="diff-old"><?= htmlspecialchars($old) ?></span>
                <span class="diff-arrow"> → </span>
                <span class="diff-new"><?= htmlspecialchars($new) ?></span>
            </div>
            <?php endif;
        }
        if ($pending['new_avatar']): ?>
        <div class="diff-row"><span class="diff-key">头像</span><span class="diff-new">已上传新头像（待审核）</span></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" <?= $pending ? 'style="opacity:.5;pointer-events:none;"' : '' ?>>
        <div class="avatar-section">
            <label for="avatar-input">
                <img src="../uploads/avatars/<?= htmlspecialchars($user['avatar'] ?: 'default.png') ?>" class="current-avatar" id="preview">
            </label>
            <p style="font-size:12px;color:#6e7681;font-family:'Courier New',monospace;">// 点击图片更换头像</p>
            <input type="file" name="avatar" id="avatar-input" accept="image/*" style="display:none;" onchange="showPreview(this)">
        </div>
        <div class="form-group">
            <label>用户名称</label>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>
        <div class="form-group" style="display:flex;gap:20px;">
            <div style="flex:1;">
                <label>性别</label>
                <select name="gender">
                    <option value="保密" <?= $user['gender']=='保密'?'selected':'' ?>>保密</option>
                    <option value="男"   <?= $user['gender']=='男'?'selected':'' ?>>男</option>
                    <option value="女"   <?= $user['gender']=='女'?'selected':'' ?>>女</option>
                </select>
            </div>
            <div style="flex:1;">
                <label>生日</label>
                <input type="date" name="birthday" value="<?= htmlspecialchars($user['birthday'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>联系电话</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="请输入手机号">
        </div>
        <div class="form-group">
            <label>个性签名</label>
            <textarea name="signature" placeholder="介绍一下你自己吧..."><?= htmlspecialchars($user['signature'] ?? '') ?></textarea>
        </div>
        <div class="btn-group">
            <a href="profile.php?id=<?= $my_id ?>" class="btn-cancel">取消</a>
            <button type="submit" class="btn-save"><?= $pending ? '已有待审核申请' : '提交审核' ?></button>
        </div>
    </form>
</div>
<script>
function showPreview(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) { document.getElementById('preview').src = e.target.result; };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
