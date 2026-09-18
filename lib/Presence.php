<?php

function presence_file(): string
{
    return LF_DATA_DIR . '/presence.json';
}

function presence_touch(string $key, string $who): void
{
    $id = $key . '|' . $who;
    json_update(presence_file(), function (array $all) use ($id, $key, $who) {
        $all[$id] = ['key' => $key, 'who' => $who, 'at' => time()];
        if (count($all) > 300) {
            $all = array_filter($all, fn($v) => ($v['at'] ?? 0) > time() - 3600);
            $all = array_slice($all, -300, null, true);
        }
        return $all;
    });
}

function presence_others(string $key, string $self, int $ttl = 90): array
{
    $all = json_read(presence_file());
    $now = time();
    $out = [];
    foreach ($all as $v) {
        if (($v['key'] ?? '') !== $key) continue;
        if (($v['who'] ?? '') === $self) continue;
        if (($v['at'] ?? 0) < $now - $ttl) continue;
        $out[] = ['key' => (string)$v['key'], 'who' => (string)$v['who']];
    }
    return $out;
}
