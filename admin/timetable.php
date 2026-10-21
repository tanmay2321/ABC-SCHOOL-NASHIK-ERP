<?php
/**
 * Class Timetables Management - Admin Portal
 * Bellwether International School Nandurbar ERP System
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_auth(['admin', 'principal', 'vice_principal']);

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
$school_name = get_setting($pdo, 'school_name', 'BELLWETHER INTERNATIONAL SCHOOL NANDURBAR');

// Handle Timetable Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_slot') {
        $class = trim($_POST['class'] ?? '');
        $day_of_week = trim($_POST['day_of_week'] ?? '');
        $period_number = intval($_POST['period_number'] ?? 1);
        $time_slot = trim($_POST['time_slot'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $teacher_name = trim($_POST['teacher_name'] ?? '');
        $room_no = trim($_POST['room_no'] ?? 'Room 101');

        if (empty($class) || empty($day_of_week) || empty($subject) || empty($teacher_name)) {
            $_SESSION['error'] = "All fields marked with an asterisk are required.";
            header("Location: /admin/timetable.php?class=" . urlencode($class));
            exit;
        }

        // Check if slot already occupied for this class and day
        $check = $pdo->prepare("SELECT id FROM timetables WHERE class = ? AND day_of_week = ? AND period_number = ?");
        $check->execute([$class, $day_of_week, $period_number]);
        $existing = $check->fetch();

        if ($existing) {
            $upd = $pdo->prepare("UPDATE timetables SET time_slot = ?, subject = ?, teacher_name = ?, room_no = ? WHERE id = ?");
            $upd->execute([$time_slot, $subject, $teacher_name, $room_no, $existing['id']]);
            $_SESSION['success'] = "Updated Period {$period_number} for {$class} on {$day_of_week}.";
        } else {
            $ins = $pdo->prepare("INSERT INTO timetables (class, day_of_week, period_number, time_slot, subject, teacher_name, room_no) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$class, $day_of_week, $period_number, $time_slot, $subject, $teacher_name, $room_no]);
            $_SESSION['success'] = "Added Period {$period_number} ({$subject}) to {$class} timetable.";
        }

        header("Location: /admin/timetable.php?class=" . urlencode($class));
        exit;
    }

    if ($action === 'delete_slot') {
        $slot_id = intval($_POST['slot_id'] ?? 0);
        $ret_class = trim($_POST['return_class'] ?? 'Class 10-A');
        if ($slot_id > 0) {
            $del = $pdo->prepare("DELETE FROM timetables WHERE id = ?");
            $del->execute([$slot_id]);
            $_SESSION['success'] = "Timetable slot removed successfully.";
        }
        header("Location: /admin/timetable.php?class=" . urlencode($ret_class));
        exit;
    }
}

// Available classes in system
$classes_query = $pdo->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' UNION SELECT DISTINCT class FROM timetables ORDER BY class");
$all_classes = $classes_query->fetchAll(PDO::FETCH_COLUMN);
if (empty($all_classes)) {
    $all_classes = ['Class 10-A', 'Class 10-B', 'Class 9-A', 'Class 9-B'];
}

$selected_class = $_GET['class'] ?? $all_classes[0];

// Fetch timetable entries for selected class
$ttStmt = $pdo->prepare("SELECT * FROM timetables WHERE class = ? ORDER BY period_number ASC");
$ttStmt->execute([$selected_class]);
$raw_slots = $ttStmt->fetchAll();

// Group by day of week
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$timetable_grid = [];
foreach ($days as $d) {
    $timetable_grid[$d] = [];
}
foreach ($raw_slots as $slot) {
    if (isset($timetable_grid[$slot['day_of_week']])) {
        $timetable_grid[$slot['day_of_week']][$slot['period_number']] = $slot;
    }
}

// Fetch list of teachers for dropdown
$teachers_list = $pdo->query("SELECT full_name, department FROM teachers ORDER BY full_name")->fetchAll();

$page_title = "Class Timetables - Admin Portal";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">
<div class="space-y-6">
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200 no-print">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-calendar-days text-indigo-600"></i>
                Class Timetable Management
            </h1>
            <p class="text-sm text-slate-500">Configure weekly periods, assign faculty subjects, and allocate classrooms.</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="document.getElementById('addSlotModal').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg inline-flex items-center gap-2 shadow-sm transition">
                <i class="fa-solid fa-plus"></i> Add / Edit Period Slot
            </button>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between no-print">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between no-print">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Class Selector Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-2 border-b border-slate-200 no-print">
        <span class="text-xs font-bold uppercase text-slate-400 mr-2 shrink-0">Select Class:</span>
        <?php foreach ($all_classes as $cls): ?>
            <a href="/admin/timetable.php?class=<?= urlencode($cls) ?>" class="px-4 py-2 rounded-xl text-xs font-bold transition shrink-0 <?= $selected_class === $cls ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
                <?= htmlspecialchars($cls) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Clean Printable Sheet Letterhead (Visible only on Print) -->
    <div class="hidden print:block p-4 border-b-2 border-slate-900 text-center mb-4">
        <h2 class="text-base font-black uppercase text-slate-900 font-['Outfit']"><?= htmlspecialchars($school_name) ?></h2>
        <p class="text-xs font-bold text-indigo-700 uppercase">Official Master Timetable &bull; Academic Schedule</p>
        <p class="text-xs text-slate-600 mt-1">Class / Section: <strong><?= htmlspecialchars($selected_class) ?></strong> &bull; Printed: <strong><?= date('d M Y') ?></strong></p>
    </div>

    <!-- Timetable Grid -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
            <div>
                <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-calendar-week text-indigo-600"></i>
                    Weekly Schedule for <span class="text-indigo-600"><?= htmlspecialchars($selected_class) ?></span>
                </h2>
                <p class="text-xs text-slate-500">6 periods per day (Monday to Saturday)</p>
            </div>
            <button onclick="window.print()" class="no-print px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-100 inline-flex items-center gap-1.5 transition">
                <i class="fa-solid fa-print"></i> Print Timetable
            </button>
        </div>

        <div class="p-5 overflow-x-auto">
            <div class="space-y-6">
                <?php foreach ($days as $day): 
                    $day_slots = $timetable_grid[$day] ?? [];
                ?>
                    <div class="border border-slate-200 rounded-xl overflow-hidden">
                        <div class="px-4 py-2.5 bg-slate-100 border-b border-slate-200 font-bold text-xs uppercase tracking-wider text-slate-700 flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <i class="fa-regular fa-calendar text-indigo-600"></i>
                                <?= $day ?>
                            </span>
                            <span class="text-2xs font-semibold text-slate-400">
                                <?= count($day_slots) ?> Periods Scheduled
                            </span>
                        </div>

                        <div class="p-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                            <?php for ($p = 1; $p <= 6; $p++): 
                                $slot = $day_slots[$p] ?? null;
                            ?>
                                <div class="p-3 rounded-lg border <?= $slot ? 'bg-indigo-50/40 border-indigo-200' : 'bg-slate-50 border-dashed border-slate-200 text-center flex flex-col items-center justify-center' ?> min-h-[110px] relative group transition hover:shadow-xs">
                                    <div class="flex items-center justify-between text-2xs font-semibold text-slate-400 mb-1 w-full">
                                        <span>Period <?= $p ?></span>
                                        <?php if ($slot): ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('Remove Period <?= $p ?> on <?= $day ?>?')">
                                                <input type="hidden" name="action" value="delete_slot">
                                                <input type="hidden" name="slot_id" value="<?= $slot['id'] ?>">
                                                <input type="hidden" name="return_class" value="<?= htmlspecialchars($selected_class) ?>">
                                                <button type="submit" class="text-slate-400 hover:text-rose-600 transition" title="Delete slot">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($slot): ?>
                                        <div class="font-bold text-slate-800 text-sm leading-snug">
                                            <?= htmlspecialchars($slot['subject']) ?>
                                        </div>
                                        <div class="text-xs text-indigo-700 font-medium mt-1 truncate">
                                            <i class="fa-solid fa-user-tie mr-1 text-indigo-400"></i><?= htmlspecialchars($slot['teacher_name']) ?>
                                        </div>
                                        <div class="flex items-center justify-between mt-2 pt-1 border-t border-indigo-100 text-2xs text-slate-500">
                                            <span class="truncate"><?= htmlspecialchars($slot['time_slot']) ?></span>
                                            <span class="font-semibold text-slate-700 bg-white px-1.5 py-0.5 rounded border border-slate-200"><?= htmlspecialchars($slot['room_no']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-xs text-slate-400 italic">No Class</div>
                                        <button onclick="openAddSlotModal('<?= $day ?>', <?= $p ?>)" class="mt-2 text-2xs font-semibold text-indigo-600 hover:text-indigo-800">
                                            + Assign
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ADD / EDIT PERIOD SLOT -->
<div id="addSlotModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-clock text-indigo-600"></i>
                Assign Timetable Period
            </h3>
            <button onclick="document.getElementById('addSlotModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_slot">
            <input type="hidden" name="class" value="<?= htmlspecialchars($selected_class) ?>">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Day of Week *</label>
                    <select name="day_of_week" id="modal_day" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <?php foreach ($days as $d): ?>
                            <option value="<?= $d ?>"><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Period Number *</label>
                    <select name="period_number" id="modal_period" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?= $i ?>">Period <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Time Slot *</label>
                <input type="text" name="time_slot" id="modal_time_slot" required value="08:30 AM - 09:20 AM" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Subject Name *</label>
                <input type="text" name="subject" id="modal_subject" required placeholder="e.g. Mathematics, Science (Physics)" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Teacher *</label>
                    <select name="teacher_name" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <?php foreach ($teachers_list as $t): ?>
                            <option value="<?= htmlspecialchars($t['full_name']) ?>"><?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['department']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Room / Lab No.</label>
                    <input type="text" name="room_no" value="Room 101" placeholder="e.g. Room 101, Physics Lab" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('addSlotModal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-xs">Save Period Slot</button>
            </div>
        </form>
    </div>
</div>

<script>
const standardTimes = {
    1: '08:30 AM - 09:20 AM',
    2: '09:20 AM - 10:10 AM',
    3: '10:25 AM - 11:15 AM',
    4: '11:15 AM - 12:05 PM',
    5: '12:45 PM - 01:30 PM',
    6: '01:30 PM - 02:15 PM'
};

function openAddSlotModal(day, period) {
    document.getElementById('modal_day').value = day;
    document.getElementById('modal_period').value = period;
    if (standardTimes[period]) {
        document.getElementById('modal_time_slot').value = standardTimes[period];
    }
    document.getElementById('addSlotModal').classList.remove('hidden');
}

document.getElementById('modal_period').addEventListener('change', function() {
    const p = this.value;
    if (standardTimes[p]) {
        document.getElementById('modal_time_slot').value = standardTimes[p];
    }
});
</script>

</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
