<?php
/**
 * Teacher Assignments & Homework Portal
 * ABC School Nashik ERP System
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_auth('teacher');

$user = current_user();
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();

if (!$teacher) {
    die("Teacher profile not found.");
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Create uploads directory if not exists
$upload_dir = __DIR__ . '/../uploads/assignments';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_assignment') {
        $class = trim($_POST['class'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_date = trim($_POST['due_date'] ?? '');
        $max_marks = floatval($_POST['max_marks'] ?? 100);

        if (empty($class) || empty($subject) || empty($title) || empty($due_date)) {
            $_SESSION['error'] = "Class, Subject, Assignment Title, and Due Date are mandatory.";
            header("Location: /teacher/assignments.php");
            exit;
        }

        $attachment_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['attachment']['tmp_name'];
            $file_name = $_FILES['attachment']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'txt', 'png', 'jpg', 'jpeg', 'zip'];

            if (!in_array($ext, $allowed)) {
                $_SESSION['error'] = "Invalid file type. Allowed: PDF, DOC, DOCX, TXT, PNG, JPG, ZIP.";
                header("Location: /teacher/assignments.php");
                exit;
            }

            $new_name = 'asgn_' . $teacher['id'] . '_' . time() . '.' . $ext;
            $dest = $upload_dir . '/' . $new_name;
            if (move_uploaded_file($file_tmp, $dest)) {
                $attachment_path = 'uploads/assignments/' . $new_name;
            }
        }

        $ins = $pdo->prepare("INSERT INTO assignments (teacher_id, class, subject, title, description, due_date, max_marks, attachment_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$user['id'], $class, $subject, $title, $description, $due_date, $max_marks, $attachment_path]);

        $_SESSION['success'] = "New assignment '{$title}' created successfully for {$class}.";
        header("Location: /teacher/assignments.php");
        exit;
    }

    if ($action === 'grade_submission') {
        $submission_id = intval($_POST['submission_id'] ?? 0);
        $marks_obtained = floatval($_POST['marks_obtained'] ?? 0);
        $teacher_feedback = trim($_POST['teacher_feedback'] ?? '');
        $asgn_id = intval($_POST['asgn_id'] ?? 0);

        if ($submission_id > 0) {
            $upd = $pdo->prepare("UPDATE assignment_submissions SET marks_obtained = ?, teacher_feedback = ?, status = 'Graded' WHERE id = ?");
            $upd->execute([$marks_obtained, $teacher_feedback, $submission_id]);
            $_SESSION['success'] = "Student submission graded successfully! Marks recorded.";
            header("Location: /teacher/assignments.php?view_submissions={$asgn_id}");
            exit;
        }
    }

    if ($action === 'delete_assignment') {
        $asgn_id = intval($_POST['asgn_id'] ?? 0);
        if ($asgn_id > 0) {
            // Find attachment to unlink if present
            $chk = $pdo->prepare("SELECT attachment_path FROM assignments WHERE id = ? AND teacher_id = ?");
            $chk->execute([$asgn_id, $user['id']]);
            $row = $chk->fetch();
            if ($row) {
                if (!empty($row['attachment_path']) && file_exists(__DIR__ . '/../' . $row['attachment_path'])) {
                    @unlink(__DIR__ . '/../' . $row['attachment_path']);
                }
                $del = $pdo->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?");
                $del->execute([$asgn_id, $user['id']]);
                $_SESSION['success'] = "Assignment deleted successfully.";
            }
            header("Location: /teacher/assignments.php");
            exit;
        }
    }
}

// Fetch teacher's assignments
$my_assignments = $pdo->prepare("
    SELECT a.*, 
           (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) as total_submissions,
           (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.status = 'Graded') as graded_count
    FROM assignments a
    WHERE a.teacher_id = ?
    ORDER BY a.id DESC
");
$my_assignments->execute([$user['id']]);
$assignments = $my_assignments->fetchAll();

// Check if viewing submissions for a specific assignment
$view_asgn_id = isset($_GET['view_submissions']) ? intval($_GET['view_submissions']) : null;
$view_asgn = null;
$submissions = [];

if ($view_asgn_id) {
    $stmtAsgn = $pdo->prepare("SELECT * FROM assignments WHERE id = ? AND teacher_id = ?");
    $stmtAsgn->execute([$view_asgn_id, $user['id']]);
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

$page_title = "Assignments & Homework - Teacher Portal";
include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-book-bookmark text-emerald-600"></i>
                Assignments & Homework Management
            </h1>
            <p class="text-sm text-slate-500">Post homework tasks, track student file submissions, and record grades with feedback.</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($view_asgn): ?>
                <a href="/teacher/assignments.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-lg inline-flex items-center gap-2 transition">
                    <i class="fa-solid fa-arrow-left"></i> Back to All Assignments
                </a>
            <?php endif; ?>
            <button onclick="document.getElementById('newAssignmentModal').classList.remove('hidden')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg inline-flex items-center gap-2 shadow-sm transition">
                <i class="fa-solid fa-plus"></i> Create New Assignment
            </button>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($view_asgn): ?>
        <!-- SUBMISSION REVIEW VIEW -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 bg-gradient-to-r from-emerald-50 to-teal-50 border-b border-slate-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-600 text-white">
                            <?= htmlspecialchars($view_asgn['class']) ?>
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-700">
                            <?= htmlspecialchars($view_asgn['subject']) ?>
                        </span>
                    </div>
                    <h2 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($view_asgn['title']) ?></h2>
                    <p class="text-sm text-slate-600 mt-1 max-w-3xl"><?= nl2br(htmlspecialchars($view_asgn['description'])) ?></p>
                </div>
                <div class="flex md:flex-col items-end justify-between md:justify-center gap-2 text-right">
                    <div class="text-xs text-slate-500">
                        Due Date: <span class="font-semibold text-rose-600"><?= date('d M Y', strtotime($view_asgn['due_date'])) ?></span>
                    </div>
                    <div class="text-xs text-slate-500">
                        Max Marks: <span class="font-bold text-slate-800"><?= $view_asgn['max_marks'] ?></span>
                    </div>
                    <?php if (!empty($view_asgn['attachment_path'])): ?>
                        <a href="/<?= htmlspecialchars($view_asgn['attachment_path']) ?>" download class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 bg-white px-3 py-1.5 rounded-lg border border-indigo-200 shadow-2xs">
                            <i class="fa-solid fa-paperclip"></i> Download Attached Sheet
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Submissions Table -->
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-emerald-600"></i>
                        Student Submissions (<?= count($submissions) ?> received)
                    </h3>
                </div>

                <?php if (empty($submissions)): ?>
                    <div class="py-12 text-center text-slate-400">
                        <i class="fa-solid fa-inbox text-4xl mb-3 text-slate-300"></i>
                        <p class="text-base font-medium text-slate-600">No submissions uploaded yet</p>
                        <p class="text-xs text-slate-400 mt-1">Students will see this assignment in their portal and upload solutions.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-600">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-y border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Student</th>
                                    <th class="px-4 py-3">Submitted At</th>
                                    <th class="px-4 py-3">Student File & Remarks</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Marks</th>
                                    <th class="px-4 py-3 text-right">Evaluation Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($submissions as $sub): 
                                    $is_graded = ($sub['status'] === 'Graded');
                                ?>
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="px-4 py-3.5">
                                            <div class="font-semibold text-slate-800"><?= htmlspecialchars($sub['student_name']) ?></div>
                                            <div class="text-xs text-slate-400">Roll: <?= htmlspecialchars($sub['roll_no'] ?: 'N/A') ?> • <?= htmlspecialchars($sub['student_code']) ?></div>
                                        </td>
                                        <td class="px-4 py-3.5 text-xs text-slate-500">
                                            <?= date('d M Y, h:i A', strtotime($sub['submitted_at'])) ?>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <div class="flex items-center gap-2 mb-1">
                                                <a href="/<?= htmlspecialchars($sub['file_path']) ?>" download class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 text-xs font-medium">
                                                    <i class="fa-solid fa-file-arrow-down"></i> Download Solution
                                                </a>
                                            </div>
                                            <?php if (!empty($sub['student_remarks'])): ?>
                                                <p class="text-xs text-slate-500 italic max-w-xs">"<?= htmlspecialchars($sub['student_remarks']) ?>"</p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <?php if ($is_graded): ?>
                                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">
                                                    <i class="fa-solid fa-check-double mr-1"></i> Graded
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">
                                                    <i class="fa-regular fa-clock mr-1"></i> Pending Grade
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <?php if ($is_graded): ?>
                                                <div class="font-bold text-slate-800 text-sm">
                                                    <?= $sub['marks_obtained'] ?> <span class="text-xs text-slate-400 font-normal">/ <?= $view_asgn['max_marks'] ?></span>
                                                </div>
                                                <?php if (!empty($sub['teacher_feedback'])): ?>
                                                    <div class="text-xs text-emerald-700 mt-0.5 truncate max-w-xs" title="<?= htmlspecialchars($sub['teacher_feedback']) ?>">
                                                        <i class="fa-solid fa-comment-dots text-emerald-500 mr-1"></i><?= htmlspecialchars($sub['teacher_feedback']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 italic">Not evaluated</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3.5 text-right">
                                            <button onclick="openGradeModal(<?= htmlspecialchars(json_encode($sub)) ?>, <?= $view_asgn['max_marks'] ?>, <?= $view_asgn['id'] ?>)" class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-medium text-xs border border-emerald-200 transition">
                                                <i class="fa-solid fa-pen-to-square mr-1"></i> <?= $is_graded ? 'Edit Marks' : 'Grade Now' ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>

        <!-- ASSIGNMENTS OVERVIEW STATS -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs flex items-center space-x-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Assignments</p>
                    <p class="text-2xl font-bold text-slate-800"><?= count($assignments) ?></p>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs flex items-center space-x-4">
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Submissions</p>
                    <p class="text-2xl font-bold text-slate-800">
                        <?= array_sum(array_column($assignments, 'total_submissions')) ?>
                    </p>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs flex items-center space-x-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pending Evaluation</p>
                    <p class="text-2xl font-bold text-slate-800">
                        <?= array_sum(array_column($assignments, 'total_submissions')) - array_sum(array_column($assignments, 'graded_count')) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- LIST OF ASSIGNMENTS -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">My Posted Homework & Tasks</h2>
                    <p class="text-xs text-slate-500">Click on 'Review Submissions' to download student homework and award marks.</p>
                </div>
            </div>

            <?php if (empty($assignments)): ?>
                <div class="py-16 text-center text-slate-400">
                    <i class="fa-solid fa-folder-open text-5xl mb-3 text-slate-300"></i>
                    <p class="text-base font-semibold text-slate-700">No assignments posted yet</p>
                    <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Click "Create New Assignment" above to assign problem sets, worksheets, or lab projects to your classes.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3.5">Assignment Details</th>
                                <th class="px-4 py-3.5">Target Class</th>
                                <th class="px-4 py-3.5">Due Date</th>
                                <th class="px-4 py-3.5">Submissions</th>
                                <th class="px-4 py-3.5">Attachment</th>
                                <th class="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($assignments as $a): 
                                $is_expired = (strtotime($a['due_date']) < strtotime(date('Y-m-d')));
                                $pending_eval = $a['total_submissions'] - $a['graded_count'];
                            ?>
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-800"><?= htmlspecialchars($a['title']) ?></div>
                                        <div class="text-xs text-slate-500 mt-0.5 line-clamp-1 max-w-md"><?= htmlspecialchars($a['description']) ?></div>
                                        <div class="text-xs text-emerald-600 font-medium mt-1">
                                            <i class="fa-solid fa-award mr-1"></i>Max Marks: <?= $a['max_marks'] ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                            <?= htmlspecialchars($a['class']) ?>
                                        </span>
                                        <div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($a['subject']) ?></div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="font-medium <?= $is_expired ? 'text-rose-600' : 'text-slate-700' ?>">
                                            <?= date('d M Y', strtotime($a['due_date'])) ?>
                                        </div>
                                        <div class="text-xs <?= $is_expired ? 'text-rose-400 font-semibold' : 'text-slate-400' ?>">
                                            <?= $is_expired ? 'Deadline passed' : 'Active' ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-bold text-slate-800"><?= $a['total_submissions'] ?></span>
                                            <span class="text-xs text-slate-400">submitted</span>
                                        </div>
                                        <?php if ($pending_eval > 0): ?>
                                            <span class="inline-block mt-1 px-2 py-0.5 text-2xs font-bold rounded bg-amber-100 text-amber-800">
                                                <?= $pending_eval ?> need review
                                            </span>
                                        <?php elseif ($a['total_submissions'] > 0): ?>
                                            <span class="inline-block mt-1 px-2 py-0.5 text-2xs font-bold rounded bg-emerald-100 text-emerald-800">
                                                All graded
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php if (!empty($a['attachment_path'])): ?>
                                            <a href="/<?= htmlspecialchars($a['attachment_path']) ?>" download class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium transition">
                                                <i class="fa-solid fa-paperclip text-slate-400"></i> File
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <a href="/teacher/assignments.php?view_submissions=<?= $a['id'] ?>" class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-medium text-xs border border-emerald-200 inline-flex items-center gap-1.5 transition">
                                            <i class="fa-solid fa-list-check"></i> Submissions (<?= $a['total_submissions'] ?>)
                                        </a>
                                        <form method="POST" class="inline-block" onsubmit="return confirm('Delete this assignment and all associated student submissions?')">
                                            <input type="hidden" name="action" value="delete_assignment">
                                            <input type="hidden" name="asgn_id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 rounded transition" title="Delete Assignment">
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
    <?php endif; ?>
</div>

<!-- MODAL: CREATE NEW ASSIGNMENT -->
<div id="newAssignmentModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-plus-circle text-emerald-600"></i>
                Create Assignment / Homework
            </h3>
            <button onclick="document.getElementById('newAssignmentModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="action" value="create_assignment">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Target Class *</label>
                    <select name="class" required class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="Class 10-A">Class 10-A</option>
                        <option value="Class 10-B">Class 10-B</option>
                        <option value="Class 9-A">Class 9-A</option>
                        <option value="Class 9-B">Class 9-B</option>
                        <option value="Class 8-A">Class 8-A</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Subject *</label>
                    <input type="text" name="subject" required value="<?= htmlspecialchars($teacher['department']) ?>" placeholder="e.g. Mathematics" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Assignment Title *</label>
                <input type="text" name="title" required placeholder="e.g. Chapter 4 Exercise Problems & Derivations" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Detailed Instructions / Description</label>
                <textarea name="description" rows="3" placeholder="Provide problem numbers, guidelines, or lab report expectations..." class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Submission Deadline *</label>
                    <input type="date" name="due_date" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Maximum Marks *</label>
                    <input type="number" name="max_marks" required value="50" min="5" max="100" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Attach Question Sheet / Reference File (Optional)</label>
                <input type="file" name="attachment" accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg,.zip" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                <p class="text-2xs text-slate-400 mt-1">Allowed: PDF, DOCX, TXT, Images, ZIP up to 10MB.</p>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('newAssignmentModal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold shadow-xs">Publish Assignment</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: GRADE SUBMISSION -->
<div id="gradeModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-award text-emerald-600"></i>
                Evaluate Student Submission
            </h3>
            <button onclick="document.getElementById('gradeModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="grade_submission">
            <input type="hidden" name="submission_id" id="modal_submission_id">
            <input type="hidden" name="asgn_id" id="modal_asgn_id">

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600">
                <div class="font-bold text-slate-800 text-sm" id="modal_student_name">Student Name</div>
                <div class="text-slate-500 mt-0.5" id="modal_student_info">Roll No • Class</div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Marks Obtained (Out of <span id="modal_max_marks">100</span>) *</label>
                <input type="number" step="0.5" name="marks_obtained" id="modal_marks_obtained" required min="0" max="100" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Teacher Remarks / Feedback</label>
                <textarea name="teacher_feedback" id="modal_teacher_feedback" rows="3" placeholder="e.g. Good derivation of formulas, neat handwriting..." class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('gradeModal').classList.add('hidden')" class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold shadow-xs">Save Marks & Feedback</button>
            </div>
        </form>
    </div>
</div>

<script>
function openGradeModal(sub, maxMarks, asgnId) {
    document.getElementById('modal_submission_id').value = sub.id;
    document.getElementById('modal_asgn_id').value = asgnId;
    document.getElementById('modal_student_name').innerText = sub.student_name;
    document.getElementById('modal_student_info').innerText = 'Student Code: ' + sub.student_code + ' • Roll: ' + (sub.roll_no || 'N/A');
    document.getElementById('modal_max_marks').innerText = maxMarks;
    document.getElementById('modal_marks_obtained').max = maxMarks;
    document.getElementById('modal_marks_obtained').value = sub.marks_obtained !== null ? sub.marks_obtained : '';
    document.getElementById('modal_teacher_feedback').value = sub.teacher_feedback || '';
    document.getElementById('gradeModal').classList.remove('hidden');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
