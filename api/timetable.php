<?php
/**
 * REST API: Timetable & Period Planner
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];
$profile = $user['profile'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $class = $_GET['class'] ?? ($profile['class'] ?? 'Class 10');
    $day = $_GET['day'] ?? ''; // optional specific day filter

    $where = "WHERE 1=1";
    $params = [];

    if ($role === 'teacher') {
        $teacher_name = $profile['full_name'] ?? $user['username'];
        $where .= " AND (teacher_name LIKE :tname OR class = :cls)";
        $params['tname'] = '%' . $teacher_name . '%';
        $params['cls'] = $profile['assigned_class'] ?? $class;
    } else {
        $where .= " AND class = :cls";
        $params['cls'] = $class;
    }

    if (!empty($day)) {
        $where .= " AND day_of_week = :day";
        $params['day'] = ucfirst(strtolower($day));
    }

    $stmt = $pdo->prepare("SELECT * FROM timetables {$where} ORDER BY 
        CASE day_of_week
            WHEN 'Monday' THEN 1
            WHEN 'Tuesday' THEN 2
            WHEN 'Wednesday' THEN 3
            WHEN 'Thursday' THEN 4
            WHEN 'Friday' THEN 5
            WHEN 'Saturday' THEN 6
            ELSE 7
        END, period_number ASC");
    $stmt->execute($params);
    $all = $stmt->fetchAll();

    // Group by day of week
    $by_day = [
        'Monday' => [],
        'Tuesday' => [],
        'Wednesday' => [],
        'Thursday' => [],
        'Friday' => [],
        'Saturday' => []
    ];

    foreach ($all as $item) {
        $d = ucfirst(strtolower($item['day_of_week']));
        if (isset($by_day[$d])) {
            $by_day[$d][] = $item;
        }
    }

    api_response([
        'class' => $class,
        'current_day' => date('l'),
        'current_time' => date('h:i A'),
        'days' => $by_day
    ]);
}

api_error('Method Not Allowed', 405);
