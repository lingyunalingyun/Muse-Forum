<?php
// 经验 & 等级辅助函数

function get_level_by_exp(int $exp): int {
    if ($exp >= 50000) return 6;
    if ($exp >= 30000) return 5;
    if ($exp >= 15000) return 4;
    if ($exp >= 5000)  return 3;
    if ($exp >= 1000)  return 2;
    return 1;
}

function get_next_level_exp(int $level): ?int {
    $map = [1 => 1000, 2 => 5000, 3 => 15000, 4 => 30000, 5 => 50000, 6 => null];
    return $map[$level] ?? null;
}

function get_prev_level_exp(int $level): int {
    $map = [1 => 0, 2 => 1000, 3 => 5000, 4 => 15000, 5 => 30000, 6 => 50000];
    return $map[$level] ?? 0;
}

function get_level_color(int $level): string {
    $c = [1 => '#8b949e', 2 => '#58a6ff', 3 => '#3fb950', 4 => '#d29922', 5 => '#f0883e', 6 => '#f85149'];
    return $c[$level] ?? '#8b949e';
}

function get_level_name(int $level): string {
    $n = [1 => '新手', 2 => '学徒', 3 => '成员', 4 => '精英', 5 => '大师', 6 => '传奇'];
    return $n[$level] ?? '???';
}

function calc_login_exp(int $streak): int {
    return min($streak * 10, 100);
}

// 增加经验并自动更新等级
function add_exp($conn, int $user_id, int $amount): array {
    $conn->query("UPDATE users SET exp = exp + $amount WHERE id = $user_id");
    $r = $conn->query("SELECT exp FROM users WHERE id = $user_id");
    $exp = $r ? (int)$r->fetch_assoc()['exp'] : 0;
    $new_level = get_level_by_exp($exp);
    $conn->query("UPDATE users SET level = $new_level WHERE id = $user_id");
    return ['exp' => $exp, 'level' => $new_level];
}

// ─── 角色信息 ───────────────────────────────────────────
// role 取值：'owner' | 'admin' | 'sponsor' | 'user'
// is_banned 为 true 时无论 role 是什么都显示封禁样式
function get_role_info(string $role, bool $is_banned = false): array {
    if ($is_banned) return [
        'label'  => '已封禁',
        'color'  => '#6e7681',
        'bg'     => 'rgba(110,118,129,.12)',
        'border' => 'rgba(110,118,129,.35)',
    ];
    $map = [
        'owner'   => ['label'=>'★ 站长', 'color'=>'#f85149','bg'=>'rgba(248,81,73,.15)',  'border'=>'rgba(248,81,73,.4)'],
        'admin'   => ['label'=>'管理员', 'color'=>'#a78bfa','bg'=>'rgba(167,139,250,.15)','border'=>'rgba(167,139,250,.4)'],
        'sponsor' => ['label'=>'赞助者', 'color'=>'#3fb950','bg'=>'rgba(63,185,80,.15)',  'border'=>'rgba(63,185,80,.4)'],
        'user'    => ['label'=>'成员',   'color'=>'#58a6ff','bg'=>'rgba(88,166,255,.15)', 'border'=>'rgba(88,166,255,.4)'],
    ];
    return $map[$role] ?? $map['user'];
}

function get_role_badge(string $role, bool $is_banned = false, string $extra = ''): string {
    $i = get_role_info($role, $is_banned);
    return "<span style=\"font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;"
         . "letter-spacing:.3px;background:{$i['bg']};color:{$i['color']};border:1px solid {$i['border']};{$extra}\">"
         . $i['label'] . "</span>";
}

