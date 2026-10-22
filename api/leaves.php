<?php
/**
 * REST API: Leave Applications & Approval Workflow
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];
$profile = $user['profile'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($role === 'student') {
        $stmt = $pdo->prepare("SELECT * FROM leave_applications WHERE user_id = :uid ORDER BY id DESC");
        $stmt->execute(['uid' => $user['id']]);
        $leaves = $stmt->fetchAll();

    } elseif ($role === 'teacher') {
        // Leaves of this teacher + leaves of students in assigned class
        $assigned_class = $profile['assigned_class'] ?? '';
        $stmt = $pdo->prepare("SELECT l.*, u.username, s.full_name as applicant_name, s.class, s.roll_no, s.photo
            FROM leave_applications l
            JOIN users u ON l.user_id = u.id
            LEFT JOIN students s ON u.id = s.user_id
            WHERE l.user_id = :uid OR (s.class = :cls AND l.role = 'student')
            ORDER BY l.id DESC");
        $stmt->execute(['uid' => $user['id'], 'cls' => $assigned_class]);
        $leaves = $stmt->fetchAll();

    } else {
        // Admin: all leaves
        $leaves = $pdo->query("SELECT l.*, u.username, 
            COALESCE(s.full_name, t.full_name, u.username) as applicant_name,
            s.class, s.roll_no, t.department
            FROM leave_applications l
            JOIN users u ON l.user_id = u.id
            LEFT JOIN students s ON u.id = s.user_id
            LEFT JOIN teachers t ON u.id = t.user_id
            ORDER BY l.id DESC")->fetchAll();
    }

    api_response([
        'total' => count($leaves),
        'leaves' => $leaves
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = get_json_input();
    $action = $input['action'] ?? 'apply';

    if ($action === 'apply') {
        $leave_type = trim($input['leave_type'] ?? 'Sick Leave');
        $from_date = trim($input['from_date'] ?? date('Y-m-d'));
        $to_date = trim($input['to_date'] ?? date('Y-m-d'));
        $reason = trim($input['reason'] ?? '');

        if (empty($reason)) {
            api_error('Reason for leave is required.');
        }

        $ins = $pdo->prepare("INSERT INTO leave_applications 
            (user_id, role, leave_type, from_date, to_date, reason, status) 
            VALUES (:uid, :role, :ltype, :from, :to, :reason, 'Pending')");
        $ins->execute([
            'uid' => $user['id'],
            'role' => $role,
            'ltype' => $leave_type,
            'from' => $from_date,
            'to' => $to_date,
            'reason' => $reason
        ]);

        api_response([
            'message' => 'Leave application submitted successfully. Awaiting approval.',
            'leave_id' => $pdo->lastInsertId()
        ], 201);

    } elseif ($action === 'update' && in_array($role, ['teacher', 'admin', 'principal', 'vice_principal'])) {
        $app_id = (int)($input['application_id'] ?? 0);
        $status = in_array($input['status'] ?? '', ['Approved', 'Rejected']) ? $input['status'] : 'Approved';
        $remarks = trim($input['admin_remarks'] ?? '');

        if ($app_id <= 0) {
            api_error('Valid application ID is required.');
        }

        $upd = $pdo->prepare("UPDATE leave_applications SET status = :st, admin_remarks = :rem WHERE id = :id");
        $upd->execute(['st' => $status, 'rem' => $remarks, 'id' => $app_id]);

        api_response([
            'message' => "Leave application #{$app_id} marked as {$status}."
        ]);
    } else {
        api_error('Invalid action or unauthorized.', 403);
    }
}

api_error('Method Not Allowed', 405);
