<?php
/**
 * Student Class Timetable & Schedule
 * ABC School Nashik ERP System
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_auth('student');

$user = current_user();
$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$user['id']]);
$student = $stmt->fetch();

if (!$student) {
    die("Student profile not found.");
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$today_day = date('l');

// Fetch timetable entries for this student's class
$ttStmt = $pdo->prepare("SELECT * FROM timetables WHERE class = ? ORDER BY period_number ASC");
$ttStmt->execute([$student['class']]);
$all_slots = $ttStmt->fetchAll();

// Group by day of week
$timetable_grid = [];
foreach ($days as $d) {
    $timetable_grid[$d] = [];
}
foreach ($all_slots as $slot) {
    if (isset($timetable_grid[$slot['day_of_week']])) {
        $timetable_grid[$slot['day_of_week']][$slot['period_number']] = $slot;
    }
}

// Subject color styling helper
function getSubjectBadge($subj) {
    $s = strtolower($subj);
    if (strpos($s, 'math') !== false) {
        return 'bg-blue-100 text-blue-800 border-blue-200';
    } elseif (strpos($s, 'science') !== false || strpos($s, 'physic') !== false || strpos($s, 'chem') !== false) {
        return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    } elseif (strpos($s, 'english') !== false) {
        return 'bg-amber-100 text-amber-800 border-amber-200';
    } elseif (strpos($s, 'social') !== false || strpos($s, 'hist') !== false) {
        return 'bg-purple-100 text-purple-800 border-purple-200';
    } elseif (strpos($s, 'computer') !== false) {
        return 'bg-cyan-100 text-cyan-800 border-cyan-200';
    } else {
        return 'bg-rose-100 text-rose-800 border-rose-200';
    }
}

$page_title = "Class Timetable - Student Portal";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-calendar-days text-indigo-600"></i>
                Class Timetable & Schedule
            </h1>
            <p class="text-sm text-slate-500">Weekly schedule of daily lectures, lab practicals, subject faculty, and classrooms.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-bold">
                <i class="fa-solid fa-users-rectangle mr-1"></i> Class: <?= htmlspecialchars($student['class']) ?>
            </span>
            <button onclick="window.print()" class="px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-100 inline-flex items-center gap-1.5 transition">
                <i class="fa-solid fa-print"></i> Print Schedule
            </button>
        </div>
    </div>

    <!-- Today's Alert Banner -->
    <?php if (isset($timetable_grid[$today_day]) && !empty($timetable_grid[$today_day])): ?>
        <div class="p-4 rounded-2xl bg-gradient-to-r from-indigo-500 to-indigo-600 text-white shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-xs flex items-center justify-center text-lg">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm">Today is <?= $today_day ?>!</h3>
                    <p class="text-xs text-indigo-100">You have <?= count($timetable_grid[$today_day]) ?> scheduled lectures today. Be on time for morning assembly at 08:15 AM.</p>
                </div>
            </div>
            <div class="text-xs font-semibold bg-white/10 px-3 py-1.5 rounded-lg border border-white/20 self-start md:self-auto">
                Regular School Hours: 08:30 AM - 02:15 PM
            </div>
        </div>
    <?php endif; ?>

    <!-- Daily Breakdown -->
    <div class="space-y-4">
        <?php foreach ($days as $day): 
            $day_slots = $timetable_grid[$day] ?? [];
            $is_today = ($day === $today_day);
        ?>
            <div class="bg-white rounded-2xl border <?= $is_today ? 'border-indigo-300 ring-2 ring-indigo-500/20' : 'border-slate-200' ?> shadow-2xs overflow-hidden">
                <div class="px-5 py-3 <?= $is_today ? 'bg-indigo-50/80 border-b border-indigo-200' : 'bg-slate-50 border-b border-slate-200' ?> flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="font-bold text-sm <?= $is_today ? 'text-indigo-900' : 'text-slate-800' ?>">
                            <?= $day ?>
                        </span>
                        <?php if ($is_today): ?>
                            <span class="px-2 py-0.5 rounded-full text-2xs font-extrabold bg-indigo-600 text-white uppercase tracking-wider">
                                Today
                            </span>
                        <?php endif; ?>
                    </div>
                    <span class="text-xs font-semibold <?= $is_today ? 'text-indigo-700' : 'text-slate-400' ?>">
                        <?= count($day_slots) ?> Periods
                    </span>
                </div>

                <div class="p-4">
                    <?php if (empty($day_slots)): ?>
                        <div class="py-4 text-center text-xs text-slate-400 italic">
                            No classes scheduled (Holiday / Weekend)
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                            <?php for ($p = 1; $p <= 6; $p++): 
                                $slot = $day_slots[$p] ?? null;
                            ?>
                                <?php if ($slot): ?>
                                    <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white hover:border-indigo-300 transition shadow-2xs flex flex-col justify-between min-h-[120px]">
                                        <div>
                                            <div class="flex items-center justify-between mb-1.5">
                                                <span class="px-2 py-0.5 rounded text-2xs font-bold bg-slate-200 text-slate-700">
                                                    Period <?= $p ?>
                                                </span>
                                            </div>
                                            <div class="font-bold text-slate-800 text-sm leading-snug">
                                                <?= htmlspecialchars($slot['subject']) ?>
                                            </div>
                                            <div class="text-xs text-slate-600 mt-1 truncate">
                                                <i class="fa-solid fa-chalkboard-user text-slate-400 mr-1"></i><?= htmlspecialchars($slot['teacher_name']) ?>
                                            </div>
                                        </div>
                                        <div class="mt-3 pt-2 border-t border-slate-200/60 flex items-center justify-between text-2xs text-slate-500">
                                            <span class="truncate"><?= htmlspecialchars($slot['time_slot']) ?></span>
                                            <span class="font-bold text-indigo-700"><?= htmlspecialchars($slot['room_no']) ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="p-3.5 rounded-xl border border-dashed border-slate-200 bg-slate-50/30 flex flex-col items-center justify-center text-center min-h-[120px]">
                                        <span class="text-2xs font-semibold text-slate-400">Period <?= $p ?></span>
                                        <span class="text-xs text-slate-300 italic mt-1">Break / Recess</span>
                                    </div>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
