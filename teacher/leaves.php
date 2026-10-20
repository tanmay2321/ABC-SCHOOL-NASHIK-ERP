<?php
/**
 * Faculty Leave Application Portal
 * ABC School Nashik ERP System
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_auth('teacher');

$user = current_user();
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();

if (!$teacher) {
    die("Teacher profile not found.");
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Handle Leave Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'apply_leave') {
        $leave_type = trim($_POST['leave_type'] ?? '');
        $from_date = trim($_POST['from_date'] ?? '');
        $to_date = trim($_POST['to_date'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        if (empty($leave_type) || empty($from_date) || empty($to_date) || empty($reason)) {
            $_SESSION['error'] = "All fields are required to apply for leave.";
            header("Location: /teacher/leaves.php");
            exit;
        }

        if (strtotime($to_date) < strtotime($from_date)) {
            $_SESSION['error'] = "To Date cannot be earlier than From Date.";
            header("Location: /teacher/leaves.php");
            exit;
        }

        $ins = $pdo->prepare("INSERT INTO leave_applications (user_id, role, leave_type, from_date, to_date, reason, status) VALUES (?, 'teacher', ?, ?, ?, ?, 'Pending')");
        $ins->execute([$user['id'], $leave_type, $from_date, $to_date, $reason]);

        $_SESSION['success'] = "Leave application submitted successfully. It has been routed to the Head Admin for approval.";
        header("Location: /teacher/leaves.php");
        exit;
    }
}

// Fetch teacher's leaves
$leaveStmt = $pdo->prepare("SELECT * FROM leave_applications WHERE user_id = ? ORDER BY id DESC");
$leaveStmt->execute([$user['id']]);
$my_leaves = $leaveStmt->fetchAll();

// Counts
$total_applied = count($my_leaves);
$pending_count = 0;
$approved_count = 0;
foreach ($my_leaves as $l) {
    if ($l['status'] === 'Pending') $pending_count++;
    if ($l['status'] === 'Approved') $approved_count++;
}

$page_title = "Apply for Leave - Teacher Portal";
include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-calendar-minus text-emerald-600"></i>
                Faculty Leave Applications
            </h1>
            <p class="text-sm text-slate-500">Apply for official leave, track approval progress, and review administrative feedback.</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="document.getElementById('applyLeaveModal').classList.remove('hidden')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg inline-flex items-center gap-2 shadow-sm transition">
                <i class="fa-solid fa-plus"></i> Submit Leave Request
            </button>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Summary Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-xl">
                <i class="fa-solid fa-file-signature"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Applied</p>
                <p class="text-2xl font-bold text-slate-800"><?= $total_applied ?></p>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                <i class="fa-regular fa-clock"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pending Review</p>
                <p class="text-2xl font-bold text-amber-600"><?= $pending_count ?></p>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Approved Requests</p>
                <p class="text-2xl font-bold text-emerald-600"><?= $approved_count ?></p>
            </div>
        </div>
    </div>

    <!-- History Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-800">My Leave Application History</h2>
        </div>

        <?php if (empty($my_leaves)): ?>
            <div class="py-16 text-center text-slate-400">
                <i class="fa-solid fa-umbrella-beach text-5xl mb-3 text-slate-300"></i>
                <p class="text-base font-semibold text-slate-700">No leave requests recorded</p>
                <p class="text-xs text-slate-400 mt-1">When you need time off for medical, casual, or duty purposes, submit an application above.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3.5">Leave Type</th>
                            <th class="px-4 py-3.5">Duration</th>
                            <th class="px-6 py-3.5">Reason & Purpose</th>
                            <th class="px-4 py-3.5">Applied Date</th>
                            <th class="px-4 py-3.5">Status</th>
                            <th class="px-6 py-3.5">Admin Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($my_leaves as $l): 
                            $from_time = strtotime($l['from_date']);
                            $to_time = strtotime($l['to_date']);
                            $days_count = max(1, round(($to_time - $from_time) / 86400) + 1);
                        ?>
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-6 py-4">
                                    <span class="font-bold text-slate-800 text-xs px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        <?= htmlspecialchars($l['leave_type']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-slate-800 text-xs">
                                        <?= date('d M Y', $from_time) ?>
                                        <?php if ($l['from_date'] !== $l['to_date']): ?>
                                            to <?= date('d M Y', $to_time) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-2xs text-slate-500 font-medium mt-0.5">
                                        <?= $days_count ?> day<?= $days_count > 1 ? 's' : '' ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-xs text-slate-700 max-w-sm"><?= htmlspecialchars($l['reason']) ?></p>
                                </td>
                                <td class="px-4 py-4 text-xs text-slate-500">
                                    <?= date('d M Y', strtotime($l['applied_at'])) ?>
                                </td>
                                <td class="px-4 py-4">
                                    <?php if ($l['status'] === 'Approved'): ?>
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 inline-flex items-center gap-1">
                                            <i class="fa-solid fa-check"></i> Approved
                                        </span>
                                    <?php elseif ($l['status'] === 'Rejected'): ?>
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-rose-100 text-rose-800 inline-flex items-center gap-1">
                                            <i class="fa-solid fa-ban"></i> Rejected
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-amber-100 text-amber-800 inline-flex items-center gap-1">
                                            <i class="fa-regular fa-clock"></i> Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500">
                                    <?= !empty($l['admin_remarks']) ? htmlspecialchars($l['admin_remarks']) : '<span class="italic text-slate-400">None yet</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL: APPLY FOR LEAVE -->
<div id="applyLeaveModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-calendar-minus text-emerald-600"></i>
                Submit Leave Application
            </h3>
            <button onclick="document.getElementById('applyLeaveModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="apply_leave">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Leave Classification *</label>
                <select name="leave_type" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="Casual Leave">Casual Leave (CL)</option>
                    <option value="Medical / Sick Leave">Medical / Sick Leave (ML)</option>
                    <option value="Duty / Conference Leave">Academic / Duty Leave</option>
                    <option value="Earned Leave">Earned Leave (EL)</option>
                    <option value="Maternity / Paternity Leave">Maternity / Paternity Leave</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">From Date *</label>
                    <input type="date" name="from_date" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">To Date *</label>
                    <input type="date" name="to_date" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Detailed Reason for Absence *</label>
                <textarea name="reason" rows="3" required placeholder="Specify reason (e.g. Attending academic faculty seminar, personal emergency, medical appointment)..." class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('applyLeaveModal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold shadow-xs">Send Application</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
