<?php

function integrations_config(): array
{
    return array_merge([
        'userloop' => ['enabled' => false, 'url' => 'http://127.0.0.1:8600/userloop/api/v1/ingest', 'secret' => ''],
        'mflow' => ['enabled' => false, 'url' => '', 'secret' => ''],
    ], (array)(lf_setting_get('integrations') ?: []));
}

function userloop_event_name(string $event): string
{
    return [
        'student.registered' => 'signup',
        'enrollment.created' => 'purchase',
        'course.completed' => 'course_completed',
        'certificate.issued' => 'certificate_issued',
        'assignment.graded' => 'assignment_graded',
        'study.reminder' => 'study_reminder_sent',
        'course.updated' => 'course_updated',
        'lesson.completed' => 'lesson_completed',
        'assignment.submitted' => 'assignment_submitted',
        'checkin.done' => 'checkin_done',
        'quiz.result' => 'quiz_result',
        'preview.viewed' => 'preview_viewed',
        'certificate.revoked' => 'certificate_revoked',
        'live.reminder' => 'live_reminder_sent',
    ][$event] ?? str_replace('.', '_', $event);
}

function lf_emit_context(string $studentId, array $extra = []): array
{
    $student = (function_exists('student_get') && $studentId !== '') ? student_get($studentId) : null;
    return array_merge([
        'student_id' => $studentId,
        'name' => (string)($student['name'] ?? ''),
        'email' => (string)($student['email'] ?? ''),
    ], $extra);
}

function userloop_payload(string $event, array $data): array
{
    $email = trim((string)($data['email'] ?? ''));
    $sid = trim((string)($data['student_id'] ?? ''));
    $distinct = $email !== '' ? $email : ($sid !== '' ? $sid : 'learnflow_anon');
    $props = [];
    foreach (['course_id', 'course_title', 'course_slug', 'cert_no', 'title', 'feedback', 'score', 'order_id', 'amount', 'link', 'source'] as $k) {
        if (isset($data[$k]) && $data[$k] !== '') $props[$k] = $data[$k];
    }
    $stable = (string)($data['cert_no'] ?? $data['order_id'] ?? ($event . '|' . $distinct . '|' . ($data['course_id'] ?? '') . '|' . ($data['title'] ?? '') . '|' . date('Ymd')));
    return [
        'distinct_id' => $distinct,
        'user_id' => $sid !== '' ? $sid : null,
        'event' => userloop_event_name($event),
        'email' => $email !== '' ? $email : null,
        'name' => (string)($data['name'] ?? '') ?: null,
        'props' => array_merge($props, ['learnflow_event' => $event]),
        'source' => 'learnflow',
        'event_id' => 'lf_' . substr(hash('sha256', $stable), 0, 28),
        'timestamp' => date('c'),
    ];
}

function lf_get_json(string $url, array $headers = [], int $timeout = 8): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => (string)$resp];
    }
    $u = parse_url($url);
    if (!is_array($u) || empty($u['host'])) return ['ok' => false, 'code' => 0, 'body' => ''];
    $scheme = $u['scheme'] ?? 'http';
    $host = $u['host'];
    $port = (int)($u['port'] ?? ($scheme === 'https' ? 443 : 80));
    $path = ($u['path'] ?? '/') . (isset($u['query']) ? '?' . $u['query'] : '');
    $transport = $scheme === 'https' ? 'ssl://' : '';
    $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, $timeout);
    if (!$fp) return ['ok' => false, 'code' => 0, 'body' => '', 'error' => $errstr];
    stream_set_timeout($fp, $timeout);
    $req = "GET $path HTTP/1.1\r\nHost: $host\r\nAccept: application/json\r\nConnection: close\r\n";
    foreach ($headers as $h) $req .= $h . "\r\n";
    $req .= "\r\n";
    fwrite($fp, $req);
    $resp = '';
    while (!feof($fp)) { $chunk = fread($fp, 8192); if ($chunk === false) break; $resp .= $chunk; }
    fclose($fp);
    $code = 0;
    if (preg_match('#^HTTP/\d\.\d\s+(\d{3})#', $resp, $m)) $code = (int)$m[1];
    $split = strpos($resp, "\r\n\r\n");
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => $split !== false ? substr($resp, $split + 4) : ''];
}

