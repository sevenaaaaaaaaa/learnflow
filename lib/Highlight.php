<?php

function highlights_file(): string
{
    return LF_DATA_DIR . '/highlights.json';
}

function highlights_for(string $studentId, string $courseId, string $lessonId): array
{
    $all = json_read(highlights_file());
    return $all[$studentId][$courseId][$lessonId] ?? [];
}

function highlight_add(string $studentId, string $courseId, string $lessonId, string $text, string $note = '', string $color = 'yellow'): array
{
    $row = [
        'id' => 'hl_' . bin2hex(random_bytes(4)),
        'text' => mb_substr(trim($text), 0, 500),
        'note' => mb_substr($note, 0, 1000),
        'color' => in_array($color, ['yellow', 'green', 'blue', 'pink'], true) ? $color : 'yellow',
        'at' => date('Y-m-d H:i:s'),
    ];
    json_update(highlights_file(), function (array $all) use ($studentId, $courseId, $lessonId, $row) {
        $all[$studentId][$courseId][$lessonId][] = $row;
        return $all;
    });
    return $row;
}

function highlight_delete(string $studentId, string $courseId, string $lessonId, string $id): void
{
    json_update(highlights_file(), function (array $all) use ($studentId, $courseId, $lessonId, $id) {
        $list = $all[$studentId][$courseId][$lessonId] ?? [];
        $all[$studentId][$courseId][$lessonId] = array_values(array_filter($list, fn($h) => ($h['id'] ?? '') !== $id));
        return $all;
    });
}
