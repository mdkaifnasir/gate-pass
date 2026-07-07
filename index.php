<?php
// index.php
header("Location: student/request.php");
exit;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>College Gate Pass System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #5b6bff, #8b4dff);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 16px;
            color: #fff;
        }

        .container {
            width: 100%;
            max-width: 480px;
        }

        .heading {
            text-align: center;
            margin-bottom: 24px;
        }

        .heading-icon {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            border: 2px solid rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 28px;
        }

        .heading h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .heading p {
            font-size: 14px;
            opacity: 0.9;
        }

        .card {
            background: #ffffff;
            border-radius: 18px;
            padding: 24px 20px;
            box-shadow: 0 18px 35px rgba(0, 0, 0, 0.18);
            text-align: center;
            color: #111827;
        }

        .card-icon {
            width: 72px;
            height: 72px;
            border-radius: 999px;
            background: radial-gradient(circle at 30% 0, #ffffff66, #4f46e5);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 32px;
            color: #fff;
        }

        .card h2 {
            font-size: 20px;
            margin-bottom: 6px;
        }

        .card p {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 18px;
        }

        .primary-btn {
            display: inline-block;
            width: 100%;
            padding: 12px 16px;
            border-radius: 999px;
            border: none;
            outline: none;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(90deg, #2563eb, #8b5cf6);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.35);
            transition: transform 0.1s ease, box-shadow 0.1s ease;
            text-decoration: none;
            text-align: center;
        }

        .primary-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 26px rgba(37, 99, 235, 0.4);
        }

        .section-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255, 255, 255, 0.8);
            text-align: center;
            margin-top: 20px;
        }

        .secondary-card {
            margin-top: 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            color: #f9fafb;
            font-size: 13px;
        }

        @media (min-width: 640px) {
            .heading h1 {
                font-size: 30px;
            }
        }
    </style>
</head>

<body>
    <div class="container">

        <div class="heading">
            <div class="heading-icon">✓</div>
            <h1>Gate Pass System</h1>
            <p>Secure, time‑limited QR code based visitor entry management</p>
        </div>

        <!-- Students card -->
        <div class="card">
            <div class="card-icon">◎</div>
            <h2>Students</h2>
            <p>Generate a gate pass with QR code for admission enquiry & campus visits.</p>
            <a href="student/request.php" class="primary-btn">Generate Gate Pass</a>
        </div>

        <!-- Gate staff card (short section like your screenshot) -->
        <div class="section-label">Gate Staff</div>
        <div class="secondary-card">
            <div>
                <strong>Scan & verify QR</strong><br>
                Manage real‑time admission visitor logs.
            </div>
            <a href="staff/login.php" style="align-self:center;padding:6px 14px;border-radius:999px;background:#10b981;color:#fff;
                  font-size:12px;font-weight:600;text-decoration:none;">
                Staff Login
            </a>
        </div>

    </div>
</body>

</html>