<?php
/**
 * BSD API: Training Analytics Dashboard
 * Returns KPI metrics, completion percentages, and station breakdowns
 * Strictly scopes team metrics for Team Leaders
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

$currentUser = getActiveBimUser();

// Employees cannot view analytics dashboard
if ($currentUser['role'] === 'employee') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Scoped to team for Team Leader; global for Admin
$teamFilter = ($currentUser['role'] === 'team_leader') ? (int)$currentUser['team_id'] : null;

$analytics = getTrainingAnalytics($teamFilter);
$roster = getEmployeeRosterWithStats($teamFilter);

echo json_encode([
    'success'      => true,
    'role'         => $currentUser['role'],
    'team_id'      => $teamFilter,
    'team_name'    => $currentUser['team_name'],
    'analytics'    => $analytics,
    'roster_count' => count($roster)
]);
exit;
