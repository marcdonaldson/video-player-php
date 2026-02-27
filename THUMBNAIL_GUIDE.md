# 🖼️ Thumbnail Preview Setup Guide

## Quick Troubleshooting

If thumbnails are not showing, follow these steps:

### 1. Verify FFmpeg Installation

**macOS:**
```bash
brew install ffmpeg
ffmpeg -version  # Should show version info
```

**Linux (Ubuntu/Debian):**
```bash
sudo apt-get update
sudo apt-get install ffmpeg
ffmpeg -version  # Should show version info
```

**Windows:**
- Download from: https://ffmpeg.org/download.html
- Add to PATH or locate installation

### 2. Check Cache Permissions

```bash
cd /path/to/video-player-php
chmod -R 755 .cache
mkdir -p .cache/thumbs
chmod 755 .cache/thumbs
```

### 3. Verify File Paths

The default file structure expects:
```
/media/
├── Movies/
│   ├── video1.mp4
│   └── video2.mkv
├── Music/
│   └── song.mp3
└── video-player-php/  ← Application folder goes here
    ├── index.php
    ├── watch.php
    ├── config/
    ├── api/
    ├── lib/
    └── .cache/
```

### 4. Test Thumbnail Generation

Visit the debug page:
```
http://localhost:8000/video-player-php/debug.php
```

This page shows:
- ✓ System command availability
- ✓ File paths and permissions
- ✓ Found media files
- ✓ Test thumbnail generation

### 5. Manual FFmpeg Test

```bash
# Replace with your video path
ffmpeg -ss 5 -i /path/to/video.mp4 -vframes 1 -vf "scale=400:225" test.jpg

# If this creates test.jpg, FFmpeg works correctly
```

## What Was Fixed

### Path Resolution
- **Before**: `MEDIA_ROOT = dirname(__DIR__)` pointed to app directory
- **After**: `MEDIA_ROOT = dirname(dirname(__DIR__))` points to parent directory (media folder)

### Thumbnail Generation
- Improved FFmpeg command with explicit quality settings
- Better error handling and validation
- Fallback SVG placeholder when generation fails
- Automatic cleanup of failed thumbnails

### Better Error Detection
- Checks if FFmpeg is available before running
- Verifies output file was actually created
- Returns meaningful errors in debug.php

## File-by-File Changes

### config/config.php
```php
// Now correctly points to parent directory
define('MEDIA_ROOT', dirname(dirname(__DIR__)));
```

### api/thumb.php
- Uses `which ffmpeg` to verify command availability
- Properly handles stdout/stderr with `2>&1`
- Validates thumbnail size before considering it successful
- Cleans up failed generation attempts

## Caching System

Thumbnails are cached in: `.cache/thumbs/`

- **Filename**: `md5(absolute_path).jpg`
- **Validity**: 24-hour browser cache
- **Regeneration**: Automatic on demand if missing

**To reset all thumbnails:**
```bash
rm -rf video-player-php/.cache/thumbs/*
```

## Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| Blank thumbnails | Check FFmpeg installed: `which ffmpeg` |
| "No Preview" shown | FFmpeg not found or file not readable |
| Slow loading | First time generates all thumbnails, subsequent loads are instant |
| Cache not updating | Manually delete `.cache/thumbs/` and reload |
| Permission denied | Run `chmod 755 .cache/thumbs` |

## Performance Tips

1. **Reduce thumbnail generation time**:
   - Use `THUMB_QUALITY = 15` (lower = faster, lower quality)
   - Current: `THUMB_QUALITY = 5` (excellent quality, slower)

2. **Optimize thumbnail size**:
   - Install jpegoptim: reduces final size by 30-50%
   - Automatic if available, fallback works without it

3. **Browser caching**:
   - Thumbnails cached for 24 hours
   - Clear browser cache to see new thumbnails immediately

## Debug Commands

```bash
# View all cached thumbnails
ls -lh video-player-php/.cache/thumbs/

# Check generated thumbnail
file video-player-php/.cache/thumbs/HASH.jpg
identify video-player-php/.cache/thumbs/HASH.jpg  # requires ImageMagick

# Monitor FFmpeg execution
# (View browser console for errors)
```

## Advanced Configuration

Edit `config/config.php`:

```php
// Thumbnail dimensions
define('THUMB_W', 400);        // Width in pixels
define('THUMB_H', 225);        // Height in pixels

// FFmpeg compression (1-31, lower = better quality but slower)
define('THUMB_QUALITY', 5);    // Default: 5 for high quality

// FFmpeg/FFprobe paths (auto-detected, override if needed)
define('FFMPEG',  'ffmpeg');   // or '/usr/bin/ffmpeg'
define('FFPROBE', 'ffprobe');  // or '/usr/bin/ffprobe'
```

## Getting Help

If thumbnails still aren't working:

1. Visit: `http://localhost:8000/video-player-php/debug.php`
2. Check all green ✓ items
3. Note any red ✗ items
4. Fix those items based on the guide above
5. Refresh and test again

---

**Still stuck?** Check the error logs:
```bash
# PHP error log
tail -f /var/log/php-fpm.log
# or check your web server logs
```
