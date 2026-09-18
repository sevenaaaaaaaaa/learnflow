<?php

function ai_extract_text(string $path, string $ext): string
{
    $ext = strtolower($ext);
    if (in_array($ext, ['txt', 'md', 'csv', 'json', 'html', 'htm'], true)) {
        $raw = (string)@file_get_contents($path);
        if (in_array($ext, ['html', 'htm'], true)) $raw = strip_tags($raw);
        return trim($raw);
    }
    if (!class_exists('ZipArchive')) return '';
    if ($ext === 'docx') {
        return ai_extract_docx($path);
    }
    if ($ext === 'pptx') {
        return ai_extract_pptx($path);
    }
    return '';
}

function ai_extract_docx(string $path): string
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return '';
    $xml = (string)$zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === '') return '';
    $xml = preg_replace('#</w:p>#', "\n", $xml);
    $text = strip_tags((string)$xml);
    return trim(html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

function ai_extract_pptx(string $path): string
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return '';
    $parts = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string)$zip->getNameIndex($i);
        if (preg_match('#^ppt/slides/slide\d+\.xml$#', $name)) $parts[$name] = $zip->getFromIndex($i);
    }
    $zip->close();
    ksort($parts, SORT_NATURAL);
    $out = [];
    foreach ($parts as $xml) {
        $xml = preg_replace('#</a:p>#', "\n", (string)$xml);
        $out[] = trim(strip_tags((string)$xml));
    }
    return trim(html_entity_decode(implode("\n---\n", $out), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}
