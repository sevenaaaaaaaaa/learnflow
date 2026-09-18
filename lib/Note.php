<?php

function notes_file(): string
{
    return LF_DATA_DIR . '/notes.json';
}

function note_get(string $studentId, string $courseId, string $lessonId): array
{
    $all = json_read(notes_file());
    return $all[$studentId][$courseId][$lessonId] ?? [];
}

function note_save(string $studentId, string $courseId, string $lessonId, string $content): array
{
    $row = ['content' => mb_substr($content, 0, 20000), 'updated_at' => date('Y-m-d H:i:s')];
    json_update(notes_file(), function (array $all) use ($studentId, $courseId, $lessonId, $row) {
        $all[$studentId][$courseId][$lessonId] = $row;
        return $all;
    });
    return $row;
}


function course_attachments(array $course): array
{
    $out = [];
    foreach (course_lessons($course) as $l) {
        foreach ((array)($l['attachments'] ?? []) as $a) {
            $out[] = ['name' => (string)($a['name'] ?? '附件'), 'url' => (string)($a['url'] ?? ''), 'lesson' => (string)($l['title'] ?? '')];
        }
    }
    return $out;
}
