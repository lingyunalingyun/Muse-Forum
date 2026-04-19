<?php
/**
 * publish.php — 发帖页
 *
 * 功能：WangEditor 富文本编辑器，支持附件上传；表单提交到 actions/save.php
 * 读写表：无直接 DB 操作
 * 权限：需登录且未被封禁
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if (!empty($_SESSION['is_banned'])) {
    header("Location: ../index.php");
    exit;
}

$uid      = (int)$_SESSION['user_id'];
$is_admin = in_array($_SESSION['role'] ?? '', ['admin', 'owner']);

$draft_id = (int)($_GET['draft_id'] ?? 0);
$draft    = null;
if ($draft_id > 0) {
    $dr = $conn->query("SELECT * FROM posts WHERE id=$draft_id AND user_id=$uid AND status='草稿'");
    $draft = ($dr && $dr->num_rows > 0) ? $dr->fetch_assoc() : null;
}

$cats_res = $conn->query("SELECT id, name, icon, color FROM categories ORDER BY sort_order ASC, id ASC");
$categories = [];
if ($cats_res) while ($c = $cats_res->fetch_assoc()) $categories[] = $c;

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
    <title><?= $draft ? '编辑草稿' : '发布帖子' ?> - 缪斯 MUSE</title>
    <link href="https://cdn.jsdelivr.net/npm/@wangeditor/editor@5.1.23/dist/css/style.css" rel="stylesheet">
    <style>
        
        .w-e-toolbar {
            background: 
            border-color: 
        }
        .w-e-bar-item button, .w-e-bar-item-group>button {
            color: 
        }
        .w-e-bar-item button:hover, .w-e-bar-item-group>button:hover,
        .w-e-bar-item button.active {
            background: 
            color: 
        }
        .w-e-text-container {
            background: 
            color: 
        }
        .w-e-scroll { background: 
        [data-slate-editor] { color: 
        .w-e-text-placeholder { color: 
        
        .w-e-drop-panel {
            background: 
            border-color: 
            box-shadow: 0 4px 16px rgba(0,0,0,.5) !important;
        }
        .w-e-select-list {
            background: 
            border-color: 
        }
        .w-e-select-list ul li { color: 
        .w-e-select-list ul li:hover,
        .w-e-select-list ul li.selected {
            background: 
            color: 
        }
        
        .w-e-panel-content-color button:hover { outline-color: 
        .w-e-modal { background: 
        .w-e-modal input { background: 
        .w-e-modal button[type=button] { background: 
        .w-e-bar-divider { background: 
    </style>
    <style>
        
        .pub-topbar {
            background: 
            padding: 0 24px; height: 50px;
            display: flex; align-items: center; gap: 12px;
            position: sticky; top: 56px; z-index: 100;
        }
        .pub-back { color: 
        .pub-back:hover { background: 
        .pub-topbar-title { font-size: 14px; font-weight: 700; color: 
        .autosave-tip { font-size: 11px; color: 
        .autosave-tip.saved { color: 
        .btn-draft {
            background: transparent; border: 1px solid 
            padding: 6px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; transition: .15s; font-family: inherit;
        }
        .btn-draft:hover { border-color: 
        .btn-publish {
            background: 
            padding: 7px 20px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 700; transition: .2s; font-family: inherit;
        }
        .btn-publish:hover { background: 
        .btn-publish:disabled { background: 

        .pub-layout { max-width: 960px; margin: 20px auto; padding: 0 16px; display: flex; gap: 18px; align-items: flex-start; }
        .pub-main { flex: 1; min-width: 0; }

        .editor-card { background: 
        .title-input {
            width: 100%; border: none; outline: none;
            font-size: 20px; font-weight: 700; color: 
            padding: 20px 22px 14px; font-family: inherit; background: transparent;
        }
        .title-input::placeholder { color: 
        .editor-divider { height: 1px; background: 
        
        

        .attach-section { border-top: 1px solid 
        .attach-header { font-size: 12px; color: 
        .attach-dropzone {
            border: 1px dashed 
            text-align: center; color: 
        }
        .attach-dropzone:hover, .attach-dropzone.drag-over { border-color: 
        .attach-dropzone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .attach-list { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
        .attach-item { display: flex; align-items: center; gap: 10px; background: 
        .attach-icon { font-size: 16px; flex-shrink: 0; }
        .attach-name { flex: 1; color: 
        .attach-size { color: 
        .attach-del  { color: 
        .attach-del:hover { opacity: 1; }
        .attach-progress { color: 

        
        .cat-select-card { background: 
        .cat-select-title { font-size: 11px; font-weight: 700; color: 
        .cat-options { display: flex; flex-wrap: wrap; gap: 8px; }
        .cat-option { display: none; }
        .cat-option + label {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 14px; border-radius: 20px; cursor: pointer;
            font-size: 13px; border: 1px solid 
            background: 
        }
        .cat-option:checked + label { color: 
        .cat-option + label:hover { border-color: 

        .settings-card { background: 
        .settings-title { font-size: 11px; font-weight: 700; color: 
        .setting-row { display: flex; align-items: flex-start; gap: 12px; padding: 9px 0; border-bottom: 1px solid 
        .setting-row:last-child { border-bottom: none; padding-bottom: 0; }
        .setting-row input[type=checkbox] { width: 15px; height: 15px; margin-top: 2px; cursor: pointer; accent-color: 
        .setting-label { font-size: 13px; color: 
        .setting-desc  { font-size: 12px; color: 

        .drafts-sidebar { width: 210px; flex-shrink: 0; }
        .drafts-card { background: 
        .drafts-card-title { font-size: 11px; font-weight: 700; color: 
        .draft-link { display: block; padding: 9px 14px; text-decoration: none; color: 
        .draft-link:last-child { border-bottom: none; }
        .draft-link:hover { background: 
        .draft-link-title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .draft-link-time  { font-size: 11px; color: 
        .no-drafts { padding: 18px 14px; text-align: center; color: 

        @media (max-width: 720px) {
            .pub-layout { flex-direction: column; padding: 0 10px; }
            .drafts-sidebar { width: 100%; order: -1; }
            .pub-topbar { padding: 0 12px; gap: 8px; }
            .pub-topbar-title { display: none; }
            .autosave-tip { display: none; }
        }
        @media (max-width: 480px) {
            .pub-topbar { height: auto; flex-wrap: wrap; padding: 8px 12px; }
            .btn-draft, .btn-publish { font-size: 12px; padding: 5px 12px; }
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

        <?php if (!empty($categories)): ?>
        <!-- 分区选择（多选） -->
        <div class="cat-select-card">
            <div class="cat-select-title">选择分区 <span style="font-weight:400;color:#484f58;font-size:10px;">可多选</span></div>
            <div class="cat-options">
                <input type="checkbox" class="cat-option" id="cat_0" value="0" checked onchange="onCatChange(this)">
                <label for="cat_0">不限分区</label>
                <?php
                
                $draft_cats = [];
                if ($draft) {
                    $dc_r = $conn->query("SELECT category_id FROM post_categories WHERE post_id=" . (int)$draft['id']);
                    if ($dc_r) while ($dc = $dc_r->fetch_assoc()) $draft_cats[] = (int)$dc['category_id'];
                }
                foreach ($categories as $c):
                    $is_checked = in_array((int)$c['id'], $draft_cats) ? 'checked' : '';
                ?>
                <input type="checkbox" class="cat-option cat-real" id="cat_<?= $c['id'] ?>" value="<?= $c['id'] ?>"
                       style="--cat-color:<?= htmlspecialchars($c['color']) ?>"
                       onchange="onCatChange(this)" <?= $is_checked ?>>
                <label for="cat_<?= $c['id'] ?>" style="--cat-color:<?= htmlspecialchars($c['color']) ?>">
                    <?= htmlspecialchars($c['icon']) ?> <?= htmlspecialchars($c['name']) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

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
            <div style="padding:10px 16px; font-size:12px; color:#3fb950; border-bottom:1px solid #30363d; font-family:'Courier New',monospace;">
                
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

<script src="https://cdn.jsdelivr.net/npm/@wangeditor/editor@5.1.23/dist/index.js"></script>
<script>
const { createEditor, createToolbar } = window.wangEditor;

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

let attachments = <?= json_encode($draft && $draft['attachments'] ? json_decode($draft['attachments'], true) : []) ?>;

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

    const realChecked = [...document.querySelectorAll('.cat-real:checked')];
    if (realChecked.length > 0) {
        realChecked.forEach(el => fd.append('category_ids[]', el.value));
    }

    try {
        const res  = await fetch('../actions/save.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status === 'ok') {
            if (isDraft) {
                
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

function onCatChange(el) {
    const none = document.getElementById('cat_0');
    const reals = [...document.querySelectorAll('.cat-real')];
    if (el.value === '0') {
        
        if (el.checked) reals.forEach(r => r.checked = false);
        else el.checked = true; 
    } else {
        
        if (el.checked) none.checked = false;
        
        if (reals.every(r => !r.checked)) none.checked = true;
    }
}
</script>
</body>
</html>
<?php $conn->close(); ?>
