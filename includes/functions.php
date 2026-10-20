<?php
/**
 * BSD BIM Training Center - Helper Functions & Analytics Engine
 * Supports Multi-Role: Admin (Global Analytics & Uploads), Team Leader (Scoped Team View), Employee (Learning & Progress Tracking)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

/**
 * Get all videos from videos.json, optionally filtered by user station or specific employee assignment
 */
function getBimVideos(?string $station = null, $employeeId = null): array {
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

    if ($employeeId !== null) {
        $empIdStr = (string)$employeeId;
        return array_values(array_filter($data, function($v) use ($station, $empIdStr) {
            // Check individual employee assignment
            if (!empty($v['assigned_employees']) && is_array($v['assigned_employees'])) {
                return in_array($empIdStr, array_map('strval', $v['assigned_employees']), true);
            }
            if (($v['station'] ?? '') === 'custom') {
                return false;
            }
            if (!$station || $station === 'all') return true;
            $target = $v['station'] ?? 'all';
            if ($target === 'all') return true;
            $parts = array_map('trim', explode(',', strtolower($target)));
            return in_array(strtolower($station), $parts, true);
        }));
    }

    if ($station && $station !== 'all') {
        return array_values(array_filter($data, function($v) use ($station) {
            $target = $v['station'] ?? 'all';
            if ($target === 'all') return true;
            $parts = array_map('trim', explode(',', strtolower($target)));
            return in_array(strtolower($station), $parts, true);
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
 * Get all training curricula from courses.json, optionally filtered by user station or employee assignment
 */
function getBimCourses(?string $station = null, $employeeId = null): array {
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

    if ($employeeId !== null) {
        $empIdStr = (string)$employeeId;
        return array_values(array_filter($data, function($c) use ($station, $empIdStr) {
            if (!empty($c['assigned_employees']) && is_array($c['assigned_employees'])) {
                return in_array($empIdStr, array_map('strval', $c['assigned_employees']), true);
            }
            if (($c['station'] ?? '') === 'custom') {
                return false;
            }
            if (!$station || $station === 'all') return true;
            $target = $c['station'] ?? 'all';
            if ($target === 'all') return true;
            $parts = array_map('trim', explode(',', strtolower($target)));
            return in_array(strtolower($station), $parts, true);
        }));
    }

    if ($station && $station !== 'all') {
        return array_values(array_filter($data, function($c) use ($station) {
            $target = $c['station'] ?? 'all';
            if ($target === 'all') return true;
            $parts = array_map('trim', explode(',', strtolower($target)));
            return in_array(strtolower($station), $parts, true);
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

    $assignedEmployees = $data['assigned_employees'] ?? ($initialVideo['assigned_employees'] ?? []);

    $newCourse = [
        'id'                 => $newId,
        'cat'                => $data['cat'] ?? 'revit',
        'station'            => $data['station'] ?? 'all',
        'assigned_employees' => $assignedEmployees,
        'title'              => $data['title'] ?? 'New BIM Curriculum',
        'icon'               => $data['icon'] ?? '',
        'level'              => $data['level'] ?? 'Intermediate',
        'duration'           => $data['duration'] ?? (count($syllabus) . ' lessons • 10 hrs'),
        'desc'               => $data['desc'] ?? 'Comprehensive BIM curriculum track.',
        'syllabus'           => $syllabus
    ];

    array_unshift($courses, $newCourse);
    saveBimCourses($courses);
    return $newCourse;
}

/**
 * Get real employee roster from PostgreSQL database
 */
function getRealEmployeesRoster(): array {
    global $pdo;
    $employees = [];

    if ($pdo) {
        try {
            $stmt = $pdo->query("
                SELECT 
                    e.id, 
                    e.full_name_english, 
                    e.full_name_hebrew, 
                    e.department, 
                    e.position, 
                    e.is_trainee, 
                    e.team as emp_team,
                    tm.team_id,
                    t.team_name,
                    tm.role_in_team
                FROM employees e
                LEFT JOIN team_members tm ON e.id = tm.employee_id AND (tm.status != 'inactive' OR tm.status IS NULL)
                LEFT JOIN teams t ON tm.team_id = t.id AND t.status = 'active'
                WHERE e.employment_status = 'active' OR e.employment_status IS NULL
                ORDER BY e.id ASC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                $id = (string)$r['id'];

                if (!isset($employees[$id])) {
                    $name = !empty($r['full_name_english']) ? trim($r['full_name_english']) : (!empty($r['full_name_hebrew']) ? trim($r['full_name_hebrew']) : ('Employee #' . $id));

                    $isTrainee = !empty($r['is_trainee']);
                    $dept = strtolower((string)($r['department'] ?? ''));
                    $pos = strtolower((string)($r['position'] ?? ''));

                    if ($isTrainee) {
                        $station = 'trainee';
                    } elseif (strpos($dept, 'architect') !== false || strpos($pos, 'architect') !== false) {
                        $station = 'architect';
                    } elseif (strpos($dept, 'civil') !== false || strpos($dept, 'sanitory') !== false || strpos($pos, 'engineer') !== false) {
                        $station = 'engineer';
                    } elseif (strpos($dept, 'model') !== false || strpos($pos, 'model') !== false || strpos($pos, 'draft') !== false) {
                        $station = 'modeler';
                    } elseif (strpos($pos, 'manager') !== false || strpos($pos, 'supervisor') !== false) {
                        $station = 'manager';
                    } else {
                        $station = 'all';
                    }

                    $teamName = !empty($r['team_name']) ? $r['team_name'] : (!empty($r['emp_team']) ? $r['emp_team'] : 'General Team');
                    $tId = !empty($r['team_id']) ? (int)$r['team_id'] : null;

                    $employees[$id] = [
                        'id'          => (int)$id,
                        'name'        => $name,
                        'station'     => $station,
                        'team_id'     => $tId,
                        'team_ids'    => $tId ? [$tId] : [],
                        'team_name'   => $teamName,
                        'videos'      => [],
                        'last_active' => 'Not Started'
                    ];
                } else {
                    if (!empty($r['team_id'])) {
                        $tId = (int)$r['team_id'];
                        if (!in_array($tId, $employees[$id]['team_ids'], true)) {
                            $employees[$id]['team_ids'][] = $tId;
                        }
                        if ($employees[$id]['team_id'] === null) {
                            $employees[$id]['team_id'] = $tId;
                            if (!empty($r['team_name'])) {
                                $employees[$id]['team_name'] = $r['team_name'];
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("Failed to load real employees: " . $e->getMessage());
        }
    }

    return $employees;
}

/**
 * Check if an employee is tagged as a Team Leader in PostgreSQL
 * Either listed as teams.team_leader_id OR team_members.role_in_team = 'leader'
 */
function isEmployeeTeamLeader(int $employeeId): bool {
    global $pdo;
    if (!$pdo || $employeeId <= 0) return false;
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM teams t
            WHERE (t.team_leader_id = ? OR t.id IN (
                SELECT team_id FROM team_members WHERE employee_id = ? AND (role_in_team = 'leader' OR role_in_team = 'team_leader')
            )) AND t.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$employeeId, $employeeId]);
        return (bool)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Get all active teams led by an employee
 */
function getEmployeeLedTeams(int $employeeId): array {
    global $pdo;
    if (!$pdo || $employeeId <= 0) return [];
    try {
        $stmt = $pdo->prepare("
            SELECT t.id, t.team_name, t.department
            FROM teams t
            WHERE (t.team_leader_id = ? OR t.id IN (
                SELECT team_id FROM team_members WHERE employee_id = ? AND (role_in_team = 'leader' OR role_in_team = 'team_leader')
            )) AND t.status = 'active'
            ORDER BY t.team_name ASC
        ");
        $stmt->execute([$employeeId, $employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * Get all member employee IDs belonging to the teams led by this team leader
 * Excludes viewing everyone across the company
 */
function getTeamMemberIdsForLeader(int $leaderEmployeeId, ?int $filterTeamId = null): array {
    global $pdo;
    if (!$pdo || $leaderEmployeeId <= 0) return [];

    try {
        if ($filterTeamId !== null) {
            $stmt = $pdo->prepare("
                SELECT t.id FROM teams t
                WHERE t.id = ? AND (t.team_leader_id = ? OR t.id IN (
                    SELECT team_id FROM team_members WHERE employee_id = ? AND (role_in_team = 'leader' OR role_in_team = 'team_leader')
                )) AND t.status = 'active'
            ");
            $stmt->execute([$filterTeamId, $leaderEmployeeId, $leaderEmployeeId]);
            $teamIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $stmt = $pdo->prepare("
                SELECT t.id FROM teams t
                WHERE (t.team_leader_id = ? OR t.id IN (
                    SELECT team_id FROM team_members WHERE employee_id = ? AND (role_in_team = 'leader' OR role_in_team = 'team_leader')
                )) AND t.status = 'active'
            ");
            $stmt->execute([$leaderEmployeeId, $leaderEmployeeId]);
            $teamIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        if (empty($teamIds)) {
            return [];
        }

        $inClause = implode(',', array_map('intval', $teamIds));
        $mStmt = $pdo->query("
            SELECT DISTINCT tm.employee_id
            FROM team_members tm
            JOIN employees e ON tm.employee_id = e.id
            WHERE tm.team_id IN ($inClause)
              AND (e.employment_status = 'active' OR e.employment_status IS NULL)
              AND (tm.status != 'inactive' OR tm.status IS NULL)
        ");
        $memberIds = $mStmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $memberIds);
    } catch (\Throwable $e) {
        error_log("Failed to get team members for leader: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all active team leaders in BSD with their led teams
 */
function getAllActiveTeamLeaders(): array {
    global $pdo;
    if (!$pdo) return [];
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT e.id, e.full_name_english, e.full_name_hebrew, e.position, t.id as team_id, t.team_name
            FROM employees e
            JOIN teams t ON (t.team_leader_id = e.id OR t.id IN (
                SELECT team_id FROM team_members WHERE employee_id = e.id AND (role_in_team = 'leader' OR role_in_team = 'team_leader')
            ))
            WHERE t.status = 'active' AND (e.employment_status = 'active' OR e.employment_status IS NULL)
            ORDER BY e.full_name_english ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * Load progress store (merges real active employees with saved progress)
 */
function getProgressStore(): array {
    $realEmployees = getRealEmployeesRoster();

    $savedProgress = [];
    if (file_exists(PROGRESS_JSON_FILE)) {
        $raw = file_get_contents(PROGRESS_JSON_FILE);
        $savedProgress = json_decode($raw, true) ?: [];
    }

    // Merge saved watched progress into real employees
    if (!empty($realEmployees)) {
        foreach ($realEmployees as $empId => &$empData) {
            if (isset($savedProgress[$empId])) {
                $empData['videos'] = $savedProgress[$empId]['videos'] ?? [];
                $empData['last_active'] = $savedProgress[$empId]['last_active'] ?? 'Not Started';
            }
        }
        return $realEmployees;
    }

    return $savedProgress;
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
function recordEmployeeVideoProgress(int|string $employeeId, string $videoId, int $percent, bool $completed, float $position = 0.0): bool {
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

    $existing = $store[$empKey]['videos'][$videoId] ?? [];
    $store[$empKey]['videos'][$videoId] = [
        'percent' => max($percent, $existing['percent'] ?? 0),
        'completed' => $completed || ($existing['completed'] ?? false),
        'position' => $position > 0 ? $position : ($existing['position'] ?? 0),
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
    $assignedVideos = getBimVideos($station, $employeeId);
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
 * If $leaderEmployeeId is provided (or user is Team Leader), filters strictly to members of their led team(s)!
 * If $filterTeamId is supplied, filters strictly to that team!
 */
function getEmployeeRosterWithStats(?int $filterTeamId = null, ?string $filterStation = null, ?int $leaderEmployeeId = null): array {
    $store = getProgressStore();
    $allVideos = getBimVideos();
    $totalVideosCount = max(1, count($allVideos));
    $roster = [];

    // If a team leader is viewing, get the allowed member IDs belonging to their led team(s)
    $allowedMemberIds = null;
    if ($leaderEmployeeId !== null && $leaderEmployeeId > 0) {
        $allowedMemberIds = getTeamMemberIdsForLeader($leaderEmployeeId, $filterTeamId);
    }

    foreach ($store as $empId => $data) {
        $empNumId = (int)$empId;

        // Team Leader constraint: strictly enforce that only team members of their team are visible!
        if ($allowedMemberIds !== null) {
            if (!in_array($empNumId, $allowedMemberIds, true)) {
                continue;
            }
        } elseif ($filterTeamId !== null) {
            $empTeamIds = $data['team_ids'] ?? (isset($data['team_id']) ? [(int)$data['team_id']] : []);
            if (!in_array($filterTeamId, $empTeamIds, true)) {
                continue;
            }
        }

        // Station filter
        if ($filterStation !== null && $filterStation !== 'all' && ($data['station'] ?? '') !== $filterStation) {
            continue;
        }

        $assignedVideos = getBimVideos($data['station'] ?? 'all', $empId);
        $totalAssigned = count($assignedVideos);
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

        $overallPct = $totalAssigned > 0 ? round($totalPct / $totalAssigned) : 0;

        $roster[] = [
            'id'             => $empId,
            'name'           => $data['name'] ?? ('Employee #' . $empId),
            'station'        => $data['station'] ?? 'trainee',
            'team_id'        => $data['team_id'] ?? null,
            'team_ids'       => $data['team_ids'] ?? [],
            'team_name'      => $data['team_name'] ?? 'General Team',
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
 * Strictly scoped to team members when viewing as Team Leader
 */
function getTrainingAnalytics(?int $filterTeamId = null, ?int $leaderEmployeeId = null): array {
    $roster = getEmployeeRosterWithStats($filterTeamId, null, $leaderEmployeeId);
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
