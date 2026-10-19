<?php
/**
 * Teacher: Marks & Assessment Management
 * ABC School Nashik ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('teacher');

$teacher_user_id = $_SESSION['user_id'];
$error = '';
$flash = get_flash();
$preselected_student_id = (int)($_GET['student_id'] ?? 0);

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

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_grade') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $exam_name = trim($_POST['exam_name'] ?? '');
        $marks_obtained = (float)($_POST['marks_obtained'] ?? 0);
        $max_marks = (float)($_POST['max_marks'] ?? 100);
        $remarks = trim($_POST['remarks'] ?? '');

        if ($student_id <= 0 || empty($subject) || empty($exam_name) || $max_marks <= 0) {
            $error = 'Please fill all required grade details.';
        } else {
            $grade = calculate_grade($marks_obtained, $max_marks);

            $stmt = $pdo->prepare("
                INSERT INTO grades (student_id, subject, exam_name, marks_obtained, max_marks, grade, remarks, teacher_id)
                VALUES (:sid, :subj, :exam, :obt, :max, :grd, :rmk, :tid)
            ");
            $stmt->execute([
                'sid' => $student_id,
                'subj' => $subject,
                'exam' => $exam_name,
                'obt' => $marks_obtained,
                'max' => $max_marks,
                'grd' => $grade,
                'rmk' => $remarks,
                'tid' => $teacher_user_id
            ]);

            set_flash('success', "Marks for {$subject} ({$exam_name}) recorded successfully!");
            header("Location: /teacher/marks.php");
            exit();
        }
    } elseif ($action === 'delete_grade') {
        $grade_id = (int)($_POST['grade_id'] ?? 0);
        if ($grade_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM grades WHERE id = :id");
            $stmt->execute(['id' => $grade_id]);
            set_flash('success', 'Grade record deleted.');
            header("Location: /teacher/marks.php");
            exit();
        }
    }
}

// Fetch approved students for select dropdown
$students = $pdo->query("
    SELECT s.id, s.full_name, s.class, s.roll_no, s.student_code
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE u.status = 'approved'
    ORDER BY s.class ASC, s.roll_no ASC
")->fetchAll();

// Fetch grades entered
$grades = $pdo->query("
    SELECT g.*, s.full_name, s.class, s.roll_no, s.student_code
    FROM grades g
    JOIN students s ON g.student_id = s.id
    ORDER BY g.id DESC
")->fetchAll();
?>

<?php 
$page_title = 'Marks & Grades Assessment';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php'; 
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
                <span>Evaluation &amp; Assessments</span>
                <span>&bull;</span>
                <span class="text-slate-400">Total: <?= count($grades) ?> Marks Entries</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Student Marks &amp; Assessment Register
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Record unit test, term, and semester scores. These marks automatically reflect on student report cards.
            </p>
        </div>

        <button type="button" onclick="openModal('addGradeModal')" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-semibold text-xs shadow-md shadow-navy-900/10 transition">
            <i class="fa-solid fa-plus-circle mr-2 text-amber-400"></i> Enter New Marks
        </button>
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

    <?php if (!empty($error)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center space-x-2.5">
            <i class="fa-solid fa-triangle-exclamation text-base"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Search & Filter -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="relative w-full sm:w-80">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
            </div>
            <input type="text" id="marksSearch" onkeyup="filterTable('marksSearch', 'marksTable')" placeholder="Search student, subject, exam..." 
                   class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
        </div>
        <div class="text-xs text-slate-500">
            Showing <strong class="text-slate-900"><?= count($grades) ?></strong> marks records
        </div>
    </div>

    <!-- Marks Table -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table id="marksTable" class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-200">
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
                <tbody class="divide-y divide-slate-100 text-xs">
                    <?php if (empty($grades)): ?>
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-500">
                                No assessment records found. Click "Enter New Marks" to submit student scores.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($grades as $g): ?>
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
                                <form method="POST" action="marks.php" onsubmit="return confirm('Delete this grade entry?');">
                                    <input type="hidden" name="action" value="delete_grade">
                                    <input type="hidden" name="grade_id" value="<?= $g['id'] ?>">
                                    <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg text-xs transition">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- ADD GRADE MODAL -->
<div id="addGradeModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-award text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">Record Student Marks</h3>
                    <p class="text-xs text-slate-500">Calculate scores &amp; generate report card grades</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('addGradeModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="marks.php" class="space-y-4">
            <input type="hidden" name="action" value="add_grade">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Select Student *</label>
                <select name="student_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-navy-600">
                    <option value="">-- Choose Student --</option>
                    <?php foreach ($students as $stu): ?>
                        <option value="<?= $stu['id'] ?>" <?= ($preselected_student_id === $stu['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($stu['full_name']) ?> (<?= htmlspecialchars($stu['class']) ?> &bull; Roll: <?= htmlspecialchars($stu['roll_no'] ?: 'N/A') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Subject *</label>
                    <select name="subject" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                        <option value="Mathematics">Mathematics</option>
                        <option value="Science">Science</option>
                        <option value="English">English</option>
                        <option value="Social Science">Social Science</option>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Hindi">Hindi</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Exam / Term *</label>
                    <select name="exam_name" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                        <option value="Mid-Term 2026">Mid-Term 2026</option>
                        <option value="Unit Test 1">Unit Test 1</option>
                        <option value="Unit Test 2">Unit Test 2</option>
                        <option value="Quarterly Exam">Quarterly Exam</option>
                        <option value="Final Examination">Final Examination</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Marks Obtained *</label>
                    <input type="number" step="0.5" min="0" max="100" name="marks_obtained" required placeholder="85" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Maximum Marks *</label>
                    <input type="number" step="1" min="1" name="max_marks" required value="100" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Teacher Remarks</label>
                <input type="text" name="remarks" placeholder="e.g. Excellent progress in problem solving" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
            </div>

            <div class="pt-4 flex items-center justify-end space-x-3">
                <button type="button" onclick="closeModal('addGradeModal')" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-semibold text-xs">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md">
                    <i class="fa-solid fa-floppy-disk mr-1.5 text-amber-400"></i> Save Marks Entry
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($preselected_student_id > 0): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            openModal('addGradeModal');
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
