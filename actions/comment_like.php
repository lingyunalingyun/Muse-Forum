<?php
/**
 * comment_like.php — 评论点赞 toggle，返回 JSON
 *
 * 功能：对评论执行点赞/取消点赞，并发送通知给评论作者
 * POST 参数：comment_id
 * 读写表：comment_likes, notifications
 * 权限：需登录
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) die("need_login");
if (!empty($_SESSION['is_banned'])) die("banned");

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
