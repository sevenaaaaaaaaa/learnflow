<?php
require_once __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/javascript; charset=utf-8');
header('Service-Worker-Allowed: ' . lf_url('/'));
$assets = [
    lf_url('/assets/tokens.css'),
    lf_url('/assets/modules.css'),
    lf_url('/assets/app.css'),
    lf_url('/assets/app.js'),
    lf_url('/assets/fonts/fonts.css'),
    lf_url('/assets/icon.svg'),
];
$fallback = lf_url('/courses');
?>
const CACHE = 'lf-shell-v1';
const ASSETS = <?= json_encode($assets) ?>;
const FALLBACK = <?= json_encode($fallback) ?>;

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS).catch(() => {})).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(req).then((r) => r || caches.match(FALLBACK))));
    return;
  }
  e.respondWith(caches.match(req).then((cached) => cached || fetch(req).then((resp) => {
    if (resp.ok && (url.pathname.includes('/assets/') || url.pathname.endsWith('.woff2'))) {
      const copy = resp.clone();
      caches.open(CACHE).then((c) => c.put(req, copy));
    }
    return resp;
  }).catch(() => cached)));
});
