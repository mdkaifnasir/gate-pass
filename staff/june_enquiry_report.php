<?php
// staff/june_enquiry_report.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

if (empty($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

// ── Query all June 2026 Admission Enquiry records ────────────────────────────
// Try live DB first; if empty or fails, fall back to the local CSV snapshot.
$enquiries  = [];
$dataSource = 'database'; // 'database' | 'csv'
$csvPath    = __DIR__ . '/../admission_enquiries.csv';
$csvMaxDate = '';

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            student_name,
            mobile,
            purpose,
            status,
            created_at
        FROM gate_passes
        WHERE created_at >= '2026-06-01 00:00:00'
          AND created_at <= '2026-06-30 23:59:59'
          AND (roll_no = 'Admission Visitor' OR purpose LIKE '%Admission Enquiry%' OR purpose LIKE '%Admission%')
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $enquiries = $stmt->fetchAll();
} catch (Exception $e) {
    $enquiries = [];
}

// If DB returned data, use it; otherwise load CSV
if (!empty($enquiries)) {
    $dataSource = 'database';
} elseif (file_exists($csvPath)) {
    $dataSource = 'csv';
    $enquiries  = [];
    if (($fh = fopen($csvPath, 'r')) !== false) {
        $headers = fgetcsv($fh); // skip header
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 4) continue;
            // CSV columns: Student Name, Mobile, Purpose of Visit, Registered On, Status
            $dateRaw = trim($row[3]); // e.g. '18 Jun 2026, 12:04 PM'
            $ts = strtotime($dateRaw);
            if ($ts === false) continue;
            $ymd = date('Y-m-d', $ts);
            // Only June 2026
            if ($ymd < '2026-06-01' || $ymd > '2026-06-30') continue;
            $enquiries[] = [
                'id'          => '',
                'student_name'=> trim($row[0]),
                'mobile'      => trim($row[1]),
                'purpose'     => trim($row[2]),
                'status'      => strtoupper(trim($row[4] ?? 'EXPIRED')),
                'created_at'  => date('Y-m-d H:i:s', $ts),
            ];
            if (!$csvMaxDate || $ymd > $csvMaxDate) $csvMaxDate = $ymd;
        }
        fclose($fh);
    }
    // Sort by date DESC
    usort($enquiries, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
}

$total        = count($enquiries);
$activeCount  = 0;
$expiredCount = 0;
$usedCount    = 0;

// Build day-wise count map
$dayMap = [];
foreach ($enquiries as $row) {
    $s = strtoupper($row['status'] ?? 'EXPIRED');
    if ($s === 'ACTIVE')   $activeCount++;
    elseif ($s === 'USED') $usedCount++;
    else                   $expiredCount++;

    $day = date('d M', strtotime($row['created_at']));
    $dayMap[$day] = ($dayMap[$day] ?? 0) + 1;
}
krsort($dayMap); // latest first

