<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('cli only');
}

$ffmpeg = null;
$probe = @shell_exec('command -v ffmpeg 2>/dev/null');
if (is_string($probe) && trim($probe) !== '') $ffmpeg = trim($probe);
if ($ffmpeg === null) {
    echo "ffmpeg 未安装：跳过视频转码/封面生成。\n";
    echo "安装后可自动为 upload: 视频生成封面与压缩 mp4：宝塔 → 软件商店安装 ffmpeg，或 apt/yum install ffmpeg。\n";
    exit(0);
}

$posterCount = 0;
$transcodeCount = 0;
$limit = (int)($argv[1] ?? 10);
$done = 0;

foreach (course_all() as $course) {
    if ($done >= $limit) break;
    $cid = (string)$course['id'];
    $changed = false;
    foreach ((array)($course['chapters'] ?? []) as $ci => $ch) {
        foreach ((array)($ch['lessons'] ?? []) as $li => $l) {
            if ($done >= $limit) break;
            if (($l['type'] ?? '') !== 'video') continue;
            $video = (string)($l['video'] ?? '');
            if (!str_starts_with($video, 'upload:')) continue;
            $rel = substr($video, 7);
            $path = lf_file_path($rel);
            if ($path === null) continue;

            if (empty($l['poster'])) {
                $outRel = dirname($rel) . '/' . pathinfo($rel, PATHINFO_FILENAME) . '-poster.jpg';
                $outPath = LF_UPLOAD_DIR . '/' . $outRel;
                @shell_exec($ffmpeg . ' -y -ss 3 -i ' . escapeshellarg($path) . ' -frames:v 1 -q:v 3 ' . escapeshellarg($outPath) . ' 2>/dev/null');
                if (is_file($outPath)) {
                    $media = media_add(['rel' => $outRel, 'name' => basename($outRel), 'ext' => 'jpg', 'size' => filesize($outPath)], ['scope' => 'poster']);
                    $course['chapters'][$ci]['lessons'][$li]['poster'] = media_public_url((string)$media['id']);
                    $posterCount++;
                    $changed = true;
                }
            }
            $done++;
        }
    }
    if ($changed) course_save(course_normalize($course));
}

echo "transcode done: poster={$posterCount} processed={$done}\n";
