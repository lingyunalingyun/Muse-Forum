<?php
/**
 * logout.php — 退出登录
 *
 * 功能：销毁当前 Session 并重定向至主页
 * 读写表：无
 * 权限：需登录
 */
session_start();
session_destroy(); // 销毁所有 session
header("Location: ../index.php"); // 跳回主页
exit();
?>
