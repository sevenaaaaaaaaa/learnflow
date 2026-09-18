<?php

function lf_md_inline(string $text): string
{
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text);
    $text = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
    return $text;
}

function lf_md_to_html(string $md): string
{
    $md = str_replace(["\r\n", "\r"], "\n", trim($md));
    if ($md === '') return '';
    $lines = explode("\n", $md);
    $html = [];
    $inUl = false;
    $inOl = false;
    $inCode = false;
    $para = [];

    $flushPara = function () use (&$para, &$html) {
        if ($para) {
            $html[] = '<p>' . lf_md_inline(implode(' ', $para)) . '</p>';
            $para = [];
        }
    };
    $closeLists = function () use (&$inUl, &$inOl, &$html) {
        if ($inUl) { $html[] = '</ul>'; $inUl = false; }
        if ($inOl) { $html[] = '</ol>'; $inOl = false; }
    };

    foreach ($lines as $line) {
        if (preg_match('/^```/', $line)) {
            $flushPara(); $closeLists();
            $html[] = $inCode ? '</code></pre>' : '<pre><code>';
            $inCode = !$inCode;
            continue;
        }
        if ($inCode) { $html[] = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); continue; }

        if (preg_match('/^(#{1,4})\s+(.*)$/', $line, $m)) {
            $flushPara(); $closeLists();
            $level = min(4, strlen($m[1]));
            $html[] = '<h' . $level . '>' . lf_md_inline($m[2]) . '</h' . $level . '>';
            continue;
        }
        if (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m)) {
            $flushPara();
            if (!$inUl) { $closeLists(); $html[] = '<ul>'; $inUl = true; }
            $html[] = '<li>' . lf_md_inline($m[1]) . '</li>';
            continue;
        }
        if (preg_match('/^\s*\d+\.\s+(.*)$/', $line, $m)) {
            $flushPara();
            if (!$inOl) { $closeLists(); $html[] = '<ol>'; $inOl = true; }
            $html[] = '<li>' . lf_md_inline($m[1]) . '</li>';
            continue;
        }
        if (preg_match('/^>\s?(.*)$/', $line, $m)) {
            $flushPara(); $closeLists();
            $html[] = '<blockquote>' . lf_md_inline($m[1]) . '</blockquote>';
            continue;
        }
        if (preg_match('/^(-{3,}|\*{3,})$/', trim($line))) {
            $flushPara(); $closeLists();
            $html[] = '<hr>';
            continue;
        }
        if (trim($line) === '') {
            $flushPara(); $closeLists();
            continue;
        }
        $para[] = trim($line);
    }
    $flushPara();
    $closeLists();
    if ($inCode) $html[] = '</code></pre>';
    return implode("\n", $html);
}
