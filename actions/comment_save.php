<?php
/**
 * comment_save.php — 发布评论或回复
 *
 * 功能：向指定帖子写入新评论或子回复；发布后通知帖子作者、被回复者及
 *       内容中 @mention 的用户
 * 读写表：comments、notifications
 * 权限：需登录
 */
// comment_save.php - 处理评论与回复
header('Content-Type: text/plain; charset=utf-8');
session_start();

if (!isset($_SESSION['user_id'])) {
    die("请先登录");
}

require_once __DIR__ . '/../config.php';

$post_id   = isset($_POST['post_id'])   ? intval($_POST['post_id'])   : 0;
$parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
$user_id   = intval($_SESSION['user_id']);
$content   = isset($_POST['content']) ? htmlspecialchars(trim($_POST['content'])) : '';

if ($post_id > 0 && !empty($content)) {
    $stmt = $conn->prepare("INSERT INTO comments (post_id, parent_id, user_id, content) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $post_id, $parent_id, $user_id, $content);

    if ($stmt->execute()) {
        $new_cid = $stmt->insert_id;

        // 通知帖子作者（自己评论自己不通知）
        $pr = $conn->query("SELECT user_id FROM posts WHERE id = $post_id");
        $post_author = $pr ? (int)$pr->fetch_assoc()['user_id'] : 0;
        if ($post_author && $post_author !== $user_id) {
            $ntype = $parent_id > 0 ? 'reply' : 'comment';
            $conn->query("INSERT INTO notifications (user_id, from_user_id, type, post_id, comment_id)
                          VALUES ($post_author, $user_id, '$ntype', $post_id, $new_cid)");
        }

        // 若是回复，额外通知被回复评论的作者
        if ($parent_id > 0) {
            $cr = $conn->query("SELECT user_id FROM comments WHERE id = $parent_id");
            $parent_author = $cr ? (int)$cr->fetch_assoc()['user_id'] : 0;
            if ($parent_author && $parent_author !== $user_id && $parent_author !== $post_author) {
                $conn->query("INSERT INTO notifications (user_id, from_user_id, type, post_id, comment_id)
                              VALUES ($parent_author, $user_id, 'reply', $post_id, $new_cid)");
            }
        }

        // @mention 检测
        preg_match_all('/@([^\s@]{1,20})/u', $content, $matches);
        $mentioned_users = array_unique($matches[1]);
        foreach ($mentioned_users as $mentioned_name) {
            $safe_name = $conn->real_escape_string($mentioned_name);
            $mr = $conn->query("SELECT id FROM users WHERE username = '$safe_name'");
            if ($mr && $mr->num_rows > 0) {
                $mentioned_id = (int)$mr->fetch_assoc()['id'];
                if ($mentioned_id !== $user_id) {
                    $conn->query("INSERT INTO notifications (user_id, from_user_id, type, post_id, comment_id)
                                  VALUES ($mentioned_id, $user_id, 'mention', $post_id, $new_cid)");
                }
            }
        }

        echo "success";
    } else {
        error_log("comment_save 写入失败: " . $conn->error);
        echo "error";
    }
    $stmt->close();
} else {
    echo "内容不能为空";
}
$conn->close();
?>
