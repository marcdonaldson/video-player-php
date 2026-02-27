<?php
/**
 * XSPF Playlist generator
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/MediaScanner.php';

if (!isset($_GET['path'])) {
    http_response_code(400);
    exit;
}

$rel = safeRelPath($_GET['path']);
$abs = absPath($rel);
$name = $rel ? basename($rel) : 'Media';

$scanner = new MediaScanner($GLOBALS['videoExt'], $GLOBALS['audioExt']);
$tracks = [];

if (is_dir($abs)) {
    $tracks = $scanner->collectMedia($abs, $rel);
    $filename = $name . '.xspf';
} elseif (is_file($abs)) {
    $tracks = [['title' => basename($abs), 'url' => publicUrl($rel)]];
    $filename = pathinfo(basename($abs), PATHINFO_FILENAME) . '.xspf';
} else {
    http_response_code(404);
    exit('Not found');
}

// Generate XSPF XML
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<playlist xmlns="http://xspf.org/ns/0/" version="1">' . "\n";
$xml .= '  <title>' . htmlspecialchars($name) . '</title>' . "\n";
$xml .= '  <trackList>' . "\n";
foreach ($tracks as $t) {
    $xml .= '    <track>' . "\n";
    $xml .= '      <title>' . htmlspecialchars($t['title']) . '</title>' . "\n";
    $xml .= '      <location>' . htmlspecialchars($t['url']) . '</location>' . "\n";
    $xml .= '    </track>' . "\n";
}
$xml .= '  </trackList>' . "\n";
$xml .= '</playlist>' . "\n";

header('Content-Type: application/xspf+xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
echo $xml;
exit;
