<?php

function embedding_config(): array
{
    return array_merge(['enabled' => false, 'base_url' => '', 'api_key' => '', 'model' => ''], (array)(lf_setting_get('embedding') ?: []));
}

function embedding_enabled(): bool
{
    $c = embedding_config();
    return !empty($c['enabled']) && $c['api_key'] !== '' && $c['base_url'] !== '' && $c['model'] !== '';
}

function embedding_embeddings_file(): string
{
    return LF_DATA_DIR . '/embeddings.json';
}

function embedding_embed(array $texts): ?array
{
    if (!embedding_enabled() || !$texts) return null;
    $cfg = embedding_config();
    $url = rtrim((string)$cfg['base_url'], '/') . '/embeddings';
    require_once __DIR__ . '/Events.php';
    $res = lf_post_json($url, ['model' => $cfg['model'], 'input' => array_values($texts)], '', 30, ['Authorization: Bearer ' . $cfg['api_key']]);
    if (empty($res['ok'])) return null;
    $data = json_decode((string)$res['body'], true);
    $out = [];
    foreach ((array)($data['data'] ?? []) as $row) {
        if (isset($row['embedding']) && is_array($row['embedding'])) $out[] = $row['embedding'];
    }
    return count($out) === count($texts) ? $out : null;
}

function embedding_index_course(array $course): void
{
    if (!embedding_enabled()) return;
    $courseId = (string)($course['id'] ?? '');
    if ($courseId === '') return;
    $all = json_read(embedding_embeddings_file());
    $existing = (array)($all[$courseId] ?? []);
    $need = [];
    $lessons = course_lessons($course);
    foreach ($lessons as $l) {
        $text = (string)($l['title'] ?? '') . "\n" . strip_tags((string)($l['content'] ?? ''));
        $hash = md5($text);
        $lid = (string)($l['id'] ?? '');
        if ($lid === '') continue;
        if (($existing[$lid]['hash'] ?? '') !== $hash) $need[$lid] = $text;
    }
    if ($need) {
        $vecs = embedding_embed(array_values($need));
        if ($vecs !== null) {
            $i = 0;
            foreach ($need as $lid => $text) {
                $existing[$lid] = ['hash' => md5($text), 'vec' => $vecs[$i] ?? []];
                $i++;
            }
            json_update(embedding_embeddings_file(), function (array $all) use ($courseId, $existing) {
                $all[$courseId] = $existing;
                return $all;
            });
        }
    }
}

function embedding_cosine(array $a, array $b): float
{
    $n = min(count($a), count($b));
    if ($n === 0) return 0.0;
    $dot = 0.0; $na = 0.0; $nb = 0.0;
    for ($i = 0; $i < $n; $i++) { $dot += $a[$i] * $b[$i]; $na += $a[$i] * $a[$i]; $nb += $b[$i] * $b[$i]; }
    if ($na <= 0 || $nb <= 0) return 0.0;
    return $dot / (sqrt($na) * sqrt($nb));
}

function embedding_search(array $course, string $question, int $top = 5): ?array
{
    if (!embedding_enabled()) return null;
    embedding_index_course($course);
    $qv = embedding_embed([$question]);
    if ($qv === null) return null;
    $qvec = $qv[0];
    $all = json_read(embedding_embeddings_file());
    $vecs = (array)($all[(string)$course['id']] ?? []);
    $scored = [];
    foreach (course_lessons($course) as $l) {
        $lid = (string)($l['id'] ?? '');
        if (!isset($vecs[$lid]['vec'])) continue;
        $score = embedding_cosine($qvec, (array)$vecs[$lid]['vec']);
        if ($score > 0) $scored[] = ['lesson' => $l, 'score' => $score];
    }
    if (!$scored) return null;
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    $scored = array_slice($scored, 0, $top);
    $sources = [];
    $contexts = [];
    foreach ($scored as $row) {
        $l = $row['lesson'];
        $content = mb_substr(trim(strip_tags((string)($l['content'] ?? ''))), 0, 900);
        $sources[] = (string)($l['title'] ?? '');
        $contexts[] = '【课时：' . (string)($l['title'] ?? '') . '】' . ($l['chapter_title'] ?? '') . "\n" . ($content !== '' ? $content : '（视频/测验课时，无文字内容）');
    }
    return ['sources' => $sources, 'contexts' => $contexts];
}
