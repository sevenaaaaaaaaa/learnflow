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
    $add = function (string $key, string $category, string $severity, string $title, string $detail, string $hint = '', array $action = []) use (&$out) {
        $out[] = ['id' => $key, 'category' => $category, 'severity' => $severity, 'title' => $title, 'detail' => $detail, 'hint' => $hint, 'action' => $action];
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
        $firstEmpty = null;
        foreach ($lessons as $l) {
            if (($l['type'] ?? '') === 'article' && trim(strip_tags((string)($l['content'] ?? ''))) === '') {
                $emptyArticles++;
                if ($firstEmpty === null) $firstEmpty = $l;
            }
        }
        if ($emptyArticles > 0) $add('content:empty_article:' . $cid, 'content', 'low', '图文课时内容为空：' . $title, $emptyArticles . ' 个图文课时无正文，可用 AI 生成。', '/admin/ai.php', ['type' => 'lesson_content', 'course_id' => $cid, 'lesson_id' => (string)($firstEmpty['id'] ?? '')]);

        $drop = lesson_dropoff($cid);
        foreach ($drop as $d) {
            if ($d['started'] >= 3 && $d['rate'] < 40) {
                $add('content:dropoff:' . $cid . ':' . md5($d['title']), 'content', 'medium', '课时流失偏高：' . $title . ' / ' . $d['title'], '完成率 ' . $d['rate'] . '%（' . $d['done'] . '/' . $d['started'] . '），建议优化内容或拆分。', '/admin/analytics.php?course=' . urlencode($cid), ['type' => 'improve_hint', 'course_id' => $cid, 'lesson_title' => $d['title'], 'rate' => $d['rate']]);
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
        if (count($risk) > 0) $add('ops:at_risk:' . $cid, 'ops', 'medium', '风险学员 ' . count($risk) . ' 名：' . $title, '进度低且近 7 天不活跃，建议触达（可经 UserLoop）。', '/admin/analytics.php?course=' . urlencode($cid), ['type' => 'userloop_signal', 'course_id' => $cid]);
    }

    $pendingGrade = 0;
    foreach (assignment_all() as $a) {
        foreach (assignment_submissions((string)$a['id']) as $s) if (empty($s['graded_at'])) $pendingGrade++;
    }
    if ($pendingGrade > 0) $add('ops:ungraded', 'ops', 'medium', '待批改作业 ' . $pendingGrade . ' 份', '及时点评可提升完课与复购。', '/admin/assignments.php');

    if (course_all(true)) {
        $ov = analytics_overview(30);
        $mkTopic = (string)(lf_setting_get('site_name') ?: 'LearnFlow') . ' 课程招生';
        if ((float)$ov['revenue'] <= 0) $add('biz:no_revenue', 'business', 'high', '近 30 天无付费', '检查定价/落地页/结账链路（PayFlow）。', '/admin/analytics.php', ['type' => 'marketing', 'mtype' => 'page', 'topic' => $mkTopic]);
        elseif ($ov['enrolled'] >= 5 && $ov['conv_paid'] < 10) $add('biz:low_conv', 'business', 'medium', '报名转付费偏低 ' . $ov['conv_paid'] . '%', '可优化试看/优惠券/页面。', '/admin/analytics.php', ['type' => 'marketing', 'mtype' => 'moments', 'topic' => $mkTopic]);
    }

    return $out;
}

function evolution_run_action(array $action): array
{
    $type = (string)($action['type'] ?? '');
    if ($type === 'lesson_content') {
        if (!ai_enabled()) return ['ok' => false, 'result' => 'AI 未启用'];
        $course = course_find((string)($action['course_id'] ?? ''));
        if ($course === null) return ['ok' => false, 'result' => '课程不存在'];
        $lesson = course_lesson_find($course, (string)($action['lesson_id'] ?? ''));
        if ($lesson === null) return ['ok' => false, 'result' => '课时不存在'];
        $html = ai_lesson_content($course, $lesson);
        if ($html === null) return ['ok' => false, 'result' => 'AI 生成失败'];
        foreach ((array)$course['chapters'] as $ci => $ch) {
            foreach ((array)($ch['lessons'] ?? []) as $li => $l) {
                if ((string)($l['id'] ?? '') === (string)$lesson['id']) $course['chapters'][$ci]['lessons'][$li]['content'] = $html;
            }
        }
        $saved = course_save(course_normalize($course));
        revision_snapshot($saved, 'self-evolve');
        return ['ok' => true, 'result' => '已为课时「' . (string)($lesson['title'] ?? '') . '」生成并写入讲义（' . mb_strlen(strip_tags($html)) . ' 字）'];
    }
    if ($type === 'marketing') {
        if (!ai_enabled()) return ['ok' => false, 'result' => 'AI 未启用'];
        $text = ai_marketing((string)($action['mtype'] ?? 'page'), (string)($action['topic'] ?? ''));
        if ($text === null) return ['ok' => false, 'result' => 'AI 生成失败'];
        $draft = ai_draft_save((string)($action['mtype'] ?? 'page'), (string)($action['topic'] ?? ''), $text);
        return ['ok' => true, 'result' => '已生成文案草稿（' . mb_strlen($text) . ' 字），见 AI 工作台草稿库：' . $draft['id']];
    }
    if ($type === 'improve_hint') {
        if (!ai_enabled()) return ['ok' => false, 'result' => 'AI 未启用'];
        $prompt = '这是课程「' . (string)($action['lesson_title'] ?? '') . '」，完成率仅 ' . (int)($action['rate'] ?? 0) . '%。请给出 3 条具体的内容优化建议（中文、条目化）。';
        $text = ai_chat([['role' => 'system', 'content' => '你是课程内容优化专家。'], ['role' => 'user', 'content' => $prompt]], 0.6, 600);
        if ($text === null) return ['ok' => false, 'result' => 'AI 生成失败'];
        $draft = ai_draft_save('improve', (string)($action['lesson_title'] ?? ''), $text);
        return ['ok' => true, 'result' => '已生成优化建议草稿：' . $draft['id']];
    }
    if ($type === 'reminder') {
        $course = course_find((string)($action['course_id'] ?? ''));
        if ($course === null) return ['ok' => false, 'result' => '课程不存在'];
        $n = 0;
        foreach (progress_at_risk((string)$course['id']) as $r) {
            $student = student_get((string)$r['student_id']);
            if ($student === null) continue;
            lf_emit('study.reminder', [
                'student_id' => (string)$r['student_id'],
                'name' => (string)($student['name'] ?? ''),
                'email' => (string)($student['email'] ?? ''),
                'course_id' => (string)$course['id'],
                'course_title' => (string)($course['title'] ?? ''),
                'link' => '/learn/' . rawurlencode((string)($course['slug'] ?? $course['id'])),
            ]);
            $n++;
        }
        return ['ok' => true, 'result' => '已向 ' . $n . ' 名风险学员发送学习提醒'];
    }
    if ($type === 'userloop_signal') {
        $course = course_find((string)($action['course_id'] ?? ''));
        if ($course === null) return ['ok' => false, 'result' => '课程不存在'];
        $n = 0;
        foreach (progress_at_risk((string)$course['id']) as $r) {
            matrix_reengage((string)$r['student_id'], (string)$course['id'], 'at_risk');
            $n++;
        }
        return ['ok' => true, 'result' => '已向 UserLoop 发出 ' . $n . ' 条再激活请求（reengage_requested），由其编排触达'];
    }
    if ($type === 'mflow_distribute') {
        $topic = (string)($action['topic'] ?? '');
        $res = matrix_call('mflow', (string)($action['path'] ?? '/api/batch/create'), ['type' => 'content', 'topic' => $topic, 'source' => 'learnflow']);
        return ['ok' => !empty($res['ok']), 'result' => !empty($res['ok']) ? '已提交 MFlow 分发' : ('MFlow 未就绪/未配置：' . (string)($res['error'] ?? ('HTTP ' . (int)($res['code'] ?? 0))))];
    }
    if ($type === 'inflo_topics') {
        $res = matrix_call('inflow', (string)($action['path'] ?? '/api/v1/insights'), [], 'GET');
        if (empty($res['ok'])) return ['ok' => false, 'result' => 'inFlow 未就绪/未配置：' . (string)($res['error'] ?? ('HTTP ' . (int)($res['code'] ?? 0)))];
        $text = (string)$res['body'];
        if (!ai_enabled()) return ['ok' => true, 'result' => '已取回 inFlow 洞察（' . mb_strlen($text) . ' 字节），AI 未开启未生成选题'];
        $draft = ai_draft_save('topic', 'inFlow 选题灵感', mb_substr($text, 0, 4000));
        return ['ok' => true, 'result' => '已基于 inFlow 洞察生成选题草稿：' . $draft['id']];
    }
    return ['ok' => false, 'result' => '该建议暂无自动动作'];
}

function evolution_execute(string $id, bool $auto = false): array
{
    $proposal = null;
    foreach (evolution_state()['proposals'] as $p) if (($p['id'] ?? '') === $id) { $proposal = $p; break; }
    if ($proposal === null) return ['ok' => false, 'result' => '建议不存在'];
    $action = (array)($proposal['action'] ?? []);
    $res = evolution_run_action($action);
    if (function_exists('strategy_touch_on_execute')) {
        strategy_touch_on_execute($proposal);
        if (empty($res['ok'])) strategy_verdict($id, false);
    }
    $status = !empty($res['ok']) ? 'executed' : 'failed';
    json_update(evolution_file(), function (array $s) use ($id, $status, $res, $auto) {
        foreach (($s['proposals'] ?? []) as $i => $p) {
            if (($p['id'] ?? '') === $id) {
                $s['proposals'][$i]['status'] = $status;
                $s['proposals'][$i]['result'] = (string)($res['result'] ?? '');
                $s['proposals'][$i]['executed_at'] = date('Y-m-d H:i:s');
                $s['proposals'][$i]['auto'] = $auto;
            }
        }
        return $s;
    });
    return $res;
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
        if (in_array($id, $detectedIds, true)) continue;
        $st = (string)($p['status'] ?? 'open');
        if ($st === 'open') { $p['status'] = 'resolved'; }
        elseif ($st === 'executed') { $p['status'] = 'verified'; $p['verified_at'] = $now; if (function_exists('strategy_verdict')) strategy_verdict($id, true); }
        $p['updated_at'] = $now;
        $next[] = $p;
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
