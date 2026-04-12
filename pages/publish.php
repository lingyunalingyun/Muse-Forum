<?php
// 1. 开启 session 以获取登录状态
session_start();

// 2. 权限检查：确保用户已登录
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>发布精彩内容</title>
    <link href="https://unpkg.com/@wangeditor/editor@latest/dist/css/style.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; font-family: "Microsoft YaHei", sans-serif; padding: 20px; }
        .editor-container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #28a745; padding-bottom: 10px; color: #333; }
        #editor-toolbar { border-bottom: 1px solid #eee; }
        #editor-text-area { height: 400px; }
        .pub-btn { background: #28a745; color: white; border: none; padding: 12px 30px; border-radius: 6px; cursor: pointer; float: right; margin-top: 15px; font-weight: bold; transition: 0.3s; }
        .pub-btn:hover { background: #218838; }
        .user-tip { font-size: 14px; color: #666; margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="editor-container">
    <h2>✍️ 创作你的帖子</h2>
    <div class="user-tip">当前身份：<strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></div>

    <input type="text" id="post-title" placeholder="请输入帖子标题（必填）"
           style="width:100%; padding:12px; font-size:16px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box; margin-bottom:15px; outline:none;"
           onfocus="this.style.borderColor='#28a745'" onblur="this.style.borderColor='#ddd'">
    <div id="editor-toolbar"></div>
    <div id="editor-text-area"></div>
    <button class="pub-btn" onclick="submitPost()">发布帖子</button>
</div>

<script src="https://unpkg.com/@wangeditor/editor@latest/dist/index.js"></script>
<script>
    const { createEditor, createToolbar } = window.wangEditor;

    const editorConfig = {
        placeholder: '在此输入内容并支持拖拽上传图片...',
        onChange(editor) {
            const html = editor.getHtml();
        }
    };

    const editor = createEditor({
        selector: '#editor-text-area',
        config: editorConfig,
        mode: 'simple'
    });

    const toolbar = createToolbar({
        editor,
        selector: '#editor-toolbar',
        mode: 'simple'
    });

    function submitPost() {
        const title = document.getElementById('post-title').value.trim();
        if(!title) return alert("标题不能为空");
        const content = editor.getHtml();
        if(editor.isEmpty()) return alert("内容不能为空");

        let formData = new FormData();
        formData.append('title', title);
        formData.append('content', content);

        fetch('../actions/save.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(data => {
            alert(data);
            if(data.includes("成功")) {
                window.location.href = '../index.php';
            }
        })
        .catch(err => {
            console.error('上传失败:', err);
            alert("网络错误，请稍后再试");
        });
    }
</script>
</body>
</html>
