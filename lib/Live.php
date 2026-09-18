<?php

function live_openflow_config(): array
{
    return array_merge(['base_url' => 'https://nownexts.com'], (array)(lf_setting_get('openflow') ?: []));
}

function live_openflow_status(string $roomId): ?array
{
    if ($roomId === '') return null;
    $cfg = live_openflow_config();
    $url = rtrim((string)$cfg['base_url'], '/') . '/api/live.php?action=status&room_id=' . rawurlencode($roomId);
    return lf_cache_get('live-status:' . $roomId, 15, function () use ($url) {
        require_once __DIR__ . '/Events.php';
        $res = lf_get_json($url, [], 4);
        if (empty($res['ok'])) return null;
        $d = json_decode((string)$res['body'], true);
        return is_array($d) ? $d : null;
    });
}

function live_openflow_room_url(string $roomId): string
{
    $cfg = live_openflow_config();
    return rtrim((string)$cfg['base_url'], '/') . '/live?room=' . rawurlencode($roomId);
}

function live_lesson_state(array $lesson): string
{
    $ls = strtotime((string)($lesson['live_start'] ?? '')) ?: 0;
    $le = strtotime((string)($lesson['live_end'] ?? '')) ?: 0;
    if ($ls === 0 && $le === 0) return '';
    if ($ls !== 0 && time() < $ls) return '未开始';
    if ($le !== 0 && time() > $le) return '已结束';
    return '直播中';
}
