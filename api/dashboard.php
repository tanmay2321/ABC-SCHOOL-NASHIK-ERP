<?php
/**
 * REST API: Role Dashboard Data
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];
$profile = $user['profile'];

$data = [
    'role' => $role,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'],
        'full_name' => $profile['full_name'] ?? $user['username']
    ]
];

$day_of_week = date('l'); // Monday, Tuesday...

if ($role === 'student') {
    $student_id = $profile['id'] ?? 0;
    $class = $profile['class'] ?? '';

    // Attendance stats
    $attStmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
        FROM attendance WHERE student_id = :sid");
    $attStmt->execute(['sid' => $student_id]);
    $att = $attStmt->fetch() ?: ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0];
    
    $total_days = (int)($att['total'] ?? 0);
    $present_days = (int)($att['present'] ?? 0);
    $percentage = $total_days > 0 ? round(($present_days / $total_days) * 100, 1) : 100.0;

    // Fees summary
    $feeStmt = $pdo->prepare("SELECT 
        SUM(total_amount) as total_billed,
        SUM(CASE WHEN status = 'Unpaid' THEN total_amount ELSE 0 END) as unpaid,
        COUNT(CASE WHEN status = 'Unpaid' THEN 1 END) as pending_invoices
        FROM fee_invoices WHERE student_id = :sid");
    $feeStmt->execute(['sid' => $student_id]);
    $fee = $feeStmt->fetch() ?: ['total_billed' => 0, 'unpaid' => 0, 'pending_invoices' => 0];

    // Today's schedule
    $ttStmt = $pdo->prepare("SELECT * FROM timetables WHERE class = :cls AND day_of_week = :day ORDER BY period_number ASC");
    $ttStmt->execute(['cls' => $class, 'day' => $day_of_week]);
    $schedule = $ttStmt->fetchAll() ?: [];

    // Pending Homework
    $asgnStmt = $pdo->prepare("SELECT a.*, 
        (SELECT s.status FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_id = :sid LIMIT 1) as submission_status
        FROM assignments a 
        WHERE a.class = :cls 
        ORDER BY a.due_date ASC LIMIT 5");
    $asgnStmt->execute(['cls' => $class, 'sid' => $student_id]);
    $assignments = $asgnStmt->fetchAll() ?: [];

    // Study notes count
    $notesStmt = $pdo->prepare("SELECT COUNT(*) FROM study_notes WHERE class = :cls");
    $notesStmt->execute(['cls' => $class]);
    $notes_count = (int)$notesStmt->fetchColumn();

    $data['student_metrics'] = [
        'attendance_percentage' => $percentage,
        'attendance_present' => $present_days,
        'attendance_total' => $total_days,
        'fee_due' => (float)($fee['unpaid'] ?? 0),
        'pending_invoices' => (int)($fee['pending_invoices'] ?? 0),
        'notes_count' => $notes_count,
        'assignments_count' => count($assignments),
        'class' => $class,
        'roll_no' => $profile['roll_no'] ?? '',
        'student_code' => $profile['student_code'] ?? ''
    ];
    $data['today_schedule'] = $schedule;
    $data['assignments'] = $assignments;

} elseif ($role === 'teacher') {
    $teacher_id = $profile['id'] ?? 0;
    $assigned_class = $profile['assigned_class'] ?? null;

    $class_students_count = 0;
    $attendance_marked_today = false;

    if ($assigned_class) {
        $csStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class = :cls");
        $csStmt->execute(['cls' => $assigned_class]);
        $class_students_count = (int)$csStmt->fetchColumn();

        $attCheck = $pdo->prepare("SELECT COUNT(*) FROM attendance a 
            JOIN students s ON a.student_id = s.id 
            WHERE s.class = :cls AND a.date = :dt");
        $attCheck->execute(['cls' => $assigned_class, 'dt' => date('Y-m-d')]);
        $attendance_marked_today = ((int)$attCheck->fetchColumn()) > 0;
    }

    // Allocated subjects count
    $subjStmt = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE teacher_id = :tid");
    $subjStmt->execute(['tid' => $teacher_id]);
    $allocated_subjects_count = (int)$subjStmt->fetchColumn();

    // Today's schedule
    $teacher_name = $profile['full_name'] ?? $user['username'];
    $ttStmt = $pdo->prepare("SELECT * FROM timetables WHERE teacher_name LIKE :tname AND day_of_week = :day ORDER BY period_number ASC");
    $ttStmt->execute(['tname' => '%' . $teacher_name . '%', 'day' => $day_of_week]);
    $schedule = $ttStmt->fetchAll() ?: [];

    // Pending assignment submissions to grade
    $subStmt = $pdo->prepare("SELECT COUNT(*) FROM assignment_submissions s 
        JOIN assignments a ON s.assignment_id = a.id 
        WHERE a.teacher_id = :tid AND s.status = 'Submitted'");
    $subStmt->execute(['tid' => $teacher_id]);
    $pending_grading = (int)$subStmt->fetchColumn();

    $data['teacher_metrics'] = [
        'assigned_class' => $assigned_class,
        'class_students_count' => $class_students_count,
        'attendance_marked_today' => $attendance_marked_today,
        'allocated_subjects_count' => $allocated_subjects_count,
        'pending_grading' => $pending_grading,
        'department' => $profile['department'] ?? 'Faculty'
    ];
    $data['today_schedule'] = $schedule;

} else {
    // Admin / Principal / Vice-Principal
    $total_students = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $total_teachers = (int)$pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    $pending_students = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'pending'")->fetchColumn();

    $fees_total = (float)$pdo->query("SELECT SUM(total_amount) FROM fee_invoices")->fetchColumn();
    $fees_collected = (float)$pdo->query("SELECT SUM(amount_paid) FROM fee_payments")->fetchColumn();
    $fees_unpaid = max(0, $fees_total - $fees_collected);

    // Today's total attendance
    $today_att = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present
        FROM attendance WHERE date = date('now')")->fetch();
    $att_rate = ($today_att && $today_att['total'] > 0) ? round(($today_att['present'] / $today_att['total']) * 100, 1) : 95.0;

    $data['admin_metrics'] = [
        'total_students' => $total_students,
        'total_teachers' => $total_teachers,
        'pending_admissions' => $pending_students,
        'fees_total' => $fees_total,
        'fees_collected' => $fees_collected,
        'fees_unpaid' => $fees_unpaid,
        'today_attendance_rate' => $att_rate
    ];
}

// Recent Notices for everyone
$notices = $pdo->query("SELECT * FROM notices ORDER BY id DESC LIMIT 5")->fetchAll();
$data['recent_notices'] = $notices;

api_response($data);
