<?php
/**
 * Admin Dashboard - ABC School Nashik ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth(['admin', 'principal', 'vice_principal']);

// Handle Quick Approve / Reject action directly from dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target_user_id = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'];

    if ($target_user_id > 0) {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE id = :id AND role = 'student'");
            $stmt->execute(['id' => $target_user_id]);
            set_flash('success', 'Student registration approved successfully! Student can now log in.');
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = :id AND role = 'student'");
            $stmt->execute(['id' => $target_user_id]);
            set_flash('warning', 'Student registration has been rejected.');
        }
        header("Location: /admin/index.php");
        exit();
    }
}

// Fetch stats
$stats = get_school_stats($pdo);

// Fetch Pending Students awaiting approval
$pending_students = $pdo->query("
    SELECT u.id as user_id, u.email, u.status, u.created_at, s.id as student_id, s.student_code, s.full_name, s.class, s.roll_no, s.phone, s.guardian_name
    FROM users u
    JOIN students s ON u.id = s.user_id
    WHERE u.role = 'student' AND u.status = 'pending'
    ORDER BY u.created_at DESC
")->fetchAll();

// Fetch Recent Teachers
$recent_teachers = $pdo->query("
    SELECT t.*, u.email, u.status
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.id DESC LIMIT 4
")->fetchAll();

// Fetch Recent Notices
$recent_notices = $pdo->query("SELECT * FROM notices ORDER BY id DESC LIMIT 3")->fetchAll();

// Fetch Fee Accounting Metrics
$fee_stats = $pdo->query("
    SELECT 
        COALESCE(SUM(total_amount), 0) as total_invoiced,
        (SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments) as total_collected
    FROM fee_invoices
")->fetch();
$total_invoiced = floatval($fee_stats['total_invoiced'] ?? 0);
$total_collected = floatval($fee_stats['total_collected'] ?? 0);
$total_pending_fee = max(0, $total_invoiced - $total_collected);

// Fetch Pending Leaves
$pending_leaves_count = $pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status = 'Pending'")->fetchColumn();

// Fetch Today's Attendance & Assignments Overview
$today_date = date('Y-m-d');
$today_att = $pdo->prepare("
    SELECT 
        COUNT(*) as total_marked,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present
    FROM attendance WHERE date = ?
");
$today_att->execute([$today_date]);
$today_att_data = $today_att->fetch();
$today_marked_count = (int)($today_att_data['total_marked'] ?? 0);
$today_present_count = (int)($today_att_data['present'] ?? 0);

$total_asgn_count = (int)$pdo->query("SELECT COUNT(*) FROM assignments")->fetchColumn();
$total_subs_count = (int)$pdo->query("SELECT COUNT(*) FROM assignment_submissions")->fetchColumn();
?>

<?php 
$page_title = 'Admin Overview';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php'; 
?>

<!-- Main Content Area -->
<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">
    
    <!-- Header Greeting -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
                <span>Administrative Control Panel</span>
                <span>&bull;</span>
                <span class="text-slate-400"><?= date('l, d F Y') ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Welcome back, Administrator
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Full administrative authority over ABC School Nashik teachers, student admissions, and academic records.
            </p>
        </div>

        <!-- Quick Action Buttons -->
        <div class="flex items-center space-x-3">
            <a href="/admin/teachers.php?action=new" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-semibold text-xs shadow-md shadow-navy-900/10 transition">
                <i class="fa-solid fa-user-plus mr-2 text-amber-400"></i> Add Teacher ID
            </a>
            <a href="/admin/students.php" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-white hover:bg-slate-100 text-slate-700 font-semibold text-xs border border-slate-300 shadow-sm transition">
                <i class="fa-solid fa-users-viewfinder mr-2 text-blue-600"></i> Manage Students
            </a>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl bg-<?= $flash['type'] === 'success' ? 'emerald' : 'amber' ?>-50 border border-<?= $flash['type'] === 'success' ? 'emerald' : 'amber' ?>-200 text-<?= $flash['type'] === 'success' ? 'emerald' : 'amber' ?>-900 text-xs font-semibold flex items-center justify-between auto-dismiss-alert">
            <div class="flex items-center space-x-2.5">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-amber-600' ?> text-base"></i>
                <span><?= $flash['message'] ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        
        <!-- Total Teachers -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Faculty Teachers</span>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="fa-solid fa-chalkboard-user text-lg"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 font-['Outfit'] mt-3"><?= $stats['total_teachers'] ?></p>
            <div class="mt-2 flex items-center text-xs text-blue-600 font-medium">
                <a href="/admin/teachers.php" class="hover:underline flex items-center">
                    <span>Manage all teachers</span>
                    <i class="fa-solid fa-arrow-right ml-1 text-[10px]"></i>
                </a>
            </div>
        </div>

        <!-- Approved Students -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Approved Students</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="fa-solid fa-user-check text-lg"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 font-['Outfit'] mt-3"><?= $stats['approved_students'] ?></p>
            <div class="mt-2 flex items-center text-xs text-emerald-600 font-medium">
                <a href="/admin/students.php?status=approved" class="hover:underline flex items-center">
                    <span>View enrolled students</span>
                    <i class="fa-solid fa-arrow-right ml-1 text-[10px]"></i>
                </a>
            </div>
        </div>

        <!-- Pending Approvals (Crucial Highlight!) -->
        <div class="bg-white p-5 rounded-2xl border <?= $stats['pending_students'] > 0 ? 'border-amber-300 bg-amber-50/30' : 'border-slate-200' ?> shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-amber-600 tracking-wider">Pending Registrations</span>
                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
                    <i class="fa-solid fa-user-clock text-lg"></i>
                </div>
            </div>
            <div class="flex items-baseline space-x-2 mt-3">
                <p class="text-3xl font-extrabold text-slate-900 font-['Outfit']"><?= $stats['pending_students'] ?></p>
                <?php if ($stats['pending_students'] > 0): ?>
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-200 text-amber-900 animate-pulse">Action Required</span>
                <?php endif; ?>
            </div>
            <div class="mt-2 flex items-center text-xs text-amber-700 font-medium">
                <span>Cannot log in until approved</span>
            </div>
        </div>

        <!-- Active Circulars -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">School Notices</span>
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <i class="fa-solid fa-bullhorn text-lg"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 font-['Outfit'] mt-3"><?= $stats['total_notices'] ?></p>
            <div class="mt-2 flex items-center text-xs text-purple-600 font-medium">
                <a href="/admin/notices.php" class="hover:underline flex items-center">
                    <span>Manage notice board</span>
                    <i class="fa-solid fa-arrow-right ml-1 text-[10px]"></i>
                </a>
            </div>
        </div>

    </div>

    <!-- ERP Operations & Real-Time Oversight Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <!-- Fees Collected -->
        <div class="bg-gradient-to-br from-indigo-900 to-navy-900 p-5 rounded-2xl text-white shadow-md flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-indigo-200">
                    <span class="text-xs font-bold uppercase tracking-wider">Fee Collection</span>
                    <i class="fa-solid fa-receipt text-amber-400 text-lg"></i>
                </div>
                <p class="text-2xl font-black mt-2 font-['Outfit']">₹<?= number_format($total_collected) ?></p>
                <div class="mt-2 text-2xs text-indigo-200 flex justify-between">
                    <span>Invoiced: ₹<?= number_format($total_invoiced) ?></span>
                    <span class="text-rose-300 font-bold">Due: ₹<?= number_format($total_pending_fee) ?></span>
                </div>
            </div>
            <div class="mt-3 pt-2 border-t border-indigo-800/80">
                <a href="/admin/fees.php" class="text-xs font-bold text-amber-400 hover:text-amber-300 flex items-center">
                    <span>Manage Invoices &amp; Excel &rarr;</span>
                </a>
            </div>
        </div>

        <!-- School Attendance Oversight -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow transition flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Morning Roll Call</span>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <i class="fa-solid fa-clipboard-user text-lg"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mt-2">
                    <p class="text-2xl font-extrabold text-slate-900 font-['Outfit']"><?= $today_present_count ?></p>
                    <span class="text-xs text-slate-500 font-medium">marked present today</span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    <?= $today_marked_count > 0 ? "{$today_marked_count} students recorded today" : "Today's morning roll call in progress" ?>
                </p>
            </div>
            <div class="mt-3 pt-2 border-t border-slate-100">
                <a href="/admin/attendance.php" class="text-xs font-bold text-emerald-600 hover:text-emerald-800 flex items-center">
                    <span>Attendance Oversight &rarr;</span>
                </a>
            </div>
        </div>

        <!-- Homework & Assignments -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow transition flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Assignments &amp; Homework</span>
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                        <i class="fa-solid fa-book-bookmark text-lg"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mt-2">
                    <p class="text-2xl font-extrabold text-slate-900 font-['Outfit']"><?= $total_asgn_count ?></p>
                    <span class="text-xs text-slate-500 font-medium">tasks active</span>
                </div>
                <p class="text-xs text-slate-500 mt-1"><?= $total_subs_count ?> student submissions recorded across all classes.</p>
            </div>
            <div class="mt-3 pt-2 border-t border-slate-100">
                <a href="/admin/assignments.php" class="text-xs font-bold text-purple-600 hover:text-purple-800 flex items-center">
                    <span>Inspect Assignments &rarr;</span>
                </a>
            </div>
        </div>

        <!-- Leave Requests & Timetable -->
        <div class="bg-white p-5 rounded-2xl border <?= $pending_leaves_count > 0 ? 'border-amber-300 bg-amber-50/20' : 'border-slate-200' ?> shadow-sm hover:shadow transition flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Leave Applications</span>
                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                        <i class="fa-solid fa-envelope-open-text text-lg"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mt-2">
                    <p class="text-2xl font-extrabold text-slate-900 font-['Outfit']"><?= $pending_leaves_count ?></p>
                    <span class="text-xs text-slate-500 font-medium">pending review</span>
                </div>
                <p class="text-xs text-slate-500 mt-1">Review leave applications from faculty &amp; students.</p>
            </div>
            <div class="mt-3 pt-2 border-t border-slate-100 flex items-center justify-between">
                <a href="/admin/leaves.php" class="text-xs font-bold text-rose-600 hover:text-rose-800">
                    Review Leaves &rarr;
                </a>
                <a href="/admin/timetable.php" class="text-xs font-semibold text-slate-400 hover:text-slate-600">
                    Timetable
                </a>
            </div>
        </div>
    </div>

    <!-- PENDING STUDENT APPROVALS QUEUE (Core Feature Highlight) -->
    <section class="mb-8">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-5 sm:p-6 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gradient-to-r from-amber-50/50 to-white">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-shield-virus text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900 font-['Outfit']">Student Registration Approval Queue</h2>
                        <p class="text-xs text-slate-500">Students registering online cannot access their dashboard until approved here</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                        <?= count($pending_students) ?> Pending Review
                    </span>
                    <a href="/admin/students.php?tab=pending" class="text-xs text-blue-600 hover:text-blue-800 font-semibold ml-2">
                        View All &rarr;
                    </a>
                </div>
            </div>

            <?php if (empty($pending_students)): ?>
                <div class="p-8 text-center">
                    <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                        <i class="fa-solid fa-check text-xl"></i>
                    </div>
                    <p class="text-sm font-bold text-slate-800">All Student Registrations Approved!</p>
                    <p class="text-xs text-slate-500 mt-1">There are no pending student applications waiting for your approval right now.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-200">
                                <th class="py-3 px-4 sm:px-6">Student Info</th>
                                <th class="py-3 px-4">Class &amp; Roll</th>
                                <th class="py-3 px-4">Guardian / Contact</th>
                                <th class="py-3 px-4">Registered Date</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 sm:px-6 text-right">Admin Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            <?php foreach ($pending_students as $p): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3.5 px-4 sm:px-6">
                                        <div class="font-bold text-slate-900"><?= htmlspecialchars($p['full_name']) ?></div>
                                        <div class="text-[11px] text-slate-500"><?= htmlspecialchars($p['email']) ?></div>
                                        <div class="text-[10px] text-blue-600 font-mono"><?= htmlspecialchars($p['student_code']) ?></div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                                            <?= htmlspecialchars($p['class']) ?>
                                        </span>
                                        <div class="text-[11px] text-slate-500 mt-0.5">Roll: <?= htmlspecialchars($p['roll_no'] ?: 'N/A') ?></div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="text-slate-800 font-medium"><?= htmlspecialchars($p['guardian_name'] ?: 'Not provided') ?></div>
                                        <div class="text-[11px] text-slate-500"><?= htmlspecialchars($p['phone'] ?: 'No phone') ?></div>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500">
                                        <?= date('d M Y, h:i A', strtotime($p['created_at'])) ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5 animate-pulse"></span>
                                            Pending Approval
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 sm:px-6 text-right">
                                        <div class="flex items-center justify-end space-x-2">
                                            <!-- One-Click Approve Form -->
                                            <form method="POST" action="index.php" onsubmit="return confirm('Approve this student? They will be allowed to log in immediately.');">
                                                <input type="hidden" name="user_id" value="<?= $p['user_id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition">
                                                    <i class="fa-solid fa-check mr-1.5"></i> Approve &amp; Activate
                                                </button>
                                            </form>

                                            <!-- One-Click Reject Form -->
                                            <form method="POST" action="index.php" onsubmit="return confirm('Reject this student registration?');">
                                                <input type="hidden" name="user_id" value="<?= $p['user_id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="inline-flex items-center px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-rose-100 text-slate-600 hover:text-rose-700 font-semibold text-xs transition">
                                                    <i class="fa-solid fa-xmark mr-1"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Two Columns: Faculty Overview & Recent School Notices -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Faculty Roster Preview -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 font-['Outfit']">Faculty Directory Preview</h3>
                            <p class="text-[11px] text-slate-500">Teachers with active system IDs</p>
                        </div>
                    </div>
                    <a href="/admin/teachers.php" class="text-xs font-bold text-blue-600 hover:text-blue-800">All Teachers &rarr;</a>
                </div>

                <div class="space-y-3">
                    <?php foreach ($recent_teachers as $t): ?>
                        <div class="flex items-center justify-between p-3 rounded-2xl bg-slate-50 border border-slate-100 hover:bg-slate-100/70 transition">
                            <div class="flex items-center space-x-3">
                                <div class="w-9 h-9 rounded-xl bg-navy-800 text-amber-300 flex items-center justify-center font-bold text-xs">
                                    <?= strtoupper(substr($t['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-900"><?= htmlspecialchars($t['full_name']) ?></p>
                                    <p class="text-[11px] text-slate-500"><?= htmlspecialchars($t['designation'] ?: $t['department']) ?> &bull; <span class="font-mono text-blue-600"><?= htmlspecialchars($t['teacher_code']) ?></span></p>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800">
                                <?= htmlspecialchars($t['department']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pt-4 mt-4 border-t border-slate-100">
                <a href="/admin/teachers.php" class="w-full py-2.5 rounded-xl border border-dashed border-slate-300 text-slate-600 hover:text-navy-900 hover:border-slate-400 font-semibold text-xs flex items-center justify-center transition">
                    <i class="fa-solid fa-plus-circle mr-2 text-blue-600"></i> Create New Teacher ID &amp; Profile
                </a>
            </div>
        </div>

        <!-- Latest Notices & Circulars -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-sm">
                            <i class="fa-solid fa-bullhorn"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 font-['Outfit']">School Announcements</h3>
                            <p class="text-[11px] text-slate-500">Active notices published to ERP</p>
                        </div>
                    </div>
                    <a href="/admin/notices.php" class="text-xs font-bold text-purple-600 hover:text-purple-800">Notice Board &rarr;</a>
                </div>

                <div class="space-y-3">
                    <?php foreach ($recent_notices as $n): ?>
                        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-purple-100 text-purple-800">
                                    <?= htmlspecialchars($n['category']) ?>
                                </span>
                                <span class="text-[10px] text-slate-400">
                                    <?= date('d M Y', strtotime($n['created_at'])) ?>
                                </span>
                            </div>
                            <h4 class="text-xs font-bold text-slate-900 leading-snug"><?= htmlspecialchars($n['title']) ?></h4>
                            <p class="text-[11px] text-slate-500 line-clamp-2 mt-1 leading-relaxed"><?= htmlspecialchars($n['content']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pt-4 mt-4 border-t border-slate-100">
                <a href="/admin/notices.php" class="w-full py-2.5 rounded-xl border border-dashed border-slate-300 text-slate-600 hover:text-purple-900 hover:border-slate-400 font-semibold text-xs flex items-center justify-center transition">
                    <i class="fa-solid fa-pen-to-square mr-2 text-purple-600"></i> Broadcast New Circular
                </a>
            </div>
        </div>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
