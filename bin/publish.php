<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('cli only');
}

$now = time();
$published = 0;
foreach (course_all() as $course) {
    if (($course['status'] ?? 'draft') !== 'draft') continue;
    $at = strtotime((string)($course['publish_at'] ?? '')) ?: 0;
    if ($at === 0 || $at > $now) continue;
    $course['status'] = 'published';
    course_save(course_normalize($course));
    $published++;
    echo "  published: " . ($course['title'] ?? $course['id']) . "\n";
}
echo "scheduled publish done: $published\n";
