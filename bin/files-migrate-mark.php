<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('cli only'); }

// 给所有已上 R2 的 media 行补 on_r2=true（自动探测，无 R2 副本则保持原状并打上 r2 丢失）
json_update(LF_DATA_DIR . '/media.json', function (array $all) {
    foreach ($all as $i => $m) {
        $rel = (string)($m['rel'] ?? '');
        if ($rel === '') continue;
        if (r2_get($rel) !== '') $all[$i]['on_r2'] = true;
        else $all[$i]['r2_note'] = 'no-copy-on-r2';
    }
    return $all;
});
// 同样处理课程课时里的本地附件：不涉及此处；附件通过 file.php 走本地
echo "media.json on_r2 markers updated\n";
