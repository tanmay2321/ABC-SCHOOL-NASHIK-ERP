<?php
/**
 * Hardened Authentication, Session Security & Protection Architecture
 * ABC School Nashik ERP System
 */

if (!ob_get_level()) {
    ob_start();
}

// 1. Send Strict Security Headers on Every Response
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// 2. Hardened Session & Cookie Configuration (Prevents Auto-login & Cookie Theft)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    session_set_cookie_params([
        'lifetime' => 0,              // Cookie expires when browser closes (prevents unauthorized persistent login)
        'path' => '/',
        'domain' => '',
        'secure' => $is_https,        // Transmitted over HTTPS when available
        'httponly' => true,           // Prevents JavaScript access / XSS cookie theft
        'samesite' => 'Lax'           // Cross-Site Request Forgery (CSRF) protection
    ]);

    session_start();
}

// 3. Inactivity Timeout Enforcement (30 Minutes Maximum Inactive Session)
$session_timeout_seconds = 1800; // 30 minutes
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout_seconds)) {
        // Session has expired due to inactivity
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        header("Location: /login.php?timeout=1");
        exit();
    }
    $_SESSION['last_activity'] = time();

    // 4. Session Hijacking / Environment Fingerprint Protection
    $current_fingerprint = md5($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $current_fingerprint;
    } elseif ($_SESSION['fingerprint'] !== $current_fingerprint) {
        // Detected unexpected environment change - terminate immediately
        $_SESSION = [];
        session_destroy();
        header("Location: /login.php?hijack_detected=1");
        exit();
    }
}

// 5. CSRF Token Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

function csrf_field() {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals(csrf_token(), $token)) {
            http_response_code(403);
            die("Security Error: Invalid or expired security token. Please refresh the page and try again.");
        }
    }
}

require_once __DIR__ . '/../config/db.php';

// Flash message helper
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Get logged in user data
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role'],
        'status' => $_SESSION['status'] ?? 'approved',
        'name' => $_SESSION['full_name'] ?? 'User',
        'profile_id' => $_SESSION['profile_id'] ?? null,
        'code' => $_SESSION['code'] ?? ''
    ];
}

// Require authentication
function require_auth($allowed_roles = []) {
    if (!is_logged_in()) {
        set_flash('danger', 'Please login to access the ABC School ERP portal.');
        header("Location: /login.php");
        exit();
    }

    // Prevent caching of authenticated dashboard pages
    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
    }

    if (!empty($allowed_roles)) {
        if (is_string($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }
        
        $role = $_SESSION['role'];
        if (!in_array($role, $allowed_roles)) {
            // Redirect to their own dashboard
            if ($role === 'admin') {
                header("Location: /admin/index.php");
            } elseif ($role === 'teacher') {
                header("Location: /teacher/index.php");
            } else {
                header("Location: /student/index.php");
            }
            exit();
        }
    }
}

// Alias for role-based requirement
function require_role($role) {
    require_auth($role);
}

// Helper to sanitize input
function clean($data) {
    if (is_array($data)) {
        return array_map('clean', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Get school summary stats
function get_school_stats($pdo) {
    $stats = [];
    $stats['total_students'] = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $stats['approved_students'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'approved'")->fetchColumn();
    $stats['pending_students'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'pending'")->fetchColumn();
    $stats['total_teachers'] = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    $stats['total_notices'] = $pdo->query("SELECT COUNT(*) FROM notices")->fetchColumn();
    return $stats;
}
?>
