<?php
/**
 * REST API: Faculty & Teacher Management
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];

// GET: List teachers
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $department = $_GET['department'] ?? '';

    $where = "WHERE 1=1";
    $params = [];

    if (!empty($department)) {
        $where .= " AND t.department = :dept";
        $params['dept'] = $department;
    }

    $sql = "SELECT t.id, t.user_id, t.teacher_code, t.full_name, t.gender, t.phone, 
            t.department, t.designation, t.qualification, t.assigned_class, t.joining_date, t.photo,
            u.email, u.username, u.status,
            (SELECT COUNT(*) FROM subjects s WHERE s.teacher_id = t.id) as subjects_count
            FROM teachers t
            JOIN users u ON t.user_id = u.id
            {$where}
            ORDER BY t.full_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $teachers = $stmt->fetchAll();

    api_response([
        'total' => count($teachers),
        'teachers' => $teachers
    ]);
}

// POST: Manage Faculty (Create, Assign Class, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($role, ['admin', 'principal', 'vice_principal'])) {
        api_error('Only administrators and school leadership can manage faculty.', 403);
    }

    $input = get_json_input();
    $action = $input['action'] ?? '';

    // 1. Create Teacher
    if ($action === 'create') {
        $full_name = trim($input['full_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? 'teacher123';
        $department = trim($input['department'] ?? 'Science & Tech');
        $designation = trim($input['designation'] ?? 'Senior Faculty');
        $qualification = trim($input['qualification'] ?? 'M.Sc., B.Ed');
        $assigned_class = trim($input['assigned_class'] ?? 'Class 10');
        $phone = trim($input['phone'] ?? '+91 9876543210');
        $gender = trim($input['gender'] ?? 'Female');

        if (empty($full_name) || empty($email)) {
            api_error('Full name and email are required.');
        }

        $check = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
        $check->execute(['e' => $email]);
        if ($check->fetch()) {
            api_error('An account with this email already exists.');
        }

        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $uStmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (:u, :e, :p, 'teacher', 'approved')");
            $uStmt->execute(['u' => $email, 'e' => $email, 'p' => $hash]);
            $user_id = (int)$pdo->lastInsertId();

            $teacher_code = 'BWIS-FAC-' . str_pad($user_id, 3, '0', STR_PAD_LEFT);

            $tStmt = $pdo->prepare("INSERT INTO teachers 
                (user_id, teacher_code, full_name, gender, phone, department, designation, qualification, assigned_class, joining_date)
                VALUES (:uid, :code, :name, :gen, :ph, :dept, :desig, :qual, :acls, :jdate)");
            $tStmt->execute([
                'uid' => $user_id,
                'code' => $teacher_code,
                'name' => $full_name,
                'gen' => $gender,
                'ph' => $phone,
                'dept' => $department,
                'desig' => $designation,
                'qual' => $qualification,
                'acls' => $assigned_class,
                'jdate' => date('Y-m-d')
            ]);
            $teacher_id = (int)$pdo->lastInsertId();

            $pdo->commit();
            api_response([
                'message' => "Faculty member {$full_name} added successfully!",
                'teacher_id' => $teacher_id,
                'teacher_code' => $teacher_code
            ], 201);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            api_error('Failed to create teacher: ' . $e->getMessage(), 500);
        }
    }

    // 2. Assign Class / Update
    if ($action === 'assign_class') {
        $teacher_id = (int)($input['teacher_id'] ?? 0);
        $assigned_class = trim($input['assigned_class'] ?? '');

        if ($teacher_id <= 0) api_error('Valid teacher_id is required.');

        $stmt = $pdo->prepare("UPDATE teachers SET assigned_class = :acls WHERE id = :tid");
        $stmt->execute(['acls' => $assigned_class, 'tid' => $teacher_id]);

        api_response(['message' => "Assigned class updated to {$assigned_class}."]);
    }

    // 3. Delete Teacher
    if ($action === 'delete') {
        $user_id = (int)($input['user_id'] ?? 0);
        if ($user_id <= 0) api_error('Valid user_id is required.');

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM users WHERE id = :uid AND role = 'teacher'")->execute(['uid' => $user_id]);
        $pdo->commit();

        api_response(['message' => 'Teacher account removed.']);
    }

    api_error('Invalid action requested.');
}

api_error('Method Not Allowed', 405);
