<?php
/**
 * Admin / Principal: Curriculum & Subjects Management
 * Configure subjects, max marks, pass criteria, and syllabus per class & academic year
 * Bellwether International School ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth(['admin', 'principal', 'vice_principal']);

$flash = get_flash();
$current_academic_year = get_setting($pdo, 'current_academic_year', '2026-2027');

// Fetch available classes from students and subjects
$db_classes = $pdo->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class ASC")->fetchAll(PDO::FETCH_COLUMN);
$subj_classes = $pdo->query("SELECT DISTINCT class FROM subjects WHERE class IS NOT NULL AND class != '' ORDER BY class ASC")->fetchAll(PDO::FETCH_COLUMN);
$all_classes = array_values(array_unique(array_merge($db_classes, $subj_classes, [
    'Class 10-A', 'Class 10-B', 'Class 9-A', 'Class 9-B', 'Class 8-A', 'Class 8-B',
    'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5',
    'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10',
    'Grade 11-Sci', 'Grade 11-Com', 'Grade 11-Arts',
    'Grade 12-Sci', 'Grade 12-Com', 'Grade 12-Arts'
])));
sort($all_classes);

$selected_class = $_GET['class'] ?? 'all';
$selected_year = $_GET['year'] ?? $current_academic_year;

// Fetch all approved teachers for allocation
$all_teachers = $pdo->query("
    SELECT t.id, t.full_name, t.department, t.teacher_code, t.designation, t.photo 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    WHERE u.status = 'approved'
    ORDER BY t.full_name ASC
")->fetchAll();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 0. Quick Faculty Allocation
    if ($action === 'quick_allocate_teacher') {
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
        $class_redir = $_POST['redirect_class'] ?? 'all';
        if ($subject_id > 0) {
            $pdo->prepare("UPDATE subjects SET teacher_id = ? WHERE id = ?")->execute([$teacher_id, $subject_id]);
            set_flash('success', 'Faculty allocation updated successfully!');
        }
        header("Location: /admin/subjects.php?class=" . urlencode($class_redir));
        exit;
    }

    // 1. Add Subject
    if ($action === 'add_subject') {
        $class_name = trim($_POST['class'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $max_marks = (float)($_POST['max_marks'] ?? 100);
        $pass_marks = (float)($_POST['pass_marks'] ?? 35);
        $syllabus = trim($_POST['syllabus_info'] ?? '');
        $acad_year = trim($_POST['academic_year'] ?? $current_academic_year);
        $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;

        if (empty($class_name) || empty($name)) {
            set_flash('danger', 'Please provide both Class and Subject Name.');
        } else {
            // Check for duplicate in same class & year
            $chk = $pdo->prepare("SELECT id FROM subjects WHERE class = ? AND name = ? AND academic_year = ? LIMIT 1");
            $chk->execute([$class_name, $name, $acad_year]);
            if ($chk->fetch()) {
                set_flash('danger', "Subject '{$name}' is already configured for {$class_name} ({$acad_year}).");
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO subjects (class, name, code, max_marks, pass_marks, syllabus_info, academic_year, teacher_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$class_name, $name, $code, $max_marks, $pass_marks, $syllabus, $acad_year, $teacher_id]);
                set_flash('success', "Subject '{$name}' added successfully for {$class_name} ({$acad_year})!");
            }
        }
        header("Location: /admin/subjects.php?class=" . urlencode($class_name) . "&year=" . urlencode($acad_year));
        exit;
    }

    // 2. Edit Subject
    if ($action === 'edit_subject') {
        $id = (int)($_POST['subject_id'] ?? 0);
        $class_name = trim($_POST['class'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $max_marks = (float)($_POST['max_marks'] ?? 100);
        $pass_marks = (float)($_POST['pass_marks'] ?? 35);
        $syllabus = trim($_POST['syllabus_info'] ?? '');
        $acad_year = trim($_POST['academic_year'] ?? $current_academic_year);
        $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;

        if ($id > 0 && !empty($class_name) && !empty($name)) {
            $upd = $pdo->prepare("
                UPDATE subjects 
                SET class = ?, name = ?, code = ?, max_marks = ?, pass_marks = ?, syllabus_info = ?, academic_year = ?, teacher_id = ?
                WHERE id = ?
            ");
            $upd->execute([$class_name, $name, $code, $max_marks, $pass_marks, $syllabus, $acad_year, $teacher_id, $id]);
            set_flash('success', "Subject '{$name}' updated successfully.");
        }
        header("Location: /admin/subjects.php?class=" . urlencode($class_name) . "&year=" . urlencode($acad_year));
        exit;
    }

    // 3. Delete Subject
    if ($action === 'delete_subject') {
        $id = (int)($_POST['subject_id'] ?? 0);
        $class_redir = $_POST['redirect_class'] ?? 'all';
        if ($id > 0) {
            $del = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
            $del->execute([$id]);
            set_flash('success', "Subject deleted from class curriculum.");
        }
        header("Location: /admin/subjects.php?class=" . urlencode($class_redir));
        exit;
    }

    // 4. Clone / Copy Subjects from one class to another or across years
    if ($action === 'clone_subjects') {
        $from_class = trim($_POST['from_class'] ?? '');
        $to_class = trim($_POST['to_class'] ?? '');
        $from_year = trim($_POST['from_year'] ?? $current_academic_year);
        $to_year = trim($_POST['to_year'] ?? $current_academic_year);

        if (empty($from_class) || empty($to_class)) {
            set_flash('danger', 'Please select both source class and destination class.');
        } else {
            $src_stmt = $pdo->prepare("SELECT * FROM subjects WHERE class = ? AND academic_year = ?");
            $src_stmt->execute([$from_class, $from_year]);
            $src_subjects = $src_stmt->fetchAll();

            if (empty($src_subjects)) {
                set_flash('danger', "No subjects found in {$from_class} for year {$from_year} to copy.");
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO subjects (class, name, code, max_marks, pass_marks, syllabus_info, academic_year)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $chk = $pdo->prepare("SELECT id FROM subjects WHERE class = ? AND name = ? AND academic_year = ?");
                $copied = 0;

                foreach ($src_subjects as $s) {
                    $chk->execute([$to_class, $s['name'], $to_year]);
                    if (!$chk->fetch()) {
                        $ins->execute([$to_class, $s['name'], $s['code'], $s['max_marks'], $s['pass_marks'], $s['syllabus_info'], $to_year]);
                        $copied++;
                    }
                }

                set_flash('success', "Copied {$copied} subject(s) from {$from_class} to {$to_class} ({$to_year})!");
                header("Location: /admin/subjects.php?class=" . urlencode($to_class) . "&year=" . urlencode($to_year));
                exit;
            }
        }
    }
}

// Fetch Subjects
$where = [];
$params = [];

if ($selected_class !== 'all') {
    $where[] = "class = ?";
    $params[] = $selected_class;
}
if (!empty($selected_year)) {
    $where[] = "academic_year = ?";
    $params[] = $selected_year;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$stmt = $pdo->prepare("
    SELECT s.*, 
           t.full_name as teacher_name, 
           t.department as teacher_department, 
           t.designation as teacher_designation,
           t.photo as teacher_photo,
           t.teacher_code
    FROM subjects s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    {$where_clause} 
    ORDER BY s.class ASC, s.name ASC
");
$stmt->execute($params);
$subjects = $stmt->fetchAll();

// Group subjects by class for statistics
$class_counts = [];
foreach ($subjects as $sub) {
    $class_counts[$sub['class']] = ($class_counts[$sub['class']] ?? 0) + 1;
}

$page_title = 'Curriculum & Subjects Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 mb-6">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
                <i class="fa-solid fa-book-open"></i>
                <span>Academic Curriculum &amp; Syllabus</span>
                <span>&bull;</span>
                <span class="text-amber-600">AY: <?= htmlspecialchars($selected_year) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Subjects &amp; Curriculum Management
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Principal &amp; Administrator oversight: configure subjects, max marks, passing criteria, and syllabus per grade.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button" onclick="openModal('cloneSubjectsModal')" class="inline-flex items-center px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs shadow-xs transition">
                <i class="fa-solid fa-copy mr-2 text-blue-600"></i> Clone Class Subjects
            </button>
            <button type="button" onclick="openModal('addSubjectModal')" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md transition">
                <i class="fa-solid fa-plus-circle mr-2 text-amber-400"></i> Add New Subject
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

    <!-- Class & Year Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <form method="GET" action="subjects.php" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <div>
                <select name="class" onchange="this.form.submit()" class="px-3.5 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                    <option value="all" <?= ($selected_class === 'all') ? 'selected' : '' ?>>All Classes (<?= count($subjects) ?> Subjects)</option>
                    <?php foreach ($all_classes as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= ($selected_class === $c) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <select name="year" onchange="this.form.submit()" class="px-3.5 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                    <option value="2026-2027" <?= ($selected_year === '2026-2027') ? 'selected' : '' ?>>Academic Year 2026-2027 (Active)</option>
                    <option value="2027-2028" <?= ($selected_year === '2027-2028') ? 'selected' : '' ?>>Academic Year 2027-2028</option>
                    <option value="2025-2026" <?= ($selected_year === '2025-2026') ? 'selected' : '' ?>>Academic Year 2025-2026</option>
                </select>
            </div>

            <?php if ($selected_class !== 'all'): ?>
                <a href="/admin/subjects.php" class="text-xs text-rose-600 hover:underline font-bold">Clear Filter</a>
            <?php endif; ?>
        </form>

        <div class="text-xs text-slate-500 font-medium">
            Configured Classes: <strong class="text-slate-900"><?= count($class_counts) ?></strong> &bull;
            Total Subjects: <strong class="text-slate-900"><?= count($subjects) ?></strong>
        </div>
    </div>

    <!-- Subjects Matrix / Table -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
        <?php if (empty($subjects)): ?>
            <div class="text-center py-12">
                <i class="fa-solid fa-book-open text-4xl text-slate-300 mb-3"></i>
                <h3 class="text-sm font-bold text-slate-700">No subjects configured for this selection</h3>
                <p class="text-xs text-slate-400 mt-1">Click "Add New Subject" or "Clone Class Subjects" to populate curriculum.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4 sm:px-6">Class / Grade</th>
                            <th class="py-3 px-4">Subject Name</th>
                            <th class="py-3 px-4">Allocated Faculty / Teacher</th>
                            <th class="py-3 px-4">Subject Code</th>
                            <th class="py-3 px-4 text-center">Max Marks</th>
                            <th class="py-3 px-4 text-center">Pass Marks</th>
                            <th class="py-3 px-4">Syllabus / Curriculum Notes</th>
                            <th class="py-3 px-4 text-center">Academic Year</th>
                            <th class="py-3 px-4 sm:px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($subjects as $s): ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-3 px-4 sm:px-6 font-bold text-slate-900 whitespace-nowrap">
                                    <span class="px-2.5 py-1 rounded-xl bg-blue-50 text-blue-800 border border-blue-200 text-xs">
                                        <?= htmlspecialchars($s['class']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-bold text-slate-900 text-sm">
                                    <?= htmlspecialchars($s['name']) ?>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <?php if (!empty($s['teacher_name'])): ?>
                                        <div class="flex items-center space-x-2">
                                            <?php if (!empty($s['teacher_photo']) && file_exists(__DIR__ . '/../' . ltrim($s['teacher_photo'], '/'))): ?>
                                                <img src="<?= htmlspecialchars($s['teacher_photo']) ?>" alt="Teacher" class="w-7 h-7 rounded-lg object-cover border border-amber-300">
                                            <?php else: ?>
                                                <div class="w-7 h-7 rounded-lg bg-navy-900 text-amber-400 font-bold text-xs flex items-center justify-center">
                                                    <?= strtoupper(substr($s['teacher_name'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <span class="font-bold text-slate-800 block leading-tight"><?= htmlspecialchars($s['teacher_name']) ?></span>
                                                <span class="text-[10px] text-slate-400 block"><?= htmlspecialchars($s['teacher_department'] ?: 'Faculty') ?></span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" action="subjects.php" class="inline-flex items-center space-x-1.5">
                                            <input type="hidden" name="action" value="quick_allocate_teacher">
                                            <input type="hidden" name="subject_id" value="<?= $s['id'] ?>">
                                            <input type="hidden" name="redirect_class" value="<?= htmlspecialchars($selected_class) ?>">
                                            <select name="teacher_id" onchange="this.form.submit()" class="px-2 py-1 rounded-lg border border-amber-300 bg-amber-50/70 text-amber-900 text-[11px] font-semibold focus:outline-none cursor-pointer">
                                                <option value="">+ Assign Teacher</option>
                                                <?php foreach ($all_teachers as $t): ?>
                                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 font-mono font-semibold text-blue-700">
                                    <?= htmlspecialchars($s['code'] ?: 'N/A') ?>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-900">
                                    <?= (int)$s['max_marks'] ?>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-emerald-700">
                                    <?= (int)$s['pass_marks'] ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600 max-w-xs truncate" title="<?= htmlspecialchars($s['syllabus_info']) ?>">
                                    <?= htmlspecialchars($s['syllabus_info'] ?: 'Standard Curriculum') ?>
                                </td>
                                <td class="py-3 px-4 text-center text-slate-500 font-medium">
                                    <span class="px-2 py-0.5 rounded-full bg-slate-100 text-[10px] font-bold">
                                        <?= htmlspecialchars($s['academic_year']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 sm:px-6 text-right whitespace-nowrap space-x-2">
                                    <button type="button" 
                                            onclick='openEditSubjectModal(<?= json_encode($s) ?>)'
                                            class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" 
                                            title="Edit Subject">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <form method="POST" action="subjects.php" class="inline" onsubmit="return confirm('Delete this subject from curriculum?');">
                                        <input type="hidden" name="action" value="delete_subject">
                                        <input type="hidden" name="subject_id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="redirect_class" value="<?= htmlspecialchars($selected_class) ?>">
                                        <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Delete Subject">
                                            <i class="fa-solid fa-trash-can"></i>
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

<!-- ADD SUBJECT MODAL -->
<div id="addSubjectModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-book-medical text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Add New Subject</h3>
                    <p class="text-xs text-slate-400">Configure class course &amp; assessment criteria</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('addSubjectModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="subjects.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="add_subject">

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Target Class / Grade *</label>
                <select name="class" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none focus:ring-2 focus:ring-navy-600">
                    <?php foreach ($all_classes as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= ($selected_class === $c) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Subject Name *</label>
                <input type="text" name="name" required placeholder="e.g. Mathematics, Science, Sanskrit" 
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none focus:ring-2 focus:ring-navy-600">
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Allocated Faculty / Teacher</label>
                <select name="teacher_id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none focus:ring-2 focus:ring-navy-600">
                    <option value="">-- No Teacher Allocated (Assign Later) --</option>
                    <?php foreach ($all_teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['department']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Code</label>
                    <input type="text" name="code" placeholder="MATH10" 
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none focus:ring-2 focus:ring-navy-600">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Max Marks *</label>
                    <input type="number" name="max_marks" required value="100" min="1" 
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none focus:ring-2 focus:ring-navy-600">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Pass Marks *</label>
                    <input type="number" name="pass_marks" required value="35" min="0" 
                           class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none focus:ring-2 focus:ring-navy-600">
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Academic Year</label>
                <input type="text" name="academic_year" value="<?= htmlspecialchars($selected_year) ?>" 
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none focus:ring-2 focus:ring-navy-600">
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Syllabus / Textbook Info</label>
                <textarea name="syllabus_info" rows="2" placeholder="e.g. NCERT Textbook Units 1-12, Lab Practicals 20 Marks" 
                          class="w-full px-3.5 py-2 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none focus:ring-2 focus:ring-navy-600"></textarea>
            </div>

            <div class="pt-3 flex justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('addSubjectModal')" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50 font-bold">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold shadow-md">
                    <i class="fa-solid fa-floppy-disk mr-1 text-amber-400"></i> Save Subject
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT SUBJECT MODAL -->
<div id="editSubjectModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
            <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Edit Subject Details</h3>
            <button type="button" onclick="closeModal('editSubjectModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="subjects.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="edit_subject">
            <input type="hidden" name="subject_id" id="edit_subject_id">

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Class / Grade *</label>
                <select name="class" id="edit_subject_class" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none">
                    <?php foreach ($all_classes as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Subject Name *</label>
                <input type="text" name="name" id="edit_subject_name" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none">
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Allocated Faculty / Teacher</label>
                <select name="teacher_id" id="edit_subject_teacher_id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none focus:ring-2 focus:ring-navy-600">
                    <option value="">-- No Teacher Allocated (Assign Later) --</option>
                    <?php foreach ($all_teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['department']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Code</label>
                    <input type="text" name="code" id="edit_subject_code" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Max Marks *</label>
                    <input type="number" name="max_marks" id="edit_subject_max" required min="1" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Pass Marks *</label>
                    <input type="number" name="pass_marks" id="edit_subject_pass" required min="0" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Academic Year</label>
                <input type="text" name="academic_year" id="edit_subject_year" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none">
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Syllabus / Textbook Info</label>
                <textarea name="syllabus_info" id="edit_subject_syllabus" rows="2" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none"></textarea>
            </div>

            <div class="pt-3 flex justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('editSubjectModal')" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50 font-bold">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold shadow-md">
                    Update Subject
                </button>
            </div>
        </form>
    </div>
</div>

<!-- CLONE SUBJECTS MODAL -->
<div id="cloneSubjectsModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-copy text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Clone / Copy Class Subjects</h3>
                    <p class="text-xs text-slate-400">Copy all subjects from one grade/year to another</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('cloneSubjectsModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="subjects.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="clone_subjects">

            <div class="p-3 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-[11px] leading-relaxed">
                <i class="fa-solid fa-lightbulb text-amber-600 mr-1"></i>
                Duplicate subjects will be safely skipped. Perfect for copying subjects from Section A to Section B, or advancing to next year.
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Source Class</label>
                    <select name="from_class" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs">
                        <?php foreach ($all_classes as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Source Year</label>
                    <input type="text" name="from_year" value="<?= htmlspecialchars($selected_year) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Destination Class</label>
                    <select name="to_class" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs">
                        <?php foreach ($all_classes as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Destination Year</label>
                    <input type="text" name="to_year" value="<?= htmlspecialchars($selected_year) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs">
                </div>
            </div>

            <div class="pt-3 flex justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('cloneSubjectsModal')" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50 font-bold">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold shadow-md">
                    <i class="fa-solid fa-copy mr-1 text-amber-400"></i> Copy All Subjects
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditSubjectModal(sub) {
        document.getElementById('edit_subject_id').value = sub.id;
        document.getElementById('edit_subject_class').value = sub.class;
        document.getElementById('edit_subject_name').value = sub.name;
        document.getElementById('edit_subject_code').value = sub.code || '';
        document.getElementById('edit_subject_max').value = sub.max_marks;
        document.getElementById('edit_subject_pass').value = sub.pass_marks;
        document.getElementById('edit_subject_year').value = sub.academic_year || '2026-2027';
        document.getElementById('edit_subject_syllabus').value = sub.syllabus_info || '';
        document.getElementById('edit_subject_teacher_id').value = sub.teacher_id || '';
        openModal('editSubjectModal');
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
