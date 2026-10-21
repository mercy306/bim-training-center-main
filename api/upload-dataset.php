<?php
/**
 * BSD API: Direct Practice Dataset File Upload
 * Handles multipart/form-data upload of RVT, DWG, NWD, IFC, PDF datasets into datasets/
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['dataset_file']) || $_FILES['dataset_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file provided or upload error: ' . ($_FILES['dataset_file']['error'] ?? 'missing')]);
    exit;
}

$file = $_FILES['dataset_file'];
$origName = $file['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

$allowedExts = ['rvt', 'rfa', 'dwg', 'dxf', 'nwd', 'nwc', 'ifc', 'pdf', 'zip', 'rar', 'xlsx', 'txt'];
if (!in_array($ext, $allowedExts)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported dataset format. Allowed: ' . implode(', ', $allowedExts)]);
    exit;
}

$datasetsDir = defined('DATASETS_DIR') ? DATASETS_DIR : (__DIR__ . '/../datasets');
if (!is_dir($datasetsDir)) {
    mkdir($datasetsDir, 0755, true);
}

$safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
$targetFilename = $safeBase . '.' . $ext;
$targetPath = $datasetsDir . DIRECTORY_SEPARATOR . $targetFilename;

if (file_exists($targetPath)) {
    $targetFilename = $safeBase . '_' . time() . '.' . $ext;
    $targetPath = $datasetsDir . DIRECTORY_SEPARATOR . $targetFilename;
}

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save uploaded file to datasets directory']);
    exit;
}

// Compute human size
$bytes = filesize($targetPath);
if ($bytes >= 1048576) {
    $formattedSize = round($bytes / 1048576, 1) . ' MB';
} elseif ($bytes >= 1024) {
    $formattedSize = round($bytes / 1024, 1) . ' KB';
} else {
    $formattedSize = $bytes . ' B';
}

// Category / type mapping
$type = strtoupper($ext);
$category = 'other';
if (in_array($ext, ['rvt', 'rfa'])) $category = 'rvt';
elseif (in_array($ext, ['dwg', 'dxf'])) $category = 'dwg';
elseif (in_array($ext, ['nwd', 'nwc'])) $category = 'nwd';
elseif ($ext === 'pdf') $category = 'pdf';
elseif ($ext === 'ifc') $category = 'ifc';

$name = trim($_POST['name'] ?? pathinfo($origName, PATHINFO_FILENAME));
$cleanTitle = ucwords(str_replace(['_', '-'], ' ', $name));

$newEntry = [
    'id'        => 'ds_' . time() . '_' . substr(md5(uniqid()), 0, 4),
    'name'      => $cleanTitle,
    'filename'  => $targetFilename,
    'url'       => 'datasets/' . $targetFilename,
    'type'      => $type,
    'category'  => $category,
    'size'      => $formattedSize,
    'bytes'     => $bytes,
    'uploaded_at' => date('Y-m-d H:i:s')
];

$jsonFile = defined('DATASETS_JSON_FILE') ? DATASETS_JSON_FILE : (__DIR__ . '/../datasets.json');
$existing = [];
if (file_exists($jsonFile)) {
    $existing = json_decode(file_get_contents($jsonFile), true) ?: [];
}

array_unshift($existing, $newEntry);
file_put_contents($jsonFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode([
    'success' => true,
    'dataset' => $newEntry,
    'total'   => count($existing)
]);
