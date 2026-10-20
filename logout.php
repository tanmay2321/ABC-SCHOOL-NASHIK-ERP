<?php
/**
 * Hardened ERP Logout Handler
 * ABC School Nashik ERP System
 */
require_once __DIR__ . '/includes/auth.php';

// 1. Clear all session data
$_SESSION = [];

// 2. Invalidate and expire session cookie on the client browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 86400,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy session storage on the server
session_destroy();

// 4. Send anti-caching headers so browser cannot show cached private pages via back button
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// 5. Redirect cleanly to login
header("Location: /login.php?logged_out=1");
exit();
