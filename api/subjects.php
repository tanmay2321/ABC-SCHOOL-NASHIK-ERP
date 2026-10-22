<?php
/**
 * REST API: Subjects & LMS Curriculum
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];
$profile = $user['profile'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $class = $_GET['class'] ?? ($profile['class'] ?? 'Class 10-A');
    $base_class = trim(explode('-', $class)[0]);

    if ($role === 'teacher') {
        $teacher_id = $profile['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT s.*, 
            (SELECT COUNT(*) FROM study_notes n WHERE (n.class = s.class OR n.class = :base_cls) AND (LOWER(TRIM(n.subject)) = LOWER(TRIM(s.name)))) as notes_count,
            (SELECT COUNT(*) FROM assignments a WHERE (a.class = s.class OR a.class = :base_cls) AND (LOWER(TRIM(a.subject)) = LOWER(TRIM(s.name)))) as assignments_count
            FROM subjects s
            WHERE s.teacher_id = :tid OR s.class = :cls OR s.class = :base_cls OR :cls LIKE (s.class || '-%')
            ORDER BY s.class ASC, s.name ASC");
        $stmt->execute([
            'tid' => $teacher_id, 
            'cls' => $profile['assigned_class'] ?? $class,
            'base_cls' => $base_class
        ]);
        $subjects = $stmt->fetchAll();

    } else {
        // Student / Admin
        $stmt = $pdo->prepare("SELECT s.*, 
            t.full_name as teacher_name, t.photo as teacher_photo, t.department as teacher_department, 
            t.designation as teacher_designation, t.qualification as teacher_qualification, t.phone as teacher_phone,
            (SELECT COUNT(*) FROM study_notes n WHERE (n.class = s.class OR n.class = :base_cls) AND (LOWER(TRIM(n.subject)) = LOWER(TRIM(s.name)))) as notes_count,
            (SELECT COUNT(*) FROM assignments a WHERE (a.class = s.class OR a.class = :base_cls) AND (LOWER(TRIM(a.subject)) = LOWER(TRIM(s.name)))) as assignments_count
            FROM subjects s
            LEFT JOIN teachers t ON s.teacher_id = t.id
            WHERE s.class = :cls OR s.class = :base_cls OR :cls LIKE (s.class || '-%')
            ORDER BY s.name ASC");
        $stmt->execute(['cls' => $class, 'base_cls' => $base_class]);
        $subjects = $stmt->fetchAll();

        // If no subjects found for specific division, fallback to grade level subjects
        if (empty($subjects) && $class !== 'Class 10-A') {
            $stmt = $pdo->prepare("SELECT s.*, 
                t.full_name as teacher_name, t.photo as teacher_photo, t.department as teacher_department, 
                t.designation as teacher_designation, t.qualification as teacher_qualification, t.phone as teacher_phone,
                (SELECT COUNT(*) FROM study_notes n WHERE n.class = s.class AND (LOWER(TRIM(n.subject)) = LOWER(TRIM(s.name)))) as notes_count,
                (SELECT COUNT(*) FROM assignments a WHERE a.class = s.class AND (LOWER(TRIM(a.subject)) = LOWER(TRIM(s.name)))) as assignments_count
                FROM subjects s
                LEFT JOIN teachers t ON s.teacher_id = t.id
                WHERE s.class = 'Class 10-A' OR s.class = 'Grade 10'
                ORDER BY s.name ASC");
            $stmt->execute();
            $subjects = $stmt->fetchAll();
        }
    }

    api_response([
        'class' => $class,
        'total_subjects' => count($subjects),
        'subjects' => $subjects
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($role, ['admin', 'principal', 'vice_principal'])) {
        api_error('Only administrators and school leadership can manage subjects.', 403);
    }

    $input = get_json_input();
    $action = $input['action'] ?? 'create';

    if ($action === 'create') {
        $name = trim($input['name'] ?? '');
        $class = trim($input['class'] ?? '');
        $code = trim($input['code'] ?? strtoupper(substr($name, 0, 3) . rand(100, 999)));
        $teacher_id = !empty($input['teacher_id']) ? (int)$input['teacher_id'] : null;
        $syllabus = trim($input['syllabus'] ?? 'CBSE National Standard Curriculum 2026-27');

        if (empty($name) || empty($class)) {
            api_error('Subject Name and Class are required.');
        }

        $ins = $pdo->prepare("INSERT INTO subjects (name, code, class, teacher_id, syllabus_info) VALUES (:n, :c, :cls, :tid, :syl)");
        $ins->execute([
            'n' => $name,
            'c' => $code,
            'cls' => $class,
            'tid' => $teacher_id,
            'syl' => $syllabus
        ]);

        api_response([
            'message' => "Subject {$name} for {$class} added successfully!",
            'subject_id' => $pdo->lastInsertId()
        ], 201);
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) api_error('Valid subject id is required.');

        $pdo->prepare("DELETE FROM subjects WHERE id = :id")->execute(['id' => $id]);
        api_response(['message' => 'Subject removed successfully.']);
    }

    api_error('Invalid action requested.');
}

api_error('Method Not Allowed', 405);
