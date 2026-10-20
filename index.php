<?php
/**
 * BSD BIM Training Center - Enterprise Role-Based Portal
 * Supports: Admin (Global Analytics & Uploads), Team Leader (Scoped Team View), Employee (Learning & Progress Tracking)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';

// Active User & Role
$user = getActiveBimUser();
$role = $user['role']; // 'admin' | 'team_leader' | 'employee'

// Enforce: Only employees tagged as team leaders in PostgreSQL can access team leader mode
$isRealLeader = isEmployeeTeamLeader((int)$user['id']);
if ($role === 'team_leader' && !$isRealLeader) {
    // Downgrade to standard employee if not tagged as team leader
    $role = 'employee';
    $user['role'] = 'employee';
}

$isStaffAdmin = ($role === 'admin');
$isTeamLeader = ($role === 'team_leader');
$isEmployee = ($role === 'employee');

$userStation = $user['station'] ?? 'all';
$userName = $user['name'] ?? 'User';
$teamName = $user['team_name'] ?? 'General Team';
$teamId = $user['team_id'] ?? null;
$userAvatar = !empty($user['avatar']) ? $user['avatar'] : (!empty($user['photo']) ? $user['photo'] : null);

// Preload videos according to role & station
// Employees only see videos assigned to their station, 'all', or assigned to them individually
$visibleVideos = getBimVideos($isStaffAdmin ? null : $userStation, $isStaffAdmin ? null : ($user['id'] ?? null));
$visibleCourses = getBimCourses($isStaffAdmin ? null : $userStation, $isStaffAdmin ? null : ($user['id'] ?? null));
$allCourses = getBimCourses(); // Complete curricula catalog for admin assignment
$allStations = BIM_STATIONS;

// Preload analytics for admin / team leader (strictly scoped to leader's team members!)
$analytics = ($isStaffAdmin || $isTeamLeader) ? getTrainingAnalytics($isTeamLeader ? (int)$teamId : null, $isTeamLeader ? (int)$user['id'] : null) : null;
$roster = ($isStaffAdmin || $isTeamLeader) ? getEmployeeRosterWithStats($isTeamLeader ? (int)$teamId : null, null, $isTeamLeader ? (int)$user['id'] : null) : [];
$myProgress = getEmployeeProgressSummary($user['id']);

$stationCounts = [];
foreach ($roster as $rEmp) {
    $stKey = $rEmp['station'] ?? 'trainee';
    $stationCounts[$stKey] = ($stationCounts[$stKey] ?? 0) + 1;
}

// Collect all active employees and teams for Individual Video Assignment Picker
$allStore = getProgressStore();
$allAssignableEmployees = [];
$allAvailableTeams = [];
foreach ($allStore as $eId => $eData) {
    $eTeam = !empty($eData['team_name']) ? trim($eData['team_name']) : 'General Team';
    $allAssignableEmployees[] = [
        'id'        => (string)$eId,
        'name'      => $eData['name'] ?? ('Employee #' . $eId),
        'station'   => $eData['station'] ?? 'all',
        'team_id'   => $eData['team_id'] ?? null,
        'team_name' => $eTeam
    ];
    if ($eTeam && !in_array($eTeam, $allAvailableTeams, true)) {
        $allAvailableTeams[] = $eTeam;
    }
}
sort($allAvailableTeams);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>BSD BIM Training Center | <?= htmlspecialchars($userName) ?></title>
  <script src="https://www.gstatic.com/antigravity/web/dev/tailwindcss.min.js"></script>
  <style>
    html { scroll-behavior: smooth; }
    ::-webkit-scrollbar { width: 7px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    /* Blue Gradient Hero */
    .hero-bg {
      position: relative;
      background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 40%, #0284c7 75%, #38bdf8 100%);
      overflow: hidden;
      display: flex;
      align-items: center;
      padding: 38px 24px;
    }
    .page-header-bg {
      position: relative;
      background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 40%, #0284c7 75%, #38bdf8 100%);
      overflow: hidden;
      display: flex;
      align-items: center;
      padding: 26px 24px;
    }

    .hero-glow-1 {
      display: none;
    }
    .hero-glow-2 {
      display: none;
    }
    .hero-circle-accent {
      display: none;
    }

    /* Cards */
    .dashboard-card, .course-card {
      background: #ffffff;
      border-radius: 10px;
      border: 1px solid #e2e8f0;
      box-shadow: none;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      position: relative;
    }
    .dashboard-card:hover, .course-card:hover {
      border-color: #2563eb;
      box-shadow: 0 4px 14px rgba(37, 99, 235, 0.08);
    }

    /* Squircle Badges */
    .icon-squircle {
      width: 48px; height: 48px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; color: #ffffff; flex-shrink: 0;
    }
    .glow-blue, .glow-green, .glow-purple, .glow-amber { background: linear-gradient(135deg, #1d4ed8, #2563eb); box-shadow: 0 8px 18px -4px rgba(37, 99, 235, 0.4); }

    .video-thumb {
      position: relative; overflow: hidden; border-radius: 14px; cursor: pointer;
    }
    .video-thumb .play-btn {
      position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
      background: rgba(15, 23, 42, 0.35); transition: all 0.25s;
    }
    .video-thumb:hover .play-btn {
      background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(2px);
    }
    .video-thumb:hover .play-icon {
      transform: scale(1.12); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    }

    .badge {
      display: inline-flex; align-items: center; padding: 4px 10px;
      border-radius: 9999px; font-size: 11px; font-weight: 700; letter-spacing: 0.04em;
    }

    .nav-btn {
      border: 1px solid transparent; background: transparent; padding: 10px 20px; border-radius: 11px;
      font-size: 14px; font-weight: 700; color: rgba(255, 255, 255, 0.9); cursor: pointer;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      display: inline-flex; align-items: center; gap: 8px;
    }
    .nav-btn:hover { color: #ffffff; background: rgba(255, 255, 255, 0.18); }
    .nav-btn.active {
      color: #ffffff !important;
      background: rgba(255, 255, 255, 0.25) !important;
      border: 1px solid rgba(255, 255, 255, 0.45) !important;
      backdrop-filter: blur(8px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .nav-btn.active:hover {
      background: rgba(255, 255, 255, 0.32) !important;
    }

    .btn-upload-prominent {
      background: #ffffff !important;
      color: #1d4ed8 !important;
      border: none !important;
      padding: 10px 22px !important;
      border-radius: 11px !important;
      font-size: 14px !important;
      font-weight: 800 !important;
      letter-spacing: 0.01em;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 4px 14px rgba(0,0,0,0.12) !important;
      transition: all 0.2s ease !important;
    }
    .btn-upload-prominent:hover {
      background: #f1f5f9 !important;
      transform: translateY(-1px);
    }

    .particle {
      position: absolute; background: rgba(255, 255, 255, 0.35); border-radius: 50%;
      pointer-events: none; animation: floatUp linear infinite;
    }
    @keyframes floatUp {
      0% { transform: translateY(0) scale(1); opacity: 0; }
      15% { opacity: 0.6; }
      85% { opacity: 0.4; }
      100% { transform: translateY(-380px) scale(0.6); opacity: 0; }
    }

    .modal-overlay {
      position: fixed; inset: 0; z-index: 1000; background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; padding: 20px;
    }
    .modal-box {
      background: #ffffff; border-radius: 24px; box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.25);
      border: 1px solid #e2e8f0; width: 100%; max-width: 600px; position: relative;
      animation: modalPop 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    @keyframes modalPop {
      from { transform: scale(0.92); opacity: 0; }
      to   { transform: scale(1); opacity: 1; }
    }

    .toast {
      position: fixed; bottom: 24px; right: 24px; z-index: 3000; padding: 14px 22px;
      border-radius: 12px; color: #fff; font-weight: 700; font-size: 14px;
      box-shadow: 0 12px 30px rgba(15, 23, 42, 0.2); animation: slideIn 0.3s ease-out;
      display: flex; align-items: center; gap: 10px;
    }
    @keyframes slideIn {
      from { transform: translateY(30px); opacity: 0; }
      to   { transform: translateY(0); opacity: 1; }
    }

    .yt-frame { aspect-ratio: 16/9; width: 100%; border-radius: 14px; overflow: hidden; }
    .yt-frame iframe { width: 100%; height: 100%; border: none; }

    /* Progress Meter Ring / Bar */
    .progress-bar-fill {
      transition: width 0.4s ease-out;
    }

    /* ── INTERACTION POLISH ───────────────────────────────────────────── */
    :root {
      --ease-out: cubic-bezier(0.22, 1, 0.36, 1);
      --focus-ring: 0 0 0 4px rgba(37, 99, 235, 0.18);
    }
    *, *::before, *::after { box-sizing: border-box; }
    body { min-height: 100vh; -webkit-font-smoothing: antialiased; }
    button, a, select, input, textarea { -webkit-tap-highlight-color: transparent; }
    button, select { touch-action: manipulation; }
    button:focus-visible, a:focus-visible, select:focus-visible,
    input:focus-visible, textarea:focus-visible, [role="button"]:focus-visible {
      outline: none;
      box-shadow: var(--focus-ring) !important;
    }
    button:not(:disabled) { transition: transform 160ms var(--ease-out), box-shadow 160ms var(--ease-out), background-color 160ms ease, border-color 160ms ease, color 160ms ease; }
    button:not(:disabled):active { transform: translateY(1px) scale(0.985); }
    button:disabled { cursor: not-allowed; opacity: 0.62; }

    /* The inline navigation uses two position declarations; this keeps it pinned while scrolling. */
    body > nav { position: sticky !important; top: 0; }
    #app-root { min-height: calc(100vh - 160px); }
    #app-root > section { animation: sectionEnter 320ms var(--ease-out) both; }
    #app-root > section.is-entering { animation: sectionEnter 320ms var(--ease-out) both; }
    @keyframes sectionEnter {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .dashboard-card, .course-card { will-change: transform; }
    .dashboard-card:hover, .course-card:hover { transform: translateY(-3px); }
    .video-thumb .play-icon { transition: transform 180ms var(--ease-out), box-shadow 180ms var(--ease-out); }

    /* ── TRANSLUCENT GLASS SURFACES ───────────────────────────────────── */
    body {
      background:
        radial-gradient(circle at 10% 10%, rgba(96, 165, 250, 0.19), transparent 30rem),
        radial-gradient(circle at 94% 34%, rgba(125, 211, 252, 0.17), transparent 26rem),
        linear-gradient(145deg, #edf5ff 0%, #f8fbff 46%, #eef8ff 100%) !important;
      background-attachment: fixed !important;
    }
    body > nav {
      background: linear-gradient(105deg, rgba(29, 78, 216, 0.83), rgba(37, 99, 235, 0.76) 46%, rgba(2, 132, 199, 0.70)) !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.26);
      backdrop-filter: blur(20px) saturate(145%);
      -webkit-backdrop-filter: blur(20px) saturate(145%);
      box-shadow: 0 12px 34px rgba(15, 23, 42, 0.14) !important;
    }
    .hero-bg, .page-header-bg {
      background: linear-gradient(105deg, rgba(29, 78, 216, 0.86), rgba(37, 99, 235, 0.76) 48%, rgba(2, 132, 199, 0.68)) !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.24);
      box-shadow: inset 0 1px rgba(255, 255, 255, 0.22);
    }
    .dashboard-card, .course-card, .modal-box {
      background: rgba(255, 255, 255, 0.68) !important;
      border-color: rgba(255, 255, 255, 0.7) !important;
      backdrop-filter: blur(18px) saturate(135%);
      -webkit-backdrop-filter: blur(18px) saturate(135%);
      box-shadow: 0 12px 32px rgba(30, 64, 175, 0.08), inset 0 1px rgba(255, 255, 255, 0.58) !important;
    }
    .dashboard-card:hover, .course-card:hover {
      background: rgba(255, 255, 255, 0.82) !important;
      border-color: rgba(147, 197, 253, 0.78) !important;
      box-shadow: 0 18px 38px rgba(30, 64, 175, 0.13), inset 0 1px rgba(255, 255, 255, 0.78) !important;
    }
    input, textarea, select {
      background: rgba(255, 255, 255, 0.66) !important;
      border-color: rgba(148, 163, 184, 0.42) !important;
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }
    select option { background: #ffffff; color: #0f172a; }
    .modal-overlay { background: rgba(15, 23, 42, 0.5); }
    .glass-course-track {
      background: rgba(255, 255, 255, 0.42) !important;
      border-color: rgba(255, 255, 255, 0.62) !important;
      box-shadow: 0 18px 42px rgba(30, 64, 175, 0.07), inset 0 1px rgba(255, 255, 255, 0.55);
      backdrop-filter: blur(22px) saturate(130%);
      -webkit-backdrop-filter: blur(22px) saturate(130%);
    }
    .glass-video-tile {
      background: rgba(255, 255, 255, 0.48) !important;
      border-color: rgba(255, 255, 255, 0.66) !important;
      box-shadow: 0 12px 26px rgba(30, 64, 175, 0.08), inset 0 1px rgba(255, 255, 255, 0.58);
      backdrop-filter: blur(16px) saturate(125%);
      -webkit-backdrop-filter: blur(16px) saturate(125%);
      cursor: default;
      transition: transform 220ms var(--ease-out), background-color 220ms ease, box-shadow 220ms ease;
      animation: tileFloat 5s ease-in-out infinite;
    }
    .glass-video-tile:hover {
      background: rgba(255, 255, 255, 0.64) !important;
      border-color: rgba(255, 255, 255, 0.82) !important;
      box-shadow: 0 20px 34px rgba(30, 64, 175, 0.13), inset 0 1px rgba(255, 255, 255, 0.74);
      animation-play-state: paused;
      transform: translateY(-5px);
    }
    .glass-video-tile:nth-child(even) { animation-delay: -2.4s; }
    .glass-video-frame { box-shadow: inset 0 1px rgba(255, 255, 255, 0.18); }
    @keyframes tileFloat {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-4px); }
    }

    @media (max-width: 720px) {
      body > nav > div { min-height: auto !important; padding: 12px 16px 14px !important; gap: 12px !important; }
      body > nav img { height: 40px !important; }
      body > nav [style*="justify-content:space-between"] { justify-content: flex-start !important; }
      .nav-btn { padding: 8px 12px; font-size: 13px; }
      #app-root > section { padding-left: 16px !important; padding-right: 16px !important; }
      #video-cards-grid { grid-template-columns: 1fr !important; gap: 16px !important; }
      .modal-overlay { padding: 12px; align-items: flex-end; }
      .modal-box { max-height: calc(100vh - 24px); border-radius: 20px 20px 14px 14px; }
      .toast { left: 16px; right: 16px; bottom: 16px; justify-content: center; }
    }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { animation-duration: 1ms !important; animation-iteration-count: 1 !important; scroll-behavior: auto !important; transition-duration: 1ms !important; }
    }
  </style>
  <script>
    window.ACTIVE_USER = <?= json_encode($user) ?>;
    window.SERVER_INITIAL_VIDEOS = <?= json_encode($visibleVideos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.SERVER_INITIAL_COURSES = <?= json_encode($visibleCourses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.ALL_ADMIN_COURSES = <?= json_encode($allCourses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.ALL_ASSIGNABLE_EMPLOYEES = <?= json_encode($allAssignableEmployees, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.ALL_AVAILABLE_TEAMS = <?= json_encode($allAvailableTeams, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.USER_ROLE = '<?= $role ?>';
    window.USER_STATION = '<?= $userStation ?>';
    window.INITIAL_PROGRESS = <?= json_encode($myProgress) ?>;
  </script>
</head>
<body style="background:#ffffff;color:#0f172a;font-family:system-ui,-apple-system,sans-serif;margin:0;padding:0;">

<!-- ══════════════════════════════════════════
     TOP NAVIGATION BAR (UNIFIED ONE-COLOR BLUE HEADER)
══════════════════════════════════════════ -->
<nav style="background:radial-gradient(circle at 10% 20%, rgba(255,255,255,0.14) 0%, transparent 40%), radial-gradient(circle at 90% 80%, rgba(56,189,248,0.2) 0%, transparent 45%), linear-gradient(90deg, #1d4ed8 0%, #2563eb 40%, #0284c7 75%, #38bdf8 100%);position:sticky;top:0;z-index:100;box-shadow:0 8px 30px rgba(14,116,144,0.22);position:relative;overflow:hidden;">
  
  <!-- Subtle Ambient Circles -->
  <div style="position:absolute;left:-40px;top:-40px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,0.06);pointer-events:none;"></div>
  <div style="position:absolute;right:80px;bottom:-60px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,0.05);pointer-events:none;"></div>

  <div style="width:100%;box-sizing:border-box;padding:16px 44px 18px;min-height:160px;display:flex;flex-direction:column;justify-content:space-between;gap:18px;position:relative;z-index:2;">
    
    <!-- Top Row: Brand Logo (HIGH UP) + Global Search + User Profile + Switch View -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:18px;">
      
      <!-- Brand Logo - Positioned high up with enhanced size -->
      <div style="display:flex;align-items:center;cursor:pointer;margin-top:0;" onclick="showSection(getRoleLandingSection())">
        <img src="bsd-logo-white.png" alt="BSD Design & Management" style="height:56px;width:auto;display:block;object-fit:contain;" />
      </div>

      <!-- Right Controls: Switch View (Admin & Trainee) -->
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">

        <!-- Demo Switcher in-line with the top controls (Single Layer) -->
        <?php if (!empty($user['is_demo'])): ?>
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
          <a href="?demo_role=admin" style="background:<?= $isStaffAdmin ? 'rgba(255,255,255,0.28)' : 'rgba(15,23,42,0.3)' ?>;color:#fff;border:1px solid rgba(255,255,255,0.28);padding:6px 14px;border-radius:8px;text-decoration:none;font-weight:700;font-size:12px;">Admin</a>
          <a href="?demo_role=employee_trainee" style="background:<?= !$isStaffAdmin ? 'rgba(255,255,255,0.28)' : 'rgba(15,23,42,0.3)' ?>;color:#fff;border:1px solid rgba(255,255,255,0.28);padding:6px 14px;border-radius:8px;text-decoration:none;font-weight:700;font-size:12px;">Trainee</a>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <!-- Bottom Row: Navigation Tabs (Seamless, Single Layer) -->
    <div style="padding-top:4px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
      
      <!-- Navigation Tabs -->
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <?php if ($isStaffAdmin): ?>
          <button onclick="showSection('analytics')" class="nav-btn active" id="nav-analytics">Analytics</button>
          <button onclick="showSection('upload')" class="nav-btn" id="nav-upload">Upload Material</button>
        <?php else: ?>
          <button onclick="showSection('my-progress')" class="nav-btn active" id="nav-my-progress">My Training</button>
          <button onclick="showSection('files')"   class="nav-btn" id="nav-files">Practice Files</button>
        <?php endif; ?>
      </div>

    </div>

  </div>
</nav>


<!-- ══════════════════════════════════════════
     MAIN SECTIONS
══════════════════════════════════════════ -->
<div id="app-root">










<!-- ─────────────────────────────────────────
     4. PRACTICE FILES DOWNLOAD CENTER
───────────────────────────────────────── -->
<section id="section-files" style="display:none;background:#ffffff;padding:40px 24px 80px;">
  <div style="max-width:1200px;margin:0 auto;">

    <!-- Practice File Type Filter Dropdown -->
    <div style="margin-bottom:28px;display:flex;align-items:center;gap:12px;">
      <label for="practice-file-type-select" style="font-size:13px;font-weight:700;color:#0f172a;">File Type:</label>
      <select id="practice-file-type-select" onchange="filterPracticeFiles(this.value)" style="padding:8px 14px;border:1px solid #cbd5e1;border-radius:7px;font-size:13px;font-weight:600;color:#0f172a;background:#ffffff;outline:none;min-width:200px;cursor:pointer;">
        <option value="all">All File Types</option>
        <option value="rvt">Revit (.RVT / .RFA)</option>
        <option value="dwg">AutoCAD (.DWG)</option>
        <option value="nwd">Navisworks (.NWD)</option>
        <option value="pdf">PDF Guides</option>
      </select>
    </div>

    <div id="practice-files-grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:24px;"></div>
  </div>
</section>


<!-- ─────────────────────────────────────────
     5. EMPLOYEE PERSONAL PROGRESS SECTION
───────────────────────────────────────── -->
<?php if ($isEmployee || $isTeamLeader): ?>
<section id="section-my-progress" style="display:none;background:#ffffff;padding:40px 24px 80px;">
  <div style="max-width:1100px;margin:0 auto;">
    
    <!-- Hero Profile & Meter -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:24px;margin-bottom:32px;border-bottom:1px solid #e2e8f0;padding-bottom:28px;">
      <div>
        <h2 style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 6px;"><?= htmlspecialchars($userName) ?></h2>
        <div style="color:#64748b;font-size:14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <span>Team: <strong><?= htmlspecialchars($teamName) ?></strong></span>
        </div>
      </div>

      <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:10px;padding:16px 24px;text-align:center;min-width:200px;">
        <div style="font-size:32px;font-weight:800;color:#1d4ed8;line-height:1;" id="my-overall-pct"><?= $myProgress['overall_percent'] ?>%</div>
        <div style="font-size:12px;color:#64748b;margin-top:6px;" id="my-completed-ratio"><?= $myProgress['completed_count'] ?> of <?= $myProgress['total_assigned'] ?> Videos Completed</div>
      </div>
    </div>

    <!-- Video Completion Checklist -->
    <div style="background:transparent;border:none;padding:0;box-shadow:none;">
      <h3 style="font-size:18px;font-weight:800;color:#0f172a;margin:0 0 16px;">Assigned Training Materials Checklist</h3>
      <div id="my-videos-checklist" style="display:flex;flex-direction:column;gap:12px;"></div>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- ─────────────────────────────────────────
     6. TEAM LEADER SCOPED VIEW (TEAM ONLY)
───────────────────────────────────────── -->
<?php if ($isTeamLeader): ?>
<section id="section-team-progress" style="display:none;background:#ffffff;padding:40px 24px 80px;">
  <div style="max-width:1200px;margin:0 auto;">
    
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:36px;height:36px;border-radius:8px;background:#2563eb;color:white;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;">
          TL
        </div>
        <div>
          <h3 style="font-size:17px;font-weight:800;color:#1e3a8a;margin:0;">Team Leader: <?= htmlspecialchars($userName) ?></h3>
          <div style="font-size:13px;color:#3b82f6;font-weight:600;">
            Scoped exclusively to members of: <strong><?= htmlspecialchars($teamName) ?></strong> (<?= count($roster) ?> assigned member<?= count($roster) === 1 ? '' : 's' ?>).
          </div>
        </div>
      </div>

      <?php if (!empty($user['is_demo'])): ?>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span style="font-size:12px;font-weight:700;color:#1e40af;">Switch Team Leader:</span>
        <a href="?demo_role=team_leader&leader_id=27" style="background:<?= ($user['id'] == 27) ? '#1d4ed8' : '#ffffff' ?>;color:<?= ($user['id'] == 27) ? '#ffffff' : '#1e40af' ?>;border:1px solid #93c5fd;padding:4px 10px;border-radius:8px;font-size:11.5px;font-weight:700;text-decoration:none;">Tekle Ketema (A.D_DOR)</a>
        <a href="?demo_role=team_leader&leader_id=8" style="background:<?= ($user['id'] == 8) ? '#1d4ed8' : '#ffffff' ?>;color:<?= ($user['id'] == 8) ? '#ffffff' : '#1e40af' ?>;border:1px solid #93c5fd;padding:4px 10px;border-radius:8px;font-size:11.5px;font-weight:700;text-decoration:none;">Bezawit Birhanu (ARCH)</a>
        <a href="?demo_role=team_leader&leader_id=43" style="background:<?= ($user['id'] == 43) ? '#1d4ed8' : '#ffffff' ?>;color:<?= ($user['id'] == 43) ? '#ffffff' : '#1e40af' ?>;border:1px solid #93c5fd;padding:4px 10px;border-radius:8px;font-size:11.5px;font-weight:700;text-decoration:none;">Natan Derara (IT)</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Team Metrics -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-bottom:24px;">
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px 20px;">
        <div style="font-size:13px;color:#64748b;font-weight:600;">Team Average Completion</div>
        <div style="font-size:26px;font-weight:800;color:#2563eb;margin-top:4px;"><?= $analytics['avg_completion'] ?? 0 ?>%</div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px 20px;">
        <div style="font-size:13px;color:#64748b;font-weight:600;">Assigned Members</div>
        <div style="font-size:26px;font-weight:800;color:#0f172a;margin-top:4px;"><?= count($roster) ?></div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px 20px;">
        <div style="font-size:13px;color:#64748b;font-weight:600;">Fully Certified</div>
        <div style="font-size:26px;font-weight:800;color:#059669;margin-top:4px;"><?= $analytics['fully_completed'] ?? 0 ?></div>
      </div>
    </div>

    <!-- Scoped Team Member Roster -->
    <div style="border:1px solid #e2e8f0;border-radius:10px;padding:20px;background:#ffffff;">
      <h3 style="font-size:17px;font-weight:800;color:#0f172a;margin:0 0 16px;">Team Member Progress Details</h3>
      
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;text-align:left;font-size:13.5px;">
          <thead>
            <tr style="border-bottom:2px solid #f1f5f9;color:#64748b;">
              <th style="padding:12px 14px;">Member Name</th>
              <th style="padding:12px 14px;">Station / Discipline</th>
              <th style="padding:12px 14px;">Completed Videos</th>
              <th style="padding:12px 14px;">Completion %</th>
              <th style="padding:12px 14px;">Status</th>
              <th style="padding:12px 14px;">Last Activity</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($roster)): ?>
              <tr>
                <td colspan="6" style="padding:30px;text-align:center;color:#64748b;">No members currently assigned to this team.</td>
              </tr>
            <?php else: foreach ($roster as $m): ?>
              <tr style="border-bottom:1px solid #f8fafc;">
                <td style="padding:14px;font-weight:800;color:#0f172a;">
                  <div style="display:flex;align-items:center;gap:10px;">
                    <span style="width:32px;height:32px;border-radius:50%;background:#e0f2fe;color:#0369a1;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;">
                      <?= strtoupper(substr($m['name'], 0, 1)) ?>
                    </span>
                    <?= htmlspecialchars($m['name']) ?>
                  </div>
                </td>
                <td style="padding:14px;">
                  <span class="badge" style="background:#f1f5f9;color:#334155;"><?= htmlspecialchars(BIM_STATIONS[$m['station']] ?? $m['station']) ?></span>
                </td>
                <td style="padding:14px;font-weight:700;color:#0f172a;">
                  <?= $m['completed'] ?> / <?= $m['total_assigned'] ?>
                </td>
                <td style="padding:14px;width:180px;">
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div style="flex:1;background:#f1f5f9;height:8px;border-radius:9999px;overflow:hidden;">
                      <div style="width:<?= $m['percent'] ?>%;background:linear-gradient(90deg,#2563eb,#06b6d4);height:100%;"></div>
                    </div>
                    <span style="font-weight:800;font-size:12px;color:#1e293b;"><?= $m['percent'] ?>%</span>
                  </div>
                </td>
                <td style="padding:14px;">
                  <span class="badge" style="background:<?= $m['percent'] >= 100 ? '#ecfdf5' : ($m['percent'] > 0 ? '#eff6ff' : '#fef2f2') ?>;color:<?= $m['percent'] >= 100 ? '#059669' : ($m['percent'] > 0 ? '#1d4ed8' : '#b91c1c') ?>;">
                    <?= $m['status'] ?>
                  </span>
                </td>
                <td style="padding:14px;color:#64748b;font-size:12px;">
                  <?= htmlspecialchars($m['last_active']) ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- ─────────────────────────────────────────
     7. ADMIN GLOBAL ANALYTICS & ROSTER BOARD
───────────────────────────────────────── -->
<?php if ($isStaffAdmin): ?>
<section id="section-analytics" style="display:none;background:#ffffff;padding:40px 24px 80px;">
  <div style="max-width:1300px;margin:0 auto;">
    


    <!-- Metrics Row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:24px;">
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;">
        <div style="font-size:13px;color:#64748b;font-weight:600;">Total Trainees Enrolled</div>
        <div style="font-size:24px;font-weight:800;color:#0f172a;margin-top:4px;"><?= $analytics['total_employees'] ?? 0 ?></div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;">
        <div style="font-size:13px;color:#64748b;font-weight:600;">Completion Rate</div>
        <div style="font-size:24px;font-weight:800;color:#2563eb;margin-top:4px;"><?= $analytics['avg_completion'] ?? 0 ?>%</div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;">
        <div style="font-size:13px;color:#64748b;font-weight:600;">Fully Certified</div>
        <div style="font-size:24px;font-weight:800;color:#2563eb;margin-top:4px;"><?= $analytics['fully_completed'] ?? 0 ?></div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;">
        <div style="font-size:13px;color:#64748b;font-weight:600;">In Progress</div>
        <div style="font-size:24px;font-weight:800;color:#0f172a;margin-top:4px;"><?= $analytics['in_progress'] ?? 0 ?></div>
      </div>
    </div>

    <!-- Company-Wide Trainee Roster Table -->
    <div style="border:1px solid #e2e8f0;border-radius:8px;padding:20px;background:#ffffff;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;">
        <div>
          <h3 style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">Trainee Training Progress</h3>
        </div>
        
        <div style="display:flex;gap:10px;align-items:center;">
          <input id="admin-roster-search" oninput="filterAdminRoster(this.value)" placeholder="Search trainee..." style="padding:8px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;width:220px;outline:none;" />
        </div>
      </div>

      <div style="overflow-x:auto;">
        <table id="admin-roster-table" style="width:100%;border-collapse:collapse;text-align:left;font-size:13.5px;">
          <thead>
            <tr style="border-bottom:1px solid #e2e8f0;color:#64748b;">
              <th style="padding:10px 14px;font-weight:600;">Trainee Name</th>
              <th style="padding:10px 14px;font-weight:600;">Team</th>
              <th style="padding:10px 14px;font-weight:600;">Videos</th>
              <th style="padding:10px 14px;font-weight:600;">Progress</th>
              <th style="padding:10px 14px;font-weight:600;">Status</th>
              <th style="padding:10px 14px;font-weight:600;">Last Activity</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($roster as $emp): ?>
              <tr class="roster-row" data-name="<?= strtolower(htmlspecialchars($emp['name'])) ?>" data-station="<?= $emp['station'] ?>" style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:12px 14px;font-weight:700;color:#0f172a;">
                  <?= htmlspecialchars($emp['name']) ?>
                </td>
                <td style="padding:12px 14px;color:#64748b;">
                  <?= htmlspecialchars($emp['team_name']) ?>
                </td>
                <td style="padding:12px 14px;font-weight:600;color:#0f172a;">
                  <?= $emp['completed'] ?> / <?= $emp['total_assigned'] ?>
                </td>
                <td style="padding:12px 14px;width:160px;">
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div style="flex:1;background:#f1f5f9;height:6px;border-radius:9999px;overflow:hidden;">
                      <div style="width:<?= $emp['percent'] ?>%;background:#2563eb;height:100%;"></div>
                    </div>
                    <span style="font-weight:700;font-size:12px;color:#0f172a;"><?= $emp['percent'] ?>%</span>
                  </div>
                </td>
                <td style="padding:12px 14px;">
                  <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:600;background:<?= $emp['percent'] >= 100 ? '#eff6ff' : '#f8fafc' ?>;color:<?= $emp['percent'] >= 100 ? '#2563eb' : '#475569' ?>;border:1px solid <?= $emp['percent'] >= 100 ? '#bfdbfe' : '#e2e8f0' ?>;">
                    <?= $emp['status'] ?>
                  </span>
                </td>
                <td style="padding:12px 14px;color:#64748b;font-size:12px;">
                  <?= htmlspecialchars($emp['last_active']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- ─────────────────────────────────────────
     8. ADMIN UPLOAD MATERIAL (ADMIN ONLY)
───────────────────────────────────────── -->
<?php if ($isStaffAdmin): ?>
<section id="section-upload" style="display:none;background:#ffffff;padding:40px 24px 80px;">
  <div style="max-width:960px;margin:0 auto;">
    
    <div style="margin-bottom:24px;">
      <h2 style="font-size:24px;font-weight:800;color:#0f172a;margin:0;">Upload Training Material</h2>
    </div>

    <!-- Admin Subtabs -->
    <div style="display:flex;gap:10px;margin-bottom:28px;border-bottom:1px solid #e2e8f0;padding-bottom:14px;">
      <button id="atab-video" onclick="switchAdminUploadTab('video')" style="background:#2563eb;color:#ffffff;border:none;padding:9px 18px;border-radius:8px;font-size:13.5px;font-weight:700;cursor:pointer;">
        Video Lessons
      </button>
      <button id="atab-course" onclick="switchAdminUploadTab('course')" style="background:#f8fafc;color:#475569;border:1px solid #cbd5e1;padding:9px 18px;border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;">
        Curricula Management
      </button>
      <button id="atab-files" onclick="switchAdminUploadTab('files')" style="background:#f8fafc;color:#475569;border:1px solid #cbd5e1;padding:9px 18px;border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;">
        Practice Datasets
      </button>
    </div>

    <!-- TAB 1: VIDEO UPLOAD (Single Layer, Flat Clean UI) -->
    <div id="admin-panel-video" style="background:transparent;border:none;border-radius:0;padding:0;box-shadow:none;">

      <!-- Mode Selector -->
      <div style="display:flex;gap:8px;margin-bottom:20px;max-width:540px;">
        <button type="button" id="vmode-btn-file" onclick="setVideoUploadMode('file')" style="flex:1;background:#2563eb;color:white;border:none;padding:9px 14px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
          Upload Video File
        </button>
        <button type="button" id="vmode-btn-yt" onclick="setVideoUploadMode('yt')" style="flex:1;background:#ffffff;color:#475569;border:1px solid #cbd5e1;padding:9px 14px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
          YouTube / Link
        </button>
        <button type="button" id="vmode-btn-folder" onclick="setVideoUploadMode('folder')" style="flex:1;background:#ffffff;color:#475569;border:1px solid #cbd5e1;padding:9px 14px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
          Local Folder
        </button>
      </div>

      <!-- Mode 1: File Upload Drop Zone & Fullscreen Preview Player -->
      <div id="vinput-container-file" style="display:block;margin-bottom:20px;">
        <div class="drop-zone" onclick="document.getElementById('new-video-file-input').click()" style="border:1.5px dashed #cbd5e1;border-radius:10px;padding:28px 20px;text-align:center;cursor:pointer;background:#f8fafc;transition:border-color 0.2s;" ondragover="event.preventDefault();this.style.borderColor='#2563eb';" ondragleave="this.style.borderColor='#cbd5e1';" ondrop="handleAdminVideoDrop(event)">
          <div id="vfile-selected-name" style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:4px;">Click or drag to select video file (.mp4, .webm, .mov)</div>
          <div style="font-size:12px;color:#64748b;">Multiple files supported • Click to preview fullscreen</div>
          <input id="new-video-file-input" type="file" accept="video/*" multiple style="display:none;" onchange="handleAdminVideoFilePick(this)" />
        </div>

        <!-- Video Fullscreen Preview Shown Immediately When Added -->
        <video id="upload-video-preview" controls playsinline style="width:100%;max-height:480px;border-radius:10px;background:#000;display:none;margin-top:14px;outline:none;"></video>

        <!-- Multiple Files Queue -->
        <div id="vfiles-queue-container" style="display:none;margin-top:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
            <span style="font-size:13px;font-weight:800;color:#0f172a;" id="vfiles-queue-title">Selected Videos</span>
            <span style="font-size:12px;color:#64748b;" id="vfiles-queue-size"></span>
            <button type="button" onclick="clearSelectedVideoFiles()" style="background:#ffffff;color:#475569;border:1px solid #cbd5e1;padding:4px 10px;border-radius:6px;font-size:11.5px;font-weight:700;cursor:pointer;">Clear</button>
          </div>
          <div id="vfiles-queue-list" style="display:flex;flex-direction:column;gap:8px;max-height:220px;overflow-y:auto;"></div>
        </div>
      </div>

      <!-- Mode 2: Link -->
      <div id="vinput-container-yt" style="display:none;margin-bottom:20px;">
        <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Video URL (YouTube or Web Link) *</label>
        <input id="new-video-url" type="text" placeholder="https://www.youtube.com/watch?v=..." style="width:100%;padding:11px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:13.5px;box-sizing:border-box;"/>
      </div>

      <!-- Mode 3: Direct Folder -->
      <div id="vinput-container-folder" style="display:none;margin-bottom:20px;">
        <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Video Filename in videos/ folder *</label>
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="font-weight:700;color:#64748b;font-size:13.5px;background:#f1f5f9;padding:11px 14px;border:1px solid #cbd5e1;border-radius:8px;">videos/</span>
          <input id="new-video-folder-file" type="text" placeholder="lesson.mp4" style="flex:1;padding:11px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:13.5px;box-sizing:border-box;"/>
        </div>
      </div>

      <!-- Form Grid (Single Flat Layer) -->
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
        <div style="grid-column:1/-1;">
          <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Video Title *</label>
          <input id="new-video-title" type="text" placeholder="e.g. Structural Modeling & Detailing" style="width:100%;padding:11px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:13.5px;box-sizing:border-box;"/>
        </div>

        <div>
          <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Video Type *</label>
          <select id="new-video-cat" style="width:100%;padding:11px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:13.5px;background:white;color:#0f172a;">
            <option value="revit">Revit Structure</option>
            <option value="coordination">BIM Coordination</option>
            <option value="autocad">AutoCAD Drafting</option>
            <option value="navisworks">Navisworks</option>
          </select>
        </div>

        <input type="hidden" id="new-video-station" value="trainee" />
        <input type="hidden" id="new-video-assigned-employees" value="" />

        <div>
          <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Duration <span style="font-weight:600;color:#64748b;">(automatic)</span></label>
          <input id="new-video-duration" type="text" value="Select a video file" readonly aria-label="Automatically detected video duration" style="width:100%;padding:11px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:13.5px;box-sizing:border-box;color:#475569;cursor:default;"/>
        </div>

        <div style="grid-column:1/-1;">
          <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Description</label>
          <textarea id="new-video-desc" rows="3" placeholder="Learning outcomes..." style="width:100%;padding:11px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:13.5px;box-sizing:border-box;resize:vertical;"></textarea>
        </div>
      </div>

      <input type="hidden" id="inline-curriculum-title" value="" />
      <input type="hidden" id="inline-curriculum-level" value="Intermediate" />
      <input type="hidden" id="inline-curriculum-icon" value="" />
      <input type="hidden" id="inline-curriculum-desc" value="" />

      <div style="display:flex;justify-content:flex-end;margin-bottom:24px;">
        <button id="admin-publish-video-btn" onclick="adminPublishVideo()" style="background:#2563eb;color:white;border:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
          Publish Video
        </button>
      </div>

      <!-- Live Video Management List -->
      <div style="border-top:1px solid #e2e8f0;padding-top:20px;">
        <h4 style="font-size:15px;font-weight:800;color:#0f172a;margin:0 0 14px;">
          Published Videos Catalog (<span id="admin-video-count"><?= count($visibleVideos) ?></span>)
        </h4>
        <div id="admin-video-manage-list" style="display:flex;flex-direction:column;gap:10px;"></div>
      </div>
    </div>

    <!-- TAB 2: CURRICULA MANAGEMENT & CREATOR -->
    <div id="admin-panel-course" style="display:none;background:transparent;border:none;padding:0;box-shadow:none;">
      <div style="margin-bottom:20px;">
        <h3 style="font-size:18px;font-weight:800;color:#0f172a;margin:0 0 6px;">Curricula Management</h3>
        <p style="color:#64748b;font-size:13.5px;margin:0;">Create comprehensive multi-lesson training curricula, set target stations, and organize course syllabi.</p>
      </div>

      <div style="margin-bottom:28px;">
        <div style="font-size:15px;font-weight:800;color:#0f172a;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
          Create New Curriculum Track
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px;">
          <div style="grid-column:1/-1;">
            <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Curriculum Track Title *</label>
            <input id="new-course-title" type="text" placeholder="e.g. Navisworks Clash Management & BCF Coordination" style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;box-sizing:border-box;"/>
          </div>

          <div>
            <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Category / Software *</label>
            <select id="new-course-cat" style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;background:white;">
              <option value="coordination">BIM Coordination</option>
              <option value="revit">Revit Structure & Arch</option>
              <option value="autocad">AutoCAD Drafting</option>
              <option value="navisworks">Navisworks Manage</option>
            </select>
          </div>

          <input type="hidden" id="new-course-station" value="trainee" />

          <div style="display:none;">
            <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Difficulty Level</label>
            <select id="new-course-level" style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;background:white;">
              <option value="Beginner">Beginner</option>
              <option value="Intermediate" selected>Intermediate</option>
              <option value="Advanced">Advanced</option>
            </select>
          </div>

          <div>
            <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Curriculum Icon Badge</label>
            <select id="new-course-icon" style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;background:white;">
              <option value="" selected>None (Clean Title)</option>
              <option value="">Structure & Modeling</option>
              <option value="">Reinforcement & Rebar</option>
              <option value="">Coordination & Clash</option>
              <option value="️">️ AutoCAD 2D/3D</option>
              <option value="">Architecture & Building</option>
              <option value="">Standards & Management</option>
            </select>
          </div>

          <div style="grid-column:1/-1;">
            <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Course Track</label>
            <input id="new-course-duration" type="text" placeholder="e.g. Standard (or self-paced)" style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;box-sizing:border-box;"/>
          </div>

          <div style="grid-column:1/-1;">
            <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Curriculum Description & Objectives</label>
            <textarea id="new-course-desc" rows="2" placeholder="Course syllabus overview and core learning outcomes..." style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;box-sizing:border-box;resize:vertical;"></textarea>
          </div>
        </div>

        <div style="display:flex;justify-content:flex-end;">
          <button id="admin-create-course-btn" onclick="adminPublishCourse()" style="background:#2563eb;color:white;border:none;padding:12px 28px;border-radius:10px;font-size:14px;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(37,99,235,0.3);">
            Create Curriculum Track
          </button>
        </div>
      </div>

      <!-- Published Curricula & Syllabus Inspector -->
      <div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
          <h4 style="font-size:16px;font-weight:900;color:#0f172a;margin:0;">
            Active Curricula Tracks (<span id="admin-curricula-count">0</span>)
          </h4>
          <span style="font-size:12px;color:#64748b;">Videos can be added to any of these tracks during video upload</span>
        </div>
        <div id="admin-curricula-list" style="display:flex;flex-direction:column;gap:14px;"></div>
      </div>
    </div>

    <!-- TAB 3: PRACTICE FILES -->
    <div id="admin-panel-files" style="display:none;background:transparent;border:none;padding:0;box-shadow:none;">
      <h3 style="font-size:18px;font-weight:800;color:#0f172a;margin:0 0 12px;">Upload Practice Datasets</h3>
      <div class="drop-zone" onclick="document.getElementById('admin-file-picker').click()" style="border:1.5px dashed #cbd5e1;border-radius:10px;padding:48px 24px;text-align:center;cursor:pointer;background:#f8fafc;">
        <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:4px;">Upload RVT, DWG, NWD Datasets</div>
        <input id="admin-file-picker" type="file" multiple style="display:none;" onchange="handleAdminPracticeFilesUpload(this)" />
      </div>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- ─────────────────────────────────────────
     9. CLASSROOM & PLAYER (Modern Ronasit-Style Course Player)
───────────────────────────────────────── -->
<section id="section-classroom" style="display:none;background:#ffffff;padding:24px 24px 80px;">
  <div style="max-width:1400px;margin:0 auto;">
    
    <!-- Breadcrumbs Navigation -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
      <div style="display:flex;align-items:center;gap:10px;font-size:13.5px;color:#64748b;font-weight:600;flex-wrap:wrap;">
        <button onclick="showSection('my-progress')" style="background:#ffffff;border:1px solid #cbd5e1;color:#0f172a;padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all 0.15s;">
          <span>←</span> Back to My Training
        </button>
        <span style="color:#cbd5e1;">/</span>
        <span>Curricula</span>
        <span style="color:#cbd5e1;">/</span>
        <span id="classroom-course-cat" style="color:#2563eb;font-weight:700;text-transform:uppercase;">REVIT</span>
        <span style="color:#cbd5e1;">/</span>
        <span id="classroom-course-title" style="color:#0f172a;font-weight:700;">Course Title</span>
      </div>
    </div>

    <!-- Main Two-Column Player & Playlist Layout Grid -->
    <div id="classroom-layout-grid" style="display:grid;grid-template-columns:minmax(0, 1fr) 380px;gap:28px;align-items:start;">
      
      <!-- LEFT COLUMN: Main Video Player & Metadata -->
      <div>
        <!-- 16:9 Aspect Ratio Video Viewport -->
        <div id="classroom-player-viewport" style="position:relative;width:100%;aspect-ratio:16/9;background:#000000;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.1);border:1px solid #e2e8f0;">
          <div id="classroom-player" style="width:100%;height:100%;"></div>
        </div>

        <!-- Video Metadata, Tags, Title & Instructor Actions -->
        <div style="margin-top:20px;">
          <!-- Category / Tags Row -->
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
            <span style="display:inline-block;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:700;background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;">BIM Training</span>
            <span id="classroom-badge-software" style="display:inline-block;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:700;background:#f8fafc;color:#475569;border:1px solid #e2e8f0;">Revit Structure</span>
            <span id="classroom-lesson-resume-badge" style="display:none;background:#eff6ff;color:#2563eb;font-size:11.5px;font-weight:700;padding:3px 8px;border-radius:6px;border:1px solid #bfdbfe;">⏱️ Resumed</span>
          </div>

          <!-- Video Lesson Title -->
          <h1 id="classroom-lesson-title" style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 16px;line-height:1.35;">Lesson Overview</h1>

          <!-- Author & Controls Bar -->
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;padding-top:16px;border-top:1px solid #e2e8f0;">
            <!-- Author Info -->
            <div style="display:flex;align-items:center;gap:12px;">
              <div style="width:42px;height:42px;border-radius:50%;background:#eff6ff;border:1.5px solid #bfdbfe;color:#2563eb;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;">
                BSD
              </div>
              <div>
                <div style="font-size:14px;font-weight:700;color:#0f172a;">BSD BIM Department</div>
                <div style="font-size:12px;color:#64748b;font-weight:500;">Training Center Instructor</div>
              </div>
            </div>

            <!-- Action Controls -->
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <button id="classroom-complete-btn" onclick="toggleCurrentLessonCompletion()" style="background:#ffffff;color:#0f172a;border:1.5px solid #cbd5e1;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all 0.18s;">
                <span id="classroom-complete-icon">○</span> Mark as Completed
              </button>
              <button id="classroom-prev-btn" onclick="goToPrevClassroomLesson()" style="background:#f8fafc;border:1px solid #cbd5e1;color:#334155;padding:9px 16px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;">
                ← Prev
              </button>
              <div id="classroom-step-indicator" style="font-size:12px;color:#64748b;font-weight:700;">Lesson 1 of 1</div>
              <button id="classroom-next-btn" onclick="goToNextClassroomLesson()" style="background:#2563eb;color:#ffffff;border:none;padding:9px 20px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 8px rgba(37,99,235,0.25);">
                <span>Next</span> →
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN: Playlist Sidebar with Thumbnails + Suggested Courses -->
      <div>
        <!-- Course Lessons Playlist Card -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:20px;box-shadow:0 4px 20px rgba(15,23,42,0.03);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div>
              <h3 style="font-size:15px;font-weight:800;color:#0f172a;margin:0;">Course Lessons</h3>
              <div style="font-size:12px;color:#64748b;margin-top:2px;">Step-by-step curriculum</div>
            </div>
            <span id="classroom-playlist-count" style="font-size:12px;color:#2563eb;font-weight:800;background:#eff6ff;padding:4px 9px;border-radius:6px;">0 / 0</span>
          </div>

          <div style="background:#f1f5f9;border-radius:9999px;height:6px;overflow:hidden;margin-bottom:6px;">
            <div id="classroom-progress-bar" style="background:#2563eb;width:0%;height:100%;transition:width 0.3s;"></div>
          </div>
          <div id="classroom-progress-text" style="font-size:11.5px;color:#64748b;font-weight:700;text-align:right;margin-bottom:16px;">0% Completed</div>

          <div id="classroom-playlist-items" style="display:flex;flex-direction:column;gap:10px;max-height:450px;overflow-y:auto;padding-right:2px;"></div>
        </div>

        <!-- Suggested Courses Card -->
        <div style="margin-top:20px;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;box-shadow:0 4px 20px rgba(15,23,42,0.03);">
          <h4 style="font-size:14px;font-weight:800;color:#0f172a;margin:0 0 12px;">Other Curricula</h4>
          <div id="classroom-suggested-courses" style="display:flex;flex-direction:column;gap:10px;"></div>
        </div>
      </div>

    </div>
  </div>
</section>

</div><!-- End app-root -->


<!-- ══════════════════════════════════════════
     POPUP MODALS (VIDEO PLAYER WITH RANGE & PROGRESS)
══════════════════════════════════════════ -->
<div id="modal-container" style="display:none;" class="modal-overlay" onclick="if(event.target===this)closeModal()">
  <div id="modal-video" class="modal-box" style="display:none;width:95vw;max-width:1200px;max-height:94vh;padding:20px;border-radius:12px;background:#ffffff;box-sizing:border-box;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;gap:12px;">
      <h3 id="vmodal-title" style="font-size:18px;font-weight:800;color:#0f172a;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">Demonstration Video</h3>
      <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
        <button type="button" onclick="toggleActiveVideoFullscreen()" style="background:#2563eb;color:#ffffff;border:none;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;">
          Fullscreen
        </button>
        <button type="button" onclick="closeModal()" style="background:#f1f5f9;border:1px solid #cbd5e1;font-size:14px;font-weight:800;color:#64748b;padding:4px 10px;border-radius:6px;cursor:pointer;">✕</button>
      </div>
    </div>
    
    <div class="yt-frame" id="vmodal-player" style="background:#000;border-radius:10px;overflow:hidden;width:100%;min-height:500px;"></div>
    
    <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <p id="vmodal-desc" style="color:#64748b;font-size:13px;line-height:1.4;margin:0;flex:1;"></p>
      <div id="vmodal-status-pill" style="color:#64748b;font-size:12px;font-weight:600;"></div>
    </div>
  </div>
</div>





<!-- ══════════════════════════════════════════
     CLIENT JAVASCRIPT & PROGRESS ENGINE
══════════════════════════════════════════ -->
<script>
const IS_STAFF_ADMIN = <?= $isStaffAdmin ? 'true' : 'false' ?>;
let currentPlayingVideoId = null;
let currentPlayingVideoElement = null;
let videoProgressDebounceTimer = null;

const STORAGE_KEY_COURSES = 'BSD_CUSTOM_COURSES';
const STORAGE_KEY_PROGRESS = 'BSD_STUDENT_PROGRESS';

const DEFAULT_COURSES = [];
const DEFAULT_PRACTICE_FILES = [];

function getAllCourses() {
  return window.SERVER_INITIAL_COURSES || [];
}

function getAllAdminCourses() {
  if (Array.isArray(window.ALL_ADMIN_COURSES)) {
    return window.ALL_ADMIN_COURSES;
  }
  return getAllCourses();
}

function getAllPracticeFiles() {
  return DEFAULT_PRACTICE_FILES;
}

async function getAllVideos() {
  return window.SERVER_INITIAL_VIDEOS || [];
}

/* ── COOKIE PERSISTENCE HELPERS ── */
function setBimCookie(name, value, days = 30) {
  try {
    const d = new Date();
    d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
    const expires = "expires=" + d.toUTCString();
    document.cookie = encodeURIComponent(name) + "=" + encodeURIComponent(value) + ";" + expires + ";path=/;SameSite=Lax";
  } catch (e) {
    console.error('Cookie set error:', e);
  }
}

function getBimCookie(name) {
  try {
    const cname = encodeURIComponent(name) + "=";
    const decodedCookie = decodeURIComponent(document.cookie);
    const ca = decodedCookie.split(';');
    for (let i = 0; i < ca.length; i++) {
      let c = ca[i].trim();
      if (c.indexOf(cname) === 0) {
        return c.substring(cname.length);
      }
    }
  } catch (e) {
    console.error('Cookie get error:', e);
  }
  return null;
}

/* ── CURRICULUM UNLOCK & PROGRESS LOGIC ── */
function isLessonCompleted(lessonId) {
  if (!lessonId) return false;
  const progressData = window.INITIAL_PROGRESS || {};
  const watchedMap = progressData.watched_videos || {};
  const rec = watchedMap[lessonId];
  return !!(rec && (rec.completed === true || rec.percent >= 90));
}

function isLessonUnlocked(course, lessonIdx) {
  // Staff Admin has global access to all lessons for preview/auditing
  if (typeof window.USER_ROLE !== 'undefined' && window.USER_ROLE === 'admin') return true;
  // First video in sequence is always unlocked
  if (lessonIdx <= 0) return true;
  if (!course || !course.syllabus) return false;

  // Video #N cannot be opened unless Video #(N-1) is finished!
  const prevLesson = course.syllabus[lessonIdx - 1];
  if (!prevLesson) return false;
  const prevId = prevLesson.id || ('les_' + (lessonIdx - 1));
  return isLessonCompleted(prevId);
}

function findVideoCurriculumContext(videoId) {
  if (!videoId) return null;
  const courses = getAllCourses();
  for (const c of courses) {
    if (c.syllabus && Array.isArray(c.syllabus)) {
      const idx = c.syllabus.findIndex(l => l.id === videoId || l.ytId === videoId);
      if (idx !== -1) {
        return { course: c, index: idx, lesson: c.syllabus[idx] };
      }
    }
  }
  return null;
}

function getCurriculumProgressStats(course) {
  if (!course || !course.syllabus || course.syllabus.length === 0) {
    return { completedCount: 0, total: 0, percent: 0, isAllCompleted: false };
  }
  const total = course.syllabus.length;
  let completedCount = 0;
  course.syllabus.forEach((l, idx) => {
    const lId = l.id || ('les_' + idx);
    if (isLessonCompleted(lId)) completedCount++;
  });
  const percent = total > 0 ? Math.round((completedCount / total) * 100) : 0;
  return { completedCount, total, percent, isAllCompleted: completedCount >= total };
}

/* ── PROGRESS TRACKING REALTIME ENGINE ── */
async function syncVideoProgress(videoId, percent, completed = false, position = 0) {
  if (!videoId) return;
  try {
    const res = await fetch('api/progress.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        video_id: videoId, 
        percent: percent, 
        completed: completed, 
        position: position 
      })
    });
    const json = await res.json();
    if (json.success && json.summary) {
      window.INITIAL_PROGRESS = json.summary;
      updateProgressDisplay(json.summary);
      if (currentClassroomCourse) {
        renderClassroomPlaylist();
        updateClassroomNavButtons();
      }
    }
  } catch (err) {
    console.error('Progress sync error:', err);
  }
}

function updateProgressDisplay(summary) {
  if (!summary) return;
  const navPct = document.getElementById('nav-my-pct');
  const myPct = document.getElementById('my-overall-pct');
  const myRatio = document.getElementById('my-completed-ratio');
  const statPct = document.getElementById('stat-my-pct');
  const statDone = document.getElementById('stat-my-done');

  if (navPct) navPct.textContent = summary.overall_percent + '%';
  if (myPct) myPct.textContent = summary.overall_percent + '%';
  if (statPct) statPct.textContent = summary.overall_percent + '%';
  if (myRatio) myRatio.textContent = `${summary.completed_count} of ${summary.total_assigned} Videos Completed`;
  if (statDone) statDone.textContent = `${summary.completed_count} / ${summary.total_assigned}`;

  // Re-render checklist if on my-progress
  renderEmployeeProgressChecklist();
}

/* ── ANTI-SKIPPING & COOKIE RESUME VIDEO TRACKER ── */
function attachVideoTracking(videoEl, videoId, courseContext = null) {
  if (!videoEl || !videoId) return;
  currentPlayingVideoId = videoId;
  currentPlayingVideoElement = videoEl;

  const currentUserId = (window.ACTIVE_USER && window.ACTIVE_USER.id) ? window.ACTIVE_USER.id : 'emp';
  const cookieKey = `bim_video_resume_${currentUserId}_${videoId}`;
  const localKey = `bim_video_resume_${currentUserId}_${videoId}`;

  const alreadyDone = isLessonCompleted(videoId);
  const isAdmin = (typeof window.USER_ROLE !== 'undefined' && window.USER_ROLE === 'admin');
  
  // Anti-skip is enforced for uncompleted videos of members/trainees/employees
  const antiSkipStrict = !alreadyDone && !isAdmin;

  // Read saved position from Cookie > LocalStorage > Backend
  let savedPosition = 0;
  try {
    const fromCookie = getBimCookie(cookieKey);
    const fromLocal = localStorage.getItem(localKey);
    const fromServer = (window.INITIAL_PROGRESS?.watched_videos?.[videoId]?.position) || 0;
    savedPosition = parseFloat(fromCookie || fromLocal || fromServer || 0);
  } catch (e) {}

  let maxWatchedSeconds = savedPosition > 0 ? savedPosition : 0;
  let lastValidTime = maxWatchedSeconds;
  let positionRestored = false;
  let isResettingSeek = false;
  let lastWarnToastMs = 0;

  const performResume = () => {
    if (positionRestored) return;
    if (savedPosition > 2 && (!videoEl.duration || savedPosition < videoEl.duration - 4)) {
      isResettingSeek = true;
      videoEl.currentTime = savedPosition;
      lastValidTime = savedPosition;
      maxWatchedSeconds = Math.max(maxWatchedSeconds, savedPosition);
      positionRestored = true;
      const mins = Math.floor(savedPosition / 60);
      const secs = Math.floor(savedPosition % 60).toString().padStart(2, '0');
      
      const badge = document.getElementById('classroom-lesson-resume-badge');
      if (badge) {
        badge.style.display = 'inline-block';
        badge.textContent = `⏱️ Resumed from ${mins}:${secs} (via cookie)`;
      }
      showToast(`⏱️ Resumed from where you stopped (${mins}:${secs}) via cookie`, '#2563eb');
    }
  };

  videoEl.addEventListener('loadedmetadata', performResume);
  videoEl.addEventListener('canplay', () => {
    if (!positionRestored && savedPosition > 2) performResume();
  });

  // 1. Anti-Skipping: Block forward seeking beyond watched content
  videoEl.addEventListener('seeking', () => {
    if (isResettingSeek) {
      return;
    }

    if (!antiSkipStrict) {
      lastValidTime = videoEl.currentTime;
      return;
    }

    // Forward scrubbing past watched progress is rejected
    if (videoEl.currentTime > maxWatchedSeconds + 1.2) {
      isResettingSeek = true;
      const targetTime = Math.min(lastValidTime, maxWatchedSeconds);
      videoEl.currentTime = targetTime;

      const now = Date.now();
      if (now - lastWarnToastMs > 2500) {
        lastWarnToastMs = now;
        showToast('️ Fast-forwarding is restricted. Please watch the video to progress. (Rewind is allowed)', '#ef4444');
      }
    } else {
      // Rewinding or seeking within already-watched range is completely allowed
      lastValidTime = videoEl.currentTime;
    }
  });

  videoEl.addEventListener('seeked', () => {
    isResettingSeek = false;
    lastValidTime = videoEl.currentTime;
  });

  // 2. Continuous timeupdate: update max watched position, throttled cookie saving
  let lastCookieSaveMs = 0;
  let lastSyncPercentTier = 0;

  videoEl.addEventListener('timeupdate', () => {
    if (!videoEl.duration) return;
    const curTime = videoEl.currentTime;

    // During normal playback (not actively seeking forward)
    if (!videoEl.seeking) {
      if (curTime > maxWatchedSeconds) {
        maxWatchedSeconds = curTime;
      }
      lastValidTime = curTime;
    }

    // Save exact second to Cookie & LocalStorage every 2.5 seconds
    const now = Date.now();
    if (now - lastCookieSaveMs > 2500 && curTime > 1) {
      lastCookieSaveMs = now;
      setBimCookie(cookieKey, Math.floor(curTime));
      try { localStorage.setItem(localKey, Math.floor(curTime)); } catch (e) {}
    }

    const pct = Math.round((curTime / videoEl.duration) * 100);

    // Update modal pill if visible
    const pill = document.getElementById('vmodal-status-pill');
    if (pill) {
      pill.textContent = pct >= 90 ? 'Completed' : `Watching (${pct}%) • Cookies Active`;
      pill.style.background = pct >= 90 ? '#ecfdf5' : '#eff6ff';
      pill.style.color = pct >= 90 ? '#059669' : '#1d4ed8';
    }

    // Milestones sync to backend with exact position
    if (pct >= 90 && lastSyncPercentTier < 90) {
      lastSyncPercentTier = 90;
      onVideoLessonFinished(videoId, curTime, courseContext);
    } else if (pct >= 50 && lastSyncPercentTier < 50) {
      lastSyncPercentTier = 50;
      syncVideoProgress(videoId, pct, false, curTime);
    }
  });

  // 3. Pause & unload: immediate cookie & progress save
  videoEl.addEventListener('pause', () => {
    if (videoEl.currentTime > 1) {
      setBimCookie(cookieKey, Math.floor(videoEl.currentTime));
      try { localStorage.setItem(localKey, Math.floor(videoEl.currentTime)); } catch (e) {}
      syncVideoProgress(videoId, Math.round((videoEl.currentTime / (videoEl.duration || 1)) * 100), isLessonCompleted(videoId), videoEl.currentTime);
    }
  });

  // 4. Video ended: completion
  videoEl.addEventListener('ended', () => {
    onVideoLessonFinished(videoId, videoEl.duration || 0, courseContext);
  });
}

function onVideoLessonFinished(videoId, position = 0, courseContext = null) {
  syncVideoProgress(videoId, 100, true, position);

  if (currentClassroomCourse) {
    const syllabus = currentClassroomCourse.syllabus || [];
    const currentIdx = currentClassroomLessonIdx;
    const nextIdx = currentIdx + 1;

    renderClassroomPlaylist();
    updateClassroomNavButtons();

    if (nextIdx < syllabus.length) {
      const nextL = syllabus[nextIdx];
      showToast(`Lesson completed! Next video "${nextL.title}" is now unlocked!`, '#10b981');
    } else {
      showToast('Masterclass Completed! All videos in this curriculum track finished!', '#059669');
    }
  } else {
    showToast('Video completed! Progress recorded.', '#10b981');
  }
}

/* ── MY PROGRESS PAGE: CURRICULUM TRACKER & VIDEO CHECKLIST ── */
function playCourseVideo(courseId, idx) {
  const courses = getAllCourses();
  const course = courses.find(c => c.id === courseId);
  if (!course || !course.syllabus || !course.syllabus[idx]) return;

  const lesson = course.syllabus[idx];
  const playerContainer = document.getElementById('course-player-' + courseId);
  const titleEl = document.getElementById('course-current-title-' + courseId);

  if (titleEl) {
    titleEl.textContent = lesson.title;
  }

  let cleanUrl = (lesson.ytId || '').replace(/^\.?\/?/, '');
  const isYt = lesson.type === 'yt' || cleanUrl.includes('youtube.com') || cleanUrl.includes('youtu.be');

  if (playerContainer) {
    if (isYt) {
      const ytMatch = cleanUrl.match(/(?:youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=)([^#&?]*)/);
      const ytCode = ytMatch ? ytMatch[1] : cleanUrl;
      playerContainer.innerHTML = `<iframe src="https://www.youtube-nocookie.com/embed/${ytCode}?autoplay=1&rel=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="width:100%;height:310px;border-radius:8px;border:none;background:#000;display:block;"></iframe>`;
    } else {
      if (cleanUrl.startsWith('videos/')) {
        cleanUrl = 'api/stream.php?file=' + encodeURIComponent(cleanUrl);
      }
      playerContainer.innerHTML = `<video src="${cleanUrl}" controls autoplay playsinline style="width:100%;max-height:320px;border-radius:8px;background:#000;outline:none;display:block;"></video>`;
      const vidEl = playerContainer.querySelector('video');
      if (vidEl && typeof attachVideoTracking === 'function') {
        attachVideoTracking(vidEl, lesson.id, { course, index: idx });
      }
    }
  }

  // Highlight active playlist item
  document.querySelectorAll(`.course-lesson-item-${courseId}`).forEach((el, i) => {
    if (i === idx) {
      el.style.borderColor = '#2563eb';
      el.style.background = '#eff6ff';
    } else {
      el.style.borderColor = '#e2e8f0';
      el.style.background = '#ffffff';
    }
  });
}

function renderEmployeeProgressChecklist() {
  const container = document.getElementById('my-videos-checklist');
  if (!container) return;

  const courses = getAllCourses();
  const videos = window.SERVER_INITIAL_VIDEOS || [];
  const progressData = window.INITIAL_PROGRESS || {};
  const watchedMap = progressData.watched_videos || {};

  let html = '';

  // 1. CURRICULUM TRACKS PROGRESS SECTION
  if (courses.length > 0) {
    html += `
      <div style="margin-bottom:28px;">
        <div style="display:flex;flex-direction:column;gap:20px;">
          ${courses.map(course => {
            const syllabus = course.syllabus || [];
            
            return `
              <div class="glass-course-track" style="max-width:1000px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
                  <div style="font-size:17px;font-weight:800;color:#0f172a;">${course.title}</div>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-size:12px;color:#64748b;font-weight:600;">${syllabus.length} Video Lessons</span>
                    <button onclick="openClassroom('${course.id}')" style="background:#2563eb;color:#ffffff;border:none;padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 6px rgba(37,99,235,0.25);">
                      <span>▶ Open Player</span>
                    </button>
                  </div>
                </div>

                <!-- Video Lessons Collection (Side by Side Grid) -->
                <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(270px, 1fr));gap:16px;">
                  ${syllabus.map((lesson, idx) => {
                    const lId = lesson.id || ('les_' + idx);
                    const done = isLessonCompleted(lId);
                    const unlocked = isLessonUnlocked(course, idx);

                    let cleanUrl = (lesson.ytId || '').replace(/^\.?\/?/, '');
                    const isYt = lesson.type === 'yt' || cleanUrl.includes('youtube.com') || cleanUrl.includes('youtu.be');
                    let videoMediaHtml = '';

                    if (isYt) {
                      const ytMatch = cleanUrl.match(/(?:youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=)([^#&?]*)/);
                      const ytCode = ytMatch ? ytMatch[1] : cleanUrl;
                      videoMediaHtml = `<iframe src="https://www.youtube-nocookie.com/embed/${ytCode}?rel=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="width:100%;height:100%;border-radius:8px;border:none;background:#000;display:block;"></iframe>`;
                    } else if (cleanUrl) {
                      if (cleanUrl.startsWith('videos/')) {
                        cleanUrl = 'api/stream.php?file=' + encodeURIComponent(cleanUrl);
                      }
                      videoMediaHtml = `<video src="${cleanUrl}" controls playsinline preload="metadata" style="width:100%;height:100%;object-fit:contain;border-radius:8px;background:#000;outline:none;display:block;"></video>`;
                    }

                    let statusLabel = 'Locked';
                    let statusColor = '#94a3b8';

                    if (done) {
                      statusLabel = '✓ Completed';
                      statusColor = '#2563eb';
                    } else if (unlocked) {
                      statusLabel = 'Up next';
                      statusColor = '#2563eb';
                    }

                    return `
                      <div class="glass-video-tile" style="background:#ffffff;border:1px solid ${unlocked ? '#cbd5e1' : '#e2e8f0'};border-radius:10px;padding:12px;box-sizing:border-box;display:flex;flex-direction:column;justify-content:space-between;">
                        <div>
                          <div class="glass-video-frame" style="border-radius:8px;overflow:hidden;background:#000000;margin-bottom:10px;height:180px;">
                            ${videoMediaHtml}
                          </div>
                          <div style="font-size:13.5px;font-weight:700;color:#0f172a;line-height:1.35;margin-bottom:8px;">
                            ${lesson.title}
                          </div>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding-top:8px;border-top:1px solid #f1f5f9;">
                          <span style="font-size:11.5px;color:#64748b;font-weight:600;">${lesson.duration || '20:00'}</span>
                          <div style="display:flex;align-items:center;gap:8px;">
                            <span style="font-size:12px;font-weight:700;color:${statusColor};">${statusLabel}</span>
                            <button onclick="openClassroom('${course.id}', ${idx})" style="background:#eff6ff;border:1px solid #bfdbfe;color:#2563eb;padding:3px 8px;border-radius:6px;font-size:11.5px;font-weight:700;cursor:pointer;">
                              Player ↗
                            </button>
                          </div>
                        </div>
                      </div>
                    `;
                  }).join('')}
                </div>
              </div>
            `;
          }).join('')}
        </div>
      </div>
    `;
  }

  // 2. ASSIGNED VIDEOS CHECKLIST
  html += `
    <h4 style="font-size:16px;font-weight:800;color:#0f172a;margin:28px 0 14px;max-width:1000px;">All Assigned Training Videos</h4>
  `;

  if (videos.length === 0) {
    html += '<div style="color:#64748b;padding:20px;text-align:center;max-width:1000px;">No videos assigned to your station yet.</div>';
    container.innerHTML = html;
    return;
  }

  html += `
    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(270px, 1fr));gap:16px;max-width:1000px;">
      ${videos.map(v => {
        const record = watchedMap[v.id] || {};
        const isDone = !!record.completed;
        let cleanUrl = (v.ytId || '').replace(/^\.?\/?/, '');
        const isYt = v.type === 'yt' || cleanUrl.includes('youtube.com') || cleanUrl.includes('youtu.be');
        let videoMediaHtml = '';

        if (isYt) {
          const ytMatch = cleanUrl.match(/(?:youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=)([^#&?]*)/);
          const ytCode = ytMatch ? ytMatch[1] : cleanUrl;
          videoMediaHtml = `<iframe src="https://www.youtube-nocookie.com/embed/${ytCode}?rel=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="width:100%;height:100%;border-radius:8px;border:none;background:#000;display:block;"></iframe>`;
        } else if (cleanUrl) {
          if (cleanUrl.startsWith('videos/')) {
            cleanUrl = 'api/stream.php?file=' + encodeURIComponent(cleanUrl);
          }
          videoMediaHtml = `<video src="${cleanUrl}" controls playsinline preload="metadata" style="width:100%;height:100%;object-fit:contain;border-radius:8px;background:#000;outline:none;display:block;"></video>`;
        }

        return `
          <div style="background:#ffffff;border:1px solid ${isDone ? '#bfdbfe' : '#e2e8f0'};border-radius:10px;padding:12px;box-sizing:border-box;display:flex;flex-direction:column;justify-content:space-between;">
            <div>
              <div style="border-radius:8px;overflow:hidden;background:#000000;margin-bottom:10px;height:180px;">
                ${videoMediaHtml}
              </div>
              <div style="font-size:13.5px;font-weight:700;color:#0f172a;line-height:1.35;margin-bottom:8px;">${v.title}</div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding-top:8px;border-top:1px solid #f1f5f9;">
              <span style="font-size:12px;font-weight:700;color:#2563eb;">${isDone ? '✓ Completed' : 'In Progress'}</span>
            </div>
          </div>
        `;
      }).join('')}
    </div>
  `;

  container.innerHTML = html;
}

/* ── NAVIGATION ── */
function showSection(name) {
  if (name === 'home' || name === 'courses' || name === 'videos') {
    name = getRoleLandingSection();
  }
  // Strict role authorization:
  if ((name === 'analytics' || name === 'upload') && window.USER_ROLE !== 'admin') {
    name = getRoleLandingSection();
  }
  if (name === 'team-progress' && window.USER_ROLE !== 'team_leader' && window.USER_ROLE !== 'admin') {
    name = getRoleLandingSection();
  }

  ['videos','files','classroom','my-progress','team-progress','analytics','upload'].forEach(s => {
    const el = document.getElementById('section-' + s);
    if (el) {
      const isActive = s === name;
      el.style.display = isActive ? 'block' : 'none';
      if (isActive) {
        el.classList.remove('is-entering');
        // Restart the entrance animation only for the section being opened.
        requestAnimationFrame(() => el.classList.add('is-entering'));
      }
    }

    const btn = document.getElementById('nav-' + s);
    if (btn) {
      if (s === name) btn.classList.add('active');
      else btn.classList.remove('active');
    }
  });

  if (name === 'videos') renderVideosCategory('all_sims');
  if (name === 'files') renderPracticeFiles('all');
  if (name === 'my-progress') renderEmployeeProgressChecklist();
  if (name === 'upload') {
    renderAdminVideosList();
    populateCurriculumDropdown();
    renderAdminCurriculaList();
  }
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ── VIDEO PLAYER ── */
async function launchVideoPlayer(videoRef, encodedTitle, encodedDesc, type, videoId, station) {
  const title = decodeURIComponent(encodedTitle || '');
  const desc = decodeURIComponent(encodedDesc || '');

  // If this video is part of a curriculum track, verify prerequisites
  const curCtx = findVideoCurriculumContext(videoId);
  if (curCtx && !isLessonUnlocked(curCtx.course, curCtx.index)) {
    const prev = curCtx.course.syllabus[curCtx.index - 1];
    const prevTitle = prev ? prev.title : 'the prerequisite video';
    showToast(`Locked: This video is Part ${curCtx.index + 1} of "${curCtx.course.title}". You must finish "${prevTitle}" first!`, '#ef4444');
    return;
  }

  document.getElementById('vmodal-title').textContent = title;
  document.getElementById('vmodal-desc').textContent = desc;

  const stBadge = document.getElementById('vmodal-station-badge');
  if (stBadge) stBadge.textContent = 'STATION: ' + (station || 'ALL EMPLOYEES').toUpperCase();

  const playerContainer = document.getElementById('vmodal-player');
  playerContainer.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#94a3b8;background:#000;">Loading Video...</div>';
  openModal('video');

  const isLocal = type === 'local' || videoRef.includes('.mp4') || videoRef.includes('videos/');

  if (type === 'yt' || (!isLocal && videoRef.length === 11)) {
    playerContainer.innerHTML = `<iframe src="https://www.youtube.com/embed/${videoRef}?autoplay=1" allow="autoplay;encrypted-media;picture-in-picture" allowfullscreen style="width:100%;height:100%;border:none;border-radius:12px;"></iframe>`;
  } else {
    let playUrl = videoRef;
    if (playUrl.startsWith('videos/')) {
      playUrl = 'api/stream.php?file=' + encodeURIComponent(playUrl.replace(/^\.?\/?/, ''));
    }

    playerContainer.innerHTML = `
      <video id="active-bim-video" src="${playUrl}" controls autoplay playsinline preload="auto" style="width:100%;height:100%;background:#000000;border-radius:12px;outline:none;">
        Your browser does not support direct HTML5 video playback.
      </video>
    `;

    const vidEl = document.getElementById('active-bim-video');
    if (vidEl && videoId) {
      attachVideoTracking(vidEl, videoId, curCtx);
    }
  }
}

/* ── VIDEO RENDERER ── */
async function renderVideosCategory(category) {
  const container = document.getElementById('video-cards-grid');
  if (!container) return;

  let videos = await getAllVideos();
  if (category !== 'all_sims') {
    videos = videos.filter(v => v.cat === category);
  }

  const isAdmin = (typeof IS_STAFF_ADMIN !== 'undefined' && IS_STAFF_ADMIN);

  if (videos.length === 0) {
    container.innerHTML = `
      <div style="grid-column:1/-1;text-align:center;padding:54px 20px;background:#ffffff;border:1.5px dashed #cbd5e1;border-radius:20px;">
        <h4 style="font-size:18px;font-weight:900;color:#0f172a;margin:0 0 6px;">No Training Videos Uploaded Yet</h4>
        <p style="color:#64748b;font-size:13.5px;max-width:480px;margin:0 auto 18px;">All dummy records have been removed. The video catalog is ready for real company training videos.</p>
        ${isAdmin ? `<button onclick="showSection('upload')" style="background:#2563eb;color:white;border:none;padding:10px 22px;border-radius:10px;font-size:13px;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(37,99,235,0.25);">+ Upload First Video</button>` : ''}
      </div>
    `;
    return;
  }

  const progressData = window.INITIAL_PROGRESS || {};
  const watchedMap = progressData.watched_videos || {};

  container.innerHTML = videos.map(v => {
    const isLocal = v.type === 'local' || v.ytId.includes('videos/');
    const isDone = watchedMap[v.id]?.completed;
    const pct = watchedMap[v.id]?.percent || 0;
    const thumbHtml = `<div style="width:100%;height:100%;background:linear-gradient(135deg,#1d4ed8,#0284c7);display:flex;flex-direction:column;align-items:center;justify-content:center;color:white;"><span style="font-size:40px;"></span><span style="font-size:10px;font-weight:900;margin-top:4px;">HD VIDEO</span></div>`;

    return `
      <div class="dashboard-card" onclick="launchVideoPlayer('${v.ytId}','${encodeURIComponent(v.title)}','${encodeURIComponent(v.desc)}','${v.type || 'local'}','${v.id}','${v.station || 'all'}')" style="padding:20px;display:flex;flex-direction:column;justify-content:space-between;min-height:300px;">
        <div>
          <div class="video-thumb" style="height:160px;background:#0f172a;margin-bottom:14px;">
            ${thumbHtml}
            <div class="play-btn">
              <div class="play-icon" style="width:44px;height:44px;background:rgba(255,255,255,0.95);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 20px rgba(0,0,0,0.25);">
                <span style="font-size:16px;margin-left:3px;color:#2563eb;">▶</span>
              </div>
            </div>
            ${isDone ? `<span style="position:absolute;top:10px;right:10px;background:#2563eb;color:white;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:800;">WATCHED</span>` : ''}
          </div>

          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <span class="badge" style="background:#eff6ff;color:#1d4ed8;">${v.cat.toUpperCase()}</span>
            <span style="font-size:11.5px;color:#0284c7;font-weight:700;">Station: ${(v.station || 'all').toUpperCase()}</span>
          </div>
          <h4 style="font-size:15px;font-weight:800;color:#0f172a;margin:0 0 10px;line-height:1.35;">${v.title}</h4>
        </div>

        <div style="padding-top:12px;border-top:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;">
          <span style="color:#1d4ed8;font-size:13px;font-weight:700;">Watch Lesson →</span>
        </div>
      </div>
    `;
  }).join('');
}

function switchVideoCategory(cat, btn = null) {
  const sel = document.getElementById('video-type-select');
  if (sel && sel.value !== cat) sel.value = cat;
  renderVideosCategory(cat);
}

/* ── COURSES RENDERER ── */
function renderHomeFeatured() {
  const container = document.getElementById('home-featured-courses');
  if (!container) return;
  const courses = getAllCourses().slice(0, 3);
  const isAdmin = (typeof IS_STAFF_ADMIN !== 'undefined' && IS_STAFF_ADMIN);

  if (courses.length === 0) {
    container.innerHTML = `
      <div style="grid-column:1/-1;text-align:center;padding:42px 20px;background:#ffffff;border:1.5px dashed #cbd5e1;border-radius:20px;">
        <h4 style="font-size:16px;font-weight:900;color:#0f172a;margin:0 0 6px;">No Curricula Created Yet</h4>
        <p style="color:#64748b;font-size:13px;margin:0 auto 14px;max-width:440px;">Publish curriculum tracks with structured video lessons to train employees.</p>
        ${isAdmin ? `<button onclick="showSection('upload')" style="background:#2563eb;color:white;border:none;padding:9px 20px;border-radius:8px;font-size:12.5px;font-weight:700;cursor:pointer;">+ Create Curriculum</button>` : ''}
      </div>
    `;
    return;
  }

  container.innerHTML = courses.map(c => `
    <div class="dashboard-card" onclick="openClassroom('${c.id}')" style="padding:22px;display:flex;flex-direction:column;justify-content:space-between;min-height:200px;">
      <div>
        <div style="display:flex;align-items:center;justify-content:flex-end;margin-bottom:12px;">
          <span class="badge" style="background:#eff6ff;color:#1d4ed8;">${c.cat.toUpperCase()}</span>
        </div>
        <h3 style="font-size:16px;font-weight:800;color:#0f172a;margin:0 0 8px;">${c.title}</h3>
      </div>
      <div style="padding-top:12px;border-top:1px solid #f1f5f9;display:flex;justify-content:space-between;">
        <span style="color:#1d4ed8;font-size:13px;font-weight:700;">Start Curriculum →</span>
      </div>
    </div>
  `).join('');
}

function renderCoursesCatalog(filterCat) {
  const container = document.getElementById('catalog-courses-grid');
  if (!container) return;
  let list = getAllCourses();
  if (filterCat !== 'all') list = list.filter(c => c.cat === filterCat);
  const isAdmin = (typeof IS_STAFF_ADMIN !== 'undefined' && IS_STAFF_ADMIN);

  if (list.length === 0) {
    container.innerHTML = `
      <div style="grid-column:1/-1;text-align:center;padding:54px 20px;background:#ffffff;border:1.5px dashed #cbd5e1;border-radius:20px;">
        <h4 style="font-size:18px;font-weight:900;color:#0f172a;margin:0 0 6px;">Curricula Catalog is Empty</h4>
        <p style="color:#64748b;font-size:13.5px;max-width:480px;margin:0 auto 18px;">All dummy tracks have been removed. Use <strong>Upload Material</strong> to create real curriculum tracks and attach videos in sequential order.</p>
        ${isAdmin ? `<button onclick="showSection('upload')" style="background:#2563eb;color:white;border:none;padding:10px 22px;border-radius:10px;font-size:13px;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(37,99,235,0.25);">+ Create First Curriculum Track</button>` : ''}
      </div>
    `;
    return;
  }

  container.innerHTML = list.map(c => `
    <div class="dashboard-card" onclick="openClassroom('${c.id}')" style="padding:22px;display:flex;flex-direction:column;justify-content:space-between;min-height:200px;">
      <div>
        <div style="display:flex;align-items:center;justify-content:flex-end;margin-bottom:12px;">
          <span class="badge" style="background:#eff6ff;color:#1d4ed8;">${c.cat.toUpperCase()}</span>
        </div>
        <h3 style="font-size:16px;font-weight:800;color:#0f172a;margin:0 0 8px;">${c.title}</h3>
      </div>
      <div style="padding-top:12px;border-top:1px solid #f1f5f9;display:flex;justify-content:space-between;">
        <span style="color:#1d4ed8;font-size:13px;font-weight:700;">Start Curriculum →</span>
      </div>
    </div>
  `).join('');
}

function filterCoursesCatalog(cat) {
  ['all','coordination','revit','autocad','navisworks'].forEach(b => {
    const btn = document.getElementById('cfilter-' + b);
    if (btn) {
      btn.style.background = (b === cat) ? '#2563eb' : '#ffffff';
      btn.style.color = (b === cat) ? '#ffffff' : '#334155';
    }
  });
  renderCoursesCatalog(cat);
}

/* ── CLASSROOM & SEQUENTIAL CURRICULUM CONTROLLER ── */
let currentClassroomCourse = null;
let currentClassroomLessonIdx = 0;

function openClassroom(courseId, lessonIdx = null) {
  const courses = getAllCourses();
  if (courses.length === 0) {
    showToast('ℹ️ No curriculum tracks created yet. Go to Upload Material to create one.', '#2563eb');
    showSection('upload');
    return;
  }

  const course = courses.find(c => c.id === courseId) || courses[0];
  if (!course) return;

  currentClassroomCourse = course;
  document.getElementById('classroom-course-title').textContent = course.title;
  
  const catEl = document.getElementById('classroom-course-cat');
  if (catEl) catEl.textContent = (course.cat || 'BIM').toUpperCase();

  // If lessonIdx is not explicitly passed or is locked, find the first incomplete unlocked lesson
  const syllabus = course.syllabus || [];
  let targetIdx = 0;
  if (lessonIdx !== null && typeof lessonIdx === 'number' && isLessonUnlocked(course, lessonIdx)) {
    targetIdx = lessonIdx;
  } else {
    // Find first unlocked incomplete lesson
    for (let i = 0; i < syllabus.length; i++) {
      const lId = syllabus[i].id || ('les_' + i);
      if (isLessonUnlocked(course, i) && !isLessonCompleted(lId)) {
        targetIdx = i;
        break;
      }
    }
  }

  showSection('classroom');
  loadClassroomLesson(targetIdx);
}

function renderClassroomSuggestedCourses() {
  const container = document.getElementById('classroom-suggested-courses');
  if (!container) return;
  const all = getAllCourses();
  const others = all.filter(c => !currentClassroomCourse || c.id !== currentClassroomCourse.id);
  if (others.length === 0) {
    container.innerHTML = '<div style="font-size:12px;color:#94a3b8;padding:8px 0;">No other curricula available.</div>';
    return;
  }
  container.innerHTML = others.slice(0, 3).map(c => {
    const lessonCount = (c.syllabus || []).length;
    return `
      <div onclick="openClassroom('${c.id}')" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px;display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;transition:all 0.15s;" onmouseover="this.style.borderColor='#2563eb'" onmouseout="this.style.borderColor='#e2e8f0'">
        <div>
          <div style="font-size:13px;font-weight:700;color:#0f172a;line-height:1.3;">${c.title}</div>
          <div style="font-size:11.5px;color:#64748b;margin-top:2px;">${lessonCount} lesson${lessonCount === 1 ? '' : 's'} • ${(c.cat || 'BIM').toUpperCase()}</div>
        </div>
        <span style="color:#2563eb;font-weight:800;font-size:12px;white-space:nowrap;">Start →</span>
      </div>
    `;
  }).join('');
}

function renderClassroomPlaylist() {
  const container = document.getElementById('classroom-playlist-items');
  if (!container || !currentClassroomCourse) return;
  const lessons = currentClassroomCourse.syllabus || [];

  const stats = getCurriculumProgressStats(currentClassroomCourse);
  const countEl = document.getElementById('classroom-playlist-count');
  if (countEl) countEl.textContent = `${stats.completedCount} / ${stats.total}`;

  const barEl = document.getElementById('classroom-progress-bar');
  if (barEl) barEl.style.width = stats.percent + '%';

  const textEl = document.getElementById('classroom-progress-text');
  if (textEl) textEl.textContent = `${stats.percent}% Completed (${stats.completedCount} of ${stats.total})`;

  container.innerHTML = lessons.map((l, idx) => {
    const lId = l.id || ('les_' + idx);
    const isCurrent = (idx === currentClassroomLessonIdx);
    const isDone = isLessonCompleted(lId);
    const isUnlocked = isLessonUnlocked(currentClassroomCourse, idx);

    let border = '#e2e8f0';
    let bg = '#ffffff';
    let badgeText = isUnlocked ? 'Unlocked' : `Locked`;
    let badgeBg = '#f1f5f9';
    let badgeColor = '#64748b';

    if (isDone) {
      bg = isCurrent ? '#eff6ff' : '#f8fafc';
      border = isCurrent ? '#2563eb' : '#cbd5e1';
      badgeText = '✓ Completed';
      badgeBg = '#eff6ff';
      badgeColor = '#2563eb';
    } else if (isUnlocked) {
      bg = isCurrent ? '#eff6ff' : '#ffffff';
      border = isCurrent ? '#2563eb' : '#e2e8f0';
      badgeText = isCurrent ? 'Playing Now' : 'Up Next';
      badgeBg = isCurrent ? '#dbeafe' : '#f8fafc';
      badgeColor = '#2563eb';
    }

    let cleanUrl = (l.ytId || '').replace(/^\.?\/?/, '');
    const isYt = l.type === 'yt' || cleanUrl.includes('youtube.com') || cleanUrl.includes('youtu.be');
    let thumbHtml = '';
    if (isYt) {
      const ytMatch = cleanUrl.match(/(?:youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=)([^#&?]*)/);
      const ytCode = ytMatch ? ytMatch[1] : cleanUrl;
      thumbHtml = `<img src="https://img.youtube.com/vi/${ytCode}/mqdefault.jpg" style="width:100%;height:100%;object-fit:cover;display:block;" onerror="this.onerror=null;this.parentElement.innerHTML='<div style=\'width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#0f172a;color:#93c5fd;font-size:14px;\'>▶</div>'" />`;
    } else {
      thumbHtml = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#0f172a;color:#ffffff;font-size:13px;font-weight:700;"><span style="color:#2563eb;">▶</span></div>`;
    }

    return `
      <div onclick="loadClassroomLesson(${idx})" style="padding:10px 12px;border-radius:12px;background:${bg};border:1.5px solid ${border};cursor:${isUnlocked ? 'pointer' : 'not-allowed'};display:flex;align-items:center;gap:12px;transition:all 0.15s;opacity:${isUnlocked ? '1' : '0.65'};box-shadow:${isCurrent ? '0 2px 8px rgba(37,99,235,0.12)' : 'none'};">
        <!-- Thumbnail -->
        <div style="width:68px;height:46px;border-radius:8px;overflow:hidden;flex-shrink:0;background:#0f172a;position:relative;">
          ${thumbHtml}
        </div>

        <!-- Info -->
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;margin-bottom:3px;">
            <span style="font-size:11.5px;font-weight:700;color:${isCurrent ? '#2563eb' : '#64748b'};">${l.duration || '20:00'}</span>
            <span style="font-size:10.5px;font-weight:700;padding:1px 6px;border-radius:4px;background:${badgeBg};color:${badgeColor};">${badgeText}</span>
          </div>
          <div style="font-size:13px;font-weight:${isCurrent ? '800' : '700'};color:${isCurrent ? '#2563eb' : '#0f172a'};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            ${l.title}
          </div>
        </div>
      </div>
    `;
  }).join('');

  renderClassroomSuggestedCourses();
}

function loadClassroomLesson(idx) {
  if (!currentClassroomCourse) return;
  const syllabus = currentClassroomCourse.syllabus || [];
  const l = syllabus[idx];
  if (!l) return;

  const unlocked = isLessonUnlocked(currentClassroomCourse, idx);
  if (!unlocked) {
    const prev = syllabus[idx - 1];
    const prevTitle = prev ? prev.title : 'the previous lesson';
    showToast(`Lesson Locked! You must finish "${prevTitle}" before opening this video.`, '#ef4444');
    return;
  }

  currentClassroomLessonIdx = idx;
  document.getElementById('classroom-lesson-title').textContent = l.title;

  const badgeSoftware = document.getElementById('classroom-badge-software');
  if (badgeSoftware) {
    badgeSoftware.textContent = (currentClassroomCourse.cat || 'Revit').toUpperCase();
  }

  const resumeBadge = document.getElementById('classroom-lesson-resume-badge');
  if (resumeBadge) resumeBadge.style.display = 'none';

  const player = document.getElementById('classroom-player');
  let cleanUrl = (l.ytId || '').replace(/^\.?\/?/, '');
  const isYt = l.type === 'yt' || cleanUrl.includes('youtube.com') || cleanUrl.includes('youtu.be');

  if (isYt) {
    const ytMatch = cleanUrl.match(/(?:youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=)([^#&?]*)/);
    const ytCode = ytMatch ? ytMatch[1] : cleanUrl;
    player.innerHTML = `<iframe id="classroom-yt-frame" src="https://www.youtube-nocookie.com/embed/${ytCode}?autoplay=1&rel=0&enablejsapi=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="width:100%;height:100%;border:none;background:#000;display:block;"></iframe>`;
  } else if (cleanUrl) {
    if (cleanUrl.startsWith('videos/')) {
      cleanUrl = 'api/stream.php?file=' + encodeURIComponent(cleanUrl);
    }
    player.innerHTML = `<video id="classroom-vid-el" src="${cleanUrl}" controls autoplay playsinline style="width:100%;height:100%;background:#000;outline:none;display:block;object-fit:contain;"></video>`;
    const vidEl = document.getElementById('classroom-vid-el');
    const lId = l.id || ('les_' + idx);
    if (vidEl) {
      attachVideoTracking(vidEl, lId, { course: currentClassroomCourse, index: idx });
    }
  }

  updateClassroomNavButtons();
  renderClassroomPlaylist();
}

function updateClassroomNavButtons() {
  if (!currentClassroomCourse) return;
  const syllabus = currentClassroomCourse.syllabus || [];
  const curIdx = currentClassroomLessonIdx;
  const total = syllabus.length;

  const prevBtn = document.getElementById('classroom-prev-btn');
  const nextBtn = document.getElementById('classroom-next-btn');
  const stepInd = document.getElementById('classroom-step-indicator');
  const completeBtn = document.getElementById('classroom-complete-btn');

  if (stepInd) {
    stepInd.textContent = `Lesson ${curIdx + 1} of ${total}`;
  }

  if (prevBtn) {
    prevBtn.style.display = curIdx > 0 ? 'inline-flex' : 'none';
  }

  if (nextBtn) {
    const nextIdx = curIdx + 1;
    if (nextIdx >= total) {
      nextBtn.style.display = 'none';
    } else {
      nextBtn.style.display = 'inline-flex';
      const isNextUnlocked = isLessonUnlocked(currentClassroomCourse, nextIdx);
      if (isNextUnlocked) {
        nextBtn.style.background = '#2563eb';
        nextBtn.style.cursor = 'pointer';
        nextBtn.innerHTML = '<span>Next Lesson</span> →';
      } else {
        nextBtn.style.background = '#94a3b8';
        nextBtn.style.cursor = 'not-allowed';
        nextBtn.innerHTML = '<span>Next Lesson Locked</span>';
      }
    }
  }

  if (completeBtn && syllabus[curIdx]) {
    const lId = syllabus[curIdx].id || ('les_' + curIdx);
    const done = isLessonCompleted(lId);
    if (done) {
      completeBtn.style.background = '#ecfdf5';
      completeBtn.style.borderColor = '#a7f3d0';
      completeBtn.style.color = '#059669';
      completeBtn.innerHTML = 'Completed';
    } else {
      completeBtn.style.background = '#ffffff';
      completeBtn.style.borderColor = '#cbd5e1';
      completeBtn.style.color = '#334155';
      completeBtn.innerHTML = '<span>○</span> Mark as Completed';
    }
  }
}

function goToPrevClassroomLesson() {
  if (!currentClassroomCourse || currentClassroomLessonIdx <= 0) return;
  loadClassroomLesson(currentClassroomLessonIdx - 1);
}

function goToNextClassroomLesson() {
  if (!currentClassroomCourse) return;
  const nextIdx = currentClassroomLessonIdx + 1;
  const syllabus = currentClassroomCourse.syllabus || [];
  if (nextIdx >= syllabus.length) {
    showToast('You have reached the end of this curriculum!', '#10b981');
    return;
  }
  loadClassroomLesson(nextIdx);
}

function toggleCurrentLessonCompletion() {
  if (!currentClassroomCourse) return;
  const syllabus = currentClassroomCourse.syllabus || [];
  const l = syllabus[currentClassroomLessonIdx];
  if (!l) return;
  const lId = l.id || ('les_' + currentClassroomLessonIdx);
  const isDone = isLessonCompleted(lId);

  if (!isDone) {
    syncVideoProgress(lId, 100, true, 0);
    onVideoLessonFinished(lId, 0, { course: currentClassroomCourse, index: currentClassroomLessonIdx });
  } else {
    syncVideoProgress(lId, 0, false, 0);
    renderClassroomPlaylist();
    updateClassroomNavButtons();
    showToast('Lesson marked as not completed.', '#64748b');
  }
}

/* ── PRACTICE FILES ── */
function renderPracticeFiles(filterCat = 'all') {
  const container = document.getElementById('practice-files-grid');
  if (!container) return;
  const list = getAllPracticeFiles();
  const isAdmin = (typeof IS_STAFF_ADMIN !== 'undefined' && IS_STAFF_ADMIN);
  container.innerHTML = list.map(f => `
    <div class="dashboard-card" style="padding:20px;display:flex;flex-direction:column;justify-content:space-between;min-height:160px;">
      <div>
        <div style="display:flex;justify-content:flex-end;align-items:center;margin-bottom:10px;">
          <span class="badge" style="background:#eff6ff;color:#1d4ed8;">${f.type}</span>
        </div>
        <h4 style="font-size:14px;font-weight:800;color:#0f172a;margin:0 0 4px;">${f.name}</h4>
      </div>
      <button onclick="showToast('Downloading ${f.name}...', '#2563eb')" style="background:linear-gradient(135deg,#1d4ed8,#0284c7);color:white;border:none;padding:8px;border-radius:8px;font-size:12.5px;font-weight:700;cursor:pointer;margin-top:12px;">
        ⤓ Download Dataset
      </button>
    </div>
  `).join('');
}

function filterPracticeFiles(cat) { renderPracticeFiles(cat); }

/* ── ADMIN PUBLISHING, CURRICULA & STATION TARGETING ── */
let currentVideoUploadMode = 'file';
let selectedAdminVideoFiles = [];
let selectedUploadStations = ['all'];
let currentVideoCurriculumMode = 'none';
let currentRosterStation = 'all';
let currentRosterQuery = '';

function switchAdminUploadTab(tab) {
  const tabs = ['video', 'course', 'files'];
  tabs.forEach(t => {
    const panel = document.getElementById('admin-panel-' + t);
    const btn = document.getElementById('atab-' + t);
    if (panel) panel.style.display = (t === tab) ? 'block' : 'none';
    if (btn) {
      if (t === tab) {
        btn.style.background = '#2563eb';
        btn.style.color = '#ffffff';
        btn.style.border = 'none';
        btn.style.fontWeight = '700';
      } else {
        btn.style.background = '#f8fafc';
        btn.style.color = '#475569';
        btn.style.border = '1px solid #cbd5e1';
        btn.style.fontWeight = '600';
      }
    }
  });

  if (tab === 'course') {
    renderAdminCurriculaList();
  }
}

function toggleVideoCurriculumMode(mode) {
  currentVideoCurriculumMode = mode;
  ['existing', 'new', 'none'].forEach(m => {
    const container = document.getElementById('curriculum-container-' + m);
    const lbl = document.getElementById('copt-label-' + m);
    if (container) container.style.display = (m === mode) ? 'block' : 'none';
    if (lbl) {
      if (m === mode) {
        lbl.style.background = '#eff6ff';
        lbl.style.borderColor = '#2563eb';
        lbl.style.borderWidth = '2px';
        lbl.style.color = '#1d4ed8';
      } else {
        lbl.style.background = '#ffffff';
        lbl.style.borderColor = '#cbd5e1';
        lbl.style.borderWidth = '1.5px';
        lbl.style.color = '#334155';
      }
    }
  });
}

function populateCurriculumDropdown(selectedId = null) {
  const select = document.getElementById('new-video-curriculum-id');
  if (!select) return;

  const courses = getAllAdminCourses();
  if (courses.length === 0) {
    select.innerHTML = '<option value="">No curricula available (select "Create New" above)</option>';
    return;
  }

  select.innerHTML = courses.map(c => {
    const lessonCount = Array.isArray(c.syllabus) ? c.syllabus.length : 0;
    const stationLabel = (c.station && c.station !== 'all') ? c.station.toUpperCase() : 'ALL';
    const isSel = (selectedId && c.id === selectedId) ? 'selected' : '';
    return `<option value="${c.id}" ${isSel}>${c.icon || ''} ${c.title} (${lessonCount} lesson${lessonCount === 1 ? '' : 's'}) [Target: ${stationLabel}]</option>`;
  }).join('');

  if (selectedId) select.value = selectedId;
  updateSelectedCurriculumInfo(select.value);
}

function updateSelectedCurriculumInfo(courseId) {
  const infoEl = document.getElementById('curriculum-existing-info');
  if (!infoEl) return;
  const courses = getAllAdminCourses();
  const found = courses.find(c => c.id === courseId);
  if (found) {
    const lessonCount = Array.isArray(found.syllabus) ? found.syllabus.length : 0;
    infoEl.innerHTML = `Track: <strong>${found.title}</strong> • Contains <strong>${lessonCount} lesson(s)</strong> • Target: <strong>${(found.station || 'all').toUpperCase()}</strong>`;
  } else {
    infoEl.innerHTML = '';
  }
}

function handleAdminStationSelectionChange(stationVal) {
  const inlineStation = document.getElementById('new-course-station');
  if (inlineStation) inlineStation.value = stationVal;
}

function renderAdminCurriculaList() {
  const container = document.getElementById('admin-curricula-list');
  const countEl = document.getElementById('admin-curricula-count');
  if (!container) return;

  const courses = getAllAdminCourses();
  if (countEl) countEl.textContent = courses.length;

  if (courses.length === 0) {
    container.innerHTML = '<div style="color:#64748b;padding:24px;text-align:center;background:#f8fafc;border-radius:12px;">No curricula published yet. Use the form above to create your first curriculum!</div>';
    return;
  }

  container.innerHTML = courses.map(c => {
    const syllabus = Array.isArray(c.syllabus) ? c.syllabus : [];
    const stationBadge = (c.station && c.station !== 'all') 
      ? `<span class="badge" style="background:#eff6ff;color:#1d4ed8;">Station: ${c.station.toUpperCase()}</span>` 
      : `<span class="badge" style="background:#f1f5f9;color:#334155;">ALL EMPLOYEES</span>`;

    const lessonsHtml = syllabus.length > 0 
      ? syllabus.map((l, idx) => `
        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
          <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-weight:800;color:#2563eb;font-size:12px;">#${idx + 1}</span>
            <span style="font-weight:700;color:#0f172a;">${l.title}</span>
          </div>
        </div>
      `).join('')
      : '<div style="font-size:12px;color:#94a3b8;font-style:italic;padding:8px 0;">No lessons attached yet. Upload a video and assign it to this curriculum!</div>';

    const hasCurriculumIcon = c.icon && !['️', '', '\ud83c\udfd7\ufe0f', '\ud83c\udfd7'].includes(c.icon);
    const curriculumIconMarkup = hasCurriculumIcon ? `
      <div style="width:44px;height:44px;border-radius:12px;background:#eff6ff;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-size:22px;">
        ${c.icon}
      </div>
    ` : '';

    return `
      <div style="background:#ffffff;border:1.5px solid #e2e8f0;border-radius:16px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.03);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
          <div style="display:flex;align-items:center;gap:12px;">
            ${curriculumIconMarkup}
            <div>
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <h4 style="font-size:16px;font-weight:900;color:#0f172a;margin:0;">${c.title}</h4>
                ${stationBadge}
              </div>
              <div style="font-size:12px;color:#64748b;margin-top:3px;">
                Category: <strong>${(c.cat || 'BIM').toUpperCase()}</strong> • Total Duration: <strong>${c.duration || 'Flexible'}</strong> • <strong>${syllabus.length} Lesson${syllabus.length === 1 ? '' : 's'}</strong>
              </div>
            </div>
          </div>

          <div style="display:flex;gap:8px;">
            <button onclick="openClassroom('${c.id}')" style="background:#2563eb;color:white;border:none;padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:700;cursor:pointer;">
              ▶ Open Classroom
            </button>
            <button onclick="prefillUploadWithCurriculum('${c.id}')" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:700;cursor:pointer;">
              Add Video Here
            </button>
          </div>
        </div>

        <div style="background:#f8fafc;border-radius:12px;padding:12px 14px;">
          <div style="font-size:11.5px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px;">
            Attached Syllabus Lessons (${syllabus.length}):
          </div>
          <div style="display:flex;flex-direction:column;gap:6px;">
            ${lessonsHtml}
          </div>
        </div>
      </div>
    `;
  }).join('');
}

function prefillUploadWithCurriculum(courseId) {
  switchAdminUploadTab('video');
  toggleVideoCurriculumMode('existing');
  const sel = document.getElementById('new-video-curriculum-id');
  if (sel) {
    sel.value = courseId;
    updateSelectedCurriculumInfo(courseId);
  }
  const rad = document.querySelector('input[name="video_curriculum_choice"][value="existing"]');
  if (rad) rad.checked = true;
  document.getElementById('new-video-title').focus();
  showToast('Target curriculum selected! Choose video file to upload.', '#2563eb');
}

async function adminPublishCourse() {
  const title = document.getElementById('new-course-title').value.trim();
  const cat = document.getElementById('new-course-cat').value;
  const station = document.getElementById('new-course-station').value;
  const level = document.getElementById('new-course-level').value;
  const icon = document.getElementById('new-course-icon').value;
  const duration = document.getElementById('new-course-duration').value.trim() || '15 hrs';
  const desc = document.getElementById('new-course-desc').value.trim() || 'Comprehensive BIM curriculum track.';

  if (!title) {
    showToast('️ Curriculum Title is required', '#f59e0b');
    return;
  }

  showToast('⏳ Creating curriculum track...', '#2563eb');

  try {
    const res = await fetch('api/courses.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'create',
        title,
        cat,
        station,
        level,
        icon,
        duration,
        desc
      })
    });

    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Failed to create curriculum');

    if (!window.ALL_ADMIN_COURSES) window.ALL_ADMIN_COURSES = [];
    window.ALL_ADMIN_COURSES.unshift(json.curriculum);
    if (!window.SERVER_INITIAL_COURSES) window.SERVER_INITIAL_COURSES = [];
    window.SERVER_INITIAL_COURSES.unshift(json.curriculum);

    document.getElementById('new-course-title').value = '';
    document.getElementById('new-course-duration').value = '';
    document.getElementById('new-course-desc').value = '';

    populateCurriculumDropdown(json.curriculum.id);
    renderAdminCurriculaList();
    renderCoursesCatalog('all');

    showToast(`Curriculum "${title}" created successfully!`, '#10b981');

  } catch (err) {
    console.error(err);
    showToast('Failed: ' + err.message, '#ef4444');
  }
}

function setVideoUploadMode(m) {
  currentVideoUploadMode = m;
  ['file','yt','folder'].forEach(mode => {
    const el = document.getElementById('vinput-container-' + mode);
    if (el) el.style.display = (mode === m) ? 'block' : 'none';
    const btn = document.getElementById('vmode-btn-' + mode);
    if (btn) {
      btn.style.background = (mode === m) ? '#2563eb' : 'transparent';
      btn.style.color = (mode === m) ? '#ffffff' : '#475569';
    }
  });
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

let selectedIndividualEmployeeIds = [];

function toggleIndividualPicker() {
  const panel = document.getElementById('individual-employee-picker-panel');
  if (!panel) return;
  const isHidden = (panel.style.display === 'none' || panel.style.display === '');
  if (isHidden) {
    panel.style.display = 'block';
    initIndividualPickerUI();
  } else {
    panel.style.display = 'none';
  }
}

function closeIndividualPicker() {
  const panel = document.getElementById('individual-employee-picker-panel');
  if (panel) panel.style.display = 'none';
}

function initIndividualPickerUI() {
  const teamSelect = document.getElementById('indiv-filter-team');
  if (teamSelect && teamSelect.options.length <= 1) {
    const teams = window.ALL_AVAILABLE_TEAMS || [];
    teams.forEach(t => {
      const opt = document.createElement('option');
      opt.value = t;
      opt.textContent = '' + t;
      teamSelect.appendChild(opt);
    });
  }
  renderIndividualEmployeeList();
}

function onIndivTeamFilterChange() {
  renderIndividualEmployeeList();
}

function getFilteredAssignableEmployees() {
  const employees = window.ALL_ASSIGNABLE_EMPLOYEES || [];
  const stationFilter = document.getElementById('indiv-filter-station')?.value || 'all';
  const teamFilter = document.getElementById('indiv-filter-team')?.value || 'all';
  const search = (document.getElementById('indiv-search-name')?.value || '').trim().toLowerCase();

  return employees.filter(emp => {
    // Station filter
    if (stationFilter !== 'all') {
      const empStation = (emp.station || 'all').toLowerCase();
      if (empStation !== stationFilter.toLowerCase() && empStation !== 'all') {
        return false;
      }
    }
    // Team filter
    if (teamFilter !== 'all') {
      const empTeam = (emp.team_name || '').toLowerCase();
      if (empTeam !== teamFilter.toLowerCase()) {
        return false;
      }
    }
    // Search query
    if (search) {
      const empName = (emp.name || '').toLowerCase();
      const empTeam = (emp.team_name || '').toLowerCase();
      if (!empName.includes(search) && !empTeam.includes(search)) {
        return false;
      }
    }
    return true;
  });
}

function renderIndividualEmployeeList() {
  const container = document.getElementById('indiv-employee-card-list');
  if (!container) return;

  const filtered = getFilteredAssignableEmployees();

  if (filtered.length === 0) {
    container.innerHTML = `
      <div style="grid-column:1/-1;text-align:center;padding:24px;color:#64748b;font-size:13px;background:white;border-radius:10px;border:1px dashed #cbd5e1;">
        No employees match the current filter or search criteria.
      </div>
    `;
    updateIndividualAssignmentUI();
    return;
  }

  const stationIcons = {
    trainee: 'Trainee',
    engineer: 'Engineer',
    architect: 'Architect',
    modeler: 'Modeler',
    all: 'General'
  };

  container.innerHTML = filtered.map(emp => {
    const isSelected = selectedIndividualEmployeeIds.includes(String(emp.id));
    const empStationLabel = stationIcons[emp.station] || (emp.station ? (emp.station.charAt(0).toUpperCase() + emp.station.slice(1)) : 'General');
    const borderCol = isSelected ? '#2563eb' : '#e2e8f0';
    const bgCol = isSelected ? '#eff6ff' : '#ffffff';

    return `
      <div onclick="toggleIndividualEmployeeSelection('${emp.id}')"
           style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:9px 12px;border:1.5px solid ${borderCol};background:${bgCol};border-radius:10px;transition:all 0.15s;user-select:none;"
           onmouseover="this.style.borderColor='#2563eb'"
           onmouseout="this.style.borderColor='${borderCol}'">
        <input type="checkbox"
               ${isSelected ? 'checked' : ''}
               onclick="event.stopPropagation();toggleIndividualEmployeeSelection('${emp.id}')"
               style="cursor:pointer;accent-color:#2563eb;width:16px;height:16px;margin:0;flex-shrink:0;">
        <div style="min-width:0;flex:1;">
          <div style="font-size:13px;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            ${escapeHtml(emp.name)}
          </div>
          <div style="display:flex;align-items:center;gap:6px;margin-top:2px;font-size:11px;flex-wrap:wrap;">
            <span style="background:#eff6ff;color:#1d4ed8;padding:1px 6px;border-radius:4px;font-weight:600;">
              ${escapeHtml(emp.team_name || 'General Team')}
            </span>
            <span style="background:#f1f5f9;color:#475569;padding:1px 6px;border-radius:4px;font-weight:600;">
              ${empStationLabel}
            </span>
          </div>
        </div>
      </div>
    `;
  }).join('');

  updateIndividualAssignmentUI();
}

function toggleIndividualEmployeeSelection(empId) {
  const idStr = String(empId);
  if (selectedIndividualEmployeeIds.includes(idStr)) {
    selectedIndividualEmployeeIds = selectedIndividualEmployeeIds.filter(id => id !== idStr);
  } else {
    selectedIndividualEmployeeIds.push(idStr);
  }
  syncIndividualSelectionWithUploadState();
  renderIndividualEmployeeList();
}

function selectCurrentTeamEmployees() {
  const filtered = getFilteredAssignableEmployees();
  filtered.forEach(emp => {
    const idStr = String(emp.id);
    if (!selectedIndividualEmployeeIds.includes(idStr)) {
      selectedIndividualEmployeeIds.push(idStr);
    }
  });
  syncIndividualSelectionWithUploadState();
  renderIndividualEmployeeList();
}

function deselectCurrentTeamEmployees() {
  const filtered = getFilteredAssignableEmployees();
  const removeIds = new Set(filtered.map(e => String(e.id)));
  selectedIndividualEmployeeIds = selectedIndividualEmployeeIds.filter(id => !removeIds.has(id));
  syncIndividualSelectionWithUploadState();
  renderIndividualEmployeeList();
}

function selectAllFilteredEmployees() {
  selectCurrentTeamEmployees();
}

function clearAllIndividualEmployees() {
  selectedIndividualEmployeeIds = [];
  syncIndividualSelectionWithUploadState();
  renderIndividualEmployeeList();
}

function removeIndividualEmployee(empId) {
  selectedIndividualEmployeeIds = selectedIndividualEmployeeIds.filter(id => id !== String(empId));
  syncIndividualSelectionWithUploadState();
  renderIndividualEmployeeList();
}

function syncIndividualSelectionWithUploadState() {
  const hiddenStation = document.getElementById('new-video-station');
  const hiddenAssigned = document.getElementById('new-video-assigned-employees');
  const btnIndiv = document.getElementById('btn-choose-individually');
  const badge = document.getElementById('indiv-count-badge');
  const hint = document.getElementById('station-assignment-hint');

  const count = selectedIndividualEmployeeIds.length;

  if (hiddenAssigned) {
    hiddenAssigned.value = count > 0 ? JSON.stringify(selectedIndividualEmployeeIds) : '';
  }

  if (count > 0) {
    if (hiddenStation) hiddenStation.value = 'custom';
    selectedUploadStations = ['custom'];

    const stationButtons = document.querySelectorAll('#station-picker-group .st-pick-btn:not(#btn-choose-individually)');
    stationButtons.forEach(b => {
      b.style.background = '#ffffff';
      b.style.color = '#334155';
      b.style.border = '1.5px solid #cbd5e1';
      b.style.boxShadow = 'none';
      b.classList.remove('active');
    });

    if (btnIndiv) {
      btnIndiv.style.background = '#2563eb';
      btnIndiv.style.color = '#ffffff';
      btnIndiv.style.border = 'none';
      btnIndiv.style.boxShadow = 'none';
      btnIndiv.classList.add('active');
    }
    if (badge) {
      badge.textContent = count;
      badge.style.display = 'inline-block';
      badge.style.background = '#ffffff';
      badge.style.color = '#2563eb';
    }

    if (hint) {
      hint.innerHTML = `Assigned individually to: <strong style="color:#2563eb;">${count} Selected Employee${count > 1 ? 's' : ''}</strong> (Custom Assignment)`;
    }
  } else {
    if (hiddenStation && hiddenStation.value === 'custom') {
      hiddenStation.value = 'all';
      selectedUploadStations = ['all'];
    }

    if (btnIndiv) {
      btnIndiv.style.background = '#ffffff';
      btnIndiv.style.color = '#0f172a';
      btnIndiv.style.border = '1.5px solid #cbd5e1';
      btnIndiv.style.boxShadow = 'none';
      btnIndiv.classList.remove('active');
    }
    if (badge) {
      badge.style.display = 'none';
    }

    const buttons = document.querySelectorAll('#station-picker-group .st-pick-btn:not(#btn-choose-individually)');
    buttons.forEach(b => {
      const bSt = b.getAttribute('data-st');
      const isAct = selectedUploadStations.includes(bSt);
      if (isAct) {
        b.style.background = '#2563eb';
        b.style.color = '#ffffff';
        b.style.border = 'none';
        b.style.boxShadow = '0 2px 6px rgba(37,99,235,0.25)';
      } else {
        b.style.background = '#ffffff';
        b.style.color = '#334155';
        b.style.border = '1.5px solid #cbd5e1';
        b.style.boxShadow = 'none';
      }
    });

    if (hint && selectedUploadStations.includes('all')) {
      hint.innerHTML = 'Assigned to: <strong>All Employees (General Curriculum)</strong>';
    }
  }
}

function updateIndividualAssignmentUI() {
  const countText = document.getElementById('indiv-selection-count-text');
  const count = selectedIndividualEmployeeIds.length;
  if (countText) {
    countText.textContent = `${count} employee${count !== 1 ? 's' : ''} selected`;
  }

  const chipsBar = document.getElementById('indiv-selected-chips-bar');
  if (chipsBar) {
    if (count === 0) {
      chipsBar.style.display = 'none';
      chipsBar.innerHTML = '';
    } else {
      chipsBar.style.display = 'flex';
      const allEmps = window.ALL_ASSIGNABLE_EMPLOYEES || [];
      const empMap = {};
      allEmps.forEach(e => { empMap[String(e.id)] = e; });

      chipsBar.innerHTML = selectedIndividualEmployeeIds.map(id => {
        const emp = empMap[id];
        const name = emp ? emp.name : ('Employee #' + id);
        return `
          <span style="display:inline-flex;align-items:center;gap:4px;background:#e0e7ff;color:#3730a3;padding:3px 8px;border-radius:999px;font-size:11.5px;font-weight:700;">
            ${escapeHtml(name)}
            <button type="button" onclick="removeIndividualEmployee('${id}')" style="background:none;border:none;cursor:pointer;color:#4f46e5;font-weight:900;padding:0 2px;line-height:1;" title="Remove"></button>
          </span>
        `;
      }).join('');
    }
  }
}

function toggleUploadStation(st, btn) {
  // Clear individual employee selection when a standard station is chosen
  selectedIndividualEmployeeIds = [];
  const hiddenAssigned = document.getElementById('new-video-assigned-employees');
  if (hiddenAssigned) hiddenAssigned.value = '';
  const btnIndiv = document.getElementById('btn-choose-individually');
  if (btnIndiv) {
    btnIndiv.style.background = '#ffffff';
    btnIndiv.style.color = '#0f172a';
    btnIndiv.style.border = '1.5px solid #cbd5e1';
    btnIndiv.style.boxShadow = 'none';
    btnIndiv.classList.remove('active');
  }
  const badge = document.getElementById('indiv-count-badge');
  if (badge) badge.style.display = 'none';
  updateIndividualAssignmentUI();

  if (st === 'all') {
    selectedUploadStations = ['all'];
  } else {
    selectedUploadStations = selectedUploadStations.filter(s => s !== 'all' && s !== 'custom');
    if (selectedUploadStations.includes(st)) {
      selectedUploadStations = selectedUploadStations.filter(s => s !== st);
      if (selectedUploadStations.length === 0) selectedUploadStations = ['all'];
    } else {
      selectedUploadStations.push(st);
    }
  }

  const hiddenInput = document.getElementById('new-video-station');
  if (hiddenInput) hiddenInput.value = selectedUploadStations.join(',');

  const buttons = document.querySelectorAll('#station-picker-group .st-pick-btn:not(#btn-choose-individually)');
  buttons.forEach(b => {
    const bSt = b.getAttribute('data-st');
    const isAct = selectedUploadStations.includes(bSt);
    if (isAct) {
      b.style.background = '#2563eb';
      b.style.color = '#ffffff';
      b.style.border = 'none';
      b.style.boxShadow = 'none';
    } else {
      b.style.background = '#ffffff';
      b.style.color = '#334155';
      b.style.border = '1.5px solid #cbd5e1';
      b.style.boxShadow = 'none';
    }
  });

  const hint = document.getElementById('station-assignment-hint');
  if (hint) {
    if (selectedUploadStations.includes('all')) {
      hint.innerHTML = 'Assigned to: <strong>All Employees (General Curriculum)</strong>';
    } else {
      const labels = {
        trainee: 'Trainees',
        engineer: 'Structural & MEP Engineers',
        architect: 'Architects & Designers'
      };
      const names = selectedUploadStations.map(s => labels[s] || s).join(', ');
      hint.innerHTML = `Assigned to: <strong>${names}</strong> (${selectedUploadStations.length} station${selectedUploadStations.length > 1 ? 's' : ''})`;
    }
  }
}

function handleAdminVideoFilePick(input) {
  if (input.files && input.files.length > 0) {
    addFilesToVideoQueue(Array.from(input.files));
  }
}

function handleAdminVideoDrop(e) {
  e.preventDefault();
  const zone = e.currentTarget;
  if (zone) {
    zone.style.borderColor = '#cbd5e1';
    zone.style.background = '#f8fafc';
  }
  if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
    const files = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('video/') || /\.(mp4|webm|mov|mkv|avi)$/i.test(f.name));
    if (files.length > 0) {
      addFilesToVideoQueue(files);
    } else {
      showToast('️ Please drop video files (.mp4, .webm, .mov)', '#f59e0b');
    }
  }
}

function addFilesToVideoQueue(files) {
  files.forEach(f => {
    if (!selectedAdminVideoFiles.some(item => item.file.name === f.name && item.file.size === f.size)) {
      const cleanTitle = f.name.replace(/\.[^/.]+$/, '').replace(/[_.-]+/g, ' ').trim().replace(/\b\w/g, c => c.toUpperCase());
      const item = {
        file: f,
        title: cleanTitle,
        sizeMb: (f.size / (1024 * 1024)).toFixed(1),
        duration: ''
      };
      selectedAdminVideoFiles.push(item);
      detectVideoDuration(f).then(duration => {
        item.duration = duration;
        renderSelectedVideoFilesQueue();
      });
    }
  });
  renderSelectedVideoFilesQueue();
}

function formatVideoDuration(seconds) {
  if (!Number.isFinite(seconds) || seconds < 0) return '';
  const total = Math.round(seconds);
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const secs = total % 60;
  return hours > 0
    ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
    : `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

function detectVideoDuration(file) {
  return new Promise(resolve => {
    const video = document.createElement('video');
    const objectUrl = URL.createObjectURL(file);
    const cleanUp = () => {
      URL.revokeObjectURL(objectUrl);
      video.removeAttribute('src');
      video.load();
    };
    video.preload = 'metadata';
    video.onloadedmetadata = () => {
      const duration = formatVideoDuration(video.duration);
      cleanUp();
      resolve(duration);
    };
    video.onerror = () => {
      cleanUp();
      resolve('');
    };
    video.src = objectUrl;
  });
}

function renderSelectedVideoFilesQueue() {
  const container = document.getElementById('vfiles-queue-container');
  const list = document.getElementById('vfiles-queue-list');
  const title = document.getElementById('vfiles-queue-title');
  const sizeLbl = document.getElementById('vfiles-queue-size');
  const dropLabel = document.getElementById('vfile-selected-name');
  const preview = document.getElementById('upload-video-preview');

  if (preview) {
    if (selectedAdminVideoFiles.length > 0) {
      try {
        preview.src = URL.createObjectURL(selectedAdminVideoFiles[0].file);
        preview.style.display = 'block';
      } catch (e) {}
    } else {
      preview.style.display = 'none';
      preview.src = '';
    }
  }

  if (!container || !list) return;

  if (selectedAdminVideoFiles.length === 0) {
    container.style.display = 'none';
    if (dropLabel) dropLabel.textContent = 'Click or drag to select one or multiple video files';
    const durationInput = document.getElementById('new-video-duration');
    if (durationInput) durationInput.value = 'Select a video file';
    return;
  }

  container.style.display = 'block';
  const totalSizeMb = selectedAdminVideoFiles.reduce((acc, item) => acc + (item.file.size / (1024 * 1024)), 0).toFixed(1);
  if (title) title.textContent = `Selected Videos (${selectedAdminVideoFiles.length})`;
  if (sizeLbl) sizeLbl.textContent = `• Total: ${totalSizeMb} MB`;
  if (dropLabel) dropLabel.textContent = `${selectedAdminVideoFiles.length} video file(s) selected (${totalSizeMb} MB)`;

  const titleInput = document.getElementById('new-video-title');
  if (titleInput && (!titleInput.value || selectedAdminVideoFiles.length === 1)) {
    titleInput.value = selectedAdminVideoFiles[0].title;
  }
  const inlineTitle = document.getElementById('inline-curriculum-title');
  if (inlineTitle && !inlineTitle.value) {
    inlineTitle.value = selectedAdminVideoFiles[0].title + ' Course Series';
  }
  const durationInput = document.getElementById('new-video-duration');
  if (durationInput) {
    const firstDuration = selectedAdminVideoFiles[0].duration;
    durationInput.value = firstDuration || 'Detecting duration…';
  }

  list.innerHTML = selectedAdminVideoFiles.map((item, idx) => `
    <div style="background:#ffffff;border:1.5px solid #e2e8f0;border-radius:10px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
        <span style="font-size:12px;font-weight:900;color:#2563eb;background:#eff6ff;padding:4px 8px;border-radius:6px;flex-shrink:0;">#${idx + 1}</span>
        <div style="flex:1;min-width:0;">
          <input type="text" value="${item.title.replace(/"/g, '&quot;')}" onchange="updateVideoQueueTitle(${idx}, this.value)" style="width:100%;font-size:13px;font-weight:700;color:#0f172a;border:1px solid #cbd5e1;border-radius:6px;padding:4px 8px;box-sizing:border-box;" placeholder="Video title..." />
          <div style="font-size:11.5px;color:#64748b;margin-top:3px;display:flex;gap:8px;">
            <span>${item.file.name}</span>
            <span>(${item.sizeMb} MB)</span>
            <span>${item.duration ? item.duration : 'Detecting duration…'}</span>
          </div>
        </div>
      </div>
      <button type="button" onclick="removeSelectedVideoFile(${idx})" style="background:#fee2e2;color:#b91c1c;border:none;width:28px;height:28px;border-radius:6px;cursor:pointer;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0;" title="Remove this video"></button>
    </div>
  `).join('');
}

function updateVideoQueueTitle(index, newTitle) {
  if (selectedAdminVideoFiles[index]) {
    selectedAdminVideoFiles[index].title = newTitle.trim() || selectedAdminVideoFiles[index].title;
  }
}

function removeSelectedVideoFile(index) {
  selectedAdminVideoFiles.splice(index, 1);
  renderSelectedVideoFilesQueue();
}

function clearSelectedVideoFiles() {
  selectedAdminVideoFiles = [];
  const input = document.getElementById('new-video-file-input');
  if (input) input.value = '';
  const preview = document.getElementById('upload-video-preview');
  if (preview) {
    preview.style.display = 'none';
    preview.src = '';
  }
  renderSelectedVideoFilesQueue();
}

async function adminPublishVideo() {
  const isIndividualMode = (selectedIndividualEmployeeIds && selectedIndividualEmployeeIds.length > 0);
  const station = isIndividualMode ? 'custom' : (document.getElementById('new-video-station')?.value || 'all');
  const assignedEmployeesJson = document.getElementById('new-video-assigned-employees')?.value || (isIndividualMode ? JSON.stringify(selectedIndividualEmployeeIds) : '');
  const cat = document.getElementById('new-video-cat')?.value || 'revit';
  const defaultDuration = document.getElementById('new-video-duration')?.value.trim() || '00:00';
  const defaultDesc = document.getElementById('new-video-desc')?.value.trim() || 'Direct BIM demonstration.';

  const curriculumMode = currentVideoCurriculumMode; // 'existing', 'new', 'none'
  let curriculumId = document.getElementById('new-video-curriculum-id')?.value || '';
  const newCurriculumTitle = document.getElementById('inline-curriculum-title')?.value.trim() || '';
  const newCurriculumLevel = document.getElementById('inline-curriculum-level')?.value || 'Intermediate';
  const newCurriculumIcon = document.getElementById('inline-curriculum-icon')?.value || '';
  const newCurriculumDesc = document.getElementById('inline-curriculum-desc')?.value.trim() || defaultDesc;

  if (curriculumMode === 'new' && !newCurriculumTitle) {
    showToast('️ Please enter a Title for the new Curriculum Track', '#f59e0b');
    return;
  }

  const publishBtn = document.getElementById('admin-publish-video-btn');
  const originalBtnText = publishBtn ? publishBtn.innerHTML : 'Publish';

  if (currentVideoUploadMode === 'file') {
    if (!selectedAdminVideoFiles || selectedAdminVideoFiles.length === 0) {
      showToast('️ Please choose at least one video file to upload', '#f59e0b');
      return;
    }

    if (publishBtn) {
      publishBtn.disabled = true;
      publishBtn.style.opacity = '0.7';
    }

    const total = selectedAdminVideoFiles.length;
    let successCount = 0;

    try {
      for (let i = 0; i < total; i++) {
        const item = selectedAdminVideoFiles[i];
        const vTitle = item.title || item.file.name;
        const progressPct = Math.round(((i) / total) * 100);

        if (publishBtn) {
          publishBtn.innerHTML = `⏳ Uploading ${i + 1} of ${total} (${progressPct}%)...`;
        }
        showToast(`⏳ Uploading video ${i + 1}/${total}: "${vTitle}"...`, '#2563eb');

        const formData = new FormData();
        formData.append('video_file', item.file);
        formData.append('title', vTitle);
        formData.append('category', cat);
        formData.append('station', station);
        if (isIndividualMode) {
          formData.append('assigned_employees', assignedEmployeesJson);
        }
        formData.append('duration', item.duration || defaultDuration);
        formData.append('description', defaultDesc);

        if (curriculumMode === 'new') {
          if (i === 0) {
            formData.append('curriculum_mode', 'new');
            formData.append('new_curriculum_title', newCurriculumTitle);
            formData.append('new_curriculum_level', newCurriculumLevel);
            formData.append('new_curriculum_icon', newCurriculumIcon);
            formData.append('new_curriculum_desc', newCurriculumDesc);
          } else {
            formData.append('curriculum_mode', 'existing');
            formData.append('curriculum_id', curriculumId);
          }
        } else if (curriculumMode === 'existing') {
          formData.append('curriculum_mode', 'existing');
          formData.append('curriculum_id', curriculumId);
        } else {
          formData.append('curriculum_mode', 'none');
        }

        const res = await fetch('api/upload-video.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (!json.success) {
          throw new Error(`Video #${i + 1} (${vTitle}) failed: ${json.error || 'Server error'}`);
        }

        if (i === 0 && curriculumMode === 'new' && json.curriculum_id) {
          curriculumId = json.curriculum_id;
        }

        successCount++;
      }

      showToast(`Successfully uploaded and published ${successCount} video(s)!`, '#10b981');
      if (publishBtn) publishBtn.innerHTML = `All ${successCount} Videos Published!`;
      setTimeout(() => location.reload(), 1400);

    } catch (err) {
      console.error(err);
      showToast('Upload stopped: ' + err.message, '#ef4444');
      if (publishBtn) {
        publishBtn.disabled = false;
        publishBtn.style.opacity = '1';
        publishBtn.innerHTML = originalBtnText;
      }
    }

  } else {
    // Mode is 'yt' (YouTube/Link) or 'folder'
    const mainTitle = document.getElementById('new-video-title')?.value.trim();
    if (!mainTitle) {
      showToast('️ Video Title is required', '#f59e0b');
      return;
    }

    showToast('⏳ Registering video link...', '#2563eb');

    try {
      let ytId = '';
      if (currentVideoUploadMode === 'folder') {
        const fn = document.getElementById('new-video-folder-file').value.trim();
        ytId = fn.startsWith('videos/') ? fn : ('videos/' + fn);
      } else {
        ytId = document.getElementById('new-video-url').value.trim();
      }

      if (!ytId) {
        showToast('️ Video URL or filename is required', '#f59e0b');
        return;
      }

      const newVideoEntry = {
        id: 'vid_' + Date.now(),
        cat,
        station,
        title: mainTitle,
        desc: defaultDesc,
        ytId,
        duration: defaultDuration,
        type: ytId.includes('videos/') ? 'local' : 'yt',
        assigned_employees: isIndividualMode ? selectedIndividualEmployeeIds : []
      };

      const current = await getAllVideos();
      const updated = [newVideoEntry, ...current];
      await fetch('api/save-videos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updated)
      });

      if (curriculumMode === 'existing' && curriculumId) {
        await fetch('api/courses.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'add_video',
            course_id: curriculumId,
            video: newVideoEntry
          })
        });
      } else if (curriculumMode === 'new' && newCurriculumTitle) {
        await fetch('api/courses.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'create',
            title: newCurriculumTitle,
            cat,
            station,
            assigned_employees: isIndividualMode ? selectedIndividualEmployeeIds : [],
            level: newCurriculumLevel,
            icon: newCurriculumIcon,
            duration: '1 lesson • ' + defaultDuration,
            desc: newCurriculumDesc,
            initial_video: newVideoEntry
          })
        });
      }

      showToast(`Video published successfully!`, '#10b981');
      setTimeout(() => location.reload(), 1200);

    } catch (err) {
      console.error(err);
      showToast('Failed: ' + err.message, '#ef4444');
    }
  }
}

