<?php

function coupons_file(): string
{
    return LF_DATA_DIR . '/coupons.json';
}

function coupon_all(): array
{
    $all = json_read(coupons_file());
    usort($all, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $all;
}

function coupon_find(string $code): ?array
{
    $code = strtoupper(trim($code));
    foreach (coupon_all() as $c) if (strtoupper((string)($c['code'] ?? '')) === $code) return $c;
    return null;
}

function coupon_save(array $data): array
{
    $code = strtoupper(trim((string)($data['code'] ?? ''))) ?: strtoupper(bin2hex(random_bytes(4)));
    $row = [
        'code' => $code,
        'name' => trim((string)($data['name'] ?? '')),
        'type' => ($data['type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed',
        'value' => max(0, (float)($data['value'] ?? 0)),
        'min_amount' => max(0, (float)($data['min_amount'] ?? 0)),
        'course_ids' => array_values(array_filter(array_map('strval', (array)($data['course_ids'] ?? [])))),
        'max_uses' => max(0, (int)($data['max_uses'] ?? 0)),
        'uses' => (int)($data['uses'] ?? 0),
        'expires_at' => (string)($data['expires_at'] ?? ''),
        'enabled' => !isset($data['enabled']) || !empty($data['enabled']),
        'created_at' => (string)($data['created_at'] ?? date('Y-m-d H:i:s')),
    ];
    json_update(coupons_file(), function (array $all) use ($row) {
        foreach ($all as $i => $c) if (strtoupper((string)($c['code'] ?? '')) === $row['code']) { $all[$i] = $row; return $all; }
        $all[] = $row;
        return $all;
    });
    return $row;
}

function coupon_delete(string $code): void
{
    $code = strtoupper(trim($code));
    json_update(coupons_file(), function (array $all) use ($code) {
        return array_values(array_filter($all, fn($c) => strtoupper((string)($c['code'] ?? '')) !== $code));
    });
}

function coupon_validate(string $code, string $courseId, float $amount): array
{
    $c = coupon_find($code);
    if ($c === null) return ['ok' => false, 'error' => '优惠券不存在'];
    if (empty($c['enabled'])) return ['ok' => false, 'error' => '优惠券已停用'];
    if (($c['expires_at'] ?? '') !== '' && strtotime((string)$c['expires_at']) < time()) return ['ok' => false, 'error' => '优惠券已过期'];
    if ((int)($c['max_uses'] ?? 0) > 0 && (int)($c['uses'] ?? 0) >= (int)$c['max_uses']) return ['ok' => false, 'error' => '优惠券已用尽'];
    $ids = (array)($c['course_ids'] ?? []);
    if ($ids && !in_array($courseId, $ids, true)) return ['ok' => false, 'error' => '该券不适用于此课程'];
    if ($amount < (float)($c['min_amount'] ?? 0)) return ['ok' => false, 'error' => '未达到最低使用金额 ¥' . $c['min_amount']];
    $discount = $c['type'] === 'percent' ? round($amount * (float)$c['value'] / 100, 2) : (float)$c['value'];
    $discount = min($discount, $amount);
    return ['ok' => true, 'coupon' => $c, 'discount' => round($discount, 2), 'final' => round(max(0, $amount - $discount), 2)];
}

function coupon_redeem(string $code): void
{
    $code = strtoupper(trim($code));
    if ($code === '') return;
    json_update(coupons_file(), function (array $all) use ($code) {
        foreach ($all as $i => $c) {
            if (strtoupper((string)($c['code'] ?? '')) === $code) {
                $all[$i]['uses'] = (int)($c['uses'] ?? 0) + 1;
                break;
            }
        }
        return $all;
    });
}
