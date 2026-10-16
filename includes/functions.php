<?php
/**
 * BSD BIM Training Center - Helper Functions & Analytics Engine
 * Supports Multi-Role: Admin (Global Analytics & Uploads), Team Leader (Scoped Team View), Employee (Learning & Progress Tracking)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

/**
 * Get all videos from videos.json, optionally filtered by user station
 */
function getBimVideos(?string $station = null): array {
    if (!file_exists(VIDEOS_JSON_FILE)) {
        return [];
    }
    $content = file_get_contents(VIDEOS_JSON_FILE);
    $data = json_decode($content, true);
    if (!is_array($data)) return [];

    // Ensure all items have a default station if missing
    foreach ($data as &$item) {
        if (empty($item['station'])) {
            $item['station'] = 'all';
        }
    }

    if ($station && $station !== 'all') {
        return array_values(array_filter($data, function($v) use ($station) {
            $target = $v['station'] ?? 'all';
            return $target === 'all' || $target === $station;
        }));
    }

    return $data;
}

/**
 * Save videos array to videos.json
 */
function saveBimVideos(array $videos): bool {
    $encoded = json_encode($videos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents(VIDEOS_JSON_FILE, $encoded) !== false;
}

/**
 * Get all training curricula from courses.json, optionally filtered by user station
 */
function getBimCourses(?string $station = null): array {
    if (!file_exists(COURSES_JSON_FILE)) {
        return [];
    }
    $content = file_get_contents(COURSES_JSON_FILE);
    $data = json_decode($content, true);
    if (!is_array($data)) return [];

    foreach ($data as &$c) {
        if (empty($c['station'])) $c['station'] = 'all';
        if (!isset($c['syllabus'])) $c['syllabus'] = [];
    }

    if ($station && $station !== 'all') {
        return array_values(array_filter($data, function($c) use ($station) {
            $target = $c['station'] ?? 'all';
            return $target === 'all' || $target === $station;
        }));
    }

    return $data;
}

/**
 * Save courses/curricula array to courses.json
 */
function saveBimCourses(array $courses): bool {
    $encoded = json_encode($courses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents(COURSES_JSON_FILE, $encoded) !== false;
}

/**
 * Add a video lesson to an existing curriculum syllabus
 */
function addVideoToCurriculum(string $courseId, array $videoInfo): bool {
    $courses = getBimCourses();
    $found = false;
    foreach ($courses as &$c) {
        if ($c['id'] === $courseId) {
            if (!isset($c['syllabus']) || !is_array($c['syllabus'])) {
                $c['syllabus'] = [];
            }
            $lessonNumber = count($c['syllabus']) + 1;
            $c['syllabus'][] = [
                'id'       => $videoInfo['id'] ?? ('les_' . time()),
                'title'    => $lessonNumber . '. ' . ($videoInfo['title'] ?? 'Lesson'),
                'duration' => $videoInfo['duration'] ?? '20:00',
                'type'     => $videoInfo['type'] ?? 'local',
                'ytId'     => $videoInfo['ytId'] ?? ''
            ];
            $found = true;
            break;
        }
    }
    if ($found) {
        return saveBimCourses($courses);
    }
    return false;
}

/**
 * Create a new curriculum and optionally attach an initial video lesson
 */
function createBimCurriculum(array $data, ?array $initialVideo = null): array {
    $courses = getBimCourses();
    $newId = 'curriculum_' . time() . '_' . substr(md5(uniqid()), 0, 4);
    
    $syllabus = [];
    if ($initialVideo) {
        $syllabus[] = [
            'id'       => $initialVideo['id'] ?? ('les_' . time()),
            'title'    => '1. ' . ($initialVideo['title'] ?? 'Module Overview'),
            'duration' => $initialVideo['duration'] ?? '20:00',
            'type'     => $initialVideo['type'] ?? 'local',
            'ytId'     => $initialVideo['ytId'] ?? ''
        ];
    }

    $newCourse = [
        'id'       => $newId,
        'cat'      => $data['cat'] ?? 'revit',
        'station'  => $data['station'] ?? 'all',
        'title'    => $data['title'] ?? 'New BIM Curriculum',
        'icon'     => $data['icon'] ?? '🏗️',
        'level'    => $data['level'] ?? 'Intermediate',
        'duration' => $data['duration'] ?? (count($syllabus) . ' lessons • 10 hrs'),
        'desc'     => $data['desc'] ?? 'Comprehensive BIM curriculum track.',
        'syllabus' => $syllabus
    ];

    array_unshift($courses, $newCourse);
    saveBimCourses($courses);
    return $newCourse;
}

/**
 * Load progress store
 */
function getProgressStore(): array {
    if (!file_exists(PROGRESS_JSON_FILE)) {
        // Sample seed progress for demonstration
        $seed = [
            '301' => [ // Yoni Levi (Trainee)
                'name' => 'Yoni Levi',
                'station' => 'trainee',
                'team_id' => 10,
                'team_name' => 'Structural Modeling Team A',
                'videos' => [
                    'vid_1791639419351' => ['completed' => true, 'percent' => 100, 'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day'))],
                    'vid_rebar_01'      => ['completed' => true, 'percent' => 100, 'updated_at' => date('Y-m-d H:i:s', strtotime('-3 hours'))]
                ],
                'last_active' => date('Y-m-d H:i:s', strtotime('-3 hours'))
            ],
            '302' => [ // Sarah Alon (Engineer)
                'name' => 'Sarah Alon',
                'station' => 'engineer',
                'team_id' => 10,
                'team_name' => 'Structural Modeling Team A',
                'videos' => [
                    'vid_rebar_01' => ['completed' => true, 'percent' => 100, 'updated_at' => date('Y-m-d H:i:s', strtotime('-2 days'))]
                ],
                'last_active' => date('Y-m-d H:i:s', strtotime('-2 days'))
            ],
            '303' => [ // Ronen Katz (Trainee, Team A)
                'name' => 'Ronen Katz',
                'station' => 'trainee',
                'team_id' => 10,
                'team_name' => 'Structural Modeling Team A',
                'videos' => [
                    'vid_1791639419351' => ['completed' => false, 'percent' => 45, 'updated_at' => date('Y-m-d H:i:s', strtotime('-4 hours'))]
                ],
                'last_active' => date('Y-m-d H:i:s', strtotime('-4 hours'))
            ],
            '401' => [ // Michael Ben (Architect, Team B)
                'name' => 'Michael Ben',
                'station' => 'architect',
                'team_id' => 20,
                'team_name' => 'Architectural BIM Team B',
                'videos' => [
                    'vid_1791639419351' => ['completed' => true, 'percent' => 100, 'updated_at' => date('Y-m-d H:i:s', strtotime('-5 days'))]
                ],
                'last_active' => date('Y-m-d H:i:s', strtotime('-5 days'))
            ],
            '402' => [ // Noa Stern (Modeler, Team B)
                'name' => 'Noa Stern',
                'station' => 'modeler',
                'team_id' => 20,
                'team_name' => 'Architectural BIM Team B',
                'videos' => [],
                'last_active' => date('Y-m-d H:i:s', strtotime('-6 days'))
            ]
        ];
        file_put_contents(PROGRESS_JSON_FILE, json_encode($seed, JSON_PRETTY_PRINT));
        return $seed;
    }

    $raw = file_get_contents(PROGRESS_JSON_FILE);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Save progress store
 */
function saveProgressStore(array $store): bool {
    return file_put_contents(PROGRESS_JSON_FILE, json_encode($store, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Record video progress for an employee
 */
function recordEmployeeVideoProgress(int|string $employeeId, string $videoId, int $percent, bool $completed): bool {
    global $pdo;
    $store = getProgressStore();
    $empKey = (string)$employeeId;
    $activeUser = getActiveBimUser();

    if (!isset($store[$empKey])) {
        $store[$empKey] = [
            'name' => $activeUser['name'] ?? ('Employee #' . $employeeId),
            'station' => $activeUser['station'] ?? 'trainee',
            'team_id' => $activeUser['team_id'] ?? 10,
            'team_name' => $activeUser['team_name'] ?? 'Structural Modeling Team A',
            'videos' => []
        ];
    }

    $store[$empKey]['videos'][$videoId] = [
        'percent' => max($percent, $store[$empKey]['videos'][$videoId]['percent'] ?? 0),
        'completed' => $completed || ($store[$empKey]['videos'][$videoId]['completed'] ?? false),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    $store[$empKey]['last_active'] = date('Y-m-d H:i:s');

    // Also persist to PostgreSQL if available
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO bim_employee_progress (employee_id, video_id, percent, completed, updated_at)
                VALUES (?, ?, ?, ?, NOW())
                ON CONFLICT (employee_id, video_id)
                DO UPDATE SET percent = EXCLUDED.percent, completed = EXCLUDED.completed, updated_at = NOW()
            ");
            $stmt->execute([$employeeId, $videoId, $percent, $completed ? 'true' : 'false']);
        } catch (\Throwable $e) {}
    }

    return saveProgressStore($store);
}

/**
 * Get individual progress summary for an employee
 */
function getEmployeeProgressSummary(int|string $employeeId): array {
    $store = getProgressStore();
    $empKey = (string)$employeeId;
    $userRecord = $store[$empKey] ?? null;

    $station = $userRecord['station'] ?? 'all';
    $assignedVideos = getBimVideos($station);
    $totalAssigned = count($assignedVideos);

    $completedCount = 0;
    $totalPercent = 0;
    $watchedVideos = $userRecord['videos'] ?? [];

    foreach ($assignedVideos as $v) {
        $vId = $v['id'];
        if (!empty($watchedVideos[$vId]['completed'])) {
            $completedCount++;
            $totalPercent += 100;
        } else {
            $totalPercent += ($watchedVideos[$vId]['percent'] ?? 0);
        }
    }

    $overallPct = $totalAssigned > 0 ? round($totalPercent / $totalAssigned) : 0;

    return [
        'employee_id'     => $employeeId,
        'name'            => $userRecord['name'] ?? 'Employee',
        'station'         => $station,
        'team_id'         => $userRecord['team_id'] ?? 10,
        'team_name'       => $userRecord['team_name'] ?? 'Team A',
        'total_assigned'  => $totalAssigned,
        'completed_count' => $completedCount,
        'overall_percent' => $overallPct,
        'watched_videos'  => $watchedVideos,
        'last_active'     => $userRecord['last_active'] ?? 'Never'
    ];
}

/**
 * Get employee roster with their progress stats
 * If $filterTeamId is supplied (Team Leader), filters strictly to that team!
 */
function getEmployeeRosterWithStats(?int $filterTeamId = null, ?string $filterStation = null): array {
    $store = getProgressStore();
    $allVideos = getBimVideos();
    $totalVideosCount = max(1, count($allVideos));
    $roster = [];

    foreach ($store as $empId => $data) {
        // Team Leader constraint: strictly enforce team filter
        if ($filterTeamId !== null && (int)($data['team_id'] ?? 0) !== $filterTeamId) {
            continue;
        }

        // Station filter
        if ($filterStation !== null && $filterStation !== 'all' && ($data['station'] ?? '') !== $filterStation) {
            continue;
        }

        $assignedVideos = getBimVideos($data['station'] ?? 'all');
        $totalAssigned = max(1, count($assignedVideos));
        $completed = 0;
        $totalPct = 0;

        foreach ($assignedVideos as $v) {
            $vId = $v['id'];
            if (!empty($data['videos'][$vId]['completed'])) {
                $completed++;
                $totalPct += 100;
            } else {
                $totalPct += ($data['videos'][$vId]['percent'] ?? 0);
            }
        }

        $overallPct = round($totalPct / $totalAssigned);

        $roster[] = [
            'id'             => $empId,
            'name'           => $data['name'] ?? ('Employee #' . $empId),
            'station'        => $data['station'] ?? 'trainee',
            'team_id'        => $data['team_id'] ?? 10,
            'team_name'      => $data['team_name'] ?? 'Team A',
            'completed'      => $completed,
            'total_assigned' => $totalAssigned,
            'percent'        => $overallPct,
            'last_active'    => $data['last_active'] ?? 'Recent',
            'status'         => $overallPct >= 100 ? 'Completed' : ($overallPct > 0 ? 'In Progress' : 'Not Started')
        ];
    }

    return $roster;
}

/**
 * Get high-level analytics for Admin or Team Leader
 */
function getTrainingAnalytics(?int $filterTeamId = null): array {
    $roster = getEmployeeRosterWithStats($filterTeamId);
    $totalEmployees = count($roster);
    
    if ($totalEmployees === 0) {
        return [
            'total_employees' => 0,
            'avg_completion'  => 0,
            'fully_completed' => 0,
            'in_progress'     => 0,
            'not_started'     => 0,
            'station_stats'   => []
        ];
    }

    $sumPercent = 0;
    $completedCount = 0;
    $inProgressCount = 0;
    $notStartedCount = 0;
    $stationMap = [];

    foreach ($roster as $emp) {
        $sumPercent += $emp['percent'];
        if ($emp['percent'] >= 100) $completedCount++;
        elseif ($emp['percent'] > 0) $inProgressCount++;
        else $notStartedCount++;

        $st = $emp['station'];
        if (!isset($stationMap[$st])) {
            $stationMap[$st] = ['count' => 0, 'sum_pct' => 0];
        }
        $stationMap[$st]['count']++;
        $stationMap[$st]['sum_pct'] += $emp['percent'];
    }

    $stationStats = [];
    foreach ($stationMap as $stKey => $data) {
        $stationStats[] = [
            'station'   => $stKey,
            'title'     => BIM_STATIONS[$stKey] ?? ucfirst($stKey),
            'headcount' => $data['count'],
            'avg_pct'   => round($data['sum_pct'] / max(1, $data['count']))
        ];
    }

    return [
        'total_employees' => $totalEmployees,
        'avg_completion'  => round($sumPercent / $totalEmployees),
        'fully_completed' => $completedCount,
        'in_progress'     => $inProgressCount,
        'not_started'     => $notStartedCount,
        'station_stats'   => $stationStats
    ];
}
