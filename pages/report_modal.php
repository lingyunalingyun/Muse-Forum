<?php
/**
 * 举报弹窗组件（被 post.php 和 profile.php include）
 * 通过 openReportModal(type, targetId) 触发
 * type: 'post' | 'user'
 */
?>
<!-- 举报弹窗 -->
<div id="report-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9500;align-items:center;justify-content:center;">
<div style="background:#161b22;border:1px solid #30363d;border-radius:8px;padding:24px 26px;width:420px;max-width:94vw;max-height:88vh;overflow-y:auto;font-family:'Microsoft YaHei',sans-serif;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <p style="margin:0;font-size:15px;font-weight:700;color:#f85149;">举报</p>
        <button onclick="closeReportModal()"
                style="background:none;border:none;color:#6e7681;font-size:18px;cursor:pointer;padding:0;line-height:1;">✕</button>
    </div>

    <p style="font-size:12px;color:#8b949e;margin:0 0 14px;">请选择举报原因（必选）</p>

    <div id="report-reasons-post" style="display:none;">
        <?php
        $post_reasons = [
            '侵犯企业权益', '侵犯个人知识产权', '侵犯个人肖像权',
            '色情低俗', '违规广告引流', '涉政敏感',
            '引战网络不友善', '传播谣言', '涉嫌诈骗',
            '引人不适', '涉及未成年不良信息', '其他',
        ];
        foreach ($post_reasons as $r): ?>
        <label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:5px;cursor:pointer;transition:background .12s;"
               onmouseover="this.style.background='#21262d'" onmouseout="this.style.background='transparent'">
            <input type="radio" name="report_reason" value="<?= htmlspecialchars($r) ?>"
                   style="accent-color:#f85149;flex-shrink:0;">
            <span style="font-size:13px;color:#c9d1d9;"><?= htmlspecialchars($r) ?></span>
        </label>
        <?php endforeach; ?>
    </div>

    <div id="report-reasons-user" style="display:none;">
        <?php
        $user_reasons = [
            '个人信息违规', '色情低俗', '不实信息',
            '人身攻击', '赌博诈骗', '违规引流', '其他',
        ];
        foreach ($user_reasons as $r): ?>
        <label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:5px;cursor:pointer;transition:background .12s;"
               onmouseover="this.style.background='#21262d'" onmouseout="this.style.background='transparent'">
            <input type="radio" name="report_reason" value="<?= htmlspecialchars($r) ?>"
                   style="accent-color:#f85149;flex-shrink:0;">
            <span style="font-size:13px;color:#c9d1d9;"><?= htmlspecialchars($r) ?></span>
        </label>
        <?php endforeach; ?>
    </div>

    <div style="margin-top:14px;">
        <label style="font-size:12px;color:#6e7681;display:block;margin-bottom:5px;">补充说明（选填）</label>
        <textarea id="report-detail" maxlength="200" placeholder="可以补充描述问题详情…"
                  style="width:100%;box-sizing:border-box;background:#0d1117;border:1px solid #30363d;color:#c9d1d9;
                         padding:8px 10px;border-radius:4px;font-size:13px;font-family:inherit;resize:vertical;
                         min-height:70px;outline:none;"></textarea>
    </div>

    <div id="report-msg" style="font-size:12px;margin-top:8px;min-height:16px;"></div>

    <div style="display:flex;gap:10px;margin-top:14px;">
        <button onclick="submitReport()"
                style="flex:1;padding:9px;border-radius:4px;border:none;cursor:pointer;font-size:13px;font-weight:700;
                       background:#f85149;color:#fff;font-family:inherit;transition:opacity .15s;"
                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            提交举报
        </button>
        <button onclick="closeReportModal()"
                style="flex:1;padding:9px;border-radius:4px;border:1px solid #30363d;background:transparent;
                       color:#8b949e;cursor:pointer;font-size:13px;font-family:inherit;">
            取消
        </button>
    </div>
</div>
</div>

<script>
let _reportType = '', _reportTargetId = 0;

function openReportModal(type, targetId) {
    _reportType = type;
    _reportTargetId = targetId;
    document.getElementById('report-reasons-post').style.display = type === 'post' ? 'block' : 'none';
    document.getElementById('report-reasons-user').style.display = type === 'user' ? 'block' : 'none';
    document.querySelectorAll('input[name="report_reason"]').forEach(r => r.checked = false);
    document.getElementById('report-detail').value = '';
    document.getElementById('report-msg').textContent = '';
    document.getElementById('report-msg').style.color = '';
    document.getElementById('report-modal').style.display = 'flex';
}
function closeReportModal() {
    document.getElementById('report-modal').style.display = 'none';
}
function submitReport() {
    const reason = document.querySelector('input[name="report_reason"]:checked')?.value;
    if (!reason) {
        const msg = document.getElementById('report-msg');
        msg.textContent = '请选择举报原因';
        msg.style.color = '#f85149';
        return;
    }
    const detail = document.getElementById('report-detail').value.trim();
    const fd = new FormData();
    fd.append('type', _reportType);
    fd.append('target_id', _reportTargetId);
    fd.append('reason', reason);
    fd.append('detail', detail);
    fetch('../actions/report_submit.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            const msg = document.getElementById('report-msg');
            msg.textContent = d.msg || (d.ok ? '举报已提交' : '提交失败');
            msg.style.color = d.ok ? '#3fb950' : '#f85149';
            if (d.ok) setTimeout(closeReportModal, 1200);
        });
}
document.getElementById('report-modal').addEventListener('click', function(e) {
    if (e.target === this) closeReportModal();
});
</script>
