<?php

function mail_config(): array
{
    return array_merge([
        'enabled' => false,
        'host' => '',
        'port' => 465,
        'user' => '',
        'pass' => '',
        'secure' => 'ssl',
        'from' => '',
        'from_name' => '',
    ], (array)(lf_setting_get('smtp') ?: []));
}

function lf_mail_log_file(): string
{
    return LF_DATA_DIR . '/mail-log.json';
}

function lf_mail_log(string $to, string $subject, string $html): void
{
    json_update(lf_mail_log_file(), function (array $log) use ($to, $subject, $html) {
        $log[] = ['to' => $to, 'subject' => $subject, 'html' => $html, 'at' => date('Y-m-d H:i:s')];
        if (count($log) > 200) $log = array_slice($log, -200);
        return $log;
    });
}

function lf_mail_encode_header(string $text): string
{
    if (preg_match('/[\x80-\xFF]/', $text)) {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
    return $text;
}

function lf_mail_send(string $to, string $subject, string $html, string $toName = ''): bool
{
    $cfg = mail_config();
    if (empty($cfg['enabled']) || $cfg['host'] === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        lf_mail_log($to, $subject, $html);
        return false;
    }

    $from = $cfg['from'] !== '' ? $cfg['from'] : ('no-reply@' . (parse_url(lf_abs_url('/'), PHP_URL_HOST) ?: 'localhost'));
    $fromName = $cfg['from_name'] !== '' ? $cfg['from_name'] : (string)lf_setting_get('site_name', 'LearnFlow');
    $secure = (string)$cfg['secure'];
    $port = (int)$cfg['port'];
    $host = (string)$cfg['host'];
    $transport = $secure === 'ssl' ? 'ssl://' : '';
    $timeout = 15;

    $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        lf_mail_log($to, $subject, $html);
        @file_put_contents(LF_LOG_FILE, '[' . date('Y-m-d H:i:s') . "] SMTP connect failed: $errstr ($errno)\n", FILE_APPEND);
        return false;
    }
    stream_set_timeout($fp, $timeout);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (string $c, array $expect) use ($fp, $read): bool {
        fwrite($fp, $c . "\r\n");
        $resp = $read();
        foreach ($expect as $code) if (str_starts_with($resp, (string)$code)) return true;
        return false;
    };

    $greeting = $read();
    if (strpos($greeting, '220') !== 0) {
        fclose($fp);
        lf_mail_log($to, $subject, $html);
        return false;
    }
    $ehloHost = parse_url(lf_abs_url('/'), PHP_URL_HOST) ?: 'localhost';
    $cmd('EHLO ' . $ehloHost, [250]);

    if ($secure === 'tls') {
        if (!$cmd('STARTTLS', [220])) { fclose($fp); lf_mail_log($to, $subject, $html); return false; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            lf_mail_log($to, $subject, $html);
            return false;
        }
        $cmd('EHLO ' . $ehloHost, [250]);
    }

    if ($cfg['user'] !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode((string)$cfg['user']), [334]);
        if (!$cmd(base64_encode((string)$cfg['pass']), [235, 503])) {
            fclose($fp);
            lf_mail_log($to, $subject, $html);
            @file_put_contents(LF_LOG_FILE, '[' . date('Y-m-d H:i:s') . "] SMTP auth failed\n", FILE_APPEND);
            return false;
        }
    }

    if (!$cmd('MAIL FROM:<' . $from . '>', [250])) { fclose($fp); lf_mail_log($to, $subject, $html); return false; }
    if (!$cmd('RCPT TO:<' . $to . '>', [250, 251])) { fclose($fp); lf_mail_log($to, $subject, $html); return false; }
    if (!$cmd('DATA', [354])) { fclose($fp); lf_mail_log($to, $subject, $html); return false; }

    $boundary = 'lf' . bin2hex(random_bytes(8));
    $headers = [];
    $headers[] = 'From: ' . lf_mail_encode_header($fromName) . ' <' . $from . '>';
    $headers[] = 'To: ' . ($toName !== '' ? lf_mail_encode_header($toName) . ' ' : '') . '<' . $to . '>';
    $headers[] = 'Subject: ' . lf_mail_encode_header($subject);
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: base64';
    $headers[] = 'Date: ' . date('r');
    $body = chunk_split(base64_encode($html));
    $data = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
    fwrite($fp, $data . "\r\n");
    $resp = $read();
    $ok = strpos($resp, '250') === 0;
    $cmd('QUIT', [221]);
    fclose($fp);
    if (!$ok) lf_mail_log($to, $subject, $html);
    return $ok;
}

