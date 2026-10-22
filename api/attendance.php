<?php
/**
 * REST API: Attendance Management
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];
$profile = $user['profile'];

// Handle GET: Fetch attendance
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($role === 'student') {
        $student_id = $profile['id'] ?? 0;
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');

        $stmt = $pdo->prepare("SELECT * FROM attendance 
            WHERE student_id = :sid AND strftime('%m', date) = :m AND strftime('%Y', date) = :y 
            ORDER BY date DESC");
        $stmt->execute(['sid' => $student_id, 'm' => str_pad($month, 2, '0', STR_PAD_LEFT), 'y' => $year]);
        $records = $stmt->fetchAll();

        // Overall stats
        $allStmt = $pdo->prepare("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
            FROM attendance WHERE student_id = :sid");
        $allStmt->execute(['sid' => $student_id]);
        $stats = $allStmt->fetch() ?: ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0];

        $pct = ($stats['total'] > 0) ? round(($stats['present'] / $stats['total']) * 100, 1) : 100.0;

        api_response([
            'summary' => [
                'total_days' => (int)$stats['total'],
                'present_days' => (int)$stats['present'],
                'absent_days' => (int)$stats['absent'],
                'late_days' => (int)$stats['late'],
                'percentage' => $pct
            ],
            'records' => $records
        ]);

    } else {
        // Teacher / Admin: Fetch class attendance roster
        $class = $_GET['class'] ?? ($profile['assigned_class'] ?? 'Class 10');
        $date = $_GET['date'] ?? date('Y-m-d');

        // Fetch all approved students in this class
        $sStmt = $pdo->prepare("SELECT s.id, s.student_code, s.full_name, s.roll_no, s.gender, s.photo,
            a.status as attendance_status, a.remarks as attendance_remarks
            FROM students s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN attendance a ON s.id = a.student_id AND a.date = :dt
            WHERE s.class = :cls AND u.status = 'approved'
            ORDER BY CAST(s.roll_no AS INTEGER) ASC, s.full_name ASC");
        $sStmt->execute(['cls' => $class, 'dt' => $date]);
        $roster = $sStmt->fetchAll();

        $marked = false;
        $present_count = 0;
        $absent_count = 0;

        foreach ($roster as &$stu) {
            if ($stu['attendance_status'] !== null) {
                $marked = true;
                if ($stu['attendance_status'] === 'Present') $present_count++;
                else $absent_count++;
            } else {
                $stu['attendance_status'] = 'Present'; // default to Present for easy 1-click marking
            }
        }

        api_response([
            'class' => $class,
            'date' => $date,
            'is_marked' => $marked,
            'summary' => [
                'total_students' => count($roster),
                'present' => $present_count,
                'absent' => $absent_count
            ],
            'students' => $roster
        ]);
    }
}

// Handle POST: Submit attendance (Teachers & Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($role === 'student') {
        api_error('Students cannot submit attendance records.', 403);
    }

    $input = get_json_input();
    $class = $input['class'] ?? ($profile['assigned_class'] ?? '');
    $date = $input['date'] ?? date('Y-m-d');
    $records = $input['attendance'] ?? [];

    if (empty($class) || empty($records)) {
        api_error('Class and attendance records array are required.');
    }

    try {
        $pdo->beginTransaction();

        $delStmt = $pdo->prepare("DELETE FROM attendance WHERE date = :dt AND student_id IN (SELECT id FROM students WHERE class = :cls)");
        $delStmt->execute(['dt' => $date, 'cls' => $class]);

        $insStmt = $pdo->prepare("INSERT INTO attendance (student_id, date, status, remarks, marked_by) 
            VALUES (:sid, :dt, :st, :rem, :mby)");

        foreach ($records as $item) {
            $sid = (int)($item['student_id'] ?? $item['id'] ?? 0);
            $status = in_array($item['status'] ?? '', ['Present', 'Absent', 'Late']) ? $item['status'] : 'Present';
            $remarks = $item['remarks'] ?? '';

            if ($sid > 0) {
                $insStmt->execute([
                    'sid' => $sid,
                    'dt' => $date,
                    'st' => $status,
                    'rem' => $remarks,
                    'mby' => $user['id']
                ]);
            }
        }

        $pdo->commit();
        api_response([
            'message' => "Attendance for {$class} on {$date} recorded successfully.",
            'records_updated' => count($records)
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        api_error('Failed to save attendance: ' . $e->getMessage(), 500);
    }
}

api_error('Method Not Allowed', 405);
