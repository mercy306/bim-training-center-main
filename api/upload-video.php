<?php
/**
 * BSD API: Direct Video File Upload
 * Handles multipart/form-data upload of MP4 / WebM / OGG video files into videos/
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

if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No video file provided or upload error: ' . ($_FILES['video_file']['error'] ?? 'missing')]);
    exit;
}

$file = $_FILES['video_file'];
$origName = $file['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

$allowedExts = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
if (!in_array($ext, $allowedExts)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported video format. Allowed: ' . implode(', ', $allowedExts)]);
    exit;
}

// Ensure videos directory exists
if (!is_dir(VIDEOS_DIR)) {
    mkdir(VIDEOS_DIR, 0755, true);
}

// Generate clean safe filename
$safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
$targetFilename = $safeBase . '.' . $ext;
$targetPath = VIDEOS_DIR . DIRECTORY_SEPARATOR . $targetFilename;

// If duplicate filename, append timestamp
if (file_exists($targetPath)) {
    $targetFilename = $safeBase . '_' . time() . '.' . $ext;
    $targetPath = VIDEOS_DIR . DIRECTORY_SEPARATOR . $targetFilename;
}

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save uploaded file to videos directory']);
    exit;
}

$relativePath = 'videos/' . $targetFilename;

// Extract additional metadata sent from the form
$title = trim($_POST['title'] ?? pathinfo($origName, PATHINFO_FILENAME));
$cat = trim($_POST['category'] ?? 'revit');
$duration = trim($_POST['duration'] ?? '15:00');
$desc = trim($_POST['description'] ?? 'Direct BIM modeling tutorial.');
$chaptersRaw = trim($_POST['chapters'] ?? '');
$chapters = json_decode($chaptersRaw, true);
if (!is_array($chapters) || count($chapters) === 0) {
    $chapters = [['time' => '00:00', 'title' => 'Full lesson']];
} else {
    $chapters = array_values(array_filter(array_map(function ($chapter) {
        if (!is_array($chapter)) return null;
        $time = trim((string)($chapter['time'] ?? ''));
        $title = trim((string)($chapter['title'] ?? ''));
        if ($time === '' || $title === '') return null;
        return ['time' => $time, 'title' => $title];
    }, $chapters)));
    if (count($chapters) === 0) {
        $chapters = [['time' => '00:00', 'title' => 'Full lesson']];
    }
}

$station = trim($_POST['station'] ?? 'all');
$assignedEmployeesRaw = trim($_POST['assigned_employees'] ?? '');
$assignedEmployees = [];
if (!empty($assignedEmployeesRaw)) {
    $decoded = json_decode($assignedEmployeesRaw, true);
    if (is_array($decoded)) {
        $assignedEmployees = array_values(array_unique(array_map('strval', $decoded)));
    } else {
        $parts = array_filter(array_map('trim', explode(',', $assignedEmployeesRaw)));
        $assignedEmployees = array_values(array_unique($parts));
    }
}
if (!empty($assignedEmployees) && $station === 'all') {
    $station = 'custom';
}

$newEntry = [
    'id'                 => 'vid_' . time() . '_' . substr(md5(uniqid()), 0, 4),
    'cat'                => $cat,
    'station'            => $station,
    'assigned_employees' => $assignedEmployees,
    'title'              => $title,
    'desc'               => $desc,
    'ytId'               => $relativePath,
    'duration'           => $duration,
    'chapters'           => $chapters,
    'type'               => 'local'
];

// Append to videos.json automatically
$videos = getBimVideos();
array_unshift($videos, $newEntry);
saveBimVideos($videos);

// Handle Curriculum Attachment
$curriculumMode = trim($_POST['curriculum_mode'] ?? 'none');
$attachedCurriculum = null;

if ($curriculumMode === 'existing' && !empty($_POST['curriculum_id'])) {
    $curriculumId = trim($_POST['curriculum_id']);
    addVideoToCurriculum($curriculumId, $newEntry);
    $attachedCurriculum = $curriculumId;
} elseif ($curriculumMode === 'new' && !empty($_POST['new_curriculum_title'])) {
    $newCurriculum = createBimCurriculum([
        'title'              => trim($_POST['new_curriculum_title']),
        'cat'                => $cat,
        'station'            => $station,
        'assigned_employees' => $assignedEmployees,
        'level'              => trim($_POST['new_curriculum_level'] ?? 'Intermediate'),
        'duration'           => '1 lesson • ' . $duration,
        'desc'               => trim($_POST['new_curriculum_desc'] ?? $desc),
        'icon'               => trim($_POST['new_curriculum_icon'] ?? '')
    ], $newEntry);
    $attachedCurriculum = $newCurriculum['id'];
}

echo json_encode([
    'success'       => true,
    'video'         => $newEntry,
    'curriculum_id' => $attachedCurriculum,
    'message'       => 'Video uploaded and published successfully!'
]);
exit;
