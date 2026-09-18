<?php

function revisions_file(): string
{
    return LF_DATA_DIR . '/course-revisions.json';
}

function revision_snapshot(array $course, string $by = ''): void
{
    $id = (string)($course['id'] ?? '');
    if ($id === '') return;
    $row = [
        'id' => 'rev_' . bin2hex(random_bytes(4)),
        'at' => date('Y-m-d H:i:s'),
        'by' => $by,
        'title' => (string)($course['title'] ?? ''),
        'status' => (string)($course['status'] ?? ''),
        'snapshot' => $course,
    ];
    json_update(revisions_file(), function (array $all) use ($id, $row) {
        $list = $all[$id] ?? [];
        array_unshift($list, $row);
        $all[$id] = array_slice($list, 0, 20);
        return $all;
    });
}

function revision_list(string $courseId): array
{
    $all = json_read(revisions_file());
    return $all[$courseId] ?? [];
}

function revision_restore(string $courseId, string $revId): ?array
{
    foreach (revision_list($courseId) as $r) {
        if (($r['id'] ?? '') === $revId && !empty($r['snapshot'])) {
            revision_snapshot(course_find($courseId) ?? [], 'restore-backup');
            $course = $r['snapshot'];
            $course['id'] = $courseId;
            return course_save(course_normalize($course));
        }
    }
    return null;
}
