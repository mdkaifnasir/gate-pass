<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent browser/proxy caching so changes always appear immediately
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../phpqrcode/qrlib.php';

$errors = [];
$success = false;

// Add this to retain values for repopulating form
$full_name = '';
$mobile = '';
$visit_date = '';
$purpose_of_visit = '';
$other_purpose = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // renamed meanings for simplified admission students
    $full_name = trim($_POST['full_name'] ?? '');    // student name
    $mobile = trim($_POST['mobile'] ?? '');
    $visit_date = date('Y-m-d'); // Force current date as instructed (not changeable)
    $purpose_of_visit = trim($_POST['purpose_of_visit'] ?? '');
    $other_purpose = trim($_POST['other_purpose'] ?? '');

    // Validation checks
    if ($full_name === '')
        $errors[] = 'Student name is required.';
    if ($mobile === '')
        $errors[] = 'Mobile number is required.';
    if ($purpose_of_visit === '')
        $errors[] = 'Purpose of visit is required.';
    if ($purpose_of_visit === 'Other' && $other_purpose === '')
        $errors[] = 'Please specify your purpose of visit.';

    if (empty($errors)) {
        $photo_path = ''; // No photo captured in simplified form

        // token + expiry: valid until 6:00 PM IST on the same day
        $token = bin2hex(random_bytes(16));
        $expires_at = date('Y-m-d') . ' 18:00:00';  // stored in IST

        // Compose full purpose with standard visit info
        $chosen_purpose = ($purpose_of_visit === 'Other') ? $other_purpose : $purpose_of_visit;
        $purpose_full = 'Purpose: ' . $chosen_purpose . ' | Date: ' . $visit_date;

        // insert into DB mapping fields to existing columns
        // - institute_name is set to 'N/A'
        // - roll_no stores "Admission Visitor"
        // - department stores "0" for accompanying persons
        $stmt = $pdo->prepare("
            INSERT INTO gate_passes 
            (student_name, institute_name, roll_no, department, year, mobile, purpose, photo_path, token, expires_at, status, created_at) 
            VALUES 
            (:student_name, 'N/A', 'Admission Visitor', '0', 'Admission Visitor', :mobile, :purpose, :photo_path, :token, :expires_at, 'ACTIVE', NOW())
        ");
        $stmt->execute([
            ':student_name' => $full_name,
            ':mobile' => $mobile,
            ':purpose' => $purpose_full,
            ':photo_path' => $photo_path,
            ':token' => $token,
            ':expires_at' => $expires_at,
        ]);

        // QR
        $qrDir = __DIR__ . '/../qrcodes/';
        if (!is_dir($qrDir))
            mkdir($qrDir, 0777, true);

        $qrFile = $qrDir . $token . '.png';

        // Dynamically build base URL to support both local localhost and live domain seamlessly
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $requestUri = $_SERVER['REQUEST_URI'];
        $baseDir = (strpos($requestUri, '/gate-pass/') !== false) ? '/gate-pass' : '';
        $baseUrl = $protocol . "://" . $host . $baseDir;

        $qrContent = $baseUrl . '/verify.php?t=' . urlencode($token);

        QRcode::png($qrContent, $qrFile, QR_ECLEVEL_L, 4);
        if (!file_exists($qrFile)) {
            die('QR not created at: ' . $qrFile);
        }

        header("Location: success.php?token=" . urlencode($token));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Gate Pass for Students</title>
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
            background: linear-gradient(180deg, rgba(240, 246, 255, 0.88) 0%, rgba(219, 234, 254, 0.94) 100%), url('campus.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 40px 0 0 0;
        }

        /* Container keeping all blocks aligned nicely */
        .wrapper {
            width: 100%;
            max-width: 580px;
            margin-bottom: 60px;
            display: flex;
            flex-direction: column;
            padding: 0 20px;
        }

        /* Centered Header Section */
        .header-section {
            text-align: center;
            margin-bottom: 24px;
        }

        .header-section .logo {
            width: 135px;
            height: 135px;
            display: block;
            margin: 0 auto 14px auto;
            border-radius: 50%;
            object-fit: cover;
        }

        .header-section h1 {
            color: #0d2a5c;
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 2px;
            letter-spacing: -0.01em;
        }

        .header-section hr {
            border: none;
            border-top: 1.5px solid rgba(13, 42, 92, 0.2);
            width: 90%;
            margin: 8px auto;
        }

        .header-section .tagline {
            color: #0d2a5c;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.25em;
            margin-top: 6px;
        }

        /* Banner holding dynamic Student Gate Pass info */
        .banner-row {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            padding: 0 4px;
        }

        .banner-row .icon-box {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #0f46a2;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 6px 15px rgba(15, 70, 162, 0.25);
        }

        .banner-row .icon-box svg {
            width: 24px;
            height: 24px;
            color: #ffffff;
        }

        .banner-row .text-box h2 {
            font-size: 21px;
            font-weight: 800;
            color: #0d2a5c;
            margin: 0;
            line-height: 1.15;
        }

        .banner-row .text-box p {
            font-size: 13px;
            color: #4b5563;
            margin-top: 2px;
            font-weight: 500;
        }

        /* Main Form Card */
        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 28px 28px 24px 28px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(219, 234, 254, 0.5);
            width: 100%;
        }

        /* Info Alert Box inside Card */
        .info-alert {
            background: #eff6ff;
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 22px;
        }

        .info-alert .info-circle {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #3b82f6;
            color: #ffffff;
            font-size: 13px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .info-alert h4 {
            color: #1e3a8a;
            font-size: 13.5px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .info-alert p {
            color: #4b5563;
            font-size: 11.5px;
            font-weight: 500;
        }

        /* Form Element Controls */
        form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        label {
            display: block;
            font-size: 13.5px;
            font-weight: 700;
            color: #0d2a5c;
            margin-bottom: 8px;
        }

        /* Wrapper putting icons on the left inside input fields */
        .input-container {
            position: relative;
        }

        .input-container input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            font-size: 14px;
            color: #1e293b;
            outline: none;
            background: #ffffff;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .input-container input::placeholder {
            color: #94a3b8;
        }

        .input-container input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3.5px rgba(59, 130, 246, 0.12);
        }

        .input-container svg.field-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #94a3b8;
            pointer-events: none;
        }

        .input-container svg.lock-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #94a3b8;
            pointer-events: none;
        }

        /* Purpose dropdown */
        .select-container {
            position: relative;
        }

        .select-container select {
            width: 100%;
            padding: 12px 40px 12px 42px;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            font-size: 14px;
            color: #1e293b;
            outline: none;
            background: #ffffff;
            transition: all 0.2s ease;
            font-weight: 500;
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
        }

        .select-container select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3.5px rgba(59, 130, 246, 0.12);
        }

        .select-container select option[value=""] {
            color: #94a3b8;
        }

        .select-container svg.field-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #94a3b8;
            pointer-events: none;
        }

        .select-container .chevron-icon {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #94a3b8;
            pointer-events: none;
        }



        /* Other purpose textarea */
        .other-purpose-wrap {
            display: none;
            margin-top: 12px;
            animation: slideDown 0.2s ease;
        }

        .other-purpose-wrap.visible {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .other-purpose-wrap textarea {
            width: 100%;
            padding: 11px 14px;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            font-size: 14px;
            color: #1e293b;
            outline: none;
            background: #ffffff;
            transition: all 0.2s ease;
            font-weight: 500;
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }

        .other-purpose-wrap textarea::placeholder {
            color: #94a3b8;
        }

        .other-purpose-wrap textarea:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3.5px rgba(59, 130, 246, 0.12);
        }

        .helper-text {
            font-size: 11.5px;
            color: #6b7280;
            margin-top: 5px;
            font-weight: 500;
            line-height: 1.25;
        }

        /* Submit Button custom overrides */
        .primary-btn {
            width: 100%;
            padding: 13px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.15s ease;
        }

        .primary-btn:hover {
            opacity: 0.96;
            transform: translateY(-0.5px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
        }

        .primary-btn svg {
            width: 18px;
            height: 18px;
            color: #ffffff;
        }

        /* Information secure shield indicator */
        .secure-notice {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: #6b7280;
            font-size: 11.5px;
            margin-top: 14px;
            font-weight: 500;
        }

        .secure-notice svg {
            width: 15px;
            height: 15px;
            color: #6b7280;
        }

        /* Important Note Box (under the white card) */
        .bottom-alert {
            background: #eff6ff;
            border: 1.5px solid #dbeafe;
            border-radius: 12px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 18px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.01);
            width: 100%;
        }

        .bottom-alert .icon-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #dbeafe;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #2563eb;
        }

        .bottom-alert .icon-badge svg {
            width: 18px;
            height: 18px;
        }

        .bottom-alert h5 {
            color: #1e3a8a;
            font-size: 13.5px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .bottom-alert p {
            color: #4b5563;
            font-size: 11.5px;
            font-weight: 500;
            line-height: 1.3;
        }



        .errors {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            color: #b91c1c;
            border-radius: 10px;
            padding: 12px;
            font-size: 13px;
            margin-bottom: 18px;
            line-height: 1.4;
            font-weight: 500;
        }

        /* Professional dark blue footer */
        .global-footer {
            width: 100%;
            background: #030712;
            color: #f9fafb;
            padding: 30px 24px;
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 24px;
            font-size: 13px;
        }

        .global-footer .left-col {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .global-footer .left-col .address {
            color: #9ca3af;
            max-width: 320px;
            line-height: 1.4;
            margin-top: 2px;
        }

        .global-footer .right-col {
            display: flex;
            flex-direction: column;
            gap: 8px;
            border-left: 1.5px solid rgba(255, 255, 255, 0.15);
            padding-left: 28px;
        }

        .global-footer a {
            color: #f9fafb;
            text-decoration: none;
            transition: color 0.1s ease;
        }

        .global-footer a:hover {
            color: #3b82f6;
        }

        .footer-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        .footer-item svg {
            width: 15px;
            height: 15px;
            color: #3b82f6;
        }

        @media (max-width: 768px) {
            .global-footer {
                flex-direction: column;
                align-items: flex-start;
                padding: 24px 20px;
            }

            .global-footer .right-col {
                border-left: none;
                padding-left: 0;
                width: 100%;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                padding-top: 16px;
            }
        }

        @media (max-width: 580px) {
            body {
                padding: 20px 0 0 0;
            }

            .wrapper {
                padding: 0 12px;
                margin-bottom: 40px;
            }

            .header-section .logo {
                width: 90px;
                height: 90px;
                margin-bottom: 10px;
            }

            .header-section h1 {
                font-size: 19px;
            }

            .header-section .tagline {
                font-size: 8.5px;
                letter-spacing: 0.15em;
            }

            .banner-row {
                gap: 10px;
                margin-bottom: 16px;
            }

            .banner-row .icon-box {
                width: 40px;
                height: 40px;
                box-shadow: 0 4px 10px rgba(15, 70, 162, 0.25);
            }

            .banner-row .icon-box svg {
                width: 18px;
                height: 18px;
            }

            .banner-row .text-box h2 {
                font-size: 17.5px;
            }

            .banner-row .text-box p {
                font-size: 12px;
            }

            .card {
                padding: 20px 16px;
                border-radius: 12px;
            }

            .info-alert {
                padding: 10px;
                gap: 10px;
                margin-bottom: 16px;
            }

            .info-alert h4 {
                font-size: 12.5px;
            }

            .info-alert p {
                font-size: 11px;
            }

            label {
                font-size: 12.5px;
                margin-bottom: 6px;
            }

            .input-container input {
                padding: 10px 12px 10px 38px;
                font-size: 13.5px;
            }

            .input-container svg.field-icon {
                left: 12px;
                width: 16px;
                height: 16px;
            }

            .input-container svg.lock-icon {
                right: 12px;
                width: 16px;
                height: 16px;
            }

            .primary-btn {
                padding: 11px 16px;
                font-size: 14px;
            }
        }
    </style>
</head>

<body>

    <div class="wrapper">

        <!-- Top Branding Logo & Title Header -->
        <div class="header-section">
            <img src="logo.png" alt="Dr. P. A. Inamdar University" class="logo">
            <h1>Dr. P. A. Inamdar University | Pune</h1>
            <hr>
            <p class="tagline">EDUCATE • EMPOWER • EVOLVE</p>
        </div>

        <!-- College Gate Pass Indicator -->
        <div class="banner-row">
            <div class="icon-box">
                <!-- Arch Gate Vector SVG -->
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M3 21h18M5 21V10a7 7 0 0 1 14 0v11M9 21v-6a3 3 0 0 1 6 0v6" />
                </svg>
            </div>
            <div class="text-box">
                <h2>Gate Pass for Students</h2>
                <p>Register your visit to the university campus</p>
            </div>
        </div>

        <!-- Main Registration Box -->
        <div class="card">

            <!-- Info alert notice block -->
            <div class="info-alert">
                <div class="info-circle">i</div>
                <div>
                    <h4>Please fill in your details below</h4>
                    <p>Your gate pass will be generated after submission.</p>
                </div>
            </div>



            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <?php foreach ($errors as $e)
                        echo htmlspecialchars($e) . '<br>'; ?>
                </div>
            <?php endif; ?>

            <!-- Student Form -->
            <form method="post">

                <div>
                    <label>Full Name</label>
                    <div class="input-container">
                        <!-- User Icon SVG -->
                        <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <input type="text" name="full_name" required value="<?= htmlspecialchars($full_name ?? '') ?>"
                            placeholder="Enter your full name">
                    </div>
                </div>



                <div>
                    <label>Mobile Number</label>
                    <div class="input-container">
                        <!-- Phone Icon SVG -->
                        <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <input type="text" name="mobile" required pattern="^[6-9]\d{9}$" maxlength="10"
                            inputmode="numeric" placeholder="Enter your 10-digit mobile number"
                            value="<?= htmlspecialchars($mobile ?? '') ?>">
                    </div>
                    <p class="helper-text">Please enter a valid 10-digit mobile number.</p>
                </div>

                <!-- Purpose of Visit -->
                <div style="display:block;">
                    <label for="purpose_of_visit">Purpose of Visit</label>
                    <div class="select-container">
                        <!-- Purpose Icon SVG -->
                        <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <select id="purpose_of_visit" name="purpose_of_visit" required
                            onchange="toggleOtherPurpose(this.value)">
                            <option value="" disabled <?= $purpose_of_visit === '' ? 'selected' : '' ?>>-- Select purpose --</option>
                            <option value="Admission Enquiry" <?= $purpose_of_visit === 'Admission Enquiry' ? 'selected' : '' ?>>Admission Enquiry</option>
                            <option value="Campus Visit" <?= $purpose_of_visit === 'Campus Visit' ? 'selected' : '' ?>>Campus Visit</option>
                            <option value="Other" <?= $purpose_of_visit === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                        <!-- Chevron Down Icon -->
                        <svg class="chevron-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>



                    <!-- Other purpose text box (shown only when Other is selected) -->
                    <div class="other-purpose-wrap" id="other-purpose-wrap">
                        <label for="other_purpose" style="margin-top:0; font-size:12.5px; color:#475569;">Please specify
                            your purpose</label>
                        <textarea id="other_purpose" name="other_purpose"
                            placeholder="Describe your reason for visiting the campus..."
                            maxlength="300"><?= htmlspecialchars($other_purpose ?? '') ?></textarea>
                        <p class="helper-text">Max 300 characters.</p>
                    </div>
                </div>

                <div>
                    <label>Date of Visit</label>
                    <div class="input-container">
                        <!-- Calendar Icon SVG -->
                        <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>

                        <input type="text" value="<?= date('d M Y') ?> (Today)" disabled
                            style="background: #f1f5f9; color: #4b5563; cursor: not-allowed;">
                        <input type="hidden" name="visit_date" value="<?= date('Y-m-d') ?>">

                        <!-- Lock Icon SVG on Right -->
                        <svg class="lock-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <p class="helper-text">This date is auto-selected and cannot be changed.</p>
                </div>

                <div style="margin-top: 6px;">
                    <button type="submit" class="primary-btn">
                        <!-- Badge Icon SVG -->
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 014 0v1m-4-1a2 2 0 002 2h2a2 2 0 002-2" />
                        </svg>
                        Generate Gate Pass
                    </button>
                </div>
            </form>

            <!-- Information secure shield footer inside card -->
            <div class="secure-notice">
                <!-- Shield SVG -->
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                Your information is secure and will only be used for gate pass generation.
            </div>

        </div>

        <!-- Bottom ID Proof Alert Note Box -->
        <div class="bottom-alert">
            <div class="icon-badge">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <div>
                <h5>Important Note</h5>
                <p>Please carry your valid University ID or any government ID proof during your visit.</p>
            </div>
        </div>

    </div>

    <!-- Global bottom address and contact footer -->
    <div class="global-footer">
        <div class="left-col">
            <div class="footer-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Dr. P. A. Inamdar University | Pune
            </div>
            <p class="address">2390-B, K. B. Hidayatullah Road, Camp, Pune - 411001, Maharashtra, India</p>

            <div class="footer-item" style="margin-top: 6px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                </svg>
                <a href="https://www.dpaiu.edu.in" target="_blank">www.dpaiu.edu.in</a>
            </div>
        </div>

        <div class="right-col">
            <div class="footer-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                020 2605 5171
            </div>

            <div class="footer-item" style="margin-top: 4px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <a href="mailto:info@dpaiu.edu.in">info@dpaiu.edu.in</a>
            </div>

            <div class="footer-item"
                style="margin-top: 10px; border-top: 1px dashed rgba(255,255,255,0.15); padding-top: 8px;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2.5" style="color: #10b981;">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <a href="../staff/login.php" style="color: #10b981;">Staff Portal Login</a>
            </div>
        </div>
    </div>

    <script>
        function toggleOtherPurpose(val) {
            const wrap = document.getElementById('other-purpose-wrap');
            const textarea = document.getElementById('other_purpose');
            if (val === 'Other') {
                wrap.classList.add('visible');
                textarea.required = true;
            } else {
                wrap.classList.remove('visible');
                textarea.required = false;
                textarea.value = '';
            }
        }
        // On page load, restore state (e.g. after validation failure)
        window.addEventListener('DOMContentLoaded', function () {
            const sel = document.getElementById('purpose_of_visit');
            if (sel && sel.value === 'Other') {
                toggleOtherPurpose('Other');
            }
        });
    </script>

</body>

</html>