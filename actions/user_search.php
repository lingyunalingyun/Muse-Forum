<?php
/**
 * user_search.php — 用户名前缀搜索（@mention 联想）
 *
 * 功能：根据 GET 参数 q 按用户名前缀匹配，最多返回 8 条结果，
 *       用于编辑器 @mention 联想下拉
 * 读写表：users（只读）
 * 权限：需登录
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
