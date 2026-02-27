<?php
/**
 * Thumbnail handler with FFmpeg generation
 */

require_once __DIR__ . '/../config/config.php';

if (!isset($_GET['file'])) {
    http_response_code(400);
    exit;
}

$rel = safeRelPath($_GET['file']);
$abs = absPath($rel);

if (!file_exists($abs) || !is_file($abs)) {
    http_response_code(404);
    exit;
}

// Create thumbnails directory
if (!is_dir(THUMBS_DIR)) {
    @mkdir(THUMBS_DIR, 0755, true);
}

$thumbCacheKey = md5($abs) . '.jpg';
$thumbPath = THUMBS_DIR . '/' . $thumbCacheKey;

// Generate thumbnail if not exists
if (!file_exists($thumbPath)) {
    generateThumbnail($abs, $thumbPath);
}

// Serve thumbnail
if (file_exists($thumbPath)) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    readfile($thumbPath);
} else {
    // Fallback placeholder
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="225"><rect width="400" height="225" fill="#1f2937"/><text x="50%" y="50%" fill="#6b7280" font-family="sans-serif" font-size="16" text-anchor="middle" dominant-baseline="middle">No Preview</text></svg>';
}
exit;

/**
 * Generate thumbnail using FFmpeg
 */
function generateThumbnail($absVideo, $thumbPath) {
    $cmd = sprintf(
        '%s -y -ss 00:00:05 -i %s -vframes 1 -vf "scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2" -q:v %d %s 2>/dev/null',
        FFMPEG, escapeshellarg($absVideo),
        THUMB_W, THUMB_H, THUMB_W, THUMB_H,
        THUMB_QUALITY,
        escapeshellarg($thumbPath)
    );

    exec($cmd, $out, $code);

    if ($code === 0 && file_exists($thumbPath)) {
        // Try to optimize with jpegoptim if available
        $optimizeCmd = 'command -v jpegoptim >/dev/null 2>&1 && jpegoptim --strip-all -q ' . escapeshellarg($thumbPath) . ' 2>/dev/null';
        @exec($optimizeCmd);
        return true;
    }

    return false;
}
