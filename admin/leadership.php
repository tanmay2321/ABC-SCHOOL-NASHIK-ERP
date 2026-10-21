<?php
/**
 * Admin: Institutional Leadership Roles Management
 * Manage separate accounts & permissions for Principal and Vice Principal
 * Bellwether International School Nandurbar ERP System
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('admin');

$flash = get_flash();
$page_title = 'Leadership Profiles (Principal & VP)';

// Fetch current Principal user account
$stmt_p = $pdo->prepare("SELECT * FROM users WHERE role = 'principal' LIMIT 1");
$stmt_p->execute();
$principal_user = $stmt_p->fetch();

// Fetch current Vice Principal user account
$stmt_vp = $pdo->prepare("SELECT * FROM users WHERE role = 'vice_principal' LIMIT 1");
$stmt_vp->execute();
$vp_user = $stmt_vp->fetch();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Save / Update Principal Profile
    if ($action === 'save_principal') {
        $p_name = trim($_POST['principal_name'] ?? '');
        $p_designation = trim($_POST['principal_designation'] ?? 'Principal');
        $p_email = trim($_POST['email'] ?? '');
        $p_password = $_POST['password'] ?? '';
        $p_status = $_POST['status'] ?? 'approved';

        if (empty($p_name) || empty($p_email)) {
            set_flash('danger', 'Principal name and email/username are required.');
            header("Location: /admin/leadership.php");
            exit;
        }

        // Check if email taken by another user
        $chk = $pdo->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND role != 'principal' LIMIT 1");
        $chk->execute([$p_email, $p_email]);
        if ($chk->fetch()) {
            set_flash('danger', 'Email or username already in use by another account.');
            header("Location: /admin/leadership.php");
            exit;
        }

        set_setting($pdo, 'principal_name', $p_name);
        set_setting($pdo, 'principal_designation', $p_designation);

        if ($principal_user) {
            if (!empty($p_password)) {
                if (strlen($p_password) < 6) {
                    set_flash('danger', 'Password must be at least 6 characters.');
                    header("Location: /admin/leadership.php");
                    exit;
                }
                $hash = password_hash($p_password, PASSWORD_BCRYPT);
                $upd = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $upd->execute([$p_email, $p_email, $hash, $p_status, $principal_user['id']]);
            } else {
                $upd = $pdo->prepare("UPDATE users SET username = ?, email = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $upd->execute([$p_email, $p_email, $p_status, $principal_user['id']]);
            }
            set_flash('success', "Principal leadership profile and login account updated successfully!");
        } else {
            if (empty($p_password)) {
                $p_password = 'Principal@1234';
            }
            $hash = password_hash($p_password, PASSWORD_BCRYPT);
            $ins = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'principal', ?)");
            $ins->execute([$p_email, $p_email, $hash, $p_status]);
            set_flash('success', "Principal account created! Login: {$p_email} / Password: {$p_password}");
        }

        header("Location: /admin/leadership.php");
        exit;
    }

    // 2. Save / Update Vice Principal Profile
    if ($action === 'save_vice_principal') {
        $vp_name = trim($_POST['vice_principal_name'] ?? '');
        $vp_designation = trim($_POST['vice_principal_designation'] ?? 'Vice Principal');
        $vp_email = trim($_POST['email'] ?? '');
        $vp_password = $_POST['password'] ?? '';
        $vp_status = $_POST['status'] ?? 'approved';

        if (empty($vp_name) || empty($vp_email)) {
            set_flash('danger', 'Vice Principal name and email/username are required.');
            header("Location: /admin/leadership.php");
            exit;
        }

        // Check if email taken by another user
        $chk = $pdo->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND role != 'vice_principal' LIMIT 1");
        $chk->execute([$vp_email, $vp_email]);
        if ($chk->fetch()) {
            set_flash('danger', 'Email or username already in use by another account.');
            header("Location: /admin/leadership.php");
            exit;
        }

        set_setting($pdo, 'vice_principal_name', $vp_name);
        set_setting($pdo, 'vice_principal_designation', $vp_designation);

        if ($vp_user) {
            if (!empty($vp_password)) {
                if (strlen($vp_password) < 6) {
                    set_flash('danger', 'Password must be at least 6 characters.');
                    header("Location: /admin/leadership.php");
                    exit;
                }
                $hash = password_hash($vp_password, PASSWORD_BCRYPT);
                $upd = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $upd->execute([$vp_email, $vp_email, $hash, $vp_status, $vp_user['id']]);
            } else {
                $upd = $pdo->prepare("UPDATE users SET username = ?, email = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $upd->execute([$vp_email, $vp_email, $vp_status, $vp_user['id']]);
            }
            set_flash('success', "Vice Principal profile and login account updated successfully!");
        } else {
            if (empty($vp_password)) {
                $vp_password = 'VicePrincipal@1234';
            }
            $hash = password_hash($vp_password, PASSWORD_BCRYPT);
            $ins = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'vice_principal', ?)");
            $ins->execute([$vp_email, $vp_email, $hash, $vp_status]);
            set_flash('success', "Vice Principal account created! Login: {$vp_email} / Password: {$vp_password}");
        }

        header("Location: /admin/leadership.php");
        exit;
    }
}

// Current saved display names
$p_display_name = get_setting($pdo, 'principal_name', 'Dr. R. K. Sharma');
$p_display_desig = get_setting($pdo, 'principal_designation', 'Principal & Academic Director');
$vp_display_name = get_setting($pdo, 'vice_principal_name', 'Prof. S. M. Deshmukh');
$vp_display_desig = get_setting($pdo, 'vice_principal_designation', 'Vice Principal (Administration & Academics)');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200 mb-6">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-purple-600 uppercase tracking-wider mb-1">
                <i class="fa-solid fa-user-shield"></i>
                <span>Access Control &amp; Authority Hierarchy</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Institutional Leadership Profiles
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Create and manage dedicated credentials for the <strong>Principal</strong> and <strong>Vice Principal</strong> with tailored, role-limited authority.
            </p>
        </div>

        <div>
            <span class="inline-flex items-center px-3.5 py-1.5 rounded-xl bg-purple-50 border border-purple-200 text-purple-800 text-xs font-bold shadow-xs">
                <i class="fa-solid fa-lock mr-1.5"></i> Super Admin Control Only
            </span>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl bg-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-50 border border-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-200 text-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-900 text-xs font-semibold flex items-center justify-between auto-dismiss-alert">
            <div class="flex items-center space-x-2.5">
                <i class="fa-solid <?= $flash['type'] === 'danger' ? 'fa-circle-xmark text-rose-600' : 'fa-circle-check text-emerald-600' ?> text-base"></i>
                <span><?= $flash['message'] ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Role Permission Matrix Comparison -->
    <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/80 shadow-sm mb-8">
        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-3 flex items-center">
            <i class="fa-solid fa-layer-group text-purple-600 mr-2"></i>
            Role &amp; Permission Access Matrix
        </h3>
        <div class="overflow-x-auto rounded-2xl border border-slate-200">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-3.5">ERP Capability / Module</th>
                        <th class="py-3 px-3.5 text-center bg-purple-50 text-purple-900">Admin (Super Admin)</th>
                        <th class="py-3 px-3.5 text-center bg-amber-50 text-amber-900">Principal</th>
                        <th class="py-3 px-3.5 text-center bg-teal-50 text-teal-900">Vice Principal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr>
                        <td class="py-2.5 px-3.5 font-semibold text-slate-800">School-Wide Morning Roll Call &amp; Attendance</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-purple-50/20"><i class="fa-solid fa-check"></i> Full Override</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-amber-50/20"><i class="fa-solid fa-check"></i> Full Oversight</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-teal-50/20"><i class="fa-solid fa-check"></i> View &amp; Monitor</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 px-3.5 font-semibold text-slate-800">Homework &amp; Teacher Assignments Oversight</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-purple-50/20"><i class="fa-solid fa-check"></i> Full Control</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-amber-50/20"><i class="fa-solid fa-check"></i> Full Control</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-teal-50/20"><i class="fa-solid fa-check"></i> Inspect &amp; Review</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 px-3.5 font-semibold text-slate-800">Student Admissions &amp; ID Approvals</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-purple-50/20"><i class="fa-solid fa-check"></i> Approve/Reject</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-amber-50/20"><i class="fa-solid fa-check"></i> Approve/Reject</td>
                        <td class="py-2.5 px-3.5 text-center text-slate-400 bg-teal-50/20"><i class="fa-solid fa-eye mr-1"></i> Roster View Only</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 px-3.5 font-semibold text-slate-800">Leave Applications Approval (Teachers &amp; Students)</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-purple-50/20"><i class="fa-solid fa-check"></i> Final Authority</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-amber-50/20"><i class="fa-solid fa-check"></i> Approve/Reject</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-teal-50/20"><i class="fa-solid fa-check"></i> Review &amp; Recommend</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 px-3.5 font-semibold text-slate-800">Class Timetables &amp; Period Scheduling</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-purple-50/20"><i class="fa-solid fa-check"></i> Manage</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-amber-50/20"><i class="fa-solid fa-check"></i> Manage</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-teal-50/20"><i class="fa-solid fa-eye mr-1"></i> View</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 px-3.5 font-semibold text-slate-800">Fee Invoicing, Receipts &amp; Excel Export</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-purple-50/20"><i class="fa-solid fa-check"></i> Full Billing &amp; Delete</td>
                        <td class="py-2.5 px-3.5 text-center text-amber-600 font-bold bg-amber-50/20"><i class="fa-solid fa-eye mr-1"></i> Read-Only Collections</td>
                        <td class="py-2.5 px-3.5 text-center text-rose-500 font-bold bg-teal-50/20"><i class="fa-solid fa-xmark"></i> No Access</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 px-3.5 font-semibold text-slate-800">Faculty Hiring, Teachers Management &amp; Class Allotment</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-purple-50/20"><i class="fa-solid fa-check"></i> Add/Edit/Delete</td>
                        <td class="py-2.5 px-3.5 text-center text-slate-500 font-semibold bg-amber-50/20"><i class="fa-solid fa-eye mr-1"></i> Faculty Directory</td>
                        <td class="py-2.5 px-3.5 text-center text-rose-500 font-bold bg-teal-50/20"><i class="fa-solid fa-xmark"></i> No Access</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 px-3.5 font-semibold text-slate-800">System Settings, School Logo &amp; Database Explorer</td>
                        <td class="py-2.5 px-3.5 text-center text-emerald-600 font-bold bg-purple-50/20"><i class="fa-solid fa-check"></i> Exclusive Admin</td>
                        <td class="py-2.5 px-3.5 text-center text-rose-500 font-bold bg-amber-50/20"><i class="fa-solid fa-xmark"></i> Restricted</td>
                        <td class="py-2.5 px-3.5 text-center text-rose-500 font-bold bg-teal-50/20"><i class="fa-solid fa-xmark"></i> Restricted</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Management Forms Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- 1. Principal Profile Card & Form -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500 text-white flex items-center justify-center font-bold text-xl shadow-md shadow-amber-500/20">
                            <i class="fa-solid fa-award"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Principal Leadership Account</h3>
                            <p class="text-xs text-amber-600 font-semibold">Head of School &amp; Academic Leadership</p>
                        </div>
                    </div>

                    <?php if ($principal_user): ?>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $principal_user['status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                            <?= ucfirst($principal_user['status']) ?>
                        </span>
                    <?php else: ?>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500">
                            Not Configured
                        </span>
                    <?php endif; ?>
                </div>

                <form method="POST" action="leadership.php" class="space-y-4 text-xs">
                    <input type="hidden" name="action" value="save_principal">

                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Principal Full Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="principal_name" required value="<?= htmlspecialchars($p_display_name) ?>" 
                               placeholder="e.g. Dr. R. K. Sharma"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Official Title / Designation
                        </label>
                        <input type="text" name="principal_designation" value="<?= htmlspecialchars($p_display_desig) ?>" 
                               placeholder="e.g. Principal &amp; Academic Director"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Principal Login Email / Username <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="email" required value="<?= htmlspecialchars($principal_user['email'] ?? 'principal@edu.in') ?>" 
                               placeholder="principal@edu.in"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                            <?= $principal_user ? 'Change Password (Leave blank to keep unchanged)' : 'Set Login Password *' ?>
                        </label>
                        <input type="password" name="password" placeholder="<?= $principal_user ? '••••••••' : 'Enter login password (e.g. Principal@1234)' ?>" 
                               <?= $principal_user ? '' : 'required' ?>
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Account Status</label>
                        <select name="status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <option value="approved" <?= ($principal_user['status'] ?? 'approved') === 'approved' ? 'selected' : '' ?>>Active / Approved</option>
                            <option value="inactive" <?= ($principal_user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Suspended / Inactive</option>
                        </select>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-md shadow-amber-600/10 transition">
                            <i class="fa-solid fa-floppy-disk mr-1.5"></i> <?= $principal_user ? 'Update Principal Account' : 'Create Principal Profile' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2. Vice Principal Profile Card & Form -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 border border-slate-200/80 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 rounded-2xl bg-teal-600 text-white flex items-center justify-center font-bold text-xl shadow-md shadow-teal-600/20">
                            <i class="fa-solid fa-user-tie"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Vice Principal Leadership Account</h3>
                            <p class="text-xs text-teal-600 font-semibold">Academics, Attendance &amp; Operations</p>
                        </div>
                    </div>

                    <?php if ($vp_user): ?>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $vp_user['status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                            <?= ucfirst($vp_user['status']) ?>
                        </span>
                    <?php else: ?>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500">
                            Not Configured
                        </span>
                    <?php endif; ?>
                </div>

                <form method="POST" action="leadership.php" class="space-y-4 text-xs">
                    <input type="hidden" name="action" value="save_vice_principal">

                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Vice Principal Full Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="vice_principal_name" required value="<?= htmlspecialchars($vp_display_name) ?>" 
                               placeholder="e.g. Prof. S. M. Deshmukh"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-teal-600">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Official Title / Designation
                        </label>
                        <input type="text" name="vice_principal_designation" value="<?= htmlspecialchars($vp_display_desig) ?>" 
                               placeholder="e.g. Vice Principal (Academics &amp; Administration)"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-teal-600">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Vice Principal Login Email / Username <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="email" required value="<?= htmlspecialchars($vp_user['email'] ?? 'viceprincipal@edu.in') ?>" 
                               placeholder="viceprincipal@edu.in"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-teal-600">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                            <?= $vp_user ? 'Change Password (Leave blank to keep unchanged)' : 'Set Login Password *' ?>
                        </label>
                        <input type="password" name="password" placeholder="<?= $vp_user ? '••••••••' : 'Enter login password (e.g. VicePrincipal@1234)' ?>" 
                               <?= $vp_user ? '' : 'required' ?>
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-teal-600">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Account Status</label>
                        <select name="status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 focus:bg-white text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-teal-600">
                            <option value="approved" <?= ($vp_user['status'] ?? 'approved') === 'approved' ? 'selected' : '' ?>>Active / Approved</option>
                            <option value="inactive" <?= ($vp_user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Suspended / Inactive</option>
                        </select>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs shadow-md shadow-teal-600/10 transition">
                            <i class="fa-solid fa-floppy-disk mr-1.5"></i> <?= $vp_user ? 'Update Vice Principal Account' : 'Create Vice Principal Profile' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
