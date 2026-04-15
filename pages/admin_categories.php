<?php
session_start();
require_once __DIR__ . '/../config.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php"); exit;
}

// ── 自动建表 + 补列 + 建目录 ──
$conn->query("CREATE TABLE IF NOT EXISTS categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL,
    description VARCHAR(200) DEFAULT '',
    color       VARCHAR(7)   DEFAULT '#3fb950',
    icon        VARCHAR(10)  DEFAULT '#',
    cover_image VARCHAR(255) DEFAULT '',
    sort_order  INT          DEFAULT 0,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 补 posts.category_id（兼容 MySQL 5.x）
$col2 = $conn->query("SHOW COLUMNS FROM posts LIKE 'category_id'");
if ($col2 && $col2->num_rows === 0) {
    $conn->query("ALTER TABLE posts ADD COLUMN category_id INT NULL DEFAULT NULL");
}

// 补 cover_image 列（兼容旧表）
$col_check = $conn->query("SHOW COLUMNS FROM categories LIKE 'cover_image'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE categories ADD COLUMN cover_image VARCHAR(255) DEFAULT '' AFTER icon");
}

$upload_dir = __DIR__ . '/../uploads/categories/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// ── 图片上传处理 ──
function save_cover_image($file, $conn) {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null;

    $dir = __DIR__ . '/../uploads/categories/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $fname = 'cat_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
        return 'uploads/categories/' . $fname;
    }
    return null;
}

// ── 删除 ──
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // 删除封面图文件
    $row = $conn->query("SELECT cover_image FROM categories WHERE id=$del_id")->fetch_assoc();
    if (!empty($row['cover_image'])) {
        $path = __DIR__ . '/../' . $row['cover_image'];
        if (file_exists($path)) unlink($path);
    }
    $conn->query("UPDATE posts SET category_id=NULL WHERE category_id=$del_id");
    $conn->query("DELETE FROM categories WHERE id=$del_id");
    header("Location: admin_categories.php?msg=delete_ok"); exit;
}

// ── 编辑保存 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    $name    = $conn->real_escape_string(trim($_POST['name'] ?? ''));
    $desc    = $conn->real_escape_string(trim($_POST['description'] ?? ''));
    $color   = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#3fb950';
    $icon    = $conn->real_escape_string(mb_substr(trim($_POST['icon'] ?? '#'), 0, 4));
    $sort    = (int)($_POST['sort_order'] ?? 0);

    if ($name) {
        $cover_sql = '';
        if (!empty($_FILES['cover_image']['name'])) {
            $new_cover = save_cover_image($_FILES['cover_image'], $conn);
            if ($new_cover) {
                // 删旧图
                $old = $conn->query("SELECT cover_image FROM categories WHERE id=$edit_id")->fetch_assoc();
                if (!empty($old['cover_image'])) {
                    $old_path = __DIR__ . '/../' . $old['cover_image'];
                    if (file_exists($old_path)) unlink($old_path);
                }
                $safe_cover = $conn->real_escape_string($new_cover);
                $cover_sql = ", cover_image='$safe_cover'";
            }
        }
        $conn->query("UPDATE categories SET name='$name', description='$desc', color='$color', icon='$icon', sort_order=$sort $cover_sql WHERE id=$edit_id");
    }
    header("Location: admin_categories.php?msg=edit_ok"); exit;
}

// ── 新建 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name  = $conn->real_escape_string(trim($_POST['name'] ?? ''));
    $desc  = $conn->real_escape_string(trim($_POST['description'] ?? ''));
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#3fb950';
    $icon  = $conn->real_escape_string(mb_substr(trim($_POST['icon'] ?? '#'), 0, 4));
    $sort  = (int)($_POST['sort_order'] ?? 0);

    if (empty($_FILES['cover_image']['name'])) {
        $form_err = '请上传封面图片';
    } elseif ($name === '') {
        $form_err = '分区名称不能为空';
    } else {
        $cover = save_cover_image($_FILES['cover_image'], $conn);
        if (!$cover) {
            $form_err = '图片上传失败（格式须为 jpg/png/webp，≤5MB）';
        } else {
            $safe_cover = $conn->real_escape_string($cover);
            $ok = $conn->query("INSERT INTO categories (name, description, color, icon, sort_order, cover_image) VALUES ('$name','$desc','$color','$icon',$sort,'$safe_cover')");
            if ($ok) {
                header("Location: admin_categories.php?msg=create_ok"); exit;
            } else {
                $form_err = '数据库写入失败：' . $conn->error;
            }
        }
    }
}

