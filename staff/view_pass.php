<?php
// staff/view_pass.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

if (empty($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$token = $_GET['token'] ?? '';

// IF TOKEN IS EMPTY: RENDER THE VISITOR DIRECTORY
if ($token === '') {
    // Auto-expire any ACTIVE passes whose expiry time has already passed
    $pdo->exec("UPDATE gate_passes SET status = 'EXPIRED' WHERE status = 'ACTIVE' AND expires_at < NOW()");

    // Fetch all registered visitor passes from the database
    $stmt = $pdo->query("SELECT * FROM gate_passes ORDER BY created_at DESC");
    $passes = $stmt->fetchAll();

    // Extract unique clean purposes for filtering
    $unique_purposes = [];
    foreach ($passes as $p) {
        $p_str = $p['purpose'];
        if (preg_match('/^Purpose:\s*(.*?)\s*\|\s*Date:/i', $p_str, $matches)) {
            $purpose_clean = trim($matches[1]);
        } else {
            $purpose_clean = trim($p_str);
        }
        if ($purpose_clean !== '' && !in_array($purpose_clean, $unique_purposes)) {
            $unique_purposes[] = $purpose_clean;
        }
    }
    asort($unique_purposes);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Visitor Registration Directory - Gate Pass</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- html2canvas and jsPDF for table export -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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
                padding: 30px 20px;
            }

            /* Container matching staff dashboard aesthetics */
            .directory-container {
                max-width: 100%;
                margin: 0 auto;
            }

            /* Header Back navigation block */
            .dir-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 20px;
                margin-bottom: 24px;
            }
            .header-left {
                display: flex;
                align-items: center;
                gap: 16px;
            }
            .back-btn {
                background: #ffffff;
                border: 1.5px solid #cbd5e1;
                color: #334155;
                width: 38px;
                height: 38px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                text-decoration: none;
                transition: all 0.2s ease;
                font-size: 18px;
                font-weight: bold;
            }
            .back-btn:hover {
                background: #f1f5f9;
                border-color: #94a3b8;
                transform: translateX(-2px);
            }

            .dir-title h1 {
                font-size: 22px;
                font-weight: 800;
                color: #0f172a;
                letter-spacing: -0.01em;
            }
            .dir-title p {
                font-size: 13px;
                color: #64748b;
                font-weight: 500;
                margin-top: 2px;
            }

            /* Search filters card panel */
            .filter-card {
                background: #ffffff;
                border-radius: 16px;
                padding: 16px 20px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
                border: 1px solid #f1f5f9;
                margin-bottom: 20px;
                display: flex;
                gap: 16px;
                align-items: center;
                flex-wrap: wrap;
            }
            .search-input-wrapper {
                flex: 1 1 200px;
                position: relative;
            }
            .search-input-wrapper svg {
                position: absolute;
                left: 14px;
                top: 50%;
                transform: translateY(-50%);
                width: 18px;
                height: 18px;
                color: #94a3b8;
            }
            .search-input {
                width: 100%;
                padding: 10px 14px 10px 42px;
                border-radius: 10px;
                border: 1.5px solid #cbd5e1;
                font-size: 13.5px;
                font-weight: 500;
                color: #0f172a;
                outline: none;
                transition: all 0.15s ease;
            }
            .search-input:focus {
                border-color: #4f46e5;
                box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            }

            /* Date filter wrapper */
            .date-filter-wrapper {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-shrink: 0;
            }
            .date-filter-label {
                font-size: 12.5px;
                font-weight: 700;
                color: #475569;
                white-space: nowrap;
            }
            .date-filter-input {
                padding: 10px 12px;
                border-radius: 10px;
                border: 1.5px solid #cbd5e1;
                font-size: 13px;
                font-weight: 500;
                color: #0f172a;
                outline: none;
                transition: all 0.15s ease;
                background: #ffffff;
                cursor: pointer;
            }
            .date-filter-input:focus {
                border-color: #4f46e5;
                box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            }
            .clear-date-btn {
                background: #f1f5f9;
                border: 1.5px solid #e2e8f0;
                color: #64748b;
                padding: 9px 13px;
                border-radius: 9px;
                font-size: 12px;
                font-weight: 700;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 5px;
                transition: all 0.15s ease;
                white-space: nowrap;
            }
            .clear-date-btn:hover {
                background: #fee2e2;
                border-color: #fecaca;
                color: #b91c1c;
            }

            /* Table Directory styling */
            .directory-card {
                background: #ffffff;
                border-radius: 18px;
                padding: 20px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
                border: 1px solid #f1f5f9;
            }
            .table-responsive {
                width: 100%;
                overflow-x: auto;
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

            /* Status badges */
            .status-badge {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 700;
            }
            .status-badge.active {
                background: #d1fae5;
                color: #065f46;
            }
            .status-badge.used {
                background: #f3e8ff;
                color: #6b21a8;
            }
            .status-badge.expired {
                background: #fee2e2;
                color: #991b1b;
            }

            /* Actions link button */
            .view-card-btn {
                background: #eff6ff;
                border: 1px solid #bfdbfe;
                color: #2563eb;
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 700;
                text-decoration: none;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 4px;
                transition: all 0.15s ease;
            }
            .view-card-btn:hover {
                background: #2563eb;
                color: #ffffff;
                border-color: #2563eb;
            }

            /* Empty state for search */
            .empty-search-state {
                text-align: center;
                padding: 30px 20px;
                display: none;
            }
            .empty-search-state h3 {
                font-size: 15px;
                color: #1e293b;
                font-weight: 700;
            }
            .empty-search-state p {
                font-size: 12px;
                color: #64748b;
                margin-top: 2px;
            }

            /* Download CSV button */
            .download-csv-btn {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                padding: 10px 18px;
                border-radius: 10px;
                border: 1.5px solid #16a34a;
                background: #f0fdf4;
                color: #15803d;
                font-size: 13px;
                font-weight: 700;
                cursor: pointer;
                text-decoration: none;
                transition: all 0.18s ease;
                white-space: nowrap;
                flex-shrink: 0;
            }
            .download-csv-btn:hover {
                background: #16a34a;
                color: #ffffff;
                border-color: #16a34a;
                box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25);
                transform: translateY(-1px);
            }
            .download-csv-btn svg {
                width: 15px;
                height: 15px;
                flex-shrink: 0;
            }

            /* Download PDF button */
            .download-pdf-btn {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                padding: 10px 18px;
                border-radius: 10px;
                border: 1.5px solid #dc2626;
                background: #fef2f2;
                color: #b91c1c;
                font-size: 13px;
                font-weight: 700;
                cursor: pointer;
                text-decoration: none;
                transition: all 0.18s ease;
                white-space: nowrap;
                flex-shrink: 0;
            }
            .download-pdf-btn:hover {
                background: #dc2626;
                color: #ffffff;
                border-color: #dc2626;
                box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
                transform: translateY(-1px);
            }
            .download-pdf-btn svg {
                width: 15px;
                height: 15px;
                flex-shrink: 0;
            }

            /* Mobile responsiveness for Visitor Directory */
            @media (max-width: 768px) {
                body {
                    padding: 16px 10px;
                }
                .dir-header {
                    flex-direction: column;
                    align-items: stretch;
                    gap: 14px;
                }
                .dir-header .header-left {
                    gap: 10px;
                }
                .dir-title h1 {
                    font-size: 19px;
                }
                .dir-title p {
                    font-size: 11.5px;
                }
                /* Export buttons container */
                .dir-header div[style*="display: flex"] {
                    width: 100%;
                    gap: 8px;
                }
                .download-pdf-btn, .download-csv-btn {
                    flex: 1;
                    padding: 8px 12px;
                    font-size: 12px;
                    justify-content: center;
                }
                .filter-card {
                    flex-direction: column;
                    align-items: stretch;
                    gap: 12px;
                    padding: 14px;
                }
                .search-input-wrapper {
                    flex: 1 1 auto;
                    width: 100%;
                }
                .date-filter-wrapper {
                    width: 100%;
                    justify-content: space-between;
                }
                .date-filter-input {
                    flex: 1;
                    min-width: 0;
                }
                .results-counter {
                    font-size: 12px;
                }
                .directory-card {
                    padding: 12px;
                    border-radius: 12px;
                }
                table {
                    font-size: 12px;
                }
                th, td {
                    padding: 10px 8px;
                }
                .pass-purpose {
                    white-space: normal;
                    min-width: 180px;
                }
            }
        </style>
    </head>
    <body>

    <div class="directory-container">
        
        <!-- Header -->
        <div class="dir-header">
            <div class="header-left">
                <a href="dashboard.php" class="back-btn">←</a>
                <div class="dir-title">
                    <h1>Visitor Registration Directory</h1>
                    <p>Track all registered gate passes saved in the database.</p>
                </div>
            </div>
            <!-- Download Buttons (exports only currently visible/filtered rows) -->
            <div style="display: flex; gap: 10px; align-items: center;">
                <button class="download-pdf-btn" onclick="downloadPDFReport()" id="download-pdf-btn" title="Download visible rows as PDF">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Download PDF
                </button>
                <button class="download-csv-btn" onclick="downloadCSV()" id="download-btn" title="Download visible rows as CSV">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Download CSV
                </button>
            </div>
        </div>

        <!-- Filter and Instant Search bar -->
        <div class="filter-card">
            <div class="search-input-wrapper">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" id="directory-search" class="search-input" placeholder="Search by visitor name or mobile number..." oninput="filterDirectory()">
            </div>

            <!-- Quick Filters -->
            <div class="date-filter-wrapper" style="display: flex; align-items: center; gap: 8px;">
                <span class="date-filter-label">⚡ Quick:</span>
                <select id="quick-filter" class="date-filter-input" style="padding: 9px 12px; font-weight: 600;" onchange="applyQuickFilter(this.value)">
                    <option value="all">All Time</option>
                    <option value="today">Today</option>
                    <option value="week">Last Week</option>
                    <option value="month">Last Month</option>
                </select>
            </div>

            <!-- Purpose Filter -->
            <div class="date-filter-wrapper" style="display: flex; align-items: center; gap: 8px;">
                <span class="date-filter-label">📋 Purpose:</span>
                <select id="purpose-filter" class="date-filter-input" style="padding: 9px 12px; font-weight: 600;" onchange="filterDirectory()">
                    <option value="all">All Purposes</option>
                    <?php foreach ($unique_purposes as $up): ?>
                        <option value="<?= htmlspecialchars($up) ?>"><?= htmlspecialchars($up) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date Filter -->
            <div class="date-filter-wrapper">
                <span class="date-filter-label">📅 Date:</span>
                <input
                    type="date"
                    id="date-filter"
                    class="date-filter-input"
                    title="Filter by registration date"
                    onchange="document.getElementById('quick-filter').value='all'; filterDirectory()"
                >
                <button class="clear-date-btn" onclick="clearDateFilter()" title="Clear date filter">
                    ✕ Clear
                </button>
            </div>
        </div>

        <!-- Results Counter -->
        <div class="results-counter" style="margin: 0 0 12px 0; width: 100%; font-size: 13px; color: #475569; font-weight: 600; display: flex; justify-content: space-between; align-items: center; padding: 0 4px;">
            <span>Showing <span id="visible-count" style="color: #2563eb; font-weight: 700;">0</span> of <span id="total-count" style="color: #0f172a; font-weight: 700;">0</span> records</span>
        </div>

        <!-- Table Grid List -->
        <div class="directory-card">
            <?php if (empty($passes)): ?>
                <div style="text-align: center; padding: 40px 20px;">
                    <h3 style="color:#1e293b;">No registered passes found.</h3>
                    <p style="color:#64748b; font-size:13px; margin-top:4px;">When visitors register on the student page, they will show up here.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="passes-table">
                        <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Mobile Number</th>
                            <th>Purpose</th>
                            <th>Registered On</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($passes as $p):
                            $p_str = $p['purpose'];
                            if (preg_match('/^Purpose:\s*(.*?)\s*\|\s*Date:/i', $p_str, $matches)) {
                                $purpose_clean = trim($matches[1]);
                            } else {
                                $purpose_clean = trim($p_str);
                            }
                        ?>
                            <tr class="pass-row" data-date="<?= date('Y-m-d', strtotime($p['created_at'])) ?>" data-status="<?= htmlspecialchars(strtoupper($p['status'])) ?>" data-purpose="<?= htmlspecialchars($purpose_clean) ?>">
                                <td class="pass-name" style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($p['student_name']) ?></td>
                                <td class="pass-mobile"><?= htmlspecialchars($p['mobile']) ?></td>
                                <td class="pass-purpose"><?= htmlspecialchars($p['purpose']) ?></td>
                                <td><?= date('d M Y, h:i A', strtotime($p['created_at'])) ?></td>
                                <td>
                                    <?php 
                                        $statusVal = strtoupper($p['status']);
                                        if ($statusVal === 'ACTIVE') {
                                            echo '<span class="status-badge active">● Active</span>';
                                        } elseif ($statusVal === 'USED') {
                                            echo '<span class="status-badge used">● Scanned</span>';
                                        } else {
                                            echo '<span class="status-badge expired">● Expired</span>';
                                        }
                                    ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="view_pass.php?token=<?= urlencode($p['token']) ?>" class="view-card-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="width:13px; height:13px;">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        View Card
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div id="no-results" class="empty-search-state">
                    <h3>No matching visitors found.</h3>
                    <p>Try adjusting your search filters or queries.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Client-side directory filter: text search + date filter + CSV download -->
    <script>
    function filterDirectory() {
        const query      = document.getElementById('directory-search').value.toLowerCase().trim();
        const dateVal    = document.getElementById('date-filter').value;
        const quickVal   = document.getElementById('quick-filter').value;
        const purposeVal = document.getElementById('purpose-filter').value;
        const rows       = document.querySelectorAll('.pass-row');
        let visibleCount = 0;

        // Calculate relative dates for quick filter ranges
        const now = new Date();
        const todayStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
        
        // Parse range bounds in local time
        const oneDayMs = 24 * 60 * 60 * 1000;
        const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
        const sevenDaysAgo = todayStart - (7 * oneDayMs);
        const thirtyDaysAgo = todayStart - (30 * oneDayMs);

        rows.forEach(row => {
            const name       = row.querySelector('.pass-name').textContent.toLowerCase();
            const mobile     = row.querySelector('.pass-mobile').textContent.toLowerCase();
            const rowDate    = row.getAttribute('data-date'); // YYYY-MM-DD
            const rowPurpose = row.getAttribute('data-purpose');

            const textMatch = (query === '') || name.includes(query) || mobile.includes(query);
            
            // Check Date Picker match
            let dateMatch = (dateVal === '') || (rowDate === dateVal);
            
            // Check Quick Filter range match if Date Picker is empty
            if (dateVal === '' && quickVal !== 'all') {
                const rowDateTime = new Date(rowDate).getTime();
                if (quickVal === 'today') {
                    dateMatch = (rowDate === todayStr);
                } else if (quickVal === 'week') {
                    dateMatch = (rowDateTime >= sevenDaysAgo);
                } else if (quickVal === 'month') {
                    dateMatch = (rowDateTime >= thirtyDaysAgo);
                }
            }

            const purposeMatch = (purposeVal === 'all') || (rowPurpose === purposeVal);

            if (textMatch && dateMatch && purposeMatch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const noResults = document.getElementById('no-results');
        const table     = document.getElementById('passes-table');

        if (table) {
            if (visibleCount === 0) {
                table.style.display = 'none';
                if (noResults) noResults.style.display = 'block';
            } else {
                table.style.display = 'table';
                if (noResults) noResults.style.display = 'none';
            }
        }

        // Disable download button if nothing visible
        const btn = document.getElementById('download-btn');
        if (btn) btn.disabled = (visibleCount === 0);

        const pdfBtn = document.getElementById('download-pdf-btn');
        if (pdfBtn) pdfBtn.disabled = (visibleCount === 0);

        // Update counts in counter element
        const visEl = document.getElementById('visible-count');
        const totEl = document.getElementById('total-count');
        if (visEl) visEl.textContent = visibleCount;
        if (totEl) totEl.textContent = rows.length;
    }

    function applyQuickFilter(val) {
        if (val !== 'all') {
            document.getElementById('date-filter').value = '';
        }
        filterDirectory();
    }

    function clearDateFilter() {
        document.getElementById('date-filter').value = '';
        document.getElementById('quick-filter').value = 'all';
        filterDirectory();
    }

    // Run initially to set count
    window.addEventListener('DOMContentLoaded', () => {
        filterDirectory();
    });

    function downloadCSV() {
        // Collect only currently visible rows
        const rows    = document.querySelectorAll('.pass-row');
        const dateVal = document.getElementById('date-filter').value;

        // CSV header columns (skip Action column)
        const csvRows = ['Student Name,Mobile Number,Purpose,Registered On,Status'];

        rows.forEach(row => {
            if (row.style.display === 'none') return; // skip hidden rows

            const cells = row.querySelectorAll('td');
            const name       = cells[0].textContent.trim();
            const mobile     = cells[1].textContent.trim();
            const purpose    = cells[2].textContent.trim();
            const registered = cells[3].textContent.trim();
            const status     = cells[4].textContent.trim().replace(/^[●•]\s*/, '');

            // Escape fields that may contain commas
            const escape = v => '"' + v.replace(/"/g, '""') + '"';
            csvRows.push([escape(name), escape(mobile), escape(purpose), escape(registered), escape(status)].join(','));
        });

        if (csvRows.length <= 1) {
            alert('No visible data to download.');
            return;
        }

        // Build file name with filter context
        const now       = new Date();
        const datePart  = dateVal || now.toISOString().slice(0, 10);
        const fileName  = 'visitor-directory-' + datePart + '.csv';

        // Create and trigger download
        const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href     = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function downloadPDFReport() {
        const rows = document.querySelectorAll('.pass-row');
        const dateVal = document.getElementById('date-filter').value;
        const now = new Date();
        const datePart = dateVal || now.toISOString().slice(0, 10);

        // Open a blank window
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            alert('Popup blocked! Please allow popups for this site to generate the PDF report.');
            return;
        }

        let html = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Visitor Registration Directory Report - ${datePart}</title>
            <style>
                * {
                    box-sizing: border-box;
                }
                body {
                    font-family: system-ui, -apple-system, sans-serif;
                    color: #0f172a;
                    padding: 20px;
                    margin: 0;
                }
                .header {
                    margin-bottom: 20px;
                    border-bottom: 2px solid #e2e8f0;
                    padding-bottom: 12px;
                }
                .uni-name {
                    font-size: 18px;
                    font-weight: 800;
                    color: #0f46a2;
                }
                .report-title {
                    font-size: 13px;
                    color: #475569;
                    margin-top: 4px;
                    font-weight: 700;
                }
                .report-date {
                    font-size: 11px;
                    color: #64748b;
                    margin-top: 2px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 11.5px;
                    margin-top: 15px;
                }
                th {
                    background: #f1f5f9;
                    color: #334155;
                    font-weight: 700;
                    text-align: left;
                    padding: 8px 10px;
                    border-bottom: 1.5px solid #cbd5e1;
                }
                td {
                    padding: 8px 10px;
                    border-bottom: 1px solid #e2e8f0;
                    color: #334155;
                }
                .badge {
                    display: inline-block;
                    padding: 2px 6px;
                    border-radius: 4px;
                    font-size: 10px;
                    font-weight: 700;
                    text-transform: uppercase;
                }
                .badge-active { background: #d1fae5; color: #065f46; }
                .badge-used { background: #f3e8ff; color: #6b21a8; }
                .badge-expired { background: #fee2e2; color: #991b1b; }
                @media print {
                    body { padding: 0; }
                    @page {
                        size: A4 landscape;
                        margin: 15mm;
                    }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="uni-name">DR. P. A. INAMDAR UNIVERSITY</div>
                <div class="report-title">Visitor Registration Directory Report</div>
                <div class="report-date">Report Date: ${datePart} | Generated: ${now.toLocaleDateString()} ${now.toLocaleTimeString()}</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 20%;">Student Name</th>
                        <th style="width: 15%;">Mobile Number</th>
                        <th style="width: 35%;">Purpose of Visit</th>
                        <th style="width: 20%;">Registered On</th>
                        <th style="width: 10%;">Status</th>
                    </tr>
                </thead>
                <tbody>
        `;

        rows.forEach(row => {
            if (row.style.display === 'none') return; // skip hidden rows
            
            const cells      = row.querySelectorAll('td');
            const name       = cells[0].textContent.trim();
            const mobile     = cells[1].textContent.trim();
            const purpose    = cells[2].textContent.trim();
            const registered = cells[3].textContent.trim();
            
            const statusBadgeText = cells[4].textContent.trim().toUpperCase();
            let statusClass = 'badge-expired';
            let statusText = 'Expired';
            
            if (statusBadgeText.includes('ACTIVE')) {
                statusClass = 'badge-active';
                statusText = 'Active';
            } else if (statusBadgeText.includes('SCANNED') || statusBadgeText.includes('USED')) {
                statusClass = 'badge-used';
                statusText = 'Scanned';
            }

            html += `
                <tr>
                    <td><strong>${name}</strong></td>
                    <td>${mobile}</td>
                    <td>${purpose}</td>
                    <td>${registered}</td>
                    <td><span class="badge ${statusClass}">${statusText}</span></td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() { window.close(); }, 500);
                }
            <\/script>
        </body>
        </html>
        `;

        printWindow.document.write(html);
        printWindow.document.close();
    }
    </script>
    </body>
    </html>
    <?php
    exit;
}

// IF TOKEN IS PROVIDED: RETRIEVE INDIVIDUAL PASS DETAILS
$stmt = $pdo->prepare("SELECT * FROM gate_passes WHERE token = :t LIMIT 1");
$stmt->execute([':t' => $token]);
$pass = $stmt->fetch();

if (!$pass) {
    die('Gate pass not found');
}

// ensure log details (last scanned time)
$logStmt = $pdo->prepare("SELECT scanned_at FROM gate_logs WHERE pass_id = :id ORDER BY scanned_at DESC LIMIT 1");
$logStmt->execute([':id' => $pass['id']]);
$log = $logStmt->fetch();
$lastScan = $log['scanned_at'] ?? null;

// Photo path
$photoWebPath = '../' . $pass['photo_path'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gate Pass Card - <?= htmlspecialchars($pass['student_name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- html2canvas and jsPDF for card export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(9, 21, 46, 0.96) 0%, rgba(21, 39, 77, 0.96) 100%), url('../student/campus.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .outer {
            width: 100%;
            max-width: 360px;
        }

        .top-actions {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .top-btn {
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            transition: all 0.15s ease;
        }
        .top-btn:hover {
            background: rgba(255, 255, 255, 0.22);
            transform: scale(1.02);
        }

        /* Verified Visitor card overlay container */
        .card {
            width: 100%;
            background: #ffffff;
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
            color: #0f172a;
            position: relative;
        }

        .card-header {
            text-align: center;
            margin-bottom: 18px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .card-logo {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
            margin-bottom: 10px;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.01em;
            text-transform: uppercase;
        }
        .card-header span {
            display: block;
            font-size: 11.5px;
            color: #64748b;
            margin-top: 3px;
            font-weight: 600;
        }

        .verified-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            padding: 4px 14px;
            border-radius: 20px;
            background: #10b981;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
        }

        .photo-box {
            width: 100%;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        .photo-box img {
            width: 100%;
            height: auto;
            max-height: 180px;
            object-fit: cover;
        }

        .info-group {
            margin-bottom: 12px;
        }
        .section-label {
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 10px;
            color: #94a3b8;
            font-weight: 700;
        }
        .section-value {
            font-size: 13.5px;
            font-weight: 600;
            color: #1e293b;
            margin-top: 2px;
        }

        .card-body {
            border-top: 1px solid #f1f5f9;
            padding-top: 14px;
        }

        .card-footer {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1.5px dashed #e2e8f0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            font-size: 11px;
            color: #64748b;
        }
        .footer-col {
            display: flex;
            flex-direction: column;
        }
        .footer-col.right {
            text-align: right;
        }
        .footer-col strong {
            color: #475569;
            margin-bottom: 2px;
        }

        @media (max-width: 480px) {
            body { padding: 14px; }
            .card { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="outer">

    <div class="top-actions">
        <button class="top-btn" onclick="window.location.href='view_pass.php'">← Back</button>
        <button class="top-btn" onclick="downloadPassPDF()" style="background: #dc2626; color: #ffffff; border-color: #dc2626;">📄 Download PDF</button>
        <button class="top-btn" onclick="window.location.href='scan.php'">📷 Scan Again</button>
    </div>

    <div class="card" id="gate-pass-card">
        <div class="card-header">
            <img src="../student/logo.png" alt="University Logo" class="card-logo">
            <h2><?= htmlspecialchars($pass['student_name']) ?></h2>
            <span>Admission Visitor · Gate Pass</span>
            <div class="verified-pill">✔ VERIFIED</div>
        </div>

        <?php if (!empty($pass['photo_path'])): ?>
        <div class="photo-box">
            <img src="<?= htmlspecialchars($photoWebPath) ?>" alt="Student Photo">
        </div>
        <?php endif; ?>

        <div class="card-body">


            <div class="info-group">
                <div class="section-label">Mobile Number</div>
                <div class="section-value"><?= htmlspecialchars($pass['mobile']) ?></div>
            </div>

            <div class="info-group">
                <div class="section-label">Date of Visit</div>
                <div class="section-value">
                    <?= date('d M Y', strtotime($pass['created_at'])) ?>
                </div>
            </div>

            <div class="info-group">
                <div class="section-label">Purpose of Visit</div>
                <div class="section-value"><?= htmlspecialchars($pass['purpose']) ?></div>
            </div>
        </div>

        <div class="card-footer">
            <div class="footer-col">
                <strong>Valid till</strong>
                <span><?= date('d M Y h:i A', strtotime($pass['expires_at'])) ?> IST</span>
            </div>
            <div class="footer-col right">
                <strong>Last scan</strong>
                <span><?= $lastScan ? date('d M Y h:i A', strtotime($lastScan)) : 'First time' ?></span>
            </div>
        </div>
    </div>
</div>
<script>
    /**
     * Renders the card and saves it as a PDF
     */
    function downloadPassPDF() {
        const card = document.getElementById('gate-pass-card');
        
        // Fail-safe checks
        if (typeof html2canvas === 'undefined') {
            alert("HTML Capture library is not loaded. Please reload the page.");
            return;
        }
        
        const jsPDFObj = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : (window.jsPDF ? window.jsPDF : null);
        if (!jsPDFObj) {
            alert("PDF library is not loaded. Please reload the page.");
            return;
        }

        const buttons = document.querySelectorAll('.top-btn');
        const originalTextContent = [];
        buttons.forEach((btn, idx) => {
            originalTextContent[idx] = btn.innerHTML;
            btn.disabled = true;
        });
        
        // Change PDF button text to showing generating
        const pdfBtn = buttons[1]; // Download PDF button is the second button
        if (pdfBtn) pdfBtn.innerText = "⏳ Generating...";

        html2canvas(card, {
            scale: 3, // High-DPI quality
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false
        }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            
            // Convert canvas dimensions to mm (1 px ≈ 0.264583 mm)
            // Since scale is 3, we divide by 3 to get the original layout width/height in pixels
            const widthMm = (canvas.width / 3) * 0.264583;
            const heightMm = (canvas.height / 3) * 0.264583;

            const pdf = new jsPDFObj({
                orientation: widthMm > heightMm ? 'l' : 'p',
                unit: 'mm',
                format: [widthMm, heightMm]
            });

            pdf.addImage(imgData, 'PNG', 0, 0, widthMm, heightMm);
            pdf.save('gate_pass_<?= htmlspecialchars($pass['token']) ?>.pdf');
            
            // Restore buttons
            buttons.forEach((btn, idx) => {
                btn.disabled = false;
                btn.innerHTML = originalTextContent[idx];
            });
        }).catch(err => {
            console.error('Failed to generate PDF: ', err);
            alert("Error generating PDF: " + err.message);
            // Restore buttons
            buttons.forEach((btn, idx) => {
                btn.disabled = false;
                btn.innerHTML = originalTextContent[idx];
            });
        });
    }
</script>
</body>
</html>
