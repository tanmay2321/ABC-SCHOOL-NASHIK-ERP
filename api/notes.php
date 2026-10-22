<?php
/**
 * REST API: Study Notes & Course Materials
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];
$profile = $user['profile'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $file_path = '';
    $file_name = 'course_notes.pdf';
    $file_size = 1024;
    $file_type = 'pdf';

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
    } else {
        $new_name = 'note_' . time() . '.pdf';
        $dest = $upload_dir . $new_name;
        file_put_contents($dest, "%PDF-1.4 Bellwether International School Study Notes Reference Material");
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

    api_response([
        'message' => "Study notes '{$title}' published to LMS for {$class}!",
        'note_id' => $pdo->lastInsertId()
    ], 201);
}

api_error('Method Not Allowed', 405);
