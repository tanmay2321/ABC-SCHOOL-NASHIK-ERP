<?php
/**
 * REST API Helper & CORS Handler
 * Bellwether International School Nandurbar ERP
 */

// Enable CORS for Flutter Web, iOS, Android, and Desktop
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/db.php';

define('API_SECRET_KEY', 'BWIS_NANDURBAR_ERP_SUPER_SECURE_TOKEN_SALT_2026');

/**
 * Send JSON API response
 */
function api_response($data = [], $status = 200) {
    http_response_code($status);
    echo json_encode([
        'success' => true,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Send JSON API error
 */
function api_error($message = 'An error occurred', $status = 400, $extra = []) {
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $extra), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Parse JSON or form POST input
 */
function get_json_input() {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return array_merge($_POST, $json);
        }
    }
    return $_POST;
}

/**
 * Generate Secure Token for Mobile & Web clients
 */
function generate_auth_token($user) {
    $payload = [
        'uid' => $user['id'],
        'role' => $user['role'],
        'usr' => $user['username'],
        'exp' => time() + (86400 * 30) // 30 days validity
    ];
    $json = json_encode($payload);
    $b64 = base64_encode($json);
    $sig = hash_hmac('sha256', $b64, API_SECRET_KEY);
    return $b64 . '.' . $sig;
}

/**
 * Validate and authenticate Bearer token or User-ID header
 */
function get_authenticated_user($pdo, $required_role = null) {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    // Check Bearer Token
    if (preg_match('/Bearer\s+(\S+)/i', $auth, $matches)) {
        $token = $matches[1];
        $parts = explode('.', $token);
        if (count($parts) === 2) {
            list($b64, $sig) = $parts;
            if (hash_equals(hash_hmac('sha256', $b64, API_SECRET_KEY), $sig)) {
                $payload = json_decode(base64_decode($b64), true);
                if ($payload && isset($payload['uid']) && ($payload['exp'] > time())) {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
                    $stmt->execute(['id' => $payload['uid']]);
                    $user = $stmt->fetch();
                    if ($user && $user['status'] === 'approved') {
                        return attach_user_profile($pdo, $user, $required_role);
                    }
                }
            }
        }
    }

    // Fallback: check query parameter ?token=
    if (isset($_GET['token'])) {
        $token = $_GET['token'];
        $parts = explode('.', $token);
        if (count($parts) === 2) {
            list($b64, $sig) = $parts;
            if (hash_equals(hash_hmac('sha256', $b64, API_SECRET_KEY), $sig)) {
                $payload = json_decode(base64_decode($b64), true);
                if ($payload && isset($payload['uid']) && ($payload['exp'] > time())) {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
                    $stmt->execute(['id' => $payload['uid']]);
                    $user = $stmt->fetch();
                    if ($user && $user['status'] === 'approved') {
                        return attach_user_profile($pdo, $user, $required_role);
                    }
                }
            }
        }
    }

    // Fallback: check active PHP session if accessed from web browser
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user && $user['status'] === 'approved') {
            return attach_user_profile($pdo, $user, $required_role);
        }
    }

    api_error('Unauthorized: Invalid or expired authentication token. Please log in again.', 401);
}

/**
 * Attach profile data (student/teacher details) and check role
 */
function attach_user_profile($pdo, $user, $required_role = null) {
    if ($required_role) {
        $roles = is_array($required_role) ? $required_role : [$required_role];
        if (!in_array($user['role'], $roles)) {
            api_error('Forbidden: Your account role does not have access to this resource.', 403);
        }
    }

    $profile = [];
    if ($user['role'] === 'student') {
        $sStmt = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
        $sStmt->execute(['uid' => $user['id']]);
        $profile = $sStmt->fetch() ?: [];
    } elseif ($user['role'] === 'teacher') {
        $tStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = :uid LIMIT 1");
        $tStmt->execute(['uid' => $user['id']]);
        $profile = $tStmt->fetch() ?: [];
    }

    $user['profile'] = $profile;
    return $user;
}
