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
    $class = $_GET['class'] ?? ($profile['class'] ?? 'Class 10');

    if ($role === 'teacher') {
        $teacher_id = $profile['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT s.*, 
            (SELECT COUNT(*) FROM study_notes n WHERE n.class = s.class AND n.subject = s.name) as notes_count,
            (SELECT COUNT(*) FROM assignments a WHERE a.class = s.class AND a.subject = s.name) as assignments_count
            FROM subjects s
            WHERE s.teacher_id = :tid OR s.class = :cls
            ORDER BY s.class ASC, s.name ASC");
        $stmt->execute(['tid' => $teacher_id, 'cls' => $profile['assigned_class'] ?? 'Class 10']);
        $subjects = $stmt->fetchAll();

    } else {
        // Student / Admin
        $stmt = $pdo->prepare("SELECT s.*, 
            t.full_name as teacher_name, t.photo as teacher_photo, t.department as teacher_department, 
            t.designation as teacher_designation, t.qualification as teacher_qualification, t.phone as teacher_phone,
            (SELECT COUNT(*) FROM study_notes n WHERE n.class = s.class AND n.subject = s.name) as notes_count,
            (SELECT COUNT(*) FROM assignments a WHERE a.class = s.class AND a.subject = s.name) as assignments_count
            FROM subjects s
            LEFT JOIN teachers t ON s.teacher_id = t.id
            WHERE s.class = :cls
            ORDER BY s.name ASC");
        $stmt->execute(['cls' => $class]);
        $subjects = $stmt->fetchAll();
    }

    api_response([
        'class' => $class,
        'total_subjects' => count($subjects),
        'subjects' => $subjects
    ]);
}

api_error('Method Not Allowed', 405);