async function renderAdminVideosList() {
  const container = document.getElementById('admin-video-manage-list');
  if (!container) return;
  const videos = await getAllVideos();
  if (!videos || videos.length === 0) {
    container.innerHTML = `
      <div style="text-align:center;padding:28px 20px;background:#f8fafc;border:2px dashed #cbd5e1;border-radius:12px;color:#64748b;">
        <div style="font-weight:700;font-size:14px;color:#334155;">No videos uploaded yet</div>
        <div style="font-size:12px;color:#94a3b8;margin-top:2px;">Use the upload form above to add your first training video.</div>
      </div>
    `;
    return;
  }
  container.innerHTML = videos.map(v => `
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <div>
        <div style="font-size:14px;font-weight:800;color:#0f172a;">${v.title}</div>
        <div style="font-size:12px;color:#64748b;">${v.cat.toUpperCase()} • Station: <strong style="color:#2563eb;">${(v.station || 'all').toUpperCase()}</strong></div>
      </div>
      <button onclick="launchVideoPlayer('${v.ytId}','${encodeURIComponent(v.title)}','${encodeURIComponent(v.desc)}','${v.type || 'local'}','${v.id}','${v.station || 'all'}')" style="background:#2563eb;color:white;border:none;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">
        ▶ Play
      </button>
    </div>
  `).join('');
}