// Peak day
$peakDay = '';
$peakCount = 0;
foreach ($dayMap as $d => $c) {
    if ($c > $peakCount) { $peakDay = $d; $peakCount = $c; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>June 2026 – Admission Enquiry Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            min-height: 100vh;
        }

        /* ── Header ── */
        .hero {
            background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 50%, #7c3aed 100%);
            padding: 36px 32px 110px;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(rgba(255,255,255,0.06) 1px, transparent 1px);
            background-size: 20px 20px;
            pointer-events: none;
        }
        .hero-inner {
            max-width: 1180px;
            margin: 0 auto;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            z-index: 1;
        }
        .hero-left a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: rgba(255,255,255,0.75);
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 14px;
            transition: color .15s;
        }
        .hero-left a:hover { color: #fff; }
        .hero-left h1 {
            font-size: 28px;
            font-weight: 900;
            color: #fff;
            line-height: 1.15;
            letter-spacing: -.02em;
        }
        .hero-left p {
            color: rgba(255,255,255,.78);
            font-size: 13.5px;
            margin-top: 6px;
            font-weight: 500;
        }
        .hero-badge {
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 10px;
            padding: 10px 18px;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(6px);
        }
        .hero-badge span { font-size: 22px; font-weight: 900; }

        /* ── Export buttons in hero ── */
        .export-row {
            display: flex;
            gap: 12px;
            margin-top: 18px;
            flex-wrap: wrap;
        }
        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all .15s ease;
        }
        .btn-excel {
            background: #16a34a;
            color: #fff;
            box-shadow: 0 4px 14px rgba(22,163,74,.35);
        }
        .btn-excel:hover { background: #15803d; transform: translateY(-1px); }
        .btn-csv {
            background: rgba(255,255,255,.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,.25);
            backdrop-filter: blur(4px);
        }
        .btn-csv:hover { background: rgba(255,255,255,.25); transform: translateY(-1px); }

        /* ── Main Content ── */
        .main {
            max-width: 1180px;
            margin: -80px auto 48px;
            padding: 0 24px;
            position: relative;
            z-index: 10;
        }

        /* ── Stat Cards ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,.04);
            border: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .stat-icon {
            width: 42px; height: 42px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 8px;
            font-size: 20px;
        }
        .ic-blue { background: #eff6ff; }
        .ic-green { background: #f0fdf4; }
        .ic-amber { background: #fffbeb; }
        .ic-rose  { background: #fff1f2; }
        .ic-purple { background: #f5f3ff; }
        .stat-label { font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .stat-value { font-size: 30px; font-weight: 900; color: #0f172a; line-height: 1; }
        .stat-sub   { font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 2px; }

        /* ── Day-wise Bar Chart Card ── */
        .chart-card {
            background: #fff;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,.04);
            border: 1px solid #f1f5f9;
            margin-bottom: 24px;
        }
        .card-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-title::before {
            content: '';
            width: 4px; height: 18px;
            background: linear-gradient(180deg, #1d4ed8, #7c3aed);
            border-radius: 4px;
        }
        .bar-chart-wrap {
            display: flex;
            gap: 5px;
            align-items: flex-end;
            height: 140px;
            overflow-x: auto;
            padding-bottom: 24px;
            position: relative;
        }
        .bar-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            flex: 1;
            min-width: 28px;
        }
        .bar {
            width: 100%;
            background: linear-gradient(180deg, #6366f1, #1d4ed8);
            border-radius: 4px 4px 0 0;
            transition: opacity .2s;
            cursor: default;
            position: relative;
        }
        .bar:hover { opacity: .8; }
        .bar-count {
            font-size: 9px;
            font-weight: 800;
            color: #475569;
            white-space: nowrap;
        }
        .bar-label {
            font-size: 9px;
            font-weight: 700;
            color: #94a3b8;
            white-space: nowrap;
        }

        /* ── Table Card ── */
        .table-card {
            background: #fff;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,.04);
            border: 1px solid #f1f5f9;
        }
        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 9px;
            padding: 8px 14px;
        }
        .search-box svg { width: 16px; height: 16px; color: #94a3b8; flex-shrink: 0; }
        .search-box input {
            border: none; background: transparent;
            font-size: 13px; font-weight: 500; color: #0f172a;
            outline: none; width: 220px;
        }
        .search-box input::placeholder { color: #94a3b8; }

        .table-responsive { overflow-x: auto; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead th {
            background: #f4f6fc;
            padding: 11px 14px;
            text-align: left;
            font-weight: 800;
            font-size: 11.5px;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .04em;
            border-bottom: 1.5px solid #e2e8f0;
            white-space: nowrap;
        }
        tbody td {
            padding: 13px 14px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 500;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #f8fafc; }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10.5px;
            font-weight: 700;
        }
        .pill-green  { background: #dcfce7; color: #166534; }
        .pill-amber  { background: #fef9c3; color: #854d0e; }
        .pill-slate  { background: #f1f5f9; color: #475569; }
        .pill-red    { background: #fee2e2; color: #991b1b; }

        /* ── Pagination ── */
        .pag-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .pag-info { font-size: 12px; color: #64748b; font-weight: 600; }
        .pag-btns { display: flex; gap: 6px; }
        .pag-btn {
            padding: 6px 13px;
            border-radius: 7px;
            border: 1.5px solid #e2e8f0;
            background: #fff;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
            transition: all .15s;
        }
        .pag-btn:hover, .pag-btn.active {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
        }
        .pag-btn:disabled { opacity: .4; cursor: not-allowed; }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(3,1fr); }
        }
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: repeat(2,1fr); }
            .hero { padding: 28px 18px 100px; }
            .hero-left h1 { font-size: 22px; }
            .main { padding: 0 14px; }
        }
    </style>
</head>
<body>

<!-- ══ Hero Header ══════════════════════════════════════════════════════════ -->
<div class="hero">
    <div class="hero-inner">
        <div class="hero-left">
            <a href="dashboard.php">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Back to Dashboard
            </a>
            <h1>📋 June 2026 — Admission Enquiry Report</h1>
            <p>All admission enquiry registrations received during June 2026 &nbsp;·&nbsp; Dr. P.A. Inamdar University</p>

            <div class="export-row">
                <a class="export-btn btn-excel" href="export_june_excel.php" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download Excel (.xlsx)
                </a>
                <a class="export-btn btn-csv" href="export_june_csv.php" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Download CSV
                </a>
            </div>
        </div>

        <div class="hero-badge">
            📅 &nbsp;<div><div style="font-size:11px;opacity:.8;">Total Enquiries</div><span><?= number_format($total) ?></span></div>
        </div>
    </div>
</div>

<!-- ══ Main Content ═════════════════════════════════════════════════════════ -->
<div class="main">

    <!-- Data source notice banner -->
    <?php if ($dataSource === 'csv'): ?>
    <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;font-size:13px;">
        <span style="font-size:20px;">⚠️</span>
        <div>
            <strong style="color:#92400e;">Local snapshot data (CSV) — June 1–18 only</strong><br>
            <span style="color:#78350f;">The live database is not reachable from this machine. Data is sourced from the local <code>admission_enquiries.csv</code> snapshot, which covers <strong>June 1–18 Jun 2026</strong> (<?= number_format($total) ?> unique entries). To get <strong>June 19–30</strong> data, access this report from the live server.</span>
        </div>
    </div>
    <?php else: ?>
    <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:12px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;font-size:13px;">
        <span style="font-size:20px;">✅</span>
        <div>
            <strong style="color:#166534;">Live database — full June 2026 data</strong><br>
            <span style="color:#15803d;">Showing all <?= number_format($total) ?> admission enquiries from June 1–30, 2026 from the live database.</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon ic-blue">📊</div>
            <div class="stat-label">Total Enquiries</div>
            <div class="stat-value"><?= number_format($total) ?></div>
            <div class="stat-sub">June 2026</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon ic-green">✅</div>
            <div class="stat-label">Active Passes</div>
            <div class="stat-value"><?= number_format($activeCount) ?></div>
            <div class="stat-sub">Still valid</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon ic-amber">🏁</div>
            <div class="stat-label">Used / Scanned</div>
            <div class="stat-value"><?= number_format($usedCount) ?></div>
            <div class="stat-sub">Gate entry recorded</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon ic-rose">⏰</div>
            <div class="stat-label">Expired</div>
            <div class="stat-value"><?= number_format($expiredCount) ?></div>
            <div class="stat-sub">Pass expired</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon ic-purple">🏆</div>
            <div class="stat-label">Peak Day</div>
            <div class="stat-value" style="font-size:18px;margin-top:2px;"><?= $peakDay ?: '—' ?></div>
            <div class="stat-sub"><?= $peakCount ? number_format($peakCount) . ' enquiries' : '' ?></div>
        </div>
    </div>

    <!-- Day-wise Bar Chart -->
    <div class="chart-card">
        <div class="card-title">Day-wise Enquiry Distribution — June 2026</div>
        <?php
        // Sort dayMap by day number
        $sortedDayMap = $dayMap;
        uksort($sortedDayMap, function($a, $b) {
            return (int)$a - (int)$b;
        });
        $maxBar = max(array_values($sortedDayMap) ?: [1]);
        ?>
        <div class="bar-chart-wrap">
            <?php foreach ($sortedDayMap as $day => $cnt): 
                $h = max(8, round(($cnt / $maxBar) * 120));
            ?>
            <div class="bar-col">
                <div class="bar-count"><?= $cnt ?></div>
                <div class="bar" style="height:<?= $h ?>px;" title="<?= $day ?>: <?= $cnt ?> enquiries"></div>
                <div class="bar-label"><?= substr($day, 0, 2) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="font-size:10.5px;color:#94a3b8;font-weight:600;margin-top:4px;">Date (June 2026)</div>
    </div>

    <!-- Data Table -->
    <div class="table-card">
        <div class="table-header">
            <div class="card-title" style="margin-bottom:0;">All June 2026 Enquiries</div>
            <div class="search-box">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35"/></svg>
                <input type="text" id="searchInput" placeholder="Search name or mobile…" oninput="filterTable()">
            </div>
        </div>

        <div class="table-responsive">
            <table id="enquiryTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Mobile Number</th>
                        <th>Purpose</th>
                        <th>Date &amp; Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                <?php foreach ($enquiries as $i => $row):
                    $s = strtoupper($row['status'] ?? 'EXPIRED');
                    $pillClass = match($s) {
                        'ACTIVE'  => 'pill-green',
                        'USED'    => 'pill-amber',
                        'EXPIRED' => 'pill-slate',
                        default   => 'pill-slate'
                    };
                    $emoji = match($s) {
                        'ACTIVE'  => '🟢',
                        'USED'    => '🏁',
                        'EXPIRED' => '⏰',
                        default   => '⚪'
                    };
                    // Clean purpose display
                    $purposeClean = $row['purpose'];
                    $purposeClean = preg_replace('/\s*\|\s*Date:\s*\S+/', '', $purposeClean);
                    $purposeClean = preg_replace('/^Purpose:\s*/i', '', $purposeClean);
                ?>
                <tr data-name="<?= strtolower(htmlspecialchars($row['student_name'])) ?>" data-mobile="<?= htmlspecialchars($row['mobile']) ?>">
                    <td style="color:#94a3b8;font-weight:700;"><?= $i + 1 ?></td>
                    <td style="font-weight:700;color:#0f172a;"><?= htmlspecialchars($row['student_name']) ?></td>
                    <td><?= htmlspecialchars($row['mobile']) ?></td>
                    <td style="color:#475569;font-size:12px;"><?= htmlspecialchars($purposeClean) ?></td>
                    <td style="white-space:nowrap;"><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></td>
                    <td><span class="pill <?= $pillClass ?>"><?= $emoji ?> <?= $s ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination controls -->
        <div class="pag-wrap">
            <div class="pag-info" id="pagInfo"></div>
            <div class="pag-btns" id="pagBtns"></div>
        </div>
    </div>

</div><!-- /main -->

<script>
// ── Simple client-side search + pagination ──────────────────────────────────
const ROWS_PER_PAGE = 50;
let currentPage = 1;
let filteredRows = [];

const tbody = document.getElementById('tableBody');
const allRows = Array.from(tbody.querySelectorAll('tr'));

function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase().trim();
    filteredRows = allRows.filter(row => {
        if (!q) return true;
        return row.dataset.name.includes(q) || row.dataset.mobile.includes(q);
    });
    currentPage = 1;
    renderPage();
}

function renderPage() {
    const total = filteredRows.length;
    const pages = Math.ceil(total / ROWS_PER_PAGE) || 1;
    currentPage = Math.min(currentPage, pages);

    const start = (currentPage - 1) * ROWS_PER_PAGE;
    const end   = start + ROWS_PER_PAGE;

    allRows.forEach(r => r.style.display = 'none');
    filteredRows.slice(start, end).forEach(r => r.style.display = '');

    document.getElementById('pagInfo').textContent =
        `Showing ${start + 1}–${Math.min(end, total)} of ${total.toLocaleString()} records`;

    // Pagination buttons (show up to 7 page numbers)
    const btnWrap = document.getElementById('pagBtns');
    btnWrap.innerHTML = '';

    const addBtn = (label, page, disabled, active) => {
        const b = document.createElement('button');
        b.className = 'pag-btn' + (active ? ' active' : '');
        b.textContent = label;
        b.disabled = disabled;
        b.onclick = () => { currentPage = page; renderPage(); };
        btnWrap.appendChild(b);
    };

    addBtn('«', 1, currentPage === 1, false);
    addBtn('‹', currentPage - 1, currentPage === 1, false);

    const range = pagRange(currentPage, pages);
    range.forEach(p => {
        if (p === '…') {
            const sp = document.createElement('span');
            sp.textContent = '…';
            sp.style.cssText = 'padding:6px 4px;font-size:12px;color:#94a3b8;';
            btnWrap.appendChild(sp);
        } else {
            addBtn(p, p, false, p === currentPage);
        }
    });

    addBtn('›', currentPage + 1, currentPage === pages, false);
    addBtn('»', pages, currentPage === pages, false);
}

function pagRange(cur, total) {
    if (total <= 7) return Array.from({length: total}, (_, i) => i + 1);
    const pages = [];
    if (cur <= 4) {
        for (let i = 1; i <= Math.min(5, total); i++) pages.push(i);
        pages.push('…', total);
    } else if (cur >= total - 3) {
        pages.push(1, '…');
        for (let i = total - 4; i <= total; i++) pages.push(i);
    } else {
        pages.push(1, '…', cur - 1, cur, cur + 1, '…', total);
    }
    return pages;
}

// Init
filteredRows = [...allRows];
renderPage();
</script>

</body>
</html>
