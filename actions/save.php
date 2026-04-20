<?php
/**
 * save.php — 发布 / 保存草稿 / 发公告 / 转发帖子
 *
 * 功能：创建新帖或将草稿升级为正式帖，支持多分区选择、附件、话题标签及公告标记；
 *       发布后向管理员发送审核通知
 * 读写表：posts、post_categories、notifications、hashtags（via text_format）
 * 权限：需登录；封禁账号不可发布；公告仅 admin / owner
 */
ob_start();                      // 缓冲所有输出，防止 notice/warning 混入 JSON
error_reporting(0);              // 屏蔽 PHP 提示，避免污染 JSON
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/text_format.php';
ob_clean();                      // 清除 config.php 可能输出的任何空白
header('Content-Type: application/json; charset=utf-8');

$conn->set_charset("utf8mb4");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => '请先登录']);
    exit;
}
if (!empty($_SESSION['is_banned'])) {
    echo json_encode(['status' => 'error', 'msg' => '账号已被限制，无法发布帖子']);
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$title     = trim($_POST['title']   ?? '');
$content   = $_POST['content']      ?? '';
$draft_id  = (int)($_POST['draft_id']  ?? 0);
$is_draft  = ($_POST['is_draft']    ?? '0') === '1';
$repost_id = (int)($_POST['repost_id'] ?? 0);

// 转发帖允许标题和内容为空
if (!$repost_id) {
    if (empty($title)) {
        echo json_encode(['status' => 'error', 'msg' => '标题不能为空']);
        exit;
    }
    if (empty(trim(strip_tags($content)))) {
        echo json_encode(['status' => 'error', 'msg' => '内容不能为空']);
        exit;
    }
}

// 分区（多选）
$raw_cat_ids = $_POST['category_ids'] ?? [];
$cat_ids = [];
foreach ((array)$raw_cat_ids as $cid) {
    $v = (int)$cid;
    if ($v > 0) $cat_ids[] = $v;
}
$cat_ids = array_unique($cat_ids);
// 兼容旧 category_id 列：取第一个，没有则 NULL
$category_id = !empty($cat_ids) ? $cat_ids[0] : 'NULL';

// 确保关联表存在
$conn->query("CREATE TABLE IF NOT EXISTS post_categories (
    post_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (post_id, category_id)
) DEFAULT CHARSET=utf8mb4");

// 公告标记（仅管理员）
$is_notice = 0;
if (in_array($_SESSION['role'] ?? '', ['admin', 'owner']) && ($_POST['is_notice'] ?? '0') === '1') {
    $is_notice = 1;
}

// 附件列表（JSON 字符串）
$attachments = $_POST['attachments'] ?? '';
if (!empty($attachments)) {
    json_decode($attachments); // 验证格式
    if (json_last_error() !== JSON_ERROR_NONE) $attachments = '';
}

// 确定状态
if ($is_draft) {
    $status = '草稿';
} elseif ($is_notice) {
    $status = '已发布';
} else {
    $status = '待审核';
}

$safe_title       = $conn->real_escape_string($title);
$safe_content     = $conn->real_escape_string($content);
$safe_attachments = $conn->real_escape_string($attachments);

// 如果有 draft_id 就更新，否则插入
if ($draft_id > 0) {
    // 确认草稿属于当前用户
    $check = $conn->query("SELECT id FROM posts WHERE id = $draft_id AND user_id = $user_id AND status = '草稿'");
    if ($check && $check->num_rows > 0) {
        $sql = "UPDATE posts
                SET title='$safe_title', content='$safe_content', status='$status',
                    is_notice=$is_notice, attachments='$safe_attachments',
                    category_id=$category_id,
                    created_at = IF('$status'='草稿', created_at, NOW())
                WHERE id = $draft_id AND user_id = $user_id";
        $conn->query($sql);
        $new_id = $draft_id;
    } else {
        echo json_encode(['status' => 'error', 'msg' => '草稿不存在或无权限']);
        exit;
    }
} else {
    $safe_repost = $repost_id ?: 'NULL';
    $sql = "INSERT INTO posts (user_id, title, content, status, is_notice, attachments, category_id, repost_id)
            VALUES ($user_id, '$safe_title', '$safe_content', '$status', $is_notice, '$safe_attachments', $category_id, $safe_repost)";
    $insert_ok = $conn->query($sql);
    $new_id = $conn->insert_id ?: ($insert_ok ? -1 : 0);

    // 新帖子待审核时，通知所有管理员
    if ($status === '待审核' && $new_id) {
        $post_id_for_notif = ($new_id === -1)
            ? (int)$conn->query("SELECT MAX(id) c FROM posts WHERE user_id=$user_id")->fetch_assoc()['c']
            : $new_id;
        $admin_res = $conn->query("SELECT id FROM users WHERE role='admin'");
        if ($admin_res) {
            $now_n = date('Y-m-d H:i:s');
            while ($ar = $admin_res->fetch_assoc()) {
                $aid = intval($ar['id']);
                if ($aid !== $user_id) {
                    $conn->query("INSERT INTO notifications (user_id, from_user_id, type, post_id, created_at)
                                  VALUES ($aid, $user_id, 'post_review', $post_id_for_notif, '$now_n')");
                }
            }
        }
    }
}

if ($new_id) {
    // 同步 post_categories
    $real_id_for_cat = ($new_id === -1)
        ? (int)$conn->query("SELECT MAX(id) c FROM posts WHERE user_id=$user_id")->fetch_assoc()['c']
        : $new_id;
    $conn->query("DELETE FROM post_categories WHERE post_id=$real_id_for_cat");
    foreach ($cat_ids as $cid) {
        $conn->query("INSERT IGNORE INTO post_categories (post_id, category_id) VALUES ($real_id_for_cat, $cid)");
    }

    // 发布时（非草稿）保存话题标签
    if (!$is_draft) {
        $real_id = ($new_id === -1)
            ? (int)$conn->query("SELECT MAX(id) c FROM posts WHERE user_id=$user_id")->fetch_assoc()['c']
            : $new_id;
        save_post_hashtags($conn, $real_id, $title, $content);
    }

    if ($is_draft) {
        echo json_encode(['status' => 'ok', 'type' => 'draft', 'id' => $new_id, 'msg' => '草稿已保存']);
    } elseif ($is_notice) {
        echo json_encode(['status' => 'ok', 'type' => 'notice', 'id' => $new_id, 'msg' => '公告已发布']);
    } else {
        echo json_encode(['status' => 'ok', 'type' => 'post', 'id' => $new_id, 'msg' => '帖子已提交，等待审核']);
    }
} else {
    error_log("save.php 写入失败: " . $conn->error);
    echo json_encode(['status' => 'error', 'msg' => '写入失败，请稍后重试']);
}

$conn->close();
?>
