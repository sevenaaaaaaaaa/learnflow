<?php

$tmp = sys_get_temp_dir() . '/learnflow-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0755, true);
putenv('LF_DATA_DIR=' . $tmp);
putenv('LF_UPLOAD_DIR=' . $tmp . '/uploads');
@mkdir($tmp . '/uploads', 0755, true);
$_SERVER['HTTP_HOST'] = 'localhost';
putenv('LF_ENV=dev');

require_once dirname(__DIR__) . '/includes/bootstrap.php';

lf_setting_set('mysql', ['enabled' => true, 'driver' => 'sqlite', 'sqlite_path' => $tmp . '/db/learnflow.db']);

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

$created = api_key_create('测试密钥', ['read', 'write'], 60);
$auth = api_key_authenticate($created['token']);
check('api key created + authenticated', $auth !== null && ($auth['id'] ?? '') === $created['id']);
check('api key rejects bad token', api_key_authenticate('lf_deadbeef') === null);

$r = lf_api_call('course.list', [], ['key_id' => $created['id'], 'scopes' => $auth['scopes']]);
check('api course.list works', !empty($r['ok']) && isset($r['data']['courses']));
$r2 = lf_api_call('course.create', ['title' => 'API 建课'], ['key_id' => $created['id'], 'scopes' => $auth['scopes']]);
check('api course.create works (write scope)', !empty($r2['ok']) && !empty($r2['data']['course_id']));
$r3 = lf_api_call('course.create', ['title' => 'x'], ['key_id' => $created['id'], 'scopes' => ['read']]);
check('api scope enforced (403)', empty($r3['ok']) && ($r3['code'] ?? 0) === 403);
$r4 = lf_api_call('nope.tool', [], ['key_id' => $created['id'], 'scopes' => ['read']]);
check('api unknown tool 404', empty($r4['ok']) && ($r4['code'] ?? 0) === 404);
$r5 = lf_api_call('course.create', [], ['key_id' => $created['id'], 'scopes' => ['write']]);
check('api missing required param 422', empty($r5['ok']) && ($r5['code'] ?? 0) === 422);
$r6 = lf_api_call('ai.weekly_report', ['course_id' => 'x'], ['key_id' => $created['id'], 'scopes' => ['read', 'write']]);
check('api ai scope enforced', empty($r6['ok']) && ($r6['code'] ?? 0) === 403);
$r7 = lf_api_call('ai.weekly_report', ['course_id' => 'x'], ['key_id' => $created['id'], 'scopes' => ['ai']]);
check('api ai tool reachable (fails on AI disabled)', empty($r7['ok']) && str_contains((string)$r7['error'], 'AI'));

api_key_revoke((string)$created['id']);
check('api key revoked', api_key_authenticate($created['token']) === null);

check('mcp tool list has schemas', count(lf_api_tool_list()) >= 15 && isset(lf_api_tool_list()[0]['inputSchema']));

$cp = coupon_save(['code' => 'SAVE20', 'type' => 'fixed', 'value' => 20, 'min_amount' => 50, 'max_uses' => 2]);
$v1 = coupon_validate('SAVE20', (string)$course['id'], 100);
check('coupon fixed discount', !empty($v1['ok']) && (float)$v1['discount'] === 20.0 && (float)$v1['final'] === 80.0);
$v2 = coupon_validate('SAVE20', (string)$course['id'], 30);
check('coupon min_amount enforced', empty($v2['ok']));
$pct = coupon_save(['code' => 'HALF', 'type' => 'percent', 'value' => 50]);
check('coupon percent discount', (float)coupon_validate('HALF', (string)$course['id'], 100)['discount'] === 50.0);
$restricted = coupon_save(['code' => 'ONLY', 'type' => 'fixed', 'value' => 5, 'course_ids' => ['crs_other']]);
check('coupon course restriction', empty(coupon_validate('ONLY', (string)$course['id'], 100)['ok']));
coupon_redeem('SAVE20');
check('coupon redeem increments uses', (int)(coupon_find('SAVE20')['uses'] ?? 0) === 1);

$ref = referral_code_for_student($student);
check('referral code stable', referral_code_for_student($student)['code'] === $ref['code']);
$buyer = student_create(['email' => 'buyer@ref.com', 'password' => 'secret1', 'name' => '买家']);
lf_setting_set('referral_reward', 10);
$attr = referral_attribute((string)$ref['code'], (string)$buyer['id'], (string)$course['id'], 'ord_ref_1', 100);
check('referral attributed + reward coupon', !empty($attr['ok']) && $attr['reward_coupon'] !== '' && coupon_find((string)$attr['reward_coupon']) !== null);
check('self-referral blocked', empty(referral_attribute((string)$ref['code'], (string)$student['id'], (string)$course['id'], 'ord_self')['ok']));
check('referral stats counted', referral_stats((string)$student['id'])['buyers'] === 1);

