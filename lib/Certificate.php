<?php

require_once __DIR__ . '/Events.php';

function cert_file(): string
{
    return LF_DATA_DIR . '/certificates.json';
}

function cert_all(): array
{
    return json_read(cert_file());
}

function cert_signature(string $certNo, string $studentId): string
{
    return substr(hash_hmac('sha256', $certNo . '|' . $studentId, lf_secret()), 0, 12);
}

function cert_issue(string $studentId, string $courseId, string $courseTitle, string $name = ''): ?array
{
    if ($studentId === '' || $courseId === '') return null;
    foreach (cert_all() as $c) {
        if (($c['student_id'] ?? '') === $studentId && ($c['course_id'] ?? '') === $courseId && empty($c['revoked'])) {
            return $c;
        }
    }
    $certNo = 'LF-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $row = [
        'cert_no' => $certNo,
        'student_id' => $studentId,
        'course_id' => $courseId,
        'course_title' => mb_substr($courseTitle, 0, 80),
        'name' => mb_substr($name, 0, 40),
        'issued_at' => date('Y-m-d H:i:s'),
        'verify' => cert_signature($certNo, $studentId),
        'revoked' => false,
    ];
    json_update(cert_file(), function (array $all) use ($certNo, $row) {
        $all[$certNo] = $row;
        return $all;
    });
    $student = function_exists('student_get') ? student_get($studentId) : null;
    lf_emit('certificate.issued', [
        'student_id' => $studentId,
        'name' => (string)($student['name'] ?? $name),
        'email' => (string)($student['email'] ?? ''),
        'course_id' => $courseId,
        'course_title' => $courseTitle,
        'cert_no' => $certNo,
    ]);
    return $row;
}

function cert_get(string $certNo): ?array
{
    $all = cert_all();
    return $all[$certNo] ?? null;
}

function cert_verify(string $certNo): bool
{
    $c = cert_get($certNo);
    if ($c === null || !empty($c['revoked'])) return false;
    return hash_equals((string)($c['verify'] ?? ''), cert_signature($certNo, (string)($c['student_id'] ?? '')));
}

function cert_for_student(string $studentId): array
{
    $out = [];
    foreach (cert_all() as $c) {
        if (($c['student_id'] ?? '') === $studentId && empty($c['revoked'])) $out[] = $c;
    }
    usort($out, fn($a, $b) => strcmp((string)($b['issued_at'] ?? ''), (string)($a['issued_at'] ?? '')));
    return $out;
}

function cert_revoke(string $certNo): void
{
    json_update(cert_file(), function (array $all) use ($certNo) {
        if (isset($all[$certNo])) $all[$certNo]['revoked'] = true;
        return $all;
    });
}

function cert_share_url(string $certNo): string
{
    return lf_abs_url('/certificate/' . rawurlencode($certNo));
}

function cert_maybe_issue(string $studentId, array $course, string $name = ''): ?array
{
    if (empty($course['certificate'])) return null;
    require_once LF_ROOT . '/lib/Progress.php';
    require_once LF_ROOT . '/lib/Quiz.php';
    $summary = progress_summary($studentId, (string)$course['id'], $course);
    if ($summary['total'] === 0 || $summary['done'] < $summary['total']) return null;
    foreach (quiz_for_course((string)$course['id']) as $q) {
        if (($q['kind'] ?? 'chapter') === 'final' && !quiz_passed($studentId, (string)$q['id'])) {
            return null;
        }
    }
    return cert_issue($studentId, (string)$course['id'], (string)($course['title'] ?? ''), $name);
}
