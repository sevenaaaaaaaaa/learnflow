<?php

function media_index_file(): string
{
    return LF_DATA_DIR . '/media.json';
}

function media_kind_of(string $ext): string
{
    $ext = strtolower($ext);
    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'avif'], true)) return 'image';
    if (in_array($ext, ['mp4', 'mov', 'webm', 'm4v'], true)) return 'video';
    if (in_array($ext, ['mp3', 'm4a', 'wav', 'aac', 'ogg'], true)) return 'audio';
    return 'file';
}

function media_all(string $kind = ''): array
{
    $all = json_read(media_index_file());
    if ($kind !== '') $all = array_values(array_filter($all, fn($m) => ($m['kind'] ?? '') === $kind));
    usort($all, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $all;
}

function media_find(string $id): ?array
{
    foreach (media_all() as $m) if (($m['id'] ?? '') === $id) return $m;
    return null;
}

function media_add(array $saved, array $extra = []): array
{
    $row = array_merge([
        'id' => 'md_' . bin2hex(random_bytes(5)),
        'rel' => (string)($saved['rel'] ?? ''),
        'name' => (string)($saved['name'] ?? ''),
        'ext' => (string)($saved['ext'] ?? ''),
        'size' => (int)($saved['size'] ?? 0),
        'kind' => media_kind_of((string)($saved['ext'] ?? '')),
        'created_at' => date('Y-m-d H:i:s'),
    ], $extra);
    json_update(media_index_file(), function (array $all) use ($row) {
        $all[] = $row;
        return $all;
    });
    return $row;
}

function media_delete(string $id): bool
{
    $m = media_find($id);
    if ($m === null) return false;
    $path = lf_file_path((string)$m['rel']);
    if ($path !== null) @unlink($path);
    json_update(media_index_file(), function (array $all) use ($id) {
        return array_values(array_filter($all, fn($x) => ($x['id'] ?? '') !== $id));
    });
    return true;
}

function media_public_url(string $id): string
{
    return lf_url('/media/' . rawurlencode($id));
}
