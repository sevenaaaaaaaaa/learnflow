<?php

require_once __DIR__ . '/Events.php';

const LF_LESSON_TYPES = ['article', 'video', 'quiz', 'file', 'live'];

function courses_file(): string
{
    return LF_DATA_DIR . '/courses.json';
}

function course_all(bool $publishedOnly = false): array
{
    $all = json_read(courses_file());
    if ($publishedOnly) {
        $all = array_values(array_filter($all, fn($c) => ($c['status'] ?? 'draft') === 'published'));
    }
    return array_values($all);
}

function course_find(string $idOrSlug): ?array
{
    if ($idOrSlug === '') return null;
    foreach (course_all() as $c) {
        if (($c['id'] ?? '') === $idOrSlug || ($c['slug'] ?? '') === $idOrSlug) return $c;
    }
    return null;
}

function course_save(array $course): array
{
    if (empty($course['id'])) {
        $course['id'] = 'crs_' . bin2hex(random_bytes(5));
        $course['created_at'] = date('Y-m-d H:i:s');
    }
    $course['updated_at'] = date('Y-m-d H:i:s');
    if (empty($course['slug'])) $course['slug'] = lf_slugify((string)($course['title'] ?? $course['id']));
    if (!isset($course['status'])) $course['status'] = 'draft';
    if (!isset($course['chapters']) || !is_array($course['chapters'])) $course['chapters'] = [];

    json_update(courses_file(), function (array $all) use (&$course) {
        $all = array_values($all);
        $found = false;
        foreach ($all as $i => $c) {
            if (($c['id'] ?? '') === $course['id']) {
                $course['created_at'] = $c['created_at'] ?? ($course['created_at'] ?? date('Y-m-d H:i:s'));
                $all[$i] = $course;
                $found = true;
                break;
            }
        }
        if (!$found) $all[] = $course;
        return $all;
    });
    if (function_exists('lf_emit')) {
        lf_emit('course.updated', [
            'course_id' => (string)$course['id'],
            'title' => (string)($course['title'] ?? ''),
            'slug' => (string)($course['slug'] ?? ''),
            'status' => (string)($course['status'] ?? ''),
            'updated_at' => (string)$course['updated_at'],
        ]);
    }
    return $course;
}

function course_delete(string $id): bool
{
    $removed = false;
    json_update(courses_file(), function (array $all) use ($id, &$removed) {
        $out = [];
        foreach ($all as $c) {
            if (($c['id'] ?? '') === $id) {
                $removed = true;
                continue;
            }
            $out[] = $c;
        }
        return $out;
    });
    return $removed;
}

function course_lessons(array $course): array
{
    $out = [];
    foreach (($course['chapters'] ?? []) as $ci => $ch) {
        foreach (($ch['lessons'] ?? []) as $li => $l) {
            $l['chapter_id'] = $ch['id'] ?? ('ch_' . $ci);
            $l['chapter_title'] = $ch['title'] ?? '';
            $l['chapter_index'] = $ci;
            $l['lesson_index'] = $li;
            $out[] = $l;
        }
    }
    return $out;
}

function course_lesson_count(array $course): int
{
    $n = 0;
    foreach (($course['chapters'] ?? []) as $ch) $n += count($ch['lessons'] ?? []);
    return $n;
}

function course_lesson_find(array $course, string $lessonId): ?array
{
    foreach (course_lessons($course) as $l) {
        if (($l['id'] ?? '') === $lessonId) return $l;
    }
    return null;
}

function course_first_lesson(array $course): ?array
{
    $lessons = course_lessons($course);
    return $lessons[0] ?? null;
}

function course_lesson_neighbors(array $course, string $lessonId): array
{
    $lessons = course_lessons($course);
    $prev = $next = null;
    foreach ($lessons as $i => $l) {
        if (($l['id'] ?? '') === $lessonId) {
            $prev = $lessons[$i - 1] ?? null;
            $next = $lessons[$i + 1] ?? null;
            break;
        }
    }
    return ['prev' => $prev, 'next' => $next];
}

function course_price_label(array $course): string
{
    $price = (float)($course['price'] ?? 0);
    if ($price <= 0) return '免费';
    return '¥' . rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
}

function course_normalize(array $input): array
{
    $course = [
        'id' => (string)($input['id'] ?? ''),
        'slug' => lf_slugify((string)($input['slug'] ?? $input['title'] ?? '')),
        'title' => trim((string)($input['title'] ?? '')),
        'subtitle' => trim((string)($input['subtitle'] ?? '')),
        'summary' => trim((string)($input['summary'] ?? '')),
        'cover' => trim((string)($input['cover'] ?? '')),
        'instructor' => trim((string)($input['instructor'] ?? '')),
        'type' => (string)($input['type'] ?? '单课'),
        'level' => (string)($input['level'] ?? '入门'),
        'price' => (float)($input['price'] ?? 0),
        'status' => in_array(($input['status'] ?? 'draft'), ['draft', 'published', 'archived'], true) ? ($input['status'] ?? 'draft') : 'draft',
        'certificate' => !empty($input['certificate']),
        'allow_invite' => !empty($input['allow_invite']),
        'members_only' => !empty($input['members_only']),
        'categories' => array_values(array_filter(array_map('strval', (array)($input['categories'] ?? [])))),
        'tags' => array_values(array_filter(array_map('trim', (array)($input['tags'] ?? [])))),
        'i18n' => is_array($input['i18n'] ?? null) ? $input['i18n'] : [],
        'camp_start' => trim((string)($input['camp_start'] ?? '')),
        'camp_end' => trim((string)($input['camp_end'] ?? '')),
        'payflow_product_id' => trim((string)($input['payflow_product_id'] ?? '')),
        'chapters' => [],
    ];
    $chapters = [];
    foreach ((array)($input['chapters'] ?? []) as $ch) {
        $lessons = [];
        foreach ((array)($ch['lessons'] ?? []) as $l) {
            $type = in_array(($l['type'] ?? 'article'), LF_LESSON_TYPES, true) ? $l['type'] : 'article';
            $lesson = [
                'id' => (string)($l['id'] ?? ('lsn_' . bin2hex(random_bytes(4)))),
                'type' => $type,
                'title' => trim((string)($l['title'] ?? '')),
                'duration' => max(0, (int)($l['duration'] ?? 0)),
                'video' => trim((string)($l['video'] ?? '')),
                'content' => (string)($l['content'] ?? ''),
                'quiz_id' => (string)($l['quiz_id'] ?? ''),
                'attachments' => array_values(array_filter((array)($l['attachments'] ?? []))),
                'free' => !empty($l['free']),
                'published' => !isset($l['published']) || !empty($l['published']),
            ];
            if ($lesson['id'] === '') $lesson['id'] = 'lsn_' . bin2hex(random_bytes(4));
            $lessons[] = $lesson;
        }
        $chapters[] = [
            'id' => (string)($ch['id'] ?? ('ch_' . bin2hex(random_bytes(4)))),
            'title' => trim((string)($ch['title'] ?? '')),
            'summary' => trim((string)($ch['summary'] ?? '')),
            'lessons' => $lessons,
        ];
    }
    $course['chapters'] = $chapters;
    return $course;
}
