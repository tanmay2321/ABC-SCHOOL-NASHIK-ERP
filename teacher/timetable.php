<?php
/**
 * Teacher Weekly Teaching Timetable
 * ABC School Nashik ERP System
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('teacher');

$user = current_user();
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();

if (!$teacher) {
    die("Teacher profile not found.");
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$today_day = date('l'); // e.g. "Monday"

// Fetch teaching slots assigned to this teacher
$teach_name = '%' . $teacher['full_name'] . '%';
$stmtSlots = $pdo->prepare("SELECT * FROM timetables WHERE teacher_name LIKE ? ORDER BY period_number ASC");
$stmtSlots->execute([$teach_name]);
$my_slots = $stmtSlots->fetchAll();

// If no direct name match, match by department keyword as fallback
if (empty($my_slots)) {
    $dept_term = '%' . $teacher['department'] . '%';
    $stmtSlots = $pdo->prepare("SELECT * FROM timetables WHERE subject LIKE ? ORDER BY period_number ASC");
    $stmtSlots->execute([$dept_term]);
    $my_slots = $stmtSlots->fetchAll();
}

// Group slots by day
$teacher_schedule = [];
foreach ($days as $d) {
    $teacher_schedule[$d] = [];
}
foreach ($my_slots as $slot) {
    if (isset($teacher_schedule[$slot['day_of_week']])) {
        $teacher_schedule[$slot['day_of_week']][] = $slot;
    }
}

$total_weekly_periods = count($my_slots);

$page_title = "Teaching Timetable - Teacher Portal";
include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-calendar-week text-emerald-600"></i>
                Faculty Teaching Schedule
            </h1>
            <p class="text-sm text-slate-500">Weekly class periods, assigned grades, subject modules, and laboratory room numbers.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3.5 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold">
                <i class="fa-solid fa-chalkboard-user mr-1"></i> <?= htmlspecialchars($teacher['full_name']) ?> (<?= htmlspecialchars($teacher['department']) ?>)
            </span>
            <button onclick="window.print()" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-100 inline-flex items-center gap-1.5 transition">
                <i class="fa-solid fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Weekly Periods</p>
                <p class="text-2xl font-bold text-slate-800"><?= $total_weekly_periods ?> Lectures</p>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-calendar-day"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Today's Lectures (<?= $today_day ?>)</p>
                <p class="text-2xl font-bold text-indigo-600">
                    <?= isset($teacher_schedule[$today_day]) ? count($teacher_schedule[$today_day]) : 0 ?> Classes
                </p>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-school"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Department</p>
                <p class="text-xl font-bold text-slate-800"><?= htmlspecialchars($teacher['department']) ?></p>
            </div>
        </div>
    </div>

    <!-- Weekly Schedule Cards -->
    <div class="space-y-4">
        <?php foreach ($days as $day): 
            $day_classes = $teacher_schedule[$day] ?? [];
            $is_today = ($day === $today_day);
        ?>
            <div class="bg-white rounded-2xl border <?= $is_today ? 'border-emerald-300 ring-2 ring-emerald-500/20' : 'border-slate-200' ?> shadow-2xs overflow-hidden">
                <div class="px-5 py-3 <?= $is_today ? 'bg-emerald-50/80 border-b border-emerald-200' : 'bg-slate-50 border-b border-slate-200' ?> flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="font-bold text-sm <?= $is_today ? 'text-emerald-900' : 'text-slate-800' ?>">
                            <?= $day ?>
                        </span>
                        <?php if ($is_today): ?>
                            <span class="px-2 py-0.5 rounded-full text-2xs font-extrabold bg-emerald-600 text-white uppercase tracking-wider">
                                Today
                            </span>
                        <?php endif; ?>
                    </div>
                    <span class="text-xs font-semibold <?= $is_today ? 'text-emerald-700' : 'text-slate-400' ?>">
                        <?= count($day_classes) ?> lecture<?= count($day_classes) === 1 ? '' : 's' ?> scheduled
                    </span>
                </div>

                <div class="p-4">
                    <?php if (empty($day_classes)): ?>
                        <div class="py-4 text-center text-xs text-slate-400 italic">
                            No teaching periods scheduled on <?= $day ?> (Free Research / Planning Day)
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                            <?php foreach ($day_classes as $c): ?>
                                <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-emerald-50/40 hover:border-emerald-200 transition">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="px-2 py-0.5 rounded-md text-2xs font-bold bg-indigo-100 text-indigo-700">
                                            Period <?= $c['period_number'] ?>
                                        </span>
                                        <span class="px-2 py-0.5 rounded-md text-2xs font-bold bg-white border border-slate-200 text-slate-600">
                                            <?= htmlspecialchars($c['class']) ?>
                                        </span>
                                    </div>
                                    <div class="font-bold text-slate-800 text-sm leading-snug">
                                        <?= htmlspecialchars($c['subject']) ?>
                                    </div>
                                    <div class="flex items-center justify-between text-2xs text-slate-500 mt-2 pt-2 border-t border-slate-200/60">
                                        <span><i class="fa-regular fa-clock text-slate-400 mr-1"></i><?= htmlspecialchars($c['time_slot']) ?></span>
                                        <span class="font-semibold text-slate-700"><i class="fa-solid fa-door-open text-slate-400 mr-1"></i><?= htmlspecialchars($c['room_no']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
