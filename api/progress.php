<?php
/**
 * BSD API: Employee Training Progress
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
$employeeId = $currentUser['id'];

// 1. POST: Update progress for a video
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $videoId = trim($data['video_id'] ?? '');
    $percent = intval($data['percent'] ?? 0);
    $completed = !empty($data['completed']) || $percent >= 90;

    if (empty($videoId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing video_id']);
        exit;
    }

    $saved = recordEmployeeVideoProgress($employeeId, $videoId, $percent, $completed);
    $summary = getEmployeeProgressSummary($employeeId);

    echo json_encode([
        'success'   => $saved,
        'video_id'  => $videoId,
        'percent'   => $percent,
        'completed' => $completed,
        'summary'   => $summary
    ]);
    exit;
}

// 2. GET: Retrieve progress
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'my_progress';

    if ($action === 'roster') {
        // Only Admin or Team Leader can view roster
        if ($currentUser['role'] === 'employee') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        // If Team Leader, strictly scope to their own team!
        $teamFilter = ($currentUser['role'] === 'team_leader') ? (int)$currentUser['team_id'] : null;
        $stationFilter = $_GET['station'] ?? null;
        $roster = getEmployeeRosterWithStats($teamFilter, $stationFilter);

        echo json_encode([
            'success'   => true,
            'role'      => $currentUser['role'],
            'team_id'   => $teamFilter,
            'team_name' => $currentUser['team_name'],
            'roster'    => $roster
        ]);
        exit;
    }

    // Default: My Progress
    $summary = getEmployeeProgressSummary($employeeId);
    echo json_encode([
        'success' => true,
        'user'    => $currentUser,
        'summary' => $summary
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit;
