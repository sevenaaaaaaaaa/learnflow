<?php

$tmp = sys_get_temp_dir() . '/learnflow-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0755, true);
putenv('LF_DATA_DIR=' . $tmp);
putenv('LF_UPLOAD_DIR=' . $tmp . '/uploads');
@mkdir($tmp . '/uploads', 0755, true);
$_SERVER['HTTP_HOST'] = 'localhost';
putenv('LF_ENV=dev');

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $extra = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  ok  $label\n";
    } else {
        $fail++;
        echo "FAIL  $label" . ($extra !== '' ? " ($extra)" : '') . "\n";
    }
}

echo "LearnFlow domain tests\n";

$quiz = quiz_save([
    'title' => '结业测验',
    'kind' => 'final',
    'pass_score' => 1,
    'questions' => [
        ['id' => 'q1', 'type' => 'single', 'title' => '1+1=?', 'score' => 1, 'options' => [
            ['id' => 'a', 'text' => '2'], ['id' => 'b', 'text' => '3'],
        ], 'answer' => ['a']],
    ],
]);

$course = course_save(course_normalize([
    'title' => '测试课程',
    'slug' => 'test-course',
    'price' => 100,
    'status' => 'published',
    'certificate' => true,
    'i18n' => ['en' => ['title' => 'Test Course EN', 'subtitle' => 'EN sub']],
    'chapters' => [[
        'title' => '第一章',
        'lessons' => [
            ['type' => 'article', 'title' => '图文', 'content' => '<p>hi</p>'],
            ['type' => 'quiz', 'title' => '结业测验', 'quiz_id' => (string)$quiz['id']],
        ],
    ]],
]));
quiz_save(array_merge(quiz_find((string)$quiz['id']), ['course_id' => (string)$course['id']]));

check('course_find by slug', course_find('test-course') !== null);
check('course lesson count', course_lesson_count($course) === 2);

$lessons = course_lessons($course);
$article = $lessons[0];
$quizLesson = $lessons[1];

$student = student_create(['email' => 'a@b.com', 'password' => 'secret1', 'name' => 'Alice']);
check('student created', $student['id'] !== '');
check('student verify', student_verify('a@b.com', 'secret1') !== null);
check('student wrong password', student_verify('a@b.com', 'nope') === null);

$dup = false;
try { student_create(['email' => 'a@b.com']); } catch (Throwable $e) { $dup = true; }
check('duplicate email rejected', $dup);

enroll_add((string)$course['id'], (string)$student['id'], ['source' => 'payflow', 'order_id' => 'ord1']);
check('enrollment active', enroll_is_active((string)$course['id'], (string)$student['id']));

progress_set((string)$student['id'], (string)$course['id'], (string)$article['id'], ['position' => 30, 'duration' => 100, 'seconds' => 30]);
$resume = progress_resume((string)$student['id'], (string)$course['id'], $course);
check('resume picks in-progress lesson', ($resume['lesson_id'] ?? '') === (string)$article['id']);
check('resume position', ($resume['position'] ?? 0) === 30);

$summary = progress_summary((string)$student['id'], (string)$course['id'], $course);
check('summary total', $summary['total'] === 2);
check('summary done 0', $summary['done'] === 0);

$graded = quiz_grade(quiz_find((string)$quiz['id']), ['q1' => 'a']);
check('quiz grade correct', $graded['score'] === 1 && $graded['passed'] === true);
$gradedWrong = quiz_grade(quiz_find((string)$quiz['id']), ['q1' => 'b']);
check('quiz grade wrong', $gradedWrong['score'] === 0 && $gradedWrong['passed'] === false);

$submit = quiz_submit((string)$student['id'], (string)$quiz['id'], ['q1' => 'a']);
check('quiz submit stored', !empty($submit['ok']) && quiz_passed((string)$student['id'], (string)$quiz['id']));

check('no cert before completion', cert_maybe_issue((string)$student['id'], $course, 'Alice') === null);

progress_done((string)$student['id'], (string)$course['id'], (string)$article['id']);
progress_done((string)$student['id'], (string)$course['id'], (string)$quizLesson['id']);
$cert = cert_maybe_issue((string)$student['id'], $course, 'Alice');
check('cert issued after completion', $cert !== null && cert_verify((string)$cert['cert_no']));
check('cert idempotent', (cert_maybe_issue((string)$student['id'], $course, 'Alice')['cert_no'] ?? '') === (string)$cert['cert_no']);

cert_revoke((string)$cert['cert_no']);
check('cert revoked invalid', cert_verify((string)$cert['cert_no']) === false);

$invite = invite_create((string)$course['id'], ['code' => 'FREE2026', 'max_uses' => 1]);
$s2 = student_create(['email' => 'c@d.com', 'password' => 'secret1']);
$redeem = invite_redeem('FREE2026', (string)$s2['id']);
check('invite redeem ok', !empty($redeem['ok']) && enroll_is_active((string)$course['id'], (string)$s2['id']));
$s3 = student_create(['email' => 'e@f.com', 'password' => 'secret1']);
$redeem2 = invite_redeem('FREE2026', (string)$s3['id']);
check('invite max uses enforced', empty($redeem2['ok']));

