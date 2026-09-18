<?php

function autonomy_settings(): array
{
    return array_merge([
        'level' => 'propose',
        'daily_limit' => 5,
        'quiet_start' => 22,
        'quiet_end' => 8,
        'per_type' => ['reminder' => 1],
    ], (array)(lf_setting_get('autonomy') ?: []));
}

function autonomy_risk(string $type): string
{
    return [
        'marketing' => 'low',
        'improve_hint' => 'low',
        'lesson_content' => 'low',
        'reminder' => 'medium',
    ][$type] ?? 'high';
}

function autonomy_sends_outbound(string $type): bool
{
    return in_array($type, ['reminder'], true);
}

function autonomy_usage_file(): string
{
    return LF_DATA_DIR . '/autonomy-usage.json';
}

function autonomy_usage_today(): array
{
    $all = json_read(autonomy_usage_file());
    return (array)($all[date('Y-m-d')] ?? ['total' => 0, 'by_type' => []]);
}

function autonomy_record(string $type): void
{
    json_update(autonomy_usage_file(), function (array $all) use ($type) {
        $day = date('Y-m-d');
        $row = $all[$day] ?? ['total' => 0, 'by_type' => []];
        $row['total'] = (int)($row['total'] ?? 0) + 1;
        $row['by_type'][$type] = (int)($row['by_type'][$type] ?? 0) + 1;
        $all[$day] = $row;
        if (count($all) > 60) { ksort($all); $all = array_slice($all, -60, null, true); }
        return $all;
    });
}

function autonomy_reason(string $type): array
{
    $cfg = autonomy_settings();
    $level = (string)$cfg['level'];
    if ($level === 'propose') return ['ok' => false, 'reason' => 'propose 级别：仅提议，不自动执行'];
    if ($type === '') return ['ok' => false, 'reason' => '该建议无自动动作'];

    $risk = autonomy_risk($type);
    if ($risk === 'high') return ['ok' => false, 'reason' => '高风险动作必须人工确认'];
    if ($level === 'guarded' && $risk !== 'low') return ['ok' => false, 'reason' => 'guarded 级别仅自动低风险动作'];

    $usage = autonomy_usage_today();
    if ((int)$usage['total'] >= (int)$cfg['daily_limit']) return ['ok' => false, 'reason' => '已达当日自动执行上限（' . (int)$cfg['daily_limit'] . '）'];
    $cap = (int)((array)$cfg['per_type'])[$type] ?? 0;
    if ($cap > 0 && (int)($usage['by_type'][$type] ?? 0) >= $cap) return ['ok' => false, 'reason' => $type . ' 今日自动执行已达上限'];

    if (autonomy_sends_outbound($type)) {
        $h = (int)date('G');
        $qs = (int)$cfg['quiet_start'];
        $qe = (int)$cfg['quiet_end'];
        $quiet = $qs <= $qe ? ($h >= $qs && $h < $qe) : ($h >= $qs || $h < $qe);
        if ($quiet) return ['ok' => false, 'reason' => '静默时段（' . $qs . ':00–' . $qe . ':00），暂不触达'];
    }
    return ['ok' => true, 'reason' => '护栏内可自动执行'];
}

function autonomy_run(int $limit = 10): array
{
    $executed = 0;
    $skipped = [];
    foreach (evolution_state()['proposals'] as $p) {
        if ($executed >= $limit) break;
        if (($p['status'] ?? '') !== 'open' || empty($p['action'])) continue;
        $type = (string)($p['action']['type'] ?? '');
        $gate = autonomy_reason($type);
        if (empty($gate['ok'])) { $skipped[] = ['id' => $p['id'], 'reason' => $gate['reason']]; continue; }
        $res = evolution_execute((string)$p['id'], true);
        autonomy_record($type);
        $executed++;
        $skipped[] = ['id' => $p['id'], 'reason' => (!empty($res['ok']) ? '已自动执行：' : '自动执行失败：') . (string)($res['result'] ?? '')];
    }
    return ['executed' => $executed, 'log' => $skipped];
}
