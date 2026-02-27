<?php
/**
 * Diagnostic tool for troubleshooting thumbnail generation
 * Access at: http://localhost:8000/video-player-php/debug.php
 */

require_once __DIR__ . '/config/config.php';

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StreamBox - Debug Panel</title>
<style>
  body { background: #0f172a; color: #e2e8f0; font-family: monospace; padding: 20px; }
  .section { background: rgba(30, 41, 59, 0.8); padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #c084fc; }
  h2 { color: #c084fc; margin-top: 0; }
  .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
  .ok { background: rgba(34, 197, 94, 0.2); color: #86efac; }
  .error { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }
  .warning { background: rgba(245, 158, 11, 0.2); color: #fcd34d; }
  code { background: rgba(0, 0, 0, 0.4); padding: 2px 6px; border-radius: 3px; }
  .path { word-break: break-all; font-size: 12px; }
  ul { margin: 10px 0; }
  li { margin: 5px 0; }
</style>
</head>
<body>

<h1>🔧 StreamBox Diagnostic Panel</h1>

<div class="section">
  <h2>📁 File Paths</h2>
  <div class="path"><strong>MEDIA_ROOT:</strong> <?= MEDIA_ROOT ?></div>
  <div class="path"><strong>CACHE_DIR:</strong> <?= CACHE_DIR ?></div>
  <div class="path"><strong>THUMBS_DIR:</strong> <?= THUMBS_DIR ?></div>
  <div class="path"><strong>Config Location:</strong> <?= __FILE__ ?></div>
</div>

<div class="section">
  <h2>🔍 File System Checks</h2>
  
  <?php
  $checks = [
    'MEDIA_ROOT exists' => is_dir(MEDIA_ROOT),
    'CACHE_DIR exists' => is_dir(CACHE_DIR) || @mkdir(CACHE_DIR, 0755, true),
    'THUMBS_DIR exists' => is_dir(THUMBS_DIR) || @mkdir(THUMBS_DIR, 0755, true),
    'THUMBS_DIR writable' => is_writable(THUMBS_DIR) || is_writable(CACHE_DIR),
  ];

  foreach ($checks as $check => $status):
    $class = $status ? 'ok' : 'error';
    $symbol = $status ? '✓' : '✗';
  ?>
    <div class="status <?= $class ?>"><?= $symbol ?> <?= htmlspecialchars($check) ?></div>
  <?php endforeach; ?>
</div>

<div class="section">
  <h2>⚙️ System Commands</h2>
  
  <?php
  $commands = [
    'ffmpeg' => 'FFmpeg video encoder',
    'ffprobe' => 'FFmpeg probe (duration detection)',
    'jpegoptim' => 'JPEG optimizer (optional)',
  ];

  foreach ($commands as $cmd => $desc):
    $check = trim(shell_exec("which $cmd 2>&1"));
    $available = !empty($check);
    $class = $available ? 'ok' : ($cmd === 'jpegoptim' ? 'warning' : 'error');
    $symbol = $available ? '✓' : '✗';
  ?>
    <div class="status <?= $class ?>"><?= $symbol ?> <strong><?= htmlspecialchars($cmd) ?>:</strong> <?= htmlspecialchars($desc) ?> <?php if ($available): ?><code><?= htmlspecialchars($check) ?></code><?php endif; ?></div>
  <?php endforeach; ?>
</div>

<div class="section">
  <h2>📊 Media Files Found</h2>
  
  <?php
  $mediaFiles = [];
  $videoExt = $GLOBALS['videoExt'] ?? [];
  $audioExt = $GLOBALS['audioExt'] ?? [];
  
  if (is_dir(MEDIA_ROOT)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(MEDIA_ROOT, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    
    foreach ($iterator as $file) {
      // Skip video-player-php directory
      if (strpos($file->getPathname(), '/video-player-php/') !== false) continue;
      
      if ($file->isFile()) {
        $ext = strtolower($file->getExtension());
        if (in_array($ext, $videoExt) || in_array($ext, $audioExt)) {
          $mediaFiles[] = [
            'path' => $file->getPathname(),
            'rel' => str_replace(MEDIA_ROOT . '/', '', $file->getPathname()),
            'size' => filesize($file->getPathname()),
            'type' => in_array($ext, $videoExt) ? 'video' : 'audio'
          ];
        }
      }
    }
  }

  if (count($mediaFiles) > 0):
    echo "<div class=\"status ok\">✓ Found " . count($mediaFiles) . " media files</div>";
    echo "<ul>";
    foreach (array_slice($mediaFiles, 0, 10) as $file):
      echo "<li>[" . htmlspecialchars($file['type']) . "] " . htmlspecialchars($file['rel']) . " (" . round($file['size'] / 1024 / 1024, 1) . " MB)</li>";
    endforeach;
    if (count($mediaFiles) > 10):
      echo "<li>... and " . (count($mediaFiles) - 10) . " more files</li>";
    endif;
    echo "</ul>";
  else:
    echo "<div class=\"status warning\">⚠ No media files found in MEDIA_ROOT. Place media files in: " . htmlspecialchars(MEDIA_ROOT) . "</div>";
  endif;
  ?>
</div>

<div class="section">
  <h2>🎬 Test Thumbnail Generation</h2>
  
  <?php
  // Find first video file for testing
  $testVideo = null;
  if (is_dir(MEDIA_ROOT) && count($mediaFiles) > 0) {
    foreach ($mediaFiles as $file) {
      if ($file['type'] === 'video') {
        $testVideo = $file;
        break;
      }
    }
  }

  if ($testVideo):
    $thumbUrl = '/video-player-php/api/thumb.php?file=' . urlencode($testVideo['rel']);
  ?>
    <p><strong>Test File:</strong> <?= htmlspecialchars($testVideo['rel']) ?></p>
    <p><strong>Thumb URL:</strong> <code><?= htmlspecialchars($thumbUrl) ?></code></p>
    <p><strong>Preview:</strong></p>
    <div style="background: rgba(0,0,0,0.3); padding: 10px; border-radius: 4px; display: inline-block;">
      <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="Test Thumbnail" style="max-width: 200px; border-radius: 4px;">
    </div>
  <?php
  else:
    echo "<div class=\"status error\">✗ No video files found to test</div>";
  endif;
  ?>
</div>

<div class="section">
  <h2>💡 Troubleshooting Tips</h2>
  <ul>
    <li>Ensure FFmpeg is installed: <code>brew install ffmpeg</code> (macOS) or <code>apt-get install ffmpeg</code> (Linux)</li>
    <li>Check <code>THUMBS_DIR</code> is writable</li>
    <li>Clear cache: <code>rm -rf <?= htmlspecialchars(CACHE_DIR) ?></code> (careful!)</li>
    <li>Check PHP error logs for FFmpeg errors</li>
    <li>Test FFmpeg manually: <code>ffmpeg -ss 5 -i "VIDEO_FILE" -vframes 1 output.jpg</code></li>
  </ul>
</div>

<hr style="border: 1px solid rgba(168,139,250,0.2); margin: 40px 0;">
<p style="color: #94a3b8; font-size: 12px;">Last updated: <?= date('Y-m-d H:i:s') ?></p>

</body>
</html>
