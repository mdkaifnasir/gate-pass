<?php
// staff/scan.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

if (empty($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scan Gate Pass QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing:border-box; margin:0; padding:0; font-family:system-ui,-apple-system,"Segoe UI",sans-serif; }
        body {
            min-height:100vh;
            background:linear-gradient(135deg,#5b6bff,#8b4dff);
            display:flex;
            justify-content:center;
            align-items:center;
            padding:16px;
            color:#111827;
        }
        .card {
            width:100%;
            max-width:720px;
            background:#f9fafb;
            border-radius:18px;
            padding:20px 22px 22px;
            box-shadow:0 24px 45px rgba(15,23,42,0.42);
        }
        .header {
            display:flex;
            align-items:center;
            justify-content:space-between;
            margin-bottom:12px;
        }
        .left-header {
            display:flex;
            align-items:center;
            gap:10px;
        }
        .back-icon {
            width:28px;height:28px;border-radius:999px;
            background:#e5e7eb;display:flex;align-items:center;justify-content:center;
            font-size:16px;text-decoration:none;color:#111827;
        }
        h1 { font-size:20px;font-weight:600; }
        .subtitle { font-size:13px;color:#6b7280; }
        #reader {
            width:100%;
            max-width:420px;
            margin:14px auto 10px;
        }
        #result-box {
            margin-top:10px;
            padding:12px 14px;
            border-radius:14px;
        }
        .result-valid { background:#dcfce7; color:#14532d; }
        .result-invalid { background:#fee2e2; color:#991b1b; }
        .result-neutral { background:#e5e7eb; color:#374151; }
        #result-header {
            font-size:14px;
            font-weight:600;
            margin-bottom:6px;
        }
        #result-body {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
        }
        .result-left {
            font-size:12px;
            line-height:1.4;
        }
        .result-name {
            font-size:15px;
            font-weight:600;
            margin-bottom:4px;
        }
        .result-meta {
            font-size:12px;
            color:#4b5563;
        }
        .result-right img {
            width:72px;
            height:72px;
            border-radius:999px;
            object-fit:cover;
            border:2px solid rgba(255,255,255,0.8);
            box-shadow:0 4px 10px rgba(15,23,42,0.3);
        }
        .footer-text {
            margin-top:8px;
            font-size:12px;
            color:#6b7280;
            text-align:center;
        }

        /* PREMIUM ID CARD MODAL POPUP STYLES */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            padding: 20px;
            z-index: 9999;
            animation: fadeIn 0.25s ease-out;
        }

        .modal-container {
            width: 100%;
            max-width: 360px;
            background: #ffffff;
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
            transform: scale(0.9);
            animation: scaleUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            color: #0f172a;
        }

        .modal-close-btn {
            position: absolute;
            top: -14px;
            right: -14px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #ef4444;
            color: #ffffff;
            border: 2px solid #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
            transition: all 0.2s ease;
            z-index: 10000;
        }
        .modal-close-btn:hover {
            transform: scale(1.1);
            background: #dc2626;
        }

        /* ID Card Layout inside Modal */
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
            text-align: left;
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
            width: 100%;
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
            text-align: left;
            width: 100%;
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

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes scaleUp {
            from { transform: scale(0.9); }
            to { transform: scale(1); }
        }

        @media (max-width:640px){
            .card { padding:16px; }
            #result-body { flex-direction:row; }
            .result-name { font-size:14px; }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="header">
        <div class="left-header">
            <a href="dashboard.php" class="back-icon">&#8592;</a>
            <div>
                <h1>Scan QR Code</h1>
                <p class="subtitle">Point the camera at the student gate pass to verify it.</p>
            </div>
        </div>
    </div>

    <div id="reader"></div>

    <div id="result-box" class="result-neutral">
        <div id="result-header">Waiting for scan...</div>
        <div id="result-body" style="display:none;">
            <div class="result-left">
                <div class="result-name" id="res-name"></div>
                <div class="result-meta" id="res-roll"></div>
                <div class="result-meta" id="res-dept"></div>
                <div class="result-meta" id="res-mobile"></div>
                <div class="result-meta" id="res-purpose"></div>
                <div class="result-meta" id="res-valid"></div>
                <div class="result-meta" id="res-extra"></div>
            </div>
            <div class="result-right">
                <img id="student-photo" src="" alt="Student Photo">
            </div>
        </div>
    </div>

    <p class="footer-text">
        For best results, hold the QR code steady within the frame.
    </p>
</div>

<!-- Beautiful ID Card Modal Popup Overlay -->
<div class="modal-overlay" id="card-modal">
    <div class="modal-container">
        <button class="modal-close-btn" onclick="closeModal()">&times;</button>
        <div class="card-header">
            <img src="../student/logo.png" alt="University Logo" class="card-logo">
            <h2 id="modal-name">N/A</h2>
            <span id="modal-type">Admission Visitor · Gate Pass</span>
            <div class="verified-pill" id="modal-status-badge">✔ VERIFIED</div>
        </div>

        <div class="photo-box" id="modal-photo-box" style="display:none;">
            <img id="modal-photo" src="" alt="Visitor Photo">
        </div>

        <div class="card-body">


            <div class="info-group">
                <div class="section-label">Mobile Number</div>
                <div class="section-value" id="modal-mobile">N/A</div>
            </div>

            <div class="info-group">
                <div class="section-label">Date of Visit</div>
                <div class="section-value" id="modal-visit-date">N/A</div>
            </div>
        </div>

        <div class="card-footer">
            <div class="footer-col">
                <strong>Valid till</strong>
                <span id="modal-valid-till">N/A</span>
            </div>
            <div class="footer-col right">
                <strong>Last scan</strong>
                <span id="modal-last-scan">N/A</span>
            </div>
        </div>
    </div>
</div>

<!-- html5-qrcode CDN -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
    const box      = document.getElementById('result-box');
    const headEl   = document.getElementById('result-header');
    const bodyEl   = document.getElementById('result-body');
    const nameEl   = document.getElementById('res-name');
    const rollEl   = document.getElementById('res-roll');
    const deptEl   = document.getElementById('res-dept');
    const mobileEl = document.getElementById('res-mobile');
    const purposeEl= document.getElementById('res-purpose');
    const validEl  = document.getElementById('res-valid');
    const extraEl  = document.getElementById('res-extra');
    const photoImg = document.getElementById('student-photo');

    function setStatus(type){
        box.className = '';
        if(type==='valid') box.classList.add('result-valid');
        else if(type==='invalid') box.classList.add('result-invalid');
        else box.classList.add('result-neutral');
    }

    function closeModal() {
        document.getElementById('card-modal').style.display = 'none';
    }

    function showStudent(type, data, extra){
        setStatus(type);
        headEl.textContent = (data.status === 'used') ? 'Already Verified Pass' : 'Pass Verified';
        bodyEl.style.display = 'flex';

        nameEl.textContent   = data.student_name;
        rollEl.style.display = 'none';
        deptEl.textContent   = 'Mobile: ' + (data.mobile || '-');
        mobileEl.style.display = 'none';
        purposeEl.textContent= 'Valid till: ' + (data.expires_at || 'N/A');
        validEl.style.display = 'none';
        extraEl.textContent  = 'Type: Admission Visitor' + (extra ? ' · ' + extra : '');

        if (data.photo_path) {
            photoImg.src = '../' + data.photo_path;
            photoImg.style.display = 'block';
        } else {
            photoImg.style.display = 'none';
        }
        // Show "View full card" button
        showViewFullCardButton(data.token || data.token_id);

        // POPULATE AND SHOW THE STUNNING ID CARD MODAL POPUP
        document.getElementById('modal-name').textContent = data.student_name;

        document.getElementById('modal-mobile').textContent = data.mobile || 'N/A';
        
        // Format visit date
        const visitDate = data.created_at ? new Date(data.created_at) : new Date();
        const visitDateStr = visitDate.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
        document.getElementById('modal-visit-date').textContent = visitDateStr;
        
        // Format valid till
        if (data.expires_at) {
            const expDate = new Date(data.expires_at);
            const expDateStr = expDate.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' }) + ' ' + expDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            document.getElementById('modal-valid-till').textContent = expDateStr;
        } else {
            document.getElementById('modal-valid-till').textContent = 'N/A';
        }

        // Format last scan
        if (data.scanned_at) {
            const scanDate = new Date(data.scanned_at);
            const scanDateStr = scanDate.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' }) + ' ' + scanDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            document.getElementById('modal-last-scan').textContent = scanDateStr;
        } else {
            document.getElementById('modal-last-scan').textContent = 'First time';
        }

        // Handle photo display inside the modal
        const modalPhotoBox = document.getElementById('modal-photo-box');
        const modalPhoto = document.getElementById('modal-photo');
        if (data.photo_path) {
            modalPhoto.src = '../' + data.photo_path;
            modalPhotoBox.style.display = 'flex';
        } else {
            modalPhotoBox.style.display = 'none';
        }

        // Open the modal popup beautifully
        document.getElementById('card-modal').style.display = 'flex';
    }

    function simpleMessage(type, msg){
        setStatus(type);
        headEl.textContent = msg;
        bodyEl.style.display = 'none';
        hideViewFullCardButton();
    }

    function extractTokenFromUrl(decodedText) {
        try {
            const url = new URL(decodedText);
            return url.searchParams.get('t');
        } catch (e) {
            return decodedText; // if just token is encoded directly
        }
    }

    // Button handling for "View full card"
    let fullCardBtn = null;
    function showViewFullCardButton(token) {
        hideViewFullCardButton();
        if (!token) return;
        if (!fullCardBtn) {
            fullCardBtn = document.createElement('button');
            fullCardBtn.textContent = 'View full card';
            fullCardBtn.style.cssText = 
                'margin:10px auto 0;display:block;padding:8px 18px;border-radius:8px;border:none;background:#5b6bff;color:#fff;font-weight:600;cursor:pointer;font-size:15px;box-shadow:0 2px 5px rgba(91,107,255,0.10);';
            fullCardBtn.onclick = function() {
                window.location.href = 'view_pass.php?token=' + encodeURIComponent(token);
            };
            document.querySelector('.card').appendChild(fullCardBtn);
        }
    }
    function hideViewFullCardButton() {
        if (fullCardBtn && fullCardBtn.parentNode) {
            fullCardBtn.parentNode.removeChild(fullCardBtn);
        }
        fullCardBtn = null;
    }

    let lastScannedToken = '';
    let lastScannedTime = 0;
    let isScanningInProgress = false;

    function onScanSuccess(decodedText, decodedResult) {
        const token = extractTokenFromUrl(decodedText);
        if (!token) {
            return;
        }

        const now = Date.now();
        // Prevent scanning if:
        // 1. Another scan is already fetching/processing
        // 2. The ID Card modal popup is currently open on screen
        // 3. The same QR code was scanned less than 5 seconds ago (cooldown)
        if (
            isScanningInProgress || 
            document.getElementById('card-modal').style.display === 'flex' ||
            (token === lastScannedToken && now - lastScannedTime < 5000)
        ) {
            return;
        }

        isScanningInProgress = true;
        lastScannedToken = token;
        lastScannedTime = now;

        // Call verify.php so it writes to gate_logs
        fetch('../actions/verify.php?token=' + encodeURIComponent(token))
            .then(res => res.json())
            .then(data => {
                // Attach token so "View full card" works without rescanning
                data.token = token;
                if (data.status === 'valid' || data.status === 'used') {
                    showStudent('valid', data, 'Scanned at: ' + (data.scanned_at || ''));
                } else {
                    simpleMessage('invalid', data.message || 'Invalid / expired pass');
                }
            })
            .catch(() => {
                simpleMessage('invalid', 'Server error');
                // Reset scanned states on error to allow retry
                lastScannedToken = '';
            })
            .finally(() => {
                isScanningInProgress = false;
            });
    }

    // Camera startup with preference for back camera
    const html5QrCode = new Html5Qrcode("reader");

    Html5Qrcode.getCameras().then(cameras => {
        if (!cameras || cameras.length === 0) {
            simpleMessage('invalid', 'No camera found');
            return;
        }

        // Prefer back camera if available
        let cameraId = cameras[0].id;
        cameras.forEach(cam => {
            if (
                cam.label.toLowerCase().includes('back') ||
                cam.label.toLowerCase().includes('environment')
            ) {
                cameraId = cam.id;
            }
        });

        html5QrCode.start(
            cameraId,
            { fps: 10, qrbox: { width: 260, height: 260 } },
            onScanSuccess
        ).catch(err => {
            simpleMessage('invalid', 'Unable to start camera: ' + err);
        });

    }).catch(err => {
        simpleMessage('invalid', 'Camera error: ' + err);
    });
</script>
</body>
</html>
