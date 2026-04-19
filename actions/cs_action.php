<?php
/**
 * cs_action.php — 客服聊天 action 处理端点
 *
 * 功能：处理客服相关操作，包括发送消息、关闭工单等
 * POST 参数：action, chat_id, content 等（视具体操作而定）
 * 读写表：cs_chats, messages
 * 权限：需登录
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exp_helper.php';

header('Content-Type: application/json');

$conn->query("CREATE TABLE IF NOT EXISTS cs_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(80) NOT NULL,
    description TEXT,
    status ENUM('pending','active','resolved') DEFAULT 'pending',
    cs_id INT DEFAULT NULL,
    last_cs_reply_at DATETIME DEFAULT NULL,
    next_comp_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("CREATE TABLE IF NOT EXISTS cs_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    sender_id INT NOT NULL,
    is_cs TINYINT(1) DEFAULT 0,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("ALTER TABLE cs_tickets  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("ALTER TABLE cs_messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$uid      = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$role     = $_SESSION['role'] ?? 'user';
$is_owner = ($role === 'owner');
$is_cs    = in_array($role, ['admin', 'owner']) || !empty($_SESSION['is_cs']);
$action   = $_POST['action'] ?? $_GET['action'] ?? '';

function maybe_award_comp($conn, array $ticket): void {
    if ($ticket['status'] !== 'active') return;
    if (empty($ticket['next_comp_at'])) return;
    if (strtotime($ticket['next_comp_at']) > time()) return;
    $tid = (int)$ticket['id'];
    add_exp($conn, (int)$ticket['user_id'], 100);
    $conn->query("UPDATE cs_tickets SET next_comp_at = DATE_ADD(NOW(), INTERVAL 12 HOUR) WHERE id=$tid");
}

switch ($action) {

    case 'create_ticket':
        if (!$uid) { echo json_encode(['status'=>'error','msg'=>'请先登录']); exit; }
        $r = $conn->query("SELECT id FROM cs_tickets WHERE user_id=$uid AND status IN ('pending','active') LIMIT 1");
        if ($r && $r->num_rows > 0) {
            echo json_encode(['status'=>'error','msg'=>'您已有进行中的工单']); exit;
        }
        $type = $conn->real_escape_string(trim($_POST['type'] ?? ''));
        $desc = $conn->real_escape_string(trim($_POST['description'] ?? ''));
        if (!$type) { echo json_encode(['status'=>'error','msg'=>'请选择问题类型']); exit; }
        $conn->query("INSERT INTO cs_tickets (user_id, type, description, next_comp_at)
                      VALUES ($uid, '$type', '$desc', DATE_ADD(NOW(), INTERVAL 12 HOUR))");
        echo json_encode(['status'=>'ok','ticket_id'=>$conn->insert_id]);
        break;

    case 'send_message':
        if (!$uid) { echo json_encode(['status'=>'error','msg'=>'请先登录']); exit; }
        $tid     = (int)($_POST['ticket_id'] ?? 0);
        $content = $conn->real_escape_string(trim($_POST['content'] ?? ''));
        if (!$content) { echo json_encode(['status'=>'error','msg'=>'消息不能为空']); exit; }
        if ($is_cs) {
            $check = $conn->query("SELECT id FROM cs_tickets WHERE id=$tid AND cs_id=$uid AND status='active'");
        } else {
            $check = $conn->query("SELECT id FROM cs_tickets WHERE id=$tid AND user_id=$uid AND status='active'");
        }
        if (!$check || $check->num_rows === 0) {
            echo json_encode(['status'=>'error','msg'=>'无权限或工单未激活']); exit;
        }
        $ic = $is_cs ? 1 : 0;
        $conn->query("INSERT INTO cs_messages (ticket_id, sender_id, is_cs, content) VALUES ($tid, $uid, $ic, '$content')");
        if ($is_cs) {
            $conn->query("UPDATE cs_tickets SET last_cs_reply_at=NOW(), next_comp_at=DATE_ADD(NOW(), INTERVAL 12 HOUR) WHERE id=$tid");
        }
        echo json_encode(['status'=>'ok']);
        break;

    case 'poll_messages':
        if (!$uid) { echo json_encode(['status'=>'error']); exit; }
        $tid     = (int)($_GET['ticket_id'] ?? 0);
        $last_id = (int)($_GET['last_id'] ?? 0);
        if ($is_cs) {
            $tr = $conn->query("SELECT * FROM cs_tickets WHERE id=$tid AND cs_id=$uid");
        } else {
            $tr = $conn->query("SELECT * FROM cs_tickets WHERE id=$tid AND user_id=$uid");
        }
        if (!$tr || $tr->num_rows === 0) { echo json_encode(['status'=>'error']); exit; }
        $ticket = $tr->fetch_assoc();
        maybe_award_comp($conn, $ticket);
        $mr = $conn->query("SELECT id, sender_id, is_cs, content, created_at FROM cs_messages WHERE ticket_id=$tid AND id>$last_id ORDER BY id ASC");
        $msgs = [];
        while ($m = $mr->fetch_assoc()) $msgs[] = $m;
        $sr = $conn->query("SELECT status FROM cs_tickets WHERE id=$tid");
        $st = $sr->fetch_assoc()['status'];
        echo json_encode(['status'=>'ok','messages'=>$msgs,'ticket_status'=>$st]);
        break;

    case 'join_ticket':
        if (!$uid || !$is_cs) { echo json_encode(['status'=>'error','msg'=>'无权限']); exit; }
        $tid = (int)($_POST['ticket_id'] ?? 0);
        $conn->query("UPDATE cs_tickets SET status='active', cs_id=$uid, last_cs_reply_at=NOW(), next_comp_at=DATE_ADD(NOW(), INTERVAL 12 HOUR) WHERE id=$tid AND status='pending'");
        if ($conn->affected_rows > 0) {
            echo json_encode(['status'=>'ok']);
        } else {
            echo json_encode(['status'=>'error','msg'=>'工单已被接管或不存在']);
        }
        break;

    case 'resolve_ticket':
        if (!$uid || !$is_cs) { echo json_encode(['status'=>'error','msg'=>'无权限']); exit; }
        $tid = (int)($_POST['ticket_id'] ?? 0);
        $conn->query("UPDATE cs_tickets SET status='resolved', resolved_at=NOW(), next_comp_at=NULL WHERE id=$tid AND cs_id=$uid AND status='active'");
        echo json_encode(['status' => $conn->affected_rows > 0 ? 'ok' : 'error']);
        break;

    case 'get_my_ticket':
        if (!$uid) { echo json_encode(['status'=>'none']); exit; }
        $r = $conn->query("SELECT id, type, status, created_at FROM cs_tickets WHERE user_id=$uid AND status IN ('pending','active') ORDER BY id DESC LIMIT 1");
        if (!$r || $r->num_rows === 0) { echo json_encode(['status'=>'none']); exit; }
        echo json_encode(['status'=>'ok','ticket'=>$r->fetch_assoc()]);
        break;

    case 'list_pending':
        if (!$uid || !$is_cs) { echo json_encode(['status'=>'error']); exit; }
        $r = $conn->query("SELECT id, type, description, created_at FROM cs_tickets WHERE status='pending' ORDER BY id ASC");
        $list = [];
        while ($row = $r->fetch_assoc()) $list[] = $row;
        echo json_encode(['status'=>'ok','tickets'=>$list]);
        break;

    case 'list_my_active':
        if (!$uid || !$is_cs) { echo json_encode(['status'=>'error']); exit; }
        $r = $conn->query("SELECT id, type, description, created_at FROM cs_tickets WHERE cs_id=$uid AND status='active' ORDER BY id ASC");
        $list = [];
        while ($row = $r->fetch_assoc()) $list[] = $row;
        echo json_encode(['status'=>'ok','tickets'=>$list]);
        break;

    case 'add_cs_agent':
        if (!$uid || !$is_owner) { echo json_encode(['status'=>'error','msg'=>'无权限']); exit; }
        $mid = $conn->real_escape_string(trim($_POST['mid'] ?? ''));
        if (!preg_match('/^\d{8}$/', $mid)) { echo json_encode(['status'=>'error','msg'=>'MID 格式不正确']); exit; }
        $r = $conn->query("SELECT id, username, role FROM users WHERE mid='$mid'");
        if (!$r || $r->num_rows === 0) { echo json_encode(['status'=>'error','msg'=>'未找到该 MID 对应的用户']); exit; }
        $target = $r->fetch_assoc();
        if (in_array($target['role'], ['owner'])) { echo json_encode(['status'=>'error','msg'=>'无法修改站长权限']); exit; }
        $conn->query("UPDATE users SET is_cs=1 WHERE mid='$mid'");
        echo json_encode(['status'=>'ok','username'=>$target['username'],'user_id'=>(int)$target['id']]);
        break;

    case 'remove_cs_agent':
        if (!$uid || !$is_owner) { echo json_encode(['status'=>'error','msg'=>'无权限']); exit; }
        $target_id = (int)($_POST['target_id'] ?? 0);
        $conn->query("UPDATE users SET is_cs=0 WHERE id=$target_id AND role NOT IN ('owner')");
        echo json_encode(['status' => $conn->affected_rows > 0 ? 'ok' : 'error']);
        break;

    case 'list_cs_agents':
        if (!$uid || !$is_owner) { echo json_encode(['status'=>'error']); exit; }
        $r = $conn->query("SELECT id, username, mid, role FROM users WHERE is_cs=1 ORDER BY id ASC");
        $list = [];
        while ($row = $r->fetch_assoc()) $list[] = $row;
        echo json_encode(['status'=>'ok','agents'=>$list]);
        break;

    default:
        echo json_encode(['status'=>'error','msg'=>'unknown action']);
}
