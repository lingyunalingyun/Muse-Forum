<?php
/**
 * repost_card.php — 转发卡片渲染函数
 *
 * 功能：提供 render_repost_card($conn, $post_id, $base) 函数，返回转发卡片 HTML 字符串
 * 读写表：读 posts、users
 * 权限：无（纯函数库）
 */

function render_repost_card($conn, int $post_id, string $base = '../'): string {
    $r = $conn->query(
        "SELECT p.id, p.title, p.content, p.created_at, u.username, u.avatar
         FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id = $post_id"
    );
    if (!$r || !($row = $r->fetch_assoc())) {
        return '<div class="repost-card deleted">// 原帖已被删除</div>';
    }
    $title   = $row['title']
        ? htmlspecialchars($row['title'])
        : htmlspecialchars($row['username']) . ' 的分享';
    $preview = htmlspecialchars(mb_substr(strip_tags($row['content']), 0, 80));
    $av      = htmlspecialchars($row['avatar'] ?: 'default.png');
    $date    = date('m-d H:i', strtotime($row['created_at']));
    $link    = $base . 'pages/post.php?id=' . $post_id;

    return '<a href="' . $link . '" class="repost-card" onclick="event.stopPropagation()">'
         . '<div class="repc-author">'
         . '<img src="' . $base . 'uploads/' . $av . '" onerror="this.onerror=null;this.src=\'' . $base . 'uploads/default.png\'">'
         . '<span>' . htmlspecialchars($row['username']) . '</span>'
         . '<span class="repc-date">' . $date . '</span>'
         . '</div>'
         . '<div class="repc-title">' . $title . '</div>'
         . ($preview ? '<div class="repc-preview">' . $preview . '</div>' : '')
         . '</a>';
}
?>
