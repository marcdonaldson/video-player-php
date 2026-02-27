<?php
/**
 * Shared Configuration
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Media root - where your actual media files are stored (parent of video-player-php)
define('MEDIA_ROOT', dirname(dirname(__DIR__)));
// Cache in video-player-php directory for portability
define('CACHE_DIR',  dirname(__DIR__) . '/.cache');
define('THUMBS_DIR', CACHE_DIR . '/thumbs');
define('INDEX_CACHE', CACHE_DIR . '/index.json');
define('DURATIONS_CACHE', CACHE_DIR . '/durations.json');
define('RESUME_FILE', CACHE_DIR . '/resume.json');

define('FFMPEG',  'ffmpeg');
define('FFPROBE', 'ffprobe');
define('THUMB_W', 400);
define('THUMB_H', 225);
define('THUMB_QUALITY', 5);  // FFmpeg quality (1-31, lower = better)

// Video & audio extensions
$GLOBALS['videoExt'] = ['mp4','mkv','avi','mov','webm','m4v','flv','wmv','ts'];
$GLOBALS['audioExt'] = ['mp3','aac','flac','ogg','wav','m4a','opus'];

// Base URL
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'));

// Ensure cache directory exists
if (!is_dir(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0755, true);
}

/**
 * Helper: Safe relative path
 */
function safeRelPath($rel) {
    $parts = explode('/', str_replace('\\', '/', $rel));
    $safe  = [];
    foreach ($parts as $p) {
        if ($p === '' || $p === '.') continue;
        if ($p === '..') { array_pop($safe); continue; }
        $safe[] = $p;
    }
    return implode('/', $safe);
}

/**
 * Helper: Build absolute path
 */
function absPath($rel) {
    return MEDIA_ROOT . ($rel ? '/' . safeRelPath($rel) : '');
}

/**
 * Helper: Check if video
 */
function isVideo($f) {
    return in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $GLOBALS['videoExt']);
}

/**
 * Helper: Check if audio
 */
function isAudio($f) {
    return in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $GLOBALS['audioExt']);
}

/**
 * Helper: Public URL
 */
function publicUrl($rel) {
    $parts   = explode('/', $rel);
    $encoded = array_map('rawurlencode', $parts);
    return BASE_URL . '/' . implode('/', $encoded);
}
