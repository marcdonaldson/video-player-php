<?php
/**
 * Netflix-Style Watch Page
 */

session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/MediaScanner.php';

if (!isset($_GET['file'])) {
    header('Location: index.php');
    exit;
}

$rel = safeRelPath($_GET['file']);
$abs = absPath($rel);

if (!file_exists($abs) || !is_file($abs)) {
    http_response_code(404);
    exit('File not found');
}

$scanner = new MediaScanner($GLOBALS['videoExt'], $GLOBALS['audioExt']);
$metadata = $scanner->getFileMetadata($rel);

if (!$metadata) {
    http_response_code(404);
    exit('Invalid file');
}

// Get resume data
$resumeData = [];
if (file_exists(RESUME_FILE)) {
    $resumeData = json_decode(file_get_contents(RESUME_FILE), true) ?? [];
}

$resumePos = $resumeData[$rel]['position'] ?? 0;

// Build URLs
$streamUrl = 'api/stream.php?file=' . urlencode($rel);
$playlistUrl = 'api/playlist.php?path=' . urlencode($rel);
$vlcUrl = 'vlc://' . publicUrl($rel);
$scriptAbsBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($metadata['name']) ?> - StreamBox</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  * { -webkit-tap-highlight-color: transparent; }
  body { background: #000; color: #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif; }
  html, body { width: 100%; height: 100%; margin: 0; padding: 0; }
  
  #player-wrap {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: #000;
    z-index: 10;
  }
  
  video { width: 100%; height: 100%; display: block; object-fit: contain; }
  
  .player-header {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0.8), transparent);
    padding: 2rem 2rem 3rem;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 20;
  }
  
  #player-wrap:hover .player-header { opacity: 1; }
  
  .back-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    color: #fff;
    padding: 10px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    font-size: 14px;
  }
  
  .back-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.3);
  }
  
  .controls {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.95), rgba(0,0,0,0.7), transparent);
    padding: 3rem 2rem 2rem;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 20;
  }
  
  #player-wrap:hover .controls { opacity: 1; }
  
  .progress-bar {
    position: relative;
    height: 6px;
    background: rgba(51, 65, 85, 0.6);
    border-radius: 9999px;
    cursor: pointer;
    margin-bottom: 1rem;
    transition: height 0.2s ease;
  }
  
  .progress-bar:hover { height: 8px; }
  
  .progress-fill {
    height: 100%;
    background: linear-gradient(to right, #c084fc, #a78bfa);
    border-radius: 9999px;
    pointer-events: none;
    box-shadow: 0 0 8px rgba(168, 139, 250, 0.6);
  }
  
  .progress-buffer {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    background: rgba(71, 85, 105, 0.5);
    border-radius: 9999px;
  }
  
  .control-buttons {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
  }
  
  .control-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #fff;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 0;
  }
  
  .control-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.1);
  }
  
  .control-btn svg { width: 20px; height: 20px; }
  
  .time-display {
    color: #cbd5e1;
    font-size: 14px;
    font-family: monospace;
    min-width: 120px;
  }
  
  .spacer { flex: 1; }
  
  select, input[type="range"] {
    background: rgba(51, 65, 85, 0.7);
    color: #fff;
    border: 1px solid rgba(71, 85, 105, 0.5);
    border-radius: 4px;
    padding: 6px 10px;
    cursor: pointer;
    font-size: 13px;
  }
  
  select:hover, input[type="range"]:hover {
    border-color: rgba(168, 139, 250, 0.5);
  }
  
  input[type="range"] { accent-color: #c084fc; }
  
  .spinner {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: none;
    z-index: 15;
  }
  
  .spinner.active {
    display: block;
  }
  
  .spinner-inner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(192, 132, 252, 0.3);
    border-top-color: #c084fc;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }
  
  @keyframes spin { to { transform: rotate(360deg); } }
  
  @media (max-width: 640px) {
    .player-header { padding: 1.5rem 1rem 2rem; }
    .controls { padding: 2rem 1rem 1rem; }
    .control-buttons { gap: 0.5rem; }
    .control-btn { width: 36px; height: 36px; }
    .time-display { font-size: 12px; min-width: 100px; }
  }
</style>
</head>
<body>

<div id="player-wrap">
  <video id="vid" preload="metadata"></video>
  
  <!-- Header with back button -->
  <div class="player-header">
    <a href="index.php" class="back-btn">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
      </svg>
      Back
    </a>
  </div>
  
  <!-- Controls -->
  <div class="controls">
    <div class="progress-bar" id="seek-bar">
      <div class="progress-buffer" id="buf-bar"></div>
      <div class="progress-fill" id="prog-bar"></div>
    </div>
    
    <div class="control-buttons">
      <button id="btn-play" class="control-btn" title="Play/Pause (Space)">
        <svg id="ico-play" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        <svg id="ico-pause" class="hidden" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
      </button>
      
      <button id="btn-mute" class="control-btn" title="Mute">
        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3A4.5 4.5 0 0014 7.97v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
      </button>
      
      <input id="vol-slider" type="range" min="0" max="1" step="0.05" value="1" style="width: 80px;">
      
      <span class="time-display">
        <span id="time-display">0:00 / 0:00</span>
      </span>
      
      <div class="spacer"></div>
      
      <select id="speed-sel">
        <option value="0.5">0.5×</option>
        <option value="0.75">0.75×</option>
        <option value="1" selected>1×</option>
        <option value="1.25">1.25×</option>
        <option value="1.5">1.5×</option>
        <option value="2">2×</option>
      </select>
      
      <button id="btn-fs" class="control-btn" title="Fullscreen (F)">
        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
      </button>
    </div>
  </div>
  
  <div class="spinner" id="spinner">
    <div class="spinner-inner"></div>
  </div>
</div>

<script>
// Player constants
const vid = document.getElementById('vid');
const seekBar = document.getElementById('seek-bar');
const progBar = document.getElementById('prog-bar');
const bufBar = document.getElementById('buf-bar');
const timeDisp = document.getElementById('time-display');
const spinner = document.getElementById('spinner');
const btnPlay = document.getElementById('btn-play');
const icoPlay = document.getElementById('ico-play');
const icoPause = document.getElementById('ico-pause');

let resumePos = <?= $resumePos ?>;
let seeking = false;

// Format time
function fmtTime(s) {
  s = Math.floor(s || 0);
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const ss = s % 60;
  const pad = n => String(n).padStart(2, '0');
  return h > 0 ? `${h}:${pad(m)}:${pad(ss)}` : `${m}:${pad(ss)}`;
}

// Initialize player
vid.src = '<?= htmlspecialchars($streamUrl) ?>';
vid.load();

// Resume playback
if (resumePos > 5) {
  vid.addEventListener('loadedmetadata', () => {
    vid.currentTime = resumePos;
  }, { once: true });
}

// Media events
vid.addEventListener('timeupdate', () => {
  if (!vid.duration) return;
  progBar.style.width = (vid.currentTime / vid.duration * 100) + '%';
  timeDisp.textContent = fmtTime(vid.currentTime) + ' / ' + fmtTime(vid.duration);
  clearTimeout(window.saveTimer);
  if (vid.currentTime > 2) {
    window.saveTimer = setTimeout(() => {
      saveResume('<?= htmlspecialchars($rel) ?>', vid.currentTime);
    }, 5000);
  }
});

vid.addEventListener('progress', () => {
  if (!vid.duration || !vid.buffered.length) return;
  bufBar.style.width = (vid.buffered.end(vid.buffered.length - 1) / vid.duration * 100) + '%';
});

vid.addEventListener('waiting', () => spinner.classList.add('active'));
vid.addEventListener('canplay', () => spinner.classList.remove('active'));
vid.addEventListener('play', updatePlayBtn);
vid.addEventListener('pause', updatePlayBtn);
vid.addEventListener('ended', updatePlayBtn);

// Update play button
function updatePlayBtn() {
  const paused = vid.paused || vid.ended;
  icoPlay.classList.toggle('hidden', !paused);
  icoPause.classList.toggle('hidden', paused);
}

// Controls
btnPlay.addEventListener('click', () => vid.paused ? vid.play().catch(() => {}) : vid.pause());
document.getElementById('btn-mute').addEventListener('click', () => vid.muted = !vid.muted);
document.getElementById('vol-slider').addEventListener('input', e => vid.volume = e.target.value);
document.getElementById('speed-sel').addEventListener('change', e => vid.playbackRate = parseFloat(e.target.value));

// Fullscreen
document.getElementById('btn-fs').addEventListener('click', () => {
  const wrap = document.getElementById('player-wrap');
  if (!document.fullscreenElement) {
    if (wrap.requestFullscreen) wrap.requestFullscreen().catch(() => {});
    else if (wrap.webkitRequestFullscreen) wrap.webkitRequestFullscreen();
    else if (wrap.mozRequestFullScreen) wrap.mozRequestFullScreen();
    else if (wrap.msRequestFullscreen) wrap.msRequestFullscreen();
  } else {
    if (document.exitFullscreen) document.exitFullscreen();
  }
});

// Seeking
function seekTo(e) {
  if (!vid.duration) return;
  const rect = seekBar.getBoundingClientRect();
  const clientX = e.clientX !== undefined ? e.clientX : e.touches?.[0]?.clientX;
  const pct = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
  vid.currentTime = pct * vid.duration;
  progBar.style.width = (pct * 100) + '%';
}

seekBar.addEventListener('mousedown', e => { seeking = true; seekTo(e); });
seekBar.addEventListener('touchstart', e => { seeking = true; seekTo(e); }, { passive: true });
document.addEventListener('mousemove', e => { if (seeking) seekTo(e); });
document.addEventListener('touchmove', e => { if (seeking) seekTo(e); }, { passive: true });
document.addEventListener('mouseup', () => { seeking = false; });
document.addEventListener('touchend', () => { seeking = false; });

// Keyboard shortcuts
document.addEventListener('keydown', e => {
  if (['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;
  
  if (e.key === ' ') { 
    e.preventDefault(); 
    vid.paused ? vid.play().catch(() => {}) : vid.pause(); 
  }
  if (e.key === 'ArrowRight') { e.preventDefault(); vid.currentTime = Math.min(vid.currentTime + 10, vid.duration || 0); }
  if (e.key === 'ArrowLeft') { e.preventDefault(); vid.currentTime = Math.max(vid.currentTime - 10, 0); }
  if (e.key === 'ArrowUp') { 
    e.preventDefault(); 
    const slider = document.getElementById('vol-slider');
    slider.value = Math.min(1, parseFloat(slider.value) + 0.1);
    vid.volume = slider.value;
  }
  if (e.key === 'ArrowDown') { 
    e.preventDefault(); 
    const slider = document.getElementById('vol-slider');
    slider.value = Math.max(0, parseFloat(slider.value) - 0.1);
    vid.volume = slider.value;
  }
  if (e.key === 'f' || e.key === 'F') { e.preventDefault(); document.getElementById('btn-fs').click(); }
  if (e.key === 'm' || e.key === 'M') { e.preventDefault(); document.getElementById('btn-mute').click(); }
});

// Save resume position
function saveResume(file, position) {
  if (!file || position <= 0) return;
  fetch('api/resume.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ file, position })
  }).catch(() => {});
}

// Save on page exit
window.addEventListener('beforeunload', () => {
  if (vid.currentTime > 2) {
    navigator.sendBeacon('api/resume.php', JSON.stringify({ 
      file: '<?= htmlspecialchars($rel) ?>', 
      position: vid.currentTime 
    }));
  }
});

// Auto-play
vid.play().catch(() => {});
updatePlayBtn();
</script>

</body>
</html>
