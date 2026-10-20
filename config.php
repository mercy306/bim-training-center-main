<?php
/**
 * BSD BIM Training Center - Configuration
 * Integrated with BSD Design & Management System Architecture
 */

defined('BIM_APP') or define('BIM_APP', true);

// ============================================================================
// APPLICATION SETTINGS
// ============================================================================
define('APP_NAME', 'BSD BIM Training Center');
define('APP_VERSION', '2.1.0');
define('APP_ENV', 'development');
define('APP_TIMEZONE', 'Asia/Jerusalem');
date_default_timezone_set(APP_TIMEZONE);

// Paths
define('BASE_PATH', __DIR__);
define('VIDEOS_DIR', BASE_PATH . DIRECTORY_SEPARATOR . 'videos');
define('VIDEOS_JSON_FILE', BASE_PATH . DIRECTORY_SEPARATOR . 'videos.json');
define('COURSES_JSON_FILE', BASE_PATH . DIRECTORY_SEPARATOR . 'courses.json');
define('PROGRESS_JSON_FILE', BASE_PATH . DIRECTORY_SEPARATOR . 'progress.json');

// Admin Access Settings
define('DEFAULT_ADMIN_PASSWORD', 'admin123');

// ============================================================================
// EMPLOYEE STATIONS / DISCIPLINES
// ============================================================================
define('BIM_STATIONS', [
    'all'        => 'All Employees',
    'trainee'    => 'Trainees',
    'engineer'   => 'Structural & MEP Engineers',
    'architect'  => 'Architects & Designers'
]);

// ============================================================================
// DATABASE CONFIGURATION (Matches BSD Employee System: bsd2 / PostgreSQL)
// ============================================================================
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'bsd2');
define('DB_USER', 'postgres');
define('DB_PASS', 'BSDeng');
define('DB_CHARSET', 'utf8');

// ============================================================================
// SESSION MANAGEMENT (Compatible with BSD Parent Session)
// ============================================================================
if (session_status() === PHP_SESSION_NONE) {
    if (!defined('SESSION_NAME')) {
        define('SESSION_NAME', 'BSD_SESSION');
    }
    session_name(SESSION_NAME);
    session_start();
}

/**
 * Get active user identity and role
 * Returns array with: id, name, role ('admin'|'team_leader'|'employee'), station, team_id, team_name
 */
