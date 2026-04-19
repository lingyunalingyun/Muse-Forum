<?php
/**
 * edit_profile.php — 编辑个人资料页
 *
 * 功能：支持上传头像、修改昵称/签名/邮箱
 * 读写表：读写 users；写 uploads/avatars/ 目录
 * 权限：需登录且未被封禁
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    die("请先登录。");
}
if (!empty($_SESSION['is_banned'])) {
    header("Location: ../index.php");
    exit;
}

$my_id = intval($_SESSION['user_id']);

$res = $conn->query("SELECT * FROM users WHERE id = $my_id");
$user = $res->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $birthday = $conn->real_escape_string($_POST['birthday']);
    $signature = $conn->real_escape_string($_POST['signature']);

    
    $avatar_name = $user['avatar'];
    if (!empty($_FILES['avatar']['name'])) {
        $target_dir = __DIR__ . "/../uploads/avatars/";
        
        if ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
            die("图片大小不能超过 5MB。");
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
        finfo_close($finfo);
        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed_mime)) {
            die("不支持的文件格式，仅允许上传 jpg/png/gif/webp 格式的图片。");
        }
        
        if (!getimagesize($_FILES['avatar']['tmp_name'])) {
            die("无效的图片文件。");
        }
        $ext_map = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        $file_ext  = $ext_map[$mime];
        $avatar_name = ($user['mid'] ?? ('u'.$my_id)) . "." . $file_ext;
        
        if (!empty($user['avatar']) && $user['avatar'] !== $avatar_name && $user['avatar'] !== 'default.png') {
            $old = $target_dir . $user['avatar'];
            if (file_exists($old)) unlink($old);
        }
        move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_dir . $avatar_name);
    }

    
    $update_sql = "UPDATE users SET
                    username = '$username',
                    gender = '$gender',
                    phone = '$phone',
                    birthday = '$birthday',
                    signature = '$signature',
                    avatar = '$avatar_name'
                  WHERE id = $my_id";

    if ($conn->query($update_sql)) {
        
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
        .edit-container { max-width: 580px; margin: 30px auto; background: 
        @media(max-width:600px){
            .edit-container { margin: 12px 10px; padding: 24px 18px; border-radius: 4px; }
        }
        .edit-container h2 { font-size: 13px; font-weight: 700; color: 
        .edit-container h2::before { content: '// '; opacity: .6; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: 

        input[type="text"], input[type="date"], select, textarea {
            width: 100%; padding: 10px 12px; background: 
            box-sizing: border-box; font-size: 14px; color: 
        }
        input[type="text"]:focus, input[type="date"]:focus, select:focus, textarea:focus {
            border-color: 
        }
        input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(.6); }
        select option { background: 
        textarea { height: 80px; resize: vertical; }

        .avatar-section { text-align: center; margin-bottom: 28px; }
        .current-avatar { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 2px solid 
        .current-avatar:hover { border-color: 
        .avatar-section p { font-size: 11px; color: 

        .btn-group { display: flex; gap: 10px; margin-top: 28px; }
        .btn-save { flex: 2; background: 
        .btn-save:hover { background: 
        .btn-cancel { flex: 1; background: transparent; color: 
        .btn-cancel:hover { color: 
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
            <p style="font-size: 12px; color: #6e7681; font-family: 'Courier New', monospace;">
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
