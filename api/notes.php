<?php
/**
 * REST API: Study Notes & Course Materials (with Document Upload & Notifications)
 * Bellwether International School Nandurbar ERP (BWIS ERP)
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];
$profile = $user['profile'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $class = $_GET['class'] ?? ($profile['class'] ?? '');
    $subject = $_GET['subject'] ?? '';

    $where = "WHERE 1=1";
    $params = [];

    if (!empty($class)) {
        $base_class = trim(explode('-', $class)[0]);
        $where .= " AND (n.class = :cls OR n.class = :base_cls OR n.class = '' OR :cls LIKE (n.class || '-%') OR n.class LIKE (:base_cls || '-%'))";
        $params['cls'] = $class;
        $params['base_cls'] = $base_class;
    }
    if (!empty($subject)) {
        $where .= " AND (LOWER(TRIM(n.subject)) = LOWER(TRIM(:subj)) OR n.subject LIKE :subj_like)";
        $params['subj'] = $subject;
        $params['subj_like'] = '%' . trim($subject) . '%';
    }

    $stmt = $pdo->prepare("SELECT n.*, t.full_name as teacher_name, t.photo as teacher_photo 
        FROM study_notes n
        LEFT JOIN teachers t ON n.teacher_id = t.id
        {$where}
        ORDER BY n.id DESC");
    $stmt->execute($params);
    $notes = $stmt->fetchAll();

    api_response([
        'total' => count($notes),
        'notes' => $notes
    ]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!in_array($role, ['teacher', 'admin', 'principal', 'vice_principal'])) {
        api_error('Only faculty members and administrators can upload study notes.', 403);
    }

    $input = get_json_input();
    $title = trim($input['title'] ?? '');
    $class = trim($input['class'] ?? '');
    $subject = trim($input['subject'] ?? '');
    $description = trim($input['description'] ?? '');
    $teacher_id = $profile['id'] ?? 1;

    if (empty($title) || empty($class) || empty($subject)) {
        api_error('Title, Class, and Subject are required fields.');
    }

    $upload_dir = __DIR__ . '/../uploads/notes/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    $file_path = trim($input['file_path'] ?? '');
    $file_name = trim($input['file_name'] ?? ($title . '_notes.pdf'));
    $file_size = (int)($input['file_size'] ?? 1024);
    $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION) ?: 'pdf');

    // Handle multipart upload if present
    if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $orig_name = basename($_FILES['file']['name']);
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $file_name = $orig_name;
        $file_size = $_FILES['file']['size'];
        $file_type = $ext;

        $new_name = 'note_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $dest = $upload_dir . $new_name;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            $file_path = '/uploads/notes/' . $new_name;
        }
    } elseif (!empty($input['file_base64'])) {
        $new_name = 'note_' . time() . '_' . rand(1000, 9999) . '.' . $file_type;
        $dest = $upload_dir . $new_name;
        if (file_put_contents($dest, base64_decode($input['file_base64']))) {
            $file_path = '/uploads/notes/' . $new_name;
            $file_size = filesize($dest);
        }
    }

    if (empty($file_path)) {
        $new_name = 'note_' . time() . '.pdf';
        $dest = $upload_dir . $new_name;
        file_put_contents($dest, "%PDF-1.4 Bellwether International School Study Material\nTitle: " . $title . "\nSubject: " . $subject);
        $file_path = '/uploads/notes/' . $new_name;
    }

    $ins = $pdo->prepare("INSERT INTO study_notes 
        (teacher_id, class, subject, title, description, file_path, file_name, file_size, file_type, academic_year)
        VALUES (:tid, :cls, :sub, :t, :d, :fp, :fn, :fs, :ft, :ay)");
    
    $ins->execute([
        'tid' => $teacher_id,
        'cls' => $class,
        'sub' => $subject,
        't' => $title,
        'd' => $description,
        'fp' => $file_path,
        'fn' => $file_name,
        'fs' => $file_size,
        'ft' => $file_type,
        'ay' => get_setting($pdo, 'current_academic_year', '2026-2027')
    ]);

    $note_id = $pdo->lastInsertId();

    // Notify all students in this class
    try {
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, role, class, title, message, type, reference_id)
            SELECT u.id, 'student', s.class, :title, :msg, 'note_new', :ref
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.class = :cls OR s.class LIKE (:cls || '-%') OR :cls LIKE (s.class || '-%')");
        $notifStmt->execute([
            'title' => "New Study Material: {$title}",
            'msg' => "Subject: {$subject} • Teacher has uploaded reference notes.",
            'ref' => $note_id,
            'cls' => $class
        ]);
    } catch (Exception $e) {}

    api_response([
        'message' => "Study notes '{$title}' published to LMS with notifications sent to students!",
        'note_id' => $note_id,
        'file_path' => $file_path
    ], 201);
}

api_error('Method Not Allowed', 405);
