<?php
/**
 * REST API: User Login
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method Not Allowed. Use POST to authenticate.', 405);
}

$input = get_json_input();
$login_input = trim($input['login_input'] ?? $input['email'] ?? $input['username'] ?? '');
$password = $input['password'] ?? '';

if (empty($login_input) || empty($password)) {
    api_error('Please provide your Username/Email and Password.');
}

// Query user by email or username
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :input OR username = :input LIMIT 1");
$stmt->execute(['input' => $login_input]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    api_error('Invalid login credentials. Please verify your email/username and password.', 401);
}

// Check approval / status
if ($user['status'] === 'pending') {
    api_error('Your student registration is pending approval by the School Admin.', 403, [
        'status' => 'pending'
    ]);
}
if ($user['status'] === 'rejected') {
    api_error('Your registration was rejected by the administration. Please contact the school office.', 403, [
        'status' => 'rejected'
    ]);
}
if ($user['status'] === 'inactive') {
    api_error('Your account is currently inactive. Please contact school administration.', 403, [
        'status' => 'inactive'
    ]);
}

// Generate token
$token = generate_auth_token($user);

// Fetch Profile info
$profile = [];
if ($user['role'] === 'student') {
    $sStmt = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
    $sStmt->execute(['uid' => $user['id']]);
    $student = $sStmt->fetch();
    $profile = [
        'id' => $student['id'] ?? null,
        'code' => $student['student_code'] ?? '',
        'full_name' => $student['full_name'] ?? $user['username'],
        'gender' => $student['gender'] ?? '',
        'dob' => $student['dob'] ?? '',
        'class' => $student['class'] ?? '',
        'roll_no' => $student['roll_no'] ?? '',
        'guardian_name' => $student['guardian_name'] ?? '',
        'phone' => $student['phone'] ?? '',
        'address' => $student['address'] ?? '',
        'blood_group' => $student['blood_group'] ?? '',
        'photo' => $student['photo'] ?? null,
        'admission_date' => $student['admission_date'] ?? ''
    ];
} elseif ($user['role'] === 'teacher') {
    $tStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = :uid LIMIT 1");
    $tStmt->execute(['uid' => $user['id']]);
    $teacher = $tStmt->fetch();
    $profile = [
        'id' => $teacher['id'] ?? null,
        'code' => $teacher['teacher_code'] ?? '',
        'full_name' => $teacher['full_name'] ?? $user['username'],
        'gender' => $teacher['gender'] ?? '',
        'department' => $teacher['department'] ?? 'Faculty',
        'designation' => $teacher['designation'] ?? 'Teacher',
        'qualification' => $teacher['qualification'] ?? '',
        'phone' => $teacher['phone'] ?? '',
        'assigned_class' => $teacher['assigned_class'] ?? null,
        'photo' => $teacher['photo'] ?? null
    ];
} else {
    // Admin / Principal / Vice-Principal
    $name = 'System Administrator';
    if ($user['role'] === 'principal') {
        $name = get_setting($pdo, 'principal_name', 'Dr. R. K. Sharma (Principal)');
    } elseif ($user['role'] === 'vice_principal') {
        $name = get_setting($pdo, 'vice_principal_name', 'Prof. S. M. Deshmukh (Vice Principal)');
    } else {
        $name = get_setting($pdo, 'admin_name', 'System Administrator');
    }
    $profile = [
        'full_name' => $name,
        'designation' => ucfirst(str_replace('_', ' ', $user['role']))
    ];
}

// School details
$school = [
    'name' => get_setting($pdo, 'school_name', 'BELLWETHER INTERNATIONAL SCHOOL NANDURBAR'),
    'city' => get_setting($pdo, 'school_city', 'Nandurbar'),
    'phone' => get_setting($pdo, 'school_phone', '+91 6262848446'),
    'email' => get_setting($pdo, 'school_email', 'bwis.nandurbar@gmail.com'),
    'address' => get_setting($pdo, 'school_address', 'Old Nalwa Road, Nandurbar - 425412'),
    'logo' => get_setting($pdo, 'school_logo', ''),
    'academic_year' => get_setting($pdo, 'academic_year', '2026-2027')
];

api_response([
    'token' => $token,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'],
        'status' => $user['status']
    ],
    'profile' => $profile,
    'school' => $school
]);
