#!/bin/bash

# StreamBox Installation Verification Script
# Usage: bash install_check.sh

echo "🔧 StreamBox Installation Check"
echo "=================================="
echo ""

# Check PHP
echo "✓ Checking PHP..."
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1)
    echo "  ✓ PHP installed: $PHP_VERSION"
else
    echo "  ✗ PHP not found (required)"
    exit 1
fi

# Check FFmpeg
echo "✓ Checking FFmpeg..."
if command -v ffmpeg &> /dev/null; then
    FFMPEG_VERSION=$(ffmpeg -version | head -n 1)
    echo "  ✓ FFmpeg installed: $FFMPEG_VERSION"
else
    echo "  ✗ FFmpeg not found (required for thumbnails)"
    echo "    Install: brew install ffmpeg (macOS) or apt-get install ffmpeg (Linux)"
fi

# Check FFprobe
echo "✓ Checking FFprobe..."
if command -v ffprobe &> /dev/null; then
    echo "  ✓ FFprobe installed (required for duration detection)"
else
    echo "  ✗ FFprobe not found (part of FFmpeg)"
    echo "    Install: brew install ffmpeg (macOS) or apt-get install ffmpeg (Linux)"
fi

# Check jpegoptim (optional)
echo "✓ Checking jpegoptim (optional)..."
if command -v jpegoptim &> /dev/null; then
    echo "  ✓ jpegoptim installed (will optimize thumbnails)"
else
    echo "  ⚠ jpegoptim not found (optional, thumbnails still work without it)"
    echo "    Install: brew install jpegoptim (macOS) or apt-get install jpegoptim (Linux)"
fi

# Check directory structure
echo ""
echo "✓ Checking directory structure..."
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

if [ -f "$SCRIPT_DIR/index.php" ]; then
    echo "  ✓ index.php found"
else
    echo "  ✗ index.php not found"
fi

if [ -f "$SCRIPT_DIR/watch.php" ]; then
    echo "  ✓ watch.php found"
else
    echo "  ✗ watch.php not found"
fi

if [ -d "$SCRIPT_DIR/config" ]; then
    echo "  ✓ config/ directory found"
else
    echo "  ✗ config/ directory not found"
fi

if [ -d "$SCRIPT_DIR/api" ]; then
    echo "  ✓ api/ directory found"
else
    echo "  ✗ api/ directory not found"
fi

if [ -d "$SCRIPT_DIR/lib" ]; then
    echo "  ✓ lib/ directory found"
else
    echo "  ✗ lib/ directory not found"
fi

# Create cache directory
echo ""
echo "✓ Checking cache directory..."
if [ ! -d "$SCRIPT_DIR/.cache" ]; then
    mkdir -p "$SCRIPT_DIR/.cache/thumbs"
    echo "  ✓ Created .cache directory"
else
    echo "  ✓ .cache directory exists"
fi

# Check permissions
echo ""
echo "✓ Checking permissions..."
if [ -w "$SCRIPT_DIR/.cache" ]; then
    echo "  ✓ .cache is writable"
else
    echo "  ✗ .cache is not writable"
    echo "    Fix: chmod 755 $SCRIPT_DIR/.cache"
fi

echo ""
echo "=================================="
echo "✓ Installation check complete!"
echo ""
echo "📝 Next steps:"
echo "  1. Place media files in the parent directory of video-player-php"
echo "  2. Start PHP: php -S localhost:8000"
echo "  3. Visit: http://localhost:8000/video-player-php/index.php"
echo "  4. Debug: http://localhost:8000/video-player-php/debug.php"
echo ""
