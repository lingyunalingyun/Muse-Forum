<?php
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => '请先登录']);
    exit;
}
if (!empty($_SESSION['is_banned'])) {
    echo json_encode(['status' => 'error', 'msg' => '账号已被限制']);
    exit;
}

$uid    = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

$conn->query("CREATE TABLE IF NOT EXISTS social_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    intro TEXT NOT NULL,
    tags VARCHAR(200) DEFAULT '',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user (user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if ($action === 'save') {
    $gender    = trim($_POST['gender']    ?? '');
    $age_range = trim($_POST['age_range'] ?? '');
    $intro     = trim($_POST['intro']     ?? '');
    $tags      = trim($_POST['tags']      ?? '');

    if (!in_array($gender, ['男', '女'])) {
        echo json_encode(['status' => 'error', 'msg' => '请选择性别']);
        exit;
    }
    if (empty($age_range)) {
        echo json_encode(['status' => 'error', 'msg' => '请填写年龄段']);
        exit;
    }
    
    $min_age = (int)preg_replace('/[^0-9].*/', '', $age_range);
    if ($min_age > 0 && $min_age < 18) {
        echo json_encode(['status' => 'error', 'msg' => '最小年龄不能低于 18 岁']);
        exit;
    }
    if (empty($intro)) {
        echo json_encode(['status' => 'error', 'msg' => '自我介绍不能为空']);
        exit;
    }
    if (mb_strlen($intro) > 300) {
        echo json_encode(['status' => 'error', 'msg' => '介绍最多 300 字']);
        exit;
    }

    $safe_gender    = $conn->real_escape_string($gender);
    $safe_age_range = $conn->real_escape_string(mb_substr($age_range, 0, 20));
    $safe_intro     = $conn->real_escape_string($intro);
    $safe_tags      = $conn->real_escape_string(mb_substr($tags, 0, 200));

    $conn->query("INSERT INTO social_cards (user_id, gender, age_range, intro, tags, is_active)
                  VALUES ($uid, '$safe_gender', '$safe_age_range', '$safe_intro', '$safe_tags', 1)
                  ON DUPLICATE KEY UPDATE
                      gender='$safe_gender', age_range='$safe_age_range',
                      intro='$safe_intro', tags='$safe_tags', is_active=1,
                      updated_at=NOW()");

    echo json_encode(['status' => 'ok', 'msg' => '卡片已投放']);

} elseif ($action === 'delete') {
    $conn->query("DELETE FROM social_cards WHERE user_id=$uid");
    echo json_encode(['status' => 'ok', 'msg' => '卡片已撤回']);

} elseif ($action === 'admin_delete') {
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, ['admin', 'owner'])) {
        echo json_encode(['status' => 'error', 'msg' => '权限不足']);
        exit;
    }
    $target = (int)($_POST['target_uid'] ?? 0);
    if ($target <= 0) {
        echo json_encode(['status' => 'error', 'msg' => '参数错误']);
        exit;
    }
    $conn->query("DELETE FROM social_cards WHERE user_id=$target");
    echo json_encode(['status' => 'ok', 'msg' => '卡片已删除']);

} else {
    echo json_encode(['status' => 'error', 'msg' => '未知操作']);
}

$conn->close();
?>
