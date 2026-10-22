<?php
/**
 * REST API: Homework & Assignments (with Document & Solution Upload + Notifications)
 * Bellwether International School Nandurbar ERP (BWIS ERP)
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];
$profile = $user['profile'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
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

    // Also support fetching submissions for a specific assignment (for teacher grading)
    $submissions = [];
    if (!empty($_GET['assignment_id']) && in_array($role, ['teacher', 'admin', 'principal', 'vice_principal'])) {
        $aid = (int)$_GET['assignment_id'];
        $subStmt = $pdo->prepare("SELECT sub.*, s.full_name as student_name, s.roll_no, s.class as student_class, s.photo as student_photo
            FROM assignment_submissions sub
            JOIN students s ON sub.student_id = s.id
            WHERE sub.assignment_id = :aid
            ORDER BY sub.submitted_at DESC");
        $subStmt->execute(['aid' => $aid]);
        $submissions = $subStmt->fetchAll();
    }

    api_response([
        'total' => count($assignments),
        'assignments' => $assignments,
        'submissions' => $submissions
    ]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $input = get_json_input();
    $action = $input['action'] ?? ($role === 'student' ? 'submit' : 'create');

    // 1. Student submits homework
    if ($action === 'submit' && $role === 'student') {
        $assignment_id = (int)($input['assignment_id'] ?? 0);
        $remarks = trim($input['remarks'] ?? 'Submitted via BWIS ERP App');
        $student_id = $profile['id'] ?? 0;
        $file_path = trim($input['file_path'] ?? '');

        if ($assignment_id <= 0 || $student_id <= 0) {
            api_error('Valid assignment_id is required.');
        }

        if (empty($file_path)) {
            $file_path = '/uploads/submissions/sub_' . time() . '_' . $student_id . '.pdf';
        }

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

        api_response(['message' => 'Assignment submitted successfully to teacher!']);
    }

    // 2. Teacher/Admin creates assignment (with document & optional solution)
    if ($action === 'create' && in_array($role, ['teacher', 'admin', 'principal', 'vice_principal'])) {
        $title = trim($input['title'] ?? '');
        $class = trim($input['class'] ?? '');
        $subject = trim($input['subject'] ?? '');
        $description = trim($input['description'] ?? '');
        $due_date = trim($input['due_date'] ?? date('Y-m-d', strtotime('+7 days')));
        $max_marks = (float)($input['max_marks'] ?? 100);
        $teacher_id = $profile['id'] ?? 1;
        $attachment_path = trim($input['attachment_path'] ?? '');
        $solution_path = trim($input['solution_path'] ?? '');
        $solution_notes = trim($input['solution_notes'] ?? '');
        $solution_released = !empty($input['solution_released']) ? 1 : 0;

        if (empty($title) || empty($class) || empty($subject)) {
            api_error('Title, Class, and Subject are required fields.');
        }

        $ins = $pdo->prepare("INSERT INTO assignments 
            (teacher_id, class, subject, title, description, due_date, max_marks, attachment_path, solution_path, solution_notes, solution_released) 
            VALUES (:tid, :cls, :sub, :t, :d, :due, :mm, :att, :sol, :solnotes, :solrel)");
        $ins->execute([
            'tid' => $teacher_id,
            'cls' => $class,
            'sub' => $subject,
            't' => $title,
            'd' => $description,
            'due' => $due_date,
            'mm' => $max_marks,
            'att' => $attachment_path ?: null,
            'sol' => $solution_path ?: null,
            'solnotes' => $solution_notes ?: null,
            'solrel' => $solution_released
        ]);

        $new_aid = $pdo->lastInsertId();

        // Push in-app notification to all students in this class
        try {
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, role, class, title, message, type, reference_id)
                SELECT u.id, 'student', s.class, :title, :msg, 'assignment_new', :ref
                FROM students s
                JOIN users u ON s.user_id = u.id
                WHERE s.class = :cls OR s.class LIKE (:cls || '-%') OR :cls LIKE (s.class || '-%')");
            $notifStmt->execute([
                'title' => "New Assignment: {$title}",
                'msg' => "Subject: {$subject} • Due Date: {$due_date} • Max Marks: {$max_marks}",
                'ref' => $new_aid,
                'cls' => $class
            ]);
        } catch (Exception $e) {
            // Ignore notif error
        }

        api_response([
            'message' => "Assignment '{$title}' published for {$class} with notifications sent to students!",
            'assignment_id' => $new_aid
        ], 201);
    }

    // 3. Teacher/Admin uploads/updates official solution
    if ($action === 'upload_solution' && in_array($role, ['teacher', 'admin', 'principal', 'vice_principal'])) {
        $assignment_id = (int)($input['assignment_id'] ?? 0);
        $solution_notes = trim($input['solution_notes'] ?? '');
        $solution_path = trim($input['solution_path'] ?? '');
        $solution_released = isset($input['solution_released']) ? (int)$input['solution_released'] : 1;

        if ($assignment_id <= 0) {
            api_error('Valid assignment_id is required.');
        }

        $upd = $pdo->prepare("UPDATE assignments SET 
            solution_path = COALESCE(:sp, solution_path),
            solution_notes = :sn,
            solution_released = :sr
            WHERE id = :id");
        $upd->execute([
            'sp' => $solution_path ?: null,
            'sn' => $solution_notes,
            'sr' => $solution_released,
            'id' => $assignment_id
        ]);

        // Notify class about solution release
        if ($solution_released) {
            $aInfo = $pdo->prepare("SELECT title, class, subject FROM assignments WHERE id = :id");
            $aInfo->execute(['id' => $assignment_id]);
            $aRow = $aInfo->fetch();
            if ($aRow) {
                try {
                    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, role, class, title, message, type, reference_id)
                        SELECT u.id, 'student', s.class, :title, :msg, 'assignment_solution', :ref
                        FROM students s
                        JOIN users u ON s.user_id = u.id
                        WHERE s.class = :cls OR s.class LIKE (:cls || '-%') OR :cls LIKE (s.class || '-%')");
                    $notifStmt->execute([
                        'title' => "Solution Released: {$aRow['title']}",
                        'msg' => "Official answer key and solutions for {$aRow['subject']} are now available to view.",
                        'ref' => $assignment_id,
                        'cls' => $aRow['class']
                    ]);
                } catch (Exception $e) {}
            }
        }

        api_response(['message' => 'Assignment solution uploaded & released successfully!']);
    }

    // 4. Teacher grades student submission
    if ($action === 'grade' && in_array($role, ['teacher', 'admin', 'principal', 'vice_principal'])) {
        $submission_id = (int)($input['submission_id'] ?? 0);
        $marks = (float)($input['marks_obtained'] ?? 0);
        $feedback = trim($input['teacher_feedback'] ?? 'Checked and verified');

        if ($submission_id <= 0) {
            api_error('Valid submission_id is required.');
        }

        $upd = $pdo->prepare("UPDATE assignment_submissions SET 
            marks_obtained = :m, 
            teacher_feedback = :fb, 
            status = 'Graded' 
            WHERE id = :id");
        $upd->execute(['m' => $marks, 'fb' => $feedback, 'id' => $submission_id]);

        // Notify the specific student
        $sQuery = $pdo->prepare("SELECT sub.student_id, a.title, a.max_marks, u.id as user_id 
            FROM assignment_submissions sub
            JOIN assignments a ON sub.assignment_id = a.id
            JOIN students stu ON sub.student_id = stu.id
            JOIN users u ON stu.user_id = u.id
            WHERE sub.id = :id");
        $sQuery->execute(['id' => $submission_id]);
        $sRow = $sQuery->fetch();

        if ($sRow) {
            try {
                $nStmt = $pdo->prepare("INSERT INTO notifications (user_id, role, title, message, type, reference_id)
                    VALUES (:uid, 'student', :t, :m, 'assignment_checked', :ref)");
                $nStmt->execute([
                    'uid' => $sRow['user_id'],
                    't' => "Assignment Checked: {$sRow['title']}",
                    'm' => "You scored {$marks}/{$sRow['max_marks']} marks. Teacher Feedback: {$feedback}",
                    'ref' => $submission_id
                ]);
            } catch (Exception $e) {}
        }

        api_response(['message' => 'Submission graded and notification sent to student!']);
    }

    api_error('Invalid action requested.');
}

api_error('Method Not Allowed', 405);
