<?php
// student/success.php
require_once __DIR__ . '/../config.php';

$token = $_GET['token'] ?? '';

if ($token === '') {
    http_response_code(400);
    die('Invalid request');
}

// fetch pass data
$stmt = $pdo->prepare("SELECT * FROM gate_passes WHERE token = :token");
$stmt->execute([':token' => $token]);
$pass = $stmt->fetch();

if (!$pass) {
    http_response_code(404);
    die('Gate pass not found');
}

$qrPath = '../qrcodes/' . htmlspecialchars($pass['token']) . '.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gate Pass - <?= htmlspecialchars($pass['student_name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <!-- Premium Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- html2canvas and jsPDF for card export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
            font-family: 'Outfit', system-ui, -apple-system, BlinkMacSystemFont, sans-serif; 
        }

        body {
            min-height: 100vh;
            background: #f3f5f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 24px 16px;
            position: relative;
        }

        /* Outer shadow wrapper that is NOT captured by html2canvas */
        .card-shadow-wrapper {
            width: 100%;
            max-width: 480px;
            background: transparent;
            border-radius: 24px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.04);
            margin-bottom: 16px;
        }

        /* Main Gate Pass Card Container - Narrower & More Compact */
        .gate-pass-card {
            width: 100%;
            background: #ffffff;
            border-radius: 24px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        /* Top Header containing logo, title, tagline and valid pill */
        .uni-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            gap: 12px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 12px;
        }

        .uni-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .uni-logo {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .uni-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .uni-title {
            color: #061830;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .uni-tagline {
            color: #8c9ba5;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.18em;
        }

        .valid-badge {
            background: #e6fcf5;
            color: #0aa678;
            border-radius: 16px;
            padding: 5px 10px;
            font-size: 9.5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
            letter-spacing: 0.05em;
            flex-shrink: 0;
        }

        .valid-badge svg {
            width: 12px;
            height: 12px;
        }

        /* Visitor Details List */
        .details-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
        }

        .detail-row:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .detail-icon-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .detail-icon-circle.name-icon {
            background: #f3e8ff;
            color: #9333ea;
        }

        .detail-icon-circle.phone-icon {
            background: #dcfce7;
            color: #16a34a;
        }

        .detail-icon-circle.date-icon {
            background: #ede9fe;
            color: #7c3aed;
        }

        .detail-icon-circle svg {
            width: 16px;
            height: 16px;
        }

        .detail-info {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .detail-label {
            font-size: 9.5px;
            color: #94a3b8;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .detail-value {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
        }

        /* Alert Warning Banner */
        .alert-banner {
            background: #eff6ff;
            border-radius: 10px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            border: 1px solid rgba(59, 130, 246, 0.08);
        }

        .alert-banner svg {
            width: 16px;
            height: 16px;
            color: #2563eb;
            flex-shrink: 0;
        }

        .alert-text {
            font-size: 11px;
            font-weight: 600;
            color: #2563eb;
            line-height: 1.3;
        }

        /* Pass Meta side-by-side grid - Always Side-by-Side */
        .meta-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 10px;
            width: 100%;
        }

        .meta-box {
            background: #ffffff;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.01);
            overflow: hidden;
        }

        .meta-icon-circle {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .meta-icon-circle svg {
            width: 14px;
            height: 14px;
        }

        .meta-content {
            display: flex;
            flex-direction: column;
            gap: 1px;
            overflow: hidden;
            width: 100%;
        }

        .meta-label {
            font-size: 8px;
            color: #94a3b8;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .meta-value {
            font-size: 11.5px;
            font-weight: 700;
            color: #0f172a;
            white-space: normal;
            word-break: break-word;
            line-height: 1.25;
        }

        /* Token copy action specific styles */
        .token-wrapper {
            display: flex;
            align-items: center;
            gap: 4px;
            width: 100%;
            overflow: hidden;
        }

        .token-text {
            font-size: 11px;
            font-family: monospace;
            color: #475569;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex-grow: 1;
        }

        .copy-action-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3px;
            border-radius: 4px;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }

        .copy-action-btn:hover {
            color: #2563eb;
            background: #f1f5f9;
        }

        .copy-action-btn svg {
            width: 13px;
            height: 13px;
        }

        /* Centered QR code container */
        .qr-section {
            background: #ffffff;
            border: 1px solid #f1f5f9;
            border-radius: 16px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            width: 100%;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.01);
        }

        .qr-wrapper {
            position: relative;
            padding: 16px;
            background: #ffffff;
            width: 150px;
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* Corner Brackets for QR code */
        .bracket {
            position: absolute;
            width: 14px;
            height: 14px;
            border-color: #c7d2fe;
            border-style: solid;
            border-width: 0;
        }

        .bracket-tl {
            top: 0;
            left: 0;
            border-top-width: 2.5px;
            border-left-width: 2.5px;
            border-top-left-radius: 6px;
        }

        .bracket-tr {
            top: 0;
            right: 0;
            border-top-width: 2.5px;
            border-right-width: 2.5px;
            border-top-right-radius: 6px;
        }

        .bracket-bl {
            bottom: 0;
            left: 0;
            border-bottom-width: 2.5px;
            border-left-width: 2.5px;
            border-bottom-left-radius: 6px;
        }

        .bracket-br {
            bottom: 0;
            right: 0;
            border-bottom-width: 2.5px;
            border-right-width: 2.5px;
            border-bottom-right-radius: 6px;
        }

        .qr-scan-instruction {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #4b5563;
            font-size: 11.5px;
            font-weight: 700;
            text-align: center;
        }

        .qr-scan-instruction svg {
            width: 14px;
            height: 14px;
            color: #2563eb;
            flex-shrink: 0;
        }

        .qr-scan-subtext {
            font-size: 10px;
            color: #64748b;
            font-weight: 500;
            margin-top: -8px;
            text-align: center;
        }

        /* Indicators footer row (3-column) - Always Side-by-Side */
        .indicators-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            width: 100%;
        }

        .indicator-card {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 8px 4px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            text-align: center;
        }

        .indicator-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .indicator-card.secure .indicator-icon {
            background: #eff6ff;
            color: #2563eb;
        }

        .indicator-card.timebound .indicator-icon {
            background: #ede9fe;
            color: #7c3aed;
        }

        .indicator-card.verified .indicator-icon {
            background: #dcfce7;
            color: #16a34a;
        }

        .indicator-icon svg {
            width: 12px;
            height: 12px;
        }

        .indicator-text {
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow: hidden;
            width: 100%;
        }

        .indicator-title {
            font-size: 9px;
            font-weight: 750;
            color: #1e293b;
            white-space: nowrap;
        }

        .indicator-desc {
            font-size: 8px;
            color: #64748b;
            font-weight: 500;
            white-space: nowrap;
        }

        /* Padlock Safety Bottom Banner */
        .safety-banner {
            background: #eff6ff;
            border-radius: 16px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            border: 1px solid rgba(59, 130, 246, 0.05);
        }

        .safety-icon-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #ffffff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.08);
        }

        .safety-icon-circle svg {
            width: 16px;
            height: 16px;
        }

        .safety-content {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .safety-title {
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
        }

        .safety-desc {
            font-size: 9.5px;
            color: #64748b;
            font-weight: 500;
            line-height: 1.3;
        }

        /* Toast message styling */
        .copy-toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #0f172a;
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 9999;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .copy-toast.show {
            transform: translateX(-50%) translateY(0);
        }

        .copy-toast svg {
            width: 15px;
            height: 15px;
            color: #10b981;
        }

        /* Action buttons below the card (Excluded from screenshot) */
        .actions-wrapper {
            display: flex;
            gap: 12px;
            width: 100%;
            max-width: 480px;
            justify-content: center;
            margin-top: 8px;
        }

        .action-btn {
            padding: 11px 20px;
            border-radius: 12px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
            flex: 1;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }

        .btn-pdf {
            background: #dc2626;
            color: #ffffff;
        }

        .btn-pdf:hover {
            background: #b91c1c;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(220, 38, 38, 0.2);
        }

        .btn-download {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-download:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.2);
        }

        .btn-home {
            background: #0f172a;
            color: #ffffff;
        }

        .btn-home:hover {
            background: #1e293b;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.15);
        }

        .action-btn svg {
            width: 15px;
            height: 15px;
        }

        /* Mobile Adjustments (making sure text doesn't overflow) */
        @media (max-width: 420px) {
            body {
                padding: 16px 10px;
            }

            .gate-pass-card {
                padding: 16px 12px;
                gap: 12px;
            }

            .uni-title {
                font-size: 14px;
            }

            .uni-logo {
                width: 40px;
                height: 40px;
            }

            .valid-badge {
                padding: 4px 8px;
                font-size: 8.5px;
            }

            .meta-grid {
                grid-template-columns: 1.15fr 0.85fr;
                gap: 6px;
            }

            .meta-box {
                padding: 8px;
                gap: 6px;
            }

            .meta-icon-circle {
                width: 24px;
                height: 24px;
            }

            .meta-icon-circle svg {
                width: 12px;
                height: 12px;
            }

            .meta-value {
                font-size: 10px;
            }

            .token-text {
                font-size: 10px;
            }

            .indicators-grid {
                grid-template-columns: 1fr 1fr 1fr;
                gap: 6px;
            }

            .indicator-card {
                padding: 6px 2px;
                gap: 3px;
            }

            .indicator-title {
                font-size: 7.5px;
            }

            .indicator-desc {
                font-size: 6.5px;
            }

            .actions-wrapper {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
</head>
<body>

    <!-- Shadow wrapper containing the Gate Pass Card -->
    <div class="card-shadow-wrapper">
        <div class="gate-pass-card" id="gate-pass-card">
            
            <!-- University branding header -->
            <div class="uni-header">
                <div class="uni-header-left">
                    <img src="logo.png" alt="University Logo" class="uni-logo">
                    <div class="uni-text">
                        <h1 class="uni-title">Dr. P. A. Inamdar University | Pune</h1>
                        <p class="uni-tagline">EDUCATE • EMPOWER • EVOLVE</p>
                    </div>
                </div>
                <div class="valid-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    VALID PASS
                </div>
            </div>

            <!-- Visitor Details -->
            <div class="details-list">
                <!-- Student Name -->
                <div class="detail-row">
                    <div class="detail-icon-circle name-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <div class="detail-info">
                        <span class="detail-label">Student Name</span>
                        <span class="detail-value"><?= htmlspecialchars($pass['student_name']) ?></span>
                    </div>
                </div>

                <!-- Mobile Number -->
                <div class="detail-row">
                    <div class="detail-icon-circle phone-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                    </div>
                    <div class="detail-info">
                        <span class="detail-label">Mobile Number</span>
                        <span class="detail-value"><?= htmlspecialchars($pass['mobile']) ?></span>
                    </div>
                </div>

                <!-- Date of Visit -->
                <div class="detail-row">
                    <div class="detail-icon-circle date-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <div class="detail-info">
                        <span class="detail-label">Date of Visit</span>
                        <span class="detail-value"><?= date('d M Y', strtotime($pass['created_at'])) ?></span>
                    </div>
                </div>
            </div>

            <!-- Blue Alert Banner -->
            <div class="alert-banner">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <polyline points="9 11 11 13 15 9"/>
                </svg>
                <span class="alert-text">This gate pass is valid only for the date mentioned above.</span>
            </div>

            <!-- Meta Grid side-by-side (Pass ID and Valid Until) -->
            <div class="meta-grid">
                <!-- Pass ID -->
                <div class="meta-box">
                    <div class="meta-icon-circle">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                        </svg>
                    </div>
                    <div class="meta-content">
                        <span class="meta-label">Pass ID</span>
                        <div class="token-wrapper">
                            <span class="token-text" id="token-raw"><?= htmlspecialchars($pass['token']) ?></span>
                            <!-- Ignore copy button during screenshot export -->
                            <button class="copy-action-btn" onclick="copyToken()" title="Copy Pass ID" data-html2canvas-ignore="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Valid Until -->
                <div class="meta-box">
                    <div class="meta-icon-circle">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <div class="meta-content">
                        <span class="meta-label">Valid Until</span>
                        <span class="meta-value"><?= date('d M Y, h:i A', strtotime($pass['expires_at'])) ?></span>
                    </div>
                </div>
            </div>

            <!-- QR Code Section -->
            <div class="qr-section">
                <div class="qr-wrapper">
                    <!-- Brackets -->
                    <span class="bracket bracket-tl"></span>
                    <span class="bracket bracket-tr"></span>
                    <span class="bracket bracket-bl"></span>
                    <span class="bracket bracket-br"></span>
                    <img src="<?= $qrPath ?>" alt="Gate Pass QR Code" class="qr-image">
                </div>
                <div class="qr-scan-instruction">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <polyline points="9 11 11 13 15 9"/>
                    </svg>
                    Scan at gate for verification.
                </div>
                <div class="qr-scan-subtext">Do not share this QR with others.</div>
            </div>

            <!-- Horizontal Indicators Row (3-column) -->
            <div class="indicators-grid">
                <!-- Secure -->
                <div class="indicator-card secure">
                    <div class="indicator-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            <polyline points="9 11 11 13 15 9"/>
                        </svg>
                    </div>
                    <div class="indicator-text">
                        <span class="indicator-title">SECURE</span>
                        <span class="indicator-desc">Encrypted</span>
                    </div>
                </div>

                <!-- Time Bound -->
                <div class="indicator-card timebound">
                    <div class="indicator-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <div class="indicator-text">
                        <span class="indicator-title">TIME BOUND</span>
                        <span class="indicator-desc">Until 6 PM</span>
                    </div>
                </div>

                <!-- Verified -->
                <div class="indicator-card verified">
                    <div class="indicator-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <div class="indicator-text">
                        <span class="indicator-title">VERIFIED</span>
                        <span class="indicator-desc">Uni System</span>
                    </div>
                </div>
            </div>

            <!-- Bottom Safety Banner -->
            <div class="safety-banner">
                <div class="safety-icon-circle">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <div class="safety-content">
                    <span class="safety-title">Keep your visit smooth and secure.</span>
                    <span class="safety-desc">Cooperate with the security staff and follow university rules.</span>
                </div>
            </div>

        </div>
    </div>

    <!-- Web-only Action buttons (ignored in screenshot) -->
    <div class="actions-wrapper">
        <button onclick="downloadPassPDF()" class="action-btn btn-pdf">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            Download PDF
        </button>

        <button onclick="downloadPassImage(true)" class="action-btn btn-download">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Download Image
        </button>
        
        <a href="../index.php" class="action-btn btn-home">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Home
        </a>
    </div>

    <!-- Copy notification toast -->
    <div class="copy-toast" id="copy-toast">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
        Pass ID Copied to clipboard!
    </div>

    <script>
        /**
         * Copies the token to the clipboard
         */
        function copyToken() {
            const token = document.getElementById('token-raw').innerText;
            navigator.clipboard.writeText(token).then(() => {
                const toast = document.getElementById('copy-toast');
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 2200);
            }).catch(err => {
                console.error('Failed to copy text: ', err);
            });
        }

        /**
         * Renders the card and saves it as a PDF
         */
        function downloadPassPDF() {
            const card = document.getElementById('gate-pass-card');
            const { jsPDF } = window.jspdf;

            html2canvas(card, {
                scale: 3, // High-DPI quality
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
                scrollX: 0,
                scrollY: -window.scrollY
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                
                // Convert canvas dimensions to mm (1 px ≈ 0.264583 mm)
                // Since scale is 3, we divide by 3 to get the original layout width/height in pixels
                const widthMm = (canvas.width / 3) * 0.264583;
                const heightMm = (canvas.height / 3) * 0.264583;

                const pdf = new jsPDF({
                    orientation: widthMm > heightMm ? 'l' : 'p',
                    unit: 'mm',
                    format: [widthMm, heightMm]
                });

                pdf.addImage(imgData, 'PNG', 0, 0, widthMm, heightMm);
                pdf.save('gate_pass_<?= htmlspecialchars($pass['token']) ?>.pdf');
            }).catch(err => {
                console.error('Failed to generate PDF: ', err);
            });
        }

        /**
         * Renders the card into an image and triggers download
         * @param {boolean} manualTrigger If true, triggers download regardless of sessionStorage
         */
        function downloadPassImage(manualTrigger = false) {
            const card = document.getElementById('gate-pass-card');
            
            // To ensure premium rendering quality, we configure html2canvas:
            html2canvas(card, {
                scale: 3, // High-DPI quality
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
                scrollX: 0,
                scrollY: -window.scrollY // Fixes offset issue if page is scrolled
            }).then(canvas => {
                const dataUrl = canvas.toDataURL('image/png');
                const link = document.createElement('a');
                link.download = 'gate_pass_<?= htmlspecialchars($pass['token']) ?>.png';
                link.href = dataUrl;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }).catch(err => {
                console.error('Failed to capture gate pass card: ', err);
            });
        }

        // Automatic download logic on page load
        window.addEventListener('load', () => {
            // Find all image elements within the card to ensure they are fully loaded
            const images = Array.from(document.querySelectorAll('#gate-pass-card img'));
            const imagePromises = images.map(img => {
                if (img.complete) return Promise.resolve();
                return new Promise(resolve => {
                    img.onload = resolve;
                    img.onerror = resolve;
                });
            });

            // Trigger rendering once all resources are ready
            Promise.all(imagePromises).then(() => {
                setTimeout(() => {
                    const token = '<?= htmlspecialchars($pass['token']) ?>';
                    // Check sessionStorage to prevent infinite downloads on reload
                    if (!sessionStorage.getItem('downloaded_pass_' + token)) {
                        downloadPassImage(false);
                        sessionStorage.setItem('downloaded_pass_' + token, 'true');
                    }
                }, 800); // 800ms buffer to guarantee layout stabilization and font rendering
            });
        });
    </script>
</body>
</html>
