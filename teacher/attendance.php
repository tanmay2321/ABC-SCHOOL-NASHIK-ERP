<?php
/**
 * Teacher: Attendance Register
 * ABC School Nashik ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('teacher');

$teacher_user_id = $_SESSION['user_id'];
$date = $_GET['date'] ?? date('Y-m-d');
$class = $_GET['class'] ?? 'Class 10-A';

// Handle Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    $att_date = $_POST['date'] ?? date('Y-m-d');
    $att_class = $_POST['class'] ?? 'Class 10-A';
    $status_data = $_POST['status'] ?? []; // student_id => 'Present'/'Absent'/'Late'
    $remarks_data = $_POST['remarks'] ?? [];

    try {
        $pdo->beginTransaction();

        foreach ($status_data as $student_id => $status) {
            $student_id = (int)$student_id;
            $remark = trim($remarks_data[$student_id] ?? '');

            // Check if attendance already recorded for this student & date
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

        $pdo->commit();
        set_flash('success', "Attendance for {$att_class} on " . date('d M Y', strtotime($att_date)) . " has been saved successfully!");
        header("Location: /teacher/attendance.php?class=" . urlencode($att_class) . "&date=" . urlencode($att_date));
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to save attendance: ' . $e->getMessage();
    }
}

// Fetch classes
$classes = $pdo->query("SELECT DISTINCT class FROM students ORDER BY class ASC")->fetchAll(PDO::FETCH_COLUMN);

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
                Mark student physical or online presence for ABC School Nashik academic records.
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

    <!-- Class & Date Selector Card -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm mb-6">
        <form method="GET" action="attendance.php" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Select Class / Grade</label>
                <select name="class" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= ($class === $c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
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
                        <p class="text-xs text-slate-500">Date: <?= date('d M Y', strtotime($date)) ?> &bull; <?= count($students) ?> Students in list</p>
                    </div>
                </div>

                <!-- Quick Action: Mark All Present -->
                <div>
                    <button type="button" onclick="markAll('Present')" class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold text-xs transition">
                        <i class="fa-solid fa-check-double mr-1"></i> Quick: Mark All Present
                    </button>
                </div>
            </div>

            <?php if (empty($students)): ?>
                <div class="p-8 text-center text-slate-500 text-xs">
                    No approved students found in <?= htmlspecialchars($class) ?>.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-200">
                                <th class="py-3 px-4 sm:px-6">Roll No</th>
                                <th class="py-3 px-4">Student Name</th>
                                <th class="py-3 px-4">Student ID</th>
                                <th class="py-3 px-4 sm:px-6 text-center">Status</th>
                                <th class="py-3 px-4 sm:px-6">Remarks (Optional)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            <?php foreach ($students as $s): ?>
                                <?php 
                                    $current_status = $recorded_attendance[$s['id']]['status'] ?? 'Present'; 
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
                                    <td class="py-3.5 px-4 sm:px-6 text-center">
                                        <div class="inline-flex rounded-xl p-1 bg-slate-100 border border-slate-200 space-x-1">
                                            
                                            <!-- Present -->
                                            <label class="cursor-pointer">
                                                <input type="radio" name="status[<?= $s['id'] ?>]" value="Present" class="sr-only peer status-present" <?= ($current_status === 'Present') ? 'checked' : '' ?>>
                                                <span class="px-3 py-1 rounded-lg text-xs font-bold block transition peer-checked:bg-emerald-600 peer-checked:text-white text-slate-600 hover:bg-slate-200">
                                                    Present
                                                </span>
                                            </label>

                                            <!-- Absent -->
                                            <label class="cursor-pointer">
                                                <input type="radio" name="status[<?= $s['id'] ?>]" value="Absent" class="sr-only peer status-absent" <?= ($current_status === 'Absent') ? 'checked' : '' ?>>
                                                <span class="px-3 py-1 rounded-lg text-xs font-bold block transition peer-checked:bg-rose-600 peer-checked:text-white text-slate-600 hover:bg-slate-200">
                                                    Absent
                                                </span>
                                            </label>

                                            <!-- Late -->
                                            <label class="cursor-pointer">
                                                <input type="radio" name="status[<?= $s['id'] ?>]" value="Late" class="sr-only peer status-late" <?= ($current_status === 'Late') ? 'checked' : '' ?>>
                                                <span class="px-3 py-1 rounded-lg text-xs font-bold block transition peer-checked:bg-amber-500 peer-checked:text-white text-slate-600 hover:bg-slate-200">
                                                    Late
                                                </span>
                                            </label>

                                        </div>
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

                <div class="p-4 sm:p-6 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <span class="text-xs text-slate-500">
                        Attendance will be immediately accessible on Student &amp; Parent Dashboards.
                    </span>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md transition flex items-center space-x-2">
                        <i class="fa-solid fa-floppy-disk text-amber-400"></i>
                        <span>Save &amp; Submit Attendance</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </form>

</main>

<script>
    function markAll(status) {
        if (status === 'Present') {
            document.querySelectorAll('.status-present').forEach(el => el.checked = true);
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
