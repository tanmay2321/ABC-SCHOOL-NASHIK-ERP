<?php
/**
 * Admin: Student Approvals & Roster Management
 * Bellwether International School Nandurbar ERP
 * Full access: Approve/Reject registrations, Edit Student details, Delete
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth(['admin', 'principal', 'vice_principal']);

$error = '';
$active_tab = $_GET['tab'] ?? ($_GET['status'] ?? 'all');

// Handle Student Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            $stmt = $pdo->prepare("UPDATE users SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE id = :uid AND role = 'student'");
            $stmt->execute(['uid' => $user_id]);
            set_flash('success', 'Student registration approved! Student is now permitted to log in to the School ERP.');
            header("Location: /admin/students.php?tab=pending");
            exit();
        }
    } elseif ($action === 'reject') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            $stmt = $pdo->prepare("UPDATE users SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = :uid AND role = 'student'");
            $stmt->execute(['uid' => $user_id]);
            set_flash('warning', 'Student registration was rejected.');
            header("Location: /admin/students.php?tab=pending");
            exit();
        }
    } elseif ($action === 'update_student') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $class = trim($_POST['class'] ?? '');
        $roll_no = trim($_POST['roll_no'] ?? '');
        $guardian_name = trim($_POST['guardian_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $blood_group = trim($_POST['blood_group'] ?? 'B+');
        $status = trim($_POST['status'] ?? 'approved');
        $new_password = $_POST['new_password'] ?? '';

        if ($student_id > 0 && $user_id > 0) {
            try {
                $pdo->beginTransaction();

                // Update users record
                $uStmt = $pdo->prepare("UPDATE users SET email = :email, status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :uid");
                $uStmt->execute(['email' => $email, 'status' => $status, 'uid' => $user_id]);

                // Update password if given
                if (!empty($new_password)) {
                    $hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $pStmt = $pdo->prepare("UPDATE users SET password = :p WHERE id = :uid");
                    $pStmt->execute(['p' => $hash, 'uid' => $user_id]);
                }

                // Update students profile
                $sStmt = $pdo->prepare("UPDATE students SET full_name = :fname, class = :class, roll_no = :roll, guardian_name = :gname, phone = :phone, address = :addr, blood_group = :bg WHERE id = :sid");
                $sStmt->execute([
                    'fname' => $full_name,
                    'class' => $class,
                    'roll' => $roll_no,
                    'gname' => $guardian_name,
                    'phone' => $phone,
                    'addr' => $address,
                    'bg' => $blood_group,
                    'sid' => $student_id
                ]);

                $pdo->commit();
                set_flash('success', "Student details for <strong>{$full_name}</strong> updated successfully!");
                header("Location: /admin/students.php?tab=" . urlencode($active_tab));
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error updating student: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_student') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :uid AND role = 'student'");
                $stmt->execute(['uid' => $user_id]);
                set_flash('success', 'Student account and records permanently deleted.');
                header("Location: /admin/students.php?tab=" . urlencode($active_tab));
                exit();
            } catch (Exception $e) {
                $error = 'Error deleting student: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'create_student') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $class = trim($_POST['class'] ?? '');
        $roll_no = trim($_POST['roll_no'] ?? '');
        $guardian_name = trim($_POST['guardian_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $blood_group = trim($_POST['blood_group'] ?? 'B+');
        $gender = trim($_POST['gender'] ?? 'Male');
        $dob = trim($_POST['dob'] ?? '2010-01-01');
        $password = $_POST['password'] ?? 'student123';
        $status = $_POST['status'] ?? 'approved'; // Admin can directly approve

        if (empty($full_name) || empty($email) || empty($username) || empty($class)) {
            $error = 'Please fill in Name, Email, Username, and Class.';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = :e OR username = :u LIMIT 1");
            $check->execute(['e' => $email, 'u' => $username]);
            if ($check->fetch()) {
                $error = 'A user with this email or username already exists.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $uStmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (:u, :e, :p, 'student', :st)");
                    $uStmt->execute(['u' => $username, 'e' => $email, 'p' => $hash, 'st' => $status]);
                    $user_id = $pdo->lastInsertId();

                    $student_code = 'STU-2026-' . rand(1000, 9999);
                    $sStmt = $pdo->prepare("INSERT INTO students (user_id, student_code, full_name, gender, dob, class, roll_no, guardian_name, phone, address, blood_group, admission_date) 
                                            VALUES (:uid, :code, :name, :gender, :dob, :class, :roll, :gname, :phone, :addr, :bg, :adate)");
                    $sStmt->execute([
                        'uid' => $user_id,
                        'code' => $student_code,
                        'name' => $full_name,
                        'gender' => $gender,
                        'dob' => $dob,
                        'class' => $class,
                        'roll' => $roll_no,
                        'gname' => $guardian_name,
                        'phone' => $phone,
                        'addr' => $address,
                        'bg' => $blood_group,
                        'adate' => date('Y-m-d')
                    ]);

                    $pdo->commit();
                    set_flash('success', "New student <strong>{$full_name}</strong> ({$student_code}) created successfully!");
                    header("Location: /admin/students.php?tab=" . urlencode($status));
                    exit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Error creating student: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch list of students based on active tab
$where = "WHERE u.role = 'student'";
if ($active_tab === 'pending') {
    $where .= " AND u.status = 'pending'";
} elseif ($active_tab === 'approved') {
    $where .= " AND u.status = 'approved'";
} elseif ($active_tab === 'rejected') {
    $where .= " AND u.status IN ('rejected', 'inactive')";
}

$students = $pdo->query("
    SELECT s.*, u.id as user_id, u.username, u.email, u.status, u.created_at as registered_at
    FROM students s
    JOIN users u ON s.user_id = u.id
    {$where}
    ORDER BY u.id DESC
")->fetchAll();

// Counts for tabs
$counts = [
    'all' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'pending'")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'approved'")->fetchColumn(),
    'rejected' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status IN ('rejected', 'inactive')")->fetchColumn(),
];

$flash = get_flash();
?>

<?php 
$page_title = 'Student Approvals & Directory';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php'; 
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
                <span>Admissions &amp; Enrollment Hub</span>
                <span>&bull;</span>
                <span class="text-slate-400">Total Enrolled: <?= $counts['all'] ?> Students</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Student Approvals &amp; Full Access Management
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Students registering online are kept in <strong>Pending</strong> state until you approve them. Admin can modify all details.
            </p>
        </div>

        <button type="button" onclick="openModal('addStudentModal')" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-semibold text-xs shadow-md shadow-navy-900/10 transition">
            <i class="fa-solid fa-user-plus mr-2 text-amber-400"></i> Register Student Directly
        </button>
    </div>

    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl bg-<?= $flash['type'] === 'success' ? 'emerald' : ($flash['type'] === 'warning' ? 'amber' : 'rose') ?>-50 border border-<?= $flash['type'] === 'success' ? 'emerald' : ($flash['type'] === 'warning' ? 'amber' : 'rose') ?>-200 text-<?= $flash['type'] === 'success' ? 'emerald' : ($flash['type'] === 'warning' ? 'amber' : 'rose') ?>-900 text-xs font-semibold flex items-center justify-between auto-dismiss-alert">
            <div class="flex items-center space-x-2.5">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-amber-600' ?> text-base"></i>
                <span><?= $flash['message'] ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center space-x-2.5">
            <i class="fa-solid fa-triangle-exclamation text-base"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Filter Tabs & Search Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm mb-6 flex flex-col md:flex-row items-center justify-between gap-4">
        
        <!-- Navigation Tabs -->
        <div class="flex items-center space-x-2 overflow-x-auto w-full md:w-auto pb-2 md:pb-0">
            <a href="/admin/students.php?tab=all" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 <?= $active_tab === 'all' ? 'bg-navy-800 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                <span>All Students</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] <?= $active_tab === 'all' ? 'bg-navy-700 text-white' : 'bg-slate-200 text-slate-700' ?>"><?= $counts['all'] ?></span>
            </a>

            <a href="/admin/students.php?tab=pending" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 <?= $active_tab === 'pending' ? 'bg-amber-500 text-white shadow-sm' : 'bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200' ?>">
                <i class="fa-solid fa-user-clock text-xs"></i>
                <span>Pending Approvals</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] <?= $active_tab === 'pending' ? 'bg-amber-600 text-white' : 'bg-amber-200 text-amber-900 font-extrabold' ?>"><?= $counts['pending'] ?></span>
            </a>

            <a href="/admin/students.php?tab=approved" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 <?= $active_tab === 'approved' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                <i class="fa-solid fa-user-check text-xs"></i>
                <span>Approved (Active)</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] <?= $active_tab === 'approved' ? 'bg-emerald-700 text-white' : 'bg-slate-200 text-slate-700' ?>"><?= $counts['approved'] ?></span>
            </a>

            <a href="/admin/students.php?tab=rejected" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 <?= $active_tab === 'rejected' ? 'bg-rose-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                <span>Rejected / Inactive</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] <?= $active_tab === 'rejected' ? 'bg-rose-700 text-white' : 'bg-slate-200 text-slate-700' ?>"><?= $counts['rejected'] ?></span>
            </a>
        </div>

        <!-- Search Bar -->
        <div class="relative w-full md:w-72">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
            </div>
            <input type="text" id="studentSearch" onkeyup="filterTable('studentSearch', 'studentsTable')" placeholder="Search student name, roll, class..." 
                   class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
        </div>
    </div>

    <!-- Students Table -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table id="studentsTable" class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3 px-4 sm:px-6">Student Details</th>
                        <th class="py-3 px-4">Student ID</th>
                        <th class="py-3 px-4">Class &amp; Roll</th>
                        <th class="py-3 px-4">Guardian &amp; Contact</th>
                        <th class="py-3 px-4">Admission Date</th>
                        <th class="py-3 px-4">Login Status</th>
                        <th class="py-3 px-4 sm:px-6 text-right">Admin Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2 text-lg">
                                    <i class="fa-solid fa-user-slash"></i>
                                </div>
                                <p class="font-bold text-slate-700">No students found under this filter.</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">Try selecting another tab or register a new student.</p>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($students as $s): ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-4 sm:px-6">
                                <div class="flex items-center space-x-3">
                                    <?php if (!empty($s['photo']) && file_exists(__DIR__ . '/../' . ltrim($s['photo'], '/'))): ?>
                                        <img src="<?= htmlspecialchars($s['photo']) ?>" alt="Photo" class="w-9 h-9 rounded-xl object-cover border border-amber-300 shadow-2xs shrink-0">
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-xl <?= $s['gender'] === 'Female' ? 'bg-pink-100 text-pink-700' : 'bg-blue-100 text-blue-700' ?> flex items-center justify-center font-bold text-xs shrink-0">
                                            <?= strtoupper(substr($s['full_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="font-bold text-slate-900"><?= htmlspecialchars($s['full_name']) ?></div>
                                        <div class="text-[11px] text-slate-500"><?= htmlspecialchars($s['email']) ?></div>
                                        <div class="text-[10px] text-slate-400">Blood: <span class="font-semibold text-slate-600"><?= htmlspecialchars($s['blood_group'] ?: 'N/A') ?></span></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-mono font-bold text-blue-700">
                                <?= htmlspecialchars($s['student_code']) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                                    <?= htmlspecialchars($s['class']) ?>
                                </span>
                                <div class="text-[11px] text-slate-500 mt-0.5">Roll No: <strong><?= htmlspecialchars($s['roll_no'] ?: 'N/A') ?></strong></div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-medium text-slate-900"><?= htmlspecialchars($s['guardian_name'] ?: 'N/A') ?></div>
                                <div class="text-[11px] text-slate-500"><?= htmlspecialchars($s['phone'] ?: 'N/A') ?></div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-500">
                                <?= date('d M Y', strtotime($s['admission_date'] ?: $s['registered_at'])) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <?php if ($s['status'] === 'approved'): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-circle-check text-[10px] mr-1 text-emerald-600"></i>
                                        Approved (Active)
                                    </span>
                                <?php elseif ($s['status'] === 'pending'): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5 animate-pulse"></span>
                                        Pending Approval
                                    </span>
                                <?php elseif ($s['status'] === 'rejected'): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800">
                                        <i class="fa-solid fa-circle-xmark text-[10px] mr-1"></i>
                                        Rejected
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 sm:px-6 text-right">
                                <div class="flex items-center justify-end space-x-1.5">
                                    
                                    <!-- If Pending: Show Instant Approve and Reject Buttons -->
                                    <?php if ($s['status'] === 'pending'): ?>
                                        <form method="POST" action="students.php" onsubmit="return confirm('Approve this student? They will be granted immediate login access.');">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="user_id" value="<?= $s['user_id'] ?>">
                                            <button type="submit" class="p-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition" title="Approve Student Login">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                        </form>

                                        <form method="POST" action="students.php" onsubmit="return confirm('Reject this student application?');">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="user_id" value="<?= $s['user_id'] ?>">
                                            <button type="submit" class="p-2 rounded-lg bg-amber-100 hover:bg-rose-100 text-amber-800 hover:text-rose-700 text-xs transition" title="Reject Application">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Edit Student Details Button (Admin Full Access) -->
                                    <button type="button" 
                                            onclick='openEditStudentModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, "UTF-8") ?>)'
                                            class="p-2 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 font-semibold text-xs transition" title="Edit Student Details">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <!-- Delete Student -->
                                    <form method="POST" action="students.php" onsubmit="return confirm('Permanently remove this student record and their login ID?');">
                                        <input type="hidden" name="action" value="delete_student">
                                        <input type="hidden" name="user_id" value="<?= $s['user_id'] ?>">
                                        <button type="submit" class="p-2 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 text-xs transition" title="Delete Student">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- CREATE STUDENT DIRECTLY (Admin Form) -->
<div id="addStudentModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-user-plus text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">Enroll New Student</h3>
                    <p class="text-xs text-slate-500">Create student account with pre-approved or pending status</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('addStudentModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="students.php" class="space-y-4">
            <input type="hidden" name="action" value="create_student">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Full Name *</label>
                    <input type="text" name="full_name" required placeholder="e.g. Atharva Joshi" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Class / Grade *</label>
                    <select name="class" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                        <option value="Class 9-A">Class 9-A</option>
                        <option value="Class 9-B">Class 9-B</option>
                        <option value="Class 10-A" selected>Class 10-A</option>
                        <option value="Class 10-B">Class 10-B</option>
                        <option value="Class 11-Science">Class 11 - Science</option>
                        <option value="Class 11-Commerce">Class 11 - Commerce</option>
                        <option value="Class 12-Science">Class 12 - Science</option>
                        <option value="Class 12-Commerce">Class 12 - Commerce</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Email *</label>
                    <input type="email" name="email" required placeholder="student@bwis.edu.in" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Username *</label>
                    <input type="text" name="username" required placeholder="e.g. atharva.j" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Roll Number</label>
                    <input type="text" name="roll_no" placeholder="1025" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Gender</label>
                    <select name="gender" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Blood Group</label>
                    <select name="blood_group" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                        <option value="A+">A+</option>
                        <option value="B+" selected>B+</option>
                        <option value="O+">O+</option>
                        <option value="AB+">AB+</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Guardian Name</label>
                    <input type="text" name="guardian_name" placeholder="Parent / Guardian" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Phone Number</label>
                    <input type="text" name="phone" placeholder="+91 98XXX XXXXX" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Account Status</label>
                    <select name="status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                        <option value="approved" selected>Approved (Can login right away)</option>
                        <option value="pending">Pending (Awaiting verification)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Password</label>
                    <input type="password" name="password" value="student123" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
            </div>

            <div class="pt-4 flex items-center justify-end space-x-3">
                <button type="button" onclick="closeModal('addStudentModal')" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-semibold text-xs">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md">
                    <i class="fa-solid fa-check mr-1.5 text-amber-400"></i> Register Student
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT STUDENT MODAL (Admin Whole Access) -->
<div id="editStudentModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
            <div class="flex items-center space-x-3">
                <div id="edit_student_avatar" class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold overflow-hidden border border-amber-200 shrink-0">
                    <i class="fa-solid fa-user-pen text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">Edit Student Details</h3>
                    <p class="text-xs text-slate-500">Admin full access: modify student academic &amp; login details</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('editStudentModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="students.php" class="space-y-4">
            <input type="hidden" name="action" value="update_student">
            <input type="hidden" name="student_id" id="edit_student_id">
            <input type="hidden" name="user_id" id="edit_user_id">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Student Code (System ID)</label>
                    <input type="text" id="edit_student_code" readonly class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-100 text-sm font-mono text-slate-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Full Name *</label>
                    <input type="text" name="full_name" id="edit_full_name" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Class / Grade *</label>
                    <input type="text" name="class" id="edit_class" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Roll Number</label>
                    <input type="text" name="roll_no" id="edit_roll_no" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Email Address *</label>
                    <input type="email" name="email" id="edit_email" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Phone Number</label>
                    <input type="text" name="phone" id="edit_phone" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Guardian Name</label>
                    <input type="text" name="guardian_name" id="edit_guardian_name" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Blood Group</label>
                    <input type="text" name="blood_group" id="edit_blood_group" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Residential Address</label>
                <textarea name="address" id="edit_address" rows="2" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none"></textarea>
            </div>

            <!-- Login Status Switch -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Student Login Status *</label>
                <select name="status" id="edit_status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none font-semibold">
                    <option value="approved">Approved (Can login normally)</option>
                    <option value="pending">Pending (Blocked from login until approved)</option>
                    <option value="rejected">Rejected (Application declined)</option>
                    <option value="inactive">Inactive / Suspended</option>
                </select>
                <span class="text-[11px] text-slate-400 mt-1 block">Changing to <strong>Approved</strong> immediately enables student portal access.</span>
            </div>

            <div class="pt-2 border-t border-slate-200">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Reset Password (Leave blank to keep unchanged)</label>
                <input type="password" name="new_password" placeholder="New password if resetting" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
            </div>

            <div class="pt-4 flex items-center justify-end space-x-3">
                <button type="button" onclick="closeModal('editStudentModal')" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-semibold text-xs">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditStudentModal(s) {
        document.getElementById('edit_student_id').value = s.id;
        document.getElementById('edit_user_id').value = s.user_id;
        document.getElementById('edit_student_code').value = s.student_code;
        document.getElementById('edit_full_name').value = s.full_name;
        document.getElementById('edit_class').value = s.class;
        document.getElementById('edit_roll_no').value = s.roll_no || '';
        document.getElementById('edit_email').value = s.email;
        document.getElementById('edit_phone').value = s.phone || '';
        document.getElementById('edit_guardian_name').value = s.guardian_name || '';
        document.getElementById('edit_blood_group').value = s.blood_group || '';
        document.getElementById('edit_address').value = s.address || '';
        document.getElementById('edit_status').value = s.status || 'approved';
        const av = document.getElementById('edit_student_avatar');
        if (s.photo) {
            av.innerHTML = `<img src="${s.photo}" class="w-full h-full object-cover">`;
        } else {
            av.innerHTML = `<i class="fa-solid fa-user-pen text-lg"></i>`;
        }
        openModal('editStudentModal');
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
