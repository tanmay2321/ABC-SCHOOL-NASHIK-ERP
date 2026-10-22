<?php
/**
 * REST API: Digital Notice Board & Circulars
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $category = $_GET['category'] ?? '';

    $where = "WHERE 1=1";
    $params = [];

    // Audience filter
    if ($role === 'student') {
        $where .= " AND audience IN ('all', 'students')";
    } elseif ($role === 'teacher') {
        $where .= " AND audience IN ('all', 'teachers')";
    }

    if (!empty($category)) {
        $where .= " AND category = :cat";
        $params['cat'] = $category;
    }

    $stmt = $pdo->prepare("SELECT * FROM notices {$where} ORDER BY id DESC");
    $stmt->execute($params);
    $notices = $stmt->fetchAll();

    api_response([
        'total' => count($notices),
        'notices' => $notices
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($role, ['admin', 'principal', 'vice_principal'])) {
        api_error('Only administrative leadership can broadcast school notices.', 403);
    }

    $input = get_json_input();
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $category = trim($input['category'] ?? 'Circular');
    $audience = in_array($input['audience'] ?? '', ['all', 'teachers', 'students']) ? $input['audience'] : 'all';

    if (empty($title) || empty($content)) {
        api_error('Notice Title and Content are required.');
    }

    $ins = $pdo->prepare("INSERT INTO notices (title, content, audience, category, posted_by) VALUES (:t, :c, :a, :cat, :pby)");
    $ins->execute([
        't' => $title,
        'c' => $content,
        'a' => $audience,
        'cat' => $category,
        'pby' => $user['id']
    ]);

    api_response([
        'message' => 'Notice broadcasted to digital board successfully!',
        'notice_id' => $pdo->lastInsertId()
    ], 201);
}

api_error('Method Not Allowed', 405);