$order = payflow_handle_order(['product_id' => 'test-course', 'email' => 'buyer2@ref.com', 'name' => 'B2', 'order_id' => 'ord_cp', 'amount' => 100, 'coupon' => 'SAVE20', 'ref_code' => (string)$ref['code']]);
check('payflow order redeems coupon + attributes referral', !empty($order['ok']) && (int)(coupon_find('SAVE20')['uses'] ?? 0) === 2 && !empty($order['referral']['ok']));

$rc = lf_api_call('coupon.create', ['value' => 15, 'name' => 'API 券'], ['key_id' => 'k', 'scopes' => ['write']]);
check('api coupon.create', !empty($rc['ok']) && !empty($rc['data']['code']));
check('api coupon.list', !empty(lf_api_call('coupon.list', [], ['scopes' => ['read']])['ok']));
check('api referral.list', !empty(lf_api_call('referral.list', [], ['scopes' => ['read']])['ok']));

$eventNames = array_column(json_read($tmp . '/events.json'), 'event');
check('fine-grained events emitted', in_array('lesson.completed', $eventNames, true)
    && in_array('assignment.submitted', $eventNames, true)
    && in_array('checkin.done', $eventNames, true)
    && in_array('quiz.result', $eventNames, true)
    && in_array('certificate.revoked', $eventNames, true));
check('userloop event mapping', userloop_event_name('lesson.completed') === 'lesson_completed' && userloop_event_name('checkin.done') === 'checkin_done');

$an = analytics_overview(30);
check('analytics overview structure', isset($an['revenue'], $an['paid'], $an['conv_paid'], $an['sources'], $an['revenue_by_course'], $an['referrals']));
check('analytics counts paid + revenue', (int)$an['paid'] >= 1 && (float)$an['revenue'] > 0);
check('api analytics.overview', !empty(lf_api_call('analytics.overview', ['days' => 30], ['scopes' => ['read']])['ok']));

$tier = tier_save(['name' => '年费会员', 'price' => 199, 'duration_days' => 365, 'discount_percent' => 10, 'members_only' => true, 'payflow_product_id' => 'mem-year']);
check('tier saved', tier_find((string)$tier['id']) !== null);
membership_grant((string)$student['id'], (string)$tier['id']);
check('membership active', membership_active((string)$student['id']));
check('membership discount', membership_discount($course, (string)$student['id']) === 10.0);
$memberCourse = course_save(course_normalize(array_merge(course_find('test-course'), ['members_only' => true])));
lf_ensure_member_access($memberCourse, student_get((string)$student['id']));
check('member auto-enrolled to members-only course', enroll_is_active((string)$memberCourse['id'], (string)$student['id']));

$ptsBefore = points_balance((string)$student['id']);
lf_emit('lesson.completed', ['student_id' => (string)$student['id'], 'course_id' => (string)$course['id'], 'lesson_id' => 'pt_test_1']);
check('points earned on lesson completed', points_balance((string)$student['id']) > $ptsBefore);
check('achievement first_checkin', points_has_achievement((string)$student['id'], 'first_checkin'));
check('achievement first_lesson', points_has_achievement((string)$student['id'], 'first_lesson'));

$order = payflow_handle_order(['product_id' => 'mem-year', 'email' => 'member@pay.com', 'name' => 'M', 'order_id' => 'ord_mem']);
check('payflow membership order grants', !empty($order['ok']) && ($order['type'] ?? '') === 'membership');
check('api membership.list', !empty(lf_api_call('membership.list', [], ['scopes' => ['read']])['ok']));
check('api points.balance', !empty(lf_api_call('points.balance', ['student_id' => (string)$student['id']], ['scopes' => ['read']])['ok']));

$tc = course_find('test-course');
$ret = ai_retrieve($tc, '图文');
check('ai_retrieve structure + match', isset($ret['sources'], $ret['contexts']) && count($ret['sources']) >= 1);
check('bm25 ranks title match first', ($ret['sources'][0] ?? '') === '图文');
check('api ai.ask scope ok (AI disabled error)', empty(lf_api_call('ai.ask', ['course_id' => 'test-course', 'question' => 'x'], ['scopes' => ['ai']])['ok']));
check('api ai.ask read scope denied', (lf_api_call('ai.ask', ['course_id' => 'x', 'question' => 'y'], ['scopes' => ['read']])['code'] ?? 0) === 403);

