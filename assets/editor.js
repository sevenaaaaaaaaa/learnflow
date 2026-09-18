(function () {
  'use strict';

  function token() {
    var el = document.querySelector('input[name="_token"]');
    return (window.LF_CSRF || (el ? el.value : '')) || '';
  }
  function api(path) { return (window.LF_BASE || '') + path; }

  function init(ta) {
    if (ta.dataset.lfRich === '1') return;
    ta.dataset.lfRich = '1';

    var wrap = document.createElement('div');
    wrap.className = 'lf-editor';
    var bar = document.createElement('div');
    bar.className = 'lf-editor-bar';
    var area = document.createElement('div');
    area.className = 'lf-editor-area';
    area.contentEditable = 'true';
    area.innerHTML = ta.value || '';
    var file = document.createElement('input');
    file.type = 'file';
    file.accept = 'image/*';
    file.style.display = 'none';

    ta.parentNode.insertBefore(wrap, ta);
    wrap.appendChild(bar);
    wrap.appendChild(area);
    wrap.appendChild(file);
    wrap.appendChild(ta);
    ta.style.display = 'none';

    function sync() { ta.value = area.innerHTML; }
    function exec(cmd, val) { try { document.execCommand(cmd, false, val || null); } catch (e) {} sync(); }

    var cmds = [
      ['B', 'bold'], ['I', 'italic'], ['H2', 'formatBlock', '<h2>'], ['H3', 'formatBlock', '<h3>'],
      ['•', 'insertUnorderedList'], ['1.', 'insertOrderedList'],
      ['❝', 'formatBlock', '<blockquote>'], ['</>', 'formatBlock', '<pre>'], ['—', 'inserthorizontalrule']
    ];
    cmds.forEach(function (c) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'lf-ed-btn'; b.textContent = c[0];
      b.addEventListener('click', function () { area.focus(); exec(c[1], c[2]); });
      bar.appendChild(b);
    });

    var link = document.createElement('button');
    link.type = 'button'; link.className = 'lf-ed-btn'; link.textContent = '🔗';
    link.addEventListener('click', function () {
      var u = prompt('链接地址（https://…）');
      if (u) { area.focus(); exec('createLink', u); }
    });
    bar.appendChild(link);

    var img = document.createElement('button');
    img.type = 'button'; img.className = 'lf-ed-btn'; img.textContent = '🖼';
    img.title = '插入图片（上传到素材库）';
    img.addEventListener('click', function () { file.click(); });
    bar.appendChild(img);

    var md = document.createElement('button');
    md.type = 'button'; md.className = 'lf-ed-btn'; md.textContent = 'MD';
    md.title = '粘贴 Markdown 导入';
    md.addEventListener('click', function () {
      var t = prompt('粘贴 Markdown 内容（将转换为富文本）');
      if (!t) return;
      var fd = new FormData();
      fd.append('_token', token());
      fd.append('text', t);
      fetch(api('/api/markdown.php'), { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
        if (d.ok) { area.innerHTML = d.html; sync(); }
      });
    });
    bar.appendChild(md);

    file.addEventListener('change', function () {
      if (!file.files || !file.files[0]) return;
      var fd = new FormData();
      fd.append('_token', token());
      fd.append('scope', 'library');
      fd.append('file', file.files[0]);
      fetch(api('/api/upload.php'), { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
        if (!d.ok) { alert(d.error || '上传失败'); return; }
        area.focus();
        document.execCommand('insertHTML', false, '<img src="' + d.url + '" alt="">');
        sync();
        file.value = '';
      });
    });

    area.addEventListener('input', sync);
    area.addEventListener('blur', sync);
    var form = ta.closest('form');
    if (form) form.addEventListener('submit', sync);
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('textarea.lf-rich'), init);
  }
  window.lfInitEditors = function (root) {
    Array.prototype.forEach.call((root || document).querySelectorAll('textarea.lf-rich'), init);
  };
  if (document.readyState !== 'loading') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
