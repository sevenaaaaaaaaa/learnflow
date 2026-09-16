<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    lf_json_out(['ok' => false, 'error' => '仅支持 GET'], 405);
}

$slug = (string)($_GET['slug'] ?? '');

function lf_public_course(array $course, bool $full = false): array
{
    $out = [
        'id' => (string)$course['id'],
        'slug' => (string)($course['slug'] ?? $course['id']),
        'title' => (string)($course['title'] ?? ''),
        'subtitle' => (string)($course['subtitle'] ?? ''),
        'summary' => (string)($course['summary'] ?? ''),
        'cover' => (string)($course['cover'] ?? ''),
        'instructor' => (string)($course['instructor'] ?? ''),
        'type' => (string)($course['type'] ?? '单课'),
        'level' => (string)($course['level'] ?? ''),
        'price' => (float)($course['price'] ?? 0),
        'certificate' => !empty($course['certificate']),
        'lesson_count' => course_lesson_count($course),
        'chapters' => [],
    ];
    foreach ((array)($course['chapters'] ?? []) as $ch) {
        $chapter = [
            'id' => (string)($ch['id'] ?? ''),
            'title' => (string)($ch['title'] ?? ''),
            'lessons' => [],
        ];
        foreach ((array)($ch['lessons'] ?? []) as $l) {
            $lesson = [
                'id' => (string)($l['id'] ?? ''),
                'type' => (string)($l['type'] ?? 'article'),
                'title' => (string)($l['title'] ?? ''),
                'duration' => (int)($l['duration'] ?? 0),
                'free' => !empty($l['free']),
            ];
            if ($full) {
                $lesson['content'] = (string)($l['content'] ?? '');
                $lesson['video'] = (string)($l['video'] ?? '');
            }
            $chapter['lessons'][] = $lesson;
        }
        $out['chapters'][] = $chapter;
    }
    return $out;
}

if ($slug !== '') {
    $course = course_find($slug);
    if ($course === null || ($course['status'] ?? 'draft') !== 'published') {
        lf_json_out(['ok' => false, 'error' => '课程不存在'], 404);
    }
    lf_json_out(['ok' => true, 'course' => lf_public_course($course, true)]);
}

$list = [];
foreach (course_all(true) as $c) $list[] = lf_public_course($c, false);
lf_json_out(['ok' => true, 'count' => count($list), 'courses' => $list]);
