<?php
/**
 * Cache Manager with file modification tracking
 */

class Cache {
    private static $instance = null;
    private $data = [];
    private $timestamps = [];

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get cached value if valid
     */
    public static function get($key, $watchFiles = []) {
        $instance = self::getInstance();
        
        // Check if key exists and is valid
        if (!isset($instance->data[$key])) {
            return null;
        }

        // Check if watched files have changed
        if (!empty($watchFiles)) {
            $cachedTime = $instance->timestamps[$key] ?? 0;
            foreach ((array)$watchFiles as $file) {
                if (file_exists($file)) {
                    $mtime = filemtime($file);
                    if ($mtime > $cachedTime) {
                        unset($instance->data[$key]);
                        unset($instance->timestamps[$key]);
                        return null;
                    }
                }
            }
        }

        return $instance->data[$key];
    }

    /**
     * Set cached value
     */
    public static function set($key, $value, $watchPath = null) {
        $instance = self::getInstance();
        $instance->data[$key] = $value;
        
        // Store current time for watch comparison
        $watchTime = 0;
        if ($watchPath && file_exists($watchPath)) {
            $watchTime = max(
                filemtime($watchPath),
                is_dir($watchPath) ? self::getDirMtime($watchPath) : 0
            );
        }
        $instance->timestamps[$key] = $watchTime;
    }

    /**
     * Get directory modification time (recursive)
     */
    private static function getDirMtime($dir, $recursive = true) {
        $maxTime = filemtime($dir);
        if (!$recursive) return $maxTime;

        $entries = @scandir($dir);
        if (!$entries) return $maxTime;

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $time = self::getDirMtime($path, true);
                $maxTime = max($maxTime, $time);
            } else {
                $maxTime = max($maxTime, filemtime($path));
            }
        }
        return $maxTime;
    }

    /**
     * File-based cache (persistent)
     */
    public static function getFile($cacheFile) {
        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            return json_decode($content, true);
        }
        return null;
    }

    /**
     * Save to file cache
     */
    public static function setFile($cacheFile, $data) {
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Clear cache for key
     */
    public static function clear($key) {
        $instance = self::getInstance();
        unset($instance->data[$key]);
        unset($instance->timestamps[$key]);
    }
}
