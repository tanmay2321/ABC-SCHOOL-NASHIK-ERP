<?php
/**
 * REST API: Available School Classes & Divisions
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$standard_classes = [
    'Class 1-A', 'Class 1-B',
    'Class 2-A', 'Class 2-B',
    'Class 3-A', 'Class 3-B',
    'Class 4-A', 'Class 4-B',
    'Class 5-A', 'Class 5-B',
    'Class 6-A', 'Class 6-B',
    'Class 7-A', 'Class 7-B',
    'Class 8-A', 'Class 8-B',
    'Class 9-A', 'Class 9-B',
    'Class 10-A', 'Class 10-B',
    'Class 11-Science', 'Class 11-Commerce', 'Class 11-Arts',
    'Class 12-Science', 'Class 12-Commerce', 'Class 12-Arts'
];

try {
    $db_classes = $pdo->query("
        SELECT DISTINCT class FROM subjects WHERE class IS NOT NULL AND class != ''
        UNION
        SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != ''
        ORDER BY class ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $merged = array_values(array_unique(array_merge($standard_classes, $db_classes)));
    sort($merged, SORT_NATURAL);

    api_response([
        'total' => count($merged),
        'classes' => $merged
    ]);
} catch (Exception $e) {
    api_response([
        'total' => count($standard_classes),
        'classes' => $standard_classes
    ]);
}
