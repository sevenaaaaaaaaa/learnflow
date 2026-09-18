<?php

function quizzes_file(): string
{
    return LF_DATA_DIR . '/quizzes.json';
}

function attempts_file(): string
{
    return LF_DATA_DIR . '/quiz-attempts.json';
}

function quiz_all(): array
{
    return json_read(quizzes_file());
}

function quiz_find(string $quizId): ?array
{
    $all = quiz_all();
    return $all[$quizId] ?? null;
}

function quiz_for_course(string $courseId): array
{
    $out = [];
    foreach (quiz_all() as $id => $q) {
        if (($q['course_id'] ?? '') === $courseId) {
            $q['id'] = $id;
            $out[] = $q;
        }
    }
    return $out;
}

function quiz_save(array $quiz): array
{
    if (empty($quiz['id'])) $quiz['id'] = 'qz_' . bin2hex(random_bytes(5));
    $quiz['questions'] = array_values((array)($quiz['questions'] ?? []));
    foreach ($quiz['questions'] as $i => $q) {
        if (empty($q['id'])) $quiz['questions'][$i]['id'] = 'q_' . bin2hex(random_bytes(3));
    }
    json_update(quizzes_file(), function (array $all) use ($quiz) {
        $all[$quiz['id']] = $quiz;
        return $all;
    });
    return $quiz;
}

function quiz_delete(string $quizId): bool
{
    $removed = false;
    json_update(quizzes_file(), function (array $all) use ($quizId, &$removed) {
        if (isset($all[$quizId])) {
            unset($all[$quizId]);
            $removed = true;
        }
        return $all;
    });
    return $removed;
}

function quiz_total_score(array $quiz): int
{
    $sum = 0;
    foreach ((array)($quiz['questions'] ?? []) as $q) $sum += (int)($q['score'] ?? 1);
    return $sum;
}

function quiz_grade(array $quiz, array $answers): array
{
    $score = 0;
    $detail = [];
    foreach ((array)($quiz['questions'] ?? []) as $q) {
        $qid = (string)($q['id'] ?? '');
        $given = $answers[$qid] ?? null;
        $correct = $q['answer'] ?? [];
        if (!is_array($correct)) $correct = [$correct];
        $correct = array_values(array_map('strval', $correct));
        if (is_array($given)) {
            $given = array_values(array_map('strval', $given));
        } else {
            $given = $given === null ? [] : [(string)$given];
        }
        sort($given);
        sort($correct);
        $isRight = $given !== [] && $given === $correct;
        if ($isRight) $score += (int)($q['score'] ?? 1);
        $detail[] = [
            'question_id' => $qid,
            'correct' => $isRight,
            'given' => $given,
            'expected' => $correct,
            'explanation' => (string)($q['explanation'] ?? ''),
        ];
    }
    $total = quiz_total_score($quiz);
    $passScore = (int)($quiz['pass_score'] ?? (int)ceil($total * 0.6));
    return [
        'score' => $score,
        'total' => $total,
        'pass_score' => $passScore,
        'passed' => $score >= $passScore,
        'detail' => $detail,
    ];
}

function quiz_attempts(string $studentId, string $quizId): array
{
    $all = json_read(attempts_file());
    return $all[$studentId][$quizId] ?? [];
}

function quiz_best(string $studentId, string $quizId): ?array
{
    $best = null;
    foreach (quiz_attempts($studentId, $quizId) as $a) {
        if ($best === null || (int)($a['score'] ?? 0) > (int)($best['score'] ?? 0)) $best = $a;
    }
    return $best;
}

function quiz_passed(string $studentId, string $quizId): bool
{
    foreach (quiz_attempts($studentId, $quizId) as $a) {
        if (!empty($a['passed'])) return true;
    }
    return false;
}

function quiz_submit(string $studentId, string $quizId, array $answers): array
{
    $quiz = quiz_find($quizId);
    if ($quiz === null) return ['ok' => false, 'error' => '测验不存在'];
    $result = quiz_grade($quiz, $answers);
    $attempt = array_merge($result, [
        'attempt_id' => 'att_' . bin2hex(random_bytes(5)),
        'quiz_id' => $quizId,
        'course_id' => (string)($quiz['course_id'] ?? ''),
        'student_id' => $studentId,
        'submitted_at' => date('Y-m-d H:i:s'),
    ]);
    unset($attempt['detail']);
    $attempt['detail'] = $result['detail'];
    json_update(attempts_file(), function (array $all) use ($studentId, $quizId, $attempt) {
        $all[$studentId][$quizId][] = $attempt;
        return $all;
    });
    if (function_exists('lf_emit')) {
        lf_emit('quiz.result', lf_emit_context($studentId, [
            'course_id' => (string)($quiz['course_id'] ?? ''),
            'quiz_id' => $quizId,
            'quiz_title' => (string)($quiz['title'] ?? ''),
            'passed' => !empty($result['passed']),
            'score' => (int)$result['score'],
            'total' => (int)$result['total'],
        ]));
    }
    return ['ok' => true, 'result' => $result, 'attempt' => $attempt];
}

function quiz_course_progress(string $studentId, string $courseId): array
{
    $quizzes = quiz_for_course($courseId);
    $out = [];
    foreach ($quizzes as $q) {
        $best = quiz_best($studentId, (string)$q['id']);
        $out[(string)$q['id']] = [
            'title' => (string)($q['title'] ?? ''),
            'kind' => (string)($q['kind'] ?? 'chapter'),
            'best_score' => $best ? (int)$best['score'] : null,
            'total' => quiz_total_score($q),
            'passed' => $best ? !empty($best['passed']) : false,
        ];
    }
    return $out;
}
