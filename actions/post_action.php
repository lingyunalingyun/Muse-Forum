<?php
session_start();
require_once __DIR__ . '/../config.php';

$my_id = intval($_SESSION['user_id'] ?? 0);
$pid = intval($_POST['pid'] ?? 0);
$type = $_POST['type'] ?? '';

if ($my_id && $pid && ($type == 'like' || $type == 'fav')) {
    $table = ($type == 'like') ? 'post_likes' : 'post_favs';

    $check = $conn->query("SELECT id FROM $table WHERE post_id = $pid AND user_id = $my_id");

    if ($check->num_rows > 0) {
        $conn->query("DELETE FROM $table WHERE post_id = $pid AND user_id = $my_id");
        $active = false;
    } else {
        $conn->query("INSERT INTO $table (post_id, user_id) VALUES ($pid, $my_id)");
        $active = true;
    }

    $res = $conn->query("SELECT COUNT(*) as count FROM $table WHERE post_id = $pid");
    $new_count = $res ? $res->fetch_assoc()['count'] : 0;

    echo json_encode(['status' => 'success', 'active' => $active, 'new_count' => $new_count]);
}
$conn->close();
