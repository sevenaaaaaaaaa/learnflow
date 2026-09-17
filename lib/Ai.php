<?php

function ai_config(): array
{
    return array_merge([
        'enabled' => false,
        'base_url' => 'https://api.deepseek.com/v1',
        'api_key' => '',
        'model' => 'deepseek-chat',
        'daily_limit' => 200,
    ], (array)(lf_setting_get('ai') ?: []));
}

function ai_enabled(): bool
{
    $c = ai_config();
    return !empty($c['enabled']) && $c['api_key'] !== '';
}

function ai_usage_file(): string
{
    return LF_DATA_DIR . '/ai-usage.json';
}

function ai_budget_ok(): bool
{
    $cfg = ai_config();
    if ((int)$cfg['daily_limit'] <= 0) return true;
    $usage = json_read(ai_usage_file());
    return (int)($usage[date('Y-m-d')] ?? 0) < (int)$cfg['daily_limit'];
}

function ai_usage_inc(): void
{
    json_update(ai_usage_file(), function (array $u) {
        $day = date('Y-m-d');
        $u[$day] = (int)($u[$day] ?? 0) + 1;
        if (count($u) > 60) {
            ksort($u);
            $u = array_slice($u, -60, null, true);
        }
        return $u;
    });
}

function ai_chat(array $messages, float $temperature = 0.7, int $maxTokens = 1500, int $timeout = 60): ?string
{
    if (!ai_enabled() || !ai_budget_ok()) return null;
    $cfg = ai_config();
    $payload = json_encode([
        'model' => $cfg['model'],
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
        'stream' => false,
    ], JSON_UNESCAPED_UNICODE);

    $endpoint = rtrim($cfg['base_url'], '/') . '/chat/completions';
    $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['api_key']];

    $resp = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            @file_put_contents(LF_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] AI curl error: ' . curl_error($ch) . "\n", FILE_APPEND);
        }
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . $cfg['api_key'],
            'content' => $payload,
            'timeout' => $timeout,
            'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents($endpoint, false, $ctx);
    }
    if (!is_string($resp) || $resp === '') {
        return null;
    }
    $data = json_decode($resp, true);
    $text = $data['choices'][0]['message']['content'] ?? null;
    if (is_string($text)) {
        ai_usage_inc();
        return $text;
    }
    @file_put_contents(LF_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] AI bad response: ' . mb_substr($resp, 0, 500) . "\n", FILE_APPEND);
    return null;
}

function ai_chat_json(array $messages, int $maxTokens = 2000): ?array
{
    $text = ai_chat($messages, 0.4, $maxTokens);
    if ($text === null) return null;
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?|```$/m', '', $text);
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end < $start) return null;
    $data = json_decode(substr($text, $start, $end - $start + 1), true);
    return is_array($data) ? $data : null;
}

function ai_grade_assignment(array $assignment, string $content): ?array
{
    $sys = '你是训练营作业点评老师。请用中文给出简短、具体、鼓励性的点评，并给出 0-100 的分数。只输出合法 JSON：{"grade": number, "feedback": string}。';
    $user = "作业标题：" . ($assignment['title'] ?? '') . "\n作业说明：" . ($assignment['description'] ?? '') . "\n学员提交：\n" . mb_substr($content, 0, 4000);
    $data = ai_chat_json([
        ['role' => 'system', 'content' => $sys],
        ['role' => 'user', 'content' => $user],
    ], 700);
    if ($data === null) return null;
    return [
        'grade' => isset($data['grade']) ? (float)$data['grade'] : null,
        'feedback' => (string)($data['feedback'] ?? ''),
    ];
}

function ai_generate_quiz(string $topic, int $count = 3, string $difficulty = '中等'): ?array
{
    $sys = '你是课程测验出题助手。只输出合法 JSON：{"questions":[{"type":"single|multiple|judge","title":string,"score":number,"options":[{"id":string,"text":string}],"answer":[string],"explanation":string}]}。'
        . '单选/判断给 2-4 个选项，判断固定选项 id 用 yes/no；多选可 3-5 个。answer 填正确选项 id 数组。题干与选项用中文。';
    $user = "主题：$topic\n难度：$difficulty\n题目数量：$count";
    $data = ai_chat_json([
        ['role' => 'system', 'content' => $sys],
        ['role' => 'user', 'content' => $user],
    ], 2500);
    if ($data === null || empty($data['questions']) || !is_array($data['questions'])) return null;
    return $data;
}

function ai_weekly_report(array $course, array $stats): ?string
{
    $sys = '你是训练营运营助理。请用中文写一份简洁的学习周报分析（150-250 字），包含整体进度判断、风险提醒和一条可执行建议。只输出正文，不要标题。';
    $user = "课程：" . ($course['title'] ?? '') . "\n学员数：" . ($stats['learners'] ?? 0)
        . "\n完课率：" . ($stats['rate'] ?? 0) . "%\n近 7 天活跃：" . ($stats['active_7d'] ?? 0)
        . "\n风险学员数：" . ($stats['at_risk'] ?? 0) . "\n今日打卡：" . ($stats['checkin_today'] ?? 0);
    return ai_chat([
        ['role' => 'system', 'content' => $sys],
        ['role' => 'user', 'content' => $user],
    ], 0.7, 600);
}
