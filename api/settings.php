<?php
/**
 * REST API: System Settings & School Profile
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $settings = [
        'school_name' => get_setting($pdo, 'school_name', 'BELLWETHER INTERNATIONAL SCHOOL NANDURBAR'),
        'school_city' => get_setting($pdo, 'school_city', 'Nandurbar'),
        'school_address' => get_setting($pdo, 'school_address', 'Old Nalwa Road, Nandurbar - 425412, Maharashtra'),
        'school_phone' => get_setting($pdo, 'school_phone', '+91 6262848446'),
        'school_email' => get_setting($pdo, 'school_email', 'bwis.nandurbar@gmail.com'),
        'school_affiliation' => get_setting($pdo, 'school_affiliation', 'CBSE Affiliated | Senior Secondary'),
        'school_logo' => get_setting($pdo, 'school_logo', '/assets/img/logo.png'),
        'principal_name' => get_setting($pdo, 'principal_name', 'Dr. R. K. Sharma (Principal)'),
        'vice_principal_name' => get_setting($pdo, 'vice_principal_name', 'Prof. S. M. Deshmukh (Vice Principal)'),
        'current_academic_year' => get_setting($pdo, 'current_academic_year', '2026-2027')
    ];

    api_response(['settings' => $settings]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($role, ['admin', 'principal'])) {
        api_error('Only administrators and the Principal can update school branding and settings.', 403);
    }

    $input = get_json_input();
    $keys = [
        'school_name', 'school_city', 'school_address', 'school_phone', 
        'school_email', 'school_affiliation', 'principal_name', 
        'vice_principal_name', 'current_academic_year'
    ];

    $updated = [];
    foreach ($keys as $k) {
        if (isset($input[$k])) {
            $val = trim($input[$k]);
            set_setting($pdo, $k, $val);
            $updated[$k] = $val;
        }
    }

    api_response([
        'message' => 'School information and branding updated successfully across all web and mobile portals!',
        'updated' => $updated
    ]);
}

api_error('Method Not Allowed', 405);
