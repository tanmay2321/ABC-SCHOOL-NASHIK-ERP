<?php
/**
 * Student: Attendance History & Compliance
 * ABC School Nashik ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('student');

$student_user_id = $_SESSION['user_id'];
$sStmt = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
$sStmt->execute(['uid' => $student_user_id]);
$student = $sStmt->fetch();
$student_id = $student['id'] ?? 0;

// Fetch Attendance Stats
$attStatsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days
    FROM attendance
    WHERE student_id = :sid
");
$attStatsStmt->execute(['sid' => $student_id]);
$stats = $attStatsStmt->fetch();

$total_days = (int)($stats['total_days'] ?? 0);
$present_days = (int)($stats['present_days'] ?? 0);
$absent_days = (int)($stats['absent_days'] ?? 0);
$late_days = (int)($stats['late_days'] ?? 0);
$pct = ($total_days > 0) ? round(($present_days / $total_days) * 100, 1) : 100;

// Fetch Attendance Log
$logStmt = $pdo->prepare("
    SELECT a.*, t.full_name as marked_by_name
    FROM attendance a
    LEFT JOIN users u ON a.marked_by = u.id
    LEFT JOIN teachers t ON u.id = t.user_id
    WHERE a.student_id = :sid
    ORDER BY a.date DESC
");
$logStmt->execute(['sid' => $student_id]);
$logs = $logStmt->fetchAll();
?>

<?php 
$page_title = 'My Attendance Log';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php'; 
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">
                <span>Academic Attendance Tracker</span>
                <span>&bull;</span>
                <span class="text-slate-400">Class: <?= htmlspecialchars($student['class'] ?? 'N/A') ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                My Attendance Record &amp; Compliance
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Official physical lecture attendance record maintained by ABC School Nashik faculty.
            </p>
        </div>

        <div>
            <span class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-bold <?= $pct >= 75 ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300' ?>">
                <i class="fa-solid <?= $pct >= 75 ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?> mr-2"></i>
                <?= $pct >= 75 ? 'CBSE 75% Attendance Requirement Met' : 'Below 75% Attendance Threshold' ?>
            </span>
        </div>
    </div>

    <!-- Attendance Breakdown Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-5 mb-8">
        
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm text-center">
            <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Total Recorded Days</span>
            <p class="text-3xl font-extrabold text-slate-900 font-['Outfit'] mt-2"><?= $total_days ?></p>
            <p class="text-[11px] text-slate-500 mt-1">Academic Sessions</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm text-center">
            <span class="text-xs font-bold uppercase text-emerald-600 tracking-wider">Days Present</span>
            <p class="text-3xl font-extrabold text-emerald-600 font-['Outfit'] mt-2"><?= $present_days ?></p>
            <p class="text-[11px] text-slate-500 mt-1">Attended Classes</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm text-center">
            <span class="text-xs font-bold uppercase text-rose-600 tracking-wider">Days Absent</span>
            <p class="text-3xl font-extrabold text-rose-600 font-['Outfit'] mt-2"><?= $absent_days ?></p>
            <p class="text-[11px] text-slate-500 mt-1">Absences Logged</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm text-center">
            <span class="text-xs font-bold uppercase text-amber-600 tracking-wider">Late Arrivals</span>
            <p class="text-3xl font-extrabold text-amber-500 font-['Outfit'] mt-2"><?= $late_days ?></p>
            <p class="text-[11px] text-slate-500 mt-1">Tardy Sessions</p>
        </div>

    </div>

    <!-- Attendance History Log Table -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 font-['Outfit']">Detailed Session Logs</h3>
            <span class="text-xs text-slate-400">Total Entries: <?= count($logs) ?></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3 px-4 sm:px-6">Date</th>
                        <th class="py-3 px-4">Day</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Teacher Remark</th>
                        <th class="py-3 px-4 sm:px-6">Verified By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-500">
                                No attendance records have been registered for your ID yet.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($logs as $l): ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-4 sm:px-6 font-bold text-slate-900">
                                <?= date('d M Y', strtotime($l['date'])) ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-500">
                                <?= date('l', strtotime($l['date'])) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <?php if ($l['status'] === 'Present'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-check text-[10px] mr-1 text-emerald-600"></i> Present
                                    </span>
                                <?php elseif ($l['status'] === 'Absent'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800">
                                        <i class="fa-solid fa-xmark text-[10px] mr-1 text-rose-600"></i> Absent
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">
                                        <i class="fa-solid fa-clock text-[10px] mr-1 text-amber-600"></i> Late
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 italic">
                                <?= htmlspecialchars($l['remarks'] ?: 'Regular lecture session') ?>
                            </td>
                            <td class="py-3.5 px-4 sm:px-6 text-slate-500">
                                <?= htmlspecialchars($l['marked_by_name'] ?: 'Faculty Member') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
