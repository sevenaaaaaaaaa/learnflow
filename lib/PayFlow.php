<?php

function payflow_config(): array
{
    return array_merge([
        'enabled' => false,
        'base_url' => 'https://payflow.nownexts.com',
        'api_key' => '',
        'secret' => '',
    ], (array)(lf_setting_get('payflow') ?: []));
}

function payflow_course_product(array $course): string
{
    return (string)($course['payflow_product_id'] ?? '');
}

function payflow_checkout_url(array $course, string $email = '', string $returnTo = '', string $coupon = '', string $refCode = ''): string
{
    $cfg = payflow_config();
    $product = payflow_course_product($course);
    if ($product === '') return '';
    $params = [
        'product' => $product,
        'ref' => $course['id'] ?? '',
        'success' => $returnTo !== '' ? $returnTo : lf_abs_url('/course/' . ($course['slug'] ?? $course['id']) . '?enrolled=1'),
    ];
    if ($email !== '') $params['email'] = $email;
    if ($coupon !== '') $params['coupon'] = $coupon;
    if ($refCode !== '') $params['ref_code'] = $refCode;
    return rtrim($cfg['base_url'], '/') . '/checkout?' . http_build_query($params);
}

function payflow_product_checkout(string $productId, string $email = '', string $returnTo = ''): string
{
    $cfg = payflow_config();
    if ($productId === '') return '';
    $params = ['product' => $productId, 'success' => $returnTo !== '' ? $returnTo : lf_abs_url('/membership?joined=1')];
    if ($email !== '') $params['email'] = $email;
    return rtrim($cfg['base_url'], '/') . '/checkout?' . http_build_query($params);
}


function payflow_verify(string $payload, string $signature): bool
{
    $cfg = payflow_config();
    if ((string)$cfg['secret'] === '') return false;
    $expected = hash_hmac('sha256', $payload, (string)$cfg['secret']);
    return hash_equals($expected, $signature);
}

function payflow_map_product_to_course(string $productId): ?array
{
    if ($productId === '') return null;
    foreach (course_all() as $c) {
        if (payflow_course_product($c) === $productId || ($c['id'] ?? '') === $productId || ($c['slug'] ?? '') === $productId) {
            return $c;
        }
    }
    return null;
}

function payflow_handle_order(array $order): array
{
    $product = (string)($order['product_id'] ?? $order['product'] ?? '');
    $course = payflow_map_product_to_course($product);
    $email = mb_strtolower(trim((string)($order['email'] ?? '')));

    require_once __DIR__ . '/Membership.php';
    if ($course === null) {
        $tier = tier_by_product($product);
        if ($tier !== null) {
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => '订单缺少有效邮箱'];
            }
            require_once __DIR__ . '/Student.php';
            $student = student_find_or_create(['email' => $email, 'name' => (string)($order['name'] ?? ''), 'phone' => (string)($order['phone'] ?? ''), 'source' => 'payflow']);
            $grant = membership_grant((string)$student['id'], (string)$tier['id']);
            return ['ok' => true, 'type' => 'membership', 'tier_id' => $tier['id'], 'student_id' => $student['id'], 'membership' => $grant];
        }
        return ['ok' => false, 'error' => 'product 未映射到任何课程/会员', 'product' => $product];
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => '订单缺少有效邮箱'];
    }

    require_once LF_ROOT . '/lib/Student.php';
    require_once LF_ROOT . '/lib/Enrollment.php';
    $student = student_find_or_create([
        'email' => $email,
        'name' => (string)($order['name'] ?? ''),
        'phone' => (string)($order['phone'] ?? ''),
        'source' => 'payflow',
    ]);

    $orderId = (string)($order['order_id'] ?? $order['id'] ?? '');
    $enrollment = enroll_from_order(
        (string)$course['id'],
        (string)$student['id'],
        $orderId,
        (float)($order['amount'] ?? 0)
    );

    require_once __DIR__ . '/Coupon.php';
    require_once __DIR__ . '/Referral.php';
    $couponCode = strtoupper(trim((string)($order['coupon'] ?? ($order['coupon_code'] ?? ''))));
    if ($couponCode !== '') coupon_redeem($couponCode);

    $refCode = strtoupper(trim((string)($order['ref_code'] ?? '')));
    if ($refCode === '') {
        $maybe = strtoupper(trim((string)($order['ref'] ?? '')));
        if ($maybe !== '' && referral_find($maybe) !== null) $refCode = $maybe;
    }
    $referral = ['ok' => false];
    if ($refCode !== '' && referral_find($refCode) !== null) {
        $referral = referral_attribute($refCode, (string)$student['id'], (string)$course['id'], $orderId, (float)($order['amount'] ?? 0));
    }

    return [
        'ok' => true,
        'course_id' => $course['id'],
        'student_id' => $student['id'],
        'enrollment' => $enrollment,
        'coupon' => $couponCode,
        'referral' => $referral,
    ];
}