function applyRosterFilters() {
  const rows = document.querySelectorAll('#admin-roster-table .roster-row');
  rows.forEach(r => {
    const name = (r.getAttribute('data-name') || '').toLowerCase();
    const st = r.getAttribute('data-station') || '';
    const matchName = !currentRosterQuery || name.includes(currentRosterQuery);
    const matchStation = (currentRosterStation === 'all' || st === currentRosterStation);
    r.style.display = (matchName && matchStation) ? '' : 'none';
  });
}

function filterAdminRoster(query) {
  currentRosterQuery = (query || '').toLowerCase().trim();
  applyRosterFilters();
}

function filterAdminRosterStation(station) {
  currentRosterStation = station || 'all';
  const sel = document.getElementById('admin-roster-station-select');
  if (sel && sel.value !== currentRosterStation) {
    sel.value = currentRosterStation;
  }
  applyRosterFilters();
}

/* ── MODALS & TOAST ── */
function openModal(type) {
  const el = document.getElementById('modal-' + type);
  if (el) el.style.display = 'block';
  document.getElementById('modal-container').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  document.getElementById('modal-container').style.display = 'none';
  document.body.style.overflow = '';
  const p = document.getElementById('vmodal-player');
  if (p) p.innerHTML = '';
}

function toggleActiveVideoFullscreen() {
  const vid = document.getElementById('active-bim-video');
  const iframe = document.querySelector('#vmodal-player iframe');
  const target = vid || iframe;
  if (!target) return;
  if (target.requestFullscreen) target.requestFullscreen();
  else if (target.webkitRequestFullscreen) target.webkitRequestFullscreen();
  else if (target.msRequestFullscreen) target.msRequestFullscreen();
}

