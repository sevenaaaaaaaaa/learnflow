<?php

function integrations_config(): array
{
    return array_merge([
        'userloop' => ['enabled' => false, 'url' => '', 'secret' => ''],
        'mflow' => ['enabled' => false, 'url' => '', 'secret' => ''],
    ], (array)(lf_setting_get('integrations') ?: []));
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

function lf_queue_webhook(string $event, array $data): void
{
    json_update(webhook_queue_file(), function (array $q) use ($event, $data) {
        $q[] = ['id' => 'wh_' . bin2hex(random_bytes(5)), 'event' => $event, 'data' => $data, 'tries' => 0, 'at' => date('Y-m-d H:i:s')];
        if (count($q) > 500) $q = array_slice($q, -500);
        return $q;
    });
}

function lf_post_json(string $url, array $payload, string $secret = '', int $timeout = 8): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $sig = $secret !== '' ? hash_hmac('sha256', $body, $secret) : '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json'];
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
        foreach ($targets as $key => $t) {
            $res = lf_post_json((string)$t['url'], ['event' => $item['event'], 'data' => $item['data'], 'source' => 'learnflow'], (string)$t['secret']);
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
    if (array_filter(integrations_config(), fn($c) => !empty($c['enabled']) && ($c['url'] ?? '') !== '')) {
        lf_queue_webhook($event, $payload);
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
    }
}