function getActiveBimUser(): array {
    // 1. BSD Integrated Session: Check logged-in BSD user FIRST
    if (!empty($_SESSION['user_id'])) {
        // Clear any previous demo overrides
        unset($_SESSION['demo_role'], $_SESSION['demo_leader_id']);

        require_once __DIR__ . '/db.php';
        require_once __DIR__ . '/includes/functions.php';
        global $pdo;

        $parentRole = strtolower((string)($_SESSION['role'] ?? 'employee'));
        $name = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'BSD Employee');
        $userId = (int)$_SESSION['user_id'];
        $empId = $userId;
        $station = 'all';
        $teamId = isset($_SESSION['team_id']) ? (int)$_SESSION['team_id'] : null;
        $teamName = $_SESSION['team_name'] ?? 'General Team';

        // Resolve real employee record linked via employees.user_id = users.id
        if ($pdo) {
            try {
                $eStmt = $pdo->prepare("
                    SELECT id, full_name_english, full_name_hebrew, department, position, is_trainee, team
                    FROM employees
                    WHERE user_id = ? OR id = ?
                    LIMIT 1
                ");
                $eStmt->execute([$userId, $userId]);
                $empRow = $eStmt->fetch(PDO::FETCH_ASSOC);

                if ($empRow) {
                    $empId = (int)$empRow['id'];
                    if (!empty($empRow['full_name_english'])) {
                        $name = trim($empRow['full_name_english']);
                    } elseif (!empty($empRow['full_name_hebrew'])) {
                        $name = trim($empRow['full_name_hebrew']);
                    }

                    $dept = strtolower((string)($empRow['department'] ?? ''));
                    $pos = strtolower((string)($empRow['position'] ?? ''));
                    if (!empty($empRow['is_trainee'])) {
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

                    if (!empty($empRow['team'])) {
                        $teamName = $empRow['team'];
                    }
                }
            } catch (\Throwable $e) {
                error_log("Failed to resolve employee for user $userId: " . $e->getMessage());
            }
        }

        // Map parent roles: Admin
        if (in_array($parentRole, ['admin', 'system_admin', 'office_admin'])) {
            return [
                'id' => $empId,
                'name' => $name,
                'role' => 'admin',
                'station' => 'all',
                'team_id' => null,
                'team_name' => 'All Departments',
                'is_demo' => false
            ];
        }

        // Strictly verify if employee is tagged as a Team Leader in PostgreSQL
        if (isEmployeeTeamLeader($empId)) {
            $ledTeams = getEmployeeLedTeams($empId);
            $primaryTeam = !empty($ledTeams) ? $ledTeams[0] : null;
            return [
                'id' => $empId,
                'name' => $name,
                'role' => 'team_leader',
                'station' => $station ?: 'engineer',
                'team_id' => $primaryTeam ? (int)$primaryTeam['id'] : $teamId,
                'team_name' => $primaryTeam ? $primaryTeam['team_name'] : $teamName,
                'is_demo' => false
            ];
        }

        // Standard Employee Learner
        return [
            'id' => $empId,
            'name' => $name,
            'role' => 'employee',
            'station' => $station ?: 'trainee',
            'team_id' => $teamId,
            'team_name' => $teamName,
            'is_demo' => false
        ];
    }

    // 2. Standalone / Demo simulation ONLY when no BSD user is logged in
    if (isset($_GET['demo_role'])) {
        $_SESSION['demo_role'] = $_GET['demo_role'];
    }
    
    $demoRole = $_SESSION['demo_role'] ?? null;
    if ($demoRole === 'admin') {
        return [
            'id' => 1,
            'name' => 'BSD Executive Admin',
            'role' => 'admin',
            'station' => 'all',
            'team_id' => null,
            'team_name' => 'All Departments',
            'is_demo' => true
        ];
    } elseif ($demoRole === 'team_leader') {
        // Support inspecting different real BSD team leaders via ?leader_id=
        $reqLeaderId = isset($_GET['leader_id']) ? (int)$_GET['leader_id'] : (isset($_SESSION['demo_leader_id']) ? (int)$_SESSION['demo_leader_id'] : 27);
        if (isset($_GET['leader_id'])) {
            $_SESSION['demo_leader_id'] = $reqLeaderId;
        }

        // Real team leader presets
        if ($reqLeaderId === 8) {
            return [
                'id' => 8,
                'name' => 'Bezawit Birhanu (Team Leader)',
                'role' => 'team_leader',
                'station' => 'architect',
                'team_id' => 12,
                'team_name' => 'ARCH',
                'is_demo' => true
            ];
        } elseif ($reqLeaderId === 43) {
            return [
                'id' => 43,
                'name' => 'Natan Derara (Team Leader)',
                'role' => 'team_leader',
                'station' => 'engineer',
                'team_id' => 13,
                'team_name' => 'IT',
                'is_demo' => true
            ];
        } elseif ($reqLeaderId === 28) {
            return [
                'id' => 28,
                'name' => 'Henok Solomon (Team Leader)',
                'role' => 'team_leader',
                'station' => 'engineer',
                'team_id' => 16,
                'team_name' => 'BSD',
                'is_demo' => true
            ];
        } else {
            // Default real team leader: Tekle Ketema (Leads Team A.D_DOR)
            return [
                'id' => 27,
                'name' => 'Tekle Ketema (Team Leader)',
                'role' => 'team_leader',
                'station' => 'engineer',
                'team_id' => 4,
                'team_name' => 'A.D_DOR',
                'is_demo' => true
            ];
        }
    } elseif ($demoRole === 'employee_trainee') {
        return [
            'id' => 79,
            'name' => 'Kalbekal Haileleul (Trainee)',
            'role' => 'employee',
            'station' => 'trainee',
            'team_id' => null,
            'team_name' => 'General Team',
            'is_demo' => true
        ];
    } elseif ($demoRole === 'employee_engineer') {
        return [
            'id' => 29,
            'name' => 'Wondifraw Shituneh (Civil Engineer)',
            'role' => 'employee',
            'station' => 'engineer',
            'team_id' => 4,
            'team_name' => 'A.D_DOR',
            'is_demo' => true
        ];
    }

    // 3. Standalone mode fallback
    if (!empty($_SESSION['bim_admin_auth'])) {
        return [
            'id' => 1,
            'name' => 'BSD Administrator',
            'role' => 'admin',
            'station' => 'all',
            'team_id' => null,
            'team_name' => 'Management',
            'is_demo' => false
        ];
    }

    // Default to real active employee view (Kalbekal Haileleul, Trainee)
    return [
        'id' => 79,
        'name' => 'Kalbekal Haileleul',
        'role' => 'employee',
        'station' => 'trainee',
        'team_id' => 10,
        'team_name' => 'Civil & Architecture',
        'is_demo' => true
    ];
}

function isBimAdmin(): bool {
    $u = getActiveBimUser();
    return $u['role'] === 'admin';
}

function isBimTeamLeader(): bool {
    $u = getActiveBimUser();
    return $u['role'] === 'team_leader' || $u['role'] === 'admin';
}
