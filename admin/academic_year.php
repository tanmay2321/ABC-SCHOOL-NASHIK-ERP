<?php
/**
 * Admin / Principal: Academic Year Shift & Student Promotion
 * Annual batch promotion to next grade and new academic calendar transition
 * Bellwether International School ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth(['admin', 'principal']);

$flash = get_flash();
$current_academic_year = get_setting($pdo, 'current_academic_year', '2026-2027');

// Standard progressive promotion helper map
function get_suggested_next_class($class) {
    $class = trim($class);
    // Patterns like "Class 9-A" -> "Class 10-A"
    if (preg_match('/^Class\s+(\d+)(.*)$/i', $class, $matches)) {
        $num = (int)$matches[1];
        $suffix = $matches[2];
        if ($num >= 12) return 'Alumni / Graduated';
        if ($num == 10) return 'Class 11' . $suffix;
        return 'Class ' . ($num + 1) . $suffix;
    }
    // Patterns like "Grade 9" -> "Grade 10"
    if (preg_match('/^Grade\s+(\d+)(.*)$/i', $class, $matches)) {
        $num = (int)$matches[1];
        $suffix = $matches[2];
        if ($num >= 12) return 'Alumni / Graduated';
        return 'Grade ' . ($num + 1) . $suffix;
    }
    return $class . ' (Promoted)';
}

// Available classes in system
$classes = $pdo->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($classes)) {
    $classes = ['Class 10-A', 'Class 10-B', 'Class 9-A', 'Class 9-B', 'Class 8-A', 'Class 8-B'];
}

$selected_source_class = $_GET['source_class'] ?? ($classes[0] ?? 'Class 9-A');
$suggested_target = get_suggested_next_class($selected_source_class);

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Update Global Active Academic Year Setting
    if ($action === 'update_year') {
        $new_year = trim($_POST['academic_year'] ?? '');
        if (!empty($new_year)) {
            set_setting($pdo, 'current_academic_year', $new_year);
            set_flash('success', "Active Academic Year shifted to {$new_year}! All dashboards, LMS courses, and reports are now aligned to {$new_year}.");
        }
        header("Location: /admin/academic_year.php");
        exit;
    }

    // 2. Promote Selected Class Students
    if ($action === 'promote_students') {
        $src_class = trim($_POST['source_class'] ?? '');
        $tgt_class = trim($_POST['target_class'] ?? '');
        $target_year = trim($_POST['target_year'] ?? $current_academic_year);
        $student_ids = $_POST['student_ids'] ?? [];

        if (empty($src_class) || empty($tgt_class)) {
            set_flash('danger', 'Please specify both source and target class.');
        } elseif (empty($student_ids)) {
            set_flash('danger', 'Please select at least one student to promote.');
        } else {
            try {
                $pdo->beginTransaction();

                $ins_hist = $pdo->prepare("
                    INSERT INTO promotion_history (student_id, from_class, to_class, academic_year, promoted_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $upd_student = $pdo->prepare("UPDATE students SET class = ? WHERE id = ?");

                $promoted_count = 0;
                foreach ($student_ids as $sid) {
                    $sid = (int)$sid;
                    if ($sid > 0) {
                        $ins_hist->execute([$sid, $src_class, $tgt_class, $target_year, $_SESSION['user_id']]);
                        $upd_student->execute([$tgt_class, $sid]);
                        $promoted_count++;
                    }
                }

                $pdo->commit();
                set_flash('success', "Promoted {$promoted_count} student(s) from {$src_class} to {$tgt_class} for Academic Year {$target_year}! Their LMS dashboards have been updated.");
                header("Location: /admin/academic_year.php?source_class=" . urlencode($tgt_class));
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                set_flash('danger', "Promotion failed: " . $e->getMessage());
            }
        }
    }
}

// Fetch students in selected source class
$stu_stmt = $pdo->prepare("
    SELECT s.*, u.status as user_status
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.class = ? AND u.status = 'approved'
    ORDER BY CAST(s.roll_no AS INTEGER) ASC, s.full_name ASC
");
$stu_stmt->execute([$selected_source_class]);
$source_students = $stu_stmt->fetchAll();

// Fetch recent promotion logs
$history = $pdo->query("
    SELECT ph.*, s.full_name, s.student_code, u.username as promoter_name
    FROM promotion_history ph
    JOIN students s ON ph.student_id = s.id
    LEFT JOIN users u ON ph.promoted_by = u.id
    ORDER BY ph.id DESC LIMIT 25
")->fetchAll();

$page_title = 'Academic Year Shift & Promotion';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 mb-6">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Annual Session Transition</span>
                <span>&bull;</span>
                <span class="text-amber-600">Current AY: <?= htmlspecialchars($current_academic_year) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Academic Year Shift &amp; Student Promotion
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Once a year, promote students to their next standard and advance the active academic calendar for all LMS dashboards.
            </p>
        </div>

        <div class="flex items-center space-x-2">
            <button type="button" onclick="openModal('shiftYearModal')" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md transition">
                <i class="fa-solid fa-calendar-plus mr-2 text-amber-400"></i> Change Academic Year
            </button>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl bg-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-50 border border-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-200 text-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-900 text-xs font-semibold flex items-center justify-between shadow-xs">
            <div class="flex items-center space-x-2.5">
                <i class="fa-solid <?= $flash['type'] === 'danger' ? 'fa-triangle-exclamation' : 'fa-circle-check' ?> text-base"></i>
                <span><?= $flash['message'] ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Overview Banners -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        
        <div class="bg-gradient-to-br from-navy-950 to-navy-800 rounded-3xl p-6 text-white shadow-md relative overflow-hidden">
            <div class="absolute -right-4 -bottom-4 text-white/5 text-8xl font-black">AY</div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400">Current Session</span>
            <p class="text-3xl font-extrabold font-['Outfit'] mt-1 text-white"><?= htmlspecialchars($current_academic_year) ?></p>
            <p class="text-xs text-slate-300 mt-1">All grade reports, attendance records, and timetables are active under this session.</p>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Enrolled Students</span>
                <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-user-graduate text-base"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 font-['Outfit'] mt-2">
                <?= (int)$pdo->query("SELECT COUNT(*) FROM students s JOIN users u ON s.user_id = u.id WHERE u.status = 'approved'")->fetchColumn() ?>
            </p>
            <p class="text-[11px] text-slate-400 mt-1">Across all standards and sections</p>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Promotions Executed</span>
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-arrow-trend-up text-base"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-emerald-700 font-['Outfit'] mt-2">
                <?= (int)$pdo->query("SELECT COUNT(*) FROM promotion_history")->fetchColumn() ?>
            </p>
            <p class="text-[11px] text-emerald-600 mt-1">Recorded promotion history records</p>
        </div>

    </div>

    <!-- Main Promotion Workflow Section -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 mb-6 gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-900 font-['Outfit'] flex items-center">
                    <i class="fa-solid fa-graduation-cap text-amber-500 mr-2"></i>
                    Batch Promotion by Class Section
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Select a class section, verify student roster, choose target grade, and promote with 1 click.</p>
            </div>

            <!-- Class Selector Filter -->
            <form method="GET" action="academic_year.php" class="flex items-center space-x-2">
                <label class="text-xs font-bold text-slate-600 uppercase">Select Class:</label>
                <select name="source_class" onchange="this.form.submit()" class="px-3.5 py-1.5 rounded-xl border border-slate-300 bg-slate-50 text-xs font-bold text-slate-800 focus:outline-none">
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= ($selected_source_class === $c) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (empty($source_students)): ?>
            <div class="text-center py-10 bg-slate-50/70 rounded-2xl border border-dashed border-slate-200">
                <i class="fa-solid fa-users-slash text-3xl text-slate-300 mb-2"></i>
                <h4 class="text-sm font-bold text-slate-700">No active students in <?= htmlspecialchars($selected_source_class) ?></h4>
                <p class="text-xs text-slate-400 mt-1">Select another class from the dropdown above to view eligible students for promotion.</p>
            </div>
        <?php else: ?>
            <form method="POST" action="academic_year.php" onsubmit="return confirm('Promote selected students to the target grade? This will update their class and LMS portal immediately.');">
                <input type="hidden" name="action" value="promote_students">
                <input type="hidden" name="source_class" value="<?= htmlspecialchars($selected_source_class) ?>">

                <!-- Target Configuration Bar -->
                <div class="p-4 rounded-2xl bg-amber-50/80 border border-amber-200 mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold shrink-0">
                            <i class="fa-solid fa-arrow-right-arrow-left"></i>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-900 block">Promotion Mapping</span>
                            <div class="text-xs text-amber-950 flex items-center space-x-2 mt-0.5">
                                <span>Promoting <strong><?= htmlspecialchars($selected_source_class) ?></strong></span>
                                <i class="fa-solid fa-arrow-right text-amber-600 text-[10px]"></i>
                                <span>Target Grade:</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                        <div>
                            <input type="text" name="target_class" required value="<?= htmlspecialchars($suggested_target) ?>" 
                                   placeholder="e.g. Class 10-A"
                                   class="px-3.5 py-2 rounded-xl border border-amber-300 bg-white text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-amber-500 shadow-2xs">
                        </div>
                        <div>
                            <select name="target_year" class="px-3 py-2 rounded-xl border border-amber-300 bg-white text-xs font-bold text-slate-900 focus:outline-none shadow-2xs">
                                <option value="2027-2028">For AY 2027-2028</option>
                                <option value="2026-2027">For AY 2026-2027</option>
                                <option value="2028-2029">For AY 2028-2029</option>
                            </select>
                        </div>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md transition flex items-center space-x-1.5">
                            <i class="fa-solid fa-check-double mr-1"></i>
                            <span>Promote Selected Students</span>
                        </button>
                    </div>
                </div>

                <!-- Students Roster Table -->
                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4 w-12 text-center">
                                    <input type="checkbox" id="masterPromoteToggle" checked onchange="toggleAllPromote(this.checked)" class="w-4 h-4 rounded text-emerald-600 accent-emerald-600 cursor-pointer">
                                </th>
                                <th class="py-3 px-4">Roll</th>
                                <th class="py-3 px-4">Student ID</th>
                                <th class="py-3 px-4">Student Name</th>
                                <th class="py-3 px-4">Guardian Name</th>
                                <th class="py-3 px-4 text-center">Current Class</th>
                                <th class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($source_students as $stu): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 text-center">
                                        <input type="checkbox" name="student_ids[]" value="<?= $stu['id'] ?>" checked class="promote-checkbox w-4 h-4 rounded text-emerald-600 accent-emerald-600 cursor-pointer">
                                    </td>
                                    <td class="py-3 px-4 font-bold text-slate-800">
                                        <?= htmlspecialchars($stu['roll_no'] ?: '-') ?>
                                    </td>
                                    <td class="py-3 px-4 font-mono font-semibold text-blue-700">
                                        <?= htmlspecialchars($stu['student_code']) ?>
                                    </td>
                                    <td class="py-3 px-4 font-bold text-slate-900">
                                        <?= htmlspecialchars($stu['full_name']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-slate-500">
                                        <?= htmlspecialchars($stu['guardian_name'] ?: 'N/A') ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 font-bold text-[11px]">
                                            <?= htmlspecialchars($stu['class']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">
                                            Eligible for Promotion
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Promotion Audit Log -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 font-['Outfit'] flex items-center">
                <i class="fa-solid fa-clock-rotate-left mr-2 text-navy-800"></i>
                Promotion Audit Log &amp; History
            </h3>
            <span class="text-xs text-slate-400">Past Academic Transitions</span>
        </div>

        <?php if (empty($history)): ?>
            <p class="text-xs text-slate-400 py-4 text-center italic">No promotions recorded yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3.5">Student</th>
                            <th class="py-2.5 px-3.5">From Class</th>
                            <th class="py-2.5 px-3.5">Promoted To</th>
                            <th class="py-2.5 px-3.5 text-center">Academic Year</th>
                            <th class="py-2.5 px-3.5">Promoted By</th>
                            <th class="py-2.5 px-3.5">Date &amp; Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($history as $h): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-2.5 px-3.5 font-bold text-slate-900">
                                    <?= htmlspecialchars($h['full_name']) ?>
                                    <span class="text-[10px] text-blue-600 font-mono block"><?= htmlspecialchars($h['student_code']) ?></span>
                                </td>
                                <td class="py-2.5 px-3.5 text-rose-700 font-semibold">
                                    <?= htmlspecialchars($h['from_class']) ?>
                                </td>
                                <td class="py-2.5 px-3.5 text-emerald-700 font-bold">
                                    <i class="fa-solid fa-arrow-right text-[10px] mr-1 text-slate-400"></i>
                                    <?= htmlspecialchars($h['to_class']) ?>
                                </td>
                                <td class="py-2.5 px-3.5 text-center font-semibold">
                                    <span class="px-2 py-0.5 rounded-full bg-slate-100 text-[10px]">
                                        <?= htmlspecialchars($h['academic_year']) ?>
                                    </span>
                                </td>
                                <td class="py-2.5 px-3.5 text-slate-600">
                                    <?= htmlspecialchars($h['promoter_name'] ?? 'Admin') ?>
                                </td>
                                <td class="py-2.5 px-3.5 text-slate-400 text-[11px]">
                                    <?= date('d M Y, h:i A', strtotime($h['promoted_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>

<!-- CHANGE ACADEMIC YEAR MODAL -->
<div id="shiftYearModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-calendar-days text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Change Academic Year</h3>
                    <p class="text-xs text-slate-400">Update system active calendar session</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('shiftYearModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="academic_year.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="update_year">

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Current Session</label>
                <div class="p-3 rounded-xl bg-slate-100 font-bold text-slate-700">
                    <?= htmlspecialchars($current_academic_year) ?>
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">New Academic Session *</label>
                <input type="text" name="academic_year" required placeholder="e.g. 2027-2028" 
                       value="<?= '20' . ((int)substr($current_academic_year, 2, 2) + 1) . '-20' . ((int)substr($current_academic_year, -2) + 1) ?>"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-900 font-bold text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600">
                <p class="text-[11px] text-slate-400 mt-1">This will update report cards, notices, attendance logs, and student portal headers.</p>
            </div>

            <div class="pt-3 flex justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('shiftYearModal')" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50 font-bold">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-md">
                    <i class="fa-solid fa-arrow-right-arrow-left mr-1"></i> Apply Academic Year
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleAllPromote(checked) {
        document.querySelectorAll('.promote-checkbox').forEach(cb => cb.checked = checked);
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