let activeToastTimer = null;
function showToast(msg, bg) {
  // Clear any existing toasts to prevent DOM flood and UI freezing
  document.querySelectorAll('.toast').forEach(t => t.remove());
  if (activeToastTimer) clearTimeout(activeToastTimer);

  const t = document.createElement('div');
  t.className = 'toast';
  t.style.background = bg || '#2563eb';
  t.textContent = msg;
  document.body.appendChild(t);

  activeToastTimer = setTimeout(() => {
    t.style.opacity = '0';
    setTimeout(() => t.remove(), 300);
  }, 3200);
}

function spawnHeroParticles() {
  const host = document.getElementById('particles-host');
  if (!host) return;
  for (let i = 0; i < 20; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    p.style.left = (Math.random() * 100) + 'vw';
    p.style.bottom = '-10px';
    const sz = (Math.random() * 3 + 1.5);
    p.style.width = sz + 'px';
    p.style.height = sz + 'px';
    p.style.animationDuration = (Math.random() * 8 + 6) + 's';
    host.appendChild(p);
  }
}

function handleGlobalSearch(query) {
  const q = (query || '').toLowerCase().trim();
  const cards = document.querySelectorAll('#video-cards-grid .dashboard-card');
  cards.forEach(card => {
    card.style.display = (!q || card.textContent.toLowerCase().includes(q)) ? 'flex' : 'none';
  });
}

function getRoleLandingSection() {
  if (window.USER_ROLE === 'admin') return 'analytics';
  return 'my-progress';
}

document.addEventListener('DOMContentLoaded', () => {
  spawnHeroParticles();
  showSection(getRoleLandingSection());
  populateCurriculumDropdown();
  renderAdminCurriculaList();
  renderAdminVideosList();
});
</script>

</body>
</html>
