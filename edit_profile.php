<?php
session_start();
$conn = new mysqli("localhost", "svh_b14f1q", "xrb23va4gs", "svh_b14f1q");
$conn->set_charset("utf8mb4");

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
        $target_dir = "uploads/avatars/";
        $file_ext = pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION);
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
        echo "错误：" . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>完善个人资料</title>
    <style>
        body { background: #f4f7f6; font-family: "Microsoft YaHei", sans-serif; }
        .edit-container { max-width: 600px; margin: 30px auto; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #444; }
        
        input[type="text"], input[type="date"], select, textarea {
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 14px;
        }
        textarea { height: 80px; resize: vertical; }
        
        .avatar-section { text-align: center; margin-bottom: 30px; }
        .current-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #eee; cursor: pointer; }
        
        .btn-group { display: flex; gap: 10px; margin-top: 30px; }
        .btn-save { flex: 2; background: #28a745; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; font-size: 16px; }
        .btn-cancel { flex: 1; background: #eee; color: #666; text-align: center; padding: 12px; border-radius: 8px; text-decoration: none; }
        .btn-save:hover { background: #218838; }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="edit-container">
    <h2 style="text-align: center; margin-bottom: 30px; color: #333;">编辑个人资料</h2>
    
    <form action="" method="POST" enctype="multipart/form-data">
        <div class="avatar-section">
            <label for="avatar-input">
                <img src="uploads/avatars/<?php echo $user['avatar'] ?: 'default.png'; ?>" class="current-avatar" id="preview">
            </label>
            <p style="font-size: 12px; color: #999;">点击图片更换头像</p>
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