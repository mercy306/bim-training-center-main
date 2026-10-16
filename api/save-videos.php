<?php
/**
 * BSD API: Save Videos
 * Persists video catalog to videos.json (and PostgreSQL when available)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $videos = getBimVideos();
    echo json_encode($videos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data format. Expected an array of video items.']);
    exit;
}

// Persist to videos.json
$saved = saveBimVideos($data);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to write videos.json']);
    exit;
}

echo json_encode([
    'success' => true,
    'count'   => count($data),
    'message' => 'Videos database updated successfully'
]);
exit;
