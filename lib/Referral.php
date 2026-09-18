<?php

require_once __DIR__ . '/Coupon.php';

function referrals_file(): string
{
    return LF_DATA_DIR . '/referrals.json';
}

function attributions_file(): string
{
    return LF_DATA_DIR . '/referral-attributions.json';
}

function referral_all(): array
{
    return json_read(referrals_file());
}

function referral_find(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') return null;
    $all = referral_all();
    return $all[$code] ?? null;
}

function referral_code_for_student(array $student): array
{
    $sid = (string)($student['id'] ?? '');
    foreach (referral_all() as $code => $r) {
        if (($r['owner_student_id'] ?? '') === $sid) return array_merge(['code' => (string)$code], $r);
    }
    $base = 'R' . strtoupper(substr(hash('sha256', (string)($student['email'] ?? $sid)), 0, 6));
    $code = $base;
    $n = 1;
    while (referral_find($code) !== null) { $code = $base . $n; $n++; }
    $row = [
        'code' => $code,
        'owner_student_id' => $sid,
        'owner_name' => (string)($student['name'] ?? ''),
        'uses' => 0,
        'enabled' => true,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    json_update(referrals_file(), function (array $all) use ($code, $row) {
        $all[$code] = $row;
        return $all;
    });
    return $row;
}

function referral_attribute(string $code, string $buyerId, string $courseId, string $orderId, float $amount = 0): array
{
    $row = referral_find($code);
    if ($row === null) return ['ok' => false, 'error' => '推荐码不存在'];
    if (($row['owner_student_id'] ?? '') === $buyerId) return ['ok' => false, 'error' => '不能推荐自己'];
    $referrerId = (string)$row['owner_student_id'];
    json_update(referrals_file(), function (array $all) use ($code) {
        if (isset($all[$code])) $all[$code]['uses'] = (int)($all[$code]['uses'] ?? 0) + 1;
        return $all;
    });
    json_update(attributions_file(), function (array $all) use ($code, $referrerId, $buyerId, $courseId, $orderId, $amount) {
        $all[] = ['at' => date('Y-m-d H:i:s'), 'code' => $code, 'referrer_id' => $referrerId, 'buyer_id' => $buyerId, 'course_id' => $courseId, 'order_id' => $orderId, 'amount' => $amount];
        if (count($all) > 2000) $all = array_slice($all, -2000);
        return $all;
    });

    require_once __DIR__ . '/Commission.php';
    commission_record($buyerId, $courseId, $orderId, $amount);

    $reward = (float)(lf_setting_get('referral_reward', 0) ?: 0);    $rewardCoupon = '';
    if ($reward > 0) {
        $coupon = coupon_save([
            'code' => 'RW' . strtoupper(bin2hex(random_bytes(3))),
            'name' => '推荐奖励',
            'type' => 'fixed',
            'value' => $reward,
            'max_uses' => 1,
        ]);
        $rewardCoupon = $coupon['code'];
        require_once __DIR__ . '/Notify.php';
        notify_add($referrerId, 'system', '推荐奖励已发放', '推荐码 ' . $code . ' 带来一位新学员，奖励券 ' . $rewardCoupon . ' 已生成。', lf_url('/dashboard'));
    }
    return ['ok' => true, 'referrer_id' => $referrerId, 'reward_coupon' => $rewardCoupon];
}

function referral_stats(string $ownerId): array
{
    $all = json_read(attributions_file());
    $mine = array_values(array_filter($all, fn($a) => ($a['referrer_id'] ?? '') === $ownerId));
    $buyers = [];
    $amount = 0.0;
    foreach ($mine as $a) {
        $buyers[(string)($a['buyer_id'] ?? '')] = true;
        $amount += (float)($a['amount'] ?? 0);
    }
    return ['uses' => count($mine), 'buyers' => count($buyers), 'amount' => round($amount, 2)];
}

function attribution_all(): array
{
    $all = json_read(attributions_file());
    return array_reverse($all);
}
