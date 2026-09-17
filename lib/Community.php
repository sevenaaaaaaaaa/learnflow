<?php

function community_file(): string
{
    return LF_DATA_DIR . '/community.json';
}

function post_all(string $courseId, int $limit = 60): array
{
    $all = json_read(community_file());
    $rows = $all[$courseId] ?? [];
    usort($rows, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_slice($rows, 0, $limit);
}

function post_find(string $courseId, string $postId): ?array
{
    $all = json_read(community_file());
    foreach (($all[$courseId] ?? []) as $p) if (($p['id'] ?? '') === $postId) return $p;
    return null;
}

function post_create(string $courseId, array $student, array $data): array
{
    $row = [
        'id' => 'pst_' . bin2hex(random_bytes(5)),
        'student_id' => (string)($student['id'] ?? ''),
        'name' => (string)($student['name'] ?? '学员'),
        'type' => in_array(($data['type'] ?? 'note'), ['question', 'progress', 'note'], true) ? $data['type'] : 'note',
        'title' => mb_substr(trim((string)($data['title'] ?? '')), 0, 100),
        'body' => mb_substr((string)($data['body'] ?? ''), 0, 3000),
        'likes' => [],
        'comments' => [],
        'created_at' => date('Y-m-d H:i:s'),
    ];
    json_update(community_file(), function (array $all) use ($courseId, $row) {
        $all[$courseId][] = $row;
        return $all;
    });
    return $row;
}

function post_delete(string $courseId, string $postId): void
{
    json_update(community_file(), function (array $all) use ($courseId, $postId) {
        $all[$courseId] = array_values(array_filter($all[$courseId] ?? [], fn($p) => ($p['id'] ?? '') !== $postId));
        return $all;
    });
}

function post_like(string $courseId, string $postId, string $studentId): int
{
    $count = 0;
    json_update(community_file(), function (array $all) use ($courseId, $postId, $studentId, &$count) {
        foreach (($all[$courseId] ?? []) as $i => $p) {
            if (($p['id'] ?? '') !== $postId) continue;
            $likes = array_map('strval', (array)($p['likes'] ?? []));
            if (in_array($studentId, $likes, true)) {
                $likes = array_values(array_filter($likes, fn($x) => $x !== $studentId));
            } else {
                $likes[] = $studentId;
            }
            $all[$courseId][$i]['likes'] = array_values(array_unique($likes));
            $count = count($all[$courseId][$i]['likes']);
            break;
        }
        return $all;
    });
    return $count;
}

function comment_add(string $courseId, string $postId, array $student, string $body): void
{
    $comment = [
        'id' => 'cmt_' . bin2hex(random_bytes(4)),
        'student_id' => (string)($student['id'] ?? ''),
        'name' => (string)($student['name'] ?? '学员'),
        'body' => mb_substr($body, 0, 1500),
        'created_at' => date('Y-m-d H:i:s'),
    ];
    json_update(community_file(), function (array $all) use ($courseId, $postId, $comment) {
        foreach (($all[$courseId] ?? []) as $i => $p) {
            if (($p['id'] ?? '') !== $postId) continue;
            $all[$courseId][$i]['comments'][] = $comment;
            break;
        }
        return $all;
    });
}