$liveCourse = course_save(course_normalize(['title' => '直播测试', 'status' => 'draft', 'chapters' => [['title' => '第一章', 'lessons' => [['type' => 'live', 'title' => '开班直播', 'live_url' => 'https://cdn.test/live.m3u8', 'live_start' => '2026-10-01 20:00', 'live_end' => '2026-10-01 21:00']]]]]));
$ll = course_lessons($liveCourse)[0] ?? [];
check('live lesson fields saved', ($ll['live_url'] ?? '') === 'https://cdn.test/live.m3u8' && ($ll['live_start'] ?? '') === '2026-10-01 20:00' && ($ll['live_end'] ?? '') === '2026-10-01 21:00');
check('live lesson state', live_lesson_state(['live_start' => '2030-01-01 20:00', 'live_end' => '2030-01-01 21:00']) === '未开始' && live_lesson_state(['live_start' => '2020-01-01 20:00', 'live_end' => '2020-01-01 21:00']) === '已结束');
check('openflow live room url', str_contains(live_openflow_room_url('room_1'), '/live?room=room_1'));

$cm = commission_summary();
check('commission recorded from referral', (int)$cm['count'] >= 1 && (float)$cm['pending'] > 0);
$firstCm = commission_all()[0] ?? [];
commission_mark_settled((string)($firstCm['id'] ?? ''));
check('commission settled', (commission_all()[0]['status'] ?? '') === 'settled');
check('api commission.list', !empty(lf_api_call('commission.list', [], ['scopes' => ['read']])['ok']));

$md = lf_md_to_html("# 标题\n\n**粗体** 和 `代码`\n\n- 一\n- 二\n");
check('markdown to html', str_contains($md, '<h1>标题</h1>') && str_contains($md, '<strong>粗体</strong>') && str_contains($md, '<li>一</li>'));
check('template default + vars', template_vars('课程 {course}', ['course' => 'X']) === '课程 X' && (template_get('welcome')['subject'] ?? '') !== '');
[$nt, $nb] = notify_template('notify_enrollment', ['course' => 'C'], 'fb', 'fb2');
check('notify template render', $nt === '报名成功：C');

$savedMedia = media_add(['rel' => 'library/x.png', 'name' => 'x.png', 'ext' => 'png', 'size' => 10]);
check('media library add/find', media_find((string)$savedMedia['id']) !== null && media_kind_of('png') === 'image' && str_contains(media_public_url((string)$savedMedia['id']), '/media/'));
media_delete((string)$savedMedia['id']);
check('media library delete', media_find((string)$savedMedia['id']) === null);

$pc = course_save(course_normalize(['title' => '定时课', 'status' => 'draft', 'publish_at' => '2030-01-01T10:00']));
check('publish_at saved', ($pc['publish_at'] ?? '') === '2030-01-01T10:00');

check('marketing types', count(ai_marketing_types()) === 7);
$matFile = $tmp . '/material.txt';
file_put_contents($matFile, str_repeat('增长飞轮与交付闭环。', 10));
check('doc extract txt', mb_strlen(ai_extract_text($matFile, 'txt')) > 20);
$draft = ai_draft_save('page', '测试主题', '文案内容');
check('ai draft saved', ($draft['id'] ?? '') !== '' && count(ai_drafts()) >= 1);
check('content.marketing scope denied', (lf_api_call('content.marketing', ['topic' => 'x'], ['scopes' => ['read']])['code'] ?? 0) === 403);
check('content.generate_lesson reachable (AI off)', empty(lf_api_call('content.generate_lesson', ['course_id' => 'x', 'lesson_id' => 'y'], ['scopes' => ['ai']])['ok']));

$tpl = course_template_from_course(course_find('test-course'), '测试模板');
check('course template saved', course_template_find((string)$tpl['id']) !== null);
$fromTpl = course_from_template((string)$tpl['id']);
check('course from template', $fromTpl !== null && count((array)$fromTpl['chapters']) >= 1);
$copied = course_copy_chapter('test-course', 0, (string)$fromTpl['id']);
check('copy chapter across courses', $copied !== null && count((array)$copied['chapters']) >= 2);
$mdChapters = course_import_markdown("# 第一章\n## 第一节\n正文一\n## 第二节\n正文二\n# 第二章\n## 第三节\n正文三");
check('markdown import splits chapters/lessons', count($mdChapters) === 2 && count($mdChapters[0]['lessons']) === 2 && str_contains($mdChapters[0]['lessons'][0]['content'], '正文一'));
$pending = course_save(course_normalize(['title' => '待审课', 'status' => 'pending']));
check('pending status allowed', ($pending['status'] ?? '') === 'pending');

note_save((string)$student['id'], (string)$course['id'], (string)$article['id'], '我的第一条笔记');
check('note save/get', (note_get((string)$student['id'], (string)$course['id'], (string)$article['id'])['content'] ?? '') === '我的第一条笔记');