$mapped = payflow_map_product_to_course((string)$course['id']);
check('payflow product maps to course', $mapped !== null);
$order = payflow_handle_order(['product_id' => (string)$course['id'], 'email' => 'g@h.com', 'name' => 'G', 'order_id' => 'ord2', 'amount' => 100]);
check('payflow order enrolls student', !empty($order['ok']) && enroll_is_active((string)$order['course_id'], (string)$order['student_id']));

$curve = progress_curve((string)$course['id']);
check('progress curve learners', $curve['learners'] >= 1);

$notifs = notify_list((string)$student['id'], 50);
$types = array_column($notifs, 'type');
check('welcome+enrollment+assignment notifications exist', in_array('system', $types, true) && in_array('course', $types, true));
check('certificate notification emitted', in_array('certificate', $types, true));
check('mail log written (SMTP disabled fallback)', file_exists($tmp . '/mail-log.json'));
$events = json_read($tmp . '/events.json');
check('event log recorded', count($events) >= 3);

$cat = category_save('增长训练营', 'growth');
check('category saved', category_find('growth') !== null);
$withCat = course_save(course_normalize(array_merge(course_find('test-course'), ['categories' => ['growth'], 'tags' => ['x', 'y']])));
check('course stores categories/tags', in_array('growth', (array)$withCat['categories'], true) && count((array)$withCat['tags']) === 2);

enroll_set_group((string)$course['id'], (string)$student['id'], 'A 组');
check('enrollment group saved', (enroll_row((string)$course['id'], (string)$student['id'])['group'] ?? '') === 'A 组');

$asg = assignment_save(['course_id' => (string)$course['id'], 'title' => '第一次作业', 'allow_file' => true]);
assignment_submit((string)$asg['id'], (string)$student['id'], '这是我的作业');
check('assignment submission stored', (assignment_submission((string)$asg['id'], (string)$student['id'])['status'] ?? '') === 'submitted');
assignment_grade((string)$asg['id'], (string)$student['id'], 90, '写得不错');
$sub = assignment_submission((string)$asg['id'], (string)$student['id']);
check('assignment graded', ($sub['status'] ?? '') === 'graded' && (float)$sub['grade'] === 90.0);
check('assignment grade notification', in_array('assignment', array_column(notify_list((string)$student['id']), 'type'), true));

$task = task_save((string)$course['id'], ['day_index' => 1, 'title' => '第 1 天任务']);
task_complete((string)$course['id'], (string)$student['id'], (string)$task['id']);
check('daily task completed', (task_progress((string)$course['id'], (string)$student['id'])['done'] ?? 0) === 1);

checkin_do((string)$course['id'], (string)$student['id'], '打卡');
check('checkin today', checkin_today((string)$course['id'], (string)$student['id']));
check('checkin streak 1', checkin_streak((string)$course['id'], (string)$student['id']) === 1);

$post = post_create((string)$course['id'], $student, ['type' => 'question', 'title' => '提问', 'body' => '怎么开始？']);
post_like((string)$course['id'], (string)$post['id'], (string)$student['id']);
comment_add((string)$course['id'], (string)$post['id'], $student, '同问');
$p = post_find((string)$course['id'], (string)$post['id']);
check('community post with like/comment', count((array)$p['likes']) === 1 && count((array)$p['comments']) === 1);

$uploadRel = 'assignments/test/demo.txt';
@mkdir($tmp . '/uploads/assignments/test', 0755, true);
file_put_contents($tmp . '/uploads/' . $uploadRel, 'hi');
$signed = lf_file_url($uploadRel, (string)$student['id']);
check('signed file url includes base + sig', str_contains($signed, 'file?') && str_contains($signed, 's='));
check('file path safe', lf_file_path($uploadRel) !== null && lf_file_path('../../etc/passwd') === null);

check('media_resolve external passthrough', media_resolve(['video' => 'https://cdn.test/a.mp4']) === 'https://cdn.test/a.mp4');
$signedVideo = media_resolve(['video' => 'upload:' . $uploadRel], (string)$course['id'], (string)$student['id']);
check('media_resolve upload -> signed file url', str_contains($signedVideo, 'file?') && str_contains($signedVideo, 's='));
check('media_kind hls', media_kind('https://media.test/x/index.m3u8') === 'hls' && media_kind('https://media.test/x/a.mp4') === 'video');
check('media protected flag', media_is_protected(['video' => 'upload:x']) && !media_is_protected(['video' => 'https://x/y.mp4']));

$en = lf_localize_course(course_find('test-course'), 'en');
$zh = lf_localize_course(course_find('test-course'), 'zh');
check('i18n en title applied', ($en['title'] ?? '') === 'Test Course EN' && ($zh['title'] ?? '') === '测试课程');

check('ai disabled by default', !ai_enabled());

foreach (glob($tmp . '/*') ?: [] as $f) { is_file($f) && @unlink($f); }
@rmdir($tmp);

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
