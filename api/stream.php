<?php
/**
 * BSD Video Streaming API
 * Implements HTTP 206 Partial Content (HTTP Range) for low-latency scrubbing and seeking.
 * Architecture matches BSD filedrop_download.php streaming engine.
 */

require_once __DIR__ . '/../config.php';

$fileParam = $_GET['file'] ?? '';
if (empty($fileParam)) {
    http_response_code(400);
    exit('Missing file parameter');
}

// Normalize and prevent directory traversal
$cleanPath = str_replace(['..', "\0"], '', $fileParam);
$cleanPath = ltrim($cleanPath, '/\\');

// Only allow files inside videos/ or root
$fullPath = realpath(BASE_PATH . DIRECTORY_SEPARATOR . $cleanPath);

if (!$fullPath || !file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    exit('Video file not found');
}

// Ensure the resolved path is within the project base directory
if (strpos($fullPath, realpath(BASE_PATH)) !== 0) {
    http_response_code(403);
    exit('Forbidden');
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'ogg'  => 'video/ogg',
    'mov'  => 'video/quicktime',
    'avi'  => 'video/x-msvideo',
    'mkv'  => 'video/x-matroska',
    'rvt'  => 'application/octet-stream',
    'dwg'  => 'application/octet-stream',
    'nwd'  => 'application/octet-stream',
    'pdf'  => 'application/pdf',
];

$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
$size = filesize($fullPath);
$start = 0;
$end = $size - 1;

// Handle HTTP Range header for streaming & seeking
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') $start = (int)$m[1];
    if ($m[2] !== '') $end   = min((int)$m[2], $size - 1);
    
    if ($start > $end || $start >= $size) {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header("Content-Range: bytes */$size");
        exit;
    }
    
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$size");
} else {
    header('HTTP/1.1 200 OK');
}

// Clear any buffered output
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . $contentType);
header('Content-Length: ' . ($end - $start + 1));
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=86400');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}

$fp = fopen($fullPath, 'rb');
if (!$fp) {
    http_response_code(500);
    exit('Cannot open file');
}

fseek($fp, $start);
$remaining = $end - $start + 1;
$chunkSize = 2 * 1024 * 1024; // 2MB buffers for smooth video playback

while ($remaining > 0 && !feof($fp) && !connection_aborted()) {
    $bytesToRead = min($chunkSize, $remaining);
    $buffer = fread($fp, $bytesToRead);
    if ($buffer === false) break;
    echo $buffer;
    flush();
    $remaining -= strlen($buffer);
}

fclose($fp);
exit;
