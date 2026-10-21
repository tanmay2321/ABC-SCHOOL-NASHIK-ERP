<?php
/**
 * Teacher: Attendance Register
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('teacher');

$teacher_user_id = $_SESSION['user_id'];

// Fetch teacher profile to get their assigned class
$tProfileStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$tProfileStmt->execute([$teacher_user_id]);
$teacher_profile = $tProfileStmt->fetch();
$assigned_class = $teacher_profile['assigned_class'] ?? null;
$school_name = get_setting($pdo, 'school_name', 'BELLWETHER INTERNATIONAL SCHOOL NANDURBAR');

// Fetch classes
$classes = $pdo->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($classes)) {
    $classes = ['Class 10-A', 'Class 10-B', 'Class 9-A', 'Class 9-B', 'Class 8-A', 'Class 8-B'];
}
if (!empty($assigned_class) && !in_array($assigned_class, $classes)) {
    array_unshift($classes, $assigned_class);
}

// Default class: use teacher's assigned class if set, otherwise default to first class
$default_class = (!empty($assigned_class)) ? $assigned_class : $classes[0];
$class = $_GET['class'] ?? $default_class;
$date = $_GET['date'] ?? date('Y-m-d');

// Check designated Class Teacher for the selected class
$ctStmt = $pdo->prepare("SELECT full_name FROM teachers WHERE assigned_class = ? LIMIT 1");
$ctStmt->execute([$class]);
$designated_ct_name = $ctStmt->fetchColumn();

// Handle Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    $att_date = $_POST['date'] ?? date('Y-m-d');
    $att_class = $_POST['class'] ?? 'Class 10-A';
    $all_students = $_POST['all_students'] ?? [];
    $present_data = $_POST['present'] ?? []; // student_id => "1"
    $remarks_data = $_POST['remarks'] ?? [];

    // Fallback if legacy form status array submitted
    $legacy_status = $_POST['status'] ?? [];

    try {
        $pdo->beginTransaction();

        // If all_students is passed from tickbox sheet
        if (!empty($all_students)) {
            foreach ($all_students as $student_id) {
                $student_id = (int)$student_id;
                $status = isset($present_data[$student_id]) ? 'Present' : 'Absent';
                $remark = trim($remarks_data[$student_id] ?? '');

                $check = $pdo->prepare("SELECT id FROM attendance WHERE student_id = :sid AND date = :dt LIMIT 1");
                $check->execute(['sid' => $student_id, 'dt' => $att_date]);
                $existing_id = $check->fetchColumn();

                if ($existing_id) {
                    $uStmt = $pdo->prepare("UPDATE attendance SET status = :st, remarks = :rmk, marked_by = :mb WHERE id = :id");
                    $uStmt->execute(['st' => $status, 'rmk' => $remark, 'mb' => $teacher_user_id, 'id' => $existing_id]);
                } else {
                    $iStmt = $pdo->prepare("INSERT INTO attendance (student_id, date, status, remarks, marked_by) VALUES (:sid, :dt, :st, :rmk, :mb)");
                    $iStmt->execute(['sid' => $student_id, 'dt' => $att_date, 'st' => $status, 'rmk' => $remark, 'mb' => $teacher_user_id]);
                }
            }
        } elseif (!empty($legacy_status)) {
            foreach ($legacy_status as $student_id => $status) {
                $student_id = (int)$student_id;
                $remark = trim($remarks_data[$student_id] ?? '');

                $check = $pdo->prepare("SELECT id FROM attendance WHERE student_id = :sid AND date = :dt LIMIT 1");
                $check->execute(['sid' => $student_id, 'dt' => $att_date]);
                $existing_id = $check->fetchColumn();

                if ($existing_id) {
                    $uStmt = $pdo->prepare("UPDATE attendance SET status = :st, remarks = :rmk, marked_by = :mb WHERE id = :id");
                    $uStmt->execute(['st' => $status, 'rmk' => $remark, 'mb' => $teacher_user_id, 'id' => $existing_id]);
                } else {
                    $iStmt = $pdo->prepare("INSERT INTO attendance (student_id, date, status, remarks, marked_by) VALUES (:sid, :dt, :st, :rmk, :mb)");
                    $iStmt->execute(['sid' => $student_id, 'dt' => $att_date, 'st' => $status, 'rmk' => $remark, 'mb' => $teacher_user_id]);
                }
            }
        }

        $pdo->commit();
        $p_count = count($present_data);
        $a_count = max(0, count($all_students) - $p_count);
        set_flash('success', "Attendance for {$att_class} on " . date('d M Y', strtotime($att_date)) . " saved successfully! ({$p_count} Present, {$a_count} Absent)");
        header("Location: /teacher/attendance.php?class=" . urlencode($att_class) . "&date=" . urlencode($att_date));
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to save attendance: ' . $e->getMessage();
    }
}


// Fetch students for selected class
$sStmt = $pdo->prepare("
    SELECT s.*, u.status as user_status
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.class = :class AND u.status = 'approved'
    ORDER BY s.roll_no ASC, s.full_name ASC
");
$sStmt->execute(['class' => $class]);
$students = $sStmt->fetchAll();

// Fetch already recorded attendance for this date & class
$attStmt = $pdo->prepare("
    SELECT student_id, status, remarks
    FROM attendance
    WHERE date = :dt
");
$attStmt->execute(['dt' => $date]);
$recorded_attendance = [];
while ($row = $attStmt->fetch()) {
    $recorded_attendance[$row['student_id']] = [
        'status' => $row['status'],
        'remarks' => $row['remarks']
    ];
}

$flash = get_flash();
?>

<?php 
$page_title = 'Attendance Register';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php'; 
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
                <span>Daily Roll Call</span>
                <span>&bull;</span>
                <span class="text-slate-400"><?= date('d F Y', strtotime($date)) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Student Attendance Register
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Mark student physical or online presence for Bellwether International School academic records.
            </p>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-semibold flex items-center justify-between auto-dismiss-alert">
            <div class="flex items-center space-x-2.5">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                <span><?= $flash['message'] ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Class Teacher Status Banner -->
    <?php if (!empty($assigned_class) && $assigned_class === $class): ?>
        <div class="mb-6 p-4 rounded-2xl bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-300 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 no-print">
            <div class="flex items-center space-x-3.5">
                <div class="w-11 h-11 rounded-2xl bg-amber-500 text-white flex items-center justify-center font-bold text-lg shadow-md shadow-amber-500/20">
                    <i class="fa-solid fa-chalkboard-user"></i>
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-200 text-amber-900">
                            Class Teacher Responsibility
                        </span>
                        <span class="text-xs text-amber-700 font-semibold">Morning Roll Call</span>
                    </div>
                    <h2 class="text-sm font-bold text-amber-950 mt-0.5">
                        You are the Class Teacher for <?= htmlspecialchars($class) ?>
                    </h2>
                    <p class="text-xs text-amber-800/90">
                        Please take the morning attendance for your class every day to maintain CBSE 75% compliance records.
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-2 self-start sm:self-auto">
                <span class="px-3 py-1.5 rounded-xl bg-white border border-amber-300 text-amber-900 font-bold text-xs shadow-2xs">
                    <i class="fa-regular fa-clock mr-1 text-amber-600"></i> <?= date('d M Y', strtotime($date)) ?>
                </span>
            </div>
        </div>
    <?php elseif (!empty($assigned_class)): ?>
        <div class="mb-6 p-3.5 rounded-2xl bg-blue-50 border border-blue-200 text-blue-900 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 no-print">
            <div class="flex items-center space-x-2.5">
                <i class="fa-solid fa-circle-info text-blue-600 text-base"></i>
                <span>You are the designated Class Teacher for <strong><?= htmlspecialchars($assigned_class) ?></strong>. Designated Class Teacher for <?= htmlspecialchars($class) ?> is <strong><?= htmlspecialchars($designated_ct_name ?: 'Not alloted') ?></strong>.</span>
            </div>
            <a href="/teacher/attendance.php?class=<?= urlencode($assigned_class) ?>&date=<?= urlencode($date) ?>" class="inline-flex items-center px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs transition shadow-xs self-start sm:self-auto">
                Go to My Class (<?= htmlspecialchars($assigned_class) ?>) &rarr;
            </a>
        </div>
    <?php endif; ?>

    <!-- Class & Date Selector Card -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm mb-6 no-print">
        <form method="GET" action="attendance.php" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Select Class / Grade</label>
                <select name="class" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= ($class === $c) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?> <?= (!empty($assigned_class) && $assigned_class === $c) ? '⭐ (My Assigned Class)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Attendance Date</label>
                <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
            </div>

            <div>
                <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md transition flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-arrows-rotate text-amber-400"></i>
                    <span>Load Attendance Register</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Attendance Sheet Form -->
    <form method="POST" action="attendance.php">
        <input type="hidden" name="submit_attendance" value="1">
        <input type="hidden" name="class" value="<?= htmlspecialchars($class) ?>">
        <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">

        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden mb-6">
            <div class="p-4 sm:p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/70">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Roster for <?= htmlspecialchars($class) ?></h2>
                        <p class="text-xs text-slate-500">
                            Date: <?= date('d M Y', strtotime($date)) ?> &bull; 
                            <span id="attendanceSummaryCounter" class="font-medium text-slate-700">
                                <?= count($students) ?> Students enrolled
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Quick Action: Tick/Untick All & Print -->
                <div class="flex flex-wrap items-center gap-2 no-print">
                    <button type="button" onclick="setAllPresent(true)" class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 font-bold text-xs transition flex items-center space-x-1.5 shadow-2xs">
                        <i class="fa-solid fa-square-check text-emerald-600"></i>
                        <span>Tick All Present</span>
                    </button>
                    <button type="button" onclick="setAllPresent(false)" class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-300 font-bold text-xs transition flex items-center space-x-1.5 shadow-2xs">
                        <i class="fa-regular fa-square text-rose-600"></i>
                        <span>Untick All (Absent)</span>
                    </button>
                    <button type="button" onclick="window.print()" class="px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 font-bold text-xs transition flex items-center space-x-1.5 shadow-2xs">
                        <i class="fa-solid fa-print text-navy-800"></i>
                        <span>Print Sheet</span>
                    </button>
                </div>
            </div>

            <!-- Clean Printable Sheet Letterhead (Visible only on Print) -->
            <div class="hidden print:block p-4 border-b-2 border-slate-900 text-center">
                <h2 class="text-base font-black uppercase text-slate-900 font-['Outfit']"><?= htmlspecialchars($school_name) ?></h2>
                <p class="text-xs font-bold text-amber-700 uppercase">Daily Attendance Register &bull; CBSE Compliance</p>
                <p class="text-xs text-slate-600 mt-1">Class / Section: <strong><?= htmlspecialchars($class) ?></strong> &bull; Date: <strong><?= date('d M Y', strtotime($date)) ?></strong> &bull; Teacher: <strong><?= htmlspecialchars($teacher_profile['full_name'] ?? 'Faculty') ?></strong></p>
            </div>

            <?php if (empty($students)): ?>
                <div class="p-8 text-center text-slate-500 text-xs">
                    No approved students found in <?= htmlspecialchars($class) ?>.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                <th class="py-3 px-4 sm:px-6">Roll No</th>
                                <th class="py-3 px-4">Student Name</th>
                                <th class="py-3 px-4">Student ID</th>
                                <th class="py-3 px-4 sm:px-6 text-center">
                                    <div class="inline-flex items-center space-x-2">
                                        <input type="checkbox" id="masterToggle" title="Master Toggle: Tick/Untick All" 
                                               class="w-4 h-4 rounded text-emerald-600 accent-emerald-600 cursor-pointer"
                                               onchange="setAllPresent(this.checked)">
                                        <span>Status (Tick = Present)</span>
                                    </div>
                                </th>
                                <th class="py-3 px-4 sm:px-6">Remarks (Optional)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            <?php foreach ($students as $s): ?>
                                <?php 
                                    $has_record = isset($recorded_attendance[$s['id']]);
                                    $is_present = $has_record ? ($recorded_attendance[$s['id']]['status'] === 'Present') : true;
                                    $current_remarks = $recorded_attendance[$s['id']]['remarks'] ?? '';
                                ?>
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 px-4 sm:px-6 font-bold text-slate-900">
                                        <?= htmlspecialchars($s['roll_no'] ?: 'N/A') ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-900"><?= htmlspecialchars($s['full_name']) ?></div>
                                        <div class="text-[11px] text-slate-500">Guardian: <?= htmlspecialchars($s['guardian_name'] ?: 'N/A') ?></div>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono font-bold text-blue-700">
                                        <?= htmlspecialchars($s['student_code']) ?>
                                    </td>
                                    <td class="py-3.5 px-4 sm:px-6 text-center whitespace-nowrap">
                                        <input type="hidden" name="all_students[]" value="<?= $s['id'] ?>">
                                        <label class="inline-flex items-center space-x-2.5 cursor-pointer select-none group py-1 px-2 rounded-xl hover:bg-slate-100 transition">
                                            <input type="checkbox" 
                                                   name="present[<?= $s['id'] ?>]" 
                                                   value="1" 
                                                   class="att-checkbox w-5 h-5 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300 accent-emerald-600 cursor-pointer"
                                                   <?= $is_present ? 'checked' : '' ?>
                                                   onchange="updateStatusBadge(this)">
                                            
                                            <span class="status-badge px-3 py-1 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 <?= $is_present ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-rose-100 text-rose-800 border border-rose-300' ?>">
                                                <i class="badge-icon fa-solid <?= $is_present ? 'fa-check text-emerald-700' : 'fa-xmark text-rose-700' ?>"></i>
                                                <span class="badge-text"><?= $is_present ? 'Present' : 'Absent' ?></span>
                                            </span>
                                        </label>
                                    </td>
                                    <td class="py-3.5 px-4 sm:px-6">
                                        <input type="text" name="remarks[<?= $s['id'] ?>]" value="<?= htmlspecialchars($current_remarks) ?>" placeholder="Optional note..." 
                                               class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs bg-slate-50 focus:bg-white focus:outline-none">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 sm:p-6 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 no-print">
                    <span class="text-xs text-slate-500">
                        <i class="fa-solid fa-circle-info text-blue-500 mr-1"></i>
                        Checked boxes indicate <strong>Present</strong>. Unchecked boxes indicate <strong>Absent</strong>.
                    </span>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md transition flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-floppy-disk text-amber-400"></i>
                        <span>Save &amp; Submit Attendance</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </form>

</main>

<script>
    function updateStatusBadge(checkbox) {
        const badge = checkbox.closest('label').querySelector('.status-badge');
        const icon = badge.querySelector('.badge-icon');
        const text = badge.querySelector('.badge-text');
        if (checkbox.checked) {
            badge.className = 'status-badge px-3 py-1 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 bg-emerald-100 text-emerald-800 border border-emerald-300';
            icon.className = 'badge-icon fa-solid fa-check text-emerald-700';
            text.textContent = 'Present';
        } else {
            badge.className = 'status-badge px-3 py-1 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 bg-rose-100 text-rose-800 border border-rose-300';
            icon.className = 'badge-icon fa-solid fa-xmark text-rose-700';
            text.textContent = 'Absent';
        }
        updateCounter();
    }

    function setAllPresent(val) {
        document.querySelectorAll('.att-checkbox').forEach(cb => {
            cb.checked = val;
            updateStatusBadge(cb);
        });
        const master = document.getElementById('masterToggle');
        if (master) master.checked = val;
        updateCounter();
    }

    function updateCounter() {
        const total = document.querySelectorAll('.att-checkbox').length;
        const present = document.querySelectorAll('.att-checkbox:checked').length;
        const absent = total - present;
        const counterEl = document.getElementById('attendanceSummaryCounter');
        if (counterEl) {
            counterEl.innerHTML = `<span class="text-emerald-700 font-bold">${present} Present</span> &bull; <span class="text-rose-700 font-bold">${absent} Absent</span> (Total: ${total})`;
        }
    }

    document.addEventListener('DOMContentLoaded', updateCounter);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