$fileCourse = course_save(course_normalize(['title' => '资料课', 'chapters' => [['title' => 'c1', 'lessons' => [['type' => 'file', 'title' => '模板下载', 'attachments' => [['name' => 't.md', 'url' => 'https://x/t.md']]]]]]]));
check('course attachments aggregate', count(course_attachments($fileCourse)) === 1);

check('lesson_dropoff structure', is_array(lesson_dropoff((string)$course['id'])));
check('quiz_question_stats structure', is_array(quiz_question_stats((string)$course['id'])));

$hl = highlight_add((string)$student['id'], (string)$course['id'], (string)$article['id'], '交付闭环', '重点');
check('highlight add/list', count(highlights_for((string)$student['id'], (string)$course['id'], (string)$article['id'])) === 1 && ($hl['text'] ?? '') === '交付闭环');
highlight_delete((string)$student['id'], (string)$course['id'], (string)$article['id'], (string)$hl['id']);
check('highlight delete', count(highlights_for((string)$student['id'], (string)$course['id'], (string)$article['id'])) === 0);
$pv = course_save(course_normalize(['title' => '封面课', 'chapters' => [['title' => 'c', 'lessons' => [['type' => 'video', 'title' => 'v', 'poster' => 'https://x/p.jpg', 'subtitle' => 'https://x/s.vtt']]]]]));
$pvl = course_lessons($pv)[0];
check('lesson poster+subtitle saved', ($pvl['poster'] ?? '') === 'https://x/p.jpg' && ($pvl['subtitle'] ?? '') === 'https://x/s.vtt');

$revCourse = course_find('test-course');
revision_snapshot($revCourse, 'tester');
check('revision snapshot/list', count(revision_list((string)$revCourse['id'])) >= 1);
$rev = revision_list((string)$revCourse['id'])[0];
$restored = revision_restore((string)$revCourse['id'], (string)$rev['id']);
check('revision restore', $restored !== null && ($restored['id'] ?? '') === (string)$revCourse['id']);

presence_touch('course:test', 'alice');
presence_touch('course:test', 'bob');
check('presence others', count(presence_others('course:test', 'alice')) === 1);

$tc = team_comment_add('test-course', 'alice', '这里建议补充案例');
check('team comment add/list', count(team_comments('test-course')) >= 1);
team_comment_resolve('test-course', (string)$tc['id']);
check('team comment resolve', !empty(team_comments('test-course')[0]['resolved']));

$tcLessons = course_lessons(course_find('test-course'));
$lid = (string)($tcLessons[0]['id'] ?? '');
course_save(course_normalize(array_merge(course_find('test-course'), ['i18n' => ['en' => ['title' => 'Test Course EN', 'lessons' => [$lid => ['title' => 'Article EN', 'content' => '<p>EN</p>']]]]])));
$lc = lf_localize_course(course_find('test-course'), 'en');
check('lesson i18n title+content applied', ($lc['chapters'][0]['lessons'][0]['title'] ?? '') === 'Article EN' && ($lc['chapters'][0]['lessons'][0]['content'] ?? '') === '<p>EN</p>');
check('i18n lf_t fallback', lf_t('x', 'y') === 'x' || lf_t('x', 'y') === 'y');

$_SESSION = [];
lf_admin_create('ed', 'secret1', 'Ed', 'editor');
lf_admin_create('vw', 'secret1', 'Vw', 'viewer');
$_SESSION['lf_admin'] = 'ed';
check('admin role editor', lf_admin_role() === 'editor');
check('role caps', lf_role_can('editor', 'content') && !lf_role_can('editor', 'admin') && !lf_role_can('viewer', 'content') && lf_role_can('admin', 'admin'));
check('cap map by script', lf_admin_cap_for_script('settings.php') === 'admin' && lf_admin_cap_for_script('courses.php') === 'content');
$_SESSION['lf_admin'] = 'vw';
check('viewer role', lf_admin_role() === 'viewer');
unset($_SESSION['lf_admin']);

$dbStatus = lf_db_status();
check('dual-driver connected (sqlite)', !empty($dbStatus['connected']) && ($dbStatus['driver'] ?? '') === 'sqlite');
$kvCount = 0;
$pdo = lf_db();
if ($pdo !== null) $kvCount = (int)$pdo->query('SELECT COUNT(*) AS c FROM lf_kv')->fetch()['c'];
check('collections persisted in DB', $kvCount >= 6);
$pdo2 = lf_db();
$row = $pdo2->prepare('SELECT v FROM lf_kv WHERE k = ?');
$row->execute(['courses']);
$persisted = json_decode((string)($row->fetch()['v'] ?? '[]'), true);
check('course data readable from DB', is_array($persisted) && count($persisted) >= 1);

foreach (glob($tmp . '/*') ?: [] as $f) { is_file($f) && @unlink($f); }
@rmdir($tmp);

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