// ─── 检查并补全 users 表所需字段 ─────────────────────────
function ensure_user_columns($conn): void {
    $cols = [
        'email_verified'       => 'TINYINT(1) NOT NULL DEFAULT 0',
        'verify_token'         => 'VARCHAR(64) DEFAULT NULL',
        'verify_token_expires' => 'DATETIME DEFAULT NULL',
        'verify_resend_at'     => 'DATETIME DEFAULT NULL',
        'reset_token'          => 'VARCHAR(64) DEFAULT NULL',
        'reset_token_expires'  => 'DATETIME DEFAULT NULL',
        'exp'                  => 'INT NOT NULL DEFAULT 0',
        'login_streak'         => 'INT NOT NULL DEFAULT 0',
        'last_login_date'      => 'DATE DEFAULT NULL',
        'is_banned'            => 'TINYINT(1) NOT NULL DEFAULT 0',
        'ban_reason'           => 'VARCHAR(255) DEFAULT NULL',
        'show_followers'       => 'TINYINT(1) NOT NULL DEFAULT 1',
        'show_following'       => 'TINYINT(1) NOT NULL DEFAULT 1',
        'post_visibility'      => "VARCHAR(20) NOT NULL DEFAULT 'public'",
        'mid'                  => "CHAR(8) DEFAULT NULL",
        'is_cs'                => "TINYINT(1) NOT NULL DEFAULT 0",
    ];
    foreach ($cols as $col => $def) {
        $r = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
        if ($r && $r->num_rows === 0) {
            $conn->query("ALTER TABLE users ADD COLUMN $col $def");
        }
    }

    $no_mid = $conn->query("SELECT id FROM users WHERE mid IS NULL");
    if ($no_mid && $no_mid->num_rows > 0) {
        while ($row = $no_mid->fetch_assoc()) {
            $uid = (int)$row['id'];
            do {
                $new_mid = (string)random_int(10000000, 99999999);
                $chk = $conn->query("SELECT id FROM users WHERE mid='$new_mid'");
            } while ($chk && $chk->num_rows > 0);
            $conn->query("UPDATE users SET mid='$new_mid' WHERE id=$uid");
        }
    }

    // 黑名单表
    $conn->query("CREATE TABLE IF NOT EXISTS user_blocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blocker_id INT NOT NULL,
        blocked_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_block (blocker_id, blocked_id)
    )");
}

// 可见性检查：当前访问者能否看到 $author_id 的帖子
// 返回 true = 可以看，false = 不能看
function can_see_posts($conn, string $visibility, int $author_id, int $viewer_id, string $viewer_role = 'user'): bool {
    if ($viewer_id === $author_id) return true;
    if (in_array($viewer_role, ['admin', 'owner'])) return true;
    if ($visibility === 'public') return true;
    if ($visibility === 'private') return false;
    if ($viewer_id === 0) return false; // 未登录

    if ($visibility === 'followers') {
        // 访问者需关注作者
        $r = $conn->query("SELECT id FROM follows WHERE follower_id=$viewer_id AND followed_id=$author_id");
        return $r && $r->num_rows > 0;
    }
    if ($visibility === 'following') {
        // 作者需关注访问者
        $r = $conn->query("SELECT id FROM follows WHERE follower_id=$author_id AND followed_id=$viewer_id");
        return $r && $r->num_rows > 0;
    }
    if ($visibility === 'mutual') {
        // 互相关注
        $r1 = $conn->query("SELECT id FROM follows WHERE follower_id=$viewer_id AND followed_id=$author_id");
        $r2 = $conn->query("SELECT id FROM follows WHERE follower_id=$author_id AND followed_id=$viewer_id");
        return ($r1 && $r1->num_rows > 0) && ($r2 && $r2->num_rows > 0);
    }
    return true;
}

// 检查是否被拉黑（blocker 拉黑了 blocked）
function is_blocked($conn, int $blocker_id, int $blocked_id): bool {
    if ($blocker_id === 0 || $blocked_id === 0) return false;
    $r = $conn->query("SELECT id FROM user_blocks WHERE blocker_id=$blocker_id AND blocked_id=$blocked_id");
    return $r && $r->num_rows > 0;
}

