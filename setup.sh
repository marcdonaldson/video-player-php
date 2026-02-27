#!/bin/bash

# StreamBox Quick Setup
# This script sets up proper file structure and permissions

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

echo "🎬 StreamBox Setup"
echo "=================="
echo ""

# Create cache directories
echo "📁 Creating cache directories..."
mkdir -p "$SCRIPT_DIR/.cache/thumbs"
mkdir -p "$SCRIPT_DIR/.cache/durations"
echo "   ✓ Cache directories created"

# Set permissions
echo "🔐 Setting permissions..."
chmod 755 "$SCRIPT_DIR/.cache"
chmod 755 "$SCRIPT_DIR/.cache/thumbs"
chmod 755 "$SCRIPT_DIR/.cache/durations"
echo "   ✓ Permissions set"

# Show next steps
echo ""
echo "=================="
echo "✓ Setup complete!"
echo ""
echo "📝 Next steps:"
echo "  1. Place media files in parent directory"
echo "     Structure:"
echo "       ~/media/"
echo "       ├── Movie1.mp4"
echo "       ├── Movie2.mkv"  
echo "       ├── Music/"
echo "       │   └── Song.mp3"
echo "       └── video-player-php/  ← This folder"
echo ""
echo "  2. Start the PHP server:"
echo "     php -S localhost:8000"
echo ""
echo "  3. Open in browser:"
echo "     http://localhost:8000/video-player-php/index.php"
echo ""
echo "  4. Verify setup:"
echo "     http://localhost:8000/video-player-php/debug.php"
echo ""
echo "💡 Requirements:"
echo "   - PHP 7.4+"
echo "   - FFmpeg (for thumbnails)"
echo "   - FFprobe (for duration detection)"
echo ""
