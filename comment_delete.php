<?php
session_start();
$conn = new mysqli("localhost", "svh_b14f1q", "xrb23va4gs", "svh_b14f1q");
$id = intval($_GET['id']);
$uid = $_SESSION['user_id'];
$role = $_SESSION['role'];
// 检查是否有权删除
$check = $conn->query("SELECT user_id FROM comments WHERE id = $id")->fetch_assoc();
if($check['user_id'] == $uid || $role == 'admin') {
    $conn->query("DELETE FROM comments WHERE id = $id OR parent_id = $id"); // 删除评论及其回复
    echo "success";
}