<?php
/**
 * 部署/更新 LearnFlow 文件 Worker（learnflow-files）：
 *   路由 nownexts.com/lf-files/* → R2 桶 nownexts-static（键前缀 lf/）
 *   PUT/DELETE 需 X-LF-Token（sha256(token) 哈希写入 Worker，不落明文）
 *   GET 公开（媒体库图片等公有内容直接边缘分发；受控内容仍走 file.php 代理）
 * 用法: php bin/cf-deploy-files.php
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('cli only'); }

$token  = (string)(lf_setting_get('cf_token') ?: '');
$workerName = 'learnflow-files';
$routePattern = 'nownexts.com/lf-files/*';

if ($token === '') {
    fwrite(STDERR, "缺少 cf_token（bin/set-cf-token.php 或后台设置）\n");
    exit(1);
}

$secret = (string)(lf_setting_get('r2_token') ?: '');
if ($secret === '') {
    $secret = bin2hex(random_bytes(32));
    lf_setting_set('r2_token', $secret);
    echo "generated r2_token (store in secrets-local.md)\n";
}
$secretHash = hash('sha256', $secret);

$js = <<<JS
const PREFIX = 'lf/';
const TOKEN_HASH = '{$secretHash}';
async function sha256hex(s) {
  const d = new TextEncoder().encode(s);
  const h = await crypto.subtle.digest('SHA-256', d);
  return [...new Uint8Array(h)].map(b => b.toString(16).padStart(2, '0')).join('');
}
function parseRange(range) {
  const m = /bytes=(\\d*)-(\\d*)/.exec(range);
  return { offset: m[1] === '' ? undefined : parseInt(m[1], 10), length: m[2] === '' ? undefined : parseInt(m[2], 10) - (m[1] === '' ? 0 : parseInt(m[1], 10)) + 1 };
}
export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const path = url.pathname.replace(/^\\/lf-files\\/?/, '');
    if (!path) return new Response('Not Found: lf/*', { status: 404 });
    const key = PREFIX + decodeURIComponent(path);

    if (request.method === 'PUT' || request.method === 'DELETE') {
      const tok = request.headers.get('x-lf-token') || '';
      const h = await sha256hex(tok);
      if (h !== TOKEN_HASH) return new Response('Forbidden', { status: 403 });
    }
    if (request.method === 'PUT') {
      const ct = request.headers.get('content-type') || 'application/octet-stream';
      await env.R2_FILES.put(key, request.body, { httpMetadata: { contentType: ct } });
      return new Response(JSON.stringify({ ok: true, key: key }), { status: 200, headers: { 'content-type': 'application/json' } });
    }
    if (request.method === 'DELETE') {
      await env.R2_FILES.delete(key);
      return new Response(JSON.stringify({ ok: true, key: key }), { status: 200, headers: { 'content-type': 'application/json' } });
    }

    const range = request.headers.get('Range');
    let object = null;
    try {
      object = await env.R2_FILES.get(key, { range: range ? parseRange(range) : undefined });
    } catch (err) {
      return new Response('Storage error: ' + err.message, { status: 500 });
    }
    if (!object) return new Response('Not Found', { status: 404 });

    const headers = new Headers();
    object.writeHttpMetadata(headers);
    headers.set('etag', object.httpEtag);
    headers.set('accept-ranges', 'bytes');
    headers.set('access-control-allow-origin', '*');
    headers.set('cache-control', 'public, max-age=3600');
    if (request.method === 'HEAD') {
      headers.set('Content-Length', String(object.size));
      return new Response(null, { status: 200, headers });
    }
    if (range && object.range) {
      headers.set('Content-Length', String(object.range.length));
      headers.set('Content-Range', 'bytes ' + object.range.offset + '-' + (object.range.offset + object.range.length - 1) + '/' + object.size);
      return new Response(object.body, { status: 206, headers });
    }
    headers.set('Content-Length', String(object.size));
    return new Response(object.body, { status: 200, headers });
  },
};
JS;

$account = '00d02a54a3c0f7a3f6c3fc75068e29c5';
$bucket  = 'nownexts-static';
$zoneId  = '8135597542c2723a06a91a7e14a6e747';
$api = "https://api.cloudflare.com/client/v4";

function cf_put(string $url, array $headers, $body, array $curlOpts = []): array
{
    $ch = curl_init($url);
    $h = $headers;
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => $h,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
    ]);
    foreach ($curlOpts as $k => $v) curl_setopt($ch, $k, $v);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $code, 'body' => (string)$resp];
}

// 1) 上传 worker 脚本（multipart + metadata 含 r2_bucket 绑定）
$tmp = tempnam(sys_get_temp_dir(), 'js');
file_put_contents($tmp, $js);
$meta = json_encode([
    'main_module' => 'worker.js',
    'compatibility_date' => '2024-09-01',
    'bindings' => [['type' => 'r2_bucket', 'name' => 'R2_FILES', 'bucket_name' => $bucket]],
]);
$cfile = curl_file_create($tmp, 'application/javascript+module', 'worker.js');
$cmeta = curl_file_create('data://text/plain;base64,' . base64_encode($meta), 'application/json', 'metadata.json');

$ch = curl_init("$api/accounts/{$account}/workers/scripts/{$workerName}");
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Expect:'],
    CURLOPT_POSTFIELDS => ['worker.js' => $cfile, 'metadata' => $cmeta],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
]);
$resp = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
unlink($tmp);
echo "upload script: HTTP {$code}\n";
if ($code !== 200) { echo mb_substr((string)$resp, 0, 600), "\n"; exit(1); }

// 2) 绑定 zone 路由
$route = [ 'pattern' => $routePattern, 'script' => $workerName ];
$r = cf_put("$api/zones/{$zoneId}/workers/routes", ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], json_encode($route));
echo "bind route: HTTP {$r['code']}\n";
if ($r['code'] !== 200) { echo mb_substr($r['body'], 0, 400), "\n"; exit(1); }

echo "done: https://nownexts.com/lf-files/<key> (PUT/DELETE 需 X-LF-Token)\n";
