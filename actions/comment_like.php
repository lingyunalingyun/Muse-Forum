<?php
/**
 * actions/comment_like.php — 评论点赞 / 取消点赞（toggle）
 *
 * POST 参数：cid（评论 ID）
 * 返回：纯文本 "liked" 或 "unliked"
 * 点赞时：
 *   - comments.likes 计数 +1
 *   - 向评论作者发送 like_comment 通知（不通知自己）
 * 取消时：comments.likes 计数 -1，删除点赞记录
 * 读写表：comment_likes, comments（likes）, notifications
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) die("need_login");

$uid = $_SESSION['user_id'];
$cid = intval($_POST['cid']);

if ($cid > 0) {
    $check = $conn->query("SELECT id FROM comment_likes WHERE user_id = $uid AND comment_id = $cid");

    if ($check->num_rows > 0) {
        $conn->query("DELETE FROM comment_likes WHERE user_id = $uid AND comment_id = $cid");
        $conn->query("UPDATE comments SET likes = likes - 1 WHERE id = $cid");
        echo "unliked";
    } else {
        $conn->query("INSERT INTO comment_likes (user_id, comment_id) VALUES ($uid, $cid)");
        $conn->query("UPDATE comments SET likes = likes + 1 WHERE id = $cid");

        // 通知评论作者
        $cr = $conn->query("SELECT user_id, post_id FROM comments WHERE id = $cid");
        $c_info = $cr ? $cr->fetch_assoc() : null;
        if ($c_info && (int)$c_info['user_id'] !== $uid) {
            $c_author  = (int)$c_info['user_id'];
            $c_post_id = (int)$c_info['post_id'];
            $conn->query("INSERT INTO notifications (user_id, from_user_id, type, post_id, comment_id)
                          VALUES ($c_author, $uid, 'like_comment', $c_post_id, $cid)");
        }

        echo "liked";
    }
}
$conn->close();
?>
