<?php

function templates_file(): string
{
    return LF_DATA_DIR . '/templates.json';
}

function template_defaults(): array
{
    $site = '{site}';
    return [
        'welcome' => ['label' => '欢迎（邮件）', 'subject' => '欢迎加入 ' . $site, 'body' => '<p>{name} 你好，账号已创建：{email}</p><p><a href="{url}">去看看课程</a></p>'],
        'enrollment' => ['label' => '报名成功（邮件）', 'subject' => '已加入课程：{course}', 'body' => '<p>你已成功加入《{course}》。</p><p><a href="{url}">开始学习</a></p>'],
        'assignment_graded' => ['label' => '作业点评（邮件）', 'subject' => '作业已点评：{title}', 'body' => '<p>《{title}》已点评。</p><p>{feedback}</p>'],
        'certificate' => ['label' => '结业证书（邮件）', 'subject' => '恭喜获得结业证书', 'body' => '<p>《{course}》结业证书已颁发。</p><p><a href="{url}">查看证书</a></p>'],
        'reminder' => ['label' => '学习提醒（邮件）', 'subject' => '继续你的学习', 'body' => '<p>《{course}》还有进度未完成。</p><p><a href="{url}">继续学习</a></p>'],
        'reset' => ['label' => '重置密码（邮件）', 'subject' => '重置你的密码', 'body' => '<p>点击下面链接重置密码，1 小时内有效。</p><p><a href="{url}">重置密码</a></p>'],
        'notify_welcome' => ['label' => '欢迎（站内）', 'subject' => '欢迎加入', 'body' => '完善资料后即可开始学习。'],
        'notify_enrollment' => ['label' => '报名成功（站内）', 'subject' => '报名成功：{course}', 'body' => '开始你的学习吧。'],
        'notify_assignment' => ['label' => '作业点评（站内）', 'subject' => '作业已点评：{title}', 'body' => '{feedback}'],
        'notify_certificate' => ['label' => '证书（站内）', 'subject' => '证书已颁发：{course}', 'body' => '点击查看并分享。'],
        'notify_reminder' => ['label' => '学习提醒（站内）', 'subject' => '继续学习：{course}', 'body' => '还有进度未完成。'],
        'notify_live' => ['label' => '直播提醒（站内）', 'subject' => '直播提醒：{title}', 'body' => '即将开播，记得进入直播间。'],
    ];
}

function templates_all(): array
{
    $all = template_defaults();
    foreach (json_read(templates_file()) as $k => $t) {
        if (isset($all[$k])) $all[$k] = array_merge($all[$k], ['subject' => (string)($t['subject'] ?? ''), 'body' => (string)($t['body'] ?? '')]);
    }
    return $all;
}

function template_get(string $key): array
{
    $all = templates_all();
    return $all[$key] ?? ['label' => $key, 'subject' => '', 'body' => ''];
}

function template_save(string $key, string $subject, string $body): void
{
    json_update(templates_file(), function (array $all) use ($key, $subject, $body) {
        $all[$key] = ['subject' => $subject, 'body' => $body];
        return $all;
    });
}

function template_vars(string $text, array $vars): string
{
    $vars = array_merge(['site' => (string)(lf_setting_get('site_name') ?: 'LearnFlow')], $vars);
    foreach ($vars as $k => $v) $text = str_replace('{' . $k . '}', (string)$v, $text);
    return $text;
}

function notify_template(string $key, array $vars, string $fallbackTitle, string $fallbackBody): array
{
    $t = template_get($key);
    if (($t['subject'] ?? '') === '' && ($t['body'] ?? '') === '') return [$fallbackTitle, $fallbackBody];
    return [template_vars((string)$t['subject'], $vars), template_vars((string)$t['body'], $vars)];
}
