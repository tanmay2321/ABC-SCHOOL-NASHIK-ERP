<?php
/**
 * Admin: School-Wide Attendance & Morning Roll Call Oversight
 * ABC School Nashik ERP System
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('admin');

$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_class = $_GET['class'] ?? 'all';
$flash = get_flash();

// Available classes
$all_classes = [
    'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5',
    'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10',
    'Grade 11-Sci', 'Grade 11-Com', 'Grade 11-Arts',
    'Grade 12-Sci', 'Grade 12-Com', 'Grade 12-Arts'
];

// Handle Admin Quick Attendance Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    $date = $_POST['date'] ?? date('Y-m-d');
    $status_data = $_POST['status'] ?? [];
    $remarks_data = $_POST['remarks'] ?? [];
    $target_class = $_POST['target_class'] ?? '';

    $ins_upd = $pdo->prepare("
        INSERT INTO attendance (student_id, date, status, remarks, marked_by)
        VALUES (:sid, :dt, :st, :rem, :uid)
        ON CONFLICT(student_id, date) DO UPDATE SET
            status = excluded.status,
            remarks = excluded.remarks,
            marked_by = excluded.marked_by
    ");

    // Fallback if ON CONFLICT requires specific unique index
    $chk_stmt = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND date = ?");
    $upd_stmt = $pdo->prepare("UPDATE attendance SET status = ?, remarks = ?, marked_by = ? WHERE id = ?");
    $ins_stmt = $pdo->prepare("INSERT INTO attendance (student_id, date, status, remarks, marked_by) VALUES (?, ?, ?, ?, ?)");

    $count = 0;
    foreach ($status_data as $student_id => $st) {
        $rem = trim($remarks_data[$student_id] ?? '');
        $chk_stmt->execute([$student_id, $date]);
        $existing = $chk_stmt->fetch();

        if ($existing) {
            $upd_stmt->execute([$st, $rem, $_SESSION['user_id'], $existing['id']]);
        } else {
            $ins_stmt->execute([$student_id, $date, $st, $rem, $_SESSION['user_id']]);
        }
        $count++;
    }

    set_flash('success', "Attendance records updated successfully for {$count} student(s) on {$date}.");
    header("Location: /admin/attendance.php?date=" . urlencode($date) . ($target_class ? "&class=" . urlencode($target_class) : ""));
    exit;
}

// 1. Fetch Class Teachers Mapping
$class_teachers = [];
$t_stmt = $pdo->query("SELECT id, full_name, assigned_class, department, phone FROM teachers WHERE assigned_class IS NOT NULL AND assigned_class != ''");
while ($t = $t_stmt->fetch()) {
    $class_teachers[$t['assigned_class']] = $t;
}

// 2. School-wide metrics for selected date
$school_students_count = (int)$pdo->query("SELECT COUNT(*) FROM students s JOIN users u ON s.user_id = u.id WHERE u.status = 'approved'")->fetchColumn();

$att_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_marked,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    WHERE a.date = ?
");
$att_stats->execute([$selected_date]);
$stats_res = $att_stats->fetch();

$total_marked = (int)($stats_res['total_marked'] ?? 0);
$present_count = (int)($stats_res['present_count'] ?? 0);
$absent_count = (int)($stats_res['absent_count'] ?? 0);
$late_count = (int)($stats_res['late_count'] ?? 0);
$att_pct = ($total_marked > 0) ? round(($present_count / $total_marked) * 100, 1) : 0;

// 3. Class-by-Class Roll Call Completion Matrix
$class_matrix = [];
foreach ($all_classes as $cls) {
    // Count students in class
    $s_cnt = $pdo->prepare("SELECT COUNT(*) FROM students s JOIN users u ON s.user_id = u.id WHERE s.class = ? AND u.status = 'approved'");
    $s_cnt->execute([$cls]);
    $total_in_class = (int)$s_cnt->fetchColumn();

    // Count marked today
    $m_cnt = $pdo->prepare("
        SELECT 
            COUNT(*) as marked,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE s.class = ? AND a.date = ?
    ");
    $m_cnt->execute([$cls, $selected_date]);
    $m_res = $m_cnt->fetch();
    $marked_in_class = (int)($m_res['marked'] ?? 0);
    $present_in_class = (int)($m_res['present'] ?? 0);

    $class_matrix[$cls] = [
        'total' => $total_in_class,
        'marked' => $marked_in_class,
        'present' => $present_in_class,
        'teacher' => $class_teachers[$cls] ?? null,
        'status' => ($total_in_class > 0 && $marked_in_class >= $total_in_class) ? 'Completed' : (($marked_in_class > 0) ? 'Partial' : 'Pending')
    ];
}

// 4. Fetch Detailed Student Roster for selected class (or all)
$where_class = ($selected_class !== 'all') ? "AND s.class = :cls" : "";
$roster_sql = "
    SELECT s.id as student_id, s.student_code, s.full_name, s.class, s.roll_no, s.phone,
           a.id as attendance_id, a.status as attendance_status, a.remarks, a.marked_by,
           u_marker.username as marker_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN attendance a ON a.student_id = s.id AND a.date = :dt
    LEFT JOIN users u_marker ON a.marked_by = u_marker.id
    WHERE u.status = 'approved' {$where_class}
    ORDER BY s.class ASC, CAST(s.roll_no AS INTEGER) ASC, s.full_name ASC
";
$roster_stmt = $pdo->prepare($roster_sql);
$binds = ['dt' => $selected_date];
if ($selected_class !== 'all') {
    $binds['cls'] = $selected_class;
}
$roster_stmt->execute($binds);
$roster = $roster_stmt->fetchAll();

$page_title = 'Attendance Oversight - Principal';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200 mb-6">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">
                <i class="fa-solid fa-clipboard-user"></i>
                <span>School-Wide Morning Roll Call &amp; Attendance</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Principal Attendance Oversight
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Monitor morning attendance marked by class teachers across all standards and grades.
            </p>
        </div>

        <!-- Date & Class Selector Form -->
        <form method="GET" action="attendance.php" class="flex flex-wrap items-center gap-2.5">
            <div>
                <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>" 
                       onchange="this.form.submit()"
                       class="px-3.5 py-2 text-xs font-semibold rounded-xl border border-slate-300 bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600 shadow-xs">
            </div>
            <div>
                <select name="class" onchange="this.form.submit()"
                        class="px-3.5 py-2 text-xs font-semibold rounded-xl border border-slate-300 bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600 shadow-xs">
                    <option value="all" <?= $selected_class === 'all' ? 'selected' : '' ?>>All Classes (School-Wide)</option>
                    <?php foreach ($all_classes as $cls): ?>
                        <option value="<?= htmlspecialchars($cls) ?>" <?= $selected_class === $cls ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cls) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" onclick="window.print()" class="px-3.5 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs transition shadow-xs">
                <i class="fa-solid fa-print mr-1.5"></i> Print
            </button>
        </form>
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

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Enrolled</span>
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-slate-900 font-['Outfit'] mt-2"><?= $school_students_count ?></p>
            <p class="text-[11px] text-slate-400 mt-0.5">Approved students</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Present Today</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-emerald-700 font-['Outfit'] mt-2"><?= $present_count ?></p>
            <p class="text-[11px] text-emerald-600 mt-0.5"><?= $att_pct ?>% attendance rate</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Absent</span>
                <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-rose-700 font-['Outfit'] mt-2"><?= $absent_count ?></p>
            <p class="text-[11px] text-rose-500 mt-0.5">Reported absences</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Late Arrivals</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-amber-700 font-['Outfit'] mt-2"><?= $late_count ?></p>
            <p class="text-[11px] text-amber-600 mt-0.5">Recorded late</p>
        </div>

    </div>

    <!-- Morning Roll Call Matrix By Standard -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm mb-8">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 font-['Outfit'] flex items-center">
                    <i class="fa-solid fa-table-cells mr-2 text-navy-800"></i>
                    Daily Morning Roll Call Status by Class Teacher (<?= date('d M Y', strtotime($selected_date)) ?>)
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Overview of which class teachers have marked morning attendance for their assigned sections.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3.5">
            <?php foreach ($class_matrix as $cls => $info): ?>
                <?php
                    $is_completed = ($info['status'] === 'Completed');
                    $is_partial = ($info['status'] === 'Partial');
                    $bg_color = $is_completed ? 'bg-emerald-50/70 border-emerald-200' : ($is_partial ? 'bg-amber-50/70 border-amber-200' : 'bg-slate-50 border-slate-200');
                    $badge_color = $is_completed ? 'bg-emerald-100 text-emerald-800' : ($is_partial ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-600');
                ?>
                <a href="attendance.php?date=<?= urlencode($selected_date) ?>&class=<?= urlencode($cls) ?>"
                   class="p-4 rounded-2xl border transition-all hover:shadow-sm <?= $bg_color ?> <?= $selected_class === $cls ? 'ring-2 ring-navy-800' : '' ?>">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-xs text-navy-950 font-['Outfit']"><?= htmlspecialchars($cls) ?></span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $badge_color ?>">
                            <?= $info['status'] ?>
                        </span>
                    </div>

                    <div class="text-[11px] text-slate-500 space-y-1">
                        <div>
                            Class Teacher: 
                            <strong class="text-slate-700">
                                <?= $info['teacher'] ? htmlspecialchars($info['teacher']['full_name']) : '<span class="text-amber-600 italic">Not Assigned</span>' ?>
                            </strong>
                        </div>
                        <div class="flex items-center justify-between text-[11px] pt-1.5 border-t border-slate-200/60">
                            <span>Students: <strong><?= $info['total'] ?></strong></span>
                            <span class="text-emerald-700 font-bold">Present: <?= $info['present'] ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Student Attendance Roll Call Table -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 mb-4 gap-2">
            <div>
                <h3 class="text-base font-bold text-slate-900 font-['Outfit'] flex items-center">
                    <i class="fa-solid fa-list-check mr-2 text-emerald-600"></i>
                    Student Roll Call Register: <?= htmlspecialchars($selected_class === 'all' ? 'All Classes' : $selected_class) ?>
                </h3>
                <p class="text-xs text-slate-500">Date: <?= date('l, d F Y', strtotime($selected_date)) ?> • <?= count($roster) ?> Student(s) listed</p>
            </div>
        </div>

        <?php if (empty($roster)): ?>
            <div class="text-center py-12 bg-slate-50/70 rounded-2xl border border-dashed border-slate-200">
                <i class="fa-solid fa-user-slash text-3xl text-slate-300 mb-2"></i>
                <h4 class="text-sm font-bold text-slate-700">No students found</h4>
                <p class="text-xs text-slate-400 mt-1">There are no approved students registered under this class.</p>
            </div>
        <?php else: ?>
            <form method="POST" action="attendance.php" class="space-y-4">
                <input type="hidden" name="action" value="save_attendance">
                <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                <input type="hidden" name="target_class" value="<?= htmlspecialchars($selected_class) ?>">

                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-3.5">Roll</th>
                                <th class="py-3 px-3.5">Student Code</th>
                                <th class="py-3 px-3.5">Full Name</th>
                                <th class="py-3 px-3.5">Class</th>
                                <th class="py-3 px-3.5 text-center">Status</th>
                                <th class="py-3 px-3.5">Remarks / Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($roster as $r): ?>
                                <?php $current_status = $r['attendance_status'] ?? 'Present'; ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-2.5 px-3.5 font-bold text-slate-800">
                                        <?= htmlspecialchars($r['roll_no'] ?: '-') ?>
                                    </td>
                                    <td class="py-2.5 px-3.5 font-mono text-blue-700 font-semibold">
                                        <?= htmlspecialchars($r['student_code']) ?>
                                    </td>
                                    <td class="py-2.5 px-3.5 font-bold text-slate-900">
                                        <?= htmlspecialchars($r['full_name']) ?>
                                    </td>
                                    <td class="py-2.5 px-3.5">
                                        <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold text-[10px]">
                                            <?= htmlspecialchars($r['class']) ?>
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3.5 text-center whitespace-nowrap">
                                        <div class="inline-flex rounded-xl bg-slate-100 p-1 border border-slate-200">
                                            <label class="flex items-center px-2 py-1 rounded-lg text-[11px] font-bold cursor-pointer transition has-[:checked]:bg-emerald-600 has-[:checked]:text-white text-slate-600">
                                                <input type="radio" name="status[<?= $r['student_id'] ?>]" value="Present" class="sr-only" <?= $current_status === 'Present' ? 'checked' : '' ?>>
                                                Present
                                            </label>
                                            <label class="flex items-center px-2 py-1 rounded-lg text-[11px] font-bold cursor-pointer transition has-[:checked]:bg-rose-600 has-[:checked]:text-white text-slate-600">
                                                <input type="radio" name="status[<?= $r['student_id'] ?>]" value="Absent" class="sr-only" <?= $current_status === 'Absent' ? 'checked' : '' ?>>
                                                Absent
                                            </label>
                                            <label class="flex items-center px-2 py-1 rounded-lg text-[11px] font-bold cursor-pointer transition has-[:checked]:bg-amber-600 has-[:checked]:text-white text-slate-600">
                                                <input type="radio" name="status[<?= $r['student_id'] ?>]" value="Late" class="sr-only" <?= $current_status === 'Late' ? 'checked' : '' ?>>
                                                Late
                                            </label>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3.5">
                                        <input type="text" name="remarks[<?= $r['student_id'] ?>]" 
                                               value="<?= htmlspecialchars($r['remarks'] ?? '') ?>"
                                               placeholder="Optional remark..." 
                                               class="w-full px-2.5 py-1 text-xs rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-1 focus:ring-navy-600">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-3">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md shadow-emerald-700/10 transition">
                        <i class="fa-solid fa-floppy-disk mr-1.5"></i> Save / Override Daily Attendance
                    </button>
                </div>
            </form>
        <?php endif; ?>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
