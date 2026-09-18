<?php

function commission_config(): array
{
    return array_merge(['enabled' => true, 'level1' => 10.0, 'level2' => 5.0], (array)(lf_setting_get('commission') ?: []));
}

function commission_enabled(): bool
{
    $c = commission_config();
    return !empty($c['enabled']) && (float)$c['level1'] > 0;
}

function commissions_file(): string
{
    return LF_DATA_DIR . '/commissions.json';
}

function commission_all(): array
{
    return json_read(commissions_file());
}

function commission_record(string $buyerId, string $courseId, string $orderId, float $amount): void
{
    if (!commission_enabled() || $amount <= 0) return;
    $cfg = commission_config();

    $attributions = array_reverse(json_read(attributions_file()));
    $first = null;
    foreach ($attributions as $a) {
        if (($a['buyer_id'] ?? '') === $buyerId && ($a['order_id'] ?? '') === $orderId) { $first = $a; break; }
    }
    if ($first === null) return;
    $level1 = (string)($first['referrer_id'] ?? '');
    if ($level1 === '') return;

    $level2 = '';
    if ((float)$cfg['level2'] > 0) {
        foreach ($attributions as $a) {
            if (($a['buyer_id'] ?? '') === $level1) { $level2 = (string)($a['referrer_id'] ?? ''); break; }
        }
        if ($level2 === $level1) $level2 = '';
    }

    $entries = [];
    $entries[] = ['referrer_id' => $level1, 'level' => 1, 'rate' => (float)$cfg['level1']];
    if ($level2 !== '') $entries[] = ['referrer_id' => $level2, 'level' => 2, 'rate' => (float)$cfg['level2']];

    json_update(commissions_file(), function (array $all) use ($entries, $orderId, $courseId, $amount) {
        foreach ($entries as $e) {
            $dup = false;
            foreach ($all as $row) {
                if (($row['order_id'] ?? '') === $orderId && (int)($row['level'] ?? 0) === $e['level']) { $dup = true; break; }
            }
            if ($dup) continue;
            $all[] = [
                'id' => 'cm_' . bin2hex(random_bytes(4)),
                'at' => date('Y-m-d H:i:s'),
                'order_id' => $orderId,
                'course_id' => $courseId,
                'amount' => round($amount, 2),
                'referrer_id' => $e['referrer_id'],
                'level' => (int)$e['level'],
                'rate' => (float)$e['rate'],
                'commission' => round($amount * $e['rate'] / 100, 2),
                'status' => 'pending',
            ];
        }
        if (count($all) > 5000) $all = array_slice($all, -5000);
        return $all;
    });
}

function commission_mark_settled(string $id): void
{
    json_update(commissions_file(), function (array $all) use ($id) {
        foreach ($all as $i => $row) {
            if (($row['id'] ?? '') === $id) {
                $all[$i]['status'] = 'settled';
                $all[$i]['settled_at'] = date('Y-m-d H:i:s');
            }
        }
        return $all;
    });
}

function commission_summary(): array
{
    $all = commission_all();
    $byReferrer = [];
    $pending = 0.0;
    $settled = 0.0;
    foreach ($all as $r) {
        $amt = (float)($r['commission'] ?? 0);
        $rid = (string)($r['referrer_id'] ?? '');
        $byReferrer[$rid] = ($byReferrer[$rid] ?? 0) + $amt;
        if (($r['status'] ?? 'pending') === 'settled') $settled += $amt; else $pending += $amt;
    }
    arsort($byReferrer);
    return ['count' => count($all), 'pending' => round($pending, 2), 'settled' => round($settled, 2), 'by_referrer' => $byReferrer];
}
