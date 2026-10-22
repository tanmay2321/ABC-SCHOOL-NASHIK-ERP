<?php
/**
 * REST API: Homework & Assignments
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];
$profile = $user['profile'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $class = $_GET['class'] ?? ($profile['class'] ?? '');
    $subject = $_GET['subject'] ?? '';

    if ($role === 'student') {
        $student_id = $profile['id'] ?? 0;
        $base_class = trim(explode('-', $class)[0]);

        $where = "WHERE (a.class = :cls OR a.class = :base_cls OR a.class = '' OR :cls LIKE (a.class || '-%') OR a.class LIKE (:base_cls || '-%'))";
        $params = ['cls' => $class, 'base_cls' => $base_class];

        if (!empty($subject)) {
            $where .= " AND (LOWER(TRIM(a.subject)) = LOWER(TRIM(:subj)) OR a.subject LIKE :subj_like)";
            $params['subj'] = $subject;
            $params['subj_like'] = '%' . trim($subject) . '%';
        }

        $stmt = $pdo->prepare("SELECT a.*, 
            t.full_name as teacher_name, t.photo as teacher_photo,
            sub.id as submission_id, sub.file_path as submission_file, 
            sub.submitted_at, sub.marks_obtained, sub.teacher_feedback, 
            COALESCE(sub.status, 'Pending') as submission_status
            FROM assignments a
            LEFT JOIN teachers t ON a.teacher_id = t.id
            LEFT JOIN assignment_submissions sub ON a.id = sub.assignment_id AND sub.student_id = :sid
            {$where}
            ORDER BY a.due_date ASC");
        $params['sid'] = $student_id;
        $stmt->execute($params);
        $assignments = $stmt->fetchAll();

    } elseif ($role === 'teacher') {
        $teacher_id = $profile['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT a.*, 
            (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) as total_submissions,
            (SELECT COUNT(*) FROM students stu WHERE stu.class = a.class) as total_students
            FROM assignments a
            WHERE a.teacher_id = :tid
            ORDER BY a.due_date DESC");
        $stmt->execute(['tid' => $teacher_id]);
        $assignments = $stmt->fetchAll();

    } else {
        // Admin / Principal / Vice-Principal
        $stmt = $pdo->query("SELECT a.*, t.full_name as teacher_name FROM assignments a LEFT JOIN teachers t ON a.teacher_id = t.id ORDER BY a.id DESC LIMIT 50");
        $assignments = $stmt->fetchAll();
    }

    api_response([
        'total' => count($assignments),
        'assignments' => $assignments
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = get_json_input();
    $action = $input['action'] ?? ($role === 'student' ? 'submit' : 'create');

    if ($action === 'submit' && $role === 'student') {
        $assignment_id = (int)($input['assignment_id'] ?? 0);
        $remarks = trim($input['remarks'] ?? 'Submitted via Mobile App');
        $student_id = $profile['id'] ?? 0;

        if ($assignment_id <= 0 || $student_id <= 0) {
            api_error('Valid assignment_id is required.');
        }

        $file_path = '/uploads/submissions/sub_' . time() . '_' . $student_id . '.pdf';
        
        $chk = $pdo->prepare("SELECT id FROM assignment_submissions WHERE assignment_id = :aid AND student_id = :sid");
        $chk->execute(['aid' => $assignment_id, 'sid' => $student_id]);
        $existing = $chk->fetch();

        if ($existing) {
            $upd = $pdo->prepare("UPDATE assignment_submissions SET file_path = :fp, student_remarks = :rem, status = 'Submitted', submitted_at = CURRENT_TIMESTAMP WHERE id = :id");
            $upd->execute(['fp' => $file_path, 'rem' => $remarks, 'id' => $existing['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, file_path, student_remarks, status) VALUES (:aid, :sid, :fp, :rem, 'Submitted')");
            $ins->execute(['aid' => $assignment_id, 'sid' => $student_id, 'fp' => $file_path, 'rem' => $remarks]);
        }

        api_response(['message' => 'Homework assignment submitted successfully to faculty!']);
    }

    if ($action === 'create' && in_array($role, ['teacher', 'admin', 'principal', 'vice_principal'])) {
        $title = trim($input['title'] ?? '');
        $class = trim($input['class'] ?? '');
        $subject = trim($input['subject'] ?? '');
        $description = trim($input['description'] ?? '');
        $due_date = trim($input['due_date'] ?? date('Y-m-d', strtotime('+7 days')));
        $max_marks = (float)($input['max_marks'] ?? 100);
        $teacher_id = $profile['id'] ?? 1;

        if (empty($title) || empty($class) || empty($subject)) {
            api_error('Title, Class, and Subject are required fields.');
        }

        $ins = $pdo->prepare("INSERT INTO assignments (teacher_id, class, subject, title, description, due_date, max_marks) VALUES (:tid, :cls, :sub, :t, :d, :due, :mm)");
        $ins->execute([
            'tid' => $teacher_id,
            'cls' => $class,
            'sub' => $subject,
            't' => $title,
            'd' => $description,
            'due' => $due_date,
            'mm' => $max_marks
        ]);

        api_response([
            'message' => "Assignment {$title} created successfully for {$class}!",
            'assignment_id' => $pdo->lastInsertId()
        ], 201);
    }

    api_error('Invalid action requested.');
}

api_error('Method Not Allowed', 405);
