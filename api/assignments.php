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
        $where = "WHERE a.class = :cls";
        $params = ['cls' => $class];

        if (!empty($subject)) {
            $where .= " AND a.subject = :subj";
            $params['subj'] = $subject;
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
        // Admin
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

        if ($assignment_id <= 0) {
            api_error('Valid assignment ID required.');
        }

        $upload_dir = __DIR__ . '/../uploads/submissions/';
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

        $file_name = 'sub_' . $student_id . '_' . $assignment_id . '_' . time() . '.pdf';
        $dest = $upload_dir . $file_name;
        $file_path = '/uploads/submissions/' . $file_name;

        if (!empty($_FILES['file']['name'])) {
            move_uploaded_file($_FILES['file']['tmp_name'], $dest);
        } elseif (!empty($input['file_base64'])) {
            file_put_contents($dest, base64_decode($input['file_base64']));
        } else {
            file_put_contents($dest, "%PDF-1.4 Assignment Solution Submission by Student #{$student_id}");
        }

        // Delete existing submission if any
        $del = $pdo->prepare("DELETE FROM assignment_submissions WHERE assignment_id = :aid AND student_id = :sid");
        $del->execute(['aid' => $assignment_id, 'sid' => $student_id]);

        $ins = $pdo->prepare("INSERT INTO assignment_submissions 
            (assignment_id, student_id, file_path, student_remarks, status) 
            VALUES (:aid, :sid, :fpath, :rem, 'Submitted')");
        $ins->execute([
            'aid' => $assignment_id,
            'sid' => $student_id,
            'fpath' => $file_path,
            'rem' => $remarks
        ]);

        api_response([
            'message' => 'Assignment submitted successfully!',
            'submission_status' => 'Submitted',
            'file_path' => $file_path
        ]);

    } elseif ($action === 'create' && in_array($role, ['teacher', 'admin', 'principal', 'vice_principal'])) {
        $class = trim($input['class'] ?? '');
        $subject = trim($input['subject'] ?? '');
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $due_date = trim($input['due_date'] ?? date('Y-m-d', strtotime('+7 days')));
        $max_marks = (float)($input['max_marks'] ?? 100);
        $teacher_id = $profile['id'] ?? 1;

        if (empty($class) || empty($subject) || empty($title)) {
            api_error('Class, Subject, and Title are required.');
        }

        $ins = $pdo->prepare("INSERT INTO assignments 
            (teacher_id, class, subject, title, description, due_date, max_marks) 
            VALUES (:tid, :cls, :sub, :title, :desc, :due, :mm)");
        $ins->execute([
            'tid' => $teacher_id,
            'cls' => $class,
            'sub' => $subject,
            'title' => $title,
            'desc' => $description,
            'due' => $due_date,
            'mm' => $max_marks
        ]);

        api_response([
            'message' => 'Assignment published successfully!',
            'assignment_id' => $pdo->lastInsertId()
        ], 201);
    } else {
        api_error('Invalid action or unauthorized role.', 403);
    }
}

api_error('Method Not Allowed', 405);
