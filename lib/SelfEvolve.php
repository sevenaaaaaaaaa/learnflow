<?php

function evolution_file(): string
{
    return LF_DATA_DIR . '/evolution.json';
}

function evolution_state(): array
{
    return array_merge(['at' => '', 'proposals' => [], 'ai_plan' => '', 'ai_at' => ''], json_read(evolution_file()));
}

function evolution_proposals(): array
{
    $p = evolution_state()['proposals'];
    $order = ['high' => 0, 'medium' => 1, 'low' => 2];
    usort($p, fn($a, $b) => ($order[$a['severity'] ?? 'low'] ?? 2) <=> ($order[$b['severity'] ?? 'low'] ?? 2));
    return $p;
}

function evolution_signals(): array
{
    $out = [];
    $add = function (string $key, string $category, string $severity, string $title, string $detail, string $hint = '') use (&$out) {
        $out[] = ['id' => $key, 'category' => $category, 'severity' => $severity, 'title' => $title, 'detail' => $detail, 'hint' => $hint];
    };

    if (is_file(LF_LOG_FILE)) {
        $recent = 0;
        foreach (array_slice(file(LF_LOG_FILE) ?: [], -400) as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2})/', $line, $m) && strtotime($m[1]) >= strtotime('-1 day')) $recent++;
        }
        if ($recent > 0) $add('eng:php_errors', 'engineering', 'high', '近 24h 有 PHP 错误 ' . $recent . ' 条', '检查 data/php-error.log 并修复。', '/admin/audit.php');
    }

    $backups = glob(LF_DATA_DIR . '/backups/*', GLOB_ONLYDIR) ?: [];
    rsort($backups);
    $latest = $backups[0] ?? '';
    if ($latest === '' || filemtime($latest) < time() - 2 * 86400) {
        $add('eng:backup_stale', 'engineering', 'medium', '备份超过 2 天未更新', '检查 bin/backup.php 的 cron。', '/admin/settings.php');
    }

    foreach (course_all(true) as $c) {
        $cid = (string)$c['id'];
        $title = (string)($c['title'] ?? '');
        $lessons = course_lessons($c);
        if (!$lessons) { $add('content:no_lesson:' . $cid, 'content', 'medium', '课程无课时：' . $title, '上架课程没有任何课时。', '/admin/course-edit.php?id=' . urlencode($cid)); continue; }
        $emptyArticles = 0;
        foreach ($lessons as $l) if (($l['type'] ?? '') === 'article' && trim(strip_tags((string)($l['content'] ?? ''))) === '') $emptyArticles++;
        if ($emptyArticles > 0) $add('content:empty_article:' . $cid, 'content', 'low', '图文课时内容为空：' . $title, $emptyArticles . ' 个图文课时无正文，可用 AI 生成。', '/admin/ai.php');

        $drop = lesson_dropoff($cid);
        foreach ($drop as $d) {
            if ($d['started'] >= 3 && $d['rate'] < 40) {
                $add('content:dropoff:' . $cid . ':' . md5($d['title']), 'content', 'medium', '课时流失偏高：' . $title . ' / ' . $d['title'], '完成率 ' . $d['rate'] . '%（' . $d['done'] . '/' . $d['started'] . '），建议优化内容或拆分。', '/admin/analytics.php?course=' . urlencode($cid));
                break;
            }
        }
        foreach (quiz_question_stats($cid) as $q) {
            if ($q['total'] >= 3 && $q['rate'] < 50) {
                $add('content:quiz_weak:' . $cid, 'content', 'low', '题目正确率偏低：' . $title, '「' . mb_substr($q['question'], 0, 24) . '」正确率 ' . $q['rate'] . '%。', '/admin/analytics.php?course=' . urlencode($cid));
                break;
            }
        }

        $risk = progress_at_risk($cid);
        if (count($risk) > 0) $add('ops:at_risk:' . $cid, 'ops', 'medium', '风险学员 ' . count($risk) . ' 名：' . $title, '进度低且近 7 天不活跃，建议触达（可经 UserLoop）。', '/admin/analytics.php?course=' . urlencode($cid));
    }

    $pendingGrade = 0;
    foreach (assignment_all() as $a) {
        foreach (assignment_submissions((string)$a['id']) as $s) if (empty($s['graded_at'])) $pendingGrade++;
    }
    if ($pendingGrade > 0) $add('ops:ungraded', 'ops', 'medium', '待批改作业 ' . $pendingGrade . ' 份', '及时点评可提升完课与复购。', '/admin/assignments.php');

    if (course_all(true)) {
        $ov = analytics_overview(30);
        if ((float)$ov['revenue'] <= 0) $add('biz:no_revenue', 'business', 'high', '近 30 天无付费', '检查定价/落地页/结账链路（PayFlow）。', '/admin/analytics.php');
        elseif ($ov['enrolled'] >= 5 && $ov['conv_paid'] < 10) $add('biz:low_conv', 'business', 'medium', '报名转付费偏低 ' . $ov['conv_paid'] . '%', '可优化试看/优惠券/页面。', '/admin/analytics.php');
    }

    return $out;
}

function evolution_generate(): array
{
    $detected = evolution_signals();
    $state = evolution_state();
    $prev = [];
    foreach ($state['proposals'] as $p) $prev[(string)($p['id'] ?? '')] = $p;

    $next = [];
    $now = date('Y-m-d H:i:s');
    foreach ($detected as $d) {
        $p = $prev[$d['id']] ?? [];
        $p = array_merge($d, [
            'status' => in_array(($p['status'] ?? ''), ['accepted', 'resolved'], true) ? $p['status'] : 'open',
            'created_at' => (string)($p['created_at'] ?? $now),
            'updated_at' => $now,
        ]);
        $next[] = $p;
    }
    $detectedIds = array_column($detected, 'id');
    foreach ($prev as $id => $p) {
        if (!in_array($id, $detectedIds, true) && ($p['status'] ?? 'open') === 'open') {
            $p['status'] = 'resolved';
            $p['updated_at'] = $now;
            $next[] = $p;
        }
    }

    $state['proposals'] = $next;
    $state['at'] = $now;
    json_write(evolution_file(), $state);
    return $next;
}

function evolution_set_status(string $id, string $status): void
{
    if (!in_array($status, ['open', 'accepted', 'resolved', 'ignored'], true)) return;
    json_update(evolution_file(), function (array $s) use ($id, $status) {
        foreach (($s['proposals'] ?? []) as $i => $p) if (($p['id'] ?? '') === $id) $s['proposals'][$i]['status'] = $status;
        return $s;
    });
}

function evolution_ai_plan_set(string $text): void
{
    json_update(evolution_file(), function (array $s) use ($text) {
        $s['ai_plan'] = $text;
        $s['ai_at'] = date('Y-m-d H:i:s');
        return $s;
    });
}

function evolution_ai_prompt(): string
{
    $lines = [];
    foreach (evolution_proposals() as $p) {
        if (($p['status'] ?? '') === 'resolved') continue;
        $lines[] = '- [' . $p['severity'] . '/' . $p['category'] . '] ' . $p['title'] . '：' . $p['detail'];
    }
    return "以下是 LearnFlow（知识付费课程交付系统）当前体检发现的问题，请给出按优先级排序的改进计划（中文、条目化、每条给出具体动作与预期指标），不超过 400 字：\n" . implode("\n", $lines);
}
