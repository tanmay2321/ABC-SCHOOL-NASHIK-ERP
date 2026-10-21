<?php
/**
 * Teacher: Student Marks & All-Subject Assessment Register
 * Bellwether International School ERP
 * Allows filling marks at once for all subjects for an entire class or student
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth(['teacher', 'admin', 'principal', 'vice_principal']);

$teacher_user_id = $_SESSION['user_id'];
$error = '';
$flash = get_flash();

// Helper function to calculate grade letter
function calculate_grade($obtained, $max) {
    if ($max <= 0) return 'F';
    $pct = ($obtained / $max) * 100;
    if ($pct >= 90) return 'A+';
    if ($pct >= 80) return 'A';
    if ($pct >= 70) return 'B+';
    if ($pct >= 60) return 'B';
    if ($pct >= 50) return 'C';
    if ($pct >= 35) return 'D';
    return 'F';
}

// 1. Fetch teacher profile & assigned class
$tProfileStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$tProfileStmt->execute([$teacher_user_id]);
$teacher_profile = $tProfileStmt->fetch();
$assigned_class = $teacher_profile['assigned_class'] ?? null;
$school_name = get_setting($pdo, 'school_name', 'BELLWETHER INTERNATIONAL SCHOOL NANDURBAR');
$academic_year = get_setting($pdo, 'current_academic_year', '2026-2027');

// 2. Fetch all available classes
$classes = $pdo->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($classes)) {
    $classes = ['Class 10-A', 'Class 10-B', 'Class 9-A', 'Class 9-B', 'Class 8-A', 'Class 8-B'];
}
if (!empty($assigned_class) && !in_array($assigned_class, $classes)) {
    array_unshift($classes, $assigned_class);
}

$default_class = (!empty($assigned_class)) ? $assigned_class : $classes[0];
$selected_class = $_GET['class'] ?? $default_class;
$selected_exam = $_GET['exam'] ?? 'Mid-Term Examination';
$active_tab = $_GET['tab'] ?? 'matrix'; // 'matrix' (all subjects grid) or 'records' (audit log)

// Standard exam choices
$standard_exams = [
    'Unit Test 1',
    'Unit Test 2',
    'Mid-Term Examination',
    'Quarterly Examination',
    'Pre-Board Examination',
    'Final Annual Examination'
];
if (!in_array($selected_exam, $standard_exams) && !empty($selected_exam)) {
    array_unshift($standard_exams, $selected_exam);
}

// 3. Fetch subjects configured for this class
$subjStmt = $pdo->prepare("SELECT * FROM subjects WHERE class = ? ORDER BY name ASC");
$subjStmt->execute([$selected_class]);
$class_subjects = $subjStmt->fetchAll();

// Fallback to standard core subjects if not configured yet in subjects table
if (empty($class_subjects)) {
    $class_subjects = [
        ['name' => 'English', 'code' => 'ENG', 'max_marks' => 100, 'pass_marks' => 35],
        ['name' => 'Mathematics', 'code' => 'MATH', 'max_marks' => 100, 'pass_marks' => 35],
        ['name' => 'Science', 'code' => 'SCI', 'max_marks' => 100, 'pass_marks' => 35],
        ['name' => 'Social Science', 'code' => 'SST', 'max_marks' => 100, 'pass_marks' => 35],
        ['name' => 'Hindi', 'code' => 'HIN', 'max_marks' => 100, 'pass_marks' => 35],
        ['name' => 'Computer Science', 'code' => 'CS', 'max_marks' => 100, 'pass_marks' => 35]
    ];
}

// 4. Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // A. BATCH CLASS MATRIX: Save All Subjects For All Students At Once
    if ($action === 'save_all_subjects_matrix') {
        $exam_name = trim($_POST['exam_name'] ?? $selected_exam);
        $target_class = trim($_POST['class'] ?? $selected_class);
        $student_ids = $_POST['student_ids'] ?? []; // array of student ids
        $marks_matrix = $_POST['marks'] ?? []; // [student_id][subject_name] => score
        $max_matrix = $_POST['max_marks'] ?? []; // [subject_name] => max
        $remarks_data = $_POST['remarks'] ?? []; // [student_id] => remark

        if (empty($exam_name) || empty($student_ids)) {
            set_flash('danger', 'Please choose exam and select students to save marks.');
        } else {
            try {
                $pdo->beginTransaction();

                $chkStmt = $pdo->prepare("SELECT id FROM grades WHERE student_id = ? AND subject = ? AND exam_name = ? LIMIT 1");
                $updStmt = $pdo->prepare("UPDATE grades SET marks_obtained = ?, max_marks = ?, grade = ?, remarks = ?, teacher_id = ? WHERE id = ?");
                $insStmt = $pdo->prepare("INSERT INTO grades (student_id, subject, exam_name, marks_obtained, max_marks, grade, remarks, teacher_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                $saved_entries = 0;
                $students_affected = 0;

                foreach ($student_ids as $sid) {
                    $sid = (int)$sid;
                    $student_remarks = trim($remarks_data[$sid] ?? '');
                    $has_student_entry = false;

                    if (isset($marks_matrix[$sid]) && is_array($marks_matrix[$sid])) {
                        foreach ($marks_matrix[$sid] as $subject_name => $score_val) {
                            $subject_name = trim($subject_name);
                            if ($score_val === '' || $score_val === null) {
                                continue; // Skip blank inputs
                            }

                            $obtained = (float)$score_val;
                            $max = (float)($max_matrix[$subject_name] ?? 100);
                            if ($max <= 0) $max = 100;
                            $grade = calculate_grade($obtained, $max);

                            // Check existing
                            $chkStmt->execute([$sid, $subject_name, $exam_name]);
                            $existing_id = $chkStmt->fetchColumn();

                            if ($existing_id) {
                                $updStmt->execute([$obtained, $max, $grade, $student_remarks, $teacher_user_id, $existing_id]);
                            } else {
                                $insStmt->execute([$sid, $subject_name, $exam_name, $obtained, $max, $grade, $student_remarks, $teacher_user_id]);
                            }

                            $saved_entries++;
                            $has_student_entry = true;
                        }
                    }

                    if ($has_student_entry) {
                        $students_affected++;
                    }
                }

                $pdo->commit();
                set_flash('success', "Marksheet for {$target_class} ({$exam_name}) saved successfully! Recorded {$saved_entries} subject mark(s) across {$students_affected} student(s) at once.");
                header("Location: /teacher/marks.php?class=" . urlencode($target_class) . "&exam=" . urlencode($exam_name) . "&tab=matrix");
                exit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Failed to save multi-subject marksheet: ' . $e->getMessage();
            }
        }
    }

    // B. SINGLE STUDENT MULTI-SUBJECT: Save All Subjects for a Single Student at Once
    if ($action === 'save_student_all_subjects') {
        $sid = (int)($_POST['student_id'] ?? 0);
        $exam_name = trim($_POST['exam_name'] ?? $selected_exam);
        $marks_data = $_POST['subject_marks'] ?? []; // subject => score
        $max_data = $_POST['subject_max'] ?? [];
        $remarks = trim($_POST['remarks'] ?? '');

        if ($sid <= 0 || empty($exam_name)) {
            set_flash('danger', 'Please choose student and exam name.');
        } else {
            try {
                $pdo->beginTransaction();

                $chkStmt = $pdo->prepare("SELECT id FROM grades WHERE student_id = ? AND subject = ? AND exam_name = ? LIMIT 1");
                $updStmt = $pdo->prepare("UPDATE grades SET marks_obtained = ?, max_marks = ?, grade = ?, remarks = ?, teacher_id = ? WHERE id = ?");
                $insStmt = $pdo->prepare("INSERT INTO grades (student_id, subject, exam_name, marks_obtained, max_marks, grade, remarks, teacher_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                $count = 0;
                foreach ($marks_data as $sub => $score_val) {
                    if ($score_val === '' || $score_val === null) continue;
                    $score = (float)$score_val;
                    $max = (float)($max_data[$sub] ?? 100);
                    if ($max <= 0) $max = 100;
                    $grade = calculate_grade($score, $max);

                    $chkStmt->execute([$sid, $sub, $exam_name]);
                    $existing_id = $chkStmt->fetchColumn();

                    if ($existing_id) {
                        $updStmt->execute([$score, $max, $grade, $remarks, $teacher_user_id, $existing_id]);
                    } else {
                        $insStmt->execute([$sid, $sub, $exam_name, $score, $max, $grade, $remarks, $teacher_user_id]);
                    }
                    $count++;
                }

                $pdo->commit();
                set_flash('success', "All {$count} subject marks for student recorded successfully for {$exam_name}!");
                header("Location: /teacher/marks.php?class=" . urlencode($selected_class) . "&exam=" . urlencode($exam_name));
                exit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Failed to save student marks: ' . $e->getMessage();
            }
        }
    }

    // C. DELETE SINGLE GRADE RECORD
    if ($action === 'delete_grade') {
        $grade_id = (int)($_POST['grade_id'] ?? 0);
        if ($grade_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM grades WHERE id = :id");
            $stmt->execute(['id' => $grade_id]);
            set_flash('success', 'Grade record deleted.');
            header("Location: /teacher/marks.php?tab=records&class=" . urlencode($selected_class) . "&exam=" . urlencode($selected_exam));
            exit();
        }
    }
}

// 5. Fetch approved students for the selected class
$sStmt = $pdo->prepare("
    SELECT s.id, s.full_name, s.class, s.roll_no, s.student_code
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.class = :cls AND u.status = 'approved'
    ORDER BY CAST(s.roll_no AS INTEGER) ASC, s.full_name ASC
");
$sStmt->execute(['cls' => $selected_class]);
$class_students = $sStmt->fetchAll();

// 6. Fetch already recorded grades for this class & exam
$gExistingStmt = $pdo->prepare("
    SELECT g.*
    FROM grades g
    JOIN students s ON g.student_id = s.id
    WHERE s.class = :cls AND g.exam_name = :exam
");
$gExistingStmt->execute(['cls' => $selected_class, 'exam' => $selected_exam]);
$recorded_marks = [];
while ($row = $gExistingStmt->fetch()) {
    $recorded_marks[$row['student_id']][$row['subject']] = $row;
}

// 7. Fetch all grades for audit log tab
$all_grades = $pdo->query("
    SELECT g.*, s.full_name, s.class, s.roll_no, s.student_code
    FROM grades g
    JOIN students s ON g.student_id = s.id
    ORDER BY g.id DESC LIMIT 100
")->fetchAll();

$page_title = 'Student Marks & Assessment';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 mb-6 no-print">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
                <span>All-Subject Marksheet Filling</span>
                <span>&bull;</span>
                <span class="text-slate-400"><?= htmlspecialchars($selected_class) ?> &bull; <?= htmlspecialchars($selected_exam) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Student Marks &amp; Assessment Register
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Fill scores for all subjects at once for each student. Calculations for Total, Percentage, and Report Card Grades are automated.
            </p>
        </div>

        <div class="flex items-center space-x-2.5">
            <button type="button" onclick="openModal('singleStudentModal')" class="inline-flex items-center px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs shadow-xs transition">
                <i class="fa-solid fa-user-pen mr-2 text-blue-600"></i> Fill Single Student Marksheet
            </button>
            <button type="button" onclick="window.print()" class="inline-flex items-center px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs shadow-xs transition">
                <i class="fa-solid fa-print mr-2 text-slate-500"></i> Print Marksheet
            </button>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl bg-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-50 border border-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-200 text-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-900 text-xs font-semibold flex items-center justify-between shadow-xs no-print">
            <div class="flex items-center space-x-2.5">
                <i class="fa-solid <?= $flash['type'] === 'danger' ? 'fa-triangle-exclamation' : 'fa-circle-check' ?> text-base"></i>
                <span><?= $flash['message'] ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center space-x-2.5 no-print">
            <i class="fa-solid fa-triangle-exclamation text-base"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Class & Exam Selection Bar -->
    <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm mb-6 no-print">
        <form method="GET" action="marks.php" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Class / Standard *</label>
                <select name="class" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= ($selected_class === $c) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?> <?= (!empty($assigned_class) && $assigned_class === $c) ? '⭐ (My Class)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Examination / Term *</label>
                <select name="exam" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                    <?php foreach ($standard_exams as $ex): ?>
                        <option value="<?= htmlspecialchars($ex) ?>" <?= ($selected_exam === $ex) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ex) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md transition flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-arrows-rotate text-amber-400"></i>
                    <span>Load Marksheet Grid</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Tab navigation -->
    <div class="flex items-center space-x-3 mb-4 border-b border-slate-200 pb-2 no-print">
        <a href="?class=<?= urlencode($selected_class) ?>&exam=<?= urlencode($selected_exam) ?>&tab=matrix" 
           class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 <?= ($active_tab === 'matrix') ? 'bg-navy-800 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' ?>">
            <i class="fa-solid fa-table-cells text-amber-400"></i>
            <span>All-in-One Class Marksheet (<?= count($class_subjects) ?> Subjects)</span>
        </a>
        <a href="?class=<?= urlencode($selected_class) ?>&exam=<?= urlencode($selected_exam) ?>&tab=records" 
           class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 <?= ($active_tab === 'records') ? 'bg-navy-800 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' ?>">
            <i class="fa-solid fa-list-check text-blue-400"></i>
            <span>Historical Audit Log (All Records)</span>
        </a>
    </div>

    <?php if ($active_tab === 'matrix'): ?>
        <!-- ALL-SUBJECTS GRID MARKSHEET -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden mb-8 print-page">
            <div class="p-4 sm:p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/70">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-calculator"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 font-['Outfit']">
                            <?= htmlspecialchars($selected_class) ?> &bull; <?= htmlspecialchars($selected_exam) ?>
                        </h2>
                        <p class="text-xs text-slate-500">
                            <?= count($class_students) ?> Students enrolled &bull; <?= count($class_subjects) ?> Subjects configured for this standard
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-2 no-print">
                    <span class="text-xs text-slate-500">Total Marks Possible: 
                        <strong class="text-navy-950 font-bold">
                            <?= array_sum(array_column($class_subjects, 'max_marks')) ?>
                        </strong>
                    </span>
                </div>
            </div>

            <?php if (empty($class_students)): ?>
                <div class="p-10 text-center text-slate-500 text-xs">
                    <i class="fa-solid fa-user-slash text-3xl text-slate-300 mb-2"></i>
                    <p class="font-bold text-slate-700">No approved students found in <?= htmlspecialchars($selected_class) ?>.</p>
                </div>
            <?php else: ?>
                <form method="POST" action="marks.php" id="matrixMarksheetForm">
                    <input type="hidden" name="action" value="save_all_subjects_matrix">
                    <input type="hidden" name="class" value="<?= htmlspecialchars($selected_class) ?>">
                    <input type="hidden" name="exam_name" value="<?= htmlspecialchars($selected_exam) ?>">

                    <?php foreach ($class_subjects as $sub): ?>
                        <input type="hidden" name="max_marks[<?= htmlspecialchars($sub['name']) ?>]" value="<?= $sub['max_marks'] ?>">
                    <?php endforeach; ?>

                    <!-- Clean Printable Sheet Letterhead (Visible only on Print) -->
                    <div class="hidden print:block p-4 border-b-2 border-slate-900 text-center mb-4">
                        <h2 class="text-base font-black uppercase text-slate-900 font-['Outfit']"><?= htmlspecialchars($school_name) ?></h2>
                        <p class="text-xs font-bold text-amber-700 uppercase">Class Assessment &amp; Marksheet Register &bull; <?= htmlspecialchars($academic_year) ?></p>
                        <p class="text-xs text-slate-600 mt-1">Class: <strong><?= htmlspecialchars($selected_class) ?></strong> &bull; Examination: <strong><?= htmlspecialchars($selected_exam) ?></strong> &bull; Printed: <strong><?= date('d M Y') ?></strong></p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-100 text-[10px] font-bold text-slate-600 uppercase tracking-wider border-b border-slate-200">
                                    <th class="py-3 px-3.5 sticky left-0 bg-slate-100 z-10 w-12 text-center">Roll</th>
                                    <th class="py-3 px-3.5 sticky left-12 bg-slate-100 z-10 min-w-[140px]">Student Name</th>
                                    
                                    <!-- Dynamic Subject Columns -->
                                    <?php foreach ($class_subjects as $sub): ?>
                                        <th class="py-3 px-3 text-center min-w-[105px] border-l border-slate-200">
                                            <span class="block truncate" title="<?= htmlspecialchars($sub['name']) ?>">
                                                <?= htmlspecialchars($sub['name']) ?>
                                            </span>
                                            <span class="text-[9px] font-normal text-slate-400 block">
                                                (Max: <?= (int)$sub['max_marks'] ?>)
                                            </span>
                                        </th>
                                    <?php endforeach; ?>

                                    <th class="py-3 px-3 text-center min-w-[80px] bg-blue-50 text-blue-900 border-l border-slate-200">Total</th>
                                    <th class="py-3 px-3 text-center min-w-[70px] bg-blue-50 text-blue-900">Pct %</th>
                                    <th class="py-3 px-3 text-center min-w-[70px] bg-blue-50 text-blue-900">Grade</th>
                                    <th class="py-3 px-3 min-w-[140px] border-l border-slate-200">Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($class_students as $stu): ?>
                                    <?php
                                        $sid = $stu['id'];
                                        $stu_total_obtained = 0;
                                        $stu_total_max = 0;
                                        $has_marks = false;
                                        $student_remark = '';
                                    ?>
                                    <tr class="student-mark-row hover:bg-slate-50/80 transition" data-student-id="<?= $sid ?>">
                                        <input type="hidden" name="student_ids[]" value="<?= $sid ?>">
                                        
                                        <!-- Roll Number -->
                                        <td class="py-3 px-3.5 font-bold text-slate-800 text-center sticky left-0 bg-white">
                                            <?= htmlspecialchars($stu['roll_no'] ?: '-') ?>
                                        </td>

                                        <!-- Student Name -->
                                        <td class="py-3 px-3.5 font-bold text-slate-900 sticky left-12 bg-white border-r border-slate-200">
                                            <div class="truncate max-w-[150px]"><?= htmlspecialchars($stu['full_name']) ?></div>
                                            <div class="text-[10px] text-blue-600 font-mono"><?= htmlspecialchars($stu['student_code']) ?></div>
                                        </td>

                                        <!-- Subject Score Inputs -->
                                        <?php foreach ($class_subjects as $sub): ?>
                                            <?php
                                                $s_name = $sub['name'];
                                                $rec = $recorded_marks[$sid][$s_name] ?? null;
                                                $val = ($rec !== null) ? $rec['marks_obtained'] : '';
                                                $max_val = $sub['max_marks'];
                                                if ($val !== '') {
                                                    $stu_total_obtained += (float)$val;
                                                    $stu_total_max += (float)$max_val;
                                                    $has_marks = true;
                                                    if (!empty($rec['remarks'])) $student_remark = $rec['remarks'];
                                                } else {
                                                    $stu_total_max += (float)$max_val;
                                                }
                                            ?>
                                            <td class="py-2.5 px-2 text-center border-l border-slate-200">
                                                <input type="number" 
                                                       step="0.5" 
                                                       min="0" 
                                                       max="<?= (float)$max_val ?>"
                                                       name="marks[<?= $sid ?>][<?= htmlspecialchars($s_name) ?>]" 
                                                       value="<?= ($val !== '') ? htmlspecialchars($val) : '' ?>"
                                                       data-max="<?= (float)$max_val ?>"
                                                       placeholder="0-<?= (int)$max_val ?>"
                                                       class="sub-input w-20 px-2 py-1.5 text-center font-bold text-xs rounded-xl border border-slate-300 bg-white text-slate-800 focus:bg-amber-50 focus:border-navy-600 focus:outline-none focus:ring-1 focus:ring-navy-600 transition"
                                                       oninput="recalcRow(this)">
                                            </td>
                                        <?php endforeach; ?>

                                        <?php
                                            $calc_pct = ($stu_total_max > 0 && $has_marks) ? round(($stu_total_obtained / $stu_total_max) * 100, 1) : 0;
                                            $calc_grade = $has_marks ? calculate_grade($stu_total_obtained, $stu_total_max) : '-';
                                        ?>
                                        <!-- Row Calculations -->
                                        <td class="py-3 px-3 text-center font-bold text-slate-900 bg-blue-50/40 border-l border-slate-200">
                                            <span class="row-total"><?= $has_marks ? $stu_total_obtained : '-' ?></span>
                                            <span class="text-[9px] text-slate-400 font-normal">/<?= (int)$stu_total_max ?></span>
                                        </td>
                                        <td class="py-3 px-3 text-center font-bold text-blue-700 bg-blue-50/40">
                                            <span class="row-pct"><?= $has_marks ? $calc_pct . '%' : '-' ?></span>
                                        </td>
                                        <td class="py-3 px-3 text-center bg-blue-50/40">
                                            <span class="row-grade px-2 py-0.5 rounded text-[10px] font-black <?= in_array($calc_grade, ['A+', 'A']) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>">
                                                <?= $calc_grade ?>
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3 border-l border-slate-200">
                                            <input type="text" name="remarks[<?= $sid ?>]" 
                                                   value="<?= htmlspecialchars($student_remark) ?>" 
                                                   placeholder="Remark..." 
                                                   class="w-full px-2 py-1 text-[11px] rounded-lg border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Bottom Submit Bar -->
                    <div class="p-4 sm:p-6 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4 no-print">
                        <div class="text-xs text-slate-500">
                            <i class="fa-solid fa-circle-info text-blue-600 mr-1"></i>
                            Fill all subject marks in this grid. When saved, scores and report cards update immediately.
                        </div>
                        <button type="submit" class="px-8 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-lg shadow-emerald-700/20 transition flex items-center justify-center space-x-2">
                            <i class="fa-solid fa-floppy-disk text-amber-300"></i>
                            <span>Save Entire Marksheet (All Subjects at Once)</span>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- HISTORICAL AUDIT LOG TAB -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50/70">
                <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Historical Assessment Marks</h3>
                <span class="text-xs text-slate-400">Total <?= count($all_grades) ?> records</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4 sm:px-6">Student Info</th>
                            <th class="py-3 px-4">Subject</th>
                            <th class="py-3 px-4">Exam Term</th>
                            <th class="py-3 px-4 text-center">Score</th>
                            <th class="py-3 px-4 text-center">Percentage</th>
                            <th class="py-3 px-4 text-center">Grade</th>
                            <th class="py-3 px-4">Teacher Remarks</th>
                            <th class="py-3 px-4 sm:px-6 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($all_grades)): ?>
                            <tr>
                                <td colspan="8" class="py-12 text-center text-slate-400">No records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_grades as $g): ?>
                                <?php $pct = round(($g['marks_obtained'] / $g['max_marks']) * 100, 1); ?>
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 px-4 sm:px-6">
                                        <div class="font-bold text-slate-900"><?= htmlspecialchars($g['full_name']) ?></div>
                                        <div class="text-[11px] text-slate-500"><?= htmlspecialchars($g['class']) ?> &bull; Roll: <?= htmlspecialchars($g['roll_no'] ?: 'N/A') ?></div>
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-slate-900">
                                        <?= htmlspecialchars($g['subject']) ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600">
                                        <span class="px-2 py-0.5 rounded bg-slate-100 font-medium"><?= htmlspecialchars($g['exam_name']) ?></span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center font-bold text-slate-900">
                                        <?= $g['marks_obtained'] ?> <span class="text-slate-400 font-normal">/ <?= $g['max_marks'] ?></span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center font-semibold text-blue-600">
                                        <?= $pct ?>%
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-black 
                                            <?= in_array($g['grade'], ['A+', 'A']) ? 'bg-emerald-100 text-emerald-800' : (in_array($g['grade'], ['B+', 'B']) ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800') ?>">
                                            <?= htmlspecialchars($g['grade']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500 italic">
                                        <?= htmlspecialchars($g['remarks'] ?: 'None') ?>
                                    </td>
                                    <td class="py-3.5 px-4 sm:px-6 text-right">
                                        <form method="POST" action="marks.php" onsubmit="return confirm('Delete this grade record?');">
                                            <input type="hidden" name="action" value="delete_grade">
                                            <input type="hidden" name="grade_id" value="<?= $g['id'] ?>">
                                            <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg text-xs transition">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</main>

<!-- SINGLE STUDENT ALL-SUBJECTS MODAL -->
<div id="singleStudentModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto no-print">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-user-pen text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Fill Student Marksheet (All Subjects)</h3>
                    <p class="text-xs text-slate-400">Enter marks for every subject for a single student</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('singleStudentModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="marks.php" class="space-y-4 text-xs">
            <input type="hidden" name="action" value="save_student_all_subjects">
            <input type="hidden" name="exam_name" value="<?= htmlspecialchars($selected_exam) ?>">

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Select Student *</label>
                <select name="student_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none focus:ring-2 focus:ring-navy-600">
                    <option value="">-- Choose Student --</option>
                    <?php foreach ($class_students as $stu): ?>
                        <option value="<?= $stu['id'] ?>">
                            <?= htmlspecialchars($stu['full_name']) ?> (Roll <?= htmlspecialchars($stu['roll_no'] ?: 'N/A') ?> &bull; <?= htmlspecialchars($stu['student_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <span class="block font-bold text-slate-700 uppercase tracking-wider mb-2">Subject Scores for <?= htmlspecialchars($selected_exam) ?>:</span>
                <div class="space-y-2.5 max-h-60 overflow-y-auto p-3 bg-slate-50 rounded-2xl border border-slate-200">
                    <?php foreach ($class_subjects as $sub): ?>
                        <div class="flex items-center justify-between gap-3 bg-white p-2.5 rounded-xl border border-slate-200">
                            <div>
                                <span class="font-bold text-slate-800 block"><?= htmlspecialchars($sub['name']) ?></span>
                                <span class="text-[10px] text-slate-400">Max: <?= (int)$sub['max_marks'] ?> &bull; Pass: <?= (int)$sub['pass_marks'] ?></span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <input type="number" step="0.5" min="0" max="<?= (float)$sub['max_marks'] ?>" 
                                       name="subject_marks[<?= htmlspecialchars($sub['name']) ?>]" 
                                       placeholder="Marks"
                                       class="w-20 px-2.5 py-1.5 rounded-lg border border-slate-300 text-center font-bold text-xs focus:outline-none focus:ring-1 focus:ring-blue-600">
                                <input type="hidden" name="subject_max[<?= htmlspecialchars($sub['name']) ?>]" value="<?= (float)$sub['max_marks'] ?>">
                                <span class="text-xs text-slate-400">/<?= (int)$sub['max_marks'] ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Teacher Remarks (Optional)</label>
                <input type="text" name="remarks" placeholder="e.g. Good performance, excellent effort" 
                       class="w-full px-3.5 py-2 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:outline-none">
            </div>

            <div class="pt-3 flex justify-end space-x-2 border-t border-slate-100">
                <button type="button" onclick="closeModal('singleStudentModal')" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50 font-bold">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold shadow-md">
                    <i class="fa-solid fa-floppy-disk mr-1 text-amber-400"></i> Save Student Marks
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function recalcRow(input) {
        const row = input.closest('tr');
        if (!row) return;

        let totalObt = 0;
        let totalMax = 0;
        let hasAnyInput = false;

        row.querySelectorAll('.sub-input').forEach(inp => {
            const max = parseFloat(inp.getAttribute('data-max') || 100);
            totalMax += max;
            if (inp.value !== '' && !isNaN(inp.value)) {
                totalObt += parseFloat(inp.value);
                hasAnyInput = true;
            }
        });

        const totalEl = row.querySelector('.row-total');
        const pctEl = row.querySelector('.row-pct');
        const gradeEl = row.querySelector('.row-grade');

        if (hasAnyInput) {
            totalEl.textContent = totalObt;
            const pct = (totalMax > 0) ? Math.round((totalObt / totalMax) * 1000) / 10 : 0;
            pctEl.textContent = pct + '%';

            let grade = 'F';
            if (pct >= 90) grade = 'A+';
            else if (pct >= 80) grade = 'A';
            else if (pct >= 70) grade = 'B+';
            else if (pct >= 60) grade = 'B';
            else if (pct >= 50) grade = 'C';
            else if (pct >= 35) grade = 'D';

            gradeEl.textContent = grade;
            if (['A+', 'A'].includes(grade)) {
                gradeEl.className = 'row-grade px-2 py-0.5 rounded text-[10px] font-black bg-emerald-100 text-emerald-800';
            } else if (['B+', 'B'].includes(grade)) {
                gradeEl.className = 'row-grade px-2 py-0.5 rounded text-[10px] font-black bg-blue-100 text-blue-800';
            } else if (['C', 'D'].includes(grade)) {
                gradeEl.className = 'row-grade px-2 py-0.5 rounded text-[10px] font-black bg-amber-100 text-amber-800';
            } else {
                gradeEl.className = 'row-grade px-2 py-0.5 rounded text-[10px] font-black bg-rose-100 text-rose-800';
            }
        } else {
            totalEl.textContent = '-';
            pctEl.textContent = '-';
            gradeEl.textContent = '-';
            gradeEl.className = 'row-grade px-2 py-0.5 rounded text-[10px] font-black bg-slate-200 text-slate-700';
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
