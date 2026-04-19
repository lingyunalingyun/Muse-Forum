<?php
/**
 * logout.php — 登出
 *
 * 功能：销毁 session 后跳转首页
 * 读写表：无 DB 操作
 * 权限：无
 */
session_start();
session_destroy();
header("Location: ../index.php"); 
exit();
?>
