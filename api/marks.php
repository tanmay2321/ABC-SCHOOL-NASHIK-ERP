<?php
/**
 * REST API: Grades, Marksheets & Report Cards
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];
$profile = $user['profile'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $student_id = ($role === 'student') ? ($profile['id'] ?? 0) : (int)($_GET['student_id'] ?? 0);

    if ($student_id > 0) {
        $stmt = $pdo->prepare("SELECT g.*, t.full_name as teacher_name 
            FROM grades g
            LEFT JOIN teachers t ON g.teacher_id = t.id
            WHERE g.student_id = :sid
            ORDER BY g.exam_name ASC, g.subject ASC");
        $stmt->execute(['sid' => $student_id]);
        $grades = $stmt->fetchAll();

        // Group by Exam Name
        $by_exam = [];
        $total_obtained = 0;
        $total_max = 0;

        foreach ($grades as $g) {
            $exam = $g['exam_name'] ?: 'General Assessment';
            if (!isset($by_exam[$exam])) {
                $by_exam[$exam] = [
                    'exam_name' => $exam,
                    'subjects' => [],
                    'total_obtained' => 0,
                    'total_max' => 0,
                    'percentage' => 0
                ];
            }
            $by_exam[$exam]['subjects'][] = $g;
            $by_exam[$exam]['total_obtained'] += (float)$g['marks_obtained'];
            $by_exam[$exam]['total_max'] += (float)$g['max_marks'];
            $total_obtained += (float)$g['marks_obtained'];
            $total_max += (float)$g['max_marks'];
        }

        foreach ($by_exam as $k => &$e) {
            $e['percentage'] = $e['total_max'] > 0 ? round(($e['total_obtained'] / $e['total_max']) * 100, 1) : 0;
        }

        $overall_pct = $total_max > 0 ? round(($total_obtained / $total_max) * 100, 1) : 0;

        api_response([
            'overall_percentage' => $overall_pct,
            'total_exams' => count($by_exam),
            'exams' => array_values($by_exam)
        ]);

    } else {
        // Teacher / Admin: query by class and exam
        $class = $_GET['class'] ?? ($profile['assigned_class'] ?? 'Class 10');
        $exam = $_GET['exam'] ?? 'Mid-Term Examination 2026';

        $stmt = $pdo->prepare("SELECT s.id as student_id, s.student_code, s.full_name, s.roll_no,
            g.id as grade_id, g.subject, g.marks_obtained, g.max_marks, g.grade
            FROM students s
            LEFT JOIN grades g ON s.id = g.student_id AND g.exam_name = :ex
            WHERE s.class = :cls
            ORDER BY CAST(s.roll_no AS INTEGER) ASC, s.full_name ASC");
        $stmt->execute(['cls' => $class, 'ex' => $exam]);
        $rows = $stmt->fetchAll();

        api_response([
            'class' => $class,
            'exam' => $exam,
            'records' => $rows
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($role === 'student') {
        api_error('Students cannot enter marks.', 403);
    }

    $input = get_json_input();
    $exam_name = trim($input['exam_name'] ?? 'Unit Test 1');
    $marks_array = $input['marks'] ?? []; // [{student_id, subject, marks_obtained, max_marks, remarks}]

    if (empty($marks_array)) {
        api_error('Marks records array is required.');
    }

    try {
        $pdo->beginTransaction();

        $ins = $pdo->prepare("INSERT INTO grades 
            (student_id, subject, exam_name, marks_obtained, max_marks, grade, remarks, teacher_id)
            VALUES (:sid, :sub, :exam, :marks, :maxm, :grd, :rem, :tid)");

        $count = 0;
        foreach ($marks_array as $m) {
            $sid = (int)($m['student_id'] ?? 0);
            $sub = trim($m['subject'] ?? '');
            $marks = (float)($m['marks_obtained'] ?? 0);
            $maxm = (float)($m['max_marks'] ?? 100);
            $remarks = $m['remarks'] ?? '';

            // Compute Grade
            $pct = $maxm > 0 ? ($marks / $maxm) * 100 : 0;
            $grade = 'F';
            if ($pct >= 90) $grade = 'A+';
            elseif ($pct >= 80) $grade = 'A';
            elseif ($pct >= 70) $grade = 'B+';
            elseif ($pct >= 60) $grade = 'B';
            elseif ($pct >= 50) $grade = 'C';
            elseif ($pct >= 35) $grade = 'D';

            if ($sid > 0 && !empty($sub)) {
                // Delete existing entry if any
                $del = $pdo->prepare("DELETE FROM grades WHERE student_id = :sid AND subject = :sub AND exam_name = :exam");
                $del->execute(['sid' => $sid, 'sub' => $sub, 'exam' => $exam_name]);

                $ins->execute([
                    'sid' => $sid,
                    'sub' => $sub,
                    'exam' => $exam_name,
                    'marks' => $marks,
                    'maxm' => $maxm,
                    'grd' => $grade,
                    'rem' => $remarks,
                    'tid' => $user['id']
                ]);
                $count++;
            }
        }

        $pdo->commit();
        api_response([
            'message' => "Successfully recorded grades for {$count} student subject entries.",
            'count' => $count
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        api_error('Failed to submit grades: ' . $e->getMessage(), 500);
    }
}

api_error('Method Not Allowed', 405);