function lf_send_integration(string $key, array $cfg, string $event, array $data): array
{
    if ($key === 'userloop') {
        return lf_post_json((string)$cfg['url'], userloop_payload($event, $data), '', 5, ['X-UserLoop-Token: ' . (string)($cfg['secret'] ?? '')]);
    }
    return lf_post_json((string)$cfg['url'], ['event' => $event, 'data' => $data, 'source' => 'learnflow'], (string)($cfg['secret'] ?? ''), 8);
}

function webhook_queue_file(): string
{
    return LF_DATA_DIR . '/webhook-queue.json';
}

function lf_event_log(string $event, array $data): void
{
    json_update(LF_DATA_DIR . '/events.json', function (array $log) use ($event, $data) {
        $log[] = ['event' => $event, 'data' => $data, 'at' => date('Y-m-d H:i:s')];
        if (count($log) > 1000) $log = array_slice($log, -1000);
        return $log;
    });
}

function lf_queue_webhook(string $event, array $data, string $target = ''): void
{
    json_update(webhook_queue_file(), function (array $q) use ($event, $data, $target) {
        $q[] = ['id' => 'wh_' . bin2hex(random_bytes(5)), 'event' => $event, 'data' => $data, 'target' => $target, 'tries' => 0, 'at' => date('Y-m-d H:i:s')];
        if (count($q) > 500) $q = array_slice($q, -500);
        return $q;
    });
}

function lf_post_json(string $url, array $payload, string $secret = '', int $timeout = 8, array $extraHeaders = []): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $sig = $secret !== '' ? hash_hmac('sha256', $body, $secret) : '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $headers = array_merge(['Content-Type: application/json'], $extraHeaders);
        if ($sig !== '') $headers[] = 'X-LF-Signature: ' . $sig;
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => (string)$resp, 'error' => $err];
    }

    $u = parse_url($url);
    if (!is_array($u) || empty($u['host'])) return ['ok' => false, 'code' => 0, 'body' => ''];
    $scheme = $u['scheme'] ?? 'http';
    $host = $u['host'];
    $port = (int)($u['port'] ?? ($scheme === 'https' ? 443 : 80));
    $path = ($u['path'] ?? '/') . (isset($u['query']) ? '?' . $u['query'] : '');
    $transport = $scheme === 'https' ? 'ssl://' : '';
    $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, $timeout);
    if (!$fp) return ['ok' => false, 'code' => 0, 'body' => '', 'error' => $errstr];
    stream_set_timeout($fp, $timeout);
    $req = "POST $path HTTP/1.1\r\nHost: $host\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n";
    foreach ($extraHeaders as $h) $req .= $h . "\r\n";
    if ($sig !== '') $req .= "X-LF-Signature: $sig\r\n";
    $req .= "\r\n" . $body;
    fwrite($fp, $req);
    $resp = '';
    while (!feof($fp)) {
        $chunk = fread($fp, 8192);
        if ($chunk === false) break;
        $resp .= $chunk;
    }
    fclose($fp);
    $code = 0;
    if (preg_match('#^HTTP/\d\.\d\s+(\d{3})#', $resp, $m)) $code = (int)$m[1];
    $split = strpos($resp, "\r\n\r\n");
    $respBody = $split !== false ? substr($resp, $split + 4) : '';
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => $respBody];
}

function lf_dispatch_webhooks(int $max = 50): array
{
    $cfg = integrations_config();
    $targets = [];
    foreach (['userloop', 'mflow'] as $key) {
        if (!empty($cfg[$key]['enabled']) && ($cfg[$key]['url'] ?? '') !== '') {
            $targets[$key] = $cfg[$key];
        }
    }
    if (!$targets) return ['sent' => 0, 'skipped' => true];

    $queue = json_read(webhook_queue_file());
    if (!$queue) return ['sent' => 0, 'remaining' => 0];

    $remaining = [];
    $sent = 0;
    $processed = 0;
    foreach ($queue as $item) {
        if ($processed >= $max) { $remaining[] = $item; continue; }
        $processed++;
        $okAll = true;
        $target = (string)($item['target'] ?? '');
        $keys = $target !== '' ? [$target] : array_keys($targets);
        foreach ($keys as $key) {
            if (!isset($targets[$key])) continue;
            $res = lf_send_integration($key, $targets[$key], (string)$item['event'], (array)$item['data']);
            if (empty($res['ok'])) $okAll = false;
        }
        if ($okAll) {
            $sent++;
        } else {
            $item['tries'] = (int)($item['tries'] ?? 0) + 1;
            if ($item['tries'] < 5) $remaining[] = $item;
        }
    }
    json_write(webhook_queue_file(), array_values($remaining));
    return ['sent' => $sent, 'remaining' => count($remaining)];
}

