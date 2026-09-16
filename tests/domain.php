<?php

$tmp = sys_get_temp_dir() . '/learnflow-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0755, true);
putenv('LF_DATA_DIR=' . $tmp);
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

foreach (glob($tmp . '/*') ?: [] as $f) { is_file($f) && @unlink($f); }
@rmdir($tmp);

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
