<?php
/**
 * REST API: User Profile & Photo Identity
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];
$profile = $user['profile'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    api_response([
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status']
        ],
        'profile' => $profile
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = get_json_input();
    $upload_dir = __DIR__ . '/../uploads/profiles/';
    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

    $photo_path = null;

    // Handle photo upload if present
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $fname = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $fname)) {
                $photo_path = '/uploads/profiles/' . $fname;
            }
        }
    } elseif (!empty($input['photo_base64'])) {
        $ext = strtolower($input['photo_ext'] ?? 'jpg');
        $fname = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
        if (file_put_contents($upload_dir . $fname, base64_decode($input['photo_base64']))) {
            $photo_path = '/uploads/profiles/' . $fname;
        }
    }

    // Update profile based on role
    if ($role === 'student' && !empty($profile['id'])) {
        $phone = trim($input['phone'] ?? $profile['phone'] ?? '');
        $address = trim($input['address'] ?? $profile['address'] ?? '');
        $blood_group = trim($input['blood_group'] ?? $profile['blood_group'] ?? '');

        if ($photo_path) {
            $upd = $pdo->prepare("UPDATE students SET phone = :ph, address = :addr, blood_group = :bg, photo = :photo WHERE id = :id");
            $upd->execute(['ph' => $phone, 'addr' => $address, 'bg' => $blood_group, 'photo' => $photo_path, 'id' => $profile['id']]);
        } else {
            $upd = $pdo->prepare("UPDATE students SET phone = :ph, address = :addr, blood_group = :bg WHERE id = :id");
            $upd->execute(['ph' => $phone, 'addr' => $address, 'bg' => $blood_group, 'id' => $profile['id']]);
        }
    } elseif ($role === 'teacher' && !empty($profile['id'])) {
        $phone = trim($input['phone'] ?? $profile['phone'] ?? '');
        $qualification = trim($input['qualification'] ?? $profile['qualification'] ?? '');

        if ($photo_path) {
            $upd = $pdo->prepare("UPDATE teachers SET phone = :ph, qualification = :qual, photo = :photo WHERE id = :id");
            $upd->execute(['ph' => $phone, 'qual' => $qualification, 'photo' => $photo_path, 'id' => $profile['id']]);
        } else {
            $upd = $pdo->prepare("UPDATE teachers SET phone = :ph, qualification = :qual WHERE id = :id");
            $upd->execute(['ph' => $phone, 'qual' => $qualification, 'id' => $profile['id']]);
        }
    }

    // Handle password change if requested
    if (!empty($input['new_password'])) {
        $new_hash = password_hash($input['new_password'], PASSWORD_BCRYPT);
        $pwStmt = $pdo->prepare("UPDATE users SET password = :pw WHERE id = :id");
        $pwStmt->execute(['pw' => $new_hash, 'id' => $user['id']]);
    }

    api_response([
        'message' => 'Profile updated successfully!',
        'photo' => $photo_path
    ]);
}

api_error('Method Not Allowed', 405);
