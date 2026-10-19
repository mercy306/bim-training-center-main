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
    'architect'  => 'Architects & Designers',
    'modeler'    => 'BIM Modelers & Drafters',
    'manager'    => 'Project Managers & Supervisors'
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
    // 1. Role switcher simulation for standalone/preview
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
            'team_name' => 'All Departments'
        ];
    } elseif ($demoRole === 'team_leader') {
        return [
            'id' => 201,
            'name' => 'David Cohen (Team Leader)',
            'role' => 'team_leader',
            'station' => 'engineer',
            'team_id' => 10,
            'team_name' => 'Structural Modeling Team A'
        ];
    } elseif ($demoRole === 'employee_trainee') {
        return [
            'id' => 301,
            'name' => 'Yoni Levi (Trainee)',
            'role' => 'employee',
            'station' => 'trainee',
            'team_id' => 10,
            'team_name' => 'Structural Modeling Team A'
        ];
    } elseif ($demoRole === 'employee_engineer') {
        return [
            'id' => 302,
            'name' => 'Sarah Alon (Engineer)',
            'role' => 'employee',
            'station' => 'engineer',
            'team_id' => 10,
            'team_name' => 'Structural Modeling Team A'
        ];
    }

    // 2. BSD Integrated Session: Check logged-in BSD user
    if (isset($_SESSION['user_id'])) {
        $parentRole = strtolower((string)($_SESSION['role'] ?? 'employee'));
        $name = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'BSD Employee');
        $userId = (int)$_SESSION['user_id'];
        $station = strtolower((string)($_SESSION['station'] ?? $_SESSION['department'] ?? 'all'));
        $teamId = isset($_SESSION['team_id']) ? (int)$_SESSION['team_id'] : null;
        $teamName = $_SESSION['team_name'] ?? 'General Team';

        // Map parent roles
        if (in_array($parentRole, ['admin', 'system_admin', 'office_admin'])) {
            return [
                'id' => $userId,
                'name' => $name,
                'role' => 'admin',
                'station' => 'all',
                'team_id' => null,
                'team_name' => 'All Departments'
            ];
        } elseif (in_array($parentRole, ['team_leader', 'supervisor', 'office_supervisor'])) {
            return [
                'id' => $userId,
                'name' => $name,
                'role' => 'team_leader',
                'station' => $station,
                'team_id' => $teamId ?: 10,
                'team_name' => $teamName
            ];
        } else {
            return [
                'id' => $userId,
                'name' => $name,
                'role' => 'employee',
                'station' => $station ?: 'trainee',
                'team_id' => $teamId ?: 10,
                'team_name' => $teamName
            ];
        }
    }

    // 3. Standalone mode fallback
    if (!empty($_SESSION['bim_admin_auth'])) {
        return [
            'id' => 1,
            'name' => 'BSD Administrator',
            'role' => 'admin',
            'station' => 'all',
            'team_id' => null,
            'team_name' => 'Management'
        ];
    }

    // Default to Employee view
    return [
        'id' => 300,
        'name' => 'Mihiret Tsegaye',
        'role' => 'employee',
        'station' => 'engineer',
        'team_id' => 10,
        'team_name' => 'Structural Modeling Team A'
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
