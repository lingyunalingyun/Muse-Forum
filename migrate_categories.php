<?php
/**
 * 分区系统数据库迁移
 * 运行一次后可删除此文件
 */
require_once __DIR__ . '/config.php';

$errors = [];
$done   = [];

// 1. 创建 categories 表
$sql1 = "CREATE TABLE IF NOT EXISTS categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL,
    description VARCHAR(200) DEFAULT '',
    color       VARCHAR(7)   DEFAULT '#3fb950',
    icon        VARCHAR(10)  DEFAULT '#',
    sort_order  INT          DEFAULT 0,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn->query($sql1)) $done[] = '创建 categories 表';
else $errors[] = 'categories 表：' . $conn->error;

// 2. posts 表加 category_id 列（如果还没有）
$col_check = $conn->query("SHOW COLUMNS FROM posts LIKE 'category_id'");
if ($col_check && $col_check->num_rows === 0) {
    if ($conn->query("ALTER TABLE posts ADD COLUMN category_id INT NULL DEFAULT NULL AFTER is_recommend"))
        $done[] = 'posts 表添加 category_id 列';
    else
        $errors[] = 'category_id 列：' . $conn->error;
} else {
    $done[] = 'category_id 列已存在，跳过';
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"><title>迁移结果</title>
<style>
body{background:#0d1117;color:#e6edf3;font-family:"Courier New",monospace;padding:40px;line-height:2;}
.ok{color:#3fb950;} .err{color:#f85149;}
</style>
</head>
<body>
<h2 style="color:#3fb950;">// 分区系统迁移</h2>
<?php foreach($done   as $m): ?><div class="ok">✓ <?= htmlspecialchars($m) ?></div><?php endforeach; ?>
<?php foreach($errors as $e): ?><div class="err">✗ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<?php if(empty($errors)): ?>
<p class="ok" style="margin-top:20px;">迁移完成！可以删除此文件。</p>
<p><a href="index.php" style="color:#58a6ff;">← 返回首页</a></p>
<?php endif; ?>
</body>
</html>
