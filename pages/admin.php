<?php
/**
 * admin.php — 综合管理后台主页
 *
 * 功能：帖子管理（置顶/删除/恢复）、用户管理（封号/解封/改权限）、私信查看、经验调整等多标签页管理面板
 * 读写表：posts、users、messages、categories
 * 权限：admin / owner（reviewer 仅可访问私信标签页）
 */
session_start();
$skip_loader = true;

$my_role = $_SESSION['role'] ?? '';
$is_admin_or_owner = in_array($my_role, ['admin', 'owner']);
$is_owner = $my_role === 'owner';
$can_msg  = in_array($my_role, ['owner', 'reviewer']);

if (!$is_admin_or_owner && !$can_msg) {
    header("Content-Type: text/html; charset=utf-8");
    die("<h3>🚫 权限不足</h3> <a href='../index.php'>返回主页</a>");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/exp_helper.php';

$conn->set_charset('utf8mb4');
ensure_user_columns($conn);

$my_id   = intval($_SESSION['user_id']);
$my_name = htmlspecialchars($_SESSION['username'] ?? '管理员');
$now     = date('Y-m-d H:i:s');

$tab = $_GET['tab'] ?? ($is_admin_or_owner ? 'posts' : 'messages');
if (!$is_admin_or_owner && $tab !== 'messages') { header("Location: admin.php?tab=messages"); exit; }

// ── 帖子操作 ──
if ($is_admin_or_owner && $tab === 'posts' && isset($_GET['action'], $_GET['id'])) {
    $pid = intval($_GET['id']); $act = $_GET['action'];
    if ($act === 'approve') {
        $conn->query("UPDATE posts SET status='已发布', approved_by=$my_id, approved_at='$now' WHERE id=$pid");
        $pr = $conn->query("SELECT user_id FROM posts WHERE id=$pid");
        if ($pr && $row = $pr->fetch_assoc()) {
            $aid = intval($row['user_id']);
            if ($aid !== $my_id)
                $conn->query("INSERT INTO notifications (user_id,from_user_id,type,post_id,created_at) VALUES ($aid,$my_id,'post_approved',$pid,'$now')");
        }
    } elseif ($act === 'delete') {
        $conn->query("DELETE FROM posts WHERE id=$pid");
    }
    header("Location: admin.php?tab=posts&sub=" . urlencode($_GET['sub'] ?? 'all')); exit;
}

// ── 分区操作 ──
$conn->query("CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL, description VARCHAR(200) DEFAULT '', color VARCHAR(7) DEFAULT '#3fb950', icon VARCHAR(10) DEFAULT '#', cover_image VARCHAR(255) DEFAULT '', sort_order INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$col2 = $conn->query("SHOW COLUMNS FROM posts LIKE 'category_id'");
if ($col2 && $col2->num_rows === 0) $conn->query("ALTER TABLE posts ADD COLUMN category_id INT NULL DEFAULT NULL");
$col3 = $conn->query("SHOW COLUMNS FROM categories LIKE 'cover_image'");
if ($col3 && $col3->num_rows === 0) $conn->query("ALTER TABLE categories ADD COLUMN cover_image VARCHAR(255) DEFAULT '' AFTER icon");
$upload_dir = __DIR__ . '/../uploads/categories/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

function save_cover($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif']) || $file['size'] > 5*1024*1024) return null;
    $dir = __DIR__ . '/../uploads/categories/';
    $fname = 'cat_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    return move_uploaded_file($file['tmp_name'], $dir . $fname) ? 'uploads/categories/'.$fname : null;
}

$cat_msg = $_GET['cat_msg'] ?? '';
if ($is_admin_or_owner && $tab === 'categories') {
    if (isset($_GET['delete_cat'])) {
        $did = intval($_GET['delete_cat']);
        $row = $conn->query("SELECT cover_image FROM categories WHERE id=$did")->fetch_assoc();
        if (!empty($row['cover_image'])) { $p = __DIR__.'/../'.$row['cover_image']; if(file_exists($p)) unlink($p); }
        $conn->query("UPDATE posts SET category_id=NULL WHERE category_id=$did");
        $conn->query("DELETE FROM categories WHERE id=$did");
        header("Location: admin.php?tab=categories&cat_msg=delete_ok"); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cname  = $conn->real_escape_string(trim($_POST['name'] ?? ''));
        $cdesc  = $conn->real_escape_string(trim($_POST['description'] ?? ''));
        $ccolor = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#3fb950';
        $cicon  = $conn->real_escape_string(mb_substr(trim($_POST['icon'] ?? '#'), 0, 4));
        $csort  = intval($_POST['sort_order'] ?? 0);
        $edit_id = intval($_POST['edit_id'] ?? 0);
        if ($cname) {
            if ($edit_id) {
                $cover_sql = '';
                if (!empty($_FILES['cover_image']['name'])) {
                    $nc = save_cover($_FILES['cover_image']);
                    if ($nc) {
                        $old = $conn->query("SELECT cover_image FROM categories WHERE id=$edit_id")->fetch_assoc();
                        if (!empty($old['cover_image'])) { $op=__DIR__.'/../'.$old['cover_image']; if(file_exists($op)) unlink($op); }
                        $cover_sql = ", cover_image='".$conn->real_escape_string($nc)."'";
                    }
                }
                $conn->query("UPDATE categories SET name='$cname',description='$cdesc',color='$ccolor',icon='$cicon',sort_order=$csort$cover_sql WHERE id=$edit_id");
                header("Location: admin.php?tab=categories&cat_msg=edit_ok"); exit;
            } else {
                $cover = save_cover($_FILES['cover_image'] ?? []);
                if (!$cover) { $cat_form_err = '图片上传失败'; }
                else {
                    $sc = $conn->real_escape_string($cover);
                    $conn->query("INSERT INTO categories (name,description,color,icon,sort_order,cover_image) VALUES ('$cname','$cdesc','$ccolor','$cicon',$csort,'$sc')");
                    header("Location: admin.php?tab=categories&cat_msg=create_ok"); exit;
                }
            }
        }
    }
}

// ── 评论删除 ──
if ($is_admin_or_owner && $tab === 'comments' && isset($_GET['delete_comment'])) {
    $cid = intval($_GET['delete_comment']);
    $conn->query("DELETE FROM comments WHERE id=$cid OR parent_id=$cid");
    $back_mid = urlencode($_GET['mid'] ?? '');
    header("Location: admin.php?tab=comments&mid=$back_mid&cmt_msg=deleted"); exit;
}

// ── 加载帖子审核数据 ──
if ($tab === 'posts') {
    $r = $conn->query("SHOW COLUMNS FROM posts LIKE 'approved_by'");
    if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE posts ADD COLUMN approved_by INT DEFAULT NULL");
    $r = $conn->query("SHOW COLUMNS FROM posts LIKE 'approved_at'");
    if ($r && $r->num_rows === 0) $conn->query("ALTER TABLE posts ADD COLUMN approved_at DATETIME DEFAULT NULL");
    $total   = (int)$conn->query("SELECT COUNT(*) c FROM posts")->fetch_assoc()['c'];
    $pending = (int)$conn->query("SELECT COUNT(*) c FROM posts WHERE status='待审核'")->fetch_assoc()['c'];
    $pub     = (int)$conn->query("SELECT COUNT(*) c FROM posts WHERE status='已发布'")->fetch_assoc()['c'];
    $draft   = (int)$conn->query("SELECT COUNT(*) c FROM posts WHERE status='草稿'")->fetch_assoc()['c'];
    $sub = $_GET['sub'] ?? 'all';
    if ($sub === 'pending')       $where = "WHERE p.status='待审核'";
    elseif ($sub === 'published') $where = "WHERE p.status='已发布'";
    elseif ($sub === 'draft')     $where = "WHERE p.status='草稿'";
    else                          $where = '';
    $result = $conn->query("SELECT p.id,p.title,p.content,p.status,p.created_at,p.approved_at,u.username AS author_name,u.userid AS author_uid,a.username AS approver_name FROM posts p LEFT JOIN users u ON p.user_id=u.id LEFT JOIN users a ON p.approved_by=a.id $where ORDER BY FIELD(p.status,'待审核','草稿','已发布'),p.id DESC");
    $posts = [];
    if ($result) while ($r = $result->fetch_assoc()) $posts[] = $r;
}

// ── 加载举报数据 ──
if ($tab === 'reports') {
    $rfilter = $_GET['rf'] ?? 'pending'; $rtype = $_GET['rt'] ?? 'all';
    $rw = []; if ($rfilter!=='all') $rw[]="r.status='$rfilter'"; if($rtype!=='all') $rw[]="r.type='$rtype'";
    $rw_sql = $rw ? 'WHERE '.implode(' AND ',$rw) : '';
    $rres = $conn->query("SELECT r.*,u.username AS reporter_name,h.username AS handler_name FROM reports r LEFT JOIN users u ON u.id=r.reporter_id LEFT JOIN users h ON h.id=r.handler_id $rw_sql ORDER BY r.id DESC LIMIT 200");
    $reports = []; if ($rres) while ($row=$rres->fetch_assoc()) $reports[]=$row;
    $rcnt = [];
    foreach (['pending','handled','dismissed'] as $s) { $c=$conn->query("SELECT COUNT(*) c FROM reports WHERE status='$s'"); $rcnt[$s]=$c?(int)$c->fetch_assoc()['c']:0; }
    $rcnt['all'] = array_sum($rcnt);
}

// ── 加载封禁数据 ──
if ($tab === 'bans') {
    $conn->query("UPDATE users SET is_banned=0,ban_reason=NULL,ban_until=NULL,banned_by=NULL WHERE is_banned=1 AND ban_until IS NOT NULL AND ban_until<='$now'");
    $bfilter = $_GET['bf'] ?? 'all';
    $bwhere = "WHERE u.is_banned=1";
    if ($bfilter==='timed') $bwhere.=" AND u.ban_until IS NOT NULL";
    if ($bfilter==='perm')  $bwhere.=" AND u.ban_until IS NULL";
    $bres = $conn->query("SELECT u.id,u.username,u.mid,u.avatar,u.role,u.ban_reason,u.ban_until,b.username AS banned_by_name FROM users u LEFT JOIN users b ON b.id=u.banned_by $bwhere ORDER BY u.id DESC");
    $banned = []; if ($bres) while ($r=$bres->fetch_assoc()) $banned[]=$r;
}

// ── 加载分区数据 ──
if ($tab === 'categories') {
    $edit_cat = null;
    if (isset($_GET['edit_cat']) && is_numeric($_GET['edit_cat'])) {
        $r = $conn->query("SELECT * FROM categories WHERE id=".(int)$_GET['edit_cat']);
        $edit_cat = ($r && $r->num_rows>0) ? $r->fetch_assoc() : null;
    }
    $cats = [];
    $cr = $conn->query("SELECT c.*,COUNT(p.id) as post_count FROM categories c LEFT JOIN posts p ON p.category_id=c.id AND p.status='已发布' GROUP BY c.id ORDER BY c.sort_order ASC,c.id ASC");
    if ($cr) while ($c=$cr->fetch_assoc()) $cats[]=$c;
}

// ── 加载评论查询数据 ──
$cmt_user = null; $cmt_list = []; $cmt_mid = ''; $cmt_msg = $_GET['cmt_msg'] ?? '';
if ($tab === 'comments') {
    $cmt_mid = strtoupper(trim($_GET['mid'] ?? ''));
    if ($cmt_mid !== '') {
        $sm = $conn->real_escape_string($cmt_mid);
        $ur = $conn->query("SELECT id,username,avatar,role FROM users WHERE mid='$sm'");
        $cmt_user = $ur ? $ur->fetch_assoc() : null;
        if ($cmt_user) {
            $uid = intval($cmt_user['id']);
            $cr = $conn->query("SELECT c.id,c.content,c.created_at,c.likes,c.parent_id,p.id AS post_id,COALESCE(p.title,'[动态]') AS post_title FROM comments c LEFT JOIN posts p ON c.post_id=p.id WHERE c.user_id=$uid ORDER BY c.created_at DESC LIMIT 200");
            if ($cr) while ($r=$cr->fetch_assoc()) $cmt_list[]=$r;
        }
    }
}

// ── 加载私信查询数据 ──
$msg_a = $msg_b = null; $msg_list = []; $msg_errors = []; $msg_total = 0; $msg_pages = 1;
if ($tab === 'messages') {
    $mid_a = strtoupper(trim($_GET['mid_a'] ?? ''));
    $mid_b = strtoupper(trim($_GET['mid_b'] ?? ''));
    $mpage = max(1, intval($_GET['mpage'] ?? 1)); $per = 50;
    if ($mid_a !== '' || $mid_b !== '') {
        if ($mid_a==='') $msg_errors[]='请填写用户 A 的 MID';
        if ($mid_b==='') $msg_errors[]='请填写用户 B 的 MID';
        if (empty($msg_errors)) {
            $sa=$conn->real_escape_string($mid_a); $sb=$conn->real_escape_string($mid_b);
            $ra=$conn->query("SELECT id,username,avatar,role FROM users WHERE mid='$sa'");
            $rb=$conn->query("SELECT id,username,avatar,role FROM users WHERE mid='$sb'");
            $msg_a=$ra?$ra->fetch_assoc():null; $msg_b=$rb?$rb->fetch_assoc():null;
            if(!$msg_a) $msg_errors[]="MID「{$mid_a}」不存在";
            if(!$msg_b) $msg_errors[]="MID「{$mid_b}」不存在";
            if (empty($msg_errors)) {
                if ($msg_a['id']===$msg_b['id']) { $msg_errors[]='请输入两个不同用户的 MID'; }
                else {
                    $ua=(int)$msg_a['id']; $ub=(int)$msg_b['id'];
                    $tc=$conn->query("SELECT COUNT(*) c FROM messages WHERE (from_user_id=$ua AND to_user_id=$ub) OR (from_user_id=$ub AND to_user_id=$ua)");
                    $msg_total=$tc?(int)$tc->fetch_assoc()['c']:0;
                    $msg_pages=max(1,(int)ceil($msg_total/$per)); $mpage=min($mpage,$msg_pages);
                    $offset=($mpage-1)*$per;
                    $mr=$conn->query("SELECT m.id,m.from_user_id,m.content,m.created_at,u.username AS sender_name,u.avatar AS sender_avatar FROM messages m JOIN users u ON u.id=m.from_user_id WHERE (m.from_user_id=$ua AND m.to_user_id=$ub) OR (m.from_user_id=$ub AND m.to_user_id=$ua) ORDER BY m.id ASC LIMIT $per OFFSET $offset");
                    if ($mr) while ($r=$mr->fetch_assoc()) $msg_list[]=$r;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>后台管理中心 · 缪斯 MUSE</title>
<link rel="icon" type="image/svg+xml" href="../assets/logo.svg">
<link rel="shortcut icon" href="../assets/logo.svg">
<style>
*,*::before,*::after{box-sizing:border-box;}
.ap-shell{max-width:1120px;margin:24px auto;padding:0 16px;}

/* ── 顶部导航栏 ── */
.ap-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:0;padding:0 0 0 2px;}
.ap-title{font-size:13px;font-weight:700;color:#3fb950;letter-spacing:1.5px;text-transform:uppercase;font-family:"Courier New",monospace;}
.ap-title::before{content:'// ';}
.ap-meta{font-size:12px;color:#6e7681;font-family:"Courier New",monospace;}
.ap-meta a{color:#3fb950;text-decoration:none;}

/* ── Tab 导航 ── */
.ap-tabs{display:flex;gap:2px;border-bottom:1px solid #21262d;margin:16px 0 20px;flex-wrap:wrap;}
.ap-tab{padding:8px 18px;font-size:12px;font-weight:600;color:#6e7681;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-1px;transition:.15s;white-space:nowrap;font-family:"Courier New",monospace;}
.ap-tab:hover{color:#c9d1d9;}
.ap-tab.active{color:#3fb950;border-bottom-color:#3fb950;}
.ap-tab .badge{display:inline-block;background:#21262d;border-radius:9px;padding:0 6px;font-size:10px;margin-left:5px;color:#6e7681;}
.ap-tab.active .badge{background:rgba(63,185,80,.15);color:#3fb950;}

/* ── 通用卡片 ── */
.ap-card{background:#161b22;border:1px solid #30363d;border-radius:6px;margin-bottom:10px;transition:border-color .15s;}
.ap-card:hover{border-color:#3fb950;}
.ap-card-body{padding:16px 18px;display:flex;gap:16px;align-items:flex-start;}
.ap-card-info{flex:1;min-width:0;}
.ap-card-title{font-size:14px;font-weight:600;color:#c9d1d9;margin:0 0 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ap-card-title a{color:inherit;text-decoration:none;} .ap-card-title a:hover{color:#3fb950;}
.ap-card-preview{font-size:12px;color:#8b949e;line-height:1.55;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:10px;}
.ap-card-meta{display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-size:11px;color:#6e7681;font-family:"Courier New",monospace;}
.ap-card-actions{display:flex;flex-direction:column;gap:6px;flex-shrink:0;}

/* ── 状态 Tag ── */
.tag{padding:2px 8px;border-radius:3px;font-size:11px;font-weight:700;white-space:nowrap;font-family:"Courier New",monospace;}
.tag-pending{background:rgba(240,136,62,.15);color:#f0883e;border:1px solid rgba(240,136,62,.3);}
.tag-ok{background:rgba(63,185,80,.15);color:#3fb950;border:1px solid rgba(63,185,80,.3);}
.tag-draft{background:rgba(88,166,255,.15);color:#58a6ff;border:1px solid rgba(88,166,255,.3);}
.tag-report-pending{background:rgba(240,136,62,.12);color:#f0883e;border:1px solid rgba(240,136,62,.3);}
.tag-report-handled{background:rgba(63,185,80,.1);color:#3fb950;border:1px solid rgba(63,185,80,.3);}
.tag-report-dismissed{background:rgba(110,118,129,.1);color:#6e7681;border:1px solid rgba(110,118,129,.3);}
.tag-post{background:rgba(88,166,255,.12);color:#58a6ff;border:1px solid rgba(88,166,255,.3);}
.tag-user{background:rgba(167,139,250,.12);color:#a78bfa;border:1px solid rgba(167,139,250,.3);}
.tag-ban-perm{background:rgba(248,81,73,.1);color:#f85149;border:1px solid rgba(248,81,73,.3);}
.tag-ban-timed{background:rgba(240,136,62,.1);color:#f0883e;border:1px solid rgba(240,136,62,.3);}

/* ── 按钮 ── */
.btn{padding:5px 12px;border:none;border-radius:4px;cursor:pointer;text-decoration:none;font-size:12px;display:inline-block;font-weight:600;transition:opacity .15s;white-space:nowrap;text-align:center;font-family:inherit;}
.btn:hover{opacity:.85;}
.btn-approve{background:#238636;color:#fff;border:1px solid rgba(63,185,80,.4);}
.btn-view{background:#21262d;color:#8b949e;border:1px solid #30363d;}
.btn-delete{background:rgba(248,81,73,.15);color:#f85149;border:1px solid rgba(248,81,73,.3);}
.btn-unban{background:rgba(63,185,80,.08);color:#3fb950;border:1px solid rgba(63,185,80,.4);}
.btn-handled{background:rgba(63,185,80,.08);color:#3fb950;border:1px solid rgba(63,185,80,.3);}
.btn-dismiss{background:transparent;color:#6e7681;border:1px solid #30363d;}
.btn-del-post{background:rgba(248,81,73,.1);color:#f85149;border:1px solid rgba(248,81,73,.3);}
.btn-ban{background:rgba(248,81,73,.08);color:#f0883e;border:1px solid rgba(248,81,73,.25);}
.btn-green{background:#3fb950;color:#fff;} .btn-green:hover{background:#2ea043;}
.btn-outline{background:transparent;border:1px solid #30363d;color:#8b949e;text-decoration:none;display:inline-block;}
.btn-outline:hover{border-color:#8b949e;color:#e6edf3;}
.btn-danger{background:rgba(248,81,73,.12);color:#f85149;border:1px solid rgba(248,81,73,.3);text-decoration:none;display:inline-block;}
.btn-danger:hover{background:rgba(248,81,73,.22);}
.btn-sm{padding:4px 12px;font-size:12px;}
.btn-query{padding:9px 22px;background:#3fb950;color:#0d1117;border:none;border-radius:5px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;}
.btn-query:hover{opacity:.85;}

/* ── 统计卡片 ── */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;}
.stat-card{background:#161b22;border:1px solid #30363d;border-radius:6px;padding:14px 16px;}
.stat-card .num{font-size:26px;font-weight:700;line-height:1.1;font-family:"Courier New",monospace;}
.stat-card .label{font-size:11px;color:#6e7681;margin-top:4px;letter-spacing:.5px;text-transform:uppercase;font-family:"Courier New",monospace;}
.sc-pending .num{color:#f0883e;} .sc-pub .num{color:#3fb950;} .sc-draft .num{color:#58a6ff;} .sc-total .num{color:#e6edf3;}

/* ── 子过滤栏 ── */
.sub-tabs{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;}
.stab{padding:5px 14px;border-radius:20px;font-size:12px;text-decoration:none;border:1px solid #30363d;background:#161b22;color:#8b949e;transition:.15s;font-weight:600;white-space:nowrap;}
.stab:hover{border-color:#3fb950;color:#3fb950;} .stab.active{background:rgba(63,185,80,.12);color:#3fb950;border-color:rgba(63,185,80,.4);}
.stab .n{display:inline-block;background:#21262d;border-radius:9px;padding:0 6px;font-size:10px;margin-left:4px;color:#6e7681;}
.stab.active .n{background:rgba(63,185,80,.2);color:#3fb950;}
.sep-line{width:1px;height:20px;background:#30363d;display:inline-block;margin:0 4px;vertical-align:middle;}

/* ── 空状态 ── */
.empty-state{text-align:center;padding:50px 20px;background:#161b22;border:1px solid #30363d;border-radius:6px;color:#6e7681;font-size:13px;}

/* ── 搜索框 ── */
.search-card{background:#161b22;border:1px solid #30363d;border-radius:6px;padding:18px 20px;margin-bottom:18px;}
.search-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;}
.search-field{flex:1;min-width:160px;}
.search-field label{display:block;font-size:11px;color:#6e7681;margin-bottom:5px;font-family:"Courier New",monospace;}
.mid-input{width:100%;padding:9px 12px;background:#0d1117;border:1px solid #30363d;border-radius:5px;color:#3fb950;font-size:14px;font-family:"Courier New",monospace;outline:none;letter-spacing:1px;text-transform:uppercase;}
.mid-input:focus{border-color:#3fb950;} .mid-input::placeholder{color:#484f58;text-transform:none;letter-spacing:0;}

/* ── 私信样式 ── */
.user-pair{display:flex;align-items:center;gap:14px;margin-bottom:16px;flex-wrap:wrap;}
.user-card-sm{display:flex;align-items:center;gap:10px;flex:1;min-width:180px;background:#161b22;border:1px solid #30363d;border-radius:7px;padding:10px 14px;}
.user-card-sm img{width:36px;height:36px;border-radius:50%;object-fit:cover;border:1px solid #30363d;}
.user-card-sm-name{font-size:13px;font-weight:700;color:#e6edf3;}
.user-card-sm-mid{font-size:11px;color:#6e7681;font-family:"Courier New",monospace;}
.pair-sep{font-size:18px;color:#30363d;flex-shrink:0;}
.chat-log{background:#161b22;border:1px solid #30363d;border-radius:6px;padding:16px;display:flex;flex-direction:column;gap:10px;}
.date-sep{display:flex;align-items:center;gap:10px;font-size:11px;color:#484f58;margin:2px 0;}
.date-sep::before,.date-sep::after{content:'';flex:1;height:1px;background:#21262d;}
.msg-row{display:flex;gap:10px;max-width:85%;} .msg-row.right{flex-direction:row-reverse;align-self:flex-end;}
.msg-avatar{width:30px;height:30px;border-radius:50%;object-fit:cover;border:1px solid #30363d;flex-shrink:0;}
.msg-body{display:flex;flex-direction:column;gap:3px;} .msg-row.right .msg-body{align-items:flex-end;}
.msg-meta{font-size:10px;color:#6e7681;} .msg-sender{color:#8b949e;font-weight:600;margin-right:5px;}
.msg-bubble{padding:7px 12px;border-radius:6px;font-size:13px;line-height:1.55;word-break:break-word;font-family:"Microsoft YaHei",sans-serif;}
.msg-row.left .msg-bubble{background:#21262d;border:1px solid #30363d;border-top-left-radius:2px;}
.msg-row.right .msg-bubble{background:rgba(63,185,80,.08);border:1px solid rgba(63,185,80,.2);border-top-right-radius:2px;}

/* ── 分页 ── */
.pag{display:flex;gap:6px;justify-content:center;margin-top:14px;flex-wrap:wrap;}
.pag a{padding:5px 12px;border-radius:4px;font-size:12px;text-decoration:none;border:1px solid #30363d;background:#161b22;color:#8b949e;transition:.15s;}
.pag a:hover{border-color:#3fb950;color:#3fb950;} .pag a.cur{background:#3fb950;color:#0d1117;border-color:#3fb950;font-weight:700;}

/* ── 分区表单 ── */
.form-group{display:flex;flex-direction:column;gap:5px;}
.form-group label{font-size:12px;color:#6e7681;font-family:"Courier New",monospace;}
.form-group input[type=text],.form-group input[type=number],.form-group textarea{background:#0d1117;border:1px solid #30363d;border-radius:4px;color:#e6edf3;padding:7px 10px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;}
.form-group input:focus,.form-group textarea:focus{border-color:#3fb950;}
.form-group input[type=color]{width:46px;height:34px;padding:2px 4px;background:#0d1117;border:1px solid #30363d;border-radius:4px;cursor:pointer;}
.cat-row{display:flex;align-items:center;gap:14px;padding:12px 16px;border-bottom:1px solid #21262d;transition:background .15s;}
.cat-row:last-child{border-bottom:none;} .cat-row:hover{background:#1c2128;}
.cat-thumb{width:72px;height:46px;border-radius:4px;object-fit:cover;flex-shrink:0;border:1px solid #30363d;background:#0d1117;}
.cat-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;}
.cat-info{flex:1;min-width:0;}
.cat-name{font-size:14px;font-weight:700;color:#e6edf3;margin-bottom:2px;}
.cat-meta{font-size:12px;color:#6e7681;font-family:"Courier New",monospace;}
.cover-preview-box{width:220px;height:130px;border-radius:5px;background:#0d1117;border:1px solid #30363d;overflow:hidden;display:flex;align-items:center;justify-content:center;}
.cover-preview-box img{width:100%;height:100%;object-fit:cover;}
.cover-btn{display:flex;align-items:center;justify-content:center;gap:5px;background:#1c2128;border:1px solid #30363d;border-radius:4px;color:#8b949e;font-size:12px;padding:6px 12px;cursor:pointer;transition:.15s;font-family:inherit;width:220px;}
.cover-btn:hover{border-color:#3fb950;color:#3fb950;} .cover-btn input[type=file]{display:none;}

/* ── 评论卡片 ── */
.cmt-card{background:#161b22;border:1px solid #30363d;border-radius:6px;padding:12px 16px;margin-bottom:8px;display:flex;gap:12px;align-items:flex-start;transition:border-color .15s;}
.cmt-card:hover{border-color:#f85149;}
.cmt-body{flex:1;min-width:0;}
.cmt-content{font-size:13px;color:#c9d1d9;line-height:1.55;margin-bottom:8px;word-break:break-word;}
.cmt-meta{font-size:11px;color:#6e7681;font-family:"Courier New",monospace;display:flex;flex-wrap:wrap;gap:8px;}
.cmt-post-link{color:#58a6ff;text-decoration:none;} .cmt-post-link:hover{text-decoration:underline;}

.msg-bar{padding:9px 14px;border-radius:4px;font-size:12px;margin-bottom:14px;font-family:"Courier New",monospace;}
.msg-ok{background:rgba(63,185,80,.1);border:1px solid rgba(63,185,80,.3);color:#3fb950;}
.msg-err{background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);color:#f85149;}
.alert-error{background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);color:#f85149;border-radius:5px;padding:10px 14px;font-size:13px;margin-bottom:12px;}

@media(max-width:640px){
    .stats-row{grid-template-columns:repeat(2,1fr);}
    .ap-card-body{flex-direction:column;} .ap-card-actions{flex-direction:row;}
}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="ap-shell">

    <div class="ap-header">
        <div class="ap-title">后台管理中心</div>
        <div class="ap-meta"><?= $my_name ?> &nbsp;·&nbsp; <a href="../index.php">← 返回主页</a></div>
    </div>

    <!-- Tab 导航 -->
    <nav class="ap-tabs">
        <?php if ($is_admin_or_owner): ?>
        <a href="admin.php?tab=posts"      class="ap-tab <?= $tab==='posts'      ? 'active':'' ?>">帖子管理
            <?php if($tab==='posts') echo '<span class="badge">'.$total.'</span>'; ?>
        </a>
        <a href="admin.php?tab=reports"    class="ap-tab <?= $tab==='reports'    ? 'active':'' ?>">举报管理
            <?php if($tab==='reports') echo '<span class="badge">'.$rcnt['pending'].'</span>'; ?>
        </a>
        <a href="admin.php?tab=bans"       class="ap-tab <?= $tab==='bans'       ? 'active':'' ?>">封禁管理</a>
        <a href="admin.php?tab=categories" class="ap-tab <?= $tab==='categories' ? 'active':'' ?>">分区管理</a>
        <a href="admin.php?tab=comments"   class="ap-tab <?= $tab==='comments'   ? 'active':'' ?>">评论查询</a>
        <?php endif; ?>
        <?php if ($can_msg): ?>
        <a href="admin.php?tab=messages"   class="ap-tab <?= $tab==='messages'   ? 'active':'' ?>">私信查询</a>
        <?php endif; ?>
    </nav>

    <!-- ══════════════ TAB: 帖子管理 ══════════════ -->
    <?php if ($tab === 'posts'): ?>

    <div class="stats-row">
        <div class="stat-card sc-pending"><div class="num"><?= $pending ?></div><div class="label">⏳ 待审核</div></div>
        <div class="stat-card sc-pub"><div class="num"><?= $pub ?></div><div class="label">✅ 已发布</div></div>
        <div class="stat-card sc-draft"><div class="num"><?= $draft ?></div><div class="label">📝 草稿</div></div>
        <div class="stat-card sc-total"><div class="num"><?= $total ?></div><div class="label">📊 总计</div></div>
    </div>

    <div class="sub-tabs">
        <a href="admin.php?tab=posts&sub=all"       class="stab <?= ($sub??'all')==='all'       ? 'active':'' ?>">全部<span class="n"><?= $total ?></span></a>
        <a href="admin.php?tab=posts&sub=pending"   class="stab <?= ($sub??'')==='pending'      ? 'active':'' ?>">待审核<span class="n"><?= $pending ?></span></a>
        <a href="admin.php?tab=posts&sub=published" class="stab <?= ($sub??'')==='published'    ? 'active':'' ?>">已发布<span class="n"><?= $pub ?></span></a>
        <a href="admin.php?tab=posts&sub=draft"     class="stab <?= ($sub??'')==='draft'        ? 'active':'' ?>">草稿<span class="n"><?= $draft ?></span></a>
    </div>

    <?php if (empty($posts)): ?>
        <div class="empty-state">暂无帖子</div>
    <?php else: foreach ($posts as $p):
        $scls = $p['status']==='待审核' ? 'tag-pending' : ($p['status']==='已发布' ? 'tag-ok' : 'tag-draft');
        $prev = mb_substr(strip_tags($p['content']), 0, 120);
        $ts   = strtotime($p['created_at']); $diff = time()-$ts;
        $tstr = $diff<60?'刚刚':($diff<3600?floor($diff/60).'分钟前':($diff<86400?floor($diff/3600).'小时前':date('m-d H:i',$ts)));
    ?>
    <div class="ap-card">
        <div class="ap-card-body">
            <div class="ap-card-info">
                <div class="ap-card-title"><a href="post.php?id=<?= $p['id'] ?>" target="_blank"><?= htmlspecialchars($p['title']) ?></a></div>
                <div class="ap-card-preview"><?= htmlspecialchars($prev) ?><?= mb_strlen(strip_tags($p['content']))>120?'…':'' ?></div>
                <div class="ap-card-meta">
                    <span class="tag <?= $scls ?>"><?= $p['status'] ?></span>
                    <span>·</span><span style="color:#3fb950;font-weight:600;"><?= htmlspecialchars($p['author_name']??'未知') ?></span>
                    <?php if(!empty($p['author_uid'])): ?><span>@<?= htmlspecialchars($p['author_uid']) ?></span><?php endif; ?>
                    <span>·</span><span><?= $tstr ?></span><span>· ID:<?= $p['id'] ?></span>
                    <?php if($p['status']==='已发布'&&!empty($p['approver_name'])): ?>
                    <span>· ✅ <?= htmlspecialchars($p['approver_name']) ?> 审核<?= $p['approved_at']?' · '.date('m-d H:i',strtotime($p['approved_at'])):'' ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ap-card-actions">
                <?php if($p['status']==='待审核'): ?>
                <a href="admin.php?tab=posts&action=approve&id=<?= $p['id'] ?>&sub=<?= urlencode($sub??'all') ?>" class="btn btn-approve" onclick="return confirm('确认发布？')">✅ 通过</a>
                <?php endif; ?>
                <a href="post.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-view">👁 查看</a>
                <a href="admin.php?tab=posts&action=delete&id=<?= $p['id'] ?>&sub=<?= urlencode($sub??'all') ?>" class="btn btn-delete" onclick="return confirm('确认删除？')">🗑 删除</a>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>

    <!-- ══════════════ TAB: 举报管理 ══════════════ -->
    <?php elseif ($tab === 'reports'): ?>

    <div class="sub-tabs">
        <?php foreach(['pending'=>'待处理','handled'=>'已处理','dismissed'=>'已驳回','all'=>'全部'] as $k=>$label): ?>
        <a href="admin.php?tab=reports&rf=<?= $k ?>&rt=<?= $rtype ?>" class="stab <?= $rfilter===$k?'active':'' ?>"><?= $label ?><span class="n"><?= $rcnt[$k] ?></span></a>
        <?php endforeach; ?>
        <span class="sep-line"></span>
        <a href="admin.php?tab=reports&rf=<?= $rfilter ?>&rt=all"  class="stab <?= $rtype==='all'?'active':'' ?>">全部类型</a>
        <a href="admin.php?tab=reports&rf=<?= $rfilter ?>&rt=post" class="stab <?= $rtype==='post'?'active':'' ?>">帖子</a>
        <a href="admin.php?tab=reports&rf=<?= $rfilter ?>&rt=user" class="stab <?= $rtype==='user'?'active':'' ?>">用户</a>
    </div>

    <?php if(empty($reports)): ?><div class="empty-state">暂无举报记录</div>
    <?php else: foreach($reports as $r):
        $stcls=['pending'=>'tag-report-pending','handled'=>'tag-report-handled','dismissed'=>'tag-report-dismissed'][$r['status']]??'';
        $is_post=$r['type']==='post';
    ?>
    <div class="ap-card <?= in_array($r['status'],['handled','dismissed'])?'':'' ?>" id="rcard-<?= $r['id'] ?>" style="<?= $r['status']!=='pending'?'opacity:.65':'' ?>">
        <div class="ap-card-body">
            <div style="flex-shrink:0;margin-top:2px;">
                <span class="tag <?= $is_post?'tag-post':'tag-user' ?>"><?= $is_post?'帖子':'用户' ?></span>
            </div>
            <div class="ap-card-info">
                <div class="ap-card-title" style="white-space:normal;">
                    <?= htmlspecialchars($r['reason']) ?>
                    <span class="tag <?= $stcls ?>" style="margin-left:8px;"><?= ['pending'=>'待处理','handled'=>'已处理','dismissed'=>'已驳回'][$r['status']]??$r['status'] ?></span>
                </div>
                <?php if($r['detail']): ?><div class="ap-card-preview" style="-webkit-line-clamp:1;"><?= htmlspecialchars($r['detail']) ?></div><?php endif; ?>
                <div class="ap-card-meta">
                    <span>举报人：<strong style="color:#c9d1d9;"><?= htmlspecialchars($r['reporter_name']??'—') ?></strong></span>
                    <span>目标：<?php if($is_post): ?><a href="post.php?id=<?= $r['target_id'] ?>" target="_blank" style="color:#58a6ff;">#<?= $r['target_id'] ?></a><?php else: ?><a href="profile.php?id=<?= $r['target_id'] ?>" target="_blank" style="color:#58a6ff;">UID <?= $r['target_id'] ?></a><?php endif; ?></span>
                    <span><?= date('m-d H:i',strtotime($r['created_at'])) ?></span>
                    <?php if($r['handler_name']): ?><span>处理：<?= htmlspecialchars($r['handler_name']) ?></span><?php endif; ?>
                </div>
                <?php if($r['status']==='pending'): ?>
                <div style="display:flex;gap:7px;flex-wrap:wrap;margin-top:10px;">
                    <?php if($is_post): ?>
                    <a href="post.php?id=<?= $r['target_id'] ?>" target="_blank" class="btn btn-view btn-sm">查看帖子</a>
                    <button class="btn btn-del-post btn-sm" onclick="rDelPost(<?= $r['id'] ?>,<?= $r['target_id'] ?>)">删除帖子</button>
                    <?php else: ?>
                    <a href="profile.php?id=<?= $r['target_id'] ?>" target="_blank" class="btn btn-view btn-sm">查看主页</a>
                    <button class="btn btn-ban btn-sm" onclick="rBanUser(<?= $r['id'] ?>,<?= $r['target_id'] ?>)">封禁用户</button>
                    <?php endif; ?>
                    <button class="btn btn-handled btn-sm" onclick="rHandle(<?= $r['id'] ?>,'handle')">✓ 已处理</button>
                    <button class="btn btn-dismiss btn-sm" onclick="rHandle(<?= $r['id'] ?>,'dismiss')">驳回</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>

    <!-- ══════════════ TAB: 封禁管理 ══════════════ -->
    <?php elseif ($tab === 'bans'): ?>

    <div class="sub-tabs">
        <a href="admin.php?tab=bans&bf=all"   class="stab <?= ($bfilter??'all')==='all'?'active':'' ?>">全部 <span class="n"><?= count($banned) ?></span></a>
        <a href="admin.php?tab=bans&bf=timed" class="stab <?= ($bfilter??'')==='timed'?'active':'' ?>">限时</a>
        <a href="admin.php?tab=bans&bf=perm"  class="stab <?= ($bfilter??'')==='perm'?'active':'' ?>">永久</a>
    </div>

    <?php if(empty($banned)): ?><div class="empty-state">当前没有被封禁的用户 ✓</div>
    <?php else: foreach($banned as $u):
        $isPerm=$u['ban_until']===null;
        $utag=$isPerm?'<span class="tag tag-ban-perm">永久封禁</span>':'<span class="tag tag-ban-timed">⏱ 至 '.date('Y-m-d',strtotime($u['ban_until'])).'</span>';
        $can_unban=$is_owner||in_array($u['role'],['user','sponsor']);
    ?>
    <div class="ap-card" id="bcard-<?= $u['id'] ?>">
        <div class="ap-card-body" style="align-items:center;">
            <img src="../uploads/<?= htmlspecialchars($u['avatar']?:'default.png') ?>" onerror="this.onerror=null;this.src='../uploads/default.png'" style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:1px solid #30363d;flex-shrink:0;">
            <div class="ap-card-info">
                <div class="ap-card-title" style="white-space:normal;">
                    <a href="profile.php?id=<?= $u['id'] ?>" target="_blank"><?= htmlspecialchars($u['username']) ?></a>
                    <?= $utag ?>
                    <?= get_role_badge($u['role']) ?>
                </div>
                <div class="ap-card-meta">
                    <span>MID: <?= htmlspecialchars($u['mid']??'—') ?></span>
                    <span>原因：<strong style="color:#c9d1d9;"><?= htmlspecialchars($u['ban_reason']?:'未填写') ?></strong></span>
                    <?php if($u['banned_by_name']): ?><span>操作人：<?= htmlspecialchars($u['banned_by_name']) ?></span><?php endif; ?>
                </div>
            </div>
            <?php if($can_unban): ?>
            <button class="btn btn-unban" onclick="doUnban(<?= $u['id'] ?>,'<?= htmlspecialchars(addslashes($u['username'])) ?>')">解除封禁</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; endif; ?>

    <!-- ══════════════ TAB: 分区管理 ══════════════ -->
    <?php elseif ($tab === 'categories'): ?>

    <?php if($cat_msg==='create_ok'): ?><div class="msg-bar msg-ok">✓ 分区创建成功</div>
    <?php elseif($cat_msg==='edit_ok'): ?><div class="msg-bar msg-ok">✓ 分区已更新</div>
    <?php elseif($cat_msg==='delete_ok'): ?><div class="msg-bar msg-ok">✓ 分区已删除</div><?php endif; ?>
    <?php if(!empty($cat_form_err)): ?><div class="msg-bar msg-err">✗ <?= htmlspecialchars($cat_form_err) ?></div><?php endif; ?>

    <div class="ap-card" style="margin-bottom:18px;">
        <div style="padding:12px 16px;border-bottom:1px solid #30363d;font-size:12px;font-weight:700;color:#6e7681;letter-spacing:1.2px;text-transform:uppercase;font-family:'Courier New',monospace;">
            <?= $edit_cat?'编辑分区':'新建分区' ?>
        </div>
        <div style="padding:18px;">
            <form method="POST" action="admin.php?tab=categories<?= $edit_cat?'&edit_cat='.$edit_cat['id']:'' ?>" enctype="multipart/form-data">
                <?php if($edit_cat): ?><input type="hidden" name="edit_id" value="<?= $edit_cat['id'] ?>"><?php endif; ?>
                <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap;">
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <div class="cover-preview-box">
                            <img id="cat-preview" <?= ($edit_cat&&!empty($edit_cat['cover_image']))?'src="../'.htmlspecialchars($edit_cat['cover_image']).'"':'style="display:none"' ?>>
                            <div id="cat-empty" style="color:#484f58;font-size:12px;font-family:'Courier New',monospace;text-align:center;<?= ($edit_cat&&!empty($edit_cat['cover_image']))?'display:none':'' ?>">🖼 暂无封面</div>
                        </div>
                        <label class="cover-btn">
                            <input type="file" name="cover_image" accept="image/*" onchange="prevCat(this)"> 📂 选择封面
                        </label>
                    </div>
                    <div style="flex:1;min-width:200px;display:flex;flex-direction:column;gap:12px;">
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <div class="form-group" style="flex:1;min-width:130px;">
                                <label>分区名称 *</label>
                                <input type="text" name="name" placeholder="如：游戏攻略" maxlength="50" required value="<?= htmlspecialchars($edit_cat['name']??'') ?>">
                            </div>
                            <div class="form-group" style="width:58px;">
                                <label>图标</label>
                                <input type="text" name="icon" placeholder="#" maxlength="4" value="<?= htmlspecialchars($edit_cat['icon']??'#') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>简介（可选）</label>
                            <input type="text" name="description" placeholder="一句话介绍" maxlength="200" value="<?= htmlspecialchars($edit_cat['description']??'') ?>">
                        </div>
                        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                            <div class="form-group"><label>主题色</label><input type="color" name="color" value="<?= htmlspecialchars($edit_cat['color']??'#3fb950') ?>"></div>
                            <div class="form-group" style="width:70px;"><label>排序</label><input type="number" name="sort_order" min="0" max="999" value="<?= intval($edit_cat['sort_order']??0) ?>"></div>
                            <div style="display:flex;gap:8px;margin-left:auto;">
                                <button type="submit" class="btn btn-green"><?= $edit_cat?'保存修改':'创建分区' ?></button>
                                <?php if($edit_cat): ?><a href="admin.php?tab=categories" class="btn btn-outline">取消</a><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="ap-card">
        <div style="padding:12px 16px;border-bottom:1px solid #30363d;font-size:12px;font-weight:700;color:#6e7681;letter-spacing:1.2px;text-transform:uppercase;font-family:'Courier New',monospace;">现有分区（<?= count($cats) ?>）</div>
        <?php if(empty($cats)): ?><div style="padding:32px;text-align:center;color:#6e7681;font-family:'Courier New',monospace;font-size:13px;">// 还没有分区</div>
        <?php else: foreach($cats as $c): ?>
        <div class="cat-row">
            <?php if(!empty($c['cover_image'])): ?>
            <img class="cat-thumb" src="../<?= htmlspecialchars($c['cover_image']) ?>" alt="">
            <?php else: ?>
            <div class="cat-thumb" style="display:flex;align-items:center;justify-content:center;font-size:18px;color:#484f58;"><?= htmlspecialchars($c['icon']?:'#') ?></div>
            <?php endif; ?>
            <span class="cat-dot" style="background:<?= htmlspecialchars($c['color']) ?>"></span>
            <div class="cat-info">
                <div class="cat-name"><?= htmlspecialchars($c['name']) ?></div>
                <div class="cat-meta"><?= (int)$c['post_count'] ?> 篇帖子<?= !empty($c['description'])?' · '.htmlspecialchars(mb_substr($c['description'],0,30)):'' ?></div>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0;">
                <a href="admin.php?tab=categories&edit_cat=<?= $c['id'] ?>" class="btn btn-outline btn-sm">编辑</a>
                <a href="admin.php?tab=categories&delete_cat=<?= $c['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('删除「<?= htmlspecialchars($c['name']) ?>」？')">删除</a>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- ══════════════ TAB: 评论查询 ══════════════ -->
    <?php elseif ($tab === 'comments'): ?>

    <?php if($cmt_msg==='deleted'): ?><div class="msg-bar msg-ok">✓ 评论已删除</div><?php endif; ?>

    <div class="search-card">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="tab" value="comments">
            <div class="search-field" style="max-width:260px;">
                <label>用户 MID</label>
                <input class="mid-input" type="text" name="mid" value="<?= htmlspecialchars($cmt_mid) ?>" placeholder="输入 MID 查询该用户全部评论" maxlength="8">
            </div>
            <button type="submit" class="btn-query">查询评论</button>
        </form>
    </div>

    <?php if($cmt_mid!==''&&!$cmt_user): ?>
        <div class="alert-error">MID「<?= htmlspecialchars($cmt_mid) ?>」不存在</div>
    <?php elseif($cmt_user): ?>
        <!-- 用户信息 -->
        <div style="display:flex;align-items:center;gap:12px;background:#161b22;border:1px solid #30363d;border-radius:6px;padding:12px 16px;margin-bottom:16px;">
            <img src="../uploads/<?= htmlspecialchars($cmt_user['avatar']?:'default.png') ?>" onerror="this.onerror=null;this.src='../uploads/default.png'" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:1px solid #30363d;">
            <div>
                <div style="font-size:14px;font-weight:700;color:#e6edf3;"><?= htmlspecialchars($cmt_user['username']) ?> <?= get_role_badge($cmt_user['role']) ?></div>
                <div style="font-size:11px;color:#6e7681;font-family:'Courier New',monospace;">MID: <?= htmlspecialchars($cmt_mid) ?> &nbsp;·&nbsp; 共 <strong style="color:#3fb950;"><?= count($cmt_list) ?></strong> 条评论</div>
            </div>
        </div>

        <?php if(empty($cmt_list)): ?>
        <div class="empty-state">该用户暂无评论</div>
        <?php else: foreach($cmt_list as $c): ?>
        <div class="cmt-card">
            <div class="cmt-body">
                <?php if($c['parent_id']): ?><div style="font-size:11px;color:#6e7681;margin-bottom:4px;font-family:'Courier New',monospace;">↩ 回复评论</div><?php endif; ?>
                <div class="cmt-content"><?= htmlspecialchars($c['content']) ?></div>
                <div class="cmt-meta">
                    <span>帖子：<a href="post.php?id=<?= $c['post_id'] ?>" target="_blank" class="cmt-post-link"><?= htmlspecialchars($c['post_title']) ?></a></span>
                    <span>·</span>
                    <span><?= date('Y-m-d H:i', strtotime($c['created_at'])) ?></span>
                    <?php if($c['likes']>0): ?><span>· 👍 <?= $c['likes'] ?></span><?php endif; ?>
                    <span>· ID:<?= $c['id'] ?></span>
                </div>
            </div>
            <div style="flex-shrink:0;">
                <a href="admin.php?tab=comments&mid=<?= urlencode($cmt_mid) ?>&delete_comment=<?= $c['id'] ?>" class="btn btn-delete btn-sm" onclick="return confirm('删除此评论（含子回复）？')">🗑</a>
            </div>
        </div>
        <?php endforeach; endif; ?>
    <?php endif; ?>

    <!-- ══════════════ TAB: 私信查询 ══════════════ -->
    <?php elseif ($tab === 'messages'): ?>

    <div class="search-card">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="tab" value="messages">
            <div class="search-field">
                <label>用户 A 的 MID</label>
                <input class="mid-input" type="text" name="mid_a" value="<?= htmlspecialchars($mid_a??'') ?>" placeholder="例：AB12CD34" maxlength="8">
            </div>
            <div class="search-field">
                <label>用户 B 的 MID</label>
                <input class="mid-input" type="text" name="mid_b" value="<?= htmlspecialchars($mid_b??'') ?>" placeholder="例：EF56GH78" maxlength="8">
            </div>
            <button type="submit" class="btn-query">查询记录</button>
        </form>
    </div>

    <?php foreach($msg_errors as $e): ?><div class="alert-error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

    <?php if($msg_a&&$msg_b&&empty($msg_errors)): ?>
    <div class="user-pair">
        <div class="user-card-sm">
            <img src="../uploads/<?= htmlspecialchars($msg_a['avatar']?:'default.png') ?>" onerror="this.onerror=null;this.src='../uploads/default.png'" alt="">
            <div><div class="user-card-sm-name"><?= htmlspecialchars($msg_a['username']) ?></div><div class="user-card-sm-mid">MID: <?= htmlspecialchars($mid_a) ?></div></div>
        </div>
        <div class="pair-sep">↔</div>
        <div class="user-card-sm">
            <img src="../uploads/<?= htmlspecialchars($msg_b['avatar']?:'default.png') ?>" onerror="this.onerror=null;this.src='../uploads/default.png'" alt="">
            <div><div class="user-card-sm-name"><?= htmlspecialchars($msg_b['username']) ?></div><div class="user-card-sm-mid">MID: <?= htmlspecialchars($mid_b) ?></div></div>
        </div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;font-size:12px;color:#6e7681;font-family:'Courier New',monospace;">
        <span>共 <strong style="color:#3fb950;"><?= $msg_total ?></strong> 条消息</span>
        <span>第 <?= $mpage ?>/<?= $msg_pages ?> 页</span>
    </div>

    <?php if(empty($msg_list)): ?><div class="empty-state">双方之间暂无私信记录</div>
    <?php else: ?>
    <div class="chat-log">
        <?php $last_date=''; $uid_a_int=(int)$msg_a['id'];
        foreach($msg_list as $m):
            $mdate=date('Y-m-d',strtotime($m['created_at']));
            if($mdate!==$last_date){$last_date=$mdate;?><div class="date-sep"><?= htmlspecialchars($mdate) ?></div><?php }
            $is_right=((int)$m['from_user_id']!==$uid_a_int);
            $av=htmlspecialchars($m['sender_avatar']?:'default.png');
        ?>
        <div class="msg-row <?= $is_right?'right':'left' ?>">
            <img class="msg-avatar" src="../uploads/<?= $av ?>" alt="" onerror="this.onerror=null;this.src='../uploads/default.png'">
            <div class="msg-body">
                <div class="msg-meta"><span class="msg-sender"><?= htmlspecialchars($m['sender_name']) ?></span><?= date('H:i',strtotime($m['created_at'])) ?></div>
                <div class="msg-bubble"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    if($msg_pages>1):
        $qs=http_build_query(['tab'=>'messages','mid_a'=>$mid_a,'mid_b'=>$mid_b]);
        echo '<div class="pag">';
        if($mpage>1) echo '<a href="admin.php?'.$qs.'&mpage='.($mpage-1).'">‹ 上一页</a>';
        for($i=1;$i<=$msg_pages;$i++) echo '<a href="admin.php?'.$qs.'&mpage='.$i.'" '.($i===$mpage?'class="cur"':'').'>'.$i.'</a>';
        if($mpage<$msg_pages) echo '<a href="admin.php?'.$qs.'&mpage='.($mpage+1).'">下一页 ›</a>';
        echo '</div>';
    endif; ?>
    <?php endif; ?>
    <?php endif; ?>

    <?php endif; /* end tab switch */ ?>

</div>

<!-- 举报：快速封禁弹窗 -->
<div id="ban-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#161b22;border:1px solid #30363d;border-radius:8px;padding:22px;width:340px;max-width:92vw;font-family:'Courier New',monospace;">
        <p style="margin:0 0 12px;color:#f85149;font-weight:700;font-size:13px;">快速封禁用户</p>
        <input id="qban-reason" type="text" placeholder="封禁原因（必填）" maxlength="200"
               style="width:100%;background:#0d1117;border:1px solid #30363d;color:#e6edf3;padding:8px 10px;border-radius:4px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
        <div style="display:flex;gap:8px;margin-top:12px;">
            <button onclick="confirmBan()" style="flex:1;padding:8px;border:none;border-radius:4px;background:#f85149;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;">封禁</button>
            <button onclick="document.getElementById('ban-modal').style.display='none'" style="flex:1;padding:8px;border:1px solid #30363d;border-radius:4px;background:transparent;color:#8b949e;font-size:13px;cursor:pointer;font-family:inherit;">取消</button>
        </div>
    </div>
</div>

<script>
// 举报操作
let _qbRid=0,_qbUid=0;
function rHandle(rid,action){
    const fd=new FormData(); fd.append('action',action); fd.append('report_id',rid);
    fetch('admin_reports.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.ok){const c=document.getElementById('rcard-'+rid);if(c)c.style.transition='opacity .3s',c.style.opacity='0',setTimeout(()=>c.remove(),300);}
    });
}
function rDelPost(rid,pid){
    if(!confirm('确认删除该帖子并标记举报为已处理？'))return;
    const fd=new FormData(); fd.append('action','delete_post'); fd.append('report_id',rid); fd.append('target_id',pid);
    fetch('admin_reports.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.ok){const c=document.getElementById('rcard-'+rid);if(c)c.style.transition='opacity .3s',c.style.opacity='0',setTimeout(()=>c.remove(),300);}
    });
}
function rBanUser(rid,uid){_qbRid=rid;_qbUid=uid;document.getElementById('qban-reason').value='';document.getElementById('ban-modal').style.display='flex';}
function confirmBan(){
    const reason=document.getElementById('qban-reason').value.trim();
    if(!reason){alert('请填写封禁原因');return;}
    const fd=new FormData(); fd.append('action','ban_user'); fd.append('report_id',_qbRid); fd.append('target_id',_qbUid); fd.append('ban_reason',reason);
    fetch('admin_reports.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        document.getElementById('ban-modal').style.display='none';
        if(d.ok){const c=document.getElementById('rcard-'+_qbRid);if(c)c.style.transition='opacity .3s',c.style.opacity='0',setTimeout(()=>c.remove(),300);}
    });
}
document.getElementById('ban-modal').addEventListener('click',function(e){if(e.target===this)this.style.display='none';});

// 解封
function doUnban(uid,name){
    if(!confirm('确认解除「'+name+'」的封禁？'))return;
    const fd=new FormData(); fd.append('action','unban'); fd.append('user_id',uid);
    fetch('admin_ban.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.ok){const c=document.getElementById('bcard-'+uid);if(c)c.style.transition='opacity .3s',c.style.opacity='0',setTimeout(()=>c.remove(),300);}
        else alert(d.msg||'操作失败');
    });
}

// 分区封面预览
function prevCat(input){
    const p=document.getElementById('cat-preview'),e=document.getElementById('cat-empty');
    if(input.files&&input.files[0]){
        const r=new FileReader(); r.onload=ev=>{p.src=ev.target.result;p.style.display='block';if(e)e.style.display='none';};
        r.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
<?php $conn->close(); ?>
