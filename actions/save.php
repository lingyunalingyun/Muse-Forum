<?php
/**
 * save.php — 发帖或保存草稿，支持转发和话题解析
 *
 * 功能：发布新帖子或保存为草稿，支持转发（repost_id）、附件 JSON 和 #话题# 解析
 * POST 参数：title, content, repost_id（可选）, attachments（JSON）, topic 等
 * 读写表：posts, post_topics, topics, notifications
 * 权限：需登录且未被封禁
 */
ob_start();
error_reporting(0);
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/text_format.php';
ob_clean();                      
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

$raw_cat_ids = $_POST['category_ids'] ?? [];
$cat_ids = [];
foreach ((array)$raw_cat_ids as $cid) {
    $v = (int)$cid;
    if ($v > 0) $cat_ids[] = $v;
}
$cat_ids = array_unique($cat_ids);

$category_id = !empty($cat_ids) ? $cat_ids[0] : 'NULL';

$conn->query("CREATE TABLE IF NOT EXISTS post_categories (
    post_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (post_id, category_id)
) DEFAULT CHARSET=utf8mb4");

$is_notice = 0;
if (in_array($_SESSION['role'] ?? '', ['admin', 'owner']) && ($_POST['is_notice'] ?? '0') === '1') {
    $is_notice = 1;
}

$attachments = $_POST['attachments'] ?? '';
if (!empty($attachments)) {
    json_decode($attachments); 
    if (json_last_error() !== JSON_ERROR_NONE) $attachments = '';
}

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

if ($draft_id > 0) {
    
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
    
    $real_id_for_cat = ($new_id === -1)
        ? (int)$conn->query("SELECT MAX(id) c FROM posts WHERE user_id=$user_id")->fetch_assoc()['c']
        : $new_id;
    $conn->query("DELETE FROM post_categories WHERE post_id=$real_id_for_cat");
    foreach ($cat_ids as $cid) {
        $conn->query("INSERT IGNORE INTO post_categories (post_id, category_id) VALUES ($real_id_for_cat, $cid)");
    }

    
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
