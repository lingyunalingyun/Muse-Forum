<?php
/**
 * migrate_cat_cover.php — 分区封面字段迁移脚本
 *
 * 功能：为 categories 表添加 cover_image 列并创建上传目录，运行一次后可删除
 * 读写表：categories
 * 权限：admin（手动执行，无鉴权，用后删除）
 */
require_once __DIR__ . '/config.php';
$done = []; $errors = [];

// 1. categories 加 cover_image 列
$col = $conn->query("SHOW COLUMNS FROM categories LIKE 'cover_image'");
if ($col && $col->num_rows === 0) {
    if ($conn->query("ALTER TABLE categories ADD COLUMN cover_image VARCHAR(255) DEFAULT '' AFTER icon"))
        $done[] = 'categories 表添加 cover_image 列';
    else $errors[] = $conn->error;
} else { $done[] = 'cover_image 列已存在，跳过'; }

// 2. 确保上传目录存在
$dir = __DIR__ . '/uploads/categories';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
    $done[] = '创建 uploads/categories/ 目录';
} else { $done[] = 'uploads/categories/ 目录已存在'; }

$conn->close();
?><!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>迁移</title>
<style>body{background:#0d1117;color:#e6edf3;font-family:"Courier New",monospace;padding:40px;line-height:2;}.ok{color:#3fb950;}.err{color:#f85149;}</style>
</head><body>
<h2 style="color:#3fb950;">// 分区封面迁移</h2>
<?php foreach($done as $m): ?><div class="ok">✓ <?=htmlspecialchars($m)?></div><?php endforeach; ?>
<?php foreach($errors as $e): ?><div class="err">✗ <?=htmlspecialchars($e)?></div><?php endforeach; ?>
<?php if(empty($errors)): ?><p class="ok" style="margin-top:20px;">完成！可删除此文件。</p>
<p><a href="pages/admin_categories.php" style="color:#58a6ff;">→ 去管理分区</a></p><?php endif; ?>
</body></html>
