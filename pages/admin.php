<?php
session_start();

// 核心安全检查：如果没登录，或者登录了但不是 admin，直接踢出去
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Content-Type: text/html; charset=utf-8");
    die("<h3>🚫 权限不足</h3>你不是管理员，无法进入后台。 <a href='../index.php'>返回主页</a>");
}

// 1. 数据库配置
require_once __DIR__ . '/../config.php';

// --- 【核心逻辑：处理管理操作】 ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action == 'approve') {
        // 审核通过：将状态改为"已发布"
        $conn->query("UPDATE posts SET status = '已发布' WHERE id = $id");
    } elseif ($action == 'delete') {
        // 删除：直接从数据库抹除
        $conn->query("DELETE FROM posts WHERE id = $id");
    }
    // 操作完刷新页面，看到最新状态
    header("Location: admin.php");
    exit();
}

// 获取所有内容显示在表格里，同时关联用户表的 username 和 userid
$sql = "SELECT p.*, u.username, u.userid FROM posts p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>内容管理中心 - 实时操作版</title>
    <style>
        body { font-family: 'Microsoft YaHei', sans-serif; padding: 20px; background: #fafafa; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #eee; padding: 12px; text-align: left; vertical-align: top; }
        th { background-color: #f8f9fa; color: #666; }

        .content-preview {
            max-height: 200px;
            overflow-y: auto;
            font-size: 14px;
            line-height: 1.6;
            background: #fdfdfd;
            padding: 10px;
            border-radius: 4px;
        }
        .content-preview img {
            max-width: 150px;
            height: auto;
            border: 1px solid #eee;
            margin: 5px;
        }

        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 12px; display: inline-block; }
        .btn-approve { background: #28a745; color: white; }
        .btn-delete { background: #dc3545; color: white; margin-left: 5px; }
        .status-tag { padding: 2px 6px; border-radius: 3px; font-size: 12px; }
        .status-wait { background: #ffeaa7; color: #d63031; }
        .status-ok { background: #55efc4; color: #00b894; }
        .author-tag { color: #007bff; font-weight: bold; font-size: 12px; display: block; margin-bottom: 2px; }
        .uid-tag { color: #999; font-size: 11px; display: block; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
    <div class="container">
        <h2>网站内容管理中心</h2>
        <p>管理员：<strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> | <a href="../index.php">返回主页</a></p>

        <table>
            <thead>
                <tr>
                    <th width="50">ID</th>
                    <th width="150">发布者</th>
                    <th>内容预览 (支持富文本)</th>
                    <th width="80">状态</th>
                    <th width="140">管理操作</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td>
                        <span class="author-tag"><?php echo htmlspecialchars($row['username'] ?? '未知用户'); ?></span>
                        <?php if(!empty($row['userid'])): ?>
                            <span class="uid-tag">ID: <?php echo $row['userid']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="content-preview">
                            <?php echo htmlspecialchars($row['content']); ?>
                        </div>
                    </td>
                    <td>
                        <span class="status-tag <?php echo ($row['status'] == '已发布') ? 'status-ok' : 'status-wait'; ?>">
                            <?php echo $row['status']; ?>
                        </span>
                    </td>
                    <td>
                        <?php if($row['status'] !== '已发布'): ?>
                            <a href="admin.php?action=approve&id=<?php echo $row['id']; ?>" class="btn btn-approve" onclick="return confirm('确认通过审核并发布吗？')">通过</a>
                        <?php endif; ?>
                        <a href="admin.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-delete" onclick="return confirm('警告：确认要永久删除这条数据吗？')">删除</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
