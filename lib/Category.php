<?php

function categories_file(): string
{
    return LF_DATA_DIR . '/categories.json';
}

function category_all(): array
{
    $all = json_read(categories_file());
    usort($all, fn($a, $b) => ((int)($a['order'] ?? 0)) <=> ((int)($b['order'] ?? 0)));
    return $all;
}

function category_find(string $key): ?array
{
    foreach (category_all() as $c) if (($c['key'] ?? '') === $key) return $c;
    return null;
}

function category_save(string $name, string $key = ''): array
{
    $name = trim($name);
    $key = $key !== '' ? lf_slugify($key) : lf_slugify($name);
    if ($name === '' || $key === '') throw new InvalidArgumentException('分类名称不能为空');
    $row = ['key' => $key, 'name' => $name, 'order' => 100];
    json_update(categories_file(), function (array $all) use ($key, $row) {
        foreach ($all as $i => $c) if (($c['key'] ?? '') === $key) { $row['order'] = (int)($c['order'] ?? 100); $all[$i] = $row; return $all; }
        $all[] = $row;
        return $all;
    });
    return $row;
}

function category_delete(string $key): void
{
    json_update(categories_file(), function (array $all) use ($key) {
        return array_values(array_filter($all, fn($c) => ($c['key'] ?? '') !== $key));
    });
}

function category_names(array $keys): array
{
    $map = [];
    foreach (category_all() as $c) $map[(string)$c['key']] = (string)$c['name'];
    $out = [];
    foreach ($keys as $k) if (isset($map[(string)$k])) $out[(string)$k] = $map[(string)$k];
    return $out;
}
