<?php

function course_templates_file(): string
{
    return LF_DATA_DIR . '/course-templates.json';
}

function course_templates(): array
{
    $all = json_read(course_templates_file());
    usort($all, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $all;
}

function course_template_find(string $id): ?array
{
    foreach (course_templates() as $t) if (($t['id'] ?? '') === $id) return $t;
    return null;
}

function course_template_from_course(array $course, string $name = ''): array
{
    $chapters = [];
    foreach ((array)($course['chapters'] ?? []) as $ch) {
        $lessons = [];
        foreach ((array)($ch['lessons'] ?? []) as $l) {
            $lessons[] = [
                'type' => $l['type'] ?? 'article',
                'title' => $l['title'] ?? '',
                'duration' => (int)($l['duration'] ?? 0),
                'content' => (string)($l['content'] ?? ''),
            ];
        }
        $chapters[] = ['title' => $ch['title'] ?? '', 'summary' => $ch['summary'] ?? '', 'lessons' => $lessons];
    }
    $row = [
        'id' => 'tpl_' . bin2hex(random_bytes(4)),
        'name' => $name !== '' ? $name : ((string)($course['title'] ?? '课程') . ' 模板'),
        'type' => (string)($course['type'] ?? '单课'),
        'level' => (string)($course['level'] ?? ''),
        'certificate' => !empty($course['certificate']),
        'chapters' => $chapters,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    json_update(course_templates_file(), function (array $all) use ($row) {
        array_unshift($all, $row);
        return array_slice($all, 0, 100);
    });
    return $row;
}

function course_template_delete(string $id): void
{
    json_update(course_templates_file(), function (array $all) use ($id) {
        return array_values(array_filter($all, fn($t) => ($t['id'] ?? '') !== $id));
    });
}

function course_from_template(string $templateId): ?array
{
    $t = course_template_find($templateId);
    if ($t === null) return null;
    return course_save(course_normalize([
        'title' => (string)$t['name'],
        'type' => (string)($t['type'] ?? '单课'),
        'level' => (string)($t['level'] ?? ''),
        'certificate' => !empty($t['certificate']),
        'status' => 'draft',
        'chapters' => (array)($t['chapters'] ?? []),
    ]));
}

function course_copy_chapter(string $fromCourseId, int $chapterIndex, string $toCourseId): ?array
{
    $from = course_find($fromCourseId);
    $to = course_find($toCourseId);
    if ($from === null || $to === null) return null;
    $chapter = $from['chapters'][$chapterIndex] ?? null;
    if ($chapter === null) return null;
    $lessons = [];
    foreach ((array)($chapter['lessons'] ?? []) as $l) {
        $copy = $l;
        unset($copy['id']);
        $lessons[] = $copy;
    }
    $to['chapters'][] = ['title' => (string)($chapter['title'] ?? ''), 'summary' => (string)($chapter['summary'] ?? ''), 'lessons' => $lessons];
    return course_save(course_normalize($to));
}

function course_import_markdown(string $md): array
{
    $md = str_replace(["\r\n", "\r"], "\n", trim($md));
    $chapters = [];
    $curChapter = null;
    $curLesson = null;
    $buf = [];
    $flushLesson = function () use (&$curLesson, &$buf, &$curChapter) {
        if ($curLesson !== null && $curChapter !== null) {
            $curLesson['content'] = lf_md_to_html(implode("\n", $buf));
            $curChapter['lessons'][] = $curLesson;
        }
        $curLesson = null;
        $buf = [];
    };
    $flushChapter = function () use (&$curChapter, &$chapters, $flushLesson) {
        $flushLesson();
        if ($curChapter !== null) $chapters[] = $curChapter;
        $curChapter = null;
    };
    foreach (explode("\n", $md) as $line) {
        if (preg_match('/^#\s+(.*)$/', $line, $m)) {
            $flushChapter();
            $curChapter = ['title' => trim($m[1]), 'summary' => '', 'lessons' => []];
            continue;
        }
        if (preg_match('/^##\s+(.*)$/', $line, $m)) {
            if ($curChapter === null) $curChapter = ['title' => '导入内容', 'summary' => '', 'lessons' => []];
            $flushLesson();
            $curLesson = ['type' => 'article', 'title' => trim($m[1]), 'duration' => 0, 'content' => ''];
            continue;
        }
        $buf[] = $line;
    }
    $flushChapter();
    if (!$chapters && trim($md) !== '') {
        $chapters[] = ['title' => '导入内容', 'summary' => '', 'lessons' => [['type' => 'article', 'title' => '导入正文', 'duration' => 0, 'content' => lf_md_to_html($md)]]];
    }
    return $chapters;
}
