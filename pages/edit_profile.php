<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    die("请先登录。");
}

$my_id = intval($_SESSION['user_id']);

// 获取当前最新数据
$res = $conn->query("SELECT * FROM users WHERE id = $my_id");
$user = $res->fetch_assoc();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $birthday = $conn->real_escape_string($_POST['birthday']);
    $signature = $conn->real_escape_string($_POST['signature']);

    // 头像处理
    $avatar_name = $user['avatar'];
    if (!empty($_FILES['avatar']['name'])) {
        $target_dir = __DIR__ . "/../uploads/avatars/";
        // 大小限制 5MB
        if ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
            die("图片大小不能超过 5MB。");
        }
        // MIME 类型验证（防止伪装扩展名）
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
        finfo_close($finfo);
        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed_mime)) {
            die("不支持的文件格式，仅允许上传 jpg/png/gif/webp 格式的图片。");
        }
        // 确认是合法图片
        if (!getimagesize($_FILES['avatar']['tmp_name'])) {
            die("无效的图片文件。");
        }
        $ext_map = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        $file_ext  = $ext_map[$mime];
        $avatar_name = "u_" . $my_id . "_" . time() . "." . $file_ext;
        move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_dir . $avatar_name);
    }

    // 更新数据库
    $update_sql = "UPDATE users SET
                    username = '$username',
                    gender = '$gender',
                    phone = '$phone',
                    birthday = '$birthday',
                    signature = '$signature',
                    avatar = '$avatar_name'
                  WHERE id = $my_id";

    if ($conn->query($update_sql)) {
        // 同步更新常用 Session
        $_SESSION['username'] = $username;
        $_SESSION['avatar'] = $avatar_name;
        echo "<script>alert('资料更新成功！'); location.href='profile.php?id=$my_id';</script>";
    } else {
        error_log("edit_profile 更新失败: " . $conn->error);
        echo "<script>alert('更新失败，请稍后重试。');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>完善个人资料</title>
    <style>
        .edit-container { max-width: 580px; margin: 30px auto; background: #161b22; border: 1px solid #30363d; border-radius: 6px; padding: 36px 32px; }
        @media(max-width:600px){
            .edit-container { margin: 12px 10px; padding: 24px 18px; border-radius: 4px; }
        }
        .edit-container h2 { font-size: 13px; font-weight: 700; color: #3fb950; letter-spacing: 1.5px; text-transform: uppercase; font-family: "Courier New", monospace; margin: 0 0 28px; }
        .edit-container h2::before { content: '// '; opacity: .6; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #8b949e; font-size: 12px; letter-spacing: .5px; text-transform: uppercase; font-family: "Courier New", monospace; }

        input[type="text"], input[type="date"], select, textarea {
            width: 100%; padding: 10px 12px; background: #0d1117; border: 1px solid #30363d; border-radius: 4px;
            box-sizing: border-box; font-size: 14px; color: #e6edf3; font-family: inherit; outline: none; transition: border-color .2s;
        }
        input[type="text"]:focus, input[type="date"]:focus, select:focus, textarea:focus {
            border-color: #3fb950; box-shadow: 0 0 0 3px rgba(63,185,80,.15);
        }
        input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(.6); }
        select option { background: #161b22; }
        textarea { height: 80px; resize: vertical; }

        .avatar-section { text-align: center; margin-bottom: 28px; }
        .current-avatar { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 2px solid #30363d; cursor: pointer; transition: border-color .2s; }
        .current-avatar:hover { border-color: #3fb950; }
        .avatar-section p { font-size: 11px; color: #6e7681; margin-top: 8px; font-family: "Courier New", monospace; }

        .btn-group { display: flex; gap: 10px; margin-top: 28px; }
        .btn-save { flex: 2; background: #3fb950; color: #fff; border: 1px solid rgba(63,185,80,.4); padding: 11px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 700; font-family: inherit; transition: .2s; }
        .btn-save:hover { background: #2ea043; box-shadow: 0 0 12px rgba(63,185,80,.3); }
        .btn-cancel { flex: 1; background: transparent; color: #8b949e; text-align: center; padding: 11px; border-radius: 4px; text-decoration: none; border: 1px solid #30363d; font-size: 14px; transition: .2s; display: flex; align-items: center; justify-content: center; }
        .btn-cancel:hover { color: #e6edf3; border-color: #8b949e; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="edit-container">
    <h2>编辑个人资料</h2>

    <form action="" method="POST" enctype="multipart/form-data">
        <div class="avatar-section">
            <label for="avatar-input">
                <img src="../uploads/avatars/<?php echo $user['avatar'] ?: 'default.png'; ?>" class="current-avatar" id="preview">
            </label>
            <p style="font-size: 12px; color: #6e7681; font-family: 'Courier New', monospace;">// 点击图片更换头像</p>
            <input type="file" name="avatar" id="avatar-input" accept="image/*" style="display: none;" onchange="showPreview(this)">
        </div>

        <div class="form-group">
            <label>用户名称</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
        </div>

        <div class="form-group" style="display: flex; gap: 20px;">
            <div style="flex: 1;">
                <label>性别</label>
                <select name="gender">
                    <option value="保密" <?php if($user['gender']=='保密') echo 'selected'; ?>>保密</option>
                    <option value="男" <?php if($user['gender']=='男') echo 'selected'; ?>>男</option>
                    <option value="女" <?php if($user['gender']=='女') echo 'selected'; ?>>女</option>
                </select>
            </div>
            <div style="flex: 1;">
                <label>生日</label>
                <input type="date" name="birthday" value="<?php echo $user['birthday']; ?>">
            </div>
        </div>

        <div class="form-group">
            <label>联系电话</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" placeholder="请输入手机号">
        </div>

        <div class="form-group">
            <label>个性签名</label>
            <textarea name="signature" placeholder="介绍一下你自己吧..."><?php echo htmlspecialchars($user['signature']); ?></textarea>
        </div>

        <div class="btn-group">
            <a href="profile.php?id=<?php echo $my_id; ?>" class="btn-cancel">取消</a>
            <button type="submit" class="btn-save">保存更改</button>
        </div>
    </form>
</div>

<script>
function showPreview(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>
