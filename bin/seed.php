<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "seed 只能在 CLI 下运行\n");
    exit(1);
}

echo "LearnFlow seed\n";
echo "DATA_DIR = " . LF_DATA_DIR . "\n";

if (lf_admin_count() === 0) {
    lf_admin_create('admin', 'learnflow123', '主理人');
    echo "- 管理员: admin / learnflow123\n";
} else {
    echo "- 管理员已存在，跳过\n";
}

if (lf_setting_get('site_url') === null) {
    lf_setting_set('site_url', 'http://localhost:8080');
}

$finalQuiz = quiz_save([
    'title' => '结业测验',
    'kind' => 'final',
    'pass_score' => 2,
    'questions' => [
        ['type' => 'single', 'title' => '交付闭环的正确顺序是？', 'score' => 1, 'options' => [
            ['id' => 'o1', 'text' => '报名 → 学习 → 测验 → 证书'],
            ['id' => 'o2', 'text' => '证书 → 测验 → 学习'],
            ['id' => 'o3', 'text' => '学习 → 报名 → 证书'],
        ], 'answer' => ['o1'], 'explanation' => '先报名入学，学习后测验，通过发证。'],
        ['type' => 'judge', 'title' => '结业证书需要完成全部课时并通过结业测验。', 'score' => 1, 'options' => [
            ['id' => 'yes', 'text' => '正确'], ['id' => 'no', 'text' => '错误'],
        ], 'answer' => ['yes'], 'explanation' => ''],
        ['type' => 'multiple', 'title' => 'LearnFlow 支持哪些课时类型？', 'score' => 1, 'options' => [
            ['id' => 'a', 'text' => '图文'], ['id' => 'b', 'text' => '视频'], ['id' => 'c', 'text' => '测验'], ['id' => 'd', 'text' => '资料'],
        ], 'answer' => ['a', 'b', 'c', 'd'], 'explanation' => '四种均支持。'],
    ],
]);

$chapterQuiz = quiz_save([
    'title' => '第 1 章测验',
    'kind' => 'chapter',
    'pass_score' => 1,
    'questions' => [
        ['type' => 'single', 'title' => '报名后学员可以做什么？', 'score' => 1, 'options' => [
            ['id' => 'a', 'text' => '学习课时并记录进度'],
            ['id' => 'b', 'text' => '修改课程价格'],
        ], 'answer' => ['a'], 'explanation' => ''],
    ],
]);

$existing = course_find('rbe-camp-4');
if ($existing === null) {
    $course = course_normalize([
        'title' => 'R.B.E 训练营 · 第 4 期',
        'slug' => 'rbe-camp-4',
        'subtitle' => '从开营到结业的完整陪跑',
        'summary' => "一套 21 天陪跑训练营：先建立交付闭环，再完成第一个可交付作品。\n包含图文、视频、章节测验与结业测验，完成后颁发结业证书。",
        'instructor' => '芭乐派',
        'type' => '训练营',
        'level' => '入门',
        'price' => 999,
        'status' => 'published',
        'certificate' => true,
        'allow_invite' => true,
        'payflow_product_id' => 'rbe-camp-4',
        'chapters' => [
            [
                'title' => '第 1 章 · 开营与目标',
                'summary' => '明确交付目标与节奏',
                'lessons' => [
                    ['type' => 'video', 'title' => '开营导学', 'duration' => 8, 'video' => 'https://media.nownexts.com/learnflow/sample.mp4', 'free' => true],
                    ['type' => 'article', 'title' => '交付闭环总览', 'content' => '<p>LearnFlow 的交付闭环：<strong>报名 → 学习 → 测验 → 证书 → 复购</strong>。</p><p>本章你将完成开营目标设定。</p>'],
                    ['type' => 'quiz', 'title' => '第 1 章测验', 'quiz_id' => (string)$chapterQuiz['id']],
                ],
            ],
            [
                'title' => '第 2 章 · 内容与练习',
                'summary' => '动手产出第一份作业',
                'lessons' => [
                    ['type' => 'article', 'title' => '如何设计一份可交付作业', 'content' => '<p>作业要可验证、可复用、可展示。</p>'],
                    ['type' => 'file', 'title' => '作业模板下载', 'attachments' => [['name' => '作业模板.md', 'url' => 'https://media.nownexts.com/learnflow/template.md']]],
                ],
            ],
            [
                'title' => '第 3 章 · 结营',
                'summary' => '提交结业测验，领取证书',
                'lessons' => [
                    ['type' => 'article', 'title' => '结营说明', 'content' => '<p>完成全部课时并通过结业测验后，系统将自动颁发结业证书。</p>'],
                    ['type' => 'quiz', 'title' => '结业测验', 'quiz_id' => (string)$finalQuiz['id']],
                ],
            ],
        ],
    ]);
    $course = course_save($course);
    quiz_save(array_merge(quiz_find((string)$finalQuiz['id']), ['course_id' => (string)$course['id']]));
    quiz_save(array_merge(quiz_find((string)$chapterQuiz['id']), ['course_id' => (string)$course['id']]));
    foreach (course_lessons($course) as $l) {
        if (($l['type'] ?? '') === 'quiz' && !empty($l['quiz_id'])) {
            $q = quiz_find((string)$l['quiz_id']);
            if ($q) quiz_save(array_merge($q, ['lesson_id' => (string)$l['id']]));
        }
    }
    echo "- 课程: {$course['title']} ({$course['id']})\n";
} else {
    $course = $existing;
    echo "- 课程已存在，跳过\n";
}

if (invite_find('RBECAMP4') === null) {
    invite_create((string)$course['id'], ['code' => 'RBECAMP4', 'max_uses' => 0, 'note' => '第 4 期招生']);
    echo "- 邀请码: RBECAMP4\n";
}

try {
    $student = student_by_email('demo@learnflow.local') ?: student_create([
        'email' => 'demo@learnflow.local',
        'password' => 'demo123',
        'name' => '演示学员',
        'source' => 'seed',
    ]);
} catch (Throwable $e) {
    $student = student_by_email('demo@learnflow.local');
    echo "! 学员 seed 失败: " . $e->getMessage() . "\n";
}

if ($student !== null) {
    enroll_add((string)$course['id'], (string)$student['id'], ['source' => 'invite', 'invite_code' => 'RBECAMP4']);
    foreach (course_lessons($course) as $l) {
        if (($l['type'] ?? '') === 'article' || ($l['type'] ?? '') === 'video') {
            progress_set((string)$student['id'], (string)$course['id'], (string)$l['id'], ['done' => true, 'position' => 10, 'seconds' => 120]);
        }
    }
    echo "- 学员: demo@learnflow.local / demo123（已入学并部分完成）\n";
}

echo "完成。启动本地预览： php -S 127.0.0.1:8080 -t " . LF_ROOT . "\n";
