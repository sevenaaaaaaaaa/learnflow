<?php

function lf_langs(): array
{
    return ['zh', 'en'];
}

function lf_lang(): string
{
    static $lang = null;
    if ($lang !== null) return $lang;
    $langs = lf_langs();

    $path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
    $base = function_exists('lf_base_path') ? lf_base_path() : '';
    $rel = ($base !== '' && str_starts_with($path, $base)) ? substr($path, strlen($base)) : $path;
    $prefixLang = preg_match('#^/en(/|$)#', $rel) ? 'en' : '';

    $candidate = (string)($_GET['lang'] ?? ($prefixLang !== '' ? $prefixLang : ($_COOKIE['lf_lang'] ?? 'zh')));
    if (!in_array($candidate, $langs, true)) $candidate = 'zh';
    $shouldSet = (isset($_GET['lang']) || $prefixLang !== '') && PHP_SAPI !== 'cli' && !headers_sent();
    if ($shouldSet) {
        setcookie('lf_lang', $candidate, ['expires' => time() + 31536000, 'path' => ($base !== '' ? $base : '') . '/', 'samesite' => 'Lax']);
    }
    return $lang = $candidate;
}

function lf_t(string $zh, string $en = ''): string
{
    if (lf_lang() === 'en' && $en !== '') return $en;
    return $zh;
}

function lf_localize_course(array $course, ?string $lang = null): array
{
    $lang = $lang ?? lf_lang();
    if ($lang === 'zh' || empty($course['i18n'][$lang])) return $course;
    $i18n = (array)$course['i18n'][$lang];
    foreach (['title', 'subtitle', 'summary'] as $field) {
        if (!empty($i18n[$field])) $course[$field] = $i18n[$field];
    }
    $lessonMap = (array)($i18n['lessons'] ?? []);
    if ($lessonMap) {
        foreach ((array)($course['chapters'] ?? []) as $ci => $ch) {
            foreach ((array)($ch['lessons'] ?? []) as $li => $l) {
                $id = (string)($l['id'] ?? '');
                if ($id === '') continue;
                if (!empty($lessonMap[$id]['title'])) $course['chapters'][$ci]['lessons'][$li]['title'] = $lessonMap[$id]['title'];
                if (!empty($lessonMap[$id]['content'])) $course['chapters'][$ci]['lessons'][$li]['content'] = $lessonMap[$id]['content'];
            }
        }
    }
    return $course;
}

function lf_localize_lesson(array $lesson, array $course): array
{
    $lang = lf_lang();
    $id = (string)($lesson['id'] ?? '');
    $title = $course['i18n'][$lang]['lessons'][$id]['title'] ?? '';
    if ($lang === 'en' && $title !== '') $lesson['title'] = $title;
    return $lesson;
}
