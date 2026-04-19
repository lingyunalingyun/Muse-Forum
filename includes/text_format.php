<?php
/**
 * text_format.php — 帖子文本格式化函数库
 *
 * 功能：format_post_content() 将帖子内容中的 #话题 渲染为话题链接、@用户名 渲染为个人页链接，
 *       使用分组正则跳过 HTML 标签内部
 * 读写表：读 users（验证 @ 提及的用户名有效性）
 * 权限：无（纯函数库）
 */

function format_post_content($html, $conn) {
    
    $plain = strip_tags($html);
    preg_match_all('/@([\w\x{4e00}-\x{9fa5}]{1,20})/u', $plain, $mm);
    $name_id_map = _resolve_mentions(array_unique($mm[1]), $conn);

    
    return preg_replace_callback(
        '/(<[^>]+>)|#([\w\x{4e00}-\x{9fa5}]{1,20})|@([\w\x{4e00}-\x{9fa5}]{1,20})/u',
        function ($m) use ($name_id_map) {
            if (!empty($m[1])) return $m[1]; 
            if (!empty($m[2])) {             
                $tag = $m[2];
                return '<a href="../topic.php?tag=' . urlencode($tag)
                     . '" class="hashtag">#' . htmlspecialchars($tag) . '</a>';
            }
            if (!empty($m[3])) {             
                $name = $m[3];
                if (isset($name_id_map[$name])) {
                    return '<a href="profile.php?id=' . $name_id_map[$name]
                         . '" class="mention">@' . htmlspecialchars($name) . '</a>';
                }
                return '@' . htmlspecialchars($name);
            }
            return $m[0];
        },
        $html
    ) ?: $html;
}

function format_comment($text, $conn) {
    preg_match_all('/@([\w\x{4e00}-\x{9fa5}]{1,20})/u', $text, $mm);
    $name_id_map = _resolve_mentions(array_unique($mm[1]), $conn);

    $text = preg_replace_callback(
        '/#([\w\x{4e00}-\x{9fa5}]{1,20})/u',
        function ($m) {
            return '<a href="../topic.php?tag=' . urlencode($m[1])
                 . '" class="hashtag">#' . htmlspecialchars($m[1]) . '</a>';
        },
        $text
    );

    $text = preg_replace_callback(
        '/@([\w\x{4e00}-\x{9fa5}]{1,20})/u',
        function ($m) use ($name_id_map) {
            $name = $m[1];
            if (isset($name_id_map[$name])) {
                return '<a href="profile.php?id=' . $name_id_map[$name]
                     . '" class="mention">@' . htmlspecialchars($name) . '</a>';
            }
            return '@' . htmlspecialchars($name);
        },
        $text
    );

    return $text;
}

function save_post_hashtags($conn, $post_id, $title, $content) {
    $post_id = intval($post_id);
    if ($post_id <= 0) return;

    
    $conn->query("CREATE TABLE IF NOT EXISTS topics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        use_count INT NOT NULL DEFAULT 0,
        UNIQUE KEY uname (name)
    ) DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS post_topics (
        post_id INT NOT NULL,
        topic_id INT NOT NULL,
        PRIMARY KEY (post_id, topic_id)
    ) DEFAULT CHARSET=utf8mb4");

    preg_match_all('/#([\w\x{4e00}-\x{9fa5}]{1,20})/u',
                   $title . ' ' . strip_tags($content), $m);
    $tags = array_unique($m[1]);
    if (empty($tags)) {
        $conn->query("DELETE FROM post_topics WHERE post_id=$post_id");
        return;
    }

    
    $conn->query("DELETE FROM post_topics WHERE post_id=$post_id");

    foreach ($tags as $tag) {
        $safe = $conn->real_escape_string($tag);
        
        $conn->query("INSERT INTO topics (name, use_count)
                      VALUES ('$safe', 1)
                      ON DUPLICATE KEY UPDATE use_count = use_count + 1, id = LAST_INSERT_ID(id)");
        $tid = $conn->insert_id;
        if ($tid > 0) {
            $conn->query("INSERT IGNORE INTO post_topics (post_id, topic_id) VALUES ($post_id, $tid)");
        }
    }
}

function _resolve_mentions(array $names, $conn) {
    $map = [];
    foreach ($names as $name) {
        if (empty($name)) continue;
        $safe = $conn->real_escape_string($name);
        $r = $conn->query("SELECT id FROM users WHERE username='$safe'");
        if ($r && $row = $r->fetch_assoc()) $map[$name] = (int)$row['id'];
    }
    return $map;
}
