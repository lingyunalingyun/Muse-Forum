<?php
session_start();
session_destroy(); // 销毁所有 session
header("Location: ../index.php"); // 跳回主页
exit();
?>
