<?php

function notifications_file(): string
{
    return LF_DATA_DIR . '/notifications.json';
}

function notify_add(string $studentId, string $type, string $title, string $body = '', string $link = ''): array
{
    $row = [
        'id' => 'ntf_' . bin2hex(random_bytes(5)),
        'type' => $type,
        'title' => mb_substr($title, 0, 120),
        'body' => mb_substr($body, 0, 600),
        'link' => $link,
        'read' => false,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    if ($studentId !== '') {
        json_update(notifications_file(), function (array $all) use ($studentId, $row) {
            $all[$studentId][] = $row;
            if (count($all[$studentId]) > 300) $all[$studentId] = array_slice($all[$studentId], -300);
            return $all;
        });
    }
    return $row;
}

function notify_broadcast(array $studentIds, string $type, string $title, string $body = '', string $link = ''): int
{
    $n = 0;
    foreach (array_unique(array_filter($studentIds)) as $sid) {
        notify_add((string)$sid, $type, $title, $body, $link);
        $n++;
    }
    return $n;
}

function notify_list(string $studentId, int $limit = 50): array
{
    $all = json_read(notifications_file());
    $rows = $all[$studentId] ?? [];
    usort($rows, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_slice($rows, 0, $limit);
}

function notify_unread_count(string $studentId): int
{
    $all = json_read(notifications_file());
    $n = 0;
    foreach (($all[$studentId] ?? []) as $r) if (empty($r['read'])) $n++;
    return $n;
}

function notify_mark_read(string $studentId, string $id = ''): void
{
    json_update(notifications_file(), function (array $all) use ($studentId, $id) {
        if (!isset($all[$studentId])) return $all;
        foreach ($all[$studentId] as $i => $r) {
            if ($id === '' || ($r['id'] ?? '') === $id) $all[$studentId][$i]['read'] = true;
        }
        return $all;
    });
}