// ─── 内部：构建 HTML 邮件模板 ───────────────────────────
function _build_email_html(string $title, string $greeting, string $body_html, string $btn_text, string $btn_link): string {
    $site = SITE_NAME;
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
        . '<body style="margin:0;padding:0;background:#0d1117;font-family:\'Courier New\',monospace;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#0d1117;padding:40px 20px;">'
        . '<tr><td align="center">'
        . '<table width="480" cellpadding="0" cellspacing="0" style="background:#161b22;border:1px solid #30363d;border-radius:6px;">'
        . '<tr><td style="padding:10px 28px;background:#0d1117;border-bottom:1px solid #21262d;">'
        . '<span style="color:#3fb950;font-size:13px;font-weight:700;">&gt; ' . htmlspecialchars($site) . ' // mail</span>'
        . '</td></tr>'
        . '<tr><td style="padding:32px;">'
        . '<p style="color:#3fb950;font-size:20px;font-weight:700;margin:0 0 20px;">&gt; ' . $title . '</p>'
        . '<p style="color:#8b949e;font-size:13px;margin:0 0 12px;">你好，' . htmlspecialchars($greeting) . '</p>'
        . '<div style="color:#8b949e;font-size:13px;line-height:1.8;margin:0 0 28px;">' . $body_html . '</div>'
        . '<table cellpadding="0" cellspacing="0" style="margin-bottom:28px;">'
        . '<tr><td style="background:#3fb950;border-radius:4px;">'
        . '<a href="' . htmlspecialchars($btn_link) . '" style="display:inline-block;padding:11px 32px;color:#fff;text-decoration:none;font-size:14px;font-weight:700;font-family:\'Courier New\',monospace;">' . htmlspecialchars($btn_text) . '</a>'
        . '</td></tr></table>'
        . '<p style="color:#6e7681;font-size:11px;margin:0 0 6px;">或复制链接到浏览器：</p>'
        . '<p style="color:#58a6ff;font-size:11px;word-break:break-all;margin:0 0 24px;padding:8px 12px;background:#0d1117;border-radius:4px;border:1px solid #21262d;">' . htmlspecialchars($btn_link) . '</p>'
        . '<p style="color:#6e7681;font-size:11px;margin:0;">如非本人操作，请忽略此邮件。</p>'
        . '</td></tr>'
        . '<tr><td style="padding:10px 28px;background:#0d1117;border-top:1px solid #21262d;">'
        . '<span style="color:#6e7681;font-size:11px;">— ' . htmlspecialchars($site) . ' 自动发送，请勿回复</span>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

// ─── 内部：发送 HTML 邮件 ───────────────────────────────
function _send_html_mail(string $to, string $subject, string $html): bool {
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = "From: " . SITE_NAME . " <" . MAIL_FROM . ">\r\n"
             . "Reply-To: " . MAIL_FROM . "\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n";
    return mail($to, $encoded_subject, $html, $headers);
}

// 发送邮箱验证邮件
function send_verify_email(string $to_addr, string $username, string $token): bool {
    $link    = SITE_URL . '/pages/verify.php?token=' . rawurlencode($token);
    $subject = '【' . SITE_NAME . '】请验证您的邮箱';
    $body    = '感谢注册 ' . SITE_NAME . '！请点击下方按钮完成邮箱验证。<br>'
             . '链接 <b style="color:#e6edf3;">24 小时</b>内有效。';
    $html    = _build_email_html('VERIFY_EMAIL_', $username, $body, '验证邮箱', $link);
    return _send_html_mail($to_addr, $subject, $html);
}

// 发送密码重置邮件
function send_reset_email(string $to_addr, string $username, string $token): bool {
    $link    = SITE_URL . '/pages/reset_password.php?token=' . rawurlencode($token);
    $subject = '【' . SITE_NAME . '】密码重置请求';
    $body    = '我们收到了你的密码重置请求。请点击下方按钮设置新密码。<br>'
             . '链接 <b style="color:#e6edf3;">1 小时</b>内有效。如非本人操作，请忽略此邮件并确认账号安全。';
    $html    = _build_email_html('RESET_PASSWORD_', $username, $body, '重置密码', $link);
    return _send_html_mail($to_addr, $subject, $html);
}
