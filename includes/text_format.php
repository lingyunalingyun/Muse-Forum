<?php
/**
 * includes/text_format.php — 文本格式化函数库
 *
 * 提供帖子内容和评论的 #话题 / @提及 渲染，以及话题标签持久化。
 *
 * 函数列表：
 *   format_post_content($html, $conn)
 *     — 渲染富文本帖子内容中的 #话题 和 @提及，跳过 HTML 标签内部
 *   format_comment($text, $conn)
 *     — 渲染已经过 htmlspecialchars 存储的评论文本中的 #话题 和 @提及
 *   save_post_hashtags($conn, $post_id, $title, $content)
 *     — 解析帖子标题+内容中的 #话题，写入 topics / post_topics 表
 *     — 自动建表（首次运行）；先删旧关联再重新插入
 *   _resolve_mentions($names, $conn)  [内部]
 *     — 批量将 username 数组解析为 [username => id] 映射
 */
/**
 * format_post_content — 渲染帖子 HTML 内容中的 #话题 和 @提及
 * 使用分组正则，跳过 HTML 标签内部，只替换文本节点中的内容
 */
function format_post_content($html, $conn) {
    // 先收集所有 @username，批量查 ID
    $plain = strip_tags($html);
    preg_match_all('/@([\w\x{4e00}-\x{9fa5}]{1,20})/u', $plain, $mm);
    $name_id_map = _resolve_mentions(array_unique($mm[1]), $conn);

    // 用一个正则同时匹配 HTML 标签（跳过）、#话题、@提及
    return preg_replace_callback(
        '/(<[^>]+>)|#([\w\x{4e00}-\x{9fa5}]{1,20})|@([\w\x{4e00}-\x{9fa5}]{1,20})/u',
        function ($m) use ($name_id_map) {
            if (!empty($m[1])) return $m[1]; // HTML 标签原样保留
            if (!empty($m[2])) {             // #话题
                $tag = $m[2];
                return '<a href="../topic.php?tag=' . urlencode($tag)
                     . '" class="hashtag">#' . htmlspecialchars($tag) . '</a>';
            }
            if (!empty($m[3])) {             // @提及
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

/**
 * format_comment — 渲染评论文本（已经过 htmlspecialchars 存储）中的 #话题 和 @提及
 */
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

/**
 * save_post_hashtags — 解析帖子内容中的 #话题，写入 topics / post_topics 表
 */
function save_post_hashtags($conn, $post_id, $title, $content) {
    $post_id = intval($post_id);
    if ($post_id <= 0) return;

    // 建表（首次运行）
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

    // 删除旧关联，重新插入
    $conn->query("DELETE FROM post_topics WHERE post_id=$post_id");

    foreach ($tags as $tag) {
        $safe = $conn->real_escape_string($tag);
        // ON DUPLICATE KEY UPDATE 时通过 LAST_INSERT_ID(id) 拿到已有 ID
        $conn->query("INSERT INTO topics (name, use_count)
                      VALUES ('$safe', 1)
                      ON DUPLICATE KEY UPDATE use_count = use_count + 1, id = LAST_INSERT_ID(id)");
        $tid = $conn->insert_id;
        if ($tid > 0) {
            $conn->query("INSERT IGNORE INTO post_topics (post_id, topic_id) VALUES ($post_id, $tid)");
        }
    }
}

/**
 * _resolve_mentions — 批量将 username 数组解析为 [username => id] 映射
 */
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
