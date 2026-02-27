<?php
/**
 * Media Scanner with intelligent caching
 */

require_once __DIR__ . '/Cache.php';

class MediaScanner {
    private $videoExt;
    private $audioExt;

    public function __construct($videoExt, $audioExt) {
        $this->videoExt = $videoExt;
        $this->audioExt = $audioExt;
    }

    /**
     * Get directory hash based on file modification times
     */
    private function getDirHash($absPath) {
        if (!is_dir($absPath)) return '';
        return md5($this->getDirMtime($absPath));
    }

    /**
     * Get latest modification time in directory tree
     */
    private function getDirMtime($dir) {
        $maxTime = filemtime($dir);
        $entries = @scandir($dir);
        if (!$entries) return $maxTime;

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $maxTime = max($maxTime, $this->getDirMtime($path));
            } else {
                $maxTime = max($maxTime, filemtime($path));
            }
        }
        return $maxTime;
    }

    /**
     * Scan directory and return folders/files with caching
     */
    public function scanDirectory($relDir = '') {
        $absDir = absPath($relDir);
        if (!is_dir($absDir)) {
            return ['folders' => [], 'files' => [], 'breadcrumbs' => []];
        }

        // Check cache validity
        $cacheKey = 'scan_' . hash('md5', $relDir) . '_' . $this->getDirHash($absDir);
        $cached = Cache::get($cacheKey, $absDir);
        if ($cached !== null) {
            return $cached;
        }

        // Scan directory
        $entries = scandir($absDir);
        $folders = [];
        $files = [];

        if ($entries) {
            sort($entries);
            foreach ($entries as $e) {
                if ($e === '.' || $e === '..') continue;
                $full = $absDir . '/' . $e;
                if (is_dir($full)) {
                    $folders[] = $e;
                } elseif (is_file($full) && ($this->isVideo($e) || $this->isAudio($e))) {
                    $files[] = $e;
                }
            }
        }

        // Build breadcrumbs
        $breadcrumbs = [];
        if ($relDir) {
            $parts = explode('/', $relDir);
            $acc = '';
            foreach ($parts as $p) {
                $acc = $acc ? "$acc/$p" : $p;
                $breadcrumbs[] = ['label' => $p, 'path' => $acc];
            }
        }

        $result = [
            'folders' => $folders,
            'files' => $files,
            'breadcrumbs' => $breadcrumbs
        ];

        // Cache result
        Cache::set($cacheKey, $result, $absDir);

        return $result;
    }

    /**
     * Get file metadata with duration caching
     */
    public function getFileMetadata($relPath) {
        $absPath = absPath($relPath);
        if (!file_exists($absPath)) return null;

        $filename = basename($relPath);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $size = filesize($absPath);
        $sizeMb = round($size / 1048576, 1);

        return [
            'name' => $filename,
            'rel' => $relPath,
            'abs' => $absPath,
            'ext' => $ext,
            'size' => $size,
            'size_mb' => $sizeMb,
            'is_video' => $this->isVideo($filename),
            'is_audio' => $this->isAudio($filename),
            'duration' => $this->getDuration($absPath),
            'mtime' => filemtime($absPath)
        ];
    }

    /**
     * Get duration with file cache
     */
    private function getDuration($absPath) {
        $cacheFile = DURATIONS_CACHE;
        $durations = Cache::getFile($cacheFile) ?? [];
        $key = md5($absPath);

        if (isset($durations[$key])) {
            $cached = $durations[$key];
            // Verify file hasn't changed
            if ($cached['mtime'] === filemtime($absPath)) {
                return $cached['formatted'];
            }
        }

        // Call ffprobe
        $cmd = sprintf(
            '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            FFPROBE, escapeshellarg($absPath)
        );
        $secs = (float) trim((string)(shell_exec($cmd) ?? '0'));

        if ($secs <= 0) return '';

        $h = floor($secs / 3600);
        $m = floor(($secs % 3600) / 60);
        $s = floor($secs % 60);
        $formatted = $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);

        // Cache result
        $durations[$key] = [
            'formatted' => $formatted,
            'mtime' => filemtime($absPath)
        ];
        Cache::setFile($cacheFile, $durations);

        return $formatted;
    }

    private function isVideo($filename) {
        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $this->videoExt);
    }

    private function isAudio($filename) {
        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $this->audioExt);
    }

    /**
     * Recursively collect media for playlists
     */
    public function collectMedia($absDir, $relDir = '') {
        $results = [];
        $entries = scandir($absDir);
        if (!$entries) return $results;

        sort($entries);
        foreach ($entries as $e) {
            if ($e === '.' || $e === '..') continue;
            $abs = $absDir . '/' . $e;
            $rel = $relDir ? "$relDir/$e" : $e;
            if (is_dir($abs)) {
                $results = array_merge($results, $this->collectMedia($abs, $rel));
            } elseif (is_file($abs) && ($this->isVideo($e) || $this->isAudio($e))) {
                $results[] = ['title' => $e, 'url' => publicUrl($rel)];
            }
        }
        return $results;
    }
}
