<?php
// staff/logout.php

session_start();                 // make sure session is active

// remove all session data
$_SESSION = [];

// destroy session cookie (optional but cleaner)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// destroy session
session_destroy();

// redirect to staff login
header('Location: login.php');
exit;
