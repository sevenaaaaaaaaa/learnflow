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
    $candidate = (string)($_GET['lang'] ?? ($_COOKIE['lf_lang'] ?? 'zh'));
    if (!in_array($candidate, $langs, true)) $candidate = 'zh';
    if (isset($_GET['lang']) && in_array((string)$_GET['lang'], $langs, true) && PHP_SAPI !== 'cli' && !headers_sent()) {
        setcookie('lf_lang', $candidate, ['expires' => time() + 31536000, 'path' => lf_base_path() . '/', 'samesite' => 'Lax']);
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
                if ($id !== '' && !empty($lessonMap[$id]['title'])) {
                    $course['chapters'][$ci]['lessons'][$li]['title'] = $lessonMap[$id]['title'];
                }
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
