<?php
/**
 * Admin: School-Wide Assignments & Homework Oversight
 * ABC School Nashik ERP System
 * Principal / Head Administrator complete access to faculty homework tasks & student submissions
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth(['admin', 'principal', 'vice_principal']);

$flash = get_flash();
$class_filter = $_GET['class'] ?? 'all';
$teacher_filter = (int)($_GET['teacher_id'] ?? 0);

// Available classes
$all_classes = [
    'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5',
    'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10',
    'Grade 11-Sci', 'Grade 11-Com', 'Grade 11-Arts',
    'Grade 12-Sci', 'Grade 12-Com', 'Grade 12-Arts'
];

// Handle Delete Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_assignment') {
    $asgn_id = (int)($_POST['assignment_id'] ?? 0);
    if ($asgn_id > 0) {
        $stmt = $pdo->prepare("SELECT attachment_path FROM assignments WHERE id = ?");
        $stmt->execute([$asgn_id]);
        $attachment = $stmt->fetchColumn();
        if ($attachment && file_exists(__DIR__ . '/../' . $attachment)) {
            @unlink(__DIR__ . '/../' . $attachment);
        }

        // Delete submissions files
        $subFiles = $pdo->prepare("SELECT file_path FROM assignment_submissions WHERE assignment_id = ?");
        $subFiles->execute([$asgn_id]);
        while ($f = $subFiles->fetchColumn()) {
            if ($f && file_exists(__DIR__ . '/../' . $f)) {
                @unlink(__DIR__ . '/../' . $f);
            }
        }

        $del = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
        $del->execute([$asgn_id]);
        set_flash('success', 'Assignment and all corresponding student submission files deleted successfully.');
        header("Location: /admin/assignments.php");
        exit;
    }
}

// Fetch list of faculty for filter dropdown
$teachers_list = $pdo->query("SELECT id, user_id, full_name, department FROM teachers ORDER BY full_name ASC")->fetchAll();

// Check if viewing submissions for a specific assignment
$view_asgn_id = isset($_GET['view_submissions']) ? (int)$_GET['view_submissions'] : null;
$view_asgn = null;
$submissions = [];

if ($view_asgn_id > 0) {
    $stmtAsgn = $pdo->prepare("
        SELECT a.*, COALESCE(t.full_name, 'Teacher') as teacher_name, t.department
        FROM assignments a
        LEFT JOIN teachers t ON a.teacher_id = t.user_id
        WHERE a.id = ?
    ");
    $stmtAsgn->execute([$view_asgn_id]);
    $view_asgn = $stmtAsgn->fetch();

    if ($view_asgn) {
        $stmtSub = $pdo->prepare("
            SELECT s.*, stu.full_name as student_name, stu.student_code, stu.roll_no, stu.class as student_class
            FROM assignment_submissions s
            JOIN students stu ON s.student_id = stu.id
            WHERE s.assignment_id = ?
            ORDER BY s.submitted_at DESC
        ");
        $stmtSub->execute([$view_asgn_id]);
        $submissions = $stmtSub->fetchAll();
    }
}

// Build query for all assignments
$where = "WHERE 1=1";
$params = [];

if ($class_filter !== 'all') {
    $where .= " AND a.class = ?";
    $params[] = $class_filter;
}
if ($teacher_filter > 0) {
    $where .= " AND a.teacher_id = ?";
    $params[] = $teacher_filter;
}

$asgn_sql = "
    SELECT a.*, 
           COALESCE(t.full_name, 'Faculty Member') as teacher_name, 
           t.department,
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as total_submissions,
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND status = 'Graded') as total_graded
    FROM assignments a
    LEFT JOIN teachers t ON a.teacher_id = t.user_id
    {$where}
    ORDER BY a.id DESC
";
$asgn_stmt = $pdo->prepare($asgn_sql);
$asgn_stmt->execute($params);
$assignments = $asgn_stmt->fetchAll();

// Global Stats
$stat_total_asgn = (int)$pdo->query("SELECT COUNT(*) FROM assignments")->fetchColumn();
$stat_total_subs = (int)$pdo->query("SELECT COUNT(*) FROM assignment_submissions")->fetchColumn();
$stat_total_graded = (int)$pdo->query("SELECT COUNT(*) FROM assignment_submissions WHERE status = 'Graded'")->fetchColumn();

$page_title = 'Homework & Assignments Oversight - Principal';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200 mb-6">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-indigo-600 uppercase tracking-wider mb-1">
                <i class="fa-solid fa-book-bookmark"></i>
                <span>Academic Curriculum &amp; Homework Oversight</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Assignments &amp; Homework Register
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Principal oversight: inspect tasks assigned by teachers across all grades, view student submissions, and monitor grading.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <?php if ($view_asgn): ?>
                <a href="/admin/assignments.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl inline-flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-arrow-left"></i> Back to All Assignments
                </a>
            <?php endif; ?>
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

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Assignments</span>
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-book-open"></i>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-slate-900 font-['Outfit'] mt-2"><?= $stat_total_asgn ?></p>
            <p class="text-[11px] text-slate-400 mt-0.5">Active tasks created by teachers</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Student Submissions</span>
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-file-arrow-up"></i>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-blue-700 font-['Outfit'] mt-2"><?= $stat_total_subs ?></p>
            <p class="text-[11px] text-blue-600 mt-0.5">Homework files uploaded by students</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Graded &amp; Reviewed</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-check-double"></i>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-emerald-700 font-['Outfit'] mt-2"><?= $stat_total_graded ?></p>
            <p class="text-[11px] text-emerald-600 mt-0.5"><?= ($stat_total_subs > 0) ? round(($stat_total_graded / $stat_total_subs) * 100, 1) : 0 ?>% evaluation completion rate</p>
        </div>
    </div>

    <?php if ($view_asgn): ?>
        <!-- Specific Assignment Detail & Student Submissions View -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm mb-6">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between pb-4 border-b border-slate-100 mb-4 gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2.5 py-0.5 rounded-md bg-indigo-100 text-indigo-800 text-xs font-bold"><?= htmlspecialchars($view_asgn['class']) ?></span>
                        <span class="px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 text-xs font-semibold"><?= htmlspecialchars($view_asgn['subject']) ?></span>
                        <span class="text-xs text-slate-500">Teacher: <strong><?= htmlspecialchars($view_asgn['teacher_name']) ?></strong></span>
                    </div>
                    <h2 class="text-xl font-bold text-slate-900 font-['Outfit']"><?= htmlspecialchars($view_asgn['title']) ?></h2>
                    <p class="text-xs text-slate-600 mt-1"><?= nl2br(htmlspecialchars($view_asgn['description'])) ?></p>
                </div>

                <div class="text-xs sm:text-right shrink-0 space-y-1">
                    <div>Due Date: <strong class="text-slate-800"><?= date('d M Y', strtotime($view_asgn['due_date'])) ?></strong></div>
                    <div>Max Marks: <strong class="text-slate-800"><?= $view_asgn['max_marks'] ?></strong></div>
                    <?php if (!empty($view_asgn['attachment_path'])): ?>
                        <div class="pt-1">
                            <a href="/<?= htmlspecialchars($view_asgn['attachment_path']) ?>" download class="inline-flex items-center px-3 py-1.5 rounded-xl bg-blue-50 text-blue-700 hover:bg-blue-100 font-bold text-xs transition">
                                <i class="fa-solid fa-paperclip mr-1.5"></i> Download Worksheet
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-3">
                Student Submissions (<?= count($submissions) ?>)
            </h3>

            <?php if (empty($submissions)): ?>
                <div class="text-center py-10 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                    <i class="fa-solid fa-file-circle-xmark text-3xl text-slate-300 mb-2"></i>
                    <p class="text-xs text-slate-500">No student submissions uploaded yet for this assignment.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-3.5">Student</th>
                                <th class="py-3 px-3.5">Roll &amp; Class</th>
                                <th class="py-3 px-3.5">Submitted On</th>
                                <th class="py-3 px-3.5">Homework File</th>
                                <th class="py-3 px-3.5">Score</th>
                                <th class="py-3 px-3.5">Teacher Feedback</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($submissions as $s): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-3.5">
                                        <div class="font-bold text-slate-900"><?= htmlspecialchars($s['student_name']) ?></div>
                                        <div class="text-[10px] font-mono text-blue-700"><?= htmlspecialchars($s['student_code']) ?></div>
                                    </td>
                                    <td class="py-3 px-3.5 font-semibold text-slate-700">
                                        <?= htmlspecialchars($s['student_class']) ?> (Roll <?= htmlspecialchars($s['roll_no'] ?: '-') ?>)
                                    </td>
                                    <td class="py-3 px-3.5 text-slate-500 text-[11px]">
                                        <?= date('d M Y, h:i A', strtotime($s['submitted_at'])) ?>
                                    </td>
                                    <td class="py-3 px-3.5">
                                        <a href="/<?= htmlspecialchars($s['file_path']) ?>" download class="inline-flex items-center px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-bold text-[11px] transition">
                                            <i class="fa-solid fa-download mr-1.5"></i> Download File
                                        </a>
                                        <?php if (!empty($s['student_remarks'])): ?>
                                            <p class="text-[10px] text-slate-400 italic mt-0.5">"<?= htmlspecialchars($s['student_remarks']) ?>"</p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3.5">
                                        <?php if ($s['marks_obtained'] !== null): ?>
                                            <span class="px-2 py-0.5 rounded font-bold text-xs bg-emerald-100 text-emerald-800">
                                                <?= $s['marks_obtained'] ?> / <?= $view_asgn['max_marks'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic text-[11px]">Not Graded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3.5 text-slate-600 text-[11px] max-w-xs">
                                        <?= $s['teacher_feedback'] ? htmlspecialchars($s['teacher_feedback']) : '<span class="text-slate-400 italic">-</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- All Assignments List with Filters -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
        
        <!-- Filter Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 mb-4 gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-900 font-['Outfit']">
                    All Assignments Roster (<?= count($assignments) ?>)
                </h3>
            </div>

            <form method="GET" action="assignments.php" class="flex flex-wrap items-center gap-2.5">
                <select name="class" onchange="this.form.submit()" class="px-3 py-1.5 text-xs font-semibold rounded-xl border border-slate-300 bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                    <option value="all" <?= $class_filter === 'all' ? 'selected' : '' ?>>All Classes</option>
                    <?php foreach ($all_classes as $cls): ?>
                        <option value="<?= htmlspecialchars($cls) ?>" <?= $class_filter === $cls ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cls) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="teacher_id" onchange="this.form.submit()" class="px-3 py-1.5 text-xs font-semibold rounded-xl border border-slate-300 bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                    <option value="0" <?= $teacher_filter === 0 ? 'selected' : '' ?>>All Faculty</option>
                    <?php foreach ($teachers_list as $t): ?>
                        <option value="<?= $t['user_id'] ?>" <?= $teacher_filter === (int)$t['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['department']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (empty($assignments)): ?>
            <div class="text-center py-12 bg-slate-50/70 rounded-2xl border border-dashed border-slate-200">
                <i class="fa-solid fa-book text-3xl text-slate-300 mb-2"></i>
                <h4 class="text-sm font-bold text-slate-700">No assignments found</h4>
                <p class="text-xs text-slate-400 mt-1">Teachers haven't created any homework assignments matching your filter.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto rounded-2xl border border-slate-200">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-3.5">Assignment</th>
                            <th class="py-3 px-3.5">Class &amp; Subject</th>
                            <th class="py-3 px-3.5">Teacher</th>
                            <th class="py-3 px-3.5">Due Date</th>
                            <th class="py-3 px-3.5 text-center">Submissions</th>
                            <th class="py-3 px-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($assignments as $a): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-3.5">
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($a['title']) ?></div>
                                    <p class="text-[11px] text-slate-500 line-clamp-1 max-w-xs"><?= htmlspecialchars($a['description']) ?></p>
                                </td>
                                <td class="py-3 px-3.5">
                                    <span class="px-2 py-0.5 rounded font-bold text-[10px] bg-indigo-100 text-indigo-800">
                                        <?= htmlspecialchars($a['class']) ?>
                                    </span>
                                    <div class="text-slate-600 font-semibold text-[11px] mt-0.5"><?= htmlspecialchars($a['subject']) ?></div>
                                </td>
                                <td class="py-3 px-3.5">
                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($a['teacher_name']) ?></div>
                                    <div class="text-[10px] text-slate-400"><?= htmlspecialchars($a['department'] ?? 'Faculty') ?></div>
                                </td>
                                <td class="py-3 px-3.5 text-slate-700 font-medium">
                                    <?= date('d M Y', strtotime($a['due_date'])) ?>
                                </td>
                                <td class="py-3 px-3.5 text-center whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-800 border border-blue-200">
                                        <?= $a['total_submissions'] ?> submitted
                                    </span>
                                    <span class="text-[10px] text-emerald-600 font-bold block mt-0.5">
                                        <?= $a['total_graded'] ?> graded
                                    </span>
                                </td>
                                <td class="py-3 px-3.5 text-right whitespace-nowrap space-x-1.5">
                                    <a href="assignments.php?view_submissions=<?= $a['id'] ?>" class="inline-flex items-center px-3 py-1.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs transition shadow-xs">
                                        <i class="fa-solid fa-eye mr-1.5"></i> Inspect
                                    </a>
                                    <form method="POST" action="assignments.php" class="inline" onsubmit="return confirm('Permanently delete this assignment and all student submissions?');">
                                        <input type="hidden" name="action" value="delete_assignment">
                                        <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="p-1.5 rounded-xl text-rose-500 hover:bg-rose-50 hover:text-rose-700 transition" title="Delete Assignment">
                                            <i class="fa-solid fa-trash-can text-sm"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
