<?php
/**
 * Teacher Personal Profile & Identity Management
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('teacher');

$teacher_user_id = $_SESSION['user_id'];
$flash = get_flash();
$error = '';

// Fetch teacher and user details
$tStmt = $pdo->prepare("
    SELECT t.*, u.username, u.email 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    WHERE t.user_id = ?
");
$tStmt->execute([$teacher_user_id]);
$teacher = $tStmt->fetch();

if (!$teacher) {
    die("Teacher profile record not found.");
}

// Fetch subjects allocated to this teacher
$allocStmt = $pdo->prepare("
    SELECT * FROM subjects 
    WHERE teacher_id = ?
    ORDER BY class ASC, name ASC
");
$allocStmt->execute([$teacher['id']]);
$allocated_subjects = $allocStmt->fetchAll();

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Update Personal & Professional Info
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $gender = trim($_POST['gender'] ?? 'Male');

        if (empty($full_name)) {
            set_flash('danger', 'Full name is required.');
        } else {
            $upd = $pdo->prepare("
                UPDATE teachers 
                SET full_name = ?, phone = ?, department = ?, designation = ?, qualification = ?, gender = ?
                WHERE id = ?
            ");
            $upd->execute([$full_name, $phone, $department, $designation, $qualification, $gender, $teacher['id']]);
            $_SESSION['full_name'] = $full_name;
            set_flash('success', 'Profile details updated successfully!');
            header("Location: /teacher/profile.php");
            exit();
        }
    }

    // 2. Upload Profile Photo
    if ($action === 'upload_photo') {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['photo']['tmp_name'];
            $name = $_FILES['photo']['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($ext, $allowed)) {
                $dir = __DIR__ . '/../uploads/profiles';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }

                // Delete old photo if exists
                if (!empty($teacher['photo'])) {
                    $old = __DIR__ . '/../' . ltrim($teacher['photo'], '/');
                    if (file_exists($old)) @unlink($old);
                }

                $filename = 'teacher_' . $teacher['id'] . '_' . time() . '.' . $ext;
                $dest = $dir . '/' . $filename;

                if (move_uploaded_file($tmp, $dest)) {
                    $photo_path = '/uploads/profiles/' . $filename;
                    $pdo->prepare("UPDATE teachers SET photo = ? WHERE id = ?")->execute([$photo_path, $teacher['id']]);
                    $_SESSION['photo'] = $photo_path;
                    set_flash('success', 'Profile photo updated successfully!');
                } else {
                    set_flash('danger', 'Failed to upload photo.');
                }
            } else {
                set_flash('danger', 'Invalid file type. Allowed formats: JPG, PNG, WEBP.');
            }
        } else {
            set_flash('danger', 'Please choose a photo file to upload.');
        }
        header("Location: /teacher/profile.php");
        exit();
    }

    // 3. Remove Photo
    if ($action === 'remove_photo') {
        if (!empty($teacher['photo'])) {
            $old = __DIR__ . '/../' . ltrim($teacher['photo'], '/');
            if (file_exists($old)) @unlink($old);
            $pdo->prepare("UPDATE teachers SET photo = NULL WHERE id = ?")->execute([$teacher['id']]);
            $_SESSION['photo'] = null;
            set_flash('success', 'Profile photo removed.');
        }
        header("Location: /teacher/profile.php");
        exit();
    }

    // 4. Change Password
    if ($action === 'change_password') {
        $cur_pass = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $conf_pass = $_POST['confirm_password'] ?? '';

        $uStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $uStmt->execute([$teacher_user_id]);
        $hash = $uStmt->fetchColumn();

        if (!password_verify($cur_pass, $hash)) {
            set_flash('danger', 'Incorrect current password. Password was not changed.');
        } elseif (strlen($new_pass) < 6) {
            set_flash('danger', 'New password must be at least 6 characters long.');
        } elseif ($new_pass !== $conf_pass) {
            set_flash('danger', 'New password and confirmation do not match.');
        } else {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$new_hash, $teacher_user_id]);
            set_flash('success', 'Password updated successfully! Please use your new password next time you login.');
        }
        header("Location: /teacher/profile.php");
        exit();
    }
}

$page_title = 'Faculty Profile & Identity';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 mb-6 no-print">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
                <span>Faculty Profile</span>
                <span>&bull;</span>
                <span class="text-slate-400">Teacher ID: <?= htmlspecialchars($teacher['teacher_code']) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                My Teacher Profile &amp; ID Card
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Manage your faculty photo, personal contact details, allocated subjects, and account security.
            </p>
        </div>

        <div class="flex items-center space-x-3">
            <span class="px-3.5 py-1.5 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-xs font-bold flex items-center space-x-1.5">
                <i class="fa-solid fa-id-badge text-blue-600"></i>
                <span><?= htmlspecialchars($teacher['teacher_code']) ?></span>
            </span>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl bg-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-50 border border-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-200 text-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-900 text-xs font-semibold flex items-center justify-between shadow-xs auto-dismiss-alert no-print">
            <div class="flex items-center space-x-2.5">
                <i class="fa-solid <?= $flash['type'] === 'danger' ? 'fa-triangle-exclamation' : 'fa-circle-check' ?> text-base"></i>
                <span><?= $flash['message'] ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Column 1: Identity Card & Photo Upload -->
        <div class="space-y-6">

            <!-- Teacher Identity Card -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden p-6 text-center">
                <div class="relative w-28 h-28 mx-auto mb-4">
                    <?php if (!empty($teacher['photo']) && file_exists(__DIR__ . '/../' . ltrim($teacher['photo'], '/'))): ?>
                        <img src="<?= htmlspecialchars($teacher['photo']) ?>" alt="Teacher Photo" 
                             class="w-28 h-28 rounded-full object-cover border-4 border-amber-400 shadow-lg mx-auto">
                    <?php else: ?>
                        <div class="w-28 h-28 rounded-full bg-gradient-to-tr from-navy-900 to-blue-600 text-white font-bold text-4xl flex items-center justify-center border-4 border-slate-200 shadow-lg mx-auto">
                            <?= strtoupper(substr($teacher['full_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>

                    <button type="button" onclick="openModal('photoModal')" class="absolute bottom-0 right-0 w-9 h-9 rounded-full bg-navy-800 hover:bg-navy-900 text-amber-400 border-2 border-white shadow-md flex items-center justify-center transition" title="Change Photo">
                        <i class="fa-solid fa-camera text-sm"></i>
                    </button>
                </div>

                <h2 class="text-lg font-bold text-slate-900 font-['Outfit']"><?= htmlspecialchars($teacher['full_name']) ?></h2>
                <p class="text-xs text-blue-700 font-semibold"><?= htmlspecialchars($teacher['designation'] ?: 'Faculty Member') ?></p>
                <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($teacher['department']) ?> Department</p>

                <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-center gap-2 flex-wrap">
                    <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-[11px] font-semibold">
                        <i class="fa-solid fa-graduation-cap mr-1 text-slate-500"></i> <?= htmlspecialchars($teacher['qualification'] ?: 'Graduate') ?>
                    </span>
                    <?php if (!empty($teacher['assigned_class'])): ?>
                        <span class="px-3 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-bold">
                            ⭐ Class Teacher: <?= htmlspecialchars($teacher['assigned_class']) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="mt-5 flex items-center justify-center space-x-2">
                    <button type="button" onclick="openModal('photoModal')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                        <i class="fa-solid fa-upload mr-1.5 text-blue-600"></i> Upload Photo
                    </button>
                    <?php if (!empty($teacher['photo'])): ?>
                        <form method="POST" action="profile.php" onsubmit="return confirm('Remove your profile photo?');" class="inline">
                            <input type="hidden" name="action" value="remove_photo">
                            <button type="submit" class="px-3 py-2 rounded-xl text-rose-600 hover:bg-rose-50 text-xs font-semibold transition" title="Remove Photo">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Allocated Subjects Summary -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-sm font-bold text-slate-900 font-['Outfit'] flex items-center">
                        <i class="fa-solid fa-book-open mr-2 text-blue-600"></i>
                        Allocated Subjects
                    </h3>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 font-bold">
                        <?= count($allocated_subjects) ?>
                    </span>
                </div>

                <?php if (empty($allocated_subjects)): ?>
                    <p class="text-xs text-slate-400 py-4 text-center">No subjects allocated yet by Principal.</p>
                <?php else: ?>
                    <div class="space-y-2.5">
                        <?php foreach ($allocated_subjects as $sub): ?>
                            <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-bold text-slate-800 block"><?= htmlspecialchars($sub['name']) ?></span>
                                    <span class="text-[10px] text-slate-500 font-semibold"><?= htmlspecialchars($sub['class']) ?> &bull; Code: <?= htmlspecialchars($sub['code'] ?: 'N/A') ?></span>
                                </div>
                                <a href="/teacher/notes.php?class=<?= urlencode($sub['class']) ?>&subject=<?= urlencode($sub['name']) ?>" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-blue-600 hover:text-blue-800 text-[11px] font-bold shadow-2xs">
                                    Notes &rarr;
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Column 2 & 3: Detailed Information Form & Security -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Personal & Professional Information Form -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8">
                <div class="pb-4 border-b border-slate-100 mb-6">
                    <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">Faculty Details</h3>
                    <p class="text-xs text-slate-500">Update your contact information, qualification, and department details</p>
                </div>

                <form method="POST" action="profile.php" class="space-y-4">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Full Name *</label>
                            <input type="text" name="full_name" required value="<?= htmlspecialchars($teacher['full_name']) ?>" 
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Mobile Phone *</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($teacher['phone'] ?? '') ?>" placeholder="+91 98XXX XXXXX"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Department</label>
                            <select name="department" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                                <?php 
                                    $depts = ['Science', 'Mathematics', 'English', 'Social Science', 'Hindi', 'Computer Science', 'Physical Education', 'Arts & Music'];
                                    foreach ($depts as $d):
                                ?>
                                    <option value="<?= $d ?>" <?= ($teacher['department'] === $d) ? 'selected' : '' ?>><?= $d ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Designation</label>
                            <input type="text" name="designation" value="<?= htmlspecialchars($teacher['designation'] ?? '') ?>" placeholder="e.g. Senior PGT Teacher"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Qualification</label>
                            <input type="text" name="qualification" value="<?= htmlspecialchars($teacher['qualification'] ?? '') ?>" placeholder="e.g. M.Sc. B.Ed, PhD"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Gender</label>
                            <select name="gender" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm text-slate-800 focus:outline-none">
                                <option value="Male" <?= ($teacher['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($teacher['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= ($teacher['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Login Email (Read-Only)</label>
                            <input type="text" readonly value="<?= htmlspecialchars($teacher['email']) ?>" 
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-500 text-sm cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Username (Read-Only)</label>
                            <input type="text" readonly value="<?= htmlspecialchars($teacher['username']) ?>" 
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-500 text-sm cursor-not-allowed">
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md transition flex items-center space-x-2">
                            <i class="fa-solid fa-floppy-disk text-amber-400"></i>
                            <span>Save Profile Changes</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Security & Password Change -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8">
                <div class="pb-4 border-b border-slate-100 mb-6">
                    <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">Change Password</h3>
                    <p class="text-xs text-slate-500">Ensure your account uses a strong, private password</p>
                </div>

                <form method="POST" action="profile.php" class="space-y-4">
                    <input type="hidden" name="action" value="change_password">

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Current Password *</label>
                        <input type="password" name="current_password" required placeholder="Enter current password"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-navy-600">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">New Password *</label>
                            <input type="password" name="new_password" required minlength="6" placeholder="Min 6 characters"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-navy-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Confirm New Password *</label>
                            <input type="password" name="confirm_password" required minlength="6" placeholder="Re-enter new password"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-navy-600">
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs shadow-md transition flex items-center space-x-2">
                            <i class="fa-solid fa-key text-amber-400"></i>
                            <span>Update Password</span>
                        </button>
                    </div>
                </form>
            </div>

        </div>

    </div>

</main>

<!-- Upload Photo Modal -->
<div id="photoModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
            <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Upload Profile Photo</h3>
            <button type="button" onclick="closeModal('photoModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="profile.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="upload_photo">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Choose Photo (JPG, PNG, WEBP) *</label>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required
                       class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                <p class="text-[11px] text-slate-400 mt-1.5">Recommended: Square passport-style photo (max 5MB).</p>
            </div>

            <div class="pt-4 flex items-center justify-end space-x-3">
                <button type="button" onclick="closeModal('photoModal')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-semibold text-xs">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md">
                    Upload &amp; Save
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
