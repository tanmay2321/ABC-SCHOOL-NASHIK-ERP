<?php
/**
 * Student Assignments & Homework Portal
 * ABC School Nashik ERP System
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('student');

$user = current_user();
$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$user['id']]);
$student = $stmt->fetch();

if (!$student) {
    die("Student profile not found.");
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Create uploads directory if not exists
$upload_dir = __DIR__ . '/../uploads/submissions';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

// Handle Homework Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload_submission') {
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        $student_remarks = trim($_POST['student_remarks'] ?? '');

        // Verify assignment exists and is for this student's class
        $asgn_chk = $pdo->prepare("SELECT * FROM assignments WHERE id = ? AND class = ?");
        $asgn_chk->execute([$assignment_id, $student['class']]);
        $assignment = $asgn_chk->fetch();

        if (!$assignment) {
            $_SESSION['error'] = "Invalid assignment selected.";
            header("Location: /student/assignments.php");
            exit;
        }

        if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Please choose a valid file to upload.";
            header("Location: /student/assignments.php");
            exit;
        }

        $file_tmp = $_FILES['submission_file']['tmp_name'];
        $file_name = $_FILES['submission_file']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'txt', 'png', 'jpg', 'jpeg', 'zip'];

        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = "File format not supported. Allowed formats: PDF, DOCX, TXT, PNG, JPG, ZIP.";
            header("Location: /student/assignments.php");
            exit;
        }

        if ($_FILES['submission_file']['size'] > 15 * 1024 * 1024) {
            $_SESSION['error'] = "File size exceeds 15MB limit.";
            header("Location: /student/assignments.php");
            exit;
        }

        $new_filename = 'sub_' . $student['id'] . '_' . $assignment_id . '_' . time() . '.' . $ext;
        $dest = $upload_dir . '/' . $new_filename;

        if (move_uploaded_file($file_tmp, $dest)) {
            $file_path = 'uploads/submissions/' . $new_filename;

            // Check if submission already exists
            $sub_chk = $pdo->prepare("SELECT id, file_path FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
            $sub_chk->execute([$assignment_id, $student['id']]);
            $existing = $sub_chk->fetch();

            if ($existing) {
                // Remove old file if present
                if (!empty($existing['file_path']) && file_exists(__DIR__ . '/../' . $existing['file_path'])) {
                    @unlink(__DIR__ . '/../' . $existing['file_path']);
                }
                $upd = $pdo->prepare("UPDATE assignment_submissions SET file_path = ?, student_remarks = ?, submitted_at = CURRENT_TIMESTAMP, status = 'Submitted' WHERE id = ?");
                $upd->execute([$file_path, $student_remarks, $existing['id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, file_path, student_remarks, status) VALUES (?, ?, ?, ?, 'Submitted')");
                $ins->execute([$assignment_id, $student['id'], $file_path, $student_remarks]);
            }

            $_SESSION['success'] = "Homework file uploaded successfully for '" . htmlspecialchars($assignment['title']) . "'!";
            header("Location: /student/assignments.php");
            exit;
        } else {
            $_SESSION['error'] = "Failed to save file on the server. Please check folder permissions.";
            header("Location: /student/assignments.php");
            exit;
        }
    }
}

// Fetch all assignments for this student's class
$asgnStmt = $pdo->prepare("
    SELECT a.*, 
           COALESCE(t.full_name, 'Faculty Member') as teacher_name,
           s.id as submission_id,
           s.file_path,
           s.student_remarks,
           s.submitted_at,
           s.marks_obtained,
           s.teacher_feedback,
           s.status as submission_status
    FROM assignments a
    LEFT JOIN users u ON a.teacher_id = u.id
    LEFT JOIN teachers t ON t.user_id = u.id
    LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = ?
    WHERE a.class = ?
    ORDER BY a.due_date ASC, a.id DESC
");
$asgnStmt->execute([$student['id'], $student['class']]);
$assignments = $asgnStmt->fetchAll();

// Statistics
$total_assigned = count($assignments);
$total_submitted = 0;
$total_graded = 0;
$total_pending = 0;

foreach ($assignments as $a) {
    if (!empty($a['submission_id'])) {
        $total_submitted++;
        if ($a['submission_status'] === 'Graded') {
            $total_graded++;
        }
    } else {
        $total_pending++;
    }
}

$page_title = "Assignments & Homework - Student Portal";
include __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-book-open-reader text-indigo-600"></i>
                Assignments & Homework Portal
            </h1>
            <p class="text-sm text-slate-500">Access class homework, download worksheets, upload your solutions, and view teacher evaluations.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-bold">
                <i class="fa-solid fa-graduation-cap mr-1"></i> <?= htmlspecialchars($student['class']) ?>
            </span>
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

    <!-- Overview Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-400 uppercase">Total Tasks</span>
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
            </div>
            <div class="text-2xl font-bold text-slate-800"><?= $total_assigned ?></div>
            <p class="text-2xs text-slate-400 mt-1">Given this term</p>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-400 uppercase">Submitted</span>
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </div>
            </div>
            <div class="text-2xl font-bold text-blue-600"><?= $total_submitted ?></div>
            <p class="text-2xs text-slate-400 mt-1">Uploaded solutions</p>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-400 uppercase">Graded</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-award"></i>
                </div>
            </div>
            <div class="text-2xl font-bold text-emerald-600"><?= $total_graded ?></div>
            <p class="text-2xs text-slate-400 mt-1">With teacher marks</p>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-400 uppercase">To Do</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
            </div>
            <div class="text-2xl font-bold text-amber-600"><?= $total_pending ?></div>
            <p class="text-2xs text-slate-400 mt-1">Pending submission</p>
        </div>
    </div>

    <!-- Assignments List -->
    <?php if (empty($assignments)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-400 shadow-2xs">
            <i class="fa-solid fa-mug-hot text-5xl mb-3 text-slate-300"></i>
            <h3 class="text-lg font-bold text-slate-700">No Assignments Currently Due</h3>
            <p class="text-sm text-slate-400 mt-1">Your subject teachers haven't posted any homework for <?= htmlspecialchars($student['class']) ?> yet. Check back later!</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($assignments as $a): 
                $is_submitted = !empty($a['submission_id']);
                $is_graded = ($a['submission_status'] === 'Graded');
                $due_timestamp = strtotime($a['due_date']);
                $today_timestamp = strtotime(date('Y-m-d'));
                $diff_days = floor(($due_timestamp - $today_timestamp) / 86400);
            ?>
                <div class="bg-white rounded-2xl border <?= $is_graded ? 'border-emerald-200' : ($is_submitted ? 'border-blue-200' : ($diff_days < 0 ? 'border-rose-200' : 'border-slate-200')) ?> shadow-2xs overflow-hidden transition hover:shadow-md">
                    <!-- Top Bar of Card -->
                    <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col md:flex-row md:items-start justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    <i class="fa-solid fa-book mr-1"></i> <?= htmlspecialchars($a['subject']) ?>
                                </span>

                                <?php if ($is_graded): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-circle-check mr-1"></i> Graded
                                    </span>
                                <?php elseif ($is_submitted): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                        <i class="fa-solid fa-cloud-arrow-up mr-1"></i> Submitted (Awaiting Grade)
                                    </span>
                                <?php elseif ($diff_days < 0): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                        <i class="fa-solid fa-circle-exclamation mr-1"></i> Past Due Date
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                        <i class="fa-regular fa-clock mr-1"></i> Due in <?= $diff_days == 0 ? 'Today' : $diff_days . ' days' ?>
                                    </span>
                                <?php endif; ?>

                                <span class="text-xs text-slate-400">
                                    • Assigned by <span class="font-medium text-slate-600"><?= htmlspecialchars($a['teacher_name']) ?></span>
                                </span>
                            </div>

                            <h3 class="text-lg font-bold text-slate-800"><?= htmlspecialchars($a['title']) ?></h3>
                            <p class="text-sm text-slate-600 mt-1 leading-relaxed"><?= nl2br(htmlspecialchars($a['description'])) ?></p>

                            <!-- Teacher Attachment -->
                            <?php if (!empty($a['attachment_path'])): ?>
                                <div class="mt-3">
                                    <a href="/<?= htmlspecialchars($a['attachment_path']) ?>" download class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold border border-slate-200 transition">
                                        <i class="fa-solid fa-paperclip text-slate-500"></i>
                                        Download Teacher's Question Sheet / Guidelines
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Right Info & Grade Display -->
                        <div class="flex flex-row md:flex-col items-center md:items-end justify-between md:justify-start gap-3 shrink-0 pt-2 md:pt-0">
                            <div class="text-right">
                                <div class="text-xs text-slate-400">Max Marks</div>
                                <div class="text-base font-bold text-slate-800"><?= $a['max_marks'] ?> pts</div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-slate-400">Due Date</div>
                                <div class="text-xs font-semibold text-slate-700"><?= date('d M Y', strtotime($a['due_date'])) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Status / Action Section -->
                    <div class="p-5 sm:p-6 bg-slate-50/50">
                        <?php if ($is_graded): ?>
                            <!-- GRADED RESULT VIEW -->
                            <div class="bg-emerald-50/80 border border-emerald-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xl shadow-xs">
                                        <i class="fa-solid fa-award"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-emerald-800 uppercase tracking-wider">Evaluation Score</div>
                                        <div class="text-2xl font-black text-emerald-900">
                                            <?= $a['marks_obtained'] ?> <span class="text-sm font-semibold text-emerald-700">/ <?= $a['max_marks'] ?></span>
                                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-200 text-emerald-800 ml-2">
                                                <?= round(($a['marks_obtained'] / $a['max_marks']) * 100) ?>%
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex-1 max-w-lg sm:border-l sm:border-emerald-200 sm:pl-4">
                                    <div class="text-xs font-bold text-emerald-800 flex items-center gap-1.5">
                                        <i class="fa-solid fa-comment-dots text-emerald-600"></i> Teacher Feedback:
                                    </div>
                                    <p class="text-xs text-emerald-900 mt-1 italic">
                                        <?= !empty($a['teacher_feedback']) ? '"' . htmlspecialchars($a['teacher_feedback']) . '"' : 'Great effort! Good submission.' ?>
                                    </p>
                                </div>

                                <div class="shrink-0 text-right">
                                    <a href="/<?= htmlspecialchars($a['file_path']) ?>" download class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-emerald-300 text-emerald-800 text-xs font-semibold shadow-2xs hover:bg-emerald-100 transition">
                                        <i class="fa-solid fa-file-check"></i> My Uploaded File
                                    </a>
                                </div>
                            </div>

                        <?php elseif ($is_submitted): ?>
                            <!-- SUBMITTED BUT PENDING EVALUATION -->
                            <div class="bg-blue-50/70 border border-blue-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <div class="text-xs font-bold text-blue-900 flex items-center gap-2">
                                        <i class="fa-solid fa-circle-check text-blue-600"></i>
                                        Submission Received On <?= date('d M Y, h:i A', strtotime($a['submitted_at'])) ?>
                                    </div>
                                    <?php if (!empty($a['student_remarks'])): ?>
                                        <p class="text-xs text-blue-800 mt-1 italic">"<?= htmlspecialchars($a['student_remarks']) ?>"</p>
                                    <?php endif; ?>
                                    <div class="mt-2 flex items-center gap-3">
                                        <a href="/<?= htmlspecialchars($a['file_path']) ?>" download class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-white border border-blue-200 text-blue-700 text-xs font-semibold hover:bg-blue-50 transition">
                                            <i class="fa-solid fa-file-lines"></i> View My Upload
                                        </a>
                                        <button onclick="document.getElementById('upload_box_<?= $a['id'] ?>').classList.toggle('hidden')" class="text-xs text-blue-600 hover:text-blue-800 font-semibold underline">
                                            Re-upload / Update File
                                        </button>
                                    </div>
                                </div>

                                <div class="text-xs text-slate-500 flex items-center gap-2">
                                    <i class="fa-solid fa-hourglass-half text-amber-500"></i>
                                    Awaiting teacher review
                                </div>
                            </div>

                            <!-- Collapsible Re-upload Box -->
                            <div id="upload_box_<?= $a['id'] ?>" class="hidden mt-4 pt-4 border-t border-slate-200">
                                <form method="POST" enctype="multipart/form-data" class="space-y-3">
                                    <input type="hidden" name="action" value="upload_submission">
                                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Select New File to Replace Current Upload *</label>
                                            <input type="file" name="submission_file" required accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg,.zip" class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Updated Remarks (Optional)</label>
                                            <input type="text" name="student_remarks" value="<?= htmlspecialchars($a['student_remarks']) ?>" placeholder="e.g. Uploaded revised solution with question 4 proof" class="w-full px-3 py-1.5 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                        </div>
                                    </div>
                                    <button type="submit" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-2xs">
                                        Submit Revised File
                                    </button>
                                </form>
                            </div>

                        <?php else: ?>
                            <!-- NOT YET SUBMITTED: UPLOAD FORM -->
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 flex items-center gap-1.5">
                                    <i class="fa-solid fa-arrow-up-from-bracket text-indigo-600"></i> Submit Your Solution
                                </h4>

                                <form method="POST" enctype="multipart/form-data" class="space-y-3">
                                    <input type="hidden" name="action" value="upload_submission">
                                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Choose Solution File (PDF, DOCX, Images, ZIP) *</label>
                                            <input type="file" name="submission_file" required accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg,.zip" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Submission Notes / Remarks for Teacher</label>
                                            <input type="text" name="student_remarks" placeholder="e.g. Attached completed derivations for questions 1 to 10" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between pt-1">
                                        <p class="text-2xs text-slate-400">Ensure all pages are legible before submitting. Max size: 15MB.</p>
                                        <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-2xs flex items-center gap-2 transition">
                                            <i class="fa-solid fa-cloud-arrow-up"></i> Upload & Submit Homework
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
