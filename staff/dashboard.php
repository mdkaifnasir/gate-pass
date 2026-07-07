<?php
// staff/dashboard.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

if (empty($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

// Auto-expire any ACTIVE passes whose expiry time has already passed
$pdo->exec("UPDATE gate_passes SET status = 'EXPIRED' WHERE status = 'ACTIVE' AND expires_at < NOW()");

// use a single query to get all three stats to minimize DB calls
$statsStmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM gate_logs) AS total_scans,
        (SELECT COUNT(*) FROM gate_logs WHERE DATE(scanned_at) = CURDATE()) AS todays_scans,
        (SELECT COUNT(*) FROM gate_passes) AS total_visitors
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$totalScans    = (int)($stats['total_scans'] ?? 0);
$todaysScans   = (int)($stats['todays_scans'] ?? 0);
$totalVisitors = (int)($stats['total_visitors'] ?? 0);

$today = date('Y-m-d');

// Support date filter via GET param (default = today)
$filterDate = $today;
if (!empty($_GET['date'])) {
    $d = $_GET['date'];
    // Validate format YYYY-MM-DD and must not be a future date
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $d <= $today) {
        $filterDate = $d;
    }
}
$isToday = ($filterDate === $today);

// Fetch entry logs for the selected date
$logsStmt = $pdo->prepare("
    SELECT gp.student_name,
           gp.institute_name,
           gp.mobile,
           gl.scanned_at
    FROM gate_logs gl
    JOIN gate_passes gp ON gl.pass_id = gp.id
    WHERE DATE(gl.scanned_at) = :filterDate
    ORDER BY gl.scanned_at DESC
");
$logsStmt->execute([':filterDate' => $filterDate]);
$logs = $logsStmt->fetchAll();

// Human-readable label for selected date
$filterDateLabel = $isToday ? 'Today' : date('d M Y', strtotime($filterDate));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gate Staff Dashboard - University Gate Pass</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
        }

        body {
            min-height: 100vh;
            background: #f8fafc;
            color: #0f172a;
            position: relative;
            padding-bottom: 60px;
        }

        /* Large Indigo top header band */
        .header-band {
            background: linear-gradient(135deg, #2b2fe6 0%, #171aa8 100%), url('../student/campus.png');
            background-size: cover;
            background-position: center;
            background-blend-mode: overlay;
            color: #ffffff;
            padding: 40px 24px 120px 24px;
            position: relative;
            z-index: 1;
        }

        /* Digital grid pattern over the band */
        .header-band::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px);
            background-size: 16px 16px;
            pointer-events: none;
        }

        /* Topbar containing menu, logo, clock and scan actions */
        .topbar-wrap {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Hamburger button */
        .hamburger-btn {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .hamburger-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }
        .hamburger-btn svg {
            width: 20px;
            height: 20px;
        }

        /* Circular chancellor badge */
        .staff-logo-badge {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            object-fit: cover;
        }

        .title-block h1 {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.01em;
        }
        .title-block p {
            font-size: 13.5px;
            color: rgba(255, 255, 255, 0.85);
            margin-top: 2px;
            font-weight: 500;
        }

        /* Scan and logout actions under title */
        .action-button-row {
            display: flex;
            gap: 12px;
            margin-top: 14px;
        }
        .action-btn {
            padding: 9px 18px;
            border-radius: 8px;
            border: none;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.15s ease;
        }
        .action-btn.scan {
            background: #ffffff;
            color: #1e1b4b;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .action-btn.scan:hover {
            background: #f1f5f9;
            transform: translateY(-1px);
        }
        .action-btn.logout {
            background: rgba(3, 7, 18, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }
        .action-btn.logout:hover {
            background: rgba(3, 7, 18, 0.6);
            transform: translateY(-1px);
        }

        /* Live Clock floating container */
        .live-clock-pill {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: right;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            color: #ffffff;
        }
        .live-clock-pill svg {
            width: 22px;
            height: 22px;
            color: rgba(255, 255, 255, 0.9);
        }
        .clock-texts {
            display: flex;
            flex-direction: column;
            line-height: 1.25;
        }
        .clock-date {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .clock-time {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        /* Main content container overlapping the band */
        .main-container {
            max-width: 1100px;
            margin: -80px auto 0 auto;
            padding: 0 24px;
            position: relative;
            z-index: 10;
        }

        /* Section Headings with thick vertical blue line */
        .section-heading {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 17px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 16px;
            margin-top: 32px;
        }
        .section-heading::before {
            content: '';
            width: 4px;
            height: 18px;
            background: #4F46E5;
            border-radius: 4px;
            display: inline-block;
        }

        .section-heading.first {
            margin-top: 0;
            color: #0f172a;
        }

        /* Statistics Cards Grid Row */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        /* Bottom waves inside the stats cards */
        .stat-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 35%;
            mask-image: radial-gradient(circle at center, black 1px, transparent 1px);
            background: radial-gradient(ellipse at 50% 100%, rgba(79, 70, 229, 0.05), transparent 70%);
            z-index: -1;
            pointer-events: none;
        }
        
        .stat-card.today-scans::after {
            background: radial-gradient(ellipse at 50% 100%, rgba(16, 185, 129, 0.05), transparent 70%);
        }
        
        .stat-card.total-visitors::after {
            background: radial-gradient(ellipse at 50% 100%, rgba(59, 130, 246, 0.05), transparent 70%);
        }

        .stat-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-badge {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }
        .stat-badge.purple {
            background: #e0e7ff;
            color: #4f46e5;
        }
        .stat-badge.green {
            background: #d1fae5;
            color: #10b981;
        }
        .stat-badge.blue {
            background: #dbeafe;
            color: #2563eb;
        }
        .stat-badge svg {
            width: 22px;
            height: 22px;
        }

        .stat-info {
            display: flex;
            flex-direction: column;
        }
        .stat-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
        }
        .stat-number {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            margin: 2px 0;
        }
        .stat-desc {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 600;
        }

        /* Entry logs table container */
        .logs-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
            border: 1px solid #f1f5f9;
            margin-bottom: 24px;
        }
        
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        .logs-title-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }
        .logs-title-wrap svg {
            width: 20px;
            height: 20px;
            color: #4f46e5;
        }

        .export-csv-btn {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            padding: 8px 16px;
            border-radius: 8px;
            color: #334155;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }
        .export-csv-btn:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }
        .export-csv-btn svg {
            width: 14px;
            height: 14px;
        }

        /* Date filter bar inside logs card */
        .logs-date-filter {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
            padding: 12px 16px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        .logs-date-filter label {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            white-space: nowrap;
        }
        .logs-date-input {
            padding: 8px 12px;
            border-radius: 9px;
            border: 1.5px solid #cbd5e1;
            font-size: 13px;
            font-weight: 500;
            color: #0f172a;
            outline: none;
            background: #ffffff;
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .logs-date-input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .logs-today-btn {
            padding: 8px 14px;
            border-radius: 9px;
            border: 1.5px solid #cbd5e1;
            background: #ffffff;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .logs-today-btn:hover {
            background: #4f46e5;
            border-color: #4f46e5;
            color: #ffffff;
        }
        .logs-date-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
        }

        /* Entry Logs Data Table */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-radius: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            text-align: left;
        }
        th {
            background: #f4f6fc;
            padding: 12px 14px;
            color: #475569;
            font-weight: 700;
            font-size: 12.5px;
            border-bottom: 1.5px solid #e2e8f0;
        }
        td {
            padding: 14px 14px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 500;
        }
        tr:hover td {
            background: #f8fafc;
        }

        /* Scanned status badge indicator */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #d1fae5;
            color: #065f46;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        /* Checklist placeholder SVG illustration for empty records */
        .empty-illustration-container {
            text-align: center;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .empty-illustration-container svg {
            width: 140px;
            height: 140px;
            margin-bottom: 16px;
        }
        .empty-illustration-container h3 {
            font-size: 16px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .empty-illustration-container p {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        /* Quick Action navigation panels */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        .action-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 16px 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(79, 70, 229, 0.06);
            border-color: #cbd5e1;
        }
        
        .action-card.purple-action:hover {
            border-color: #dbeafe;
            background: #fafcff;
        }
        .action-card.green-action:hover {
            border-color: #d1fae5;
            background: #fbfdfa;
        }
        .action-card.blue-action:hover {
            border-color: #dbeafe;
            background: #fafcff;
        }

        .action-card-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .action-icon-badge {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .purple-action .action-icon-badge {
            background: #f5f3ff;
            color: #6366f1;
        }
        .green-action .action-icon-badge {
            background: #ecfdf5;
            color: #10b981;
        }
        .blue-action .action-icon-badge {
            background: #eff6ff;
            color: #3b82f6;
        }

        .action-icon-badge svg {
            width: 20px;
            height: 20px;
        }
        .action-card-texts {
            display: flex;
            flex-direction: column;
        }
        .action-card-title {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
        }
        
        .purple-action .action-card-title { color: #4f46e5; }
        .green-action .action-card-title { color: #047857; }
        .blue-action .action-card-title { color: #1d4ed8; }

        .action-card-desc {
            font-size: 11.5px;
            color: #64748b;
            font-weight: 500;
            margin-top: 2px;
        }
        .action-chevron {
            color: #cbd5e1;
            transition: color 0.15s ease;
            flex-shrink: 0;
        }
        .action-card:hover .action-chevron {
            color: #4f46e5;
        }

        /* Secure access system alerts status footer */
        .system-secure-alert {
            background: #eff6ff;
            border: 1.5px solid #dbeafe;
            border-radius: 14px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 10px;
        }
        .sec-alert-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .sec-alert-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #dbeafe;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .sec-alert-circle svg {
            width: 18px;
            height: 18px;
        }
        .sec-alert-texts h4 {
            font-size: 13.5px;
            font-weight: 700;
            color: #1e3a8a;
        }
        .sec-alert-texts p {
            font-size: 11.5px;
            color: #475569;
            margin-top: 2px;
            font-weight: 500;
        }
        .sec-status-pill {
            display: flex;
            align-items: center;
            gap: 5px;
            background: #d1fae5;
            color: #065f46;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            flex-shrink: 0;
        }

        /* Mobile View Responsive Breakpoints */
        @media (max-width: 820px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .header-band {
                padding: 30px 16px 100px 16px;
            }
            .topbar-wrap {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            .live-clock-pill {
                align-self: flex-start;
                text-align: left;
            }
            .main-container {
                padding: 0 14px;
                margin-top: -65px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .actions-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .system-secure-alert {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .sec-status-pill {
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>

<!-- University Header band -->
<div class="header-band">
    <div class="topbar-wrap">
        <div class="topbar-left">
            <button class="hamburger-btn" onclick="alert('Menu options: User profile, Logs database, Campus map.')" title="Open navigation menu">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <img src="../student/logo.png" alt="University Chancellor Logo" class="staff-logo-badge">
            <div class="title-block">
                <h1>Gate Staff Dashboard</h1>
                <p>Manage and monitor gate entries.</p>
                <div class="action-button-row">
                    <button class="action-btn scan" onclick="window.location.href='scan.php'">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="width:16px; height:16px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Scan QR
                    </button>
                    <button class="action-btn logout" onclick="window.location.href='logout.php'">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="width:16px; height:16px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Logout
                    </button>
                </div>
            </div>
        </div>

        <!-- Floating Live Dynamic Date & Time -->
        <div class="live-clock-pill">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="clock-texts">
                <span class="clock-date" id="live-date">-- --- ----</span>
                <span class="clock-time" id="live-time">--:-- --</span>
            </div>
        </div>
    </div>
</div>

<!-- Overlapping Dashboard Contents -->
<div class="main-container">

    <!-- Overview Statistics section -->
    <div class="section-heading first">Overview</div>
    <div class="stats-grid">
        
        <!-- Total Scans -->
        <div class="stat-card">
            <div class="stat-left">
                <div class="stat-badge purple">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2a2 2 0 002-2v-5a2 2 0 00-2-2H4a2 2 0 00-2 2v5a2 2 0 002 2h2m4 4H8m8 0h-2m-4-8a3 3 0 116 0 3 3 0 01-6 0z" />
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Total Scans</span>
                    <span class="stat-number"><?= $totalScans ?></span>
                    <span class="stat-desc">All time gate scans</span>
                </div>
            </div>
        </div>

        <!-- Today's Scans -->
        <div class="stat-card today-scans">
            <div class="stat-left">
                <div class="stat-badge green">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Today's Scans</span>
                    <span class="stat-number"><?= $todaysScans ?></span>
                    <span class="stat-desc">Scans done today</span>
                </div>
            </div>
        </div>

        <!-- Total Visitors -->
        <div class="stat-card total-visitors">
            <div class="stat-left">
                <div class="stat-badge blue">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-label">Total Visitors Registered</span>
                    <span class="stat-number"><?= $totalVisitors ?></span>
                    <span class="stat-desc">All registered visitors</span>
                </div>
            </div>
        </div>

    </div>

    <!-- entry logs table box -->
    <div class="logs-card">
        <div class="logs-header">
            <div class="logs-title-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Admission Visitor Entry Logs
            </div>
            <button class="export-csv-btn" onclick="window.location.href='export_csv.php'" title="Download Entry logs in CSV file">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Export CSV
            </button>
        </div>

        <!-- Date Filter Bar -->
        <form method="GET" action="dashboard.php" class="logs-date-filter">
            <label for="log-date-picker">📅 Filter by date:</label>
            <input
                type="date"
                id="log-date-picker"
                name="date"
                class="logs-date-input"
                value="<?= htmlspecialchars($filterDate) ?>"
                max="<?= $today ?>"
                onchange="this.form.submit()"
            >
            <?php if (!$isToday): ?>
            <button type="button" class="logs-today-btn" onclick="window.location.href='dashboard.php'">
                ↩ Back to Today
            </button>
            <?php endif; ?>
            <span class="logs-date-badge">
                📋 Showing: <?= htmlspecialchars($filterDateLabel) ?> (<?= count($logs) ?> scans)
            </span>
        </form>

        <?php if (empty($logs)): ?>
            <!-- Empty state for selected date -->
            <div class="empty-illustration-container">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="none">
                    <rect x="25" y="15" width="50" height="70" rx="6" fill="#eff6ff" stroke="#bfdbfe" stroke-width="2"></rect>
                    <rect x="35" y="32" width="22" height="4" rx="2" fill="#bfdbfe"></rect>
                    <rect x="35" y="44" width="30" height="4" rx="2" fill="#dbeafe"></rect>
                    <rect x="35" y="56" width="16" height="4" rx="2" fill="#dbeafe"></rect>
                    <!-- clipboard top binder clip -->
                    <rect x="42" y="10" width="16" height="8" rx="2" fill="#a5b4fc" stroke="#6366f1" stroke-width="1.5"></rect>
                    <circle cx="50" cy="14" r="2" fill="#ffffff"></circle>
                    <!-- magnifying glass scanning checklist -->
                    <circle cx="62" cy="62" r="10" fill="#ffffff" stroke="#818cf8" stroke-width="2.5"></circle>
                    <line x1="69" y1="69" x2="82" y2="82" stroke="#818cf8" stroke-width="3.5" stroke-linecap="round"></line>
                </svg>
                <h3>No scans on <?= htmlspecialchars($filterDateLabel) ?>.</h3>
                <p><?= $isToday ? 'All visitor entries scanned today will appear here.' : 'No gate pass scans were recorded on this date.' ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Mobile Number</th>
                        <th>Scanned At</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['student_name']) ?></td>
                            <td><?= htmlspecialchars($log['mobile']) ?></td>
                            <!-- Beautiful date-time formatting -->
                            <td><?= date('d M Y, h:i A', strtotime($log['scanned_at'])) ?></td>
                            <td>
                                <span class="status-badge">
                                    <span style="font-size:7px; vertical-align: middle;">●</span> Scanned
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions Panel -->
    <div class="section-heading">Quick Actions</div>
    <div class="actions-grid">
        
        <!-- Action 1: Scan QR Code -->
        <a href="scan.php" class="action-card purple-action">
            <div class="action-card-left">
                <div class="action-icon-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2a2 2 0 002-2v-5a2 2 0 00-2-2H4a2 2 0 00-2 2v5a2 2 0 002 2h2m4 4H8m8 0h-2m-4-8a3 3 0 116 0 3 3 0 01-6 0z" />
                    </svg>
                </div>
                <div class="action-card-texts">
                    <span class="action-card-title">Scan QR Code</span>
                    <span class="action-card-desc">Scan visitor gate pass QR code</span>
                </div>
            </div>
            <svg class="action-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" style="width: 14px; height: 14px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </a>

        <!-- Action 2: View All Visitors -->
        <a href="view_pass.php" class="action-card green-action">
            <div class="action-card-left">
                <div class="action-icon-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div class="action-card-texts">
                    <span class="action-card-title">View All Visitors</span>
                    <span class="action-card-desc">View all registered visitors</span>
                </div>
            </div>
            <svg class="action-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" style="width: 14px; height: 14px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </a>

        <!-- Action 3: Export Reports -->
        <a href="export_csv.php" class="action-card blue-action">
            <div class="action-card-left">
                <div class="action-icon-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                </div>
                <div class="action-card-texts">
                    <span class="action-card-title">Export Reports</span>
                    <span class="action-card-desc">Download entry logs in CSV</span>
                </div>
            </div>
            <svg class="action-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" style="width: 14px; height: 14px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </a>

        <!-- Action 4: June Enquiry Report -->
        <a href="june_enquiry_report.php" class="action-card" style="border-color:#f1f5f9; background:#fff;">
            <div class="action-card-left">
                <div class="action-icon-badge" style="background:#fef3c7; color:#d97706;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div class="action-card-texts">
                    <span class="action-card-title" style="color:#b45309;">June Enquiry Report</span>
                    <span class="action-card-desc">June 2026 admissions — view &amp; Excel</span>
                </div>
            </div>
            <svg class="action-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" style="width: 14px; height: 14px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </a>

    </div>

    <!-- Floating System Secure alert footer -->
    <div class="system-secure-alert">
        <div class="sec-alert-left">
            <div class="sec-alert-circle">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <div class="sec-alert-texts">
                <h4>Secure Access</h4>
                <p>You are logged in as authorized staff.</p>
            </div>
        </div>
        <div class="sec-status-pill">
            <span style="font-size:6px; vertical-align: middle;">●</span> System Secure
        </div>
    </div>

</div>

<!-- Realtime clock formatting javascript -->
<script>
function updateClock() {
    const now = new Date();
    const optionsDate = { day: 'numeric', month: 'short', year: 'numeric' };
    const dateString = now.toLocaleDateString('en-US', optionsDate); // e.g. "18 May 2026"
    
    let hours = now.getHours();
    let minutes = now.getMinutes();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // hour '0' should be '12'
    minutes = minutes < 10 ? '0' + minutes : minutes;
    const timeString = (hours < 10 ? '0' + hours : hours) + ':' + minutes + ' ' + ampm;
    
    document.getElementById('live-date').innerText = dateString;
    document.getElementById('live-time').innerText = timeString;
}
setInterval(updateClock, 1000);
updateClock(); // run initially
</script>
</body>
</html>
