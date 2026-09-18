<?php

require_once __DIR__ . '/Ai.php';

function ai_strip_fences(string $text): string
{
    return trim(preg_replace('/^```(?:html|markdown|md)?|```$/m', '', trim($text)));
}

function ai_lesson_content(array $course, array $lesson, string $hint = ''): ?string
{
    $system = '你是课程内容助理。根据课程与课时信息撰写该课时的讲义正文，用中文，输出 HTML 片段（可用 <h2> <h3> <p> <ul> <li> <blockquote> <pre> <strong>）。只输出正文 HTML，不要解释、不要 Markdown 代码围栏。';
    $neighbors = [];
    foreach (course_lessons($course) as $l) $neighbors[] = (string)($l['title'] ?? '');
    $user = "课程：" . ($course['title'] ?? '') . "\n课程简介：" . mb_substr((string)($course['summary'] ?? ''), 0, 300)
        . "\n课时：" . ($lesson['title'] ?? '') . "（类型 " . ($lesson['type'] ?? 'article') . "）"
        . "\n本课程课时目录：" . implode('、', array_slice($neighbors, 0, 20))
        . ($hint !== '' ? "\n额外要求：" . $hint : '')
        . "\n请写 400-800 字、有结构、可直接作为学员阅读的讲义。";
    $out = ai_chat([['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]], 0.7, 1400);
    return $out === null ? null : ai_strip_fences($out);
}

function ai_marketing_types(): array
{
    return [
        'page' => '招生落地页文案',
        'moments' => '朋友圈文案',
        'community' => '社群话术',
        'live' => '直播脚本',
        'shortvideo' => '短视频口播稿',
        'ppt' => 'PPT 大纲',
        'email' => '邮件推广文案',
    ];
}

function ai_marketing(string $type, string $topic, string $extra = ''): ?string
{
    $systems = [
        'page' => '你是知识付费招生文案专家。输出一份中文招生落地页文案，包含：主标题、副标题、痛点、课程亮点 3-5 条、适合人群、行动号召。用 Markdown。只输出正文。',
        'moments' => '你是私域运营专家。写 3 条中文朋友圈文案（每条含钩子+价值+行动号召+表情点缀），适合知识付费课程推广。只输出正文。',
        'community' => '你是社群运营专家。写一段中文社群话术（开营引导/催作业/促复购各一句），口语化、有温度。只输出正文。',
        'live' => '你是直播策划。输出一份中文直播脚本大纲：暖场、主题拆解、干货点、答疑、成交话术、结尾。用 Markdown。只输出正文。',
        'shortvideo' => '你是短视频编导。写一条 60-90 秒中文口播稿（开头 3 秒钩子、正文 2-3 个点、结尾引导），并标注分镜。只输出正文。',
        'ppt' => '你是课程设计。输出一份中文 PPT 大纲：每页标题 + 3-5 条要点，8-12 页。用 Markdown 列表。只输出正文。',
        'email' => '你是邮件营销专家。写一封中文课程推广邮件：主题行 + 正文 + 行动号召。只输出正文。',
    ];
    $system = $systems[$type] ?? $systems['page'];
    $user = "主题/课程：" . $topic . ($extra !== '' ? "\n补充信息：" . $extra : '');
    return ai_chat([['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]], 0.8, 1800);
}

function ai_outline_from_text(string $text, string $title = ''): ?array
{
    $system = '你是课程设计助手。请依据下方资料，产出一门课的大纲。只输出合法 JSON：{"title":string,"chapters":[{"title":string,"summary":string,"lessons":[{"title":string,"type":"article|video|quiz","duration":number}]}]}。章节 3-6 个，每章 2-4 课时。
中文标题。';
    $user = ($title !== '' ? "课程标题（可参考）：$title\n" : '') . "资料：\n" . mb_substr($text, 0, 12000);
    $data = ai_chat_json([['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]], 3000);
    if ($data === null || empty($data['chapters']) || !is_array($data['chapters'])) return null;
    return $data;
}

function ai_drafts_file(): string
{
    return LF_DATA_DIR . '/ai-drafts.json';
}

function ai_draft_save(string $kind, string $title, string $content): array
{
    $row = ['id' => 'dr_' . bin2hex(random_bytes(4)), 'kind' => $kind, 'title' => mb_substr($title, 0, 120), 'content' => $content, 'created_at' => date('Y-m-d H:i:s')];
    json_update(ai_drafts_file(), function (array $all) use ($row) {
        array_unshift($all, $row);
        return array_slice($all, 0, 200);
    });
    return $row;
}

function ai_drafts(): array
{
    return json_read(ai_drafts_file());
}
