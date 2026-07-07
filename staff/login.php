<?php
// staff/login.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

$errors = [];

// if already logged in, go to dashboard
if (!empty($_SESSION['staff_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $errors[] = 'Username and password are required.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM staff_users WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        // Check password match (plain text match per original codebase structure)
        if ($user && $password === $user['password_hash']) {
            $_SESSION['staff_id']   = $user['id'];
            $_SESSION['staff_name'] = $user['username'];
            header('Location: dashboard.php');
            exit;
        } else {
            $errors[] = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Login - Gate Pass System</title>
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
            background: linear-gradient(135deg, rgba(9, 21, 46, 0.96) 0%, rgba(21, 39, 77, 0.96) 100%), url('../student/campus.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Abstract digital grid background lines matching university designs */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px);
            background-size: 24px 24px;
            pointer-events: none;
            z-index: 0;
        }

        /* Top circular back button */
        .back-circle {
            position: absolute;
            left: 24px;
            top: 24px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            text-decoration: none;
            transition: all 0.2s ease;
            z-index: 20;
        }
        .back-circle:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }
        .back-circle svg {
            width: 18px;
            height: 18px;
        }

        /* Wrapper aligning everything nicely */
        .wrapper {
            width: 100%;
            max-width: 440px;
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 2;
        }

        /* Top branding header elements */
        .header-section {
            text-align: center;
            margin-bottom: 24px;
            width: 100%;
        }
        .header-section .logo-badge {
            width: 125px;
            height: 125px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
            margin: 0 auto 16px auto;
            display: block;
        }
        .header-section h1 {
            color: #ffffff;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.01em;
            margin-bottom: 6px;
        }
        .header-section .tagline {
            color: rgba(255, 255, 255, 0.7);
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.2em;
        }

        /* Main Login Card */
        .card {
            width: 100%;
            background: #ffffff;
            border-radius: 20px;
            padding: 32px 28px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        /* Top icon circle and labels inside Card */
        .card-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .card-shield-circle {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #eff6ff;
            color: #4F46E5;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px auto;
            border: 1.5px solid #dbeafe;
        }
        .card-shield-circle svg {
            width: 24px;
            height: 24px;
        }
        .card-header h2 {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
        }
        .card-header p {
            font-size: 13.5px;
            color: #64748b;
            margin-top: 4px;
            font-weight: 500;
        }

        /* Divider inside card */
        .divider-wrap {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px 0;
            width: 100%;
        }
        .divider-line {
            border: none;
            border-top: 1.5px solid #f1f5f9;
            width: 100%;
        }
        .divider-badge {
            position: absolute;
            background: #ffffff;
            padding: 0 10px;
            color: #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .divider-badge svg {
            width: 14px;
            height: 14px;
        }

        /* Form elements & wrappers */
        form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        label {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }

        /* Secure input wrapper with vertical shaded icons */
        .input-field-wrap {
            display: flex;
            align-items: stretch;
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.2s ease;
            position: relative;
        }
        .input-field-wrap:focus-within {
            border-color: #4F46E5;
            box-shadow: 0 0 0 3.5px rgba(79, 70, 229, 0.12);
        }
        .input-addon {
            width: 48px;
            background: #eff6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            border-right: 1.5px solid #e2e8f0;
            color: #4F46E5;
            flex-shrink: 0;
        }
        .input-addon svg {
            width: 18px;
            height: 18px;
        }
        .input-field-wrap input {
            border: none;
            outline: none;
            padding: 12px 14px;
            font-size: 14px;
            color: #1e293b;
            font-weight: 500;
            flex-grow: 1;
            width: 100%;
            background: transparent;
        }
        .input-field-wrap input::placeholder {
            color: #94a3b8;
        }
        
        /* Show/Hide eye utility button */
        .eye-toggle-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #64748b;
            padding: 0 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.15s ease;
        }
        .eye-toggle-btn:hover {
            color: #4f46e5;
        }

        /* Options line: Remember / Forgot password */
        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 2px;
            font-size: 13px;
        }
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
        }
        .checkbox-container input {
            cursor: pointer;
            accent-color: #4f46e5;
            width: 16px;
            height: 16px;
        }
        .forgot-link {
            color: #4f46e5;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.15s ease;
        }
        .forgot-link:hover {
            color: #6366f1;
            text-decoration: underline;
        }

        /* Primary Login Button */
        .primary-btn {
            width: 100%;
            padding: 13px 16px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-size: 15.5px;
            font-weight: 700;
            color: #ffffff;
            background: linear-gradient(90deg, #6366f1, #4F46E5);
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.15s ease;
            margin-top: 6px;
        }
        .primary-btn:hover {
            transform: translateY(-0.5px);
            box-shadow: 0 6px 18px rgba(79, 70, 229, 0.35);
        }
        .primary-btn:active {
            transform: translateY(0.5px);
        }

        /* Errors notification */
        .errors {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            color: #b91c1c;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 13.5px;
            font-weight: 600;
            line-height: 1.4;
            margin-bottom: 16px;
        }

        /* Default credentials info card */
        .default-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 18px;
            background: #f4f6fc;
            border: 1.5px dashed #bfdbfe;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 12.5px;
            color: #1e3a8a;
            font-weight: 500;
        }
        .default-info .info-icon-circle {
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
        .default-info .info-text span.highlight {
            color: #4F46E5;
            font-weight: 700;
        }

        /* Secure Access footer banner underneath */
        .secure-footer-card {
            width: 100%;
            background: rgba(3, 7, 18, 0.4);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 16px 20px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .sec-foot-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .sec-foot-check-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #10b981;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        }
        .sec-foot-check-circle svg {
            width: 16px;
            height: 16px;
        }
        .sec-foot-text h4 {
            font-size: 13.5px;
            font-weight: 700;
            color: #ffffff;
        }
        .sec-foot-text p {
            font-size: 11.5px;
            color: #94a3b8;
            margin-top: 2px;
            line-height: 1.35;
        }
        .sec-foot-right {
            flex-shrink: 0;
        }

        /* Padlock glowing animations */
        .glow-padlock-container {
            position: relative;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .glow-padlock-svg {
            width: 22px;
            height: 22px;
            color: #818cf8;
            position: relative;
            z-index: 2;
            filter: drop-shadow(0 0 6px rgba(129, 140, 248, 0.6));
        }
        .glow-pulse-ring {
            position: absolute;
            border-radius: 50%;
            border: 1.5px solid rgba(129, 140, 248, 0.25);
            background: rgba(129, 140, 248, 0.03);
            pointer-events: none;
        }
        .ring-1 {
            width: 32px;
            height: 32px;
            animation: pulse-glow 2.5s infinite ease-in-out;
        }
        .ring-2 {
            width: 42px;
            height: 42px;
            animation: pulse-glow 2.5s infinite ease-in-out 1.25s;
        }
        @keyframes pulse-glow {
            0% {
                transform: scale(0.9);
                opacity: 0.2;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.6;
            }
            100% {
                transform: scale(0.9);
                opacity: 0.2;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 24px 12px;
            }
            .back-circle {
                left: 16px;
                top: 16px;
                width: 36px;
                height: 36px;
            }
            .header-section .logo-badge {
                width: 100px;
                height: 100px;
            }
            .header-section h1 {
                font-size: 19px;
            }
            .card {
                padding: 24px 18px;
            }
            .card-header h2 {
                font-size: 19px;
            }
            .card-header p {
                font-size: 12.5px;
            }
            .primary-btn {
                padding: 12px;
                font-size: 14.5px;
            }
            .sec-foot-text h4 {
                font-size: 12px;
            }
            .sec-foot-text p {
                font-size: 10.5px;
            }
        }
    </style>
</head>
<body>

<!-- Circular Back Arrow -->
<a href="../student/request.php" class="back-circle" title="Back to Student Request Form">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
    </svg>
</a>

<div class="wrapper">

    <!-- Top Branding Logo & Stacked Typography -->
    <div class="header-section">
        <img src="../student/logo.png" alt="University Logo Badge" class="logo-badge">
        <h1>Dr. P. A. Inamdar University | Pune</h1>
        <p class="tagline">EDUCATE • EMPOWER • EVOLVE</p>
    </div>

    <!-- Login credentials card -->
    <div class="card">
        
        <div class="card-header">
            <!-- Shield lock badge icon -->
            <div class="card-shield-circle">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            <h2>Staff Login</h2>
            <p>Login to access the gate management dashboard.</p>
        </div>

        <div class="divider-wrap">
            <hr class="divider-line">
            <div class="divider-badge">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
        </div>

        <!-- Render visual auth validation errors if present -->
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?>
            </div>
        <?php endif; ?>

        <!-- Form submitting via secure POST -->
        <form method="post">
            
            <!-- Username Input Field -->
            <div class="form-group">
                <label for="username-field">Username</label>
                <div class="input-field-wrap">
                    <div class="input-addon">
                        <!-- User SVG Outline -->
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <input type="text" id="username-field" name="username" placeholder="Enter your username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
            </div>

            <!-- Password Input Field -->
            <div class="form-group">
                <label for="password-field">Password</label>
                <div class="input-field-wrap">
                    <div class="input-addon">
                        <!-- Locked Padlock SVG -->
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input type="password" id="password-field" name="password" placeholder="Enter your password" required>
                    
                    <!-- Eye visibility microtoggle -->
                    <button type="button" class="eye-toggle-btn" id="eye-icon" onclick="togglePassword()" title="Toggle password visibility">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Form Checkbox options -->
            <div class="form-options">
                <label class="checkbox-container">
                    <input type="checkbox" name="remember" checked>
                    Remember me
                </label>
                <a href="#" class="forgot-link" onclick="alert('Please contact the System Administrator to reset your password.')">Forgot Password?</a>
            </div>

            <!-- Submission Trigger -->
            <button type="submit" class="primary-btn">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="width: 18px; height: 18px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l4-4m0 0l-4-4m4 4H3m13-4v12a2 2 0 002 2h3a2 2 0 002-2V6a2 2 0 00-2-2h-3a2 2 0 00-2 2" />
                </svg>
                Login
            </button>

        </form>



    </div>

    <!-- Floating Translucent Secure Padlock Footer -->
    <div class="secure-footer-card">
        <div class="sec-foot-left">
            <div class="sec-foot-check-circle">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <div class="sec-foot-text">
                <h4>Secure Access</h4>
                <p>Only authorized staff can access the gate management system.</p>
            </div>
        </div>
        
        <div class="sec-foot-right">
            <div class="glow-padlock-container">
                <div class="glow-pulse-ring ring-1"></div>
                <div class="glow-pulse-ring ring-2"></div>
                <svg class="glow-padlock-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" fill="rgba(129, 140, 248, 0.15)"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    <circle cx="12" cy="16" r="1.5" fill="currentColor"></circle>
                </svg>
            </div>
        </div>
    </div>

</div>

<!-- Animated Show/Hide Password Javascript Logic -->
<script>
function togglePassword() {
    const passwordField = document.getElementById('password-field');
    const eyeIcon = document.getElementById('eye-icon');
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        eyeIcon.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
            </svg>
        `;
    } else {
        passwordField.type = 'password';
        eyeIcon.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
        `;
    }
}
</script>
</body>
</html>
