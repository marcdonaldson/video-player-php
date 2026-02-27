<?php
/**
 * Resume position handler
 */

require_once __DIR__ . '/../config/config.php';

function loadResume() {
    if (!file_exists(RESUME_FILE)) return [];
    return json_decode(file_get_contents(RESUME_FILE), true) ?? [];
}

function saveResume($data) {
    if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);
    file_put_contents(RESUME_FILE, json_encode($data));
}

header('Content-Type: application/json');

// GET - retrieve all resume positions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(loadResume());
    exit;
}

// POST - save resume position
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $file = safeRelPath($body['file'] ?? '');
    $position = (float)($body['position'] ?? 0);

    if ($file && $position > 0) {
        $data = loadResume();
        $data[$file] = ['position' => $position, 'saved' => time()];
        saveResume($data);
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
exit;
