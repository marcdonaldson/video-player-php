<?php
/**
 * Netflix-Style PHP Video Player
 * Modular architecture with intelligent caching
 * Requirements: PHP 7.4+, FFmpeg for thumbnails
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/Cache.php';
require_once __DIR__ . '/lib/MediaScanner.php';

// ── Initialize Scanner ────────────────────────────────────────────────────────
$scanner = new MediaScanner($GLOBALS['videoExt'], $GLOBALS['audioExt']);

// Directory listing with intelligent caching
$relDir = isset($_GET['dir']) ? safeRelPath($_GET['dir']) : '';
$scan = $scanner->scanDirectory($relDir);
$folders = $scan['folders'];
$files = $scan['files'];
$breadcrumbs = $scan['breadcrumbs'];

// Resume data
$resumeData = [];
if (file_exists(RESUME_FILE)) {
    $resumeData = json_decode(file_get_contents(RESUME_FILE), true) ?? [];
}

// VLC URL base
$scriptAbsBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StreamBox - Netflix-Style Video Player</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  * { -webkit-tap-highlight-color: transparent; }
  body {
    background: linear-gradient(135deg, #0a0e27 0%, #0f172a 50%, #0a0e27 100%);
    color: #e2e8f0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif;
    min-height: 100vh;
  }
  
  /* Header */
  .header { background: rgba(10, 14, 39, 0.85); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(51, 65, 85, 0.3); position: sticky; top: 0; z-index: 40; }
  
  .thumb-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.6) 100%);
    border: 1px solid rgba(71, 85, 105, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    backdrop-filter: blur(10px);
  }
  
  .thumb-card:hover {
    border-color: rgba(168, 139, 250, 0.6);
    box-shadow: 0 20px 25px -5px rgba(168, 139, 250, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.4);
    transform: translateY(-8px);
  }
  
  .thumb-overlay { opacity: 0; transition: opacity 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
  .thumb-card:hover .thumb-overlay { opacity: 1; }
  
  ::-webkit-scrollbar { width: 8px; height: 8px; }
  ::-webkit-scrollbar-track { background: rgba(30, 41, 59, 0.4); }
  ::-webkit-scrollbar-thumb { background: linear-gradient(to bottom, #c084fc, #a78bfa); border-radius: 4px; }
  ::-webkit-scrollbar-thumb:hover { background: linear-gradient(to bottom, #d8b4fe, #c4b5fd); }
  
  .vlc-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 6px;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
  }
  
  .vlc-btn:hover {
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    box-shadow: 0 8px 16px rgba(245, 158, 11, 0.4);
    transform: translateY(-2px);
  }
  
  .xspf-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
    color: #c084fc;
    font-size: 11px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 6px;
    text-decoration: none;
    white-space: nowrap;
    border: 1px solid rgba(168, 139, 250, 0.4);
    transition: all 0.3s ease;
    cursor: pointer;
  }
  
  .xspf-btn:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.3) 0%, rgba(139, 92, 246, 0.3) 100%);
    color: #f3e8ff;
    border-color: rgba(168, 139, 250, 0.8);
    box-shadow: 0 4px 12px rgba(168, 139, 250, 0.2);
  }
  
  .url-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(51, 65, 85, 0.5);
    color: #cbd5e1;
    font-size: 11px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 6px;
    text-decoration: none;
    white-space: nowrap;
    border: 1px solid rgba(71, 85, 105, 0.5);
    transition: all 0.3s ease;
    cursor: pointer;
  }
  
  .url-btn:hover {
    background: rgba(71, 85, 105, 0.7);
    color: #fff;
    border-color: rgba(71, 85, 105, 0.8);
  }
  
  .badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    backdrop-filter: blur(10px);
  }
  
  .badge-video { background: rgba(59, 130, 246, 0.3); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.5); }
  .badge-duration { background: rgba(71, 85, 105, 0.4); color: #cbd5e1; border: 1px solid rgba(71, 85, 105, 0.6); }
  .badge-resume { background: linear-gradient(135deg, rgba(236, 72, 153, 0.3) 0%, rgba(168, 139, 250, 0.3) 100%); color: #f472b6; border: 1px solid rgba(168, 139, 250, 0.5); }
  
  @media (max-width: 640px) {
    body { font-size: 14px; }
    .grid { gap: 1rem !important; }
    .thumb-card:hover { transform: translateY(-4px); }
  }
  
  @media (hover: none) {
    .thumb-overlay { opacity: 0.9; }
  }
</style>
</head>
<body class="min-h-screen">
<div class="header">
  <div class="max-w-7xl mx-auto px-4 py-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        </div>
        <h1 class="text-xl font-bold text-white tracking-tight">StreamBox</h1>
      </div>
      <code class="text-xs text-slate-400 bg-slate-900 px-3 py-2 rounded max-w-sm truncate"><?= htmlspecialchars(BASE_URL) ?></code>
    </div>
  </div>
</div>

<div class="max-w-7xl mx-auto px-4 py-6 md:py-8">

  <!-- Breadcrumb -->
  <nav class="flex items-center gap-2 text-sm mb-8 text-slate-400 flex-wrap">
    <a href="?" class="hover:text-purple-400 transition-colors duration-200 flex items-center gap-1">
      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
      Home
    </a>
    <?php foreach ($breadcrumbs as $bc): ?>
      <span class="text-slate-600">/</span>
      <a href="?dir=<?= urlencode($bc['path']) ?>" class="hover:text-purple-400 transition-colors duration-200"><?= htmlspecialchars($bc['label']) ?></a>
    <?php endforeach; ?>
  </nav>

  <!-- ── Folder Grid ────────────────────────────────────────────────────────── -->
  <?php if ($folders): ?>
  <div class="mb-10 md:mb-14">
    <h2 class="text-xs md:text-sm font-bold text-slate-300 uppercase tracking-widest mb-4 md:mb-6">📁 Collections</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 md:gap-4">
      <?php foreach ($folders as $folder):
        $folderRel = $relDir ? "$relDir/$folder" : $folder;
        $subAbs    = absPath($folderRel);
        $subCount  = 0;
        foreach (@scandir($subAbs) ?: [] as $sf) {
            if (isVideo($sf) || isAudio($sf)) $subCount++;
        }
        $folderXspfUrl   = 'api/playlist.php?path=' . urlencode($folderRel);
        $folderAbsXspf   = $scriptAbsBase . '/api/playlist.php?path=' . urlencode($folderRel);
        $folderVlcUrl    = 'vlc://' . $folderAbsXspf;
      ?>
      <div class="thumb-card flex flex-col rounded-lg overflow-hidden group">
        <a href="?dir=<?= urlencode($folderRel) ?>" class="flex flex-col items-center justify-center gap-2 p-4 flex-1 bg-gradient-to-br from-slate-700 to-slate-800">
          <div class="text-4xl md:text-5xl group-hover:scale-110 transition-transform duration-300">📁</div>
          <span class="text-xs md:text-sm font-semibold text-center text-white leading-tight line-clamp-2" title="<?= htmlspecialchars($folder) ?>"><?= htmlspecialchars($folder) ?></span>
          <?php if ($subCount > 0): ?>
            <span class="text-xs text-slate-400"><?= $subCount ?> file<?= $subCount !== 1 ? 's' : '' ?></span>
          <?php endif; ?>
        </a>
        <div class="px-2 py-2 bg-slate-900/50 border-t border-slate-700/50 flex gap-1 justify-center flex-wrap">
          <a href="<?= htmlspecialchars($folderVlcUrl) ?>" class="vlc-btn text-xs" title="Play all files in VLC" onclick="event.stopPropagation()">VLC</a>
          <a href="<?= htmlspecialchars($folderXspfUrl) ?>" class="xspf-btn text-xs" title="Download playlist" download onclick="event.stopPropagation()">List</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Media Files Grid ──────────────────────────────────────────────────── -->
  <?php if ($files): ?>
  <div>
    <div class="flex items-start md:items-center justify-between mb-4 md:mb-6 flex-col md:flex-row gap-4">
      <h2 class="text-xs md:text-sm font-bold text-slate-300 uppercase tracking-widest">
        🎬 Media <span class="text-slate-500">(<?= count($files) ?>)</span>
      </h2>
      <?php if ($relDir): ?>
      <div class="flex gap-2 flex-wrap w-full md:w-auto">
        <?php
          $curXspf    = 'api/playlist.php?path=' . urlencode($relDir);
          $curAbsXspf = $scriptAbsBase . '/api/playlist.php?path=' . urlencode($relDir);
          $curVlc     = 'vlc://' . $curAbsXspf;
        ?>
        <a href="<?= htmlspecialchars($curVlc) ?>" class="vlc-btn text-xs md:text-sm px-3 py-2 flex-1 md:flex-none justify-center">🎬 Play All</a>
        <a href="<?= htmlspecialchars($curXspf) ?>" class="xspf-btn text-xs md:text-sm px-3 py-2 flex-1 md:flex-none justify-center" download>📋 Playlist</a>
      </div>
      <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-6">
      <?php foreach ($files as $file):
        $fileRel   = $relDir ? "$relDir/$file" : $file;
        $absFile   = absPath($fileRel);
        $meta = $scanner->getFileMetadata($fileRel);
        $hasResume = isset($resumeData[$fileRel]);
        $resumePos = $hasResume ? (float)$resumeData[$fileRel]['position'] : 0;
        $directUrl = publicUrl($fileRel);
        $xspfUrl   = 'api/playlist.php?path=' . urlencode($fileRel);
        $vlcUrl    = 'vlc://' . $directUrl;
        $watchUrl  = 'watch.php?file=' . urlencode($fileRel);
      ?>
      <a href="<?= htmlspecialchars($watchUrl) ?>" class="thumb-card rounded-lg overflow-hidden flex flex-col h-full hover:no-underline group">

        <?php if ($meta['is_video']): ?>
        <div class="relative aspect-video bg-slate-900 overflow-hidden flex-shrink-0">
          <img src="api/thumb.php?file=<?= urlencode($fileRel) ?>" alt=""
               class="w-full h-full object-cover"
               onerror="this.style.display='none'">
          <div class="thumb-overlay absolute inset-0 bg-gradient-to-t from-black/60 to-black/0 flex items-center justify-center">
            <div class="w-16 h-16 rounded-full bg-purple-600/90 flex items-center justify-center shadow-2xl hover:bg-purple-500 transition-colors duration-200">
              <svg class="w-7 h-7 text-white ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </div>
          </div>
          <?php if ($hasResume): ?>
          <div class="absolute bottom-0 left-0 right-0 h-1 bg-slate-700">
            <div class="h-1 bg-gradient-to-r from-purple-500 to-pink-500" style="width:<?= min(95, (int)($resumePos / 7200 * 100)) ?>%"></div>
          </div>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="aspect-video bg-gradient-to-br from-purple-900/40 to-slate-900 flex items-center justify-center flex-shrink-0">
          <span class="text-5xl">🎵</span>
        </div>
        <?php endif; ?>

        <div class="p-3 md:p-4 flex-1 flex flex-col">
          <p class="text-sm md:text-base font-semibold text-white line-clamp-2 leading-tight" title="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></p>
          <div class="flex items-center gap-1.5 mt-2 md:mt-3 text-xs text-slate-400 flex-wrap">
            <span class="badge badge-video"><?= htmlspecialchars($meta['ext']) ?></span>
            <span class="badge badge-duration"><?= $meta['size_mb'] ?> MB</span>
            <?php if ($meta['duration']): ?><span class="badge badge-duration"><?= $meta['duration'] ?></span><?php endif; ?>
            <?php if ($hasResume): ?>
            <span class="badge badge-resume">▶ Resume</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="px-3 md:px-4 py-2 md:py-3 bg-slate-900/50 border-t border-slate-700/50 flex gap-1.5 flex-wrap items-center justify-center" onclick="event.preventDefault(); event.stopPropagation();">
          <a href="<?= htmlspecialchars($vlcUrl) ?>" class="vlc-btn text-xs" title="Open in VLC">VLC</a>
          <a href="<?= htmlspecialchars($xspfUrl) ?>" class="xspf-btn text-xs" title="Download playlist" download>List</a>
          <a href="<?= htmlspecialchars($directUrl) ?>" class="url-btn text-xs" title="Direct URL" target="_blank">Link</a>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php elseif (!$folders): ?>
  <div class="text-center py-20 text-slate-500">
    <p class="text-6xl md:text-7xl mb-4">📂</p>
    <p class="text-lg md:text-xl">No media files found.</p>
  </div>
  <?php endif; ?>

</div><!-- /container -->

</body>
</html>
