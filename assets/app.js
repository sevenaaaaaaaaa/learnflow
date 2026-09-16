(function () {
  'use strict';

  function applyTheme(theme) {
    document.documentElement.dataset.theme = theme;
    try { localStorage.setItem('learnflow-theme', JSON.stringify({ theme: theme })); } catch (e) {}
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-lf-theme]');
    if (!btn) return;
    var cur = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    applyTheme(cur);
  });

  var player = document.querySelector('[data-lf-player]');
  if (player) {
    var video = player.querySelector('video');
    var endpoint = player.getAttribute('data-endpoint');
    var courseId = player.getAttribute('data-course');
    var lessonId = player.getAttribute('data-lesson');
    var useBeacon = !!(video && navigator.sendBeacon);
    var lastSent = Date.now();

    function payload(extra) {
      return Object.assign({
        course_id: courseId,
        lesson_id: lessonId,
        position: video ? Math.floor(video.currentTime || 0) : 0,
        duration: video ? Math.floor(video.duration || 0) : 0,
        delta: Math.max(0, Math.round((Date.now() - lastSent) / 1000))
      }, extra || {});
    }

    function send(extra) {
      lastSent = Date.now();
      var body = JSON.stringify(payload(extra));
      if (useBeacon) {
        navigator.sendBeacon(endpoint, new Blob([body], { type: 'application/json' }));
      } else {
        fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true });
      }
    }

    if (video) {
      var resume = parseInt(player.getAttribute('data-resume') || '0', 10);
      if (resume > 2) {
        video.addEventListener('loadedmetadata', function () { try { video.currentTime = resume; } catch (e) {} }, { once: true });
      }
      var timer = setInterval(function () { if (!video.paused) send({}); }, 15000);
      video.addEventListener('ended', function () { send({ done: true }); });
      window.addEventListener('pagehide', function () { send({}); clearInterval(timer); });
    }

    var doneBtn = player.querySelector('[data-lf-done]');
    if (doneBtn) {
      doneBtn.addEventListener('click', function () {
        var body = JSON.stringify(payload({ done: true }));
        fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body }).then(function () {
          location.reload();
        });
      });
    }
  }

  var copyBtns = document.querySelectorAll('[data-lf-copy]');
  copyBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-lf-copy');
      if (navigator.clipboard) navigator.clipboard.writeText(text);
      var old = btn.textContent;
      btn.textContent = '已复制';
      setTimeout(function () { btn.textContent = old; }, 1600);
    });
  });
})();
