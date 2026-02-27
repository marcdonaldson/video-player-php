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
    // Fallback placeholder SVG - shows beautiful gradient instead of error
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="225" viewBox="0 0 400 225">
  <defs>
    <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#1f2937;stop-opacity:1" />
      <stop offset="100%" style="stop-color:#0f172a;stop-opacity:1" />
    </linearGradient>
  </defs>
  <rect width="400" height="225" fill="url(#grad)"/>
  <circle cx="200" cy="112" r="40" fill="rgba(168,139,250,0.2)"/>
  <path d="M185 100 L185 124 L220 112 Z" fill="rgba(168,139,250,0.4)"/>
</svg>
SVG;
}
exit;

/**
 * Generate thumbnail using FFmpeg
 */
function generateThumbnail($absVideo, $thumbPath) {
    // Check if FFmpeg exists
    $ffmpegCheck = shell_exec('which ffmpeg 2>&1');
    if (empty($ffmpegCheck)) {
        return false;
    }

    $cmd = sprintf(
        'ffmpeg -y -ss 00:00:05 -i %s -vframes 1 -vf "scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2" -q:v %d %s 2>&1',
        escapeshellarg($absVideo),
        THUMB_W, THUMB_H, THUMB_W, THUMB_H,
        THUMB_QUALITY,
        escapeshellarg($thumbPath)
    );

    exec($cmd, $output, $code);

    // Check if thumbnail was actually created
    if ($code === 0 && file_exists($thumbPath) && filesize($thumbPath) > 0) {
        // Try to optimize with jpegoptim if available
        $jpegoptim = shell_exec('which jpegoptim 2>&1');
        if (!empty($jpegoptim)) {
            @exec('jpegoptim --strip-all -q ' . escapeshellarg($thumbPath) . ' 2>/dev/null');
        }
        return true;
    }

    // Clean up failed thumbnail
    if (file_exists($thumbPath)) {
        @unlink($thumbPath);
    }

    return false;
}
