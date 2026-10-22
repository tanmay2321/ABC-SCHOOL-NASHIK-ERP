<?php
/**
 * REST API: Academic Year Shift & Student Promotion
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];

function get_suggested_next_class($class) {
    $class = trim($class);
    if (preg_match('/^Class\s+(\d+)(.*)$/i', $class, $matches)) {
        $num = (int)$matches[1];
        $suffix = $matches[2];
        if ($num >= 12) return 'Alumni / Graduated';
        if ($num == 10) return 'Class 11' . $suffix;
        return 'Class ' . ($num + 1) . $suffix;
    }
    if (preg_match('/^Grade\s+(\d+)(.*)$/i', $class, $matches)) {
        $num = (int)$matches[1];
        $suffix = $matches[2];
        if ($num >= 12) return 'Alumni / Graduated';
        return 'Grade ' . ($num + 1) . $suffix;
    }
    return $class . ' (Promoted)';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $current_academic_year = get_setting($pdo, 'current_academic_year', '2026-2027');

    // Get class statistics
    $stats = $pdo->query("SELECT s.class, COUNT(s.id) as student_count 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE u.status = 'approved' AND s.class IS NOT NULL AND s.class != ''
        GROUP BY s.class 
        ORDER BY s.class ASC")->fetchAll();

    $class_info = [];
    foreach ($stats as $st) {
        $class_info[] = [
            'class' => $st['class'],
            'student_count' => (int)$st['student_count'],
            'suggested_next_class' => get_suggested_next_class($st['class'])
        ];
    }

    // Recent promotion log
    $history = $pdo->query("SELECT h.*, s.full_name as student_name, s.student_code 
        FROM promotion_history h
        LEFT JOIN students s ON h.student_id = s.id
        ORDER BY h.id DESC LIMIT 20")->fetchAll();

    api_response([
        'current_academic_year' => $current_academic_year,
        'classes' => $class_info,
        'recent_promotions' => $history
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($role, ['admin', 'principal'])) {
        api_error('Only administrators and the Principal can shift academic years or promote students.', 403);
    }

    $input = get_json_input();
    $action = $input['action'] ?? '';

    // 1. Shift Academic Year Calendar Setting
    if ($action === 'update_year') {
        $new_year = trim($input['academic_year'] ?? '');
        if (empty($new_year)) api_error('Academic year (e.g. 2027-2028) is required.');

        set_setting($pdo, 'current_academic_year', $new_year);
        api_response([
            'message' => "Academic year successfully transitioned to {$new_year}. All LMS dashboards, timetables, and reports are aligned to {$new_year}.",
            'current_academic_year' => $new_year
        ]);
    }

    // 2. Promote Students
    if ($action === 'promote_students') {
        $src_class = trim($input['source_class'] ?? '');
        $tgt_class = trim($input['target_class'] ?? '');
        $target_year = trim($input['target_year'] ?? get_setting($pdo, 'current_academic_year', '2026-2027'));
        $student_ids = $input['student_ids'] ?? [];

        if (empty($src_class) || empty($tgt_class)) {
            api_error('Source class and Target class are required.');
        }

        // If no explicit student IDs given, promote all approved students in source class
        if (empty($student_ids)) {
            $getIds = $pdo->prepare("SELECT s.id FROM students s JOIN users u ON s.user_id = u.id WHERE s.class = :cls AND u.status = 'approved'");
            $getIds->execute(['cls' => $src_class]);
            $student_ids = $getIds->fetchAll(PDO::FETCH_COLUMN);
        }

        if (empty($student_ids)) {
            api_error("No approved students found in {$src_class} to promote.");
        }

        try {
            $pdo->beginTransaction();

            $ins_hist = $pdo->prepare("INSERT INTO promotion_history (student_id, from_class, to_class, academic_year, promoted_by) VALUES (?, ?, ?, ?, ?)");
            $upd_student = $pdo->prepare("UPDATE students SET class = ? WHERE id = ?");

            $count = 0;
            foreach ($student_ids as $sid) {
                $sid = (int)$sid;
                if ($sid > 0) {
                    $ins_hist->execute([$sid, $src_class, $tgt_class, $target_year, $user['id']]);
                    $upd_student->execute([$tgt_class, $sid]);
                    $count++;
                }
            }

            $pdo->commit();
            api_response([
                'message' => "Successfully promoted {$count} student(s) from {$src_class} to {$tgt_class} for Academic Year {$target_year}!",
                'promoted_count' => $count,
                'source_class' => $src_class,
                'target_class' => $tgt_class,
                'academic_year' => $target_year
            ]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            api_error('Failed to promote students: ' . $e->getMessage(), 500);
        }
    }

    api_error('Invalid action requested.');
}

api_error('Method Not Allowed', 405);
