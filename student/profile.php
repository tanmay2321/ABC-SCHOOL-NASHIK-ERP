<?php
/**
 * Student Personal Profile & Digital Identity
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('student');

$student_user_id = $_SESSION['user_id'];
$flash = get_flash();
$error = '';

// Fetch student and user details
$sStmt = $pdo->prepare("
    SELECT s.*, u.username, u.email 
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?
");
$sStmt->execute([$student_user_id]);
$student = $sStmt->fetch();

if (!$student) {
    die("Student profile record not found.");
}

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Update Personal & Guardian Info
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $gender = trim($_POST['gender'] ?? 'Male');
        $blood_group = trim($_POST['blood_group'] ?? '');
        $guardian_name = trim($_POST['guardian_name'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($full_name)) {
            set_flash('danger', 'Full name is required.');
        } else {
            $upd = $pdo->prepare("
                UPDATE students 
                SET full_name = ?, phone = ?, dob = ?, gender = ?, blood_group = ?, guardian_name = ?, address = ?
                WHERE id = ?
            ");
            $upd->execute([$full_name, $phone, $dob, $gender, $blood_group, $guardian_name, $address, $student['id']]);
            $_SESSION['full_name'] = $full_name;
            set_flash('success', 'Student profile details updated successfully!');
            header("Location: /student/profile.php");
            exit();
        }
    }

    // 2. Upload Student Photo
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
                if (!empty($student['photo'])) {
                    $old = __DIR__ . '/../' . ltrim($student['photo'], '/');
                    if (file_exists($old)) @unlink($old);
                }

                $filename = 'student_' . $student['id'] . '_' . time() . '.' . $ext;
                $dest = $dir . '/' . $filename;

                if (move_uploaded_file($tmp, $dest)) {
                    $photo_path = '/uploads/profiles/' . $filename;
                    $pdo->prepare("UPDATE students SET photo = ? WHERE id = ?")->execute([$photo_path, $student['id']]);
                    $_SESSION['photo'] = $photo_path;
                    set_flash('success', 'Profile photo updated successfully! It will now appear on your digital student ID and report card.');
                } else {
                    set_flash('danger', 'Failed to upload photo.');
                }
            } else {
                set_flash('danger', 'Invalid file type. Allowed formats: JPG, PNG, WEBP.');
            }
        } else {
            set_flash('danger', 'Please choose a photo file to upload.');
        }
        header("Location: /student/profile.php");
        exit();
    }

    // 3. Remove Photo
    if ($action === 'remove_photo') {
        if (!empty($student['photo'])) {
            $old = __DIR__ . '/../' . ltrim($student['photo'], '/');
            if (file_exists($old)) @unlink($old);
            $pdo->prepare("UPDATE students SET photo = NULL WHERE id = ?")->execute([$student['id']]);
            $_SESSION['photo'] = null;
            set_flash('success', 'Profile photo removed.');
        }
        header("Location: /student/profile.php");
        exit();
    }

    // 4. Change Password
    if ($action === 'change_password') {
        $cur_pass = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $conf_pass = $_POST['confirm_password'] ?? '';

        $uStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $uStmt->execute([$student_user_id]);
        $hash = $uStmt->fetchColumn();

        if (!password_verify($cur_pass, $hash)) {
            set_flash('danger', 'Incorrect current password. Password was not changed.');
        } elseif (strlen($new_pass) < 6) {
            set_flash('danger', 'New password must be at least 6 characters long.');
        } elseif ($new_pass !== $conf_pass) {
            set_flash('danger', 'New password and confirmation do not match.');
        } else {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$new_hash, $student_user_id]);
            set_flash('success', 'Password updated successfully! Please use your new password next time you login.');
        }
        header("Location: /student/profile.php");
        exit();
    }
}

$page_title = 'My Student Profile & ID Card';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 mb-6 no-print">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">
                <span>Student Digital Identity</span>
                <span>&bull;</span>
                <span class="text-slate-400">Class: <?= htmlspecialchars($student['class']) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                My Student Profile &amp; ID Card
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Official institutional profile record maintained at Bellwether International School Nandurbar.
            </p>
        </div>

        <div class="flex items-center space-x-3">
            <span class="px-3.5 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center space-x-1.5">
                <i class="fa-solid fa-graduation-cap text-emerald-600"></i>
                <span>Roll: <?= htmlspecialchars($student['roll_no'] ?: 'N/A') ?></span>
            </span>
            <span class="px-3.5 py-1.5 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-xs font-mono font-bold">
                <?= htmlspecialchars($student['student_code']) ?>
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

        <!-- Column 1: Digital School ID Card -->
        <div class="space-y-6">

            <!-- Official Student ID Card Component -->
            <div class="bg-gradient-to-br from-navy-950 via-navy-900 to-indigo-950 rounded-3xl p-6 text-white shadow-xl relative overflow-hidden border border-slate-700">
                <!-- Background design watermark -->
                <div class="absolute -right-6 -bottom-6 w-32 h-32 rounded-full bg-white/5 pointer-events-none"></div>

                <div class="flex items-center justify-between pb-4 border-b border-white/10 mb-5">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-xl bg-amber-400/20 text-amber-400 flex items-center justify-center font-bold text-sm">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold tracking-widest text-amber-400 uppercase leading-none">BELLWETHER INTL</p>
                            <p class="text-[8px] text-slate-300 uppercase tracking-wider mt-0.5">STUDENT IDENTITY CARD</p>
                        </div>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        AY 2026-27
                    </span>
                </div>

                <div class="text-center mb-4">
                    <div class="relative w-24 h-24 mx-auto mb-3">
                        <?php if (!empty($student['photo']) && file_exists(__DIR__ . '/../' . ltrim($student['photo'], '/'))): ?>
                            <img src="<?= htmlspecialchars($student['photo']) ?>" alt="Student Photo" 
                                 class="w-24 h-24 rounded-2xl object-cover border-2 border-amber-400 shadow-md mx-auto">
                        <?php else: ?>
                            <div class="w-24 h-24 rounded-2xl bg-white/10 border-2 border-dashed border-white/20 text-white font-bold text-3xl flex items-center justify-center mx-auto shadow-inner">
                                <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>

                        <button type="button" onclick="openModal('photoModal')" class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-amber-400 hover:bg-amber-500 text-slate-900 shadow-md flex items-center justify-center transition" title="Upload Photo">
                            <i class="fa-solid fa-camera text-xs"></i>
                        </button>
                    </div>

                    <h2 class="text-base font-bold font-['Outfit'] text-white"><?= htmlspecialchars($student['full_name']) ?></h2>
                    <p class="text-xs font-mono text-amber-400 font-bold"><?= htmlspecialchars($student['student_code']) ?></p>
                </div>

                <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-3 text-xs space-y-1.5 border border-white/10">
                    <div class="flex justify-between">
                        <span class="text-slate-300 text-[11px]">Class:</span>
                        <strong class="text-white"><?= htmlspecialchars($student['class']) ?></strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-300 text-[11px]">Roll No:</span>
                        <strong class="text-white"><?= htmlspecialchars($student['roll_no'] ?: 'N/A') ?></strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-300 text-[11px]">Blood Group:</span>
                        <strong class="text-rose-300 font-bold"><?= htmlspecialchars($student['blood_group'] ?: 'N/A') ?></strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-300 text-[11px]">Guardian:</span>
                        <strong class="text-white truncate max-w-[140px]"><?= htmlspecialchars($student['guardian_name'] ?: 'N/A') ?></strong>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-white/10 flex items-center justify-between text-[10px] text-slate-400">
                    <span>Nandurbar Campus</span>
                    <span class="text-emerald-400 font-semibold"><i class="fa-solid fa-circle-check mr-1"></i> Verified</span>
                </div>
            </div>

            <!-- Upload / Manage Photo Buttons -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 text-center">
                <p class="text-xs font-bold text-slate-800 mb-1">Passport Profile Photo</p>
                <p class="text-[11px] text-slate-500 mb-4">Required for ID card and official CBSE report cards.</p>

                <div class="flex items-center justify-center space-x-2">
                    <button type="button" onclick="openModal('photoModal')" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-sm">
                        <i class="fa-solid fa-upload mr-1.5"></i> <?= !empty($student['photo']) ? 'Change Photo' : 'Upload Photo' ?>
                    </button>
                    <?php if (!empty($student['photo'])): ?>
                        <form method="POST" action="profile.php" onsubmit="return confirm('Remove your student photo?');" class="inline">
                            <input type="hidden" name="action" value="remove_photo">
                            <button type="submit" class="px-3 py-2 rounded-xl text-rose-600 hover:bg-rose-50 text-xs font-semibold transition" title="Remove Photo">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Column 2 & 3: Detailed Student Form & Security -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Student Profile Form -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8">
                <div class="pb-4 border-b border-slate-100 mb-6">
                    <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">Student Information</h3>
                    <p class="text-xs text-slate-500">Keep your personal and emergency contact information up to date</p>
                </div>

                <form method="POST" action="profile.php" class="space-y-4">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Full Name *</label>
                            <input type="text" name="full_name" required value="<?= htmlspecialchars($student['full_name']) ?>" 
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Emergency Contact Phone *</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($student['phone'] ?? '') ?>" placeholder="+91 98XXX XXXXX"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Date of Birth</label>
                            <input type="date" name="dob" value="<?= htmlspecialchars($student['dob'] ?? '') ?>"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Gender</label>
                            <select name="gender" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm text-slate-800 focus:outline-none">
                                <option value="Male" <?= ($student['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($student['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= ($student['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Blood Group</label>
                            <select name="blood_group" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm text-slate-800 focus:outline-none">
                                <option value="">Select...</option>
                                <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                    <option value="<?= $bg ?>" <?= ($student['blood_group'] === $bg) ? 'selected' : '' ?>><?= $bg ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Guardian / Parent Name</label>
                        <input type="text" name="guardian_name" value="<?= htmlspecialchars($student['guardian_name'] ?? '') ?>" placeholder="e.g. Ramesh Patil"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Residential Address (Nandurbar)</label>
                        <textarea name="address" rows="2" placeholder="e.g. Plot No. 12, Old Nalwa Road, Nandurbar - 425412"
                                  class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600"><?= htmlspecialchars($student['address'] ?? '') ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Enrolled Class (Read-Only)</label>
                            <input type="text" readonly value="<?= htmlspecialchars($student['class']) ?>" 
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-500 text-sm font-bold cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Student Code (Read-Only)</label>
                            <input type="text" readonly value="<?= htmlspecialchars($student['student_code']) ?>" 
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-500 font-mono text-sm cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Login Email (Read-Only)</label>
                            <input type="text" readonly value="<?= htmlspecialchars($student['email']) ?>" 
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
                    <p class="text-xs text-slate-500">Ensure your student account password is kept private</p>
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
            <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Upload Student Photo</h3>
            <button type="button" onclick="closeModal('photoModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="profile.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="upload_photo">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Choose Photo (JPG, PNG, WEBP) *</label>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required
                       class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer">
                <p class="text-[11px] text-slate-400 mt-1.5">Recommended: Square passport photo for official school records.</p>
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
