<?php

require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Student.php';

function enrollments_file(): string
{
    return LF_DATA_DIR . '/enrollments.json';
}

function invites_file(): string
{
    return LF_DATA_DIR . '/invites.json';
}

function enrollment_all(): array
{
    return json_read(enrollments_file());
}

function enroll_row(string $courseId, string $studentId): ?array
{
    $all = enrollment_all();
    return $all[$courseId][$studentId] ?? null;
}

function enroll_is_active(string $courseId, string $studentId): bool
{
    $row = enroll_row($courseId, $studentId);
    return $row !== null && in_array(($row['status'] ?? ''), ['active', 'completed'], true);
}

function enroll_add(string $courseId, string $studentId, array $data = []): array
{
    $row = array_merge([
        'status' => 'active',
        'source' => 'direct',
        'order_id' => '',
        'invite_code' => '',
        'note' => '',
        'group' => '',
        'enrolled_at' => date('Y-m-d H:i:s'),
    ], $data);
    $existing = enroll_row($courseId, $studentId);
    if ($existing !== null) {
        $row = array_merge($existing, $data);
    }
    json_update(enrollments_file(), function (array $all) use ($courseId, $studentId, $row) {
        $all[$courseId][$studentId] = $row;
        return $all;
    });
    if ($existing === null && function_exists('lf_emit')) {
        $student = student_get($studentId);
        $course = function_exists('course_find') ? course_find($courseId) : null;
        lf_emit('enrollment.created', [
            'student_id' => $studentId,
            'name' => (string)($student['name'] ?? ''),
            'email' => (string)($student['email'] ?? ''),
            'course_id' => $courseId,
            'course_title' => (string)($course['title'] ?? $courseId),
            'course_slug' => (string)($course['slug'] ?? $courseId),
            'source' => (string)($row['source'] ?? ''),
        ]);
    }
    return $row;
}


function enroll_remove(string $courseId, string $studentId): bool
{
    $removed = false;
    json_update(enrollments_file(), function (array $all) use ($courseId, $studentId, &$removed) {
        if (isset($all[$courseId][$studentId])) {
            unset($all[$courseId][$studentId]);
            $removed = true;
        }
        return $all;
    });
    return $removed;
}

function enroll_set_group(string $courseId, string $studentId, string $group): void
{
    json_update(enrollments_file(), function (array $all) use ($courseId, $studentId, $group) {
        if (isset($all[$courseId][$studentId])) {
            $all[$courseId][$studentId]['group'] = mb_substr(trim($group), 0, 40);
        }
        return $all;
    });
}


function enroll_students(string $courseId): array
{
    $all = enrollment_all();
    return $all[$courseId] ?? [];
}

function enroll_by_student(string $studentId): array
{
    $out = [];
    foreach (enrollment_all() as $courseId => $rows) {
        if (isset($rows[$studentId])) $out[$courseId] = $rows[$studentId];
    }
    return $out;
}

function enroll_count(string $courseId): int
{
    return count(enroll_students($courseId));
}

function invite_all(): array
{
    return json_read(invites_file());
}

function invite_find(string $code): ?array
{
    $code = strtoupper(trim($code));
    $all = invite_all();
    return $all[$code] ?? null;
}

function invite_create(string $courseId, array $data = []): array
{
    $code = strtoupper(trim((string)($data['code'] ?? ''))) ?: strtoupper(bin2hex(random_bytes(4)));
    $row = [
        'code' => $code,
        'course_id' => $courseId,
        'max_uses' => (int)($data['max_uses'] ?? 0),
        'uses' => 0,
        'expires_at' => (string)($data['expires_at'] ?? ''),
        'note' => (string)($data['note'] ?? ''),
        'created_at' => date('Y-m-d H:i:s'),
    ];
    json_update(invites_file(), function (array $all) use ($code, $row) {
        $all[$code] = $row;
        return $all;
    });
    return $row;
}

function invite_delete(string $code): bool
{
    $removed = false;
    json_update(invites_file(), function (array $all) use ($code, &$removed) {
        $code = strtoupper(trim($code));
        if (isset($all[$code])) {
            unset($all[$code]);
            $removed = true;
        }
        return $all;
    });
    return $removed;
}

function invite_redeem(string $code, string $studentId): array
{
    $invite = invite_find($code);
    if ($invite === null) return ['ok' => false, 'error' => '邀请码不存在'];
    if (($invite['expires_at'] ?? '') !== '' && strtotime((string)$invite['expires_at']) < time()) {
        return ['ok' => false, 'error' => '邀请码已过期'];
    }
    $max = (int)($invite['max_uses'] ?? 0);
    if ($max > 0 && (int)($invite['uses'] ?? 0) >= $max) {
        return ['ok' => false, 'error' => '邀请码已用尽'];
    }
    $courseId = (string)$invite['course_id'];
    $already = enroll_is_active($courseId, $studentId);
    if (!$already) {
        json_update(invites_file(), function (array $all) use ($code) {
            $all[strtoupper(trim($code))]['uses'] = (int)($all[strtoupper(trim($code))]['uses'] ?? 0) + 1;
            return $all;
        });
        enroll_add($courseId, $studentId, ['source' => 'invite', 'invite_code' => strtoupper(trim($code)), 'status' => 'active']);
    }
    return ['ok' => true, 'course_id' => $courseId, 'already' => $already];
}

function enroll_from_order(string $courseId, string $studentId, string $orderId, float $amount = 0): array
{
    return enroll_add($courseId, $studentId, [
        'source' => 'payflow',
        'order_id' => $orderId,
        'amount' => $amount,
        'status' => 'active',
    ]);
}
