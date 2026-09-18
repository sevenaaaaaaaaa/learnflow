(function () {
  'use strict';
  if (document.getElementById('lf-ai-fab')) return;
  var BASE = window.LF_BASE || '';
  function token() { var el = document.querySelector('input[name="_token"]'); return (window.LF_CSRF || (el ? el.value : '')) || ''; }

  var fab = document.createElement('button');
  fab.id = 'lf-ai-fab';
  fab.type = 'button';
  fab.textContent = 'AI';
  fab.style.cssText = 'position:fixed;right:22px;bottom:22px;z-index:80;width:52px;height:52px;border-radius:999px;border:0;background:#2f6bff;color:#fff;font-weight:700;font-size:15px;cursor:pointer;box-shadow:0 10px 28px -10px rgba(47,107,255,.8)';
  document.body.appendChild(fab);

  var panel = document.createElement('div');
  panel.style.cssText = 'position:fixed;right:22px;bottom:86px;z-index:80;width:360px;max-width:92vw;background:var(--surface-strong,#fff);border:1px solid var(--border,#ddd);border-radius:16px;box-shadow:var(--shadow,0 20px 50px -20px rgba(0,0,0,.4));padding:14px;display:none';
  panel.innerHTML = '<div style="font-weight:700;margin-bottom:8px">工作台助手</div>'
    + '<textarea id="lf-ai-q" placeholder="问点什么，或让我写文案 / 出题 / 分析…" style="width:100%;min-height:70px;border:1px solid var(--border,#ddd);border-radius:10px;padding:10px;box-sizing:border-box;background:transparent;color:inherit"></textarea>'
    + '<div style="display:flex;gap:8px;margin-top:8px"><button id="lf-ai-send" style="flex:1;height:38px;border-radius:10px;border:0;background:#2f6bff;color:#fff;font-weight:600;cursor:pointer">发送</button><button id="lf-ai-close" style="width:38px;height:38px;border-radius:10px;border:1px solid var(--border,#ddd);background:transparent;cursor:pointer">×</button></div>'
    + '<div id="lf-ai-a" style="margin-top:10px;font-size:13.5px;line-height:1.8;white-space:pre-wrap;max-height:320px;overflow:auto"></div>';
  document.body.appendChild(panel);

  fab.addEventListener('click', function () { panel.style.display = panel.style.display === 'block' ? 'none' : 'block'; });
  document.getElementById('lf-ai-close').addEventListener('click', function () { panel.style.display = 'none'; });
  document.getElementById('lf-ai-send').addEventListener('click', function () {
    var q = document.getElementById('lf-ai-q').value.trim();
    var a = document.getElementById('lf-ai-a');
    if (!q) return;
    a.textContent = '思考中…';
    var fd = new FormData();
    fd.append('_token', token());
    fd.append('action', 'assistant');
    fd.append('prompt', q);
    fetch(BASE + '/api/ai.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
      a.textContent = d.ok ? d.content : (d.error || 'AI 失败');
    }).catch(function () { a.textContent = '网络错误'; });
  });
})();
