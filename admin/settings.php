<?php
/**
 * Admin: System Settings, Branding & Principal Profile
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('admin');

$current_user = current_user();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Update School Branding & Logo
    if ($action === 'update_branding') {
        $school_name = trim($_POST['school_name'] ?? '');
        $school_city = trim($_POST['school_city'] ?? 'Nandurbar');
        $school_affiliation = trim($_POST['school_affiliation'] ?? '');
        $school_address = trim($_POST['school_address'] ?? '');
        $school_phone = trim($_POST['school_phone'] ?? '');
        $school_email = trim($_POST['school_email'] ?? '');

        if (!empty($school_name)) {
            set_setting($pdo, 'school_name', $school_name);
            set_setting($pdo, 'school_city', $school_city);
            set_setting($pdo, 'school_affiliation', $school_affiliation);
            set_setting($pdo, 'school_address', $school_address);
            set_setting($pdo, 'school_phone', $school_phone);
            set_setting($pdo, 'school_email', $school_email);
        }

        // Handle Logo Upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['logo']['tmp_name'];
            $orig_name = $_FILES['logo']['name'];
            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

            if (in_array($ext, $allowed)) {
                $logo_dir = __DIR__ . '/../uploads/logo';
                if (!is_dir($logo_dir)) {
                    @mkdir($logo_dir, 0777, true);
                }

                // Delete older logo file if exists
                $old_logo = get_setting($pdo, 'school_logo', '');
                if (!empty($old_logo)) {
                    $old_file = __DIR__ . '/../' . ltrim($old_logo, '/');
                    if (file_exists($old_file)) {
                        @unlink($old_file);
                    }
                }

                $new_filename = 'logo_' . time() . '.' . $ext;
                $dest = $logo_dir . '/' . $new_filename;
                if (move_uploaded_file($tmp, $dest)) {
                    set_setting($pdo, 'school_logo', '/uploads/logo/' . $new_filename);
                    set_flash('success', 'School branding and logo updated successfully! It will now appear across all headers, receipts, and documents.');
                } else {
                    set_flash('danger', 'Failed to save uploaded logo file.');
                }
            } else {
                set_flash('danger', 'Invalid logo format. Allowed types: PNG, JPG, JPEG, WEBP, SVG.');
            }
        } else {
            set_flash('success', 'School details updated successfully!');
        }

        header("Location: /admin/settings.php");
        exit();
    }

    // 2. Remove Logo
    if ($action === 'remove_logo') {
        $old_logo = get_setting($pdo, 'school_logo', '');
        if (!empty($old_logo)) {
            $old_file = __DIR__ . '/../' . ltrim($old_logo, '/');
            if (file_exists($old_file)) {
                @unlink($old_file);
            }
            set_setting($pdo, 'school_logo', '');
            set_flash('info', 'School logo removed. System default badge will be displayed.');
        }
        header("Location: /admin/settings.php");
        exit();
    }

    // 3. Update Principal / Admin Profile
    if ($action === 'update_profile') {
        $admin_name = trim($_POST['admin_name'] ?? '');
        $admin_designation = trim($_POST['admin_designation'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($admin_name) || empty($email)) {
            set_flash('danger', 'Administrator name and email cannot be empty.');
            header("Location: /admin/settings.php");
            exit();
        }

        // Verify email uniqueness against other users
        $chk = $pdo->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ? LIMIT 1");
        $chk->execute([$email, $email, $current_user['id']]);
        if ($chk->fetch()) {
            set_flash('danger', 'That email or username is already taken by another account.');
            header("Location: /admin/settings.php");
            exit();
        }

        set_setting($pdo, 'admin_name', $admin_name);
        set_setting($pdo, 'admin_designation', $admin_designation);
        $_SESSION['full_name'] = $admin_name;
        $_SESSION['email'] = $email;

        // Check if password update is requested
        if (!empty($password)) {
            if ($password !== $confirm_password) {
                set_flash('danger', 'New password and confirmation do not match.');
                header("Location: /admin/settings.php");
                exit();
            }
            if (strlen($password) < 6) {
                set_flash('danger', 'Password must be at least 6 characters long.');
                header("Location: /admin/settings.php");
                exit();
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $upd = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $upd->execute([$email, $email, $hash, $current_user['id']]);
            set_flash('success', 'Admin Profile & Login Password updated successfully!');
        } else {
            $upd = $pdo->prepare("UPDATE users SET username = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $upd->execute([$email, $email, $current_user['id']]);
            set_flash('success', 'Principal & Administrator Profile updated successfully!');
        }

        header("Location: /admin/settings.php");
        exit();
    }
}

// Load current values
$school_name = get_setting($pdo, 'school_name', 'BELLWETHER INTERNATIONAL SCHOOL NANDURBAR');
$school_city = get_setting($pdo, 'school_city', 'Nandurbar');
$school_affiliation = get_setting($pdo, 'school_affiliation', 'CBSE Board Affiliation #113042');
$school_address = get_setting($pdo, 'school_address', 'Old Nalwa Road, Nandurbar - 425412');
$school_phone = get_setting($pdo, 'school_phone', '+91 6262848446');
$school_email = get_setting($pdo, 'school_email', 'bwis.nandurbar@gmail.com');
$school_logo = get_setting($pdo, 'school_logo', '');
$admin_name = get_setting($pdo, 'admin_name', 'Principal / Head Admin');
$admin_designation = get_setting($pdo, 'admin_designation', 'Principal & Executive Administrator');

$flash = get_flash();
$page_title = 'ERP System Settings & Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <div class="mb-8">
        <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
            <i class="fa-solid fa-sliders"></i>
            <span>Configuration &amp; Administration</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
            School ERP Settings &amp; Profile
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
            Manage school logo branding, institution profile, and Head Administrator / Principal credentials.
        </p>
    </div>

    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl text-xs font-semibold bg-<?= $flash['type'] === 'danger' ? 'rose' : ($flash['type'] === 'info' ? 'blue' : 'emerald') ?>-50 border border-<?= $flash['type'] === 'danger' ? 'rose' : ($flash['type'] === 'info' ? 'blue' : 'emerald') ?>-200 text-<?= $flash['type'] === 'danger' ? 'rose' : ($flash['type'] === 'info' ? 'blue' : 'emerald') ?>-800 flex items-center space-x-2.5 shadow-xs">
            <i class="fa-solid <?= $flash['type'] === 'danger' ? 'fa-circle-xmark' : 'fa-circle-check' ?> text-base"></i>
            <span><?= htmlspecialchars($flash['message']) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left 2 Cols: Branding & Principal Profile -->
        <div class="lg:col-span-2 space-y-8">

            <!-- 1. School Logo & Branding Form -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 font-['Outfit'] flex items-center">
                            <i class="fa-solid fa-stamp text-amber-500 mr-2"></i>
                            School Logo &amp; Identity
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">This logo appears in navigation, official fee receipts, report cards, and notices.</p>
                    </div>
                </div>

                <form method="POST" action="settings.php" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="action" value="update_branding">

                    <!-- Logo Preview and Upload Section -->
                    <div class="p-5 rounded-2xl bg-slate-50/70 border border-slate-200 flex flex-col sm:flex-row items-center gap-6">
                        <div class="w-24 h-24 rounded-2xl bg-white border border-slate-300 shadow-inner flex items-center justify-center p-2 shrink-0">
                            <?php if (!empty($school_logo) && file_exists(__DIR__ . '/../' . ltrim($school_logo, '/'))): ?>
                                <img src="<?= htmlspecialchars($school_logo) ?>" alt="School Logo" class="w-full h-full object-contain">
                            <?php else: ?>
                                <div class="text-center text-slate-400">
                                    <i class="fa-solid fa-school text-3xl mb-1 text-slate-300"></i>
                                    <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Default</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex-1 space-y-2 text-center sm:text-left">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Upload New School Logo
                            </label>
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                   class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-navy-900 file:text-white hover:file:bg-navy-800 file:cursor-pointer transition">
                            <p class="text-[11px] text-slate-400">Supported formats: PNG, JPG, WEBP, SVG (Square or transparent PNG recommended).</p>
                        </div>

                        <?php if (!empty($school_logo)): ?>
                            <div>
                                <button type="submit" form="removeLogoForm" class="px-3 py-2 rounded-xl border border-rose-200 text-rose-600 hover:bg-rose-50 text-xs font-bold transition flex items-center shrink-0">
                                    <i class="fa-solid fa-trash-can mr-1.5"></i> Remove
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Institution Information Inputs -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Institution Name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="school_name" required value="<?= htmlspecialchars($school_name) ?>" 
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Campus City / Location <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="school_city" required value="<?= htmlspecialchars($school_city) ?>" 
                                   placeholder="e.g. Nandurbar"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Board Affiliation Details
                            </label>
                            <input type="text" name="school_affiliation" value="<?= htmlspecialchars($school_affiliation) ?>" 
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Official Contact Phone
                            </label>
                            <input type="text" name="school_phone" value="<?= htmlspecialchars($school_phone) ?>" 
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Official School Email
                            </label>
                            <input type="email" name="school_email" value="<?= htmlspecialchars($school_email) ?>" 
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Campus Physical Address
                            </label>
                            <input type="text" name="school_address" value="<?= htmlspecialchars($school_address) ?>" 
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-navy-900 hover:bg-navy-800 text-white font-bold text-xs shadow-md shadow-blue-900/10 transition">
                            <i class="fa-solid fa-floppy-disk mr-1.5 text-amber-400"></i> Save School Identity &amp; Logo
                        </button>
                    </div>
                </form>

                <!-- Hidden form for removing logo -->
                <form id="removeLogoForm" method="POST" action="settings.php" onsubmit="return confirm('Remove current logo and restore default badge?');">
                    <input type="hidden" name="action" value="remove_logo">
                </form>
            </div>

            <!-- 2. Principal / Administrator Profile Section -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 font-['Outfit'] flex items-center">
                            <i class="fa-solid fa-user-tie text-blue-600 mr-2"></i>
                            Head Administrator &amp; Principal Profile
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">Configure the administrator's official display name, designation, and login credentials.</p>
                    </div>
                </div>

                <form method="POST" action="settings.php" class="space-y-4 text-xs">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Full Name of Head Admin / Principal <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="admin_name" required value="<?= htmlspecialchars($admin_name) ?>" 
                                   placeholder="e.g. Dr. Rajesh K. Sharma"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Official Designation / Title
                            </label>
                            <input type="text" name="admin_designation" value="<?= htmlspecialchars($admin_designation) ?>" 
                                   placeholder="e.g. Principal &amp; Academic Director"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Admin Login Email / Username <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="email" required value="<?= htmlspecialchars($current_user['email']) ?>" 
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                            <p class="text-[11px] text-slate-400 mt-1">This email/username is used to sign in to the Admin Dashboard.</p>
                        </div>

                        <!-- Change Password Block -->
                        <div>
                            <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                New Password (Leave blank to keep unchanged)
                            </label>
                            <input type="password" name="new_password" placeholder="••••••••" 
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Confirm New Password
                            </label>
                            <input type="password" name="confirm_password" placeholder="••••••••" 
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md shadow-blue-500/10 transition">
                            <i class="fa-solid fa-user-check mr-1.5"></i> Update Administrator Profile
                        </button>
                    </div>
                </form>
            </div>

        </div>

        <!-- Right Col: System Status & Admission Policy -->
        <div class="space-y-6">
            
            <!-- Quick Profile Card -->
            <div class="bg-gradient-to-br from-navy-950 to-navy-800 text-white rounded-3xl p-6 shadow-md space-y-4">
                <div class="flex items-center space-x-3">
                    <div class="w-14 h-14 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-xl text-amber-400 shadow-inner">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-base font-['Outfit']"><?= htmlspecialchars($admin_name) ?></h4>
                        <p class="text-xs text-slate-300"><?= htmlspecialchars($admin_designation) ?></p>
                        <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-400/20 text-amber-300 border border-amber-400/30">
                            Super Admin Access
                        </span>
                    </div>
                </div>
                <div class="text-xs text-slate-300 border-t border-white/10 pt-3 space-y-1">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Login ID:</span>
                        <span class="font-mono font-semibold"><?= htmlspecialchars($current_user['email']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Security Level:</span>
                        <span class="text-emerald-400 font-bold">Encrypted BCrypt</span>
                    </div>
                </div>
            </div>

            <!-- Admission Policy -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm space-y-4 text-xs">
                <h3 class="text-sm font-bold text-slate-900 font-['Outfit'] border-b border-slate-100 pb-2">
                    Admission Gate Policy
                </h3>
                <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 space-y-1.5">
                    <p class="font-bold flex items-center">
                        <i class="fa-solid fa-user-lock mr-2 text-amber-600"></i>
                        Verification Required
                    </p>
                    <p class="text-slate-600 leading-relaxed text-[11px]">
                        Students who register on the public portal are placed into <code>pending</code> status. They cannot access student dashboards until approved in <strong>Student Approvals</strong>.
                    </p>
                </div>
            </div>

            <!-- System Information -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm space-y-4 text-xs">
                <h3 class="text-sm font-bold text-slate-900 font-['Outfit'] border-b border-slate-100 pb-2">
                    System Environment
                </h3>
                <div class="space-y-2">
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-slate-500">Backend Engine</span>
                        <span class="font-bold text-navy-900">PHP 8.2 (PDO)</span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-slate-500">Database</span>
                        <span class="font-bold text-navy-900">SQLite 3</span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-slate-500">Session Cookie</span>
                        <span class="font-bold text-emerald-600">Strict (Lifetime: 0)</span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-slate-500">Auto-Timeout</span>
                        <span class="font-bold text-blue-600">30 Minutes</span>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100">
                    <a href="/logout.php" class="w-full py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs flex items-center justify-center transition">
                        <i class="fa-solid fa-power-off mr-2"></i> Log Out
                    </a>
                </div>
            </div>

        </div>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
