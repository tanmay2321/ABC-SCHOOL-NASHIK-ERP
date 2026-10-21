<?php
/**
 * Admin: Teacher Management
 * Bellwether International School Nandurbar ERP
 * Full access: Create Teacher ID, Edit Teacher Details, Delete Teacher
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth(['admin', 'principal']);

$error = '';
$success = '';

// Handle Actions: Add, Edit, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_teacher') {
        $teacher_code = trim($_POST['teacher_code'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $designation = trim($_POST['designation'] ?? 'TGT Teacher');
        $gender = trim($_POST['gender'] ?? 'Male');
        $phone = trim($_POST['phone'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $assigned_class = trim($_POST['assigned_class'] ?? '');
        if ($assigned_class === 'none' || empty($assigned_class)) {
            $assigned_class = null;
        }
        $joining_date = trim($_POST['joining_date'] ?? date('Y-m-d'));
        $password = $_POST['password'] ?? 'teacher123';

        if (empty($full_name) || empty($email) || empty($username) || empty($teacher_code)) {
            $error = 'Please fill all mandatory fields (Name, Email, Username, Teacher ID).';
        } else {
            // Check uniqueness
            $check = $pdo->prepare("SELECT id FROM users WHERE email = :e OR username = :u LIMIT 1");
            $check->execute(['e' => $email, 'u' => $username]);
            if ($check->fetch()) {
                $error = 'A user with this email or username already exists.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $uStmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (:u, :e, :p, 'teacher', 'approved')");
                    $uStmt->execute(['u' => $username, 'e' => $email, 'p' => $hash]);
                    $user_id = $pdo->lastInsertId();

                    $tStmt = $pdo->prepare("INSERT INTO teachers (user_id, teacher_code, full_name, gender, phone, department, designation, qualification, assigned_class, joining_date)
                                            VALUES (:uid, :code, :name, :gender, :phone, :dept, :desig, :qual, :assigned_class, :jdate)");
                    $tStmt->execute([
                        'uid' => $user_id,
                        'code' => $teacher_code,
                        'name' => $full_name,
                        'gender' => $gender,
                        'phone' => $phone,
                        'dept' => $department,
                        'desig' => $designation,
                        'qual' => $qualification,
                        'assigned_class' => $assigned_class,
                        'jdate' => $joining_date
                    ]);

                    $pdo->commit();
                    set_flash('success', "Teacher ID <strong>{$teacher_code}</strong> for {$full_name} has been successfully created!");
                    header("Location: /admin/teachers.php");
                    exit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Error creating teacher: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'update_teacher') {
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $assigned_class = trim($_POST['assigned_class'] ?? '');
        if ($assigned_class === 'none' || empty($assigned_class)) {
            $assigned_class = null;
        }
        $status = trim($_POST['status'] ?? 'approved');
        $new_password = $_POST['new_password'] ?? '';

        if ($teacher_id > 0 && $user_id > 0) {
            try {
                $pdo->beginTransaction();

                // Update users table
                $uQuery = "UPDATE users SET email = :email, status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :uid";
                $uStmt = $pdo->prepare($uQuery);
                $uStmt->execute(['email' => $email, 'status' => $status, 'uid' => $user_id]);

                // Update password if specified
                if (!empty($new_password)) {
                    $hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $pStmt = $pdo->prepare("UPDATE users SET password = :p WHERE id = :uid");
                    $pStmt->execute(['p' => $hash, 'uid' => $user_id]);
                }

                // Update teachers table
                $tStmt = $pdo->prepare("UPDATE teachers SET full_name = :name, department = :dept, designation = :desig, phone = :phone, qualification = :qual, assigned_class = :assigned_class WHERE id = :tid");
                $tStmt->execute([
                    'name' => $full_name,
                    'dept' => $department,
                    'desig' => $designation,
                    'phone' => $phone,
                    'qual' => $qualification,
                    'assigned_class' => $assigned_class,
                    'tid' => $teacher_id
                ]);

                $pdo->commit();
                set_flash('success', "Teacher details for {$full_name} have been updated successfully!");
                header("Location: /admin/teachers.php");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error updating teacher details: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_teacher') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :uid AND role = 'teacher'");
                $stmt->execute(['uid' => $user_id]);
                set_flash('success', 'Teacher account and associated profile have been permanently deleted.');
                header("Location: /admin/teachers.php");
                exit();
            } catch (Exception $e) {
                $error = 'Error deleting teacher: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all teachers
$teachers = $pdo->query("
    SELECT t.*, u.username, u.email, u.status, u.created_at as user_created
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.id DESC
")->fetchAll();

$next_teacher_code = 'TCH-' . (200 + count($teachers) + 1);
$flash = get_flash();
?>

<?php 
$page_title = 'Teachers Directory & ID Management';
require_once __DIR__ . '/../includes/header.php'; 
require_once __DIR__ . '/../includes/sidebar.php'; 
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
                <span>Faculty Administration</span>
                <span>&bull;</span>
                <span class="text-slate-400">Total: <?= count($teachers) ?> Faculty Members</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Teacher Management &amp; ID Creation
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Admin has full privileges to create new teacher IDs, modify teacher details, or manage credentials.
            </p>
        </div>

        <button type="button" onclick="openModal('addTeacherModal')" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-semibold text-xs shadow-md shadow-navy-900/10 transition">
            <i class="fa-solid fa-plus-circle mr-2 text-amber-400"></i> Create New Teacher ID
        </button>
    </div>

    <!-- Flash & Alerts -->
    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl bg-<?= $flash['type'] === 'success' ? 'emerald' : 'amber' ?>-50 border border-<?= $flash['type'] === 'success' ? 'emerald' : 'amber' ?>-200 text-<?= $flash['type'] === 'success' ? 'emerald' : 'amber' ?>-900 text-xs font-semibold flex items-center justify-between auto-dismiss-alert">
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

    <!-- Search & Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="relative w-full sm:w-80">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
            </div>
            <input type="text" id="teacherSearch" onkeyup="filterTable('teacherSearch', 'teachersTable')" placeholder="Search by name, ID, department..." 
                   class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
        </div>

        <div class="text-xs text-slate-500">
            Showing <strong class="text-slate-900"><?= count($teachers) ?></strong> registered faculty members
        </div>
    </div>

    <!-- Teachers Table -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table id="teachersTable" class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3 px-4 sm:px-6">Teacher Profile</th>
                        <th class="py-3 px-4">Teacher ID</th>
                        <th class="py-3 px-4">Department &amp; Post</th>
                        <th class="py-3 px-4">Contact Phone</th>
                        <th class="py-3 px-4">Joining Date</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 sm:px-6 text-right">Admin Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    <?php if (empty($teachers)): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                No teachers found. Click "Create New Teacher ID" to add your first faculty member.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($teachers as $t): ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-4 sm:px-6">
                                <div class="flex items-center space-x-3">
                                    <?php if (!empty($t['photo']) && file_exists(__DIR__ . '/../' . ltrim($t['photo'], '/'))): ?>
                                        <img src="<?= htmlspecialchars($t['photo']) ?>" alt="Photo" class="w-9 h-9 rounded-xl object-cover border border-amber-300 shadow-2xs shrink-0">
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-xl bg-navy-800 text-amber-300 flex items-center justify-center font-bold text-xs shrink-0">
                                            <?= strtoupper(substr($t['full_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="font-bold text-slate-900"><?= htmlspecialchars($t['full_name']) ?></div>
                                        <div class="text-[11px] text-slate-500"><?= htmlspecialchars($t['email']) ?></div>
                                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($t['qualification'] ?: 'Faculty') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-mono font-bold text-blue-700">
                                <?= htmlspecialchars($t['teacher_code']) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-slate-900"><?= htmlspecialchars($t['department']) ?></div>
                                <div class="text-[11px] text-slate-500"><?= htmlspecialchars($t['designation'] ?: 'Teacher') ?></div>
                                <?php if (!empty($t['assigned_class'])): ?>
                                    <div class="mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-900 border border-amber-300">
                                            <i class="fa-solid fa-chalkboard-user mr-1 text-amber-600"></i> Class Teacher (<?= htmlspecialchars($t['assigned_class']) ?>)
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600">
                                <?= htmlspecialchars($t['phone'] ?: 'N/A') ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-500">
                                <?= date('d M Y', strtotime($t['joining_date'] ?: $t['user_created'])) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold <?= $t['status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' ?>">
                                    <?= ucfirst($t['status']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 sm:px-6 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    <!-- Edit Teacher Button -->
                                    <button type="button" 
                                            onclick='openEditTeacherModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, "UTF-8") ?>)'
                                            class="p-2 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 font-semibold text-xs transition" title="Edit Teacher Details">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <!-- Delete Teacher Form -->
                                    <form method="POST" action="teachers.php" onsubmit="return confirm('Are you sure you want to delete this teacher account? This action cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_teacher">
                                        <input type="hidden" name="user_id" value="<?= $t['user_id'] ?>">
                                        <button type="submit" class="p-2 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 text-xs transition" title="Delete Teacher">
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

<!-- CREATE TEACHER MODAL -->
<div id="addTeacherModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-user-plus text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">Create New Teacher ID</h3>
                    <p class="text-xs text-slate-500">Register new faculty member with login credentials</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('addTeacherModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="teachers.php" class="space-y-4">
            <input type="hidden" name="action" value="create_teacher">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Teacher ID Code *</label>
                    <input type="text" name="teacher_code" required value="<?= $next_teacher_code ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm font-mono focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Full Name *</label>
                    <input type="text" name="full_name" required placeholder="e.g. Mr. Nilesh Patil" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Official Email *</label>
                    <input type="email" name="email" required placeholder="name@abcschool.edu" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Login Username *</label>
                    <input type="text" name="username" required placeholder="e.g. nilesh.patil" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Department *</label>
                    <select name="department" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                        <option value="Mathematics">Mathematics</option>
                        <option value="Science">Science (Physics/Chem/Bio)</option>
                        <option value="English">English Literature</option>
                        <option value="Social Science">Social Science / History</option>
                        <option value="Computer Science">Computer Science &amp; IT</option>
                        <option value="Hindi & Marathi">Hindi &amp; Marathi</option>
                        <option value="Physical Education">Physical Education &amp; Sports</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Designation</label>
                    <input type="text" name="designation" placeholder="e.g. TGT Mathematics" value="TGT Faculty" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Gender</label>
                    <select name="gender" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Phone</label>
                    <input type="text" name="phone" placeholder="+91 98XXX XXXXX" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Joining Date</label>
                    <input type="date" name="joining_date" value="<?= date('Y-m-d') ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Qualifications</label>
                    <input type="text" name="qualification" placeholder="e.g. M.Sc, B.Ed (Maths)" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-amber-800 uppercase tracking-wider mb-1">
                        <i class="fa-solid fa-chalkboard-user mr-1 text-amber-600"></i> Allot Class Teacher For
                    </label>
                    <select name="assigned_class" class="w-full px-3.5 py-2.5 rounded-xl border border-amber-300 bg-amber-50/50 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <option value="">None (Subject Teacher Only)</option>
                        <option value="Class 10-A">Class 10-A</option>
                        <option value="Class 10-B">Class 10-B</option>
                        <option value="Class 9-A">Class 9-A</option>
                        <option value="Class 9-B">Class 9-B</option>
                        <option value="Class 8-A">Class 8-A</option>
                        <option value="Class 8-B">Class 8-B</option>
                        <option value="Class 7-A">Class 7-A</option>
                        <option value="Class 7-B">Class 7-B</option>
                        <option value="Class 6-A">Class 6-A</option>
                        <option value="Class 6-B">Class 6-B</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Initial Password *</label>
                <input type="password" name="password" required value="teacher123" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                <span class="text-[11px] text-slate-400">Default is set to: <code>teacher123</code></span>
            </div>

            <div class="pt-4 flex items-center justify-end space-x-3">
                <button type="button" onclick="closeModal('addTeacherModal')" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-semibold text-xs">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md">
                    <i class="fa-solid fa-check mr-1.5 text-amber-400"></i> Create Teacher Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT TEACHER MODAL (Admin Full Access) -->
<div id="editTeacherModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-pen-to-square text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">Edit Teacher Details</h3>
                    <p class="text-xs text-slate-500">Admin full access: modify profile &amp; credentials</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('editTeacherModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="teachers.php" class="space-y-4">
            <input type="hidden" name="action" value="update_teacher">
            <input type="hidden" name="teacher_id" id="edit_teacher_id">
            <input type="hidden" name="user_id" id="edit_user_id">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Teacher Code (Locked)</label>
                    <input type="text" id="edit_teacher_code" readonly class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-100 text-sm font-mono text-slate-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Full Name *</label>
                    <input type="text" name="full_name" id="edit_full_name" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
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
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Department</label>
                    <input type="text" name="department" id="edit_department" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Designation</label>
                    <input type="text" name="designation" id="edit_designation" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Qualification</label>
                    <input type="text" name="qualification" id="edit_qualification" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-amber-800 uppercase tracking-wider mb-1">
                        <i class="fa-solid fa-chalkboard-user mr-1 text-amber-600"></i> Allot Class Teacher For
                    </label>
                    <select name="assigned_class" id="edit_assigned_class" class="w-full px-3.5 py-2.5 rounded-xl border border-amber-300 bg-amber-50/50 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <option value="">None (Subject Teacher Only)</option>
                        <option value="Class 10-A">Class 10-A</option>
                        <option value="Class 10-B">Class 10-B</option>
                        <option value="Class 9-A">Class 9-A</option>
                        <option value="Class 9-B">Class 9-B</option>
                        <option value="Class 8-A">Class 8-A</option>
                        <option value="Class 8-B">Class 8-B</option>
                        <option value="Class 7-A">Class 7-A</option>
                        <option value="Class 7-B">Class 7-B</option>
                        <option value="Class 6-A">Class 6-A</option>
                        <option value="Class 6-B">Class 6-B</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Account Status</label>
                <select name="status" id="edit_status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                    <option value="approved">Approved &amp; Active</option>
                    <option value="inactive">Inactive / Suspended</option>
                </select>
            </div>

            <div class="pt-2 border-t border-slate-200">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Reset Password (Leave blank to keep unchanged)</label>
                <input type="password" name="new_password" placeholder="New password if resetting" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
            </div>

            <div class="pt-4 flex items-center justify-end space-x-3">
                <button type="button" onclick="closeModal('editTeacherModal')" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-semibold text-xs">
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
    function openEditTeacherModal(t) {
        document.getElementById('edit_teacher_id').value = t.id;
        document.getElementById('edit_user_id').value = t.user_id;
        document.getElementById('edit_teacher_code').value = t.teacher_code;
        document.getElementById('edit_full_name').value = t.full_name;
        document.getElementById('edit_email').value = t.email;
        document.getElementById('edit_phone').value = t.phone || '';
        document.getElementById('edit_department').value = t.department;
        document.getElementById('edit_designation').value = t.designation || '';
        document.getElementById('edit_qualification').value = t.qualification || '';
        document.getElementById('edit_assigned_class').value = t.assigned_class || '';
        document.getElementById('edit_status').value = t.status || 'approved';
        openModal('editTeacherModal');
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
