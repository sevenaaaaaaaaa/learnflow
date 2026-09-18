<?php

function strategies_file(): string
{
    return LF_DATA_DIR . '/strategies.json';
}

function strategy_all(): array
{
    $all = json_read(strategies_file());
    usort($all, fn($a, $b) => ((int)($b['runs'] ?? 0)) <=> ((int)($a['runs'] ?? 0)));
    return $all;
}

function strategy_find(string $key): ?array
{
    foreach (strategy_all() as $s) if (($s['key'] ?? '') === $key) return $s;
    return null;
}

function strategy_touch_on_execute(array $proposal): void
{
    $key = (string)($proposal['id'] ?? '');
    if ($key === '') return;
    json_update(strategies_file(), function (array $all) use ($key, $proposal) {
        foreach ($all as $i => $s) {
            if (($s['key'] ?? '') !== $key) continue;
            $all[$i]['runs'] = (int)($s['runs'] ?? 0) + 1;
            $all[$i]['last_run_at'] = date('Y-m-d H:i:s');
            return $all;
        }
        $all[] = [
            'key' => $key,
            'title' => (string)($proposal['title'] ?? ''),
            'category' => (string)($proposal['category'] ?? ''),
            'action_type' => (string)($proposal['action']['type'] ?? ''),
            'runs' => 1,
            'successes' => 0,
            'failures' => 0,
            'status' => 'active',
            'variants' => [],
            'chosen' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'last_run_at' => date('Y-m-d H:i:s'),
        ];
        return $all;
    });
}

function strategy_verdict(string $key, bool $effective): void
{
    if ($key === '') return;
    json_update(strategies_file(), function (array $all) use ($key, $effective) {
        foreach ($all as $i => $s) {
            if (($s['key'] ?? '') !== $key) continue;
            if ($effective) $all[$i]['successes'] = (int)($s['successes'] ?? 0) + 1;
            else $all[$i]['failures'] = (int)($s['failures'] ?? 0) + 1;
            $all[$i]['last_verdict'] = $effective ? 'effective' : 'ineffective';
            $all[$i]['last_verdict_at'] = date('Y-m-d H:i:s');
            return $all;
        }
        return $all;
    });
}

function strategy_set_status(string $key, string $status): void
{
    if (!in_array($status, ['active', 'retired'], true)) return;
    json_update(strategies_file(), function (array $all) use ($key, $status) {
        foreach ($all as $i => $s) if (($s['key'] ?? '') === $key) $all[$i]['status'] = $status;
        return $all;
    });
}

function strategy_set_variants(string $key, array $variants, string $chosen = ''): void
{
    json_update(strategies_file(), function (array $all) use ($key, $variants, $chosen) {
        foreach ($all as $i => $s) {
            if (($s['key'] ?? '') !== $key) continue;
            $all[$i]['variants'] = $variants;
            if ($chosen !== '') $all[$i]['chosen'] = $chosen;
        }
        return $all;
    });
}

function strategy_ab_generate(string $key): array
{
    $s = strategy_find($key);
    if ($s === null) return ['ok' => false, 'error' => '策略不存在'];
    if (!ai_enabled()) return ['ok' => false, 'error' => 'AI 未启用'];
    $proposal = null;
    foreach (evolution_state()['proposals'] as $p) if (($p['id'] ?? '') === $key) { $proposal = $p; break; }
    $type = (string)($s['action_type'] ?? 'marketing');
    $mt = $type === 'marketing' ? 'moments' : 'page';
    $topic = (string)($proposal['action']['topic'] ?? $s['title'] ?? '');
    $a = ai_marketing($mt, $topic, '版本A：理性专业、数据与逻辑导向');
    $b = ai_marketing($mt, $topic, '版本B：情绪共鸣、故事与场景导向');
    if ($a === null || $b === null) return ['ok' => false, 'error' => 'AI 生成失败'];
    $variants = [['label' => 'A', 'content' => $a], ['label' => 'B', 'content' => $b]];
    strategy_set_variants($key, $variants, 'A');
    return ['ok' => true, 'variants' => $variants];
}

function strategy_stats(): array
{
    $all = strategy_all();
    $runs = 0; $ok = 0; $fail = 0;
    foreach ($all as $s) { $runs += (int)($s['runs'] ?? 0); $ok += (int)($s['successes'] ?? 0); $fail += (int)($s['failures'] ?? 0); }
    $verdicts = $ok + $fail;
    return ['count' => count($all), 'runs' => $runs, 'successes' => $ok, 'failures' => $fail, 'success_rate' => $verdicts > 0 ? round($ok / $verdicts * 100, 1) : 0];
}
