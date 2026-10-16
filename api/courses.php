<?php
/**
 * BSD API: BIM Curricula & Courses Management
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

$currentUser = getActiveBimUser();

// 1. GET: Fetch curricula
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stationFilter = $_GET['station'] ?? null;
    $courses = getBimCourses($stationFilter);
    echo json_encode([
        'success' => true,
        'count'   => count($courses),
        'courses' => $courses
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// 2. POST: Admin only actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($currentUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin authorization required']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;
    $action = $data['action'] ?? 'create';

    // A. Create Curriculum
    if ($action === 'create') {
        $title = trim($data['title'] ?? '');
        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['error' => 'Curriculum title is required']);
            exit;
        }

        $initialVideo = $data['initial_video'] ?? null;
        $newCurriculum = createBimCurriculum([
            'title'    => $title,
            'cat'      => $data['cat'] ?? 'coordination',
            'station'  => $data['station'] ?? 'all',
            'level'    => $data['level'] ?? 'Intermediate',
            'duration' => $data['duration'] ?? '10 lessons • 15 hrs',
            'desc'     => $data['desc'] ?? 'Comprehensive BIM curriculum track.',
            'icon'     => $data['icon'] ?? '🏗️'
        ], $initialVideo);

        echo json_encode([
            'success'    => true,
            'curriculum' => $newCurriculum,
            'message'    => 'Curriculum created successfully!'
        ]);
        exit;
    }

    // B. Add Video to Curriculum
    if ($action === 'add_video') {
        $courseId = trim($data['course_id'] ?? '');
        $video = $data['video'] ?? null;

        if (empty($courseId) || empty($video)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing course_id or video details']);
            exit;
        }

        $added = addVideoToCurriculum($courseId, $video);
        if ($added) {
            echo json_encode([
                'success' => true,
                'message' => 'Video successfully attached to curriculum syllabus!'
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Curriculum not found']);
        }
        exit;
    }

    // C. Bulk update courses
    if (isset($data['courses']) && is_array($data['courses'])) {
        saveBimCourses($data['courses']);
        echo json_encode(['success' => true, 'message' => 'Curricula saved successfully']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit;
