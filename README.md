# 🎬 StreamBox - Netflix-Style Video Player

A modern, modular PHP video player with intelligent caching and a dedicated watch page. Perfect for self-hosted media streaming.

## 🆕 Latest Updates (v2.0)

✅ **Fixed thumbnail preview system** - Now correctly detects FFmpeg and generates previews  
✅ **Improved path resolution** - Media root now properly points to parent directory  
✅ **Better error handling** - Debug page shows detailed diagnostics  
✅ **Auto cache invalidation** - Directory changes detected automatically  
✅ **Netflix-style watch page** - Dedicated player with back button overlay

## ✨ Features

### Architecture
- **Modular Design**: API handlers separated into independent files
- **Intelligent Caching**: Directory scans cached with automatic reindexing on file changes
- **File Modification Tracking**: Uses file modification times to detect directory changes
- **Smart Duration Caching**: FFprobe results cached to reduce system calls

### Performance
- 🚀 Fast directory listing through in-memory and file-based caching
- 📦 Automatic cache invalidation when files/folders are added or modified
- 💾 Persistent duration cache with modification time verification
- 🎥 Optimized FFmpeg thumbnail generation

### User Experience
- 🎬 Netflix-style dedicated watch page with custom player controls
- ◀️ Back button overlaid on video on hover
- 📱 Fully responsive design for mobile, tablet, and desktop
- ⏯️ Play/pause, volume, speed controls, fullscreen, keyboard shortcuts
- ✨ Resume playback from where you left off
- 🎨 Modern dark theme with purple/pink accents
- 🌗 Smooth animations and hover effects

### Media Support
- **Video**: MP4, MKV, AVI, MOV, WebM, M4V, FLV, WMV, TS
- **Audio**: MP3, AAC, FLAC, OGG, WAV, M4A, Opus

## 📁 Project Structure

```
video-player-php/
├── index.php                 # Home page with directory listing
├── watch.php                 # Netflix-style watch page for playback
├── debug.php                 # 🔍 Diagnostic dashboard for troubleshooting
├── setup.sh                  # Quick setup script
├── install_check.sh          # Verify requirements
├── THUMBNAIL_GUIDE.md        # 🖼️ Thumbnail troubleshooting guide
├── config/
│   └── config.php            # Shared configuration & helpers
├── lib/
│   ├── Cache.php             # Caching system with file tracking
│   └── MediaScanner.php      # Directory scanning with intelligent caching
├── api/
│   ├── stream.php            # HTTP range streaming handler
│   ├── thumb.php             # FFmpeg thumbnail generator
│   ├── playlist.php          # XSPF playlist generator
│   └── resume.php            # Resume position manager
└── .cache/                   # Automatic cache directory
    ├── thumbs/               # Generated video thumbnails
    ├── durations.json       # Video duration cache
    └── resume.json          # Playback resume positions
```

## 🚀 Setup & Installation

### Requirements
- PHP 7.4+
- FFmpeg (for thumbnail generation)
- FFprobe (for duration detection)
- jpegoptim (optional, for thumbnail optimization)

### Installation

1. **Drop in your media directory**:
   ```bash
   cp -r video-player-php ~/media_folder/
   cd ~/media_folder/video-player-php
   ```

2. **Run setup script**:
   ```bash
   bash setup.sh
   ```

3. **Ensure write permissions**:
   ```bash
   chmod 755 . config lib api .cache .cache/thumbs
   ```

4. **Start a local server** (PHP 7.4+):
   ```bash
   php -S localhost:8000
   ```

5. **Visit**: `http://localhost:8000/video-player-php`

## 🎯 How It Works

### Intelligent Caching
- **Directory Scanning**: First visit scans directory and caches structure based on modification times
- **Automatic Reindexing**: If files are added/removed, the hash changes and cache is invalidated
- **In-Memory Cache**: Session-based caching for fast repeated access
- **File-Based Cache**: Persistent caching for durations and thumbnails

### Smart Cache Invalidation
```php
// The scanner detects changes automatically
$scan = $scanner->scanDirectory($relDir);
// If any file/folder in the directory tree is modified (mtime),
// the cache key changes and a fresh scan is performed
```

### Resume Functionality
- Saves playback position every 5 seconds after 2 seconds of play
- Tracks resume position per file in `.cache/resume.json`
- Automatically resumes on next playback

