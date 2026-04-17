<?php
/**
 * pages/logout.php — 登出
 * 销毁 session 后重定向到首页。
 */
session_start();
session_destroy(); // 销毁所有 session
header("Location: ../index.php"); // 跳回主页
exit();
?>
