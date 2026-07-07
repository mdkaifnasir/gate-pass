<?php
// verify.php — PUBLIC page (no login required)
// Scanned via QR code from phone, shows pass details directly.

ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/config.php';

$token = isset($_GET['t']) ? trim($_GET['t']) : '';

if ($token === '') {
    $status  = 'error';
    $message = 'Invalid link. Please scan the QR code again.';
    $pass    = null;
} else {
    $stmt = $pdo->prepare("SELECT * FROM gate_passes WHERE token = :t LIMIT 1");
    $stmt->execute([':t' => $token]);
    $pass = $stmt->fetch();

    if (!$pass) {
        $status  = 'invalid';
        $message = 'Gate pass not found.';
    } elseif (!empty($pass['expires_at']) && new DateTime($pass['expires_at']) < new DateTime('now')) {
        $pdo->prepare("UPDATE gate_passes SET status = 'EXPIRED' WHERE id = :id")
            ->execute([':id' => $pass['id']]);
        $status  = 'expired';
        $message = 'This gate pass has expired.';
    } else {
        $status  = 'valid';
        $message = 'Gate pass verified successfully.';

        // Log the scan
        try {
            $pdo->prepare("INSERT INTO gate_logs (pass_id, scanned_at, gate_name) VALUES (:id, NOW(), :gate)")
                ->execute([':id' => $pass['id'], ':gate' => 'QR Scan (Public)']);
        } catch (PDOException $e) {
            // Logging failed — don't block the visitor
        }

        // Mark as USED only first time
        if ($pass['status'] === 'ACTIVE') {
            $pdo->prepare("UPDATE gate_passes SET status = 'USED' WHERE id = :id")
                ->execute([':id' => $pass['id']]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gate Pass Verification – Dr. P. A. Inamdar University</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Outfit', system-ui, sans-serif; }

        body {
            min-height: 100vh;
            background: #f0f4f8;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 28px 16px 40px;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.07);
            overflow: hidden;
        }

        /* ── Colour-coded banner at the top ── */
        .status-banner {
            padding: 22px 24px 18px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .status-banner.valid   { background: #dcfce7; }
        .status-banner.expired { background: #fef9c3; }
        .status-banner.invalid,
        .status-banner.error   { background: #fee2e2; }

        .status-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 26px;
        }
        .valid   .status-icon { background: #bbf7d0; }
        .expired .status-icon { background: #fef08a; }
        .invalid .status-icon,
        .error   .status-icon { background: #fecaca; }

        .status-text h2 {
            font-size: 18px;
            font-weight: 800;
            line-height: 1.2;
        }
        .valid   .status-text h2 { color: #14532d; }
        .expired .status-text h2 { color: #713f12; }
        .invalid .status-text h2,
        .error   .status-text h2 { color: #7f1d1d; }

        .status-text p {
            font-size: 12.5px;
            margin-top: 3px;
        }
        .valid   .status-text p { color: #166534; }
        .expired .status-text p { color: #92400e; }
        .invalid .status-text p,
        .error   .status-text p { color: #991b1b; }

        /* ── University Header ── */
        .uni-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 24px 0;
        }
        .uni-logo {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #e2e8f0;
        }
        .uni-name {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }
        .uni-tagline {
            font-size: 9px;
            color: #94a3b8;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        /* ── Detail rows ── */
        .details {
            padding: 16px 24px 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f1f5f9;
        }
        .detail-row:last-child { border-bottom: none; padding-bottom: 0; }

        .d-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 17px;
        }
        .d-icon.name    { background: #f3e8ff; }
        .d-icon.phone   { background: #dcfce7; }
        .d-icon.date    { background: #ede9fe; }
        .d-icon.purpose { background: #ffe4e6; }
        .d-icon.expires { background: #fff7ed; }

        .d-label {
            font-size: 10px;
            color: #94a3b8;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .d-value {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2px;
            line-height: 1.3;
        }

        /* ── Scan stamp ── */
        .scan-stamp {
            background: #f8fafc;
            border-top: 1px solid #f1f5f9;
            padding: 12px 24px;
            font-size: 11px;
            color: #64748b;
            text-align: center;
        }
        .scan-stamp strong { color: #0f172a; }

        /* ── Footer link ── */
        .footer-note {
            margin-top: 20px;
            font-size: 12px;
            color: #94a3b8;
            text-align: center;
        }
        .footer-note a { color: #3b82f6; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>

    <div class="card">

        <!-- Status Banner -->
        <div class="status-banner <?= $status ?>">
            <div class="status-icon">
                <?php if ($status === 'valid'): ?>✅
                <?php elseif ($status === 'expired'): ?>⏰
                <?php else: ?>❌<?php endif; ?>
            </div>
            <div class="status-text">
                <?php if ($status === 'valid'): ?>
                    <h2>Pass Verified ✓</h2>
                    <p>This gate pass is authentic and active.</p>
                <?php elseif ($status === 'expired'): ?>
                    <h2>Pass Expired</h2>
                    <p>This gate pass is no longer valid.</p>
                <?php elseif ($status === 'invalid'): ?>
                    <h2>Pass Not Found</h2>
                    <p>This QR code is not linked to any gate pass.</p>
                <?php else: ?>
                    <h2>Invalid Link</h2>
                    <p><?= htmlspecialchars($message) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($pass): ?>

        <!-- University Header -->
        <div class="uni-header">
            <img src="student/logo.png" alt="University Logo" class="uni-logo">
            <div>
                <div class="uni-name">Dr. P. A. Inamdar University</div>
                <div class="uni-tagline">Educate • Empower • Evolve</div>
            </div>
        </div>

        <!-- Student Details -->
        <div class="details">
            <div class="detail-row">
                <div class="d-icon name">👤</div>
                <div>
                    <div class="d-label">Student Name</div>
                    <div class="d-value"><?= htmlspecialchars($pass['student_name']) ?></div>
                </div>
            </div>

            <div class="detail-row">
                <div class="d-icon phone">📱</div>
                <div>
                    <div class="d-label">Mobile Number</div>
                    <div class="d-value"><?= htmlspecialchars($pass['mobile']) ?></div>
                </div>
            </div>

            <div class="detail-row">
                <div class="d-icon date">📅</div>
                <div>
                    <div class="d-label">Date of Visit</div>
                    <div class="d-value"><?= date('d M Y', strtotime($pass['created_at'])) ?></div>
                </div>
            </div>

            <div class="detail-row">
                <div class="d-icon purpose">📋</div>
                <div>
                    <div class="d-label">Purpose</div>
                    <div class="d-value"><?= htmlspecialchars($pass['purpose']) ?></div>
                </div>
            </div>

            <div class="detail-row">
                <div class="d-icon expires">⏳</div>
                <div>
                    <div class="d-label">Valid Until</div>
                    <div class="d-value"><?= date('d M Y, h:i A', strtotime($pass['expires_at'])) ?> IST</div>
                </div>
            </div>
        </div>

        <!-- Scan Timestamp -->
        <div class="scan-stamp">
            Scanned on <strong><?= date('d M Y \a\t h:i A') ?></strong>
        </div>

        <?php endif; ?>
    </div>

    <p class="footer-note">
        Powered by <a href="https://drpaiu.edu.in" target="_blank">Dr. P. A. Inamdar University</a>
    </p>

</body>
</html>
