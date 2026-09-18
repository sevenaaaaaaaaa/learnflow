<?php

require_once __DIR__ . '/Ai.php';

function ai_tokens(string $text): array
{
    $text = mb_strtolower(strip_tags($text));
    $tokens = [];
    if (preg_match_all('/[a-z0-9]{2,}/', $text, $m)) $tokens = array_merge($tokens, $m[0]);
    if (preg_match_all('/[\x{4e00}-\x{9fff}]+/u', $text, $m2)) {
        foreach ($m2[0] as $seg) {
            $len = mb_strlen($seg);
            if ($len === 1) continue;
            for ($i = 0; $i < $len - 1; $i++) $tokens[] = mb_substr($seg, $i, 2);
        }
    }
    return $tokens;
}

function ai_retrieve(array $course, string $question, int $top = 5): array
{
    $qt = array_count_values(ai_tokens($question));
    if (!$qt) return ['sources' => [], 'contexts' => []];

    $scored = [];
    foreach (course_lessons($course) as $lesson) {
        $text = (string)($lesson['title'] ?? '') . ' ' . (string)($lesson['title'] ?? '') . ' ' . (string)($lesson['content'] ?? '');
        $lt = array_count_values(ai_tokens($text));
        $score = 0;
        foreach ($qt as $tok => $qcount) {
            if (isset($lt[$tok])) $score += $qcount * (1 + min(3, $lt[$tok]));
        }
        if ($score > 0) $scored[] = ['lesson' => $lesson, 'score' => $score];
    }
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    $scored = array_slice($scored, 0, $top);

    $sources = [];
    $contexts = [];
    foreach ($scored as $row) {
        $l = $row['lesson'];
        $title = (string)($l['title'] ?? '');
        $content = trim(strip_tags((string)($l['content'] ?? '')));
        $content = mb_substr($content, 0, 700);
        $sources[] = $title;
        $contexts[] = '【课时：' . $title . '】' . ($l['chapter_title'] ?? '') . "\n" . ($content !== '' ? $content : '（视频/测验课时，无文字内容）');
    }
    return ['sources' => $sources, 'contexts' => $contexts];
}

function ai_course_ask(array $course, string $question): array
{
    if (!ai_enabled()) throw new RuntimeException('AI 未启用');
    $question = trim($question);
    if ($question === '') throw new InvalidArgumentException('请输入问题');
    $r = ai_retrieve($course, $question, 5);
    if (!$r['contexts']) throw new RuntimeException('课程资料中未找到相关内容');
    $system = '你是该课程的助教。只能依据下面的课程资料回答；资料不足时明确说明，并建议向讲师提问。用中文回答，简洁分点，最后一行列出引用的课时标题。';
    $user = "课程：" . ($course['title'] ?? '') . "\n\n课程资料：\n" . implode("\n\n", $r['contexts']) . "\n\n学员问题：" . $question;
    $answer = ai_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], 0.3, 800);
    if ($answer === null) throw new RuntimeException('AI 生成失败，请稍后重试');
    return ['answer' => $answer, 'sources' => $r['sources']];
}

function ai_course_outline(string $title, string $summary = '', string $requirements = ''): ?array
{
    $system = '你是课程设计助手。只输出合法 JSON：{"chapters":[{"title":string,"summary":string,"lessons":[{"title":string,"type":"article|video|quiz","duration":number}]}]}。'
        . '章节 3-5 个，每章 2-4 个课时，循序渐进，标题用中文。';
    $user = "课程标题：$title\n简介：$summary\n额外要求：$requirements";
    $data = ai_chat_json([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ], 2500);
    if ($data === null || empty($data['chapters']) || !is_array($data['chapters'])) return null;
    return $data;
}

function ai_qa_log_file(): string
{
    return LF_DATA_DIR . '/ai-qa.json';
}

function ai_qa_record(string $courseId, string $studentId, string $question, string $answer, array $sources): void
{
    json_update(ai_qa_log_file(), function (array $all) use ($courseId, $studentId, $question, $answer, $sources) {
        $all[$courseId][] = ['at' => date('Y-m-d H:i:s'), 'student_id' => $studentId, 'q' => mb_substr($question, 0, 300), 'a' => mb_substr($answer, 0, 1200), 'sources' => $sources];
        if (count($all[$courseId]) > 300) $all[$courseId] = array_slice($all[$courseId], -300);
        return $all;
    });
}
