<?php

function lf_api_tools(): array
{
    $obj = fn(array $props, array $required = []) => ['type' => 'object', 'properties' => $props, 'required' => $required];
    $str = fn(string $d = '') => ['type' => 'string', 'description' => $d];
    $num = fn(string $d = '') => ['type' => 'number', 'description' => $d];
    $bool = fn(string $d = '') => ['type' => 'boolean', 'description' => $d];
    $arr = fn(string $d = '') => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => $d];

    return [
        'course.list' => [
            'scope' => 'read', 'description' => '列出课程（可选仅已上架）',
            'schema' => $obj(['published_only' => $bool('仅返回已上架')]),
            'handler' => function (array $p) {
                $out = [];
                foreach (course_all(!empty($p['published_only'])) as $c) {
                    $out[] = ['id' => $c['id'], 'slug' => $c['slug'] ?? $c['id'], 'title' => $c['title'] ?? '', 'status' => $c['status'] ?? '', 'price' => (float)($c['price'] ?? 0), 'lessons' => course_lesson_count($c), 'chapters' => count((array)($c['chapters'] ?? []))];
                }
                return ['courses' => $out, 'count' => count($out)];
            },
        ],
        'course.get' => [
            'scope' => 'read', 'description' => '获取单个课程（含章节/课时）',
            'schema' => $obj(['id_or_slug' => $str('课程 id 或 slug')], ['id_or_slug']),
            'handler' => function (array $p) {
                $c = course_find((string)$p['id_or_slug']);
                if ($c === null) throw new RuntimeException('课程不存在');
                return ['course' => $c];
            },
        ],
        'course.create' => [
            'scope' => 'write', 'description' => '创建课程（返回课程 id）',
            'schema' => $obj([
                'title' => $str('标题'), 'slug' => $str('URL 标识，可空'), 'subtitle' => $str(), 'summary' => $str(),
                'instructor' => $str(), 'type' => $str('单课/专栏/认证课/系列课/训练营'), 'level' => $str(),
                'price' => $num('价格，0=免费'), 'status' => $str('draft/published/archived'), 'certificate' => $bool(),
                'camp_start' => $str('开营日期 YYYY-MM-DD'), 'camp_end' => $str(),
            ], ['title']),
            'handler' => function (array $p) {
                $c = course_save(course_normalize($p));
                return ['course_id' => $c['id'], 'slug' => $c['slug'], 'status' => $c['status']];
            },
        ],
        'course.update' => [
            'scope' => 'write', 'description' => '更新课程字段（部分更新）',
            'schema' => $obj(['id' => $str('课程 id'), 'fields' => ['type' => 'object', 'description' => '要更新的字段']], ['id', 'fields']),
            'handler' => function (array $p) {
                $c = course_find((string)$p['id']);
                if ($c === null) throw new RuntimeException('课程不存在');
                $fields = (array)$p['fields'];
                $allowed = ['title', 'slug', 'subtitle', 'summary', 'cover', 'instructor', 'type', 'level', 'price', 'status', 'certificate', 'allow_invite', 'categories', 'tags', 'camp_start', 'camp_end', 'payflow_product_id', 'i18n'];
                foreach ($fields as $k => $v) if (in_array($k, $allowed, true)) $c[$k] = $v;
                $saved = course_save(course_normalize($c));
                return ['course_id' => $saved['id'], 'status' => $saved['status']];
            },
        ],
        'course.publish' => [
            'scope' => 'write', 'description' => '上架/下架课程',
            'schema' => $obj(['id' => $str('课程 id'), 'status' => $str('published 或 draft')], ['id', 'status']),
            'handler' => function (array $p) {
                $c = course_find((string)$p['id']);
                if ($c === null) throw new RuntimeException('课程不存在');
                $c['status'] = ($p['status'] === 'published') ? 'published' : 'draft';
                $saved = course_save(course_normalize($c));
                return ['course_id' => $saved['id'], 'status' => $saved['status']];
            },
        ],
        'chapter.add' => [
            'scope' => 'write', 'description' => '给课程追加章节',
            'schema' => $obj(['course_id' => $str(), 'title' => $str(), 'summary' => $str()], ['course_id', 'title']),
            'handler' => function (array $p) {
                $c = course_find((string)$p['course_id']);
                if ($c === null) throw new RuntimeException('课程不存在');
                $c['chapters'][] = ['title' => (string)$p['title'], 'summary' => (string)($p['summary'] ?? ''), 'lessons' => []];
                $saved = course_save(course_normalize($c));
                return ['course_id' => $saved['id'], 'chapter_count' => count((array)$saved['chapters'])];
            },
        ],
        'lesson.add' => [
            'scope' => 'write', 'description' => '给某章节追加课时（article/video/quiz/file/live）',
            'schema' => $obj([
                'course_id' => $str(), 'chapter_index' => ['type' => 'integer', 'description' => '章节序号，从 0 开始'],
                'title' => $str(), 'type' => $str('article/video/quiz/file/live'), 'content' => $str('图文 HTML'),
                'video' => $str('视频 URL 或 upload:相对路径'), 'quiz_id' => $str(), 'duration' => $num('分钟'), 'free' => $bool('试看'),
            ], ['course_id', 'title']),
            'handler' => function (array $p) {
                $c = course_find((string)$p['course_id']);
                if ($c === null) throw new RuntimeException('课程不存在');
                $ci = (int)($p['chapter_index'] ?? 0);
                if (!isset($c['chapters'][$ci])) throw new RuntimeException('章节不存在');
                $c['chapters'][$ci]['lessons'][] = [
                    'title' => (string)$p['title'],
                    'type' => (string)($p['type'] ?? 'article'),
                    'content' => (string)($p['content'] ?? ''),
                    'video' => (string)($p['video'] ?? ''),
                    'quiz_id' => (string)($p['quiz_id'] ?? ''),
                    'duration' => (int)($p['duration'] ?? 0),
                    'free' => !empty($p['free']),
                ];
                $saved = course_save(course_normalize($c));
                return ['course_id' => $saved['id'], 'lessons' => course_lesson_count($saved)];
            },
        ],
        'task.add' => [
            'scope' => 'write', 'description' => '给训练营加每日任务',
            'schema' => $obj(['course_id' => $str(), 'day_index' => ['type' => 'integer'], 'title' => $str(), 'description' => $str(), 'lesson_id' => $str()], ['course_id', 'title']),
            'handler' => function (array $p) {
                $t = task_save((string)$p['course_id'], ['day_index' => (int)($p['day_index'] ?? 1), 'title' => (string)$p['title'], 'description' => (string)($p['description'] ?? ''), 'lesson_id' => (string)($p['lesson_id'] ?? '')]);
                return ['task_id' => $t['id']];
            },
        ],
        'quiz.list' => [
            'scope' => 'read', 'description' => '列出课程测验',
            'schema' => $obj(['course_id' => $str()], ['course_id']),
            'handler' => function (array $p) {
                $out = [];
                foreach (quiz_for_course((string)$p['course_id']) as $q) {
                    $out[] = ['id' => $q['id'], 'title' => $q['title'] ?? '', 'kind' => $q['kind'] ?? 'chapter', 'questions' => count((array)($q['questions'] ?? []))];
                }
                return ['quizzes' => $out];
            },
        ],
        'quiz.create' => [
            'scope' => 'write', 'description' => '创建测验（含题目）',
            'schema' => $obj(['course_id' => $str(), 'title' => $str(), 'kind' => $str('chapter 或 final'), 'pass_score' => $num(), 'questions' => ['type' => 'array', 'description' => '题目数组，见文档']], ['course_id', 'title', 'questions']),
            'handler' => function (array $p) {
                $q = quiz_save(['course_id' => (string)$p['course_id'], 'title' => (string)$p['title'], 'kind' => (string)($p['kind'] ?? 'chapter'), 'pass_score' => (int)($p['pass_score'] ?? 0), 'questions' => (array)$p['questions']]);
                return ['quiz_id' => $q['id']];
            },
        ],
        'assignment.list' => [
            'scope' => 'read', 'description' => '列出课程作业及提交/批改统计',
            'schema' => $obj(['course_id' => $str()], ['course_id']),
            'handler' => function (array $p) {
                $stats = assignment_stats((string)$p['course_id']);
                $out = [];
                foreach (assignment_all((string)$p['course_id']) as $a) {
                    $out[] = array_merge(['id' => $a['id'], 'title' => $a['title'] ?? '', 'due_at' => $a['due_at'] ?? ''], $stats[(string)$a['id']] ?? []);
                }
                return ['assignments' => $out];
            },
        ],
        'assignment.create' => [
            'scope' => 'write', 'description' => '创建作业',
            'schema' => $obj(['course_id' => $str(), 'title' => $str(), 'description' => $str(), 'due_at' => $str(), 'allow_file' => $bool()], ['course_id', 'title']),
            'handler' => function (array $p) {
                $a = assignment_save(['course_id' => (string)$p['course_id'], 'title' => (string)$p['title'], 'description' => (string)($p['description'] ?? ''), 'due_at' => (string)($p['due_at'] ?? ''), 'allow_file' => !empty($p['allow_file'])]);
                return ['assignment_id' => $a['id']];
            },
        ],
        'assignment.grade' => [
            'scope' => 'write', 'description' => '给学员作业评分/点评（会通知学员）',
            'schema' => $obj(['assignment_id' => $str(), 'student_id' => $str(), 'grade' => $num(), 'feedback' => $str()], ['assignment_id', 'student_id']),
            'handler' => function (array $p) {
                assignment_grade((string)$p['assignment_id'], (string)$p['student_id'], isset($p['grade']) ? (float)$p['grade'] : null, (string)($p['feedback'] ?? ''));
                return ['ok' => true];
            },
        ],
        'enrollment.list' => [
            'scope' => 'read', 'description' => '列出课程报名学员与进度',
            'schema' => $obj(['course_id' => $str()], ['course_id']),
            'handler' => function (array $p) {
                $course = course_find((string)$p['course_id']);
                if ($course === null) throw new RuntimeException('课程不存在');
                $out = [];
                foreach (enroll_students((string)$course['id']) as $sid => $row) {
                    $s = student_get((string)$sid);
                    $sum = progress_summary((string)$sid, (string)$course['id'], $course);
                    $out[] = ['student_id' => (string)$sid, 'name' => $s['name'] ?? '', 'email' => $s['email'] ?? '', 'group' => $row['group'] ?? '', 'percent' => $sum['percent'], 'done' => $sum['done'], 'total' => $sum['total'], 'source' => $row['source'] ?? ''];
                }
                return ['enrollments' => $out, 'count' => count($out)];
            },
        ],
        'enrollment.add' => [
            'scope' => 'write', 'description' => '邮箱报名入学（不存在则建学员）',
            'schema' => $obj(['course_id' => $str(), 'email' => $str(), 'name' => $str(), 'group' => $str()], ['course_id', 'email']),
            'handler' => function (array $p) {
                $course = course_find((string)$p['course_id']);
                if ($course === null) throw new RuntimeException('课程不存在');
                $student = student_find_or_create(['email' => (string)$p['email'], 'name' => (string)($p['name'] ?? ''), 'source' => 'api']);
                enroll_add((string)$course['id'], (string)$student['id'], ['source' => 'api', 'group' => (string)($p['group'] ?? '')]);
                return ['student_id' => $student['id'], 'course_id' => $course['id']];
            },
        ],
        'enrollment.set_group' => [
            'scope' => 'write', 'description' => '设置学员在课程中的分组',
            'schema' => $obj(['course_id' => $str(), 'student_id' => $str(), 'group' => $str()], ['course_id', 'student_id', 'group']),
            'handler' => function (array $p) {
                enroll_set_group((string)$p['course_id'], (string)$p['student_id'], (string)$p['group']);
                return ['ok' => true];
            },
        ],
        'student.list' => [
            'scope' => 'read', 'description' => '列出全部学员',
            'schema' => $obj([]),
            'handler' => function () {
                $out = [];
                foreach (student_all() as $id => $s) $out[] = ['id' => (string)$id, 'name' => $s['name'] ?? '', 'email' => $s['email'] ?? '', 'created_at' => $s['created_at'] ?? ''];
                return ['students' => $out, 'count' => count($out)];
            },
        ],
        'student.get' => [
            'scope' => 'read', 'description' => '获取学员详情与已报课程',
            'schema' => $obj(['student_id' => $str()], ['student_id']),
            'handler' => function (array $p) {
                $s = student_get((string)$p['student_id']);
                if ($s === null) throw new RuntimeException('学员不存在');
                return ['student' => $s, 'enrollments' => array_keys(enroll_by_student((string)$p['student_id']))];
            },
        ],
        'student.upsert' => [
            'scope' => 'write', 'description' => '按邮箱创建或更新学员',
            'schema' => $obj(['email' => $str(), 'name' => $str(), 'phone' => $str()], ['email']),
            'handler' => function (array $p) {
                $s = student_find_or_create(['email' => (string)$p['email'], 'name' => (string)($p['name'] ?? ''), 'phone' => (string)($p['phone'] ?? ''), 'source' => 'api']);
                return ['student_id' => $s['id'], 'email' => $s['email']];
            },
        ],
        'notification.send' => [
            'scope' => 'write', 'description' => '发站内通知（可按学员或按课程群发，可选邮件）',
            'schema' => $obj(['student_id' => $str(), 'course_id' => $str(), 'title' => $str(), 'body' => $str(), 'mail' => $bool()], ['title']),
            'handler' => function (array $p) {
                $title = (string)$p['title'];
                $body = (string)($p['body'] ?? '');
                $withMail = !empty($p['mail']);
                $sent = 0;
                if (!empty($p['student_id'])) {
                    $ids = [(string)$p['student_id']];
                } elseif (!empty($p['course_id'])) {
                    $ids = array_keys(enroll_students((string)$p['course_id']));
                } else {
                    $ids = array_keys(student_all());
                }
                foreach ($ids as $sid) {
                    $s = student_get((string)$sid);
                    if ($s === null) continue;
                    notify_add((string)$sid, 'system', $title, $body, '');
                    if ($withMail && !empty($s['email'])) lf_mail_send((string)$s['email'], $title, '<div style="font-size:14px;line-height:1.9">' . nl2br(lf_e($body)) . '</div>', (string)($s['name'] ?? ''));
                    $sent++;
                }
                return ['sent' => $sent, 'with_mail' => $withMail];
            },
        ],
        'analytics.course' => [
            'scope' => 'read', 'description' => '课程经营数据（完课率/学习曲线/风险学员/打卡）',
            'schema' => $obj(['course_id' => $str()], ['course_id']),
            'handler' => function (array $p) {
                $course = course_find((string)$p['course_id']);
                if ($course === null) throw new RuntimeException('课程不存在');
                $learners = 0; $completed = 0;
                foreach (enroll_students((string)$course['id']) as $sid => $row) {
                    $learners++;
                    $sum = progress_summary((string)$sid, (string)$course['id'], $course);
                    if ($sum['total'] > 0 && $sum['done'] >= $sum['total']) $completed++;
                }
                $curve = progress_curve((string)$course['id']);
                return [
                    'course_id' => $course['id'],
                    'learners' => $learners,
                    'completed' => $completed,
                    'completion_rate' => $learners > 0 ? round($completed / $learners * 100, 1) : 0,
                    'active_7d' => $curve['active_7d'],
                    'at_risk' => progress_at_risk((string)$course['id']),
                    'checkin' => checkin_course_stats((string)$course['id']),
                    'lessons' => $curve['lessons'],
                ];
            },
        ],
        'certificate.list' => [
            'scope' => 'read', 'description' => '列出证书（可按课程/学员过滤）',
            'schema' => $obj(['course_id' => $str(), 'student_id' => $str()]),
            'handler' => function (array $p) {
                $out = [];
                foreach (cert_all() as $no => $c) {
                    if (!empty($p['course_id']) && ($c['course_id'] ?? '') !== $p['course_id']) continue;
                    if (!empty($p['student_id']) && ($c['student_id'] ?? '') !== $p['student_id']) continue;
                    $out[] = ['cert_no' => (string)$no, 'student_id' => $c['student_id'] ?? '', 'course_title' => $c['course_title'] ?? '', 'issued_at' => $c['issued_at'] ?? '', 'valid' => cert_verify((string)$no)];
                }
                return ['certificates' => $out, 'count' => count($out)];
            },
        ],
        'community.list' => [
            'scope' => 'read', 'description' => '查看课程圈子帖子',
            'schema' => $obj(['course_id' => $str()], ['course_id']),
            'handler' => function (array $p) {
                $out = [];
                foreach (post_all((string)$p['course_id']) as $post) {
                    $out[] = ['id' => $post['id'], 'name' => $post['name'], 'type' => $post['type'], 'title' => $post['title'], 'body' => $post['body'], 'likes' => count((array)($post['likes'] ?? [])), 'comments' => count((array)($post['comments'] ?? [])), 'created_at' => $post['created_at']];
                }
                return ['posts' => $out, 'count' => count($out)];
            },
        ],
        'ai.generate_quiz' => [
            'scope' => 'ai', 'description' => '用 AI 根据主题生成测验题目（不落库，返回题目）',
            'schema' => $obj(['topic' => $str(), 'count' => ['type' => 'integer'], 'difficulty' => $str()], ['topic']),
            'handler' => function (array $p) {
                if (!ai_enabled()) throw new RuntimeException('AI 未启用');
                $d = ai_generate_quiz((string)$p['topic'], (int)($p['count'] ?? 3), (string)($p['difficulty'] ?? '中等'));
                if ($d === null) throw new RuntimeException('AI 生成失败');
                return ['questions' => $d['questions']];
            },
        ],
        'ai.grade_assignment' => [
            'scope' => 'ai', 'description' => '用 AI 批改学员作业（返回评分与点评，不落库）',
            'schema' => $obj(['assignment_id' => $str(), 'student_id' => $str()], ['assignment_id', 'student_id']),
            'handler' => function (array $p) {
                if (!ai_enabled()) throw new RuntimeException('AI 未启用');
                $a = assignment_find((string)$p['assignment_id']);
                if ($a === null) throw new RuntimeException('作业不存在');
                $sub = assignment_submission((string)$a['id'], (string)$p['student_id']);
                if ($sub === null) throw new RuntimeException('该学员尚未提交');
                $r = ai_grade_assignment($a, (string)($sub['content'] ?? ''));
                if ($r === null) throw new RuntimeException('AI 生成失败');
                return $r;
            },
        ],
        'ai.weekly_report' => [
            'scope' => 'ai', 'description' => '生成课程运营周报（文本）',
            'schema' => $obj(['course_id' => $str()], ['course_id']),
            'handler' => function (array $p) {
                if (!ai_enabled()) throw new RuntimeException('AI 未启用');
                $course = course_find((string)$p['course_id']);
                if ($course === null) throw new RuntimeException('课程不存在');
                $learners = 0; $completed = 0;
                foreach (enroll_students((string)$course['id']) as $sid => $row) {
                    $learners++;
                    $sum = progress_summary((string)$sid, (string)$course['id'], $course);
                    if ($sum['total'] > 0 && $sum['done'] >= $sum['total']) $completed++;
                }
                $curve = progress_curve((string)$course['id']);
                $stats = ['learners' => $learners, 'rate' => $learners ? round($completed / $learners * 100) : 0, 'active_7d' => $curve['active_7d'], 'at_risk' => count(progress_at_risk((string)$course['id'])), 'checkin_today' => checkin_course_stats((string)$course['id'])['today']];
                $text = ai_weekly_report($course, $stats);
                if ($text === null) throw new RuntimeException('AI 生成失败');
                return ['report' => $text, 'stats' => $stats];
            },
        ],
    ];
}

function lf_api_tool_list(): array
{
    $tools = [];
    foreach (lf_api_tools() as $name => $def) {
        $tools[] = [
            'name' => $name,
            'description' => $def['description'],
            'inputSchema' => $def['schema'],
        ];
    }
    return $tools;
}

function lf_api_call(string $tool, array $params, array $ctx): array
{
    $tools = lf_api_tools();
    if (!isset($tools[$tool])) {
        return ['ok' => false, 'error' => '未知工具: ' . $tool, 'code' => 404];
    }
    $def = $tools[$tool];
    $scopes = (array)($ctx['scopes'] ?? []);
    if (!in_array($def['scope'], $scopes, true) && !in_array('*', $scopes, true)) {
        return ['ok' => false, 'error' => '缺少作用域: ' . $def['scope'], 'code' => 403];
    }
    foreach ((array)($def['schema']['required'] ?? []) as $field) {
        if (!isset($params[$field]) || $params[$field] === '') {
            return ['ok' => false, 'error' => "缺少参数: $field", 'code' => 422];
        }
    }
    try {
        $data = ($def['handler'])($params);
        return ['ok' => true, 'data' => $data];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'code' => 400];
    }
}