function lf_mail_template(string $key, array $data): array
{
    $site = (string)(lf_setting_get('site_name') ?: 'LearnFlow');
    $wrap = function (string $title, string $bodyHtml) use ($site): string {
        return '<div style="font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;max-width:560px;margin:0 auto;padding:28px;color:#222">'
            . '<div style="font-size:15px;font-weight:700;margin-bottom:18px">' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<h1 style="font-size:20px;margin:0 0 14px">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<div style="font-size:14px;line-height:1.9;color:#444">' . $bodyHtml . '</div>'
            . '<div style="margin-top:24px;font-size:12px;color:#999">本邮件由 ' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . ' 自动发送</div>'
            . '</div>';
    };
    $btn = function (string $url, string $label): string {
        return '<p style="margin:20px 0"><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:11px 22px;border-radius:10px;background:#2f6bff;color:#fff;text-decoration:none">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></p>';
    };

    switch ($key) {
        case 'welcome':
            return [
                '欢迎加入 ' . $site,
                $wrap('欢迎，' . ($data['name'] ?? '同学'), '<p>账号已创建：' . htmlspecialchars((string)($data['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>' . $btn((string)($data['url'] ?? lf_abs_url('/courses')), '去看看课程')),
            ];
        case 'enrollment':
            return [
                '已加入课程：' . ($data['course'] ?? ''),
                $wrap('报名成功', '<p>你已成功加入《' . htmlspecialchars((string)($data['course'] ?? ''), ENT_QUOTES, 'UTF-8') . '》。</p>' . $btn((string)($data['url'] ?? ''), '开始学习')),
            ];
        case 'reset':
            return [
                '重置你的密码',
                $wrap('重置密码', '<p>点击下面链接重置密码，1 小时内有效。若非本人操作请忽略。</p>' . $btn((string)($data['url'] ?? ''), '重置密码')),
            ];
        case 'assignment_graded':
            return [
                '作业已点评：' . ($data['title'] ?? ''),
                $wrap('作业点评', '<p>《' . htmlspecialchars((string)($data['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '》已点评。</p>'
                    . (!empty($data['feedback']) ? '<p style="background:#f5f6f8;padding:12px;border-radius:10px">' . nl2br(htmlspecialchars((string)$data['feedback'], ENT_QUOTES, 'UTF-8')) . '</p>' : '')
                    . $btn((string)($data['url'] ?? ''), '查看作业')),
            ];
        case 'certificate':
            return [
                '恭喜获得结业证书',
                $wrap('结业证书已颁发', '<p>《' . htmlspecialchars((string)($data['course'] ?? ''), ENT_QUOTES, 'UTF-8') . '》结业证书已颁发。</p>' . $btn((string)($data['url'] ?? ''), '查看证书')),
            ];
        case 'reminder':
            return [
                '继续你的学习',
                $wrap('别忘了继续学习', '<p>《' . htmlspecialchars((string)($data['course'] ?? ''), ENT_QUOTES, 'UTF-8') . '》还有进度未完成。</p>' . $btn((string)($data['url'] ?? ''), '继续学习')),
            ];
        default:
            return [$data['subject'] ?? $site, $wrap((string)($data['subject'] ?? $site), (string)($data['body'] ?? ''))];
    }
}

function lf_mail_template_send(string $to, string $template, array $data, string $toName = ''): bool
{
    [$subject, $html] = lf_mail_template($template, $data);
    return lf_mail_send($to, $subject, $html, $toName);
}
