<?php
/**
 * actions/user_search.php — @提及用户名前缀搜索（AJAX JSON）
 *
 * 供发帖/评论框的 @mention 自动补全下拉菜单调用。
 *
 * GET 参数：q（搜索前缀，至少 1 个字符）
 * 返回：[{"id":...,"username":...,"avatar":...}]，最多 8 条，未登录返回 []
 * 读表：users
 */
ob_start();
error_reporting(0);
session_start();
require_once __DIR__ . '/../config.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

$safe = $conn->real_escape_string($q);
$res  = $conn->query("SELECT id, username, avatar FROM users WHERE username LIKE '$safe%' LIMIT 8");
$users = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $users[] = [
            'id'       => (int)$r['id'],
            'username' => $r['username'],
            'avatar'   => $r['avatar'] ?: 'default.png',
        ];
    }
}
echo json_encode($users);
$conn->close();
?>
