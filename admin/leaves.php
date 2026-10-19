<?php
/**
 * Leave Application & Approval Management - Admin Portal
 * ABC School Nashik ERP System
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $leave_id = intval($_POST['leave_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';
        $admin_remarks = trim($_POST['admin_remarks'] ?? '');

        if (!in_array($new_status, ['Approved', 'Rejected', 'Pending'])) {
            $_SESSION['error'] = "Invalid status selected.";
            header("Location: /admin/leaves.php");
            exit;
        }

        $upd = $pdo->prepare("UPDATE leave_applications SET status = ?, admin_remarks = ? WHERE id = ?");
        $upd->execute([$new_status, $admin_remarks, $leave_id]);

        $_SESSION['success'] = "Leave application marked as '{$new_status}'.";
        header("Location: /admin/leaves.php");
        exit;
    }
}

// Filters
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

$query = "
    SELECT l.*, u.username, u.email,
           CASE 
               WHEN l.role = 'teacher' THEN (SELECT full_name FROM teachers WHERE user_id = l.user_id)
               WHEN l.role = 'student' THEN (SELECT full_name FROM students WHERE user_id = l.user_id)
               ELSE u.username
           END as applicant_name,
           CASE 
               WHEN l.role = 'teacher' THEN (SELECT department FROM teachers WHERE user_id = l.user_id)
               WHEN l.role = 'student' THEN (SELECT class FROM students WHERE user_id = l.user_id)
               ELSE ''
           END as affiliation_info
    FROM leave_applications l
    JOIN users u ON l.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($role_filter !== 'all') {
    $query .= " AND l.role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== 'all') {
    $query .= " AND l.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY l.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$leaves = $stmt->fetchAll();

// Overall Stats
$count_pending = $pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status = 'Pending'")->fetchColumn();
$count_approved = $pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status = 'Approved'")->fetchColumn();
$count_rejected = $pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status = 'Rejected'")->fetchColumn();

$page_title = "Leave Management & Approvals - Admin Portal";
include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-envelope-open-text text-indigo-600"></i>
                Leave Management & Approvals
            </h1>
            <p class="text-sm text-slate-500">Review, approve, or reject leave requests submitted by faculty and students.</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($count_pending > 0): ?>
                <span class="px-3 py-1.5 rounded-full bg-amber-100 text-amber-800 text-xs font-bold flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <?= $count_pending ?> Pending Review
                </span>
            <?php endif; ?>
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

    <!-- Summary Counters -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <a href="/admin/leaves.php?status=Pending" class="bg-white p-5 rounded-2xl border <?= $status_filter === 'Pending' ? 'border-amber-400 ring-2 ring-amber-400/20' : 'border-slate-200' ?> shadow-2xs flex items-center space-x-4 hover:border-amber-300 transition">
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pending Requests</p>
                <p class="text-2xl font-bold text-amber-600"><?= $count_pending ?></p>
            </div>
        </a>

        <a href="/admin/leaves.php?status=Approved" class="bg-white p-5 rounded-2xl border <?= $status_filter === 'Approved' ? 'border-emerald-400 ring-2 ring-emerald-400/20' : 'border-slate-200' ?> shadow-2xs flex items-center space-x-4 hover:border-emerald-300 transition">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Approved Leaves</p>
                <p class="text-2xl font-bold text-emerald-600"><?= $count_approved ?></p>
            </div>
        </a>

        <a href="/admin/leaves.php?status=Rejected" class="bg-white p-5 rounded-2xl border <?= $status_filter === 'Rejected' ? 'border-rose-400 ring-2 ring-rose-400/20' : 'border-slate-200' ?> shadow-2xs flex items-center space-x-4 hover:border-rose-300 transition">
            <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-ban"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Rejected</p>
                <p class="text-2xl font-bold text-rose-600"><?= $count_rejected ?></p>
            </div>
        </a>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold uppercase text-slate-400 mr-2">Filter By Role:</span>
            <a href="/admin/leaves.php?role=all&status=<?= urlencode($status_filter) ?>" class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?= $role_filter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                All (<?= count($leaves) ?>)
            </a>
            <a href="/admin/leaves.php?role=teacher&status=<?= urlencode($status_filter) ?>" class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?= $role_filter === 'teacher' ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100' ?>">
                <i class="fa-solid fa-chalkboard-user mr-1"></i> Teachers
            </a>
            <a href="/admin/leaves.php?role=student&status=<?= urlencode($status_filter) ?>" class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?= $role_filter === 'student' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?>">
                <i class="fa-solid fa-user-graduate mr-1"></i> Students
            </a>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs font-bold uppercase text-slate-400 mr-1">Status:</span>
            <select onchange="location.href='/admin/leaves.php?role=<?= urlencode($role_filter) ?>&status=' + this.value" class="px-3 py-1.5 text-xs font-semibold border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending Only</option>
                <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved Only</option>
                <option value="Rejected" <?= $status_filter === 'Rejected' ? 'selected' : '' ?>>Rejected Only</option>
            </select>
        </div>
    </div>

    <!-- Leaves Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <?php if (empty($leaves)): ?>
            <div class="py-16 text-center text-slate-400">
                <i class="fa-solid fa-inbox text-5xl mb-3 text-slate-300"></i>
                <p class="text-base font-semibold text-slate-700">No leave applications found</p>
                <p class="text-xs text-slate-400 mt-1">There are no leave requests matching your current filter criteria.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3.5">Applicant</th>
                            <th class="px-4 py-3.5">Leave Type</th>
                            <th class="px-4 py-3.5">Duration</th>
                            <th class="px-6 py-3.5">Reason & Purpose</th>
                            <th class="px-4 py-3.5">Status</th>
                            <th class="px-6 py-3.5 text-right">Approval Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($leaves as $l): 
                            $from_time = strtotime($l['from_date']);
                            $to_time = strtotime($l['to_date']);
                            $days_count = max(1, round(($to_time - $from_time) / 86400) + 1);
                        ?>
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($l['applicant_name']) ?></div>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="px-2 py-0.5 rounded text-2xs font-bold <?= $l['role'] === 'teacher' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700' ?> uppercase">
                                            <?= $l['role'] ?>
                                        </span>
                                        <span class="text-xs text-slate-400"><?= htmlspecialchars($l['affiliation_info']) ?></span>
                                    </div>
                                    <div class="text-2xs text-slate-400 mt-1">Applied: <?= date('d M Y, h:i A', strtotime($l['applied_at'])) ?></div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="font-semibold text-slate-800 text-xs px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200">
                                        <?= htmlspecialchars($l['leave_type']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-medium text-slate-800 text-xs">
                                        <?= date('d M Y', $from_time) ?>
                                        <?php if ($l['from_date'] !== $l['to_date']): ?>
                                            <span class="text-slate-400">to</span> <?= date('d M Y', $to_time) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-2xs text-indigo-600 font-semibold mt-0.5">
                                        <?= $days_count ?> day<?= $days_count > 1 ? 's' : '' ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-xs text-slate-700 leading-relaxed max-w-md"><?= htmlspecialchars($l['reason']) ?></p>
                                    <?php if (!empty($l['admin_remarks'])): ?>
                                        <div class="mt-2 text-2xs text-slate-500 bg-slate-50 p-2 rounded-lg border border-slate-200">
                                            <span class="font-bold text-slate-700">Admin Remarks:</span> <?= htmlspecialchars($l['admin_remarks']) ?>
                                        </div>
                                    <?php endif; ?>
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
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick="openLeaveActionModal(<?= htmlspecialchars(json_encode($l)) ?>)" class="px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold text-xs border border-indigo-200 transition">
                                            <i class="fa-solid fa-sliders mr-1"></i> Review & Action
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL: APPROVE / REJECT LEAVE -->
<div id="leaveActionModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-envelope-open-text text-indigo-600"></i>
                Review Leave Application
            </h3>
            <button onclick="document.getElementById('leaveActionModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="leave_id" id="modal_leave_id">

            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5 text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-500">Applicant:</span>
                    <span class="font-bold text-slate-800" id="modal_applicant_name">--</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Leave Type:</span>
                    <span class="font-semibold text-indigo-700" id="modal_leave_type">--</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Duration:</span>
                    <span class="font-semibold text-slate-800" id="modal_duration">--</span>
                </div>
                <div class="pt-2 border-t border-slate-200 text-slate-600">
                    <span class="font-bold text-slate-700">Reason:</span>
                    <p class="mt-0.5 italic" id="modal_reason">--</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Approval Decision *</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 p-3 rounded-xl border border-emerald-200 bg-emerald-50/50 cursor-pointer hover:bg-emerald-50 transition">
                        <input type="radio" name="status" value="Approved" required class="text-emerald-600 focus:ring-emerald-500">
                        <span class="text-xs font-bold text-emerald-800">Approve Leave</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 rounded-xl border border-rose-200 bg-rose-50/50 cursor-pointer hover:bg-rose-50 transition">
                        <input type="radio" name="status" value="Rejected" required class="text-rose-600 focus:ring-rose-500">
                        <span class="text-xs font-bold text-rose-800">Reject Leave</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Administrative Remarks / Response</label>
                <textarea name="admin_remarks" id="modal_admin_remarks" rows="3" placeholder="e.g. Leave approved. Submit medical certificate upon return..." class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('leaveActionModal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-xs">Save Decision</button>
            </div>
        </form>
    </div>
</div>

<script>
function openLeaveActionModal(leave) {
    document.getElementById('modal_leave_id').value = leave.id;
    document.getElementById('modal_applicant_name').innerText = leave.applicant_name + ' (' + leave.role.toUpperCase() + ')';
    document.getElementById('modal_leave_type').innerText = leave.leave_type;
    document.getElementById('modal_duration').innerText = leave.from_date + ' to ' + leave.to_date;
    document.getElementById('modal_reason').innerText = leave.reason;
    document.getElementById('modal_admin_remarks').value = leave.admin_remarks || '';
    
    // Check radio matching status
    const radios = document.getElementsByName('status');
    for (const r of radios) {
        if (r.value === leave.status) {
            r.checked = true;
        }
    }

    document.getElementById('leaveActionModal').classList.remove('hidden');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
