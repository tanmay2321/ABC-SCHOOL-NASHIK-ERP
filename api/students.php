<?php
/**
 * REST API: Student Management & Admissions
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];

// GET: List students
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $class = $_GET['class'] ?? '';
    $status = $_GET['status'] ?? ''; // 'all', 'pending', 'approved', 'rejected'
    $search = trim($_GET['search'] ?? '');

    $where = "WHERE 1=1";
    $params = [];

    if (!empty($class)) {
        $where .= " AND s.class = :cls";
        $params['cls'] = $class;
    }

    if (!empty($status) && $status !== 'all') {
        $where .= " AND u.status = :st";
        $params['st'] = $status;
    }

    if (!empty($search)) {
        $where .= " AND (s.full_name LIKE :srch OR s.student_code LIKE :srch OR s.roll_no LIKE :srch OR u.email LIKE :srch)";
        $params['srch'] = "%{$search}%";
    }

    $sql = "SELECT s.id, s.user_id, s.student_code, s.full_name, s.gender, s.dob, s.class, 
            s.roll_no, s.guardian_name, s.phone, s.address, s.blood_group, s.photo, s.admission_date,
            u.email, u.username, u.status, u.created_at
            FROM students s
            JOIN users u ON s.user_id = u.id
            {$where}
            ORDER BY s.class ASC, CAST(s.roll_no AS INTEGER) ASC, s.full_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();

    api_response([
        'total' => count($students),
        'students' => $students
    ]);
}

// POST: Manage students (Create, Approve, Reject, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($role, ['admin', 'principal', 'vice_principal'])) {
        api_error('Only administrators and school leadership can manage student records.', 403);
    }

    $input = get_json_input();
    $action = $input['action'] ?? '';

    // 1. Approve Pending Registration
    if ($action === 'approve') {
        $user_id = (int)($input['user_id'] ?? 0);
        if ($user_id <= 0) api_error('Valid user_id is required.');

        $stmt = $pdo->prepare("UPDATE users SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE id = :uid AND role = 'student'");
        $stmt->execute(['uid' => $user_id]);

        api_response(['message' => 'Student registration approved successfully. Student can now log in.']);
    }

    // 2. Reject Pending Registration
    if ($action === 'reject') {
        $user_id = (int)($input['user_id'] ?? 0);
        if ($user_id <= 0) api_error('Valid user_id is required.');

        $stmt = $pdo->prepare("UPDATE users SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = :uid AND role = 'student'");
        $stmt->execute(['uid' => $user_id]);

        api_response(['message' => 'Student registration has been rejected.']);
    }

    // 3. Create New Student
    if ($action === 'create') {
        $full_name = trim($input['full_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? 'student123';
        $class = trim($input['class'] ?? 'Class 10');
        $roll_no = trim($input['roll_no'] ?? '');
        $guardian_name = trim($input['guardian_name'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $address = trim($input['address'] ?? '');
        $gender = trim($input['gender'] ?? 'Male');
        $dob = trim($input['dob'] ?? '2010-01-01');
        $blood_group = trim($input['blood_group'] ?? 'O+');

        if (empty($full_name) || empty($email) || empty($class)) {
            api_error('Full name, email, and class are required.');
        }

        // Check if email or username already taken
        $check = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
        $check->execute(['e' => $email]);
        if ($check->fetch()) {
            api_error('An account with this email already exists.');
        }

        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $uStmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (:u, :e, :p, 'student', 'approved')");
            $uStmt->execute([
                'u' => $email,
                'e' => $email,
                'p' => $hash
            ]);
            $user_id = (int)$pdo->lastInsertId();

            $student_code = 'BWIS-STU-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
            if (empty($roll_no)) {
                $maxRoll = $pdo->prepare("SELECT MAX(CAST(roll_no AS INTEGER)) FROM students WHERE class = :cls");
                $maxRoll->execute(['cls' => $class]);
                $roll_no = ((int)$maxRoll->fetchColumn()) + 1;
            }

            $sStmt = $pdo->prepare("INSERT INTO students 
                (user_id, student_code, full_name, gender, dob, class, roll_no, guardian_name, phone, address, blood_group, admission_date)
                VALUES (:uid, :code, :name, :gen, :dob, :cls, :roll, :guard, :ph, :addr, :bg, :adm)");
            $sStmt->execute([
                'uid' => $user_id,
                'code' => $student_code,
                'name' => $full_name,
                'gen' => $gender,
                'dob' => $dob,
                'cls' => $class,
                'roll' => (string)$roll_no,
                'guard' => $guardian_name,
                'ph' => $phone,
                'addr' => $address,
                'bg' => $blood_group,
                'adm' => date('Y-m-d')
            ]);
            $student_id = (int)$pdo->lastInsertId();

            $pdo->commit();
            api_response([
                'message' => "Student {$full_name} ({$student_code}) enrolled successfully!",
                'student_id' => $student_id,
                'user_id' => $user_id,
                'student_code' => $student_code
            ], 201);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            api_error('Failed to create student: ' . $e->getMessage(), 500);
        }
    }

    // 4. Delete Student
    if ($action === 'delete') {
        $user_id = (int)($input['user_id'] ?? 0);
        if ($user_id <= 0) api_error('Valid user_id is required.');

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM users WHERE id = :uid AND role = 'student'")->execute(['uid' => $user_id]);
        $pdo->commit();

        api_response(['message' => 'Student record and associated account removed.']);
    }

    api_error('Invalid action requested.');
}

api_error('Method Not Allowed', 405);