## 🎮 Keyboard Shortcuts

| Key | Action |
|-----|--------|
| `Space` | Play/Pause |
| `F` | Fullscreen |
| `M` | Mute |
| `←` / `→` | Seek -10s / +10s |
| `↑` / `↓` | Volume up / down |

## 🎨 Customization

### Configuration
Edit `config/config.php` to customize:

```php
// Thumbnail dimensions
define('THUMB_W', 400);
define('THUMB_H', 225);

// FFmpeg quality (1-31, lower = better)
define('THUMB_QUALITY', 5);

// Supported formats
$GLOBALS['videoExt'] = ['mp4','mkv','avi',...];
$GLOBALS['audioExt'] = ['mp3','aac','flac',...];

// Base URL (auto-detected, override if needed)
define('BASE_URL', 'https://example.com/media');
```

### Styling
The interface uses Tailwind CSS. To customize colors:
1. Modify the inline styles in `index.php` and `watch.php`
2. Replace purple/pink colors with your preferred scheme
3. Adjust gradients and shadows to match your brand

## 📊 API Endpoints

### Stream Handler
```php
GET api/stream.php?file=path/to/video.mp4
```
Supports HTTP Range requests for seeking.

### Thumbnail Generator
```php
GET api/thumb.php?file=path/to/video.mp4
```
Auto-generates and caches thumbnails on first request.

### Playlist Generator
```php
GET api/playlist.php?path=folder/or/file
```
Returns XSPF playlist for VLC or other players.

### Resume Manager
```php
POST api/resume.php
GET api/resume.php
```
Save and retrieve playback positions.

## 🔒 Security Notes

- File paths are validated with `safeRelPath()` to prevent directory traversal
- All user input is HTML-escaped using `htmlspecialchars()`
- URLs are properly encoded with `rawurlencode()`
- HTTP range requests are validated
- Session-based (uses `session_start()`)

## 🐛 Troubleshooting

### Thumbnails not generating
- **Check FFmpeg**: `which ffmpeg` should show path
- **Verify permissions**: `chmod 755 .cache/thumbs`
- **Check file exists**: Verify video files are readable
- **Use debug page**: Visit `http://localhost:8000/video-player-php/debug.php`
- **Read guide**: See [THUMBNAIL_GUIDE.md](THUMBNAIL_GUIDE.md) for detailed help

### Durations show as blank
- Verify FFprobe installed: `which ffprobe`
- Check file format supported (MP4, MKV, etc.)
- See debug.php for detailed diagnostics

### Caching issues
- **Reset all cache**: `rm -rf .cache/thumbs/*`
- **Clear browser cache**: Ctrl+Shift+Del (or Cmd+Shift+Delete)
- **Modify a file**: Changes trigger automatic cache invalidation

### Mobile playback issues
- Check browser HTML5 support
- Try different format (WebM, MP4, HLS)
- Enable hardware acceleration in browser

## 📝 Performance Tips

1. **Optimize thumbnails**: Install `jpegoptim` for smaller images
2. **Use fast disks**: SSD recommended for large media collections
3. **Configure FFmpeg presets**: Reduce quality for faster generation
4. **Enable browser caching**: Thumbnails cached for 24 hours
5. **Use streaming-compatible formats**: MP4, WebM, HLS for best performance

## 🔄 Cache Structure

```json
// .cache/index.json (auto-generated)
{
  "scan_hash_123": {
    "folders": ["Series", "Movies"],
    "files": ["video.mp4"],
    "breadcrumbs": []
  }
}

// .cache/durations.json
{
  "md5_hash": {
    "formatted": "1:23:45",
    "mtime": 1677000000
  }
}

// .cache/resume.json
{
  "path/to/video.mp4": {
    "position": 1234.5,
    "saved": 1677000000
  }
}
```

## 📜 License

Use freely for personal and commercial purposes.

## 🎯 Future Enhancements

- [ ] Subtitle support
- [ ] Playlist creation UI
- [ ] Search functionality
- [ ] Favorite/wishlist system
- [ ] Subtitle track selection
- [ ] Multi-language UI
- [ ] Dark/light theme toggle
- [ ] Admin panel for cache management

Enjoy your personal video library! 🍿
