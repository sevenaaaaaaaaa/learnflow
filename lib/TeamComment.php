<?php

function team_comments_file(): string
{
    return LF_DATA_DIR . '/team-comments.json';
}

function team_comments(string $courseId): array
{
    $all = json_read(team_comments_file());
    return array_reverse($all[$courseId] ?? []);
}

function team_comment_add(string $courseId, string $author, string $body, string $lessonId = ''): array
{
    $row = [
        'id' => 'tc_' . bin2hex(random_bytes(4)),
        'author' => $author,
        'lesson_id' => $lessonId,
        'body' => mb_substr($body, 0, 3000),
        'resolved' => false,
        'at' => date('Y-m-d H:i:s'),
    ];
    json_update(team_comments_file(), function (array $all) use ($courseId, $row) {
        $all[$courseId][] = $row;
        if (count($all[$courseId]) > 500) $all[$courseId] = array_slice($all[$courseId], -500);
        return $all;
    });
    return $row;
}

function team_comment_resolve(string $courseId, string $id): void
{
    json_update(team_comments_file(), function (array $all) use ($courseId, $id) {
        foreach (($all[$courseId] ?? []) as $i => $c) {
            if (($c['id'] ?? '') === $id) $all[$courseId][$i]['resolved'] = true;
        }
        return $all;
    });
}
