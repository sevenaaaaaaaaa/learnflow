<?php

function membership_file(): string
{
    return LF_DATA_DIR . '/membership-tiers.json';
}

function membership_tiers(): array
{
    $all = json_read(membership_file());
    usort($all, fn($a, $b) => ((float)($a['price'] ?? 0)) <=> ((float)($b['price'] ?? 0)));
    return $all;
}

function tier_find(string $id): ?array
{
    foreach (membership_tiers() as $t) if (($t['id'] ?? '') === $id) return $t;
    return null;
}

function tier_save(array $data): array
{
    $row = [
        'id' => (string)($data['id'] ?? '') ?: ('tier_' . bin2hex(random_bytes(4))),
        'name' => trim((string)($data['name'] ?? '会员')),
        'price' => max(0, (float)($data['price'] ?? 0)),
        'duration_days' => max(1, (int)($data['duration_days'] ?? 365)),
        'discount_percent' => max(0, min(100, (float)($data['discount_percent'] ?? 0))),
        'members_only' => !isset($data['members_only']) || !empty($data['members_only']),
        'payflow_product_id' => trim((string)($data['payflow_product_id'] ?? '')),
        'description' => trim((string)($data['description'] ?? '')),
    ];
    json_update(membership_file(), function (array $all) use ($row) {
        foreach ($all as $i => $t) if (($t['id'] ?? '') === $row['id']) { $all[$i] = $row; return $all; }
        $all[] = $row;
        return $all;
    });
    return $row;
}

function tier_delete(string $id): void
{
    json_update(membership_file(), function (array $all) use ($id) {
        return array_values(array_filter($all, fn($t) => ($t['id'] ?? '') !== $id));
    });
}

function tier_by_product(string $productId): ?array
{
    if ($productId === '') return null;
    foreach (membership_tiers() as $t) if (($t['payflow_product_id'] ?? '') === $productId) return $t;
    return null;
}

function membership_for_student(string $studentId): ?array
{
    $s = student_get($studentId);
    $m = (array)($s['membership'] ?? []);
    if (empty($m['tier_id'])) return null;
    return array_merge(['active' => (int)($m['expires_at'] ?? 0) >= time()], $m, ['tier' => tier_find((string)$m['tier_id'])]);
}

function membership_active(string $studentId): bool
{
    $m = membership_for_student($studentId);
    return $m !== null && !empty($m['active']);
}

function membership_tier_of(string $studentId): ?array
{
    $m = membership_for_student($studentId);
    return ($m !== null && !empty($m['active'])) ? ($m['tier'] ?? null) : null;
}

function membership_grant(string $studentId, string $tierId, ?int $days = null): array
{
    $tier = tier_find($tierId);
    if ($tier === null) throw new InvalidArgumentException('会员等级不存在');
    $days = $days ?? (int)($tier['duration_days'] ?? 365);
    $current = (array)(student_get($studentId)['membership'] ?? []);
    $base = (($current['tier_id'] ?? '') === $tierId && (int)($current['expires_at'] ?? 0) > time()) ? (int)$current['expires_at'] : time();
    $expires = $base + $days * 86400;
    json_update(students_file(), function (array $all) use ($studentId, $tierId, $expires) {
        if (isset($all[$studentId])) {
            $m = (array)($all[$studentId]['membership'] ?? []);
            $all[$studentId]['membership'] = ['tier_id' => $tierId, 'since' => $m['since'] ?? date('Y-m-d H:i:s'), 'expires_at' => $expires];
        }
        return $all;
    });
    require_once __DIR__ . '/Notify.php';
    notify_add($studentId, 'system', '会员已开通/续期：' . (string)$tier['name'], '有效期至 ' . date('Y-m-d', $expires), lf_url('/membership'));
    return ['tier_id' => $tierId, 'expires_at' => $expires];
}

function membership_revoke(string $studentId): void
{
    json_update(students_file(), function (array $all) use ($studentId) {
        if (isset($all[$studentId])) unset($all[$studentId]['membership']);
        return $all;
    });
}

function membership_discount(array $course, string $studentId): float
{
    $tier = membership_tier_of($studentId);
    if ($tier === null) return 0.0;
    $pct = (float)($tier['discount_percent'] ?? 0);
    if ($pct <= 0) return 0.0;
    return round((float)($course['price'] ?? 0) * $pct / 100, 2);
}

function lf_ensure_member_access(array $course, ?array $student): void
{
    if ($student === null || empty($course['members_only'])) return;
    $sid = (string)$student['id'];
    if (enroll_is_active((string)$course['id'], $sid)) return;
    if (membership_active($sid)) {
        enroll_add((string)$course['id'], $sid, ['source' => 'membership', 'status' => 'active']);
    }
}
