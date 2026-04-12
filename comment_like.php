<?php
// comment_like.php
session_start();
$conn = new mysqli("localhost", "svh_b14f1q", "xrb23va4gs", "svh_b14f1q");
$conn->set_charset("utf8mb4");

if (!isset($_SESSION['user_id'])) die("need_login");

$uid = $_SESSION['user_id'];
$cid = intval($_POST['cid']);

if ($cid > 0) {
    // 检查是否已经点过赞
    $check = $conn->query("SELECT id FROM comment_likes WHERE user_id = $uid AND comment_id = $cid");
    
    if ($check->num_rows > 0) {
        // 已点赞，执行取消操作
        $conn->query("DELETE FROM comment_likes WHERE user_id = $uid AND comment_id = $cid");
        $conn->query("UPDATE comments SET likes = likes - 1 WHERE id = $cid");
        echo "unliked";
    } else {
        // 未点赞，执行点赞操作
        $conn->query("INSERT INTO comment_likes (user_id, comment_id) VALUES ($uid, $cid)");
        $conn->query("UPDATE comments SET likes = likes + 1 WHERE id = $cid");
        echo "liked";
    }
}
$conn->close();
?>