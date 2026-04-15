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

// 分区列表
$cats_res = $conn->query("SELECT id, name, icon, color FROM categories ORDER BY sort_order ASC, id ASC");
$categories = [];
if ($cats_res) while ($c = $cats_res->fetch_assoc()) $categories[] = $c;

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
    <title><?= $draft ? '编辑草稿' : '发布帖子' ?> - 缪斯 MUSE</title>
    <link href="https://cdn.jsdelivr.net/npm/@wangeditor/editor@5.1.23/dist/css/style.css" rel="stylesheet">
    <style>
        /* ── WangEditor 深色主题覆盖 ── */
        .w-e-toolbar {
            background: #0d1117 !important;
            border-color: #30363d !important;
        }
        .w-e-bar-item button, .w-e-bar-item-group>button {
            color: #8b949e !important;
        }
        .w-e-bar-item button:hover, .w-e-bar-item-group>button:hover,
        .w-e-bar-item button.active {
            background: #1c2128 !important;
            color: #e6edf3 !important;
        }
        .w-e-text-container {
            background: #161b22 !important;
            color: #c9d1d9 !important;
        }
        .w-e-scroll { background: #161b22 !important; }
        [data-slate-editor] { color: #c9d1d9 !important; }
        .w-e-text-placeholder { color: #484f58 !important; }
        /* 下拉面板 */
        .w-e-drop-panel {
            background: #161b22 !important;
            border-color: #30363d !important;
            box-shadow: 0 4px 16px rgba(0,0,0,.5) !important;
        }
        .w-e-select-list {
            background: #161b22 !important;
            border-color: #30363d !important;
        }
        .w-e-select-list ul li { color: #8b949e !important; }
        .w-e-select-list ul li:hover,
        .w-e-select-list ul li.selected {
            background: #1c2128 !important;
            color: #e6edf3 !important;
        }
        /* 颜色选择器、字号等面板 */
        .w-e-panel-content-color button:hover { outline-color: #3fb950 !important; }
        .w-e-modal { background: #161b22 !important; border-color: #30363d !important; }
        .w-e-modal input { background: #0d1117 !important; border-color: #30363d !important; color: #e6edf3 !important; }
        .w-e-modal button[type=button] { background: #3fb950 !important; border-color: #3fb950 !important; color: #fff !important; }
        .w-e-bar-divider { background: #30363d !important; }
    </style>
    <style>
        /* ── 发帖页专属 ── */
        .pub-topbar {
            background: #161b22; border-bottom: 1px solid #30363d;
            padding: 0 24px; height: 50px;
            display: flex; align-items: center; gap: 12px;
            position: sticky; top: 56px; z-index: 100;
        }
        .pub-back { color: #8b949e; text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 4px; padding: 5px 10px; border-radius: 4px; transition: .15s; }
        .pub-back:hover { background: #1c2128; color: #e6edf3; }
        .pub-topbar-title { font-size: 14px; font-weight: 700; color: #e6edf3; flex: 1; font-family: "Courier New", monospace; }
        .autosave-tip { font-size: 11px; color: #6e7681; font-family: "Courier New", monospace; }
        .autosave-tip.saved { color: #3fb950; }
        .btn-draft {
            background: transparent; border: 1px solid #30363d; color: #8b949e;
            padding: 6px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; transition: .15s; font-family: inherit;
        }
        .btn-draft:hover { border-color: #8b949e; color: #e6edf3; }
        .btn-publish {
            background: #3fb950; border: none; color: #fff;
            padding: 7px 20px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 700; transition: .2s; font-family: inherit;
        }
        .btn-publish:hover { background: #2ea043; box-shadow: 0 0 12px rgba(63,185,80,.3); }
        .btn-publish:disabled { background: #1c2128; color: #6e7681; cursor: not-allowed; }

        .pub-layout { max-width: 960px; margin: 20px auto; padding: 0 16px; display: flex; gap: 18px; align-items: flex-start; }
        .pub-main { flex: 1; min-width: 0; }

        .editor-card { background: #161b22; border: 1px solid #30363d; border-radius: 6px; overflow: hidden; }
        .title-input {
            width: 100%; border: none; outline: none;
            font-size: 20px; font-weight: 700; color: #e6edf3;
            padding: 20px 22px 14px; font-family: inherit; background: transparent;
        }
        .title-input::placeholder { color: #6e7681; }
        .editor-divider { height: 1px; background: #30363d; }
        #editor-toolbar { border-bottom: 1px solid #30363d; padding: 0 10px; background: #0d1117; }
        #editor-text-area { min-height: 360px; padding: 4px 22px; background: #161b22; color: #c9d1d9; }

        .attach-section { border-top: 1px solid #30363d; padding: 14px 22px; }
        .attach-header { font-size: 12px; color: #6e7681; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; font-family: "Courier New", monospace; }
        .attach-dropzone {
            border: 1px dashed #30363d; border-radius: 4px; padding: 18px;
            text-align: center; color: #6e7681; font-size: 13px; cursor: pointer; transition: .2s; position: relative;
        }
        .attach-dropzone:hover, .attach-dropzone.drag-over { border-color: #3fb950; color: #3fb950; background: rgba(63,185,80,.04); }
        .attach-dropzone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .attach-list { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
        .attach-item { display: flex; align-items: center; gap: 10px; background: #1c2128; border: 1px solid #30363d; border-radius: 4px; padding: 7px 12px; font-size: 13px; }
        .attach-icon { font-size: 16px; flex-shrink: 0; }
        .attach-name { flex: 1; color: #8b949e; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .attach-size { color: #6e7681; font-size: 11px; flex-shrink: 0; font-family: "Courier New", monospace; }
        .attach-del  { color: #f85149; cursor: pointer; font-size: 15px; flex-shrink: 0; opacity: .7; }
        .attach-del:hover { opacity: 1; }
        .attach-progress { color: #3fb950; font-size: 11px; font-family: "Courier New", monospace; }

        /* 分区选择 */
        .cat-select-card { background: #161b22; border: 1px solid #30363d; border-radius: 6px; padding: 16px 20px; margin-top: 14px; }
        .cat-select-title { font-size: 11px; font-weight: 700; color: #6e7681; margin-bottom: 10px; letter-spacing: 1.5px; text-transform: uppercase; font-family: "Courier New", monospace; }
        .cat-options { display: flex; flex-wrap: wrap; gap: 8px; }
        .cat-option { display: none; }
        .cat-option + label {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 14px; border-radius: 20px; cursor: pointer;
            font-size: 13px; border: 1px solid #30363d; color: #8b949e;
            background: #0d1117; transition: .15s; user-select: none;
        }
        .cat-option:checked + label { color: #e6edf3; border-color: var(--cat-color, #3fb950); background: rgba(63,185,80,.1); }
        .cat-option + label:hover { border-color: #6e7681; color: #e6edf3; }

        .settings-card { background: #161b22; border: 1px solid #30363d; border-radius: 6px; padding: 18px 20px; margin-top: 14px; }
        .settings-title { font-size: 11px; font-weight: 700; color: #6e7681; margin-bottom: 12px; letter-spacing: 1.5px; text-transform: uppercase; font-family: "Courier New", monospace; }
        .setting-row { display: flex; align-items: flex-start; gap: 12px; padding: 9px 0; border-bottom: 1px solid #21262d; }
        .setting-row:last-child { border-bottom: none; padding-bottom: 0; }
        .setting-row input[type=checkbox] { width: 15px; height: 15px; margin-top: 2px; cursor: pointer; accent-color: #3fb950; flex-shrink: 0; }
        .setting-label { font-size: 13px; color: #e6edf3; }
        .setting-desc  { font-size: 12px; color: #6e7681; margin-top: 3px; }

        .drafts-sidebar { width: 210px; flex-shrink: 0; }
        .drafts-card { background: #161b22; border: 1px solid #30363d; border-radius: 6px; overflow: hidden; }
        .drafts-card-title { font-size: 11px; font-weight: 700; color: #6e7681; padding: 12px 14px; border-bottom: 1px solid #30363d; letter-spacing: 1.5px; text-transform: uppercase; font-family: "Courier New", monospace; }
        .draft-link { display: block; padding: 9px 14px; text-decoration: none; color: #8b949e; font-size: 13px; border-bottom: 1px solid #21262d; transition: .15s; }
        .draft-link:last-child { border-bottom: none; }
        .draft-link:hover { background: #1c2128; color: #3fb950; }
        .draft-link-title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .draft-link-time  { font-size: 11px; color: #6e7681; margin-top: 2px; font-family: "Courier New", monospace; }
        .no-drafts { padding: 18px 14px; text-align: center; color: #6e7681; font-size: 12px; font-family: "Courier New", monospace; }

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
        <!-- 分区选择 -->
        <div class="cat-select-card">
            <div class="cat-select-title">选择分区</div>
            <div class="cat-options">
                <input type="radio" class="cat-option" name="category_id" id="cat_0" value="0" checked>
                <label for="cat_0">不限分区</label>
                <?php foreach ($categories as $c): ?>
                <input type="radio" class="cat-option" name="category_id"
                       id="cat_<?= $c['id'] ?>" value="<?= $c['id'] ?>"
                       style="--cat-color:<?= htmlspecialchars($c['color']) ?>"
                       <?= ($draft && (int)$draft['category_id'] === (int)$c['id']) ? 'checked' : '' ?>>
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
                // 当前正在编辑此草稿
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

    const catEl = document.querySelector('input[name="category_id"]:checked');
    if (catEl) fd.append('category_id', catEl.value);

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