$msg      = $_GET['msg'] ?? '';
$edit_cat = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $r = $conn->query("SELECT * FROM categories WHERE id=" . (int)$_GET['edit']);
    $edit_cat = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
}

$cats = [];
$cr = $conn->query("SELECT c.*, COUNT(p.id) as post_count
    FROM categories c
    LEFT JOIN posts p ON p.category_id = c.id AND p.status='已发布'
    GROUP BY c.id ORDER BY c.sort_order ASC, c.id ASC");
if ($cr) while ($c = $cr->fetch_assoc()) $cats[] = $c;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>分区管理 - 缪斯 MUSE</title>
    <style>
        .page-wrap { max-width: 820px; margin: 28px auto; padding: 0 16px; }
        .page-title { font-size: 18px; font-weight: 700; color: #e6edf3; font-family: "Courier New", monospace; margin-bottom: 6px; }
        .page-title span { color: #3fb950; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #6e7681; text-decoration: none; font-size: 12px; font-family: "Courier New", monospace; }
        .back-link:hover { color: #e6edf3; }

        .msg-bar { padding: 10px 16px; border-radius: 4px; font-size: 13px; margin-bottom: 16px; font-family: "Courier New", monospace; }
        .msg-ok  { background: rgba(63,185,80,.12); border: 1px solid rgba(63,185,80,.3); color: #3fb950; }
        .msg-err { background: rgba(248,81,73,.1);  border: 1px solid rgba(248,81,73,.3); color: #f85149; }

        .card { background: #161b22; border: 1px solid #30363d; border-radius: 6px; overflow: hidden; margin-bottom: 18px; }
        .card-head { padding: 12px 18px; border-bottom: 1px solid #30363d; font-size: 12px; font-weight: 700; color: #6e7681; letter-spacing: 1.2px; text-transform: uppercase; font-family: "Courier New", monospace; }
        .card-body { padding: 20px; }

        .form-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 12px; color: #6e7681; font-family: "Courier New", monospace; }
        .form-group input[type=text],
        .form-group input[type=number],
        .form-group textarea {
            background: #0d1117; border: 1px solid #30363d; border-radius: 4px;
            color: #e6edf3; padding: 7px 10px; font-size: 13px; font-family: inherit;
            outline: none; transition: border-color .15s; resize: none;
        }
        .form-group input:focus,
        .form-group textarea:focus { border-color: #3fb950; }
        .form-group input[type=color] {
            width: 46px; height: 34px; padding: 2px 4px;
            background: #0d1117; border: 1px solid #30363d; border-radius: 4px; cursor: pointer;
        }
        .form-group.grow { flex: 1; min-width: 150px; }
        .form-group.sm   { width: 80px; }
        .form-group.xs   { width: 64px; }

        /* 封面上传区 */
        .cover-wrap { display: flex; flex-direction: column; gap: 8px; width: 240px; flex-shrink: 0; }
        .cover-preview-box {
            width: 240px; height: 150px; border-radius: 6px;
            background: #0d1117; border: 1px solid #30363d;
            overflow: hidden; position: relative;
            display: flex; align-items: center; justify-content: center;
        }
        .cover-preview {
            width: 100%; height: 100%; object-fit: cover; display: none;
        }
        .cover-empty {
            display: flex; flex-direction: column; align-items: center; gap: 5px;
            color: #484f58; font-size: 12px; font-family: "Courier New", monospace; text-align: center;
        }
        .cover-empty span:first-child { font-size: 28px; }
        .cover-btn {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            background: #1c2128; border: 1px solid #30363d; border-radius: 4px;
            color: #8b949e; font-size: 13px; padding: 7px 14px; cursor: pointer;
            transition: .15s; font-family: inherit; width: 100%;
        }
        .cover-btn:hover { border-color: #3fb950; color: #3fb950; }
        .cover-btn input[type=file] { display: none; }
        .cover-required { font-size: 11px; color: #f85149; font-family: "Courier New", monospace; }

        .btn { padding: 7px 18px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; border: none; font-family: inherit; transition: .15s; }
        .btn-green { background: #3fb950; color: #fff; }
        .btn-green:hover { background: #2ea043; }
        .btn-outline { background: transparent; border: 1px solid #30363d; color: #8b949e; text-decoration: none; display: inline-block; }
        .btn-outline:hover { border-color: #8b949e; color: #e6edf3; }
        .btn-danger { background: rgba(248,81,73,.12); color: #f85149; border: 1px solid rgba(248,81,73,.3); text-decoration: none; display: inline-block; }
        .btn-danger:hover { background: rgba(248,81,73,.22); }
        .btn-sm { padding: 4px 12px; font-size: 12px; }

        /* 分区卡片列表 */
        .cat-list { display: flex; flex-direction: column; gap: 0; }
        .cat-row {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 18px; border-bottom: 1px solid #21262d;
            transition: background .15s;
        }
        .cat-row:last-child { border-bottom: none; }
        .cat-row:hover { background: #1c2128; }
        .cat-thumb {
            width: 80px; height: 50px; border-radius: 4px;
            object-fit: cover; flex-shrink: 0; border: 1px solid #30363d;
            background: #0d1117;
        }
        .cat-thumb-placeholder {
            width: 80px; height: 50px; border-radius: 4px; flex-shrink: 0;
            background: #0d1117; border: 1px solid #30363d;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: #484f58;
        }
        .cat-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .cat-info { flex: 1; min-width: 0; }
        .cat-name { font-size: 14px; font-weight: 700; color: #e6edf3; margin-bottom: 2px; }
        .cat-meta { font-size: 12px; color: #6e7681; font-family: "Courier New", monospace; }
        .cat-actions { display: flex; gap: 6px; flex-shrink: 0; }

        .empty-tip { padding: 32px; text-align: center; color: #6e7681; font-family: "Courier New", monospace; font-size: 13px; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="page-wrap">
    <a href="../categories.php" class="back-link">← 分区页面</a>
    <div class="page-title">// <span>分区管理</span></div>

    <?php if ($msg === 'create_ok'): ?>
    <div class="msg-bar msg-ok">✓ 分区创建成功</div>
    <?php elseif ($msg === 'edit_ok'): ?>
    <div class="msg-bar msg-ok">✓ 分区已更新</div>
    <?php elseif ($msg === 'delete_ok'): ?>
    <div class="msg-bar msg-ok">✓ 分区已删除</div>
    <?php endif; ?>
    <?php if (!empty($form_err)): ?>
    <div class="msg-bar msg-err">✗ <?= htmlspecialchars($form_err) ?></div>
    <?php endif; ?>

    <!-- 新建 / 编辑表单 -->
    <div class="card">
        <div class="card-head"><?= $edit_cat ? '编辑分区' : '新建分区' ?></div>
        <div class="card-body">
            <form method="POST" action="admin_categories.php<?= $edit_cat ? '?edit='.$edit_cat['id'] : '' ?>"
                  enctype="multipart/form-data">
                <?php if ($edit_cat): ?>
                    <input type="hidden" name="edit_id" value="<?= $edit_cat['id'] ?>">
                <?php else: ?>
                    <input type="hidden" name="action" value="create">
                <?php endif; ?>

                <div class="form-row" style="align-items:flex-start;">
                    <!-- 封面上传 -->
                    <div class="cover-wrap">
                        <label style="font-size:12px;color:#6e7681;font-family:'Courier New',monospace;">
                            封面图<?= $edit_cat ? '（留空保持不变）' : ' <span class="cover-required">*必填</span>' ?>
                        </label>
                        <!-- 预览框 -->
                        <div class="cover-preview-box">
                            <img class="cover-preview" id="cover-preview"
                                 <?php if ($edit_cat && !empty($edit_cat['cover_image'])): ?>
                                 src="../<?= htmlspecialchars($edit_cat['cover_image']) ?>" style="display:block;"
                                 <?php endif; ?>>
                            <div class="cover-empty" id="cover-empty"
                                 <?= ($edit_cat && !empty($edit_cat['cover_image'])) ? 'style="display:none;"' : '' ?>>
                                <span>🖼️</span>
                                <span>暂无封面</span>
                            </div>
                        </div>
                        <!-- 上传按钮 -->
                        <label class="cover-btn">
                            <input type="file" name="cover_image" accept="image/*" onchange="previewCover(this)">
                            📂 选择封面图片
                        </label>
                        <div style="font-size:11px;color:#484f58;font-family:'Courier New',monospace;">
                            jpg / png / webp · ≤ 5MB
                        </div>
                    </div>

                    <!-- 右侧字段 -->
                    <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:12px;">
                        <div class="form-row" style="margin-bottom:0;">
                            <div class="form-group grow">
                                <label>分区名称 *</label>
                                <input type="text" name="name" placeholder="如：游戏攻略" maxlength="50" required
                                       value="<?= htmlspecialchars($edit_cat['name'] ?? '') ?>">
                            </div>
                            <div class="form-group xs">
                                <label>图标</label>
                                <input type="text" name="icon" placeholder="#" maxlength="4"
                                       value="<?= htmlspecialchars($edit_cat['icon'] ?? '#') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>简介（可选）</label>
                            <input type="text" name="description" placeholder="一句话介绍" maxlength="200"
                                   value="<?= htmlspecialchars($edit_cat['description'] ?? '') ?>">
                        </div>
                        <div class="form-row" style="margin-bottom:0;align-items:flex-end;">
                            <div class="form-group">
                                <label>主题色</label>
                                <input type="color" name="color" value="<?= htmlspecialchars($edit_cat['color'] ?? '#3fb950') ?>">
                            </div>
                            <div class="form-group sm">
                                <label>排序</label>
                                <input type="number" name="sort_order" min="0" max="999"
                                       value="<?= (int)($edit_cat['sort_order'] ?? 0) ?>">
                            </div>
                            <div style="display:flex;gap:8px;margin-left:auto;">
                                <button type="submit" class="btn btn-green"><?= $edit_cat ? '保存修改' : '创建分区' ?></button>
                                <?php if ($edit_cat): ?>
                                <a href="admin_categories.php" class="btn btn-outline">取消</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 分区列表 -->
    <div class="card">
        <div class="card-head">现有分区（<?= count($cats) ?>）</div>
        <?php if (empty($cats)): ?>
        <div class="empty-tip">// 还没有分区</div>
        <?php else: ?>
        <div class="cat-list">
        <?php foreach ($cats as $c): ?>
        <div class="cat-row">
            <?php if (!empty($c['cover_image'])): ?>
            <img class="cat-thumb" src="../<?= htmlspecialchars($c['cover_image']) ?>" alt="">
            <?php else: ?>
            <div class="cat-thumb-placeholder"><?= htmlspecialchars($c['icon'] ?: '#') ?></div>
            <?php endif; ?>
            <span class="cat-dot" style="background:<?= htmlspecialchars($c['color']) ?>"></span>
            <div class="cat-info">
                <div class="cat-name"><?= htmlspecialchars($c['name']) ?></div>
                <div class="cat-meta"><?= (int)$c['post_count'] ?> 篇帖子
                    <?= !empty($c['description']) ? ' · ' . htmlspecialchars(mb_substr($c['description'],0,30)) : '' ?>
                </div>
            </div>
            <div class="cat-actions">
                <a href="admin_categories.php?edit=<?= $c['id'] ?>" class="btn btn-outline btn-sm">编辑</a>
                <a href="admin_categories.php?delete=<?= $c['id'] ?>" class="btn btn-danger btn-sm"
                   onclick="return confirm('删除「<?= htmlspecialchars($c['name']) ?>」？')">删除</a>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function previewCover(input) {
    const preview = document.getElementById('cover-preview');
    const empty   = document.getElementById('cover-empty');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (empty) empty.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>
<?php $conn->close(); ?>
