<?php
/**
 * REST API: In-App Notifications & Alerts
 * Bellwether International School Nandurbar ERP (BWIS ERP)
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];
$profile = $user['profile'];
$user_id = $user['id'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $class = $profile['class'] ?? '';
    $base_class = !empty($class) ? trim(explode('-', $class)[0]) : '';

    $where = "WHERE (n.user_id = :uid OR (n.role = :role AND n.user_id IS NULL)";
    $params = ['uid' => $user_id, 'role' => $role];

    if ($role === 'student' && !empty($class)) {
        $where .= " OR (n.role = 'student' AND (n.class = :cls OR n.class = :base_cls OR :cls LIKE (n.class || '-%') OR n.class LIKE (:base_cls || '-%')))";
        $params['cls'] = $class;
        $params['base_cls'] = $base_class;
    }
    $where .= ")";

    // Fetch unread count
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications n {$where} AND n.is_read = 0");
    $cntStmt->execute($params);
    $unread_count = (int)$cntStmt->fetchColumn();

    // Fetch notifications list
    $stmt = $pdo->prepare("SELECT n.* FROM notifications n {$where} ORDER BY n.created_at DESC LIMIT 50");
    $stmt->execute($params);
    $list = $stmt->fetchAll();

    api_response([
        'unread_count' => $unread_count,
        'total' => count($list),
        'notifications' => $list
    ]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $input = get_json_input();
    $action = $input['action'] ?? 'mark_read';

    if ($action === 'mark_read') {
        $notif_id = (int)($input['id'] ?? 0);
        if ($notif_id > 0) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id");
            $stmt->execute(['id' => $notif_id]);
        } else {
            // Mark all read for this user
            $class = $profile['class'] ?? '';
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid OR (role = :role AND class = :cls)");
            $stmt->execute(['uid' => $user_id, 'role' => $role, 'cls' => $class]);
        }
        api_response(['message' => 'Notification(s) marked as read.']);
    }

    if ($action === 'create' && in_array($role, ['teacher', 'admin', 'principal', 'vice_principal'])) {
        $title = trim($input['title'] ?? '');
        $message = trim($input['message'] ?? '');
        $target_class = trim($input['class'] ?? '');
        $target_role = trim($input['target_role'] ?? 'student');
        $type = trim($input['type'] ?? 'general');
        $ref_id = !empty($input['reference_id']) ? (int)$input['reference_id'] : null;

        if (empty($title) || empty($message)) {
            api_error('Title and message are required.');
        }

        $ins = $pdo->prepare("INSERT INTO notifications (role, class, title, message, type, reference_id) 
            VALUES (:r, :cls, :t, :m, :type, :ref)");
        $ins->execute([
            'r' => $target_role,
            'cls' => $target_class ?: null,
            't' => $title,
            'm' => $message,
            'type' => $type,
            'ref' => $ref_id
        ]);

        api_response(['message' => 'Notification published successfully!']);
    }

    api_error('Invalid action.');
}

api_error('Method Not Allowed', 405);
