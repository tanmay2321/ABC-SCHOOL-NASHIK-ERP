<?php
/**
 * Authentication and Session Management
 * ABC School Nashik ERP System
 */

if (!ob_get_level()) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