function lf_emit(string $event, array $payload): void
{
    require_once LF_ROOT . '/lib/Notify.php';
    require_once LF_ROOT . '/lib/Mailer.php';

    lf_event_log($event, $payload);
    if (function_exists('points_for_event')) points_for_event($event, $payload);
    $integrations = array_filter(integrations_config(), fn($c) => !empty($c['enabled']) && ($c['url'] ?? '') !== '');
    foreach ($integrations as $key => $cfg) {
        if ($key === 'userloop') {
            $res = lf_send_integration('userloop', $cfg, $event, $payload);
            if (empty($res['ok'])) lf_queue_webhook($event, $payload, 'userloop');
        } else {
            lf_queue_webhook($event, $payload, $key);
        }
    }

    $studentId = (string)($payload['student_id'] ?? '');
    $name = (string)($payload['name'] ?? '');
    $email = (string)($payload['email'] ?? '');

    switch ($event) {
        case 'student.registered':
            notify_add($studentId, 'system', '欢迎加入', '完善资料后即可开始学习。', lf_url('/courses'));
            if ($email !== '') lf_mail_template_send($email, 'welcome', ['name' => $name, 'email' => $email, 'url' => lf_abs_url('/courses')], $name);
            break;

        case 'enrollment.created':
            $course = (string)($payload['course_title'] ?? '');
            $link = lf_url('/learn/' . rawurlencode((string)($payload['course_slug'] ?? $payload['course_id'] ?? '')));
            notify_add($studentId, 'course', '报名成功：' . $course, '开始你的学习吧。', $link);
            if ($email !== '') lf_mail_template_send($email, 'enrollment', ['course' => $course, 'url' => lf_abs_url($link)], $name);
            break;

        case 'assignment.graded':
            $title = (string)($payload['title'] ?? '');
            $link = lf_url((string)($payload['link'] ?? '/dashboard'));
            notify_add($studentId, 'assignment', '作业已点评：' . $title, (string)($payload['feedback'] ?? ''), $link);
            if ($email !== '') lf_mail_template_send($email, 'assignment_graded', ['title' => $title, 'feedback' => (string)($payload['feedback'] ?? ''), 'url' => lf_abs_url($link)], $name);
            break;

        case 'course.completed':
            $course = (string)($payload['course_title'] ?? '');
            notify_add($studentId, 'course', '已学完：' . $course, '全部课时已完成。', lf_url('/dashboard'));
            break;

        case 'certificate.issued':
            $course = (string)($payload['course_title'] ?? '');
            $link = lf_url('/certificate/' . rawurlencode((string)($payload['cert_no'] ?? '')));
            notify_add($studentId, 'certificate', '证书已颁发：' . $course, '点击查看并分享。', $link);
            if ($email !== '') lf_mail_template_send($email, 'certificate', ['course' => $course, 'url' => lf_abs_url($link)], $name);
            break;

        case 'study.reminder':
            $course = (string)($payload['course_title'] ?? '');
            $link = lf_url((string)($payload['link'] ?? '/dashboard'));
            notify_add($studentId, 'reminder', '继续学习：' . $course, '还有进度未完成。', $link);
            if ($email !== '') lf_mail_template_send($email, 'reminder', ['course' => $course, 'url' => lf_abs_url($link)], $name);
            break;

        case 'live.reminder':
            $title = (string)($payload['title'] ?? '直播');
            $link = lf_url((string)($payload['link'] ?? '/dashboard'));
            notify_add($studentId, 'reminder', '直播提醒：' . $title, '即将开播，记得进入直播间。', $link);
            if ($email !== '') lf_mail_template_send($email, 'reminder', ['course' => $title, 'url' => lf_abs_url($link)], $name);
            break;
    }
}
