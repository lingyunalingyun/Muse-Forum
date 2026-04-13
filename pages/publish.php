<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid      = (int)$_SESSION['user_id'];
$is_admin = ($_SESSION['role'] ?? '') === 'admin';

// 加载指定草稿
$draft_id = (int)($_GET['draft_id'] ?? 0);
$draft    = null;
if ($draft_id > 0) {
    $dr = $conn->query("SELECT * FROM posts WHERE id=$draft_id AND user_id=$uid AND status='草稿'");
    $draft = ($dr && $dr->num_rows > 0) ? $dr->fetch_assoc() : null;
}

// 其他草稿列表（排除当前打开的）
$drafts_res = $conn->query("
    SELECT id, title, created_at FROM posts
    WHERE user_id = $uid AND status = '草稿'" .
    ($draft_id > 0 ? " AND id != $draft_id" : "") .
    " ORDER BY id DESC LIMIT 8
");
$other_drafts = [];
if ($drafts_res) {
    while ($d = $drafts_res->fetch_assoc()) $other_drafts[] = $d;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= $draft ? '编辑草稿' : '发布帖子' ?> - 社区论坛</title>
    <link href="https://unpkg.com/@wangeditor/editor@latest/dist/css/style.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { background: #f0f2f5; font-family: "Microsoft YaHei", sans-serif; margin: 0; padding-bottom: 60px; }

        /* ── 顶部操作栏 ── */
        .pub-topbar {
            background: white;
            border-bottom: 1px solid #eee;
            padding: 0 24px;
            height: 56px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: sticky;
            top: 60px;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .pub-back {
            color: #888;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 6px 10px;
            border-radius: 6px;
            transition: 0.2s;
        }
        .pub-back:hover { background: #f5f5f5; color: #333; }
        .pub-topbar-title { font-size: 15px; font-weight: bold; color: #333; flex: 1; }
        .autosave-tip { font-size: 12px; color: #bbb; }
        .autosave-tip.saved { color: #28a745; }
        .btn-draft {
            background: white;
            border: 1px solid #ddd;
            color: #555;
            padding: 7px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: 0.2s;
        }
        .btn-draft:hover { border-color: #aaa; color: #333; }
        .btn-publish {
            background: #28a745;
            border: none;
            color: white;
            padding: 8px 22px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: 0.2s;
        }
        .btn-publish:hover { background: #218838; }
        .btn-publish:disabled { background: #aaa; cursor: not-allowed; }

        /* ── 主内容 ── */
        .pub-layout { max-width: 960px; margin: 24px auto; padding: 0 16px; display: flex; gap: 20px; align-items: flex-start; }
        .pub-main { flex: 1; min-width: 0; }

        /* ── 编辑卡片 ── */
        .editor-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .title-input {
            width: 100%;
            border: none;
            outline: none;
            font-size: 22px;
            font-weight: bold;
            color: #222;
            padding: 24px 24px 16px;
            font-family: inherit;
            background: transparent;
        }
        .title-input::placeholder { color: #ccc; }
        .editor-divider { height: 1px; background: #f5f5f5; margin: 0 24px; }
        #editor-toolbar {
            border-bottom: 1px solid #f0f0f0;
            padding: 0 12px;
        }
        #editor-text-area {
            min-height: 380px;
            padding: 4px 24px;
        }

        /* ── 附件区 ── */
        .attach-section {
            border-top: 1px solid #f5f5f5;
            padding: 16px 24px;
        }
        .attach-header {
            font-size: 13px;
            color: #888;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .attach-dropzone {
            border: 2px dashed #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            color: #bbb;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s;
            position: relative;
        }
        .attach-dropzone:hover, .attach-dropzone.drag-over { border-color: #28a745; color: #28a745; background: #f6fff8; }
        .attach-dropzone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .attach-list { margin-top: 12px; display: flex; flex-direction: column; gap: 8px; }
        .attach-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 13px;
        }
        .attach-icon { font-size: 18px; flex-shrink: 0; }
        .attach-name { flex: 1; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .attach-size { color: #bbb; font-size: 12px; flex-shrink: 0; }
        .attach-del { color: #ff7675; cursor: pointer; font-size: 16px; flex-shrink: 0; }
        .attach-del:hover { color: #d63031; }
        .attach-progress { flex-shrink: 0; font-size: 12px; color: #28a745; }

        /* ── 设置卡片 ── */
        .settings-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            padding: 20px;
            margin-top: 16px;
        }
        .settings-title { font-size: 13px; font-weight: bold; color: #888; margin-bottom: 14px; letter-spacing: 1px; }
        .setting-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f8f8f8;
        }
        .setting-row:last-child { border-bottom: none; padding-bottom: 0; }
        .setting-row input[type=checkbox] { width: 16px; height: 16px; margin-top: 2px; cursor: pointer; accent-color: #28a745; flex-shrink: 0; }
        .setting-label { font-size: 14px; color: #444; }
        .setting-desc  { font-size: 12px; color: #bbb; margin-top: 3px; }

        /* ── 草稿侧栏 ── */
        .drafts-sidebar { width: 220px; flex-shrink: 0; }
        .drafts-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .drafts-card-title {
            font-size: 13px;
            font-weight: bold;
            color: #888;
            padding: 14px 16px;
            border-bottom: 1px solid #f5f5f5;
            letter-spacing: 1px;
        }
        .draft-link {
            display: block;
            padding: 10px 16px;
            text-decoration: none;
            color: #444;
            font-size: 13px;
            border-bottom: 1px solid #f8f8f8;
            transition: 0.15s;
            overflow: hidden;
        }
        .draft-link:last-child { border-bottom: none; }
        .draft-link:hover { background: #f9f9f9; color: #28a745; }
        .draft-link-title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .draft-link-time  { font-size: 11px; color: #bbb; margin-top: 3px; }
        .no-drafts { padding: 20px 16px; text-align: center; color: #ccc; font-size: 13px; }

        @media (max-width: 720px) {
            .pub-layout { flex-direction: column; }
            .drafts-sidebar { width: 100%; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<!-- 顶部操作栏 -->
<div class="pub-topbar">
    <a href="../index.php" class="pub-back">← 返回</a>
    <span class="pub-topbar-title"><?= $draft ? '编辑草稿' : '发布新帖子' ?></span>
    <span class="autosave-tip" id="autosave-tip">等待编辑…</span>
    <button class="btn-draft"   onclick="submitPost(true)">保存草稿</button>
    <button class="btn-publish" onclick="submitPost(false)" id="pub-btn">发布帖子</button>
</div>

<div class="pub-layout">

    <!-- 主编辑区 -->
    <div class="pub-main">
        <div class="editor-card">
            <input type="text" id="post-title"
                   class="title-input"
                   placeholder="起一个吸引人的标题…"
                   value="<?= $draft ? htmlspecialchars($draft['title']) : '' ?>"
                   maxlength="100">
            <div class="editor-divider"></div>
            <div id="editor-toolbar"></div>
            <div id="editor-text-area"></div>

            <!-- 附件上传区 -->
            <div class="attach-section">
                <div class="attach-header">
                    📎 附件
                    <span style="color:#bbb; font-weight:normal;">支持图片、文档、压缩包等（单文件 ≤ 20MB）</span>
                </div>
                <div class="attach-dropzone" id="dropzone"
                     ondragover="event.preventDefault();this.classList.add('drag-over')"
                     ondragleave="this.classList.remove('drag-over')"
                     ondrop="handleDrop(event)">
                    <input type="file" id="attach-input" multiple onchange="handleFiles(this.files)">
                    📂 点击或拖拽文件到此处上传
                </div>
                <div class="attach-list" id="attach-list"></div>
            </div>
        </div>

        <?php if ($is_admin): ?>
        <!-- 管理员设置 -->
        <div class="settings-card">
            <div class="settings-title">管理员选项</div>
            <div class="setting-row">
                <input type="checkbox" id="is-notice" <?= ($draft && $draft['is_notice']) ? 'checked' : '' ?>>
                <div>
                    <div class="setting-label">📢 设为公告</div>
                    <div class="setting-desc">发布后将在首页侧栏展示，并自动设为已发布状态</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 草稿侧栏 -->
    <aside class="drafts-sidebar">
        <div class="drafts-card">
            <div class="drafts-card-title">📝 我的草稿</div>
            <?php if ($draft): ?>
            <div style="padding:10px 16px; font-size:12px; color:#28a745; border-bottom:1px solid #f5f5f5;">
                ✏️ 当前正在编辑此草稿
            </div>
            <?php endif; ?>
            <?php if (!empty($other_drafts)): ?>
                <?php foreach ($other_drafts as $od): ?>
                <a href="publish.php?draft_id=<?= $od['id'] ?>" class="draft-link">
                    <div class="draft-link-title"><?= htmlspecialchars($od['title'] ?: '（无标题）') ?></div>
                    <div class="draft-link-time"><?= date('m-d H:i', strtotime($od['created_at'])) ?></div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <?php if (!$draft): ?>
                <div class="no-drafts">还没有草稿</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </aside>

</div>

<!-- 草稿 ID（编辑模式） -->
<input type="hidden" id="draft-id" value="<?= $draft ? $draft['id'] : 0 ?>">

<script src="https://unpkg.com/@wangeditor/editor@latest/dist/index.js"></script>
<script>
const { createEditor, createToolbar } = window.wangEditor;

// ── 初始化编辑器 ──
const editorConfig = {
    placeholder: '写点什么吧…支持拖拽图片直接上传',
    MENU_CONF: {
        uploadImage: {
            server: '../actions/upload_image.php',
            fieldName: 'wangeditor-uploaded-image',
            maxFileSize: 5 * 1024 * 1024,
            maxNumberOfFiles: 20,
            allowedFileTypes: ['image/*'],
            customInsert(res, insertFn) {
                if (res.errno === 0) insertFn(res.data.url, '', '');
                else alert('图片上传失败：' + (res.message || '未知错误'));
            }
        }
    }
};

const editor = createEditor({
    selector: '#editor-text-area',
    html: <?= json_encode($draft ? $draft['content'] : '<p><br></p>') ?>,
    config: editorConfig,
    mode: 'default'
});

createToolbar({ editor, selector: '#editor-toolbar', mode: 'default' });

// ── 附件管理 ──
let attachments = <?= json_encode($draft && $draft['attachments'] ? json_decode($draft['attachments'], true) : []) ?>;

// 恢复草稿附件显示
attachments.forEach(att => renderAttachItem(att));

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('dropzone').classList.remove('drag-over');
    handleFiles(e.dataTransfer.files);
}

function handleFiles(files) {
    [...files].forEach(file => uploadAttachment(file));
}

function uploadAttachment(file) {
    const id    = 'att_' + Date.now() + '_' + Math.random().toString(36).slice(2);
    const item  = renderAttachItem({ id, original: file.name, size: file.size, uploading: true });

    const fd = new FormData();
    fd.append('file', file);

    fetch('../actions/upload_attachment.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            // 更新条目数据
            const att = { id, original: data.original, filename: data.filename, size: data.size, url: data.url, ext: data.ext };
            attachments.push(att);
            updateAttachItem(id, att);
        } else {
            removeAttachItem(id);
            alert('附件上传失败：' + data.msg);
        }
    })
    .catch(() => { removeAttachItem(id); alert('上传失败，请检查网络'); });
}

function getFileIcon(ext) {
    const map = { pdf:'📄', doc:'📝', docx:'📝', xls:'📊', xlsx:'📊',
                  ppt:'📊', pptx:'📊', zip:'🗜️', rar:'🗜️', '7z':'🗜️',
                  mp4:'🎬', mp3:'🎵', txt:'📃', png:'🖼️', jpg:'🖼️',
                  jpeg:'🖼️', gif:'🖼️', webp:'🖼️' };
    return map[(ext||'').toLowerCase()] || '📎';
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/1024/1024).toFixed(1) + ' MB';
}

function renderAttachItem(att) {
    const list = document.getElementById('attach-list');
    const div  = document.createElement('div');
    div.className = 'attach-item';
    div.id = 'ai_' + att.id;
    div.innerHTML = `
        <span class="attach-icon">${getFileIcon(att.ext || '')}</span>
        <span class="attach-name">${att.original}</span>
        <span class="attach-size">${att.size ? formatSize(att.size) : ''}</span>
        ${att.uploading
            ? '<span class="attach-progress">上传中…</span>'
            : `<span class="attach-del" onclick="removeAttachItem('${att.id}')">×</span>`}
    `;
    list.appendChild(div);
    return div;
}

function updateAttachItem(id, att) {
    const div = document.getElementById('ai_' + id);
    if (!div) return;
    div.querySelector('.attach-size').textContent = formatSize(att.size);
    const prog = div.querySelector('.attach-progress');
    if (prog) prog.outerHTML = `<span class="attach-del" onclick="removeAttachItem('${id}')">×</span>`;
}

function removeAttachItem(id) {
    attachments = attachments.filter(a => a.id !== id);
    const el = document.getElementById('ai_' + id);
    if (el) el.remove();
}

// ── 提交（草稿 / 发布）──
async function submitPost(isDraft) {
    const title = document.getElementById('post-title').value.trim();
    if (!title)            { alert('标题不能为空'); return; }
    if (editor.isEmpty())  { alert('内容不能为空'); return; }

    const pubBtn = document.getElementById('pub-btn');
    pubBtn.disabled = true;
    pubBtn.textContent = isDraft ? '保存中…' : '发布中…';

    const fd = new FormData();
    fd.append('title',       title);
    fd.append('content',     editor.getHtml());
    fd.append('is_draft',    isDraft ? '1' : '0');
    fd.append('draft_id',    document.getElementById('draft-id').value);
    fd.append('attachments', JSON.stringify(attachments.filter(a => !a.uploading)));

    const noticeEl = document.getElementById('is-notice');
    if (noticeEl) fd.append('is_notice', noticeEl.checked ? '1' : '0');

    try {
        const res  = await fetch('../actions/save.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status === 'ok') {
            if (isDraft) {
                // 更新草稿 ID，更新提示
                document.getElementById('draft-id').value = data.id;
                history.replaceState(null, '', 'publish.php?draft_id=' + data.id);
                setAutosaveTip('草稿已保存 ' + new Date().toLocaleTimeString());
                pubBtn.disabled = false;
                pubBtn.textContent = '发布帖子';
            } else {
                window.location.href = '../index.php';
            }
        } else {
            alert('操作失败：' + data.msg);
            pubBtn.disabled = false;
            pubBtn.textContent = isDraft ? '保存草稿' : '发布帖子';
        }
    } catch(e) {
        alert('网络错误，请稍后重试');
        pubBtn.disabled = false;
        pubBtn.textContent = isDraft ? '保存草稿' : '发布帖子';
    }
}

// ── 自动保存（60s，仅有内容时）──
let autoTimer = null;
function scheduleAutosave() {
    clearTimeout(autoTimer);
    autoTimer = setTimeout(async () => {
        const title = document.getElementById('post-title').value.trim();
        if (!title && editor.isEmpty()) return;
        setAutosaveTip('自动保存中…');
        await submitPost(true);
    }, 60000);
    setAutosaveTip('有未保存的修改');
}

function setAutosaveTip(txt) {
    const el = document.getElementById('autosave-tip');
    el.textContent = txt;
    el.className = 'autosave-tip' + (txt.includes('已保存') ? ' saved' : '');
}

document.getElementById('post-title').addEventListener('input', scheduleAutosave);
editor.on('change', scheduleAutosave);
</script>
</body>
</html>
<?php $conn->close(); ?>
