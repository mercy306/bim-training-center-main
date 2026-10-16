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
$userStation = $user['station'] ?? 'all';
$userName = $user['name'] ?? 'User';
$teamName = $user['team_name'] ?? 'General Team';
$teamId = $user['team_id'] ?? null;

// Preload videos according to role & station
// Employees only see videos assigned to their station or 'all'
$isStaffAdmin = ($role === 'admin');
$isTeamLeader = ($role === 'team_leader');
$isEmployee = ($role === 'employee');

$visibleVideos = getBimVideos($isStaffAdmin ? null : $userStation);
$visibleCourses = getBimCourses($isStaffAdmin ? null : $userStation);
$allCourses = getBimCourses(); // Complete curricula catalog for admin assignment
$allStations = BIM_STATIONS;

// Preload analytics for admin / team leader
$analytics = ($isStaffAdmin || $isTeamLeader) ? getTrainingAnalytics($isTeamLeader ? (int)$teamId : null) : null;
$roster = ($isStaffAdmin || $isTeamLeader) ? getEmployeeRosterWithStats($isTeamLeader ? (int)$teamId : null) : [];
$myProgress = getEmployeeProgressSummary($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>BSD BIM Training Center | <?= htmlspecialchars($userName) ?></title>
  <script src="https://www.gstatic.com/antigravity/web/dev/tailwindcss.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    html { scroll-behavior: smooth; }
    ::-webkit-scrollbar { width: 7px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    /* Blue Gradient Hero */
    .hero-bg, .page-header-bg {
      position: relative;
      background: linear-gradient(115deg, #1d4ed8 0%, #2563eb 40%, #0284c7 75%, #38bdf8 100%);
      overflow: hidden;
      display: flex;
      align-items: center;
      padding: 30px 24px;
    }

    .hero-glow-1 {
      position: absolute;
      top: -100px; left: -80px; width: 380px; height: 380px;
      background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 65%);
      border-radius: 50%; pointer-events: none;
    }
    .hero-glow-2 {
      position: absolute;
      bottom: -100px; right: -80px; width: 420px; height: 420px;
      background: radial-gradient(circle, rgba(255,255,255,0.18) 0%, transparent 70%);
      border-radius: 50%; pointer-events: none;
    }
    .hero-circle-accent {
      position: absolute; width: 240px; height: 240px;
      border: 28px solid rgba(255,255,255,0.06);
      border-radius: 50%; top: -30px; left: 30%; pointer-events: none;
    }

    /* Cards */
    .dashboard-card, .course-card {
      background: #ffffff;
      border-radius: 20px;
      border: 1px solid #eef2f6;
      box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      position: relative;
    }
    .dashboard-card:hover, .course-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.09);
      border-color: #dbeafe;
    }

    /* Squircle Badges */
    .icon-squircle {
      width: 48px; height: 48px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; color: #ffffff; flex-shrink: 0;
    }
    .glow-blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); box-shadow: 0 8px 18px -4px rgba(37, 99, 235, 0.4); }
    .glow-green { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 8px 18px -4px rgba(16, 185, 129, 0.4); }
    .glow-purple { background: linear-gradient(135deg, #8b5cf6, #6d28d9); box-shadow: 0 8px 18px -4px rgba(139, 92, 246, 0.4); }
    .glow-amber { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 8px 18px -4px rgba(245, 158, 11, 0.4); }

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
      border: none; background: transparent; padding: 8px 16px; border-radius: 10px;
      font-size: 13.5px; font-weight: 700; color: #64748b; cursor: pointer;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .nav-btn:hover { color: #0f172a; background: #f1f5f9; }
    .nav-btn.active { color: #ffffff; background: #2563eb; box-shadow: 0 4px 14px -2px rgba(37, 99, 235, 0.4); }

    .btn-upload-prominent {
      background: linear-gradient(135deg, #059669, #10b981) !important;
      color: #ffffff !important;
      border: none !important;
      padding: 10px 22px !important;
      border-radius: 12px !important;
      font-size: 14.5px !important;
      font-weight: 800 !important;
      letter-spacing: 0.01em;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4) !important;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }
    .btn-upload-prominent:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(16, 185, 129, 0.5) !important;
      filter: brightness(1.06);
    }
    .btn-upload-prominent.active {
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3), 0 8px 24px rgba(16, 185, 129, 0.55) !important;
      transform: scale(1.02);
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
  </style>
  <script>
    window.ACTIVE_USER = <?= json_encode($user) ?>;
    window.SERVER_INITIAL_VIDEOS = <?= json_encode($visibleVideos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.SERVER_INITIAL_COURSES = <?= json_encode($visibleCourses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.ALL_ADMIN_COURSES = <?= json_encode($allCourses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.USER_ROLE = '<?= $role ?>';
    window.USER_STATION = '<?= $userStation ?>';
    window.INITIAL_PROGRESS = <?= json_encode($myProgress) ?>;
  </script>
</head>
<body style="background:#f4f7fc;color:#0f172a;font-family:system-ui,-apple-system,sans-serif;margin:0;padding:0;">

<!-- ══════════════════════════════════════════
     ROLE SWITCHER & CONTEXT BANNER
══════════════════════════════════════════ -->
<div style="background:#0f172a;color:#cbd5e1;padding:8px 24px;font-size:12.5px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
  <div style="display:flex;align-items:center;gap:12px;">
    <span style="display:inline-flex;align-items:center;gap:6px;font-weight:700;">
      <span style="width:8px;height:8px;border-radius:50%;background:<?= $isStaffAdmin ? '#10b981' : ($isTeamLeader ? '#38bdf8' : '#fbbf24') ?>;"></span>
      Active Role: 
      <strong style="color:#ffffff;text-transform:uppercase;">
        <?= $isStaffAdmin ? 'Enterprise Administrator' : ($isTeamLeader ? 'Team Leader' : 'Employee Learner') ?>
      </strong>
    </span>
    <span style="color:#64748b;">|</span>
    <span>Target Station: <strong style="color:#38bdf8;"><?= htmlspecialchars(BIM_STATIONS[$userStation] ?? ucfirst($userStation)) ?></strong></span>
    <?php if (!$isStaffAdmin && $teamName): ?>
      <span style="color:#64748b;">|</span>
      <span>Team: <strong style="color:#f8fafc;"><?= htmlspecialchars($teamName) ?></strong></span>
    <?php endif; ?>
  </div>

  <!-- Instant Demo Switcher -->
  <div style="display:flex;align-items:center;gap:8px;">
    <span style="color:#94a3b8;font-size:11.5px;font-weight:600;">Switch View:</span>
    <a href="?demo_role=admin" style="background:<?= $isStaffAdmin ? '#2563eb' : '#1e293b' ?>;color:#fff;padding:3px 10px;border-radius:6px;text-decoration:none;font-weight:700;font-size:11px;">👑 Admin</a>
    <a href="?demo_role=team_leader" style="background:<?= $isTeamLeader ? '#2563eb' : '#1e293b' ?>;color:#fff;padding:3px 10px;border-radius:6px;text-decoration:none;font-weight:700;font-size:11px;">👥 Team Leader</a>
    <a href="?demo_role=employee_trainee" style="background:<?= ($isEmployee && $userStation === 'trainee') ? '#2563eb' : '#1e293b' ?>;color:#fff;padding:3px 10px;border-radius:6px;text-decoration:none;font-weight:700;font-size:11px;">🎓 Trainee</a>
    <a href="?demo_role=employee_engineer" style="background:<?= ($isEmployee && $userStation === 'engineer') ? '#2563eb' : '#1e293b' ?>;color:#fff;padding:3px 10px;border-radius:6px;text-decoration:none;font-weight:700;font-size:11px;">📐 Engineer</a>
  </div>
</div>


<!-- ══════════════════════════════════════════
     TOP NAVIGATION BAR
══════════════════════════════════════════ -->
<nav style="background:#ffffff;position:sticky;top:0;z-index:100;box-shadow:0 1px 3px 0 rgba(0,0,0,0.04);border-bottom:1px solid #eef2f6;">
  <div style="max-width:1440px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;padding:0 24px;height:70px;">
    
    <!-- Brand Logo -->
    <div style="display:flex;align-items:center;gap:12px;cursor:pointer;" onclick="showSection(getRoleLandingSection())">
      <div style="width:42px;height:42px;background:linear-gradient(135deg,#1d4ed8,#0284c7);border-radius:12px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(29,78,216,0.3);">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
      </div>
      <div>
        <div style="color:#0f172a;font-weight:900;font-size:18px;line-height:1.1;letter-spacing:-0.02em;">BSD BIM Center</div>
        <div style="color:#2563eb;font-size:10px;letter-spacing:0.18em;font-weight:800;">
          <?= $isStaffAdmin ? 'ENTERPRISE ANALYTICS & OPS' : ($isTeamLeader ? 'TEAM LEADER DASHBOARD' : 'LEARNING & TRAINING') ?>
        </div>
      </div>
    </div>

    <!-- Navigation Tabs (Pushed to the left next to logo) -->
    <div style="display:flex;gap:8px;align-items:center;margin-left:28px;margin-right:auto;">
      <!-- Role Landing Tab Placed First -->
      <?php if ($isStaffAdmin): ?>
        <button onclick="showSection('analytics')" class="nav-btn active" id="nav-analytics" style="color:#1d4ed8;background:#eff6ff;border:1px solid #bfdbfe;">
          📈 Analytics & Roster
        </button>
      <?php elseif ($isTeamLeader): ?>
        <button onclick="showSection('team-progress')" class="nav-btn active" id="nav-team-progress" style="color:#0369a1;background:#f0f9ff;border:1px solid #bae6fd;">
          👥 My Team Progress
        </button>
      <?php elseif ($isEmployee): ?>
        <button onclick="showSection('my-progress')" class="nav-btn active" id="nav-my-progress" style="color:#0284c7;background:#f0f9ff;border:1px solid #bae6fd;">
          📊 My Progress (<span id="nav-my-pct"><?= $myProgress['overall_percent'] ?>%</span>)
        </button>
      <?php endif; ?>

      <button onclick="showSection('home')"    class="nav-btn" id="nav-home">Home</button>
      <button onclick="showSection('courses')" class="nav-btn" id="nav-courses">Courses</button>
      <button onclick="showSection('videos')"  class="nav-btn" id="nav-videos">Videos</button>
      <button onclick="showSection('files')"   class="nav-btn" id="nav-files">Practice Files</button>
    </div>

    <!-- Right: Big Prominent "Upload Material" Button + User Avatar & Search -->
    <div style="display:flex;align-items:center;gap:14px;">
      <!-- Big Noticeable "Upload Material" Button -->
      <?php if ($isStaffAdmin): ?>
        <button onclick="showSection('upload')" class="btn-upload-prominent" id="nav-upload" title="Upload video materials, practice datasets & curricula">
          <span style="font-size:18px;line-height:1;">📤</span>
          <span>Upload Material</span>
        </button>
      <?php endif; ?>

      <div style="display:flex;align-items:center;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:6px 12px;gap:8px;">
        <span style="color:#94a3b8;font-size:13px;">🔍</span>
        <input id="global-search-input" oninput="handleGlobalSearch(this.value)" placeholder="Search..." style="border:none;background:transparent;outline:none;font-size:13px;width:120px;color:#0f172a;font-weight:600;" />
      </div>

      <!-- User Identity Tag -->
      <div style="display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #e2e8f0;padding:5px 12px;border-radius:12px;">
        <div style="width:28px;height:28px;border-radius:50%;background:<?= $isStaffAdmin ? '#1d4ed8' : ($isTeamLeader ? '#0284c7' : '#059669') ?>;color:white;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;">
          <?= strtoupper(substr($userName, 0, 1)) ?>
        </div>
        <div style="text-align:left;">
          <div style="font-size:12px;font-weight:800;color:#0f172a;line-height:1.1;"><?= htmlspecialchars($userName) ?></div>
          <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;"><?= htmlspecialchars($role) ?></div>
        </div>
      </div>
    </div>

  </div>
</nav>


<!-- ══════════════════════════════════════════
     MAIN SECTIONS
══════════════════════════════════════════ -->
<div id="app-root">

<!-- ─────────────────────────────────────────
     1. HOME SECTION
───────────────────────────────────────── -->
<section id="section-home">
  <div class="hero-bg">
    <div class="hero-glow-1"></div>
    <div class="hero-glow-2"></div>
    <div class="hero-circle-accent"></div>
    <div id="particles-host" style="position:absolute;inset:0;pointer-events:none;overflow:hidden;"></div>

    <div style="position:relative;z-index:10;max-width:1240px;width:100%;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:32px;flex-wrap:wrap;">
      <div style="flex:1;min-width:300px;max-width:680px;">
        <div class="badge" style="background:rgba(255,255,255,0.2);color:#ffffff;margin-bottom:12px;backdrop-filter:blur(6px);">
          <?= $isStaffAdmin ? 'ENTERPRISE ADMINISTRATION' : ($isTeamLeader ? 'TEAM LEADER WORKSPACE' : 'EMPLOYEE TRAINING TRACK') ?>
        </div>
        <h1 style="color:#ffffff;font-size:clamp(22px,3vw,34px);font-weight:900;line-height:1.2;margin:0 0 10px;letter-spacing:-0.02em;">
          Master <span style="text-decoration:underline;text-decoration-color:rgba(255,255,255,0.45);text-underline-offset:6px;">BIM Coordination</span> & Engineering Workflows
        </h1>
        <p style="color:rgba(255,255,255,0.92);font-size:14.5px;margin:0 0 18px;">
          <?php if ($isStaffAdmin): ?>
            Manage company-wide training videos, assign targeted curricula to specific employee stations, and review workforce analytics.
          <?php elseif ($isTeamLeader): ?>
            Monitor training adoption across members of <strong><?= htmlspecialchars($teamName) ?></strong> and inspect video completion milestones.
          <?php else: ?>
            Watch tailored video tutorials assigned to the <strong><?= htmlspecialchars(BIM_STATIONS[$userStation] ?? $userStation) ?></strong> track. Your progress is automatically recorded.
          <?php endif; ?>
        </p>

        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <button onclick="showSection('videos')" style="background:#ffffff;color:#1d4ed8;border:none;padding:10px 22px;border-radius:10px;font-size:13px;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,0.12);">
            ▶ Watch Tutorials
          </button>
          <?php if ($isEmployee): ?>
            <button onclick="showSection('my-progress')" style="background:rgba(255,255,255,0.2);color:#ffffff;border:1.5px solid rgba(255,255,255,0.4);padding:10px 20px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;backdrop-filter:blur(8px);">
              📊 View My Progress (<?= $myProgress['overall_percent'] ?>%)
            </button>
          <?php elseif ($isTeamLeader): ?>
            <button onclick="showSection('team-progress')" style="background:rgba(255,255,255,0.2);color:#ffffff;border:1.5px solid rgba(255,255,255,0.4);padding:10px 20px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;backdrop-filter:blur(8px);">
              👥 Inspect Team (<?= $teamName ?>)
            </button>
          <?php elseif ($isStaffAdmin): ?>
            <button onclick="showSection('analytics')" style="background:rgba(255,255,255,0.2);color:#ffffff;border:1.5px solid rgba(255,255,255,0.4);padding:10px 20px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;backdrop-filter:blur(8px);">
              📈 Company Analytics & Roster
            </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick Metrics Widget -->
      <div style="background:rgba(255,255,255,0.16);border:1px solid rgba(255,255,255,0.28);border-radius:20px;backdrop-filter:blur(14px);padding:16px 20px;box-shadow:0 8px 25px rgba(0,0,0,0.06);min-width:270px;flex-shrink:0;">
        <div style="color:rgba(255,255,255,0.85);font-size:11px;font-weight:800;letter-spacing:0.08em;margin-bottom:10px;text-align:center;">
          <?= $isStaffAdmin ? 'GLOBAL PORTAL METRICS' : ($isTeamLeader ? 'TEAM SUMMARY' : 'MY TRAINING STATS') ?>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;text-align:center;">
          <?php if ($isEmployee): ?>
            <div style="background:rgba(255,255,255,0.1);border-radius:12px;padding:8px 10px;">
              <div id="stat-my-pct" style="font-size:22px;font-weight:900;color:#ffffff;"><?= $myProgress['overall_percent'] ?>%</div>
              <div style="font-size:11px;color:rgba(255,255,255,0.85);font-weight:700;">Completed</div>
            </div>
            <div style="background:rgba(255,255,255,0.1);border-radius:12px;padding:8px 10px;">
              <div id="stat-my-done" style="font-size:22px;font-weight:900;color:#ffffff;"><?= $myProgress['completed_count'] ?> / <?= $myProgress['total_assigned'] ?></div>
              <div style="font-size:11px;color:rgba(255,255,255,0.85);font-weight:700;">Videos Done</div>
            </div>
          <?php else: ?>
            <div style="background:rgba(255,255,255,0.1);border-radius:12px;padding:8px 10px;">
              <div style="font-size:22px;font-weight:900;color:#ffffff;"><?= $analytics['avg_completion'] ?? 0 ?>%</div>
              <div style="font-size:11px;color:rgba(255,255,255,0.85);font-weight:700;">Avg Progress</div>
            </div>
            <div style="background:rgba(255,255,255,0.1);border-radius:12px;padding:8px 10px;">
              <div style="font-size:22px;font-weight:900;color:#ffffff;"><?= $analytics['total_employees'] ?? 0 ?></div>
              <div style="font-size:11px;color:rgba(255,255,255,0.85);font-weight:700;"><?= $isTeamLeader ? 'Team Members' : 'Total Employees' ?></div>
            </div>
          <?php endif; ?>
          <div style="background:rgba(255,255,255,0.1);border-radius:12px;padding:8px 10px;">
            <div id="stat-videos-count" style="font-size:22px;font-weight:900;color:#ffffff;"><?= count($visibleVideos) ?></div>
            <div style="font-size:11px;color:rgba(255,255,255,0.85);font-weight:700;">Visible Videos</div>
          </div>
          <div style="background:rgba(255,255,255,0.1);border-radius:12px;padding:8px 10px;">
            <div id="stat-files-count" style="font-size:22px;font-weight:900;color:#ffffff;">6</div>
            <div style="font-size:11px;color:rgba(255,255,255,0.85);font-weight:700;">Practice Files</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Featured Courses -->
  <div style="background:#f4f7fc;padding:64px 24px;">
    <div style="max-width:1200px;margin:0 auto;">
      <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:36px;flex-wrap:wrap;gap:16px;">
        <div>
          <span class="badge" style="background:#eff6ff;color:#1d4ed8;margin-bottom:8px;">BIM CURRICULA</span>
          <h2 style="font-size:28px;font-weight:900;color:#0f172a;margin:0;">Core Training Programs</h2>
        </div>
        <button onclick="showSection('courses')" style="background:#ffffff;border:1.5px solid #cbd5e1;color:#1d4ed8;font-weight:700;font-size:13.5px;padding:10px 18px;border-radius:10px;cursor:pointer;">
          View All Courses →
        </button>
      </div>

      <div id="home-featured-courses" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:24px;"></div>
    </div>
  </div>
</section>


<!-- ─────────────────────────────────────────
     2. COURSES CATALOG SECTION
───────────────────────────────────────── -->
<section id="section-courses" style="display:none;background:#f4f7fc;padding:48px 24px 80px;">
  <div style="max-width:1200px;margin:0 auto;">
    <div style="margin-bottom:32px;">
      <h2 style="font-size:30px;font-weight:900;color:#0f172a;margin:0 0 10px;">Full BIM Curriculum Catalog</h2>
      <p style="color:#64748b;font-size:15px;margin:0;">Interactive step-by-step tracks with modular lessons and progress checkpoints.</p>
    </div>

    <div style="display:flex;gap:10px;margin-bottom:32px;flex-wrap:wrap;">
      <button onclick="filterCoursesCatalog('all')"         id="cfilter-all"         style="background:#2563eb;color:#fff;border:none;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;">All Programs</button>
      <button onclick="filterCoursesCatalog('coordination')" id="cfilter-coordination" style="background:#ffffff;color:#334155;border:1.5px solid #e2e8f0;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;">Coordination</button>
      <button onclick="filterCoursesCatalog('revit')"        id="cfilter-revit"        style="background:#ffffff;color:#334155;border:1.5px solid #e2e8f0;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;">Revit</button>
      <button onclick="filterCoursesCatalog('autocad')"      id="cfilter-autocad"      style="background:#ffffff;color:#334155;border:1.5px solid #e2e8f0;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;">AutoCAD</button>
      <button onclick="filterCoursesCatalog('navisworks')"   id="cfilter-navisworks"   style="background:#ffffff;color:#334155;border:1.5px solid #e2e8f0;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;">Navisworks</button>
    </div>

    <div id="catalog-courses-grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:24px;"></div>
  </div>
</section>


<!-- ─────────────────────────────────────────
     3. VIDEOS SECTION (STREAMING & PROGRESS)
───────────────────────────────────────── -->
<section id="section-videos" style="display:none;background:#f4f7fc;padding:48px 24px 80px;">
  <div style="max-width:1200px;margin:0 auto;">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:30px;flex-wrap:wrap;gap:16px;">
      <div>
        <h2 style="font-size:30px;font-weight:900;color:#0f172a;margin:0 0 10px;">Video Tutorials & Practical Walkthroughs</h2>
        <p style="color:#64748b;font-size:15px;margin:0;">
          <?php if ($isEmployee): ?>
            Displaying lessons assigned to your station (<strong><?= htmlspecialchars(BIM_STATIONS[$userStation] ?? $userStation) ?></strong>).
          <?php else: ?>
            Video lessons repository with station assignments and Range streaming playback.
          <?php endif; ?>
        </p>
      </div>

      <?php if ($isStaffAdmin): ?>
        <button onclick="showSection('upload')" style="background:linear-gradient(135deg,#2563eb,#06b6d4);color:white;border:none;padding:10px 20px;border-radius:10px;font-size:13px;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(37,99,235,0.3);">
          ⚙️ Content Manager & Upload
        </button>
      <?php endif; ?>
    </div>

    <!-- Category Tabs -->
    <div style="display:flex;gap:20px;border-bottom:2px solid #e2e8f0;margin-bottom:30px;overflow-x:auto;">
      <button class="vtab-btn" onclick="switchVideoCategory('all_sims', this)" style="background:none;border:none;padding:12px 4px;font-size:14px;font-weight:700;color:#2563eb;border-bottom:3px solid #2563eb;cursor:pointer;white-space:nowrap;">All Videos</button>
      <button class="vtab-btn" onclick="switchVideoCategory('revit', this)"    style="background:none;border:none;padding:12px 4px;font-size:14px;font-weight:600;color:#64748b;cursor:pointer;white-space:nowrap;">Revit Structure</button>
      <button class="vtab-btn" onclick="switchVideoCategory('coordination', this)" style="background:none;border:none;padding:12px 4px;font-size:14px;font-weight:600;color:#64748b;cursor:pointer;white-space:nowrap;">BIM Coordination</button>
      <button class="vtab-btn" onclick="switchVideoCategory('autocad', this)"  style="background:none;border:none;padding:12px 4px;font-size:14px;font-weight:600;color:#64748b;cursor:pointer;white-space:nowrap;">AutoCAD</button>
      <button class="vtab-btn" onclick="switchVideoCategory('navisworks', this)" style="background:none;border:none;padding:12px 4px;font-size:14px;font-weight:600;color:#64748b;cursor:pointer;white-space:nowrap;">Navisworks</button>
    </div>

    <div id="video-cards-grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:24px;"></div>
  </div>
</section>


<!-- ─────────────────────────────────────────
     4. PRACTICE FILES DOWNLOAD CENTER
───────────────────────────────────────── -->
<section id="section-files" style="display:none;background:#f4f7fc;padding:48px 24px 80px;">
  <div style="max-width:1200px;margin:0 auto;">
    <div style="margin-bottom:30px;">
      <h2 style="font-size:30px;font-weight:900;color:#0f172a;margin:0 0 10px;">Practice Datasets & BIM Templates</h2>
      <p style="color:#64748b;font-size:15px;margin:0;">Download real-world sample models (.RVT, .DWG, .NWD, .PDF) for hands-on exercises.</p>
    </div>

    <div style="display:flex;gap:10px;margin-bottom:32px;flex-wrap:wrap;">
      <button onclick="filterPracticeFiles('all')" id="ffilter-all" style="background:#2563eb;color:#fff;border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">All Types</button>
      <button onclick="filterPracticeFiles('rvt')" id="ffilter-rvt" style="background:#ffffff;color:#334155;border:1.5px solid #e2e8f0;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Revit (.RVT / .RFA)</button>
      <button onclick="filterPracticeFiles('dwg')" id="ffilter-dwg" style="background:#ffffff;color:#334155;border:1.5px solid #e2e8f0;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">AutoCAD (.DWG)</button>
      <button onclick="filterPracticeFiles('nwd')" id="ffilter-nwd" style="background:#ffffff;color:#334155;border:1.5px solid #e2e8f0;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Navisworks (.NWD)</button>
      <button onclick="filterPracticeFiles('pdf')" id="ffilter-pdf" style="background:#ffffff;color:#334155;border:1.5px solid #e2e8f0;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">PDF Guides</button>
    </div>

    <div id="practice-files-grid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:24px;"></div>
  </div>
</section>


<!-- ─────────────────────────────────────────
     5. EMPLOYEE PERSONAL PROGRESS SECTION
───────────────────────────────────────── -->
<?php if ($isEmployee): ?>
<section id="section-my-progress" style="display:none;background:#f4f7fc;padding:48px 24px 80px;">
  <div style="max-width:1100px;margin:0 auto;">
    
    <!-- Hero Profile & Meter -->
    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:24px;padding:32px;box-shadow:0 6px 25px rgba(0,0,0,0.04);margin-bottom:32px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:24px;">
      <div>
        <div class="badge" style="background:#eff6ff;color:#1d4ed8;margin-bottom:8px;">STUDENT TRAINING DASHBOARD</div>
        <h2 style="font-size:26px;font-weight:900;color:#0f172a;margin:0 0 6px;"><?= htmlspecialchars($userName) ?></h2>
        <div style="color:#64748b;font-size:14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <span>Station: <strong style="color:#0284c7;"><?= htmlspecialchars(BIM_STATIONS[$userStation] ?? $userStation) ?></strong></span>
          <span>•</span>
          <span>Team: <strong><?= htmlspecialchars($teamName) ?></strong></span>
        </div>
      </div>

      <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:20px;padding:18px 28px;text-align:center;min-width:220px;">
        <div style="font-size:36px;font-weight:900;color:#1d4ed8;line-height:1;" id="my-overall-pct"><?= $myProgress['overall_percent'] ?>%</div>
        <div style="font-size:12px;font-weight:800;color:#0f172a;margin-top:6px;">Overall Curriculum Progress</div>
        <div style="font-size:11.5px;color:#64748b;margin-top:2px;" id="my-completed-ratio"><?= $myProgress['completed_count'] ?> of <?= $myProgress['total_assigned'] ?> Videos Completed</div>
      </div>
    </div>

    <!-- Video Completion Checklist -->
    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:24px;padding:32px;box-shadow:0 4px 20px rgba(0,0,0,0.04);">
      <h3 style="font-size:18px;font-weight:900;color:#0f172a;margin:0 0 18px;">Assigned Training Materials Checklist</h3>
      <div id="my-videos-checklist" style="display:flex;flex-direction:column;gap:12px;"></div>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- ─────────────────────────────────────────
     6. TEAM LEADER SCOPED VIEW (TEAM ONLY)
───────────────────────────────────────── -->
<?php if ($isTeamLeader): ?>
<section id="section-team-progress" style="display:none;background:#f4f7fc;padding:48px 24px 80px;">
  <div style="max-width:1200px;margin:0 auto;">
    
    <div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:18px;padding:18px 24px;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:40px;height:40px;border-radius:12px;background:#2563eb;color:white;display:flex;align-items:center;justify-content:center;font-size:18px;">
          👥
        </div>
        <div>
          <h3 style="font-size:18px;font-weight:900;color:#1e3a8a;margin:0;">Team Leader Progress Portal</h3>
          <div style="font-size:13px;color:#3b82f6;font-weight:600;">
            Scoped exclusively to members of: <strong><?= htmlspecialchars($teamName) ?></strong> (You cannot view members of other teams).
          </div>
        </div>
      </div>
    </div>

    <!-- Team Metrics -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:18px;margin-bottom:28px;">
      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;padding:20px;box-shadow:0 4px 15px rgba(0,0,0,0.03);">
        <div style="font-size:12px;color:#64748b;font-weight:700;">TEAM AVERAGE COMPLETION</div>
        <div style="font-size:32px;font-weight:900;color:#2563eb;margin-top:4px;"><?= $analytics['avg_completion'] ?? 0 ?>%</div>
        <div style="font-size:12px;color:#10b981;font-weight:600;margin-top:4px;">Across all required training modules</div>
      </div>
      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;padding:20px;box-shadow:0 4px 15px rgba(0,0,0,0.03);">
        <div style="font-size:12px;color:#64748b;font-weight:700;">ASSIGNED MEMBERS</div>
        <div style="font-size:32px;font-weight:900;color:#0f172a;margin-top:4px;"><?= count($roster) ?></div>
        <div style="font-size:12px;color:#64748b;font-weight:600;margin-top:4px;">Team personnel in training</div>
      </div>
      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;padding:20px;box-shadow:0 4px 15px rgba(0,0,0,0.03);">
        <div style="font-size:12px;color:#64748b;font-weight:700;">FULLY CERTIFIED</div>
        <div style="font-size:32px;font-weight:900;color:#059669;margin-top:4px;"><?= $analytics['fully_completed'] ?? 0 ?></div>
        <div style="font-size:12px;color:#64748b;font-weight:600;margin-top:4px;">Completed 100% of curriculum</div>
      </div>
    </div>

    <!-- Scoped Team Member Roster -->
    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:24px;padding:32px;box-shadow:0 4px 20px rgba(0,0,0,0.04);">
      <h3 style="font-size:18px;font-weight:900;color:#0f172a;margin:0 0 16px;">Team Member Progress Details</h3>
      
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
<section id="section-analytics" style="display:none;background:#f4f7fc;padding:48px 24px 80px;">
  <div style="max-width:1300px;margin:0 auto;">
    
    <div style="margin-bottom:30px;">
      <span class="badge" style="background:#eff6ff;color:#1d4ed8;margin-bottom:8px;">ENTERPRISE WORKFORCE INTELLIGENCE</span>
      <h2 style="font-size:28px;font-weight:900;color:#0f172a;margin:0 0 6px;">Company-Wide Training Analytics Board</h2>
      <p style="color:#64748b;font-size:14.5px;margin:0;">Monitor progress, track video completion rates across all employee stations, and audit learning engagement.</p>
    </div>

    <!-- KPI Row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:20px;margin-bottom:32px;">
      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;padding:24px;box-shadow:0 4px 18px rgba(0,0,0,0.03);">
        <div style="font-size:12.5px;color:#64748b;font-weight:700;">TOTAL WORKFORCE ENROLLED</div>
        <div style="font-size:36px;font-weight:900;color:#0f172a;margin:6px 0;"><?= $analytics['total_employees'] ?? 0 ?></div>
        <div style="font-size:12px;color:#2563eb;font-weight:600;">Employees in active learning tracks</div>
      </div>
      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;padding:24px;box-shadow:0 4px 18px rgba(0,0,0,0.03);">
        <div style="font-size:12.5px;color:#64748b;font-weight:700;">ORGANIZATION COMPLETION RATE</div>
        <div style="font-size:36px;font-weight:900;color:#1d4ed8;margin:6px 0;"><?= $analytics['avg_completion'] ?? 0 ?>%</div>
        <div style="font-size:12px;color:#10b981;font-weight:600;">Overall modules completed</div>
      </div>
      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;padding:24px;box-shadow:0 4px 18px rgba(0,0,0,0.03);">
        <div style="font-size:12.5px;color:#64748b;font-weight:700;">FULLY CERTIFIED EMPLOYEES</div>
        <div style="font-size:36px;font-weight:900;color:#059669;margin:6px 0;"><?= $analytics['fully_completed'] ?? 0 ?></div>
        <div style="font-size:12px;color:#059669;font-weight:600;">100% of required station videos completed</div>
      </div>
      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;padding:24px;box-shadow:0 4px 18px rgba(0,0,0,0.03);">
        <div style="font-size:12.5px;color:#64748b;font-weight:700;">IN PROGRESS</div>
        <div style="font-size:36px;font-weight:900;color:#f59e0b;margin:6px 0;"><?= $analytics['in_progress'] ?? 0 ?></div>
        <div style="font-size:12px;color:#d97706;font-weight:600;">Active learning sessions recorded</div>
      </div>
    </div>

    <!-- Station Comparison Breakdown -->
    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:24px;padding:28px;box-shadow:0 4px 20px rgba(0,0,0,0.04);margin-bottom:32px;">
      <h3 style="font-size:18px;font-weight:900;color:#0f172a;margin:0 0 18px;">Completion Rate by Station / Discipline</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;">
        <?php foreach (($analytics['station_stats'] ?? []) as $st): ?>
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:16px 20px;">
            <div style="font-size:13.5px;font-weight:800;color:#0f172a;"><?= htmlspecialchars($st['title']) ?></div>
            <div style="display:flex;align-items:flex-end;justify-content:space-between;margin:8px 0;">
              <span style="font-size:24px;font-weight:900;color:#2563eb;"><?= $st['avg_pct'] ?>%</span>
              <span style="font-size:12px;color:#64748b;font-weight:600;"><?= $st['headcount'] ?> employees</span>
            </div>
            <div style="background:#e2e8f0;height:6px;border-radius:9999px;overflow:hidden;">
              <div style="width:<?= $st['avg_pct'] ?>%;background:#2563eb;height:100%;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Company-Wide Employee Roster Table -->
    <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:24px;padding:32px;box-shadow:0 4px 20px rgba(0,0,0,0.04);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <h3 style="font-size:18px;font-weight:900;color:#0f172a;margin:0;">Employee Training Progress Roster</h3>
        
        <div style="display:flex;gap:10px;align-items:center;">
          <input id="admin-roster-search" oninput="filterAdminRoster(this.value)" placeholder="Search employee..." style="padding:8px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:13px;width:180px;" />
          <select id="admin-roster-station-filter" onchange="filterAdminRosterStation(this.value)" style="padding:8px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:13px;background:white;">
            <option value="all">All Stations</option>
            <option value="trainee">Trainees</option>
            <option value="engineer">Engineers</option>
            <option value="architect">Architects</option>
            <option value="modeler">BIM Modelers</option>
          </select>
        </div>
      </div>

      <div style="overflow-x:auto;">
        <table id="admin-roster-table" style="width:100%;border-collapse:collapse;text-align:left;font-size:13.5px;">
          <thead>
            <tr style="border-bottom:2px solid #f1f5f9;color:#64748b;">
              <th style="padding:12px 14px;">Employee Name</th>
              <th style="padding:12px 14px;">Station</th>
              <th style="padding:12px 14px;">Team</th>
              <th style="padding:12px 14px;">Videos Completed</th>
              <th style="padding:12px 14px;">Progress %</th>
              <th style="padding:12px 14px;">Status</th>
              <th style="padding:12px 14px;">Last Activity</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($roster as $emp): ?>
              <tr class="roster-row" data-name="<?= strtolower(htmlspecialchars($emp['name'])) ?>" data-station="<?= $emp['station'] ?>" style="border-bottom:1px solid #f8fafc;">
                <td style="padding:14px;font-weight:800;color:#0f172a;">
                  <div style="display:flex;align-items:center;gap:10px;">
                    <span style="width:32px;height:32px;border-radius:50%;background:#e0f2fe;color:#0369a1;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;">
                      <?= strtoupper(substr($emp['name'], 0, 1)) ?>
                    </span>
                    <?= htmlspecialchars($emp['name']) ?>
                  </div>
                </td>
                <td style="padding:14px;">
                  <span class="badge" style="background:#f1f5f9;color:#334155;"><?= htmlspecialchars(BIM_STATIONS[$emp['station']] ?? $emp['station']) ?></span>
                </td>
                <td style="padding:14px;color:#64748b;font-weight:600;">
                  <?= htmlspecialchars($emp['team_name']) ?>
                </td>
                <td style="padding:14px;font-weight:700;color:#0f172a;">
                  <?= $emp['completed'] ?> / <?= $emp['total_assigned'] ?>
                </td>
                <td style="padding:14px;width:180px;">
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div style="flex:1;background:#f1f5f9;height:8px;border-radius:9999px;overflow:hidden;">
                      <div style="width:<?= $emp['percent'] ?>%;background:linear-gradient(90deg,#2563eb,#06b6d4);height:100%;"></div>
                    </div>
                    <span style="font-weight:800;font-size:12px;color:#1e293b;"><?= $emp['percent'] ?>%</span>
                  </div>
                </td>
                <td style="padding:14px;">
                  <span class="badge" style="background:<?= $emp['percent'] >= 100 ? '#ecfdf5' : ($emp['percent'] > 0 ? '#eff6ff' : '#fef2f2') ?>;color:<?= $emp['percent'] >= 100 ? '#059669' : ($emp['percent'] > 0 ? '#1d4ed8' : '#b91c1c') ?>;">
                    <?= $emp['status'] ?>
                  </span>
                </td>
                <td style="padding:14px;color:#64748b;font-size:12px;">
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
<section id="section-upload" style="display:none;background:#f4f7fc;padding:48px 24px 80px;">
  <div style="max-width:960px;margin:0 auto;">
    
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;flex-wrap:wrap;gap:12px;">
      <div>
        <div class="badge" style="background:#ecfdf5;color:#059669;margin-bottom:6px;">ADMIN ACCESS AUTHORIZED</div>
        <h2 style="font-size:26px;font-weight:900;color:#0f172a;margin:0;">BIM Portal Content & Material Upload</h2>
        <p style="color:#64748b;font-size:14px;margin:0;">Upload training videos, datasets, and curricula, and assign them specifically to Trainees, Engineers, Architects, or All Employees.</p>
      </div>

      <button onclick="showSection('analytics')" style="background:#ffffff;border:1.5px solid #cbd5e1;color:#1d4ed8;padding:8px 16px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;">
        📈 View Analytics Board
      </button>
    </div>

    <!-- Admin Subtabs -->
    <div style="display:flex;gap:10px;margin-bottom:24px;">
      <button id="atab-video" onclick="switchAdminUploadTab('video')" style="background:#2563eb;color:#ffffff;border:none;padding:10px 20px;border-radius:10px;font-size:13.5px;font-weight:700;cursor:pointer;">
        🎬 Video Lessons
      </button>
      <button id="atab-course" onclick="switchAdminUploadTab('course')" style="background:#ffffff;color:#334155;border:1.5px solid #e2e8f0;padding:10px 20px;border-radius:10px;font-size:13.5px;font-weight:600;cursor:pointer;">
        📚 Curricula Management
      </button>
      <button id="atab-files" onclick="switchAdminUploadTab('files')" style="background:#ffffff;color:#334155;border:1.5px solid #e2e8f0;padding:10px 20px;border-radius:10px;font-size:13.5px;font-weight:600;cursor:pointer;">
        📁 Practice Datasets
      </button>
    </div>

    <!-- TAB 1: VIDEO UPLOAD WITH TARGET STATION SELECTOR -->
    <div id="admin-panel-video" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;padding:34px;box-shadow:0 4px 20px rgba(0,0,0,0.05);">
      <h3 style="font-size:20px;font-weight:900;color:#0f172a;margin:0 0 6px;">Upload Material & Assign Video Lesson</h3>
      <p style="color:#64748b;font-size:14px;margin:0 0 20px;">Upload MP4 video files or YouTube links, and target which employee station will receive them.</p>

      <!-- Mode Selector -->
      <div style="display:flex;gap:8px;margin-bottom:20px;background:#f1f5f9;padding:6px;border-radius:12px;max-width:620px;flex-wrap:wrap;">
        <button id="vmode-btn-file" onclick="setVideoUploadMode('file')" style="flex:1;background:#2563eb;color:white;border:none;padding:9px 14px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;">
          💻 Upload Video File
        </button>
        <button id="vmode-btn-yt" onclick="setVideoUploadMode('yt')" style="flex:1;background:transparent;color:#475569;border:none;padding:9px 14px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;">
          📺 YouTube / Drive Link
        </button>
        <button id="vmode-btn-folder" onclick="setVideoUploadMode('folder')" style="flex:1;background:transparent;color:#475569;border:none;padding:9px 14px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;">
          📂 Direct Folder (videos/)
        </button>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">
        <div style="grid-column:1/-1;">
          <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Video Title *</label>
          <input id="new-video-title" type="text" placeholder="e.g. Cross Rebar Placement & Splice Schedules" style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;box-sizing:border-box;"/>
        </div>

        <!-- TARGET STATION ASSIGNMENT DROPDOWN -->
        <div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:12px;padding:14px;grid-column:1/-1;">
          <label style="font-size:13px;font-weight:800;color:#1d4ed8;display:block;margin-bottom:6px;">🎯 Assign to Employee Station / Target Group *</label>
          <select id="new-video-station" onchange="handleAdminStationSelectionChange(this.value)" style="width:100%;padding:11px 14px;border:1.5px solid #93c5fd;border-radius:10px;font-size:14px;background:white;font-weight:700;color:#1e3a8a;">
            <option value="all">🌟 All Employees (General Curriculum)</option>
            <option value="trainee">🎓 Trainees (Onboarding & Foundation)</option>
            <option value="engineer">📐 Structural & MEP Engineers</option>
            <option value="architect">🏛️ Architects & Designers</option>
            <option value="modeler">🏗️ BIM Modelers & Detailers</option>
            <option value="manager">📋 Project Managers & Supervisors</option>
          </select>
          <div style="font-size:12px;color:#2563eb;margin-top:6px;">When assigned to a station (e.g. Trainees), only employees of that station and administrators will see this video in their syllabus.</div>
        </div>

        <!-- CURRICULUM TRACK ASSIGNMENT SELECTOR -->
        <div style="background:#f8fafc;border:1.5px solid #cbd5e1;border-radius:14px;padding:18px;grid-column:1/-1;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
            <label style="font-size:13.5px;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:6px;">
              <span>📚</span> Curriculum Track Assignment Option
            </label>
            <span style="font-size:11.5px;color:#64748b;font-weight:600;">Add to existing syllabus or create a brand new curriculum track</span>
          </div>

          <!-- Curriculum Choice Radio Buttons -->
          <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
            <label id="copt-label-existing" style="display:flex;align-items:center;gap:8px;background:#eff6ff;border:2px solid #2563eb;padding:9px 16px;border-radius:10px;cursor:pointer;font-size:13px;font-weight:700;color:#1d4ed8;">
              <input type="radio" name="video_curriculum_choice" value="existing" checked onchange="toggleVideoCurriculumMode('existing')">
              <span>Add to Existing Curriculum</span>
            </label>

            <label id="copt-label-new" style="display:flex;align-items:center;gap:8px;background:#ffffff;border:1.5px solid #cbd5e1;padding:9px 16px;border-radius:10px;cursor:pointer;font-size:13px;font-weight:700;color:#334155;">
              <input type="radio" name="video_curriculum_choice" value="new" onchange="toggleVideoCurriculumMode('new')">
              <span>➕ Create New Curriculum with this Video</span>
            </label>

            <label id="copt-label-none" style="display:flex;align-items:center;gap:8px;background:#ffffff;border:1.5px solid #cbd5e1;padding:9px 16px;border-radius:10px;cursor:pointer;font-size:13px;font-weight:700;color:#334155;">
              <input type="radio" name="video_curriculum_choice" value="none" onchange="toggleVideoCurriculumMode('none')">
              <span>Standalone Video Only</span>
            </label>
          </div>

          <!-- Mode 1: Existing Curriculum Dropdown -->
          <div id="curriculum-container-existing" style="display:block;">
            <label style="font-size:12.5px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Choose Target Curriculum Syllabus *</label>
            <select id="new-video-curriculum-id" onchange="updateSelectedCurriculumInfo(this.value)" style="width:100%;padding:11px 14px;border:1.5px solid #94a3b8;border-radius:10px;font-size:14px;background:white;font-weight:600;color:#0f172a;">
              <!-- Populated via JavaScript -->
            </select>
            <div id="curriculum-existing-info" style="font-size:12px;color:#2563eb;margin-top:6px;font-weight:600;"></div>
          </div>

          <!-- Mode 2: Create New Curriculum On The Fly -->
          <div id="curriculum-container-new" style="display:none;background:#ffffff;border:1.5px solid #93c5fd;border-radius:12px;padding:16px;">
            <div style="font-size:13.5px;font-weight:800;color:#1d4ed8;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
              <span>✨</span> New Curriculum Information (This video will be Lesson #1)
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
              <div style="grid-column:1/-1;">
                <label style="font-size:12.5px;font-weight:700;color:#334155;display:block;margin-bottom:4px;">Curriculum Track Title *</label>
                <input id="inline-curriculum-title" type="text" placeholder="e.g. Advanced Structural Revit & Rebar Detailing" style="width:100%;padding:10px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:13.5px;box-sizing:border-box;">
              </div>
              <div>
                <label style="font-size:12.5px;font-weight:700;color:#334155;display:block;margin-bottom:4px;">Skill Level</label>
                <select id="inline-curriculum-level" style="width:100%;padding:10px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:13.5px;background:white;">
                  <option value="Beginner">Beginner</option>
                  <option value="Intermediate" selected>Intermediate</option>
                  <option value="Advanced">Advanced</option>
                </select>
              </div>
              <div>
                <label style="font-size:12.5px;font-weight:700;color:#334155;display:block;margin-bottom:4px;">Curriculum Badge Icon</label>
                <select id="inline-curriculum-icon" style="width:100%;padding:10px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:13.5px;background:white;">
                  <option value="🏗️">🏗️ Structure & Modeling</option>
                  <option value="🔩">🔩 Reinforcement & Rebar</option>
                  <option value="🔍">🔍 Coordination & Clash Detection</option>
                  <option value="✏️">✏️ AutoCAD 2D/3D Drafting</option>
                  <option value="🏢">🏢 Architectural BIM Design</option>
                  <option value="📋">📋 Standards & ISO 19650</option>
                </select>
              </div>
              <div style="grid-column:1/-1;">
                <label style="font-size:12.5px;font-weight:700;color:#334155;display:block;margin-bottom:4px;">Curriculum Overview & Objectives</label>
                <input id="inline-curriculum-desc" type="text" placeholder="Comprehensive curriculum designed for station competency..." style="width:100%;padding:10px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:13.5px;box-sizing:border-box;">
              </div>
            </div>
            <div style="font-size:12px;color:#1e40af;margin-top:10px;font-weight:600;background:#eff6ff;padding:8px 12px;border-radius:8px;">
              💡 This video will be registered as Lesson #1 of the new curriculum track, and automatically placed in the Course Catalog!
            </div>
          </div>

          <!-- Mode 3: Standalone Notice -->
          <div id="curriculum-container-none" style="display:none;background:#f1f5f9;border-radius:8px;padding:12px 16px;font-size:12.5px;color:#475569;">
            ℹ️ This video will be published only in the general video list and assigned to the selected station, without belonging to a structured curriculum course.
          </div>
        </div>

        <!-- Mode 1: Direct File -->
        <div id="vinput-container-file" style="display:block;grid-column:1/-1;">
          <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Select Video File (.mp4, .webm, .mov) *</label>
          <div class="drop-zone" onclick="document.getElementById('new-video-file-input').click()" style="border:2px dashed #cbd5e1;border-radius:14px;padding:32px 20px;text-align:center;cursor:pointer;background:#f8fafc;">
            <div style="font-size:36px;margin-bottom:8px;">🎬</div>
            <div id="vfile-selected-name" style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:4px;">Click to choose video from your computer</div>
            <div style="font-size:12px;color:#64748b;">MP4, WEBM, MOV</div>
            <input id="new-video-file-input" type="file" accept="video/*" style="display:none;" onchange="handleAdminVideoFilePick(this)" />
          </div>
        </div>

        <!-- Mode 2: Link -->
        <div id="vinput-container-yt" style="display:none;grid-column:1/-1;">
          <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">YouTube URL, Google Drive Share Link, or Video Link *</label>
          <input id="new-video-url" type="text" placeholder="https://www.youtube.com/watch?v=..." style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;box-sizing:border-box;"/>
        </div>

        <!-- Mode 3: Direct Folder -->
        <div id="vinput-container-folder" style="display:none;grid-column:1/-1;">
          <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Video Filename in <code style="color:#2563eb;background:#e0f2fe;padding:2px 6px;border-radius:4px;">videos/</code> folder *</label>
          <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-weight:700;color:#64748b;font-size:14px;background:#f1f5f9;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;">videos/</span>
            <input id="new-video-folder-file" type="text" placeholder="lesson.mp4" style="flex:1;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;box-sizing:border-box;"/>
          </div>
        </div>

        <div>
          <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Category *</label>
          <select id="new-video-cat" style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;background:white;">
            <option value="revit">Revit Structure</option>
            <option value="autocad">AutoCAD Drafting</option>
            <option value="coordination">BIM Coordination</option>
            <option value="navisworks">Navisworks</option>
          </select>
        </div>
        <div>
          <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Duration (e.g. 28:45)</label>
          <input id="new-video-duration" type="text" placeholder="28:45" style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;box-sizing:border-box;"/>
        </div>
        <div style="grid-column:1/-1;">
          <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Description</label>
          <textarea id="new-video-desc" rows="3" placeholder="Key learning outcomes..." style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;box-sizing:border-box;resize:vertical;"></textarea>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;margin-bottom:28px;">
        <button id="admin-publish-video-btn" onclick="adminPublishVideo()" style="background:linear-gradient(135deg,#2563eb,#06b6d4);color:white;border:none;padding:14px 32px;border-radius:12px;font-size:15px;font-weight:800;cursor:pointer;box-shadow:0 6px 20px rgba(37,99,235,0.4);">
          🚀 Publish Video to Station & Curriculum
        </button>
      </div>

      <!-- Live Video Management List -->
      <div style="border-top:1.5px solid #f1f5f9;padding-top:24px;">
        <h4 style="font-size:16px;font-weight:900;color:#0f172a;margin:0 0 14px;">
          Published Videos Catalog (<span id="admin-video-count"><?= count($visibleVideos) ?></span>)
        </h4>
        <div id="admin-video-manage-list" style="display:flex;flex-direction:column;gap:10px;"></div>
      </div>
    </div>

    <!-- TAB 2: CURRICULA MANAGEMENT & CREATOR -->
    <div id="admin-panel-course" style="display:none;background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;padding:34px;box-shadow:0 4px 20px rgba(0,0,0,0.05);">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
          <h3 style="font-size:20px;font-weight:900;color:#0f172a;margin:0 0 6px;">📚 Curricula Management & Creator</h3>
          <p style="color:#64748b;font-size:14px;margin:0;">Create comprehensive multi-lesson training curricula, set target stations, and organize course syllabi.</p>
        </div>
        <button onclick="showSection('courses')" style="background:#f8fafc;border:1px solid #cbd5e1;color:#1e293b;padding:8px 14px;border-radius:8px;font-size:12.5px;font-weight:700;cursor:pointer;">
          👁️ Preview Public Catalog
        </button>
      </div>

      <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:16px;padding:22px;margin-bottom:28px;">
        <div style="font-size:15px;font-weight:800;color:#0f172a;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
          <span>✨</span> Create New Curriculum Track
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

          <div>
            <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Target Station Group *</label>
            <select id="new-course-station" style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;background:white;">
              <option value="all">🌟 All Employees (General Curriculum)</option>
              <option value="trainee">🎓 Trainees (Onboarding & Foundation)</option>
              <option value="engineer">📐 Structural & MEP Engineers</option>
              <option value="architect">🏛️ Architects & Designers</option>
              <option value="modeler">🏗️ BIM Modelers & Detailers</option>
              <option value="manager">📋 Project Managers & Supervisors</option>
            </select>
          </div>

          <div>
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
              <option value="🏗️">🏗️ Structure & Modeling</option>
              <option value="🔩">🔩 Reinforcement & Rebar</option>
              <option value="🔍">🔍 Coordination & Clash</option>
              <option value="✏️">✏️ AutoCAD 2D/3D</option>
              <option value="🏢">🏢 Architecture & Building</option>
              <option value="📋">📋 Standards & Management</option>
            </select>
          </div>

          <div style="grid-column:1/-1;">
            <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Estimated Total Duration (e.g. 24 hrs)</label>
            <input id="new-course-duration" type="text" placeholder="e.g. 15 hrs (or self-paced)" style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;box-sizing:border-box;"/>
          </div>

          <div style="grid-column:1/-1;">
            <label style="font-size:13px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">Curriculum Description & Objectives</label>
            <textarea id="new-course-desc" rows="2" placeholder="Course syllabus overview and core learning outcomes..." style="width:100%;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:14px;box-sizing:border-box;resize:vertical;"></textarea>
          </div>
        </div>

        <div style="display:flex;justify-content:flex-end;">
          <button id="admin-create-course-btn" onclick="adminPublishCourse()" style="background:linear-gradient(135deg,#2563eb,#06b6d4);color:white;border:none;padding:12px 28px;border-radius:10px;font-size:14px;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(37,99,235,0.3);">
            ✨ Create Curriculum Track
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
    <div id="admin-panel-files" style="display:none;background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;padding:34px;box-shadow:0 4px 20px rgba(0,0,0,0.05);">
      <h3 style="font-size:20px;font-weight:900;color:#0f172a;margin:0 0 8px;">Upload Practice Datasets</h3>
      <div class="drop-zone" onclick="document.getElementById('admin-file-picker').click()" style="border:2px dashed #cbd5e1;border-radius:16px;padding:48px 24px;text-align:center;cursor:pointer;background:#f8fafc;">
        <div style="font-size:48px;margin-bottom:12px;">📁</div>
        <div style="font-size:15px;font-weight:800;color:#0f172a;margin-bottom:4px;">Upload RVT, DWG, NWD Datasets</div>
        <input id="admin-file-picker" type="file" multiple style="display:none;" onchange="handleAdminPracticeFilesUpload(this)" />
      </div>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- ─────────────────────────────────────────
     9. CLASSROOM & PLAYER
───────────────────────────────────────── -->
<section id="section-classroom" style="display:none;background:#f4f7fc;padding:32px 24px 80px;">
  <div style="max-width:1300px;margin:0 auto;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
      <button onclick="showSection('courses')" style="background:#ffffff;border:1.5px solid #cbd5e1;color:#1e293b;padding:8px 16px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;">
        ← Back to Catalog
      </button>
      <div style="display:flex;align-items:center;gap:10px;">
        <span id="classroom-course-cat" class="badge" style="background:#eff6ff;color:#1d4ed8;">BIM</span>
        <h2 id="classroom-course-title" style="font-size:18px;font-weight:900;color:#0f172a;margin:0;">Course Curriculum</h2>
      </div>
    </div>

    <div id="classroom-layout-grid" style="display:grid;grid-template-columns:1fr 340px;gap:24px;">
      <div style="background:#ffffff;border:1px solid #eef2f6;border-radius:20px;padding:24px;box-shadow:0 4px 20px rgba(0,0,0,0.04);">
        <div class="yt-frame" id="classroom-player" style="background:#000000;border-radius:14px;overflow:hidden;margin-bottom:18px;min-height:360px;"></div>
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
          <div>
            <h3 id="classroom-lesson-title" style="font-size:18px;font-weight:900;color:#0f172a;margin:0 0 6px;">Lesson Overview</h3>
            <div id="classroom-lesson-duration" style="font-size:13px;color:#64748b;font-weight:600;">⏱ 18:20 • HD Video</div>
          </div>
          <button id="classroom-complete-btn" onclick="toggleCurrentLessonCompletion()" style="background:#ffffff;color:#334155;border:1.5px solid #cbd5e1;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;">
            <span>○</span> Mark as Completed
          </button>
        </div>
      </div>

      <div style="background:#ffffff;border:1px solid #eef2f6;border-radius:20px;padding:22px;box-shadow:0 4px 20px rgba(0,0,0,0.04);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
          <h4 style="font-size:15px;font-weight:900;color:#0f172a;margin:0;">Course Syllabus</h4>
          <span id="classroom-playlist-count" style="font-size:12px;color:#64748b;font-weight:700;">Lessons</span>
        </div>
        <div style="background:#f1f5f9;border-radius:9999px;height:8px;overflow:hidden;margin-bottom:6px;">
          <div id="classroom-progress-bar" style="background:linear-gradient(90deg,#2563eb,#06b6d4);width:0%;height:100%;transition:width 0.3s;"></div>
        </div>
        <div id="classroom-progress-text" style="font-size:11px;color:#64748b;font-weight:700;text-align:right;margin-bottom:16px;">0%</div>
        <div id="classroom-playlist-items" style="display:flex;flex-direction:column;gap:10px;max-height:460px;overflow-y:auto;"></div>
      </div>
    </div>
  </div>
</section>

</div><!-- End app-root -->


<!-- ══════════════════════════════════════════
     POPUP MODALS (VIDEO PLAYER WITH RANGE & PROGRESS)
══════════════════════════════════════════ -->
<div id="modal-container" style="display:none;" class="modal-overlay" onclick="if(event.target===this)closeModal()">
  <div id="modal-video" class="modal-box" style="display:none;max-width:860px;padding:24px;">
    <button onclick="closeModal()" style="position:absolute;top:14px;right:18px;background:none;border:none;font-size:24px;cursor:pointer;color:#64748b;z-index:10;">✕</button>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
      <span id="vmodal-station-badge" class="badge" style="background:#eff6ff;color:#1d4ed8;">ALL EMPLOYEES</span>
      <h3 id="vmodal-title" style="font-size:18px;font-weight:900;color:#0f172a;margin:0;padding-right:32px;">Demonstration Video</h3>
    </div>
    
    <div class="yt-frame" id="vmodal-player" style="background:#000;"></div>
    
    <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <p id="vmodal-desc" style="color:#64748b;font-size:13.5px;line-height:1.5;margin:0;flex:1;"></p>
      <div id="vmodal-status-pill" class="badge" style="background:#f1f5f9;color:#334155;padding:6px 12px;font-size:12px;">
        Tracking Progress...
      </div>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════
     FOOTER
══════════════════════════════════════════ -->
<footer style="background:#ffffff;border-top:1px solid #eef2f6;color:#64748b;padding:48px 24px 24px;">
  <div style="max-width:1200px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;font-size:13px;">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="width:30px;height:30px;background:#2563eb;border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-weight:900;">B</div>
      <span style="color:#0f172a;font-weight:800;">BSD BIM Training Center</span>
      <span>• Integrated Role Management System</span>
    </div>
    <div style="color:#94a3b8;">
      Logged in as <strong><?= htmlspecialchars($userName) ?></strong> (<?= htmlspecialchars(ucfirst($role)) ?>)
    </div>
  </div>
</footer>


<!-- ══════════════════════════════════════════
     CLIENT JAVASCRIPT & PROGRESS ENGINE
══════════════════════════════════════════ -->
<script>
let currentPlayingVideoId = null;
let currentPlayingVideoElement = null;
let videoProgressDebounceTimer = null;

const STORAGE_KEY_COURSES = 'BSD_CUSTOM_COURSES';
const STORAGE_KEY_PROGRESS = 'BSD_STUDENT_PROGRESS';

const DEFAULT_COURSES = [
  {
    id: 'revit-struct', cat: 'revit', title: 'Revit Structure Modeling Mastery', icon: '🔩', level: 'Intermediate', duration: '22 hrs',
    syllabus: [
      { id: 'rs_l1', title: '1. Foundation Grids & Concrete Elements', duration: '14:20', ytId: 'videos/EP-02_HOW__TO_LINK_REVIT_FROM_CLOUD_AR_MODELING.mp4' },
      { id: 'rs_l2', title: '2. Cross Rebar Modeling & Detailing', duration: '18:35', ytId: 'videos/X_rebar_modeling.mp4' },
      { id: 'rs_l3', title: '3. Structural Framing & Column Layout', duration: '19:40', ytId: 'videos/EP-02_HOW__TO_LINK_REVIT_FROM_CLOUD_AR_MODELING.mp4' }
    ]
  },
  {
    id: 'bim-coord', cat: 'coordination', title: 'BIM Coordination & Advanced Workflows', icon: '🏗️', level: 'Advanced', duration: '28 hrs',
    syllabus: [
      { id: 'bc_l1', title: '1. Multi-discipline Model Federation', duration: '18:10', ytId: 'videos/EP-02_HOW__TO_LINK_REVIT_FROM_CLOUD_AR_MODELING.mp4' },
      { id: 'bc_l2', title: '2. Clash Detection Matrix & Tolerances', duration: '22:45', ytId: 'videos/X_rebar_modeling.mp4' }
    ]
  },
  {
    id: 'navisworks', cat: 'navisworks', title: 'Navisworks Clash Detection & Review', icon: '🔍', level: 'Intermediate', duration: '14 hrs',
    syllabus: [
      { id: 'nw_l1', title: '1. Appending NWD & NWC Models', duration: '15:20', ytId: 'videos/EP-02_HOW__TO_LINK_REVIT_FROM_CLOUD_AR_MODELING.mp4' },
      { id: 'nw_l2', title: '2. Clash Batches & Grouping Rules', duration: '20:15', ytId: 'videos/X_rebar_modeling.mp4' }
    ]
  }
];

const DEFAULT_PRACTICE_FILES = [
  { id: 'pf_1', cat: 'rvt', name: 'Hospital_Structural_Rebar_Model.rvt', size: '42.5 MB', type: 'RVT', icon: '🏗️' },
  { id: 'pf_2', cat: 'dwg', name: 'Foundation_Piles_Coordination_Grid.dwg', size: '14.2 MB', type: 'DWG', icon: '📐' },
  { id: 'pf_3', cat: 'nwd', name: 'Multi_Discipline_Federated_Clash.nwd', size: '68.0 MB', type: 'NWD', icon: '🔍' },
  { id: 'pf_4', cat: 'pdf', name: 'ISO_19650_BIM_Execution_Plan_Template.pdf', size: '3.8 MB', type: 'PDF', icon: '📄' }
];

function getAllCourses() {
  if (Array.isArray(window.SERVER_INITIAL_COURSES) && window.SERVER_INITIAL_COURSES.length > 0) {
    return window.SERVER_INITIAL_COURSES;
  }
  return DEFAULT_COURSES;
}

function getAllAdminCourses() {
  if (Array.isArray(window.ALL_ADMIN_COURSES) && window.ALL_ADMIN_COURSES.length > 0) {
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

/* ── PROGRESS TRACKING REALTIME ENGINE ── */
async function syncVideoProgress(videoId, percent, completed = false) {
  if (!videoId) return;
  try {
    const res = await fetch('api/progress.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ video_id: videoId, percent: percent, completed: completed })
    });
    const json = await res.json();
    if (json.success && json.summary) {
      window.INITIAL_PROGRESS = json.summary;
      updateProgressDisplay(json.summary);
      if (completed) {
        showToast('🎉 Lesson completed! Progress saved.', '#10b981');
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

function attachVideoTracking(videoEl, videoId) {
  if (!videoEl || !videoId) return;
  currentPlayingVideoId = videoId;
  currentPlayingVideoElement = videoEl;

  let lastReportedTier = 0;
  videoEl.addEventListener('timeupdate', () => {
    if (!videoEl.duration) return;
    const pct = Math.round((videoEl.currentTime / videoEl.duration) * 100);
    
    // Status pill in modal
    const pill = document.getElementById('vmodal-status-pill');
    if (pill) {
      pill.textContent = pct >= 90 ? '✓ Completed' : `Watching (${pct}%)`;
      pill.style.background = pct >= 90 ? '#ecfdf5' : '#eff6ff';
      pill.style.color = pct >= 90 ? '#059669' : '#1d4ed8';
    }

    // Report milestones (25%, 50%, 75%, 90%)
    if (pct >= 90 && lastReportedTier < 90) {
      lastReportedTier = 90;
      syncVideoProgress(videoId, pct, true);
    } else if (pct >= 50 && lastReportedTier < 50) {
      lastReportedTier = 50;
      syncVideoProgress(videoId, pct, false);
    }
  });

  videoEl.addEventListener('ended', () => {
    syncVideoProgress(videoId, 100, true);
  });
}

function renderEmployeeProgressChecklist() {
  const container = document.getElementById('my-videos-checklist');
  if (!container) return;

  const videos = window.SERVER_INITIAL_VIDEOS || [];
  const progressData = window.INITIAL_PROGRESS || {};
  const watchedMap = progressData.watched_videos || {};

  if (videos.length === 0) {
    container.innerHTML = '<div style="color:#64748b;padding:20px;text-align:center;">No videos assigned to your station yet.</div>';
    return;
  }

  container.innerHTML = videos.map(v => {
    const record = watchedMap[v.id] || {};
    const isDone = !!record.completed;
    const pct = record.percent || (isDone ? 100 : 0);
    const stationLabel = (v.station && v.station !== 'all') ? v.station.toUpperCase() : 'GENERAL';

    return `
      <div style="background:#f8fafc;border:1.5px solid ${isDone ? '#a7f3d0' : '#e2e8f0'};border-radius:14px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;align-items:center;gap:14px;min-width:240px;flex:1;">
          <div style="width:36px;height:36px;border-radius:50%;background:${isDone ? '#10b981' : '#e2e8f0'};color:${isDone ? 'white' : '#64748b'};display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;">
            ${isDone ? '✓' : '○'}
          </div>
          <div>
            <div style="font-size:15px;font-weight:800;color:#0f172a;">${v.title}</div>
            <div style="font-size:12px;color:#64748b;margin-top:2px;">
              ${v.cat.toUpperCase()} • ⏱ ${v.duration || 'HD'} • Station: <strong style="color:#2563eb;">${stationLabel}</strong>
            </div>
          </div>
        </div>

        <div style="display:flex;align-items:center;gap:16px;">
          <div style="width:120px;">
            <div style="display:flex;justify-content:space-between;font-size:11px;font-weight:700;color:#64748b;margin-bottom:4px;">
              <span>Progress</span><span>${pct}%</span>
            </div>
            <div style="background:#e2e8f0;height:6px;border-radius:9999px;overflow:hidden;">
              <div style="width:${pct}%;background:${isDone ? '#10b981' : '#2563eb'};height:100%;"></div>
            </div>
          </div>

          <button onclick="launchVideoPlayer('${v.ytId}','${encodeURIComponent(v.title)}','${encodeURIComponent(v.desc)}','${v.type || 'local'}','${v.id}','${v.station || 'all'}')" style="background:${isDone ? '#f1f5f9' : '#2563eb'};color:${isDone ? '#1e293b' : '#ffffff'};border:none;padding:8px 16px;border-radius:8px;font-size:12.5px;font-weight:700;cursor:pointer;">
            ${isDone ? 'Rewatch' : 'Watch Now →'}
          </button>
        </div>
      </div>
    `;
  }).join('');
}

/* ── NAVIGATION ── */
function showSection(name) {
  ['home','courses','videos','files','classroom','my-progress','team-progress','analytics','upload'].forEach(s => {
    const el = document.getElementById('section-' + s);
    if (el) el.style.display = (s === name) ? 'block' : 'none';

    const btn = document.getElementById('nav-' + s);
    if (btn) {
      if (s === name) btn.classList.add('active');
      else btn.classList.remove('active');
    }
  });

  if (name === 'home') renderHomeFeatured();
  if (name === 'courses') renderCoursesCatalog('all');
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
      attachVideoTracking(vidEl, videoId);
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

  const progressData = window.INITIAL_PROGRESS || {};
  const watchedMap = progressData.watched_videos || {};

  container.innerHTML = videos.map(v => {
    const isLocal = v.type === 'local' || v.ytId.includes('videos/');
    const isDone = watchedMap[v.id]?.completed;
    const pct = watchedMap[v.id]?.percent || 0;
    const thumbHtml = `<div style="width:100%;height:100%;background:linear-gradient(135deg,#1d4ed8,#0284c7);display:flex;flex-direction:column;align-items:center;justify-content:center;color:white;"><span style="font-size:40px;">🎬</span><span style="font-size:10px;font-weight:900;margin-top:4px;">HD VIDEO</span></div>`;

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
            ${isDone ? `<span style="position:absolute;top:10px;right:10px;background:#10b981;color:white;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:800;">✓ WATCHED</span>` : ''}
          </div>

          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <span class="badge" style="background:#eff6ff;color:#1d4ed8;">${v.cat.toUpperCase()}</span>
            <span style="font-size:11.5px;color:#0284c7;font-weight:700;">Station: ${(v.station || 'all').toUpperCase()}</span>
          </div>
          <h4 style="font-size:15px;font-weight:800;color:#0f172a;margin:0 0 10px;line-height:1.35;">${v.title}</h4>
        </div>

        <div style="padding-top:12px;border-top:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;">
          <span style="color:#1d4ed8;font-size:13px;font-weight:700;">Watch Lesson →</span>
          <span style="font-size:12px;color:#64748b;font-weight:600;">⏱ ${v.duration || 'HD'}</span>
        </div>
      </div>
    `;
  }).join('');
}

function switchVideoCategory(cat, btn) {
  document.querySelectorAll('.vtab-btn').forEach(b => {
    b.style.color = '#64748b'; b.style.borderBottom = 'none'; b.style.fontWeight = '600';
  });
  btn.style.color = '#2563eb'; btn.style.borderBottom = '3px solid #2563eb'; btn.style.fontWeight = '700';
  renderVideosCategory(cat);
}

/* ── COURSES RENDERER ── */
function renderHomeFeatured() {
  const container = document.getElementById('home-featured-courses');
  if (!container) return;
  const courses = getAllCourses().slice(0, 3);
  container.innerHTML = courses.map(c => `
    <div class="dashboard-card" onclick="openClassroom('${c.id}')" style="padding:22px;display:flex;flex-direction:column;justify-content:space-between;min-height:200px;">
      <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
          <div class="icon-squircle glow-blue"><span>${c.icon}</span></div>
          <span class="badge" style="background:#eff6ff;color:#1d4ed8;">${c.cat.toUpperCase()}</span>
        </div>
        <h3 style="font-size:16px;font-weight:800;color:#0f172a;margin:0 0 8px;">${c.title}</h3>
        <span style="font-size:12px;color:#64748b;">⏱ ${c.duration}</span>
      </div>
      <div style="padding-top:12px;border-top:1px solid #f1f5f9;display:flex;justify-content:space-between;">
        <span style="color:#1d4ed8;font-size:13px;font-weight:700;">Start Curriculum →</span>
        <span style="background:#f1f5f9;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;">${c.level}</span>
      </div>
    </div>
  `).join('');
}

function renderCoursesCatalog(filterCat) {
  const container = document.getElementById('catalog-courses-grid');
  if (!container) return;
  let list = getAllCourses();
  if (filterCat !== 'all') list = list.filter(c => c.cat === filterCat);
  container.innerHTML = list.map(c => `
    <div class="dashboard-card" onclick="openClassroom('${c.id}')" style="padding:22px;display:flex;flex-direction:column;justify-content:space-between;min-height:200px;">
      <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
          <div class="icon-squircle glow-blue"><span>${c.icon}</span></div>
          <span class="badge" style="background:#eff6ff;color:#1d4ed8;">${c.cat.toUpperCase()}</span>
        </div>
        <h3 style="font-size:16px;font-weight:800;color:#0f172a;margin:0 0 8px;">${c.title}</h3>
        <span style="font-size:12px;color:#64748b;">⏱ ${c.duration}</span>
      </div>
      <div style="padding-top:12px;border-top:1px solid #f1f5f9;display:flex;justify-content:space-between;">
        <span style="color:#1d4ed8;font-size:13px;font-weight:700;">Start Curriculum →</span>
        <span style="background:#f1f5f9;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;">${c.level}</span>
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

/* ── CLASSROOM ── */
let currentClassroomCourse = null;
let currentClassroomLessonIdx = 0;

function openClassroom(courseId, lessonIdx = 0) {
  const course = getAllCourses().find(c => c.id === courseId) || DEFAULT_COURSES[0];
  currentClassroomCourse = course;
  document.getElementById('classroom-course-title').textContent = course.title;
  renderClassroomPlaylist();
  loadClassroomLesson(lessonIdx);
  showSection('classroom');
}

function renderClassroomPlaylist() {
  const container = document.getElementById('classroom-playlist-items');
  if (!container || !currentClassroomCourse) return;
  const lessons = currentClassroomCourse.syllabus || [];
  container.innerHTML = lessons.map((l, idx) => `
    <div onclick="loadClassroomLesson(${idx})" style="padding:12px;border-radius:10px;background:${idx === currentClassroomLessonIdx ? '#eff6ff' : '#f8fafc'};border:1.5px solid ${idx === currentClassroomLessonIdx ? '#3b82f6' : '#e2e8f0'};cursor:pointer;">
      <div style="font-size:13px;font-weight:700;color:#0f172a;">${l.title}</div>
      <div style="font-size:11px;color:#64748b;margin-top:2px;">⏱ ${l.duration || 'HD'}</div>
    </div>
  `).join('');
}

function loadClassroomLesson(idx) {
  if (!currentClassroomCourse) return;
  currentClassroomLessonIdx = idx;
  const l = currentClassroomCourse.syllabus[idx];
  if (!l) return;
  document.getElementById('classroom-lesson-title').textContent = l.title;

  const player = document.getElementById('classroom-player');
  let cleanUrl = (l.ytId || '').replace(/^\.?\/?/, '');
  if (cleanUrl.startsWith('videos/')) {
    cleanUrl = 'api/stream.php?file=' + encodeURIComponent(cleanUrl);
  }
  player.innerHTML = `<video id="classroom-vid-el" src="${cleanUrl}" controls autoplay playsinline style="width:100%;height:100%;background:#000;border-radius:12px;"></video>`;
  const vidEl = document.getElementById('classroom-vid-el');
  if (vidEl) attachVideoTracking(vidEl, l.id || ('les_' + idx));
  renderClassroomPlaylist();
}

function toggleCurrentLessonCompletion() {
  if (!currentClassroomCourse) return;
  const l = currentClassroomCourse.syllabus[currentClassroomLessonIdx];
  if (l) {
    syncVideoProgress(l.id || ('les_' + currentClassroomLessonIdx), 100, true);
  }
}

/* ── PRACTICE FILES ── */
function renderPracticeFiles(filterCat = 'all') {
  const container = document.getElementById('practice-files-grid');
  if (!container) return;
  const list = getAllPracticeFiles();
  container.innerHTML = list.map(f => `
    <div class="dashboard-card" style="padding:20px;display:flex;flex-direction:column;justify-content:space-between;min-height:160px;">
      <div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
          <div class="icon-squircle glow-blue"><span>${f.icon}</span></div>
          <span class="badge" style="background:#eff6ff;color:#1d4ed8;">${f.type}</span>
        </div>
        <h4 style="font-size:14px;font-weight:800;color:#0f172a;margin:0 0 4px;">${f.name}</h4>
        <span style="font-size:11.5px;color:#64748b;">Size: ${f.size}</span>
      </div>
      <button onclick="showToast('📥 Downloading ${f.name}...', '#2563eb')" style="background:linear-gradient(135deg,#1d4ed8,#0284c7);color:white;border:none;padding:8px;border-radius:8px;font-size:12.5px;font-weight:700;cursor:pointer;margin-top:12px;">
        ⤓ Download Dataset
      </button>
    </div>
  `).join('');
}

function filterPracticeFiles(cat) { renderPracticeFiles(cat); }

/* ── ADMIN PUBLISHING, CURRICULA & STATION TARGETING ── */
let currentVideoUploadMode = 'file';
let selectedAdminVideoFile = null;
let currentVideoCurriculumMode = 'existing';

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
        btn.style.background = '#ffffff';
        btn.style.color = '#334155';
        btn.style.border = '1.5px solid #e2e8f0';
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
    return `<option value="${c.id}" ${isSel}>${c.icon || '📚'} ${c.title} (${lessonCount} lesson${lessonCount === 1 ? '' : 's'}) [Target: ${stationLabel}]</option>`;
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
          <span style="font-size:12px;color:#64748b;font-weight:600;">⏱ ${l.duration || 'HD'}</span>
        </div>
      `).join('')
      : '<div style="font-size:12px;color:#94a3b8;font-style:italic;padding:8px 0;">No lessons attached yet. Upload a video and assign it to this curriculum!</div>';

    return `
      <div style="background:#ffffff;border:1.5px solid #e2e8f0;border-radius:16px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.03);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
          <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:44px;height:44px;border-radius:12px;background:#eff6ff;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-size:22px;">
              ${c.icon || '🏗️'}
            </div>
            <div>
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <h4 style="font-size:16px;font-weight:900;color:#0f172a;margin:0;">${c.title}</h4>
                <span class="badge" style="background:#f1f5f9;color:#475569;">${c.level || 'Intermediate'}</span>
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
              ➕ Add Video Here
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
  showToast('🎯 Target curriculum selected! Choose video file to upload.', '#2563eb');
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
    showToast('⚠️ Curriculum Title is required', '#f59e0b');
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

    showToast(`🎉 Curriculum "${title}" created successfully!`, '#10b981');

  } catch (err) {
    console.error(err);
    showToast('❌ Failed: ' + err.message, '#ef4444');
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

function handleAdminVideoFilePick(input) {
  if (input.files && input.files[0]) {
    selectedAdminVideoFile = input.files[0];
    const lbl = document.getElementById('vfile-selected-name');
    if (lbl) lbl.textContent = selectedAdminVideoFile.name;
    const titleInput = document.getElementById('new-video-title');
    if (titleInput && !titleInput.value) {
      titleInput.value = selectedAdminVideoFile.name.replace(/\.[^/.]+$/, '').replace(/[_.-]+/g, ' ').toUpperCase();
    }
    const inlineTitle = document.getElementById('inline-curriculum-title');
    if (inlineTitle && !inlineTitle.value) {
      inlineTitle.value = titleInput.value + ' Masterclass';
    }
  }
}

async function adminPublishVideo() {
  const title = document.getElementById('new-video-title').value.trim();
  const station = document.getElementById('new-video-station').value;
  const cat = document.getElementById('new-video-cat').value;
  const duration = document.getElementById('new-video-duration').value.trim() || '20:00';
  const desc = document.getElementById('new-video-desc').value.trim() || 'Direct BIM demonstration.';

  if (!title) {
    showToast('⚠️ Video Title is required', '#f59e0b');
    return;
  }

  const curriculumMode = currentVideoCurriculumMode; // 'existing', 'new', 'none'
  const curriculumId = document.getElementById('new-video-curriculum-id')?.value || '';
  const newCurriculumTitle = document.getElementById('inline-curriculum-title')?.value.trim() || '';
  const newCurriculumLevel = document.getElementById('inline-curriculum-level')?.value || 'Intermediate';
  const newCurriculumIcon = document.getElementById('inline-curriculum-icon')?.value || '🏗️';
  const newCurriculumDesc = document.getElementById('inline-curriculum-desc')?.value.trim() || desc;

  if (curriculumMode === 'new' && !newCurriculumTitle) {
    showToast('⚠️ Please enter a Title for the new Curriculum Track', '#f59e0b');
    return;
  }

  showToast('⏳ Uploading video & assigning curriculum...', '#2563eb');

  try {
    let newVideoEntry = null;

    if (currentVideoUploadMode === 'file' && selectedAdminVideoFile) {
      const formData = new FormData();
      formData.append('video_file', selectedAdminVideoFile);
      formData.append('title', title);
      formData.append('category', cat);
      formData.append('station', station);
      formData.append('duration', duration);
      formData.append('description', desc);
      formData.append('curriculum_mode', curriculumMode);
      formData.append('curriculum_id', curriculumId);
      formData.append('new_curriculum_title', newCurriculumTitle);
      formData.append('new_curriculum_level', newCurriculumLevel);
      formData.append('new_curriculum_icon', newCurriculumIcon);
      formData.append('new_curriculum_desc', newCurriculumDesc);

      const res = await fetch('api/upload-video.php', { method: 'POST', body: formData });
      const json = await res.json();
      if (!json.success) throw new Error(json.error || 'Upload failed');
      newVideoEntry = json.video;
    } else {
      let ytId = '';
      if (currentVideoUploadMode === 'folder') {
        const fn = document.getElementById('new-video-folder-file').value.trim();
        ytId = fn.startsWith('videos/') ? fn : ('videos/' + fn);
      } else {
        ytId = document.getElementById('new-video-url').value.trim();
      }

      newVideoEntry = {
        id: 'vid_' + Date.now(),
        cat,
        station,
        title,
        desc,
        ytId,
        duration,
        type: ytId.includes('videos/') ? 'local' : 'yt'
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
            level: newCurriculumLevel,
            icon: newCurriculumIcon,
            duration: '1 lesson • ' + duration,
            desc: newCurriculumDesc,
            initial_video: newVideoEntry
          })
        });
      }
    }

    showToast(`✅ Video published and assigned to curriculum!`, '#10b981');
    setTimeout(() => location.reload(), 1200);

  } catch (err) {
    console.error(err);
    showToast('❌ Failed: ' + err.message, '#ef4444');
  }
}

async function renderAdminVideosList() {
  const container = document.getElementById('admin-video-manage-list');
  if (!container) return;
  const videos = await getAllVideos();
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

function filterAdminRoster(query) {
  const q = (query || '').toLowerCase().trim();
  const rows = document.querySelectorAll('#admin-roster-table .roster-row');
  rows.forEach(r => {
    const name = r.getAttribute('data-name') || '';
    r.style.display = (!q || name.includes(q)) ? '' : 'none';
  });
}

function filterAdminRosterStation(station) {
  const rows = document.querySelectorAll('#admin-roster-table .roster-row');
  rows.forEach(r => {
    const st = r.getAttribute('data-station') || '';
    r.style.display = (station === 'all' || st === station) ? '' : 'none';
  });
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

function showToast(msg, bg) {
  const t = document.createElement('div');
  t.className = 'toast';
  t.style.background = bg || '#2563eb';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => {
    t.style.opacity = '0';
    setTimeout(() => t.remove(), 300);
  }, 3500);
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
  if (window.USER_ROLE === 'team_leader') return 'team-progress';
  return 'my-progress';
}

document.addEventListener('DOMContentLoaded', () => {
  spawnHeroParticles();
  showSection(getRoleLandingSection());
  populateCurriculumDropdown();
  renderAdminCurriculaList();
});
</script>

</body>
</html>
