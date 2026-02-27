<?php
/**
 * Stream handler - HTTP Range support
 */

require_once __DIR__ . '/../config/config.php';

if (!isset($_GET['file'])) {
    http_response_code(400);
    exit('Bad request');
}

$rel = safeRelPath($_GET['file']);
$abs = absPath($rel);

if (!file_exists($abs) || !is_file($abs)) {
    http_response_code(404);
    exit('Not found');
}

$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

// MIME type map
$mimeMap = [
    'mp4'=>'video/mp4','m4v'=>'video/mp4','mkv'=>'video/x-matroska',
    'avi'=>'video/x-msvideo','mov'=>'video/quicktime','webm'=>'video/webm',
    'flv'=>'video/x-flv','wmv'=>'video/x-ms-wmv','ts'=>'video/mp2t',
    'mp3'=>'audio/mpeg','aac'=>'audio/aac','flac'=>'audio/flac',
    'ogg'=>'audio/ogg','wav'=>'audio/wav','m4a'=>'audio/mp4','opus'=>'audio/opus',
];

$mime = isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'application/octet-stream';
$size = filesize($abs);
$start = 0;
$end = $size - 1;

header('Accept-Ranges: bytes');
header('Content-Type: ' . $mime);

// Handle Range requests
if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        $start = $m[1] !== '' ? (int)$m[1] : 0;
        $end = $m[2] !== '' ? (int)$m[2] : $size - 1;
        $end = min($end, $size - 1);
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
}

header('Content-Length: ' . ($end - $start + 1));
header('Cache-Control: public, max-age=84400');

$fp = fopen($abs, 'rb');
fseek($fp, $start);
while (!feof($fp) && ftell($fp) <= $end) {
    echo fread($fp, min(65536, $end - ftell($fp) + 1));
    flush();
}
fclose($fp);
exit;
