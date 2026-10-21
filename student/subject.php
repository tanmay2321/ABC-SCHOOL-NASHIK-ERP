<?php
/**
 * Student Subject Deep-Dive Courseware View
 * Bellwether International School Nandurbar ERP
 * Interactive LMS portal for a specific subject: Notes, Assignments, Allocated Faculty, Syllabus & Performance
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('student');

$student_user_id = $_SESSION['user_id'];
$sStmt = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
$sStmt->execute(['uid' => $student_user_id]);
$student = $sStmt->fetch();
$student_id = $student['id'] ?? 0;
$student_class = $student['class'] ?? '';

$subject_id = (int)($_GET['id'] ?? 0);
if ($subject_id <= 0) {
    header("Location: /student/lms.php");
    exit();
}

// Fetch Subject details with Allocated Faculty
$subjStmt = $pdo->prepare("
    SELECT s.*, 
           t.id as teacher_id,
           t.full_name as teacher_name, 
           t.department as teacher_department, 
           t.designation as teacher_designation,
           t.qualification as teacher_qualification,
           t.phone as teacher_phone,
           t.photo as teacher_photo,
           u.email as teacher_email
    FROM subjects s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE s.id = ?
");
$subjStmt->execute([$subject_id]);
$subject = $subjStmt->fetch();

if (!$subject) {
    die("Subject record not found.");
}

$active_tab = $_GET['tab'] ?? 'notes'; // 'notes', 'assignments', 'syllabus', 'grades'

// 1. Fetch Study Notes for this subject & class
$nStmt = $pdo->prepare("
    SELECT n.*, t.full_name as teacher_name
    FROM study_notes n
    LEFT JOIN teachers t ON n.teacher_id = t.id
    WHERE (n.class = ? OR n.class = ?) AND n.subject = ?
    ORDER BY n.id DESC
");
$nStmt->execute([$subject['class'], $student_class, $subject['name']]);
$notes = $nStmt->fetchAll();

// 2. Fetch Assignments for this subject & class with student submission status
$asgnStmt = $pdo->prepare("
    SELECT a.*, 
           sub.id as submission_id,
           sub.file_path as submitted_file,
           sub.submitted_at,
           sub.marks_obtained,
           sub.status as submission_status,
           sub.teacher_feedback
    FROM assignments a
    LEFT JOIN assignment_submissions sub ON a.id = sub.assignment_id AND sub.student_id = ?
    WHERE (a.class = ? OR a.class = ?) AND a.subject = ?
    ORDER BY a.due_date DESC
");
$asgnStmt->execute([$student_id, $subject['class'], $student_class, $subject['name']]);
$assignments = $asgnStmt->fetchAll();

// 3. Fetch Grades in this subject
$gStmt = $pdo->prepare("
    SELECT g.*, t.full_name as teacher_name
    FROM grades g
    LEFT JOIN users u ON g.teacher_id = u.id
    LEFT JOIN teachers t ON u.id = t.user_id
    WHERE g.student_id = ? AND g.subject = ?
    ORDER BY g.id DESC
");
$gStmt->execute([$student_id, $subject['name']]);
$grades = $gStmt->fetchAll();

$page_title = $subject['name'] . ' - LMS Courseware';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Back to LMS link & Breadcrumb -->
    <div class="mb-4 no-print">
        <a href="/student/lms.php" class="inline-flex items-center text-xs font-bold text-blue-600 hover:text-blue-800 transition">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to All Subjects Courseware
        </a>
    </div>

    <!-- Subject Hero Header -->
    <div class="bg-gradient-to-r from-navy-950 via-navy-900 to-indigo-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl mb-8 relative overflow-hidden">
        <div class="absolute -right-8 -bottom-8 w-44 h-44 rounded-full bg-white/5 pointer-events-none"></div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
            <div>
                <div class="flex items-center space-x-2 text-xs font-bold tracking-wider uppercase text-amber-400 mb-2">
                    <span class="px-2.5 py-0.5 rounded-full bg-amber-400/20 border border-amber-400/30">
                        Code: <?= htmlspecialchars($subject['code'] ?: 'SUB') ?>
                    </span>
                    <span>&bull;</span>
                    <span class="text-slate-300"><?= htmlspecialchars($subject['class']) ?></span>
                    <span>&bull;</span>
                    <span class="text-slate-300">AY <?= htmlspecialchars($subject['academic_year']) ?></span>
                </div>

                <h1 class="text-2xl sm:text-4xl font-black font-['Outfit'] tracking-tight">
                    <?= htmlspecialchars($subject['name']) ?>
                </h1>

                <p class="text-xs sm:text-sm text-slate-300 mt-2 max-w-2xl">
                    <?= !empty($subject['syllabus_info']) ? htmlspecialchars($subject['syllabus_info']) : 'CBSE Prescribed Curriculum & Learning Resources.' ?>
                </p>
            </div>

            <!-- Allocated Teacher Quick Summary Badge -->
            <div class="bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/10 min-w-[240px] shrink-0">
                <span class="text-[10px] uppercase font-bold tracking-widest text-amber-300 block mb-2">Allocated Faculty</span>
                <div class="flex items-center space-x-3">
                    <?php if (!empty($subject['teacher_photo']) && file_exists(__DIR__ . '/../' . ltrim($subject['teacher_photo'], '/'))): ?>
                        <img src="<?= htmlspecialchars($subject['teacher_photo']) ?>" alt="Faculty" class="w-12 h-12 rounded-2xl object-cover border-2 border-amber-400 shadow-sm shrink-0">
                    <?php elseif (!empty($subject['teacher_name'])): ?>
                        <div class="w-12 h-12 rounded-2xl bg-amber-400 text-navy-950 font-black text-lg flex items-center justify-center shrink-0">
                            <?= strtoupper(substr($subject['teacher_name'], 0, 1)) ?>
                        </div>
                    <?php else: ?>
                        <div class="w-12 h-12 rounded-2xl bg-white/20 text-white font-bold text-lg flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                    <?php endif; ?>

                    <div class="min-w-0">
                        <strong class="text-sm font-bold text-white block truncate"><?= htmlspecialchars($subject['teacher_name'] ?: 'Faculty to be assigned') ?></strong>
                        <span class="text-[11px] text-slate-300 block truncate"><?= htmlspecialchars($subject['teacher_designation'] ?: ($subject['teacher_department'] ? $subject['teacher_department'] . ' Dept' : 'Bellwether Faculty')) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex items-center space-x-2 border-b border-slate-200 mb-8 overflow-x-auto no-print">
        <a href="/student/subject.php?id=<?= $subject['id'] ?>&tab=notes" 
           class="px-5 py-3 border-b-2 font-bold text-xs sm:text-sm transition flex items-center space-x-2 shrink-0 <?= $active_tab === 'notes' ? 'border-navy-800 text-navy-800 bg-white rounded-t-2xl shadow-xs' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-file-pdf text-rose-500"></i>
            <span>Study Notes &amp; Files (<?= count($notes) ?>)</span>
        </a>

        <a href="/student/subject.php?id=<?= $subject['id'] ?>&tab=assignments" 
           class="px-5 py-3 border-b-2 font-bold text-xs sm:text-sm transition flex items-center space-x-2 shrink-0 <?= $active_tab === 'assignments' ? 'border-navy-800 text-navy-800 bg-white rounded-t-2xl shadow-xs' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-book-bookmark text-emerald-500"></i>
            <span>Homework &amp; Assignments (<?= count($assignments) ?>)</span>
        </a>

        <a href="/student/subject.php?id=<?= $subject['id'] ?>&tab=syllabus" 
           class="px-5 py-3 border-b-2 font-bold text-xs sm:text-sm transition flex items-center space-x-2 shrink-0 <?= $active_tab === 'syllabus' ? 'border-navy-800 text-navy-800 bg-white rounded-t-2xl shadow-xs' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-list-check text-blue-500"></i>
            <span>Curriculum &amp; Syllabus</span>
        </a>

        <a href="/student/subject.php?id=<?= $subject['id'] ?>&tab=grades" 
           class="px-5 py-3 border-b-2 font-bold text-xs sm:text-sm transition flex items-center space-x-2 shrink-0 <?= $active_tab === 'grades' ? 'border-navy-800 text-navy-800 bg-white rounded-t-2xl shadow-xs' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-chart-simple text-purple-500"></i>
            <span>My Marks &amp; Assessment (<?= count($grades) ?>)</span>
        </a>
    </div>

    <!-- TAB 1: STUDY NOTES & MATERIALS -->
    <?php if ($active_tab === 'notes'): ?>
        <?php if (empty($notes)): ?>
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-12 text-center">
                <div class="w-16 h-16 rounded-3xl bg-rose-50 text-rose-500 flex items-center justify-center text-2xl mx-auto mb-3">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <h3 class="text-base font-bold text-slate-800">No notes uploaded for <?= htmlspecialchars($subject['name']) ?> yet</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Your subject teacher will upload lecture slides, chapter summaries, and formula sheets soon.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($notes as $note): ?>
                    <?php
                        $ext = strtolower($note['file_type']);
                        $icon = 'fa-file-pdf text-rose-600';
                        $size_formatted = ($note['file_size'] > 1048576) ? round($note['file_size'] / 1048576, 2) . ' MB' : round($note['file_size'] / 1024, 1) . ' KB';
                    ?>
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                                <span class="px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-700 text-[10px] font-bold uppercase font-mono">
                                    <?= strtoupper($note['file_type']) ?> &bull; <?= $size_formatted ?>
                                </span>
                                <span class="text-[11px] text-slate-400">
                                    <?= date('d M Y', strtotime($note['created_at'])) ?>
                                </span>
                            </div>

                            <h3 class="text-base font-bold text-slate-900 font-['Outfit'] leading-snug">
                                <?= htmlspecialchars($note['title']) ?>
                            </h3>

                            <?php if (!empty($note['description'])): ?>
                                <p class="text-xs text-slate-600 mt-2">
                                    <?= htmlspecialchars($note['description']) ?>
                                </p>
                            <?php endif; ?>

                            <div class="mt-4 pt-3 border-t border-slate-100 text-[11px] text-slate-400">
                                Uploaded by: <strong class="text-slate-700"><?= htmlspecialchars($note['teacher_name'] ?: 'Subject Faculty') ?></strong>
                            </div>
                        </div>

                        <div class="mt-5">
                            <a href="<?= htmlspecialchars($note['file_path']) ?>" download="<?= htmlspecialchars($note['file_name']) ?>" target="_blank"
                               class="w-full py-2.5 px-4 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md transition flex items-center justify-center space-x-2">
                                <i class="fa-solid fa-download text-amber-400"></i>
                                <span>Download Document</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <!-- TAB 2: HOMEWORK & ASSIGNMENTS -->
    <?php elseif ($active_tab === 'assignments'): ?>
        <?php if (empty($assignments)): ?>
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-12 text-center">
                <div class="w-16 h-16 rounded-3xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl mx-auto mb-3">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <h3 class="text-base font-bold text-slate-800">No homework or assignments currently due</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">You're all caught up for <?= htmlspecialchars($subject['name']) ?>! Check back when your teacher assigns new work.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($assignments as $asgn): ?>
                    <?php
                        $is_submitted = !empty($asgn['submission_id']);
                        $is_graded = ($asgn['submission_status'] === 'Graded');
                        $is_overdue = (strtotime($asgn['due_date']) < time() && !$is_submitted);
                    ?>
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                            <div>
                                <div class="flex items-center space-x-2.5 mb-1.5">
                                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">
                                        Max: <?= (int)$asgn['max_marks'] ?> Marks
                                    </span>
                                    <span class="text-xs text-slate-400">
                                        Due Date: <strong class="<?= $is_overdue ? 'text-rose-600 font-bold' : 'text-slate-700' ?>"><?= date('l, d F Y', strtotime($asgn['due_date'])) ?></strong>
                                    </span>
                                </div>
                                <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">
                                    <?= htmlspecialchars($asgn['title']) ?>
                                </h3>
                            </div>

                            <div>
                                <?php if ($is_graded): ?>
                                    <span class="px-3.5 py-1.5 rounded-xl bg-purple-100 text-purple-800 border border-purple-200 text-xs font-bold flex items-center space-x-1.5">
                                        <i class="fa-solid fa-award text-purple-600"></i>
                                        <span>Graded: <?= $asgn['marks_obtained'] ?> / <?= $asgn['max_marks'] ?></span>
                                    </span>
                                <?php elseif ($is_submitted): ?>
                                    <span class="px-3.5 py-1.5 rounded-xl bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold flex items-center space-x-1.5">
                                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                        <span>Submitted</span>
                                    </span>
                                <?php elseif ($is_overdue): ?>
                                    <span class="px-3.5 py-1.5 rounded-xl bg-rose-100 text-rose-800 border border-rose-200 text-xs font-bold flex items-center space-x-1.5">
                                        <i class="fa-solid fa-clock-rotate-left text-rose-600"></i>
                                        <span>Overdue</span>
                                    </span>
                                <?php else: ?>
                                    <span class="px-3.5 py-1.5 rounded-xl bg-amber-100 text-amber-800 border border-amber-200 text-xs font-bold flex items-center space-x-1.5">
                                        <i class="fa-solid fa-hourglass-half text-amber-600"></i>
                                        <span>Pending Submission</span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="py-4">
                            <p class="text-xs text-slate-700 whitespace-pre-line leading-relaxed">
                                <?= htmlspecialchars($asgn['description']) ?>
                            </p>

                            <?php if (!empty($asgn['attachment_path'])): ?>
                                <div class="mt-3">
                                    <a href="<?= htmlspecialchars($asgn['attachment_path']) ?>" download target="_blank" 
                                       class="inline-flex items-center text-xs font-bold text-blue-600 hover:text-blue-800">
                                        <i class="fa-solid fa-paperclip mr-1.5"></i> Download Teacher Assignment Attachment
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($asgn['teacher_feedback'])): ?>
                                <div class="mt-3 p-3 rounded-2xl bg-purple-50 border border-purple-200 text-xs text-purple-900">
                                    <strong class="block font-bold mb-0.5"><i class="fa-solid fa-comment-dots mr-1"></i> Teacher Feedback:</strong>
                                    <?= htmlspecialchars($asgn['teacher_feedback']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-[11px] text-slate-400">
                                Posted on <?= date('d M Y', strtotime($asgn['created_at'])) ?>
                            </span>

                            <a href="/student/assignments.php" class="px-4 py-2 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-xs transition inline-flex items-center space-x-1.5">
                                <span><?= $is_submitted ? 'View Submission' : 'Submit Homework' ?></span>
                                <i class="fa-solid fa-arrow-right text-amber-400 text-xs"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <!-- TAB 3: CURRICULUM & SYLLABUS -->
    <?php elseif ($active_tab === 'syllabus'): ?>
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <div class="flex items-center space-x-3 pb-4 border-b border-slate-100 mb-6">
                <div class="w-10 h-10 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-book-bookmark text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">Prescribed Syllabus &amp; Learning Units</h3>
                    <p class="text-xs text-slate-500">Official course outline for <?= htmlspecialchars($subject['name']) ?> (AY <?= htmlspecialchars($subject['academic_year']) ?>)</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block">Standard / Class</span>
                    <strong class="text-sm text-slate-900 mt-0.5 block"><?= htmlspecialchars($subject['class']) ?></strong>
                </div>
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block">Maximum Marks</span>
                    <strong class="text-sm text-blue-700 mt-0.5 block"><?= (int)$subject['max_marks'] ?> Marks</strong>
                </div>
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block">Passing Threshold</span>
                    <strong class="text-sm text-emerald-700 mt-0.5 block"><?= (int)$subject['pass_marks'] ?> Marks (35%)</strong>
                </div>
            </div>

            <div>
                <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Chapters, Units &amp; Learning Topics</h4>
                <div class="p-6 rounded-2xl bg-slate-50/70 border border-slate-200 text-xs sm:text-sm text-slate-800 whitespace-pre-line leading-relaxed font-sans">
                    <?= !empty($subject['syllabus_info']) ? htmlspecialchars($subject['syllabus_info']) : 'Syllabus content is being compiled by the faculty member.' ?>
                </div>
            </div>
        </div>

    <!-- TAB 4: MY PERFORMANCE & MARKS -->
    <?php elseif ($active_tab === 'grades'): ?>
        <?php if (empty($grades)): ?>
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-12 text-center">
                <div class="w-16 h-16 rounded-3xl bg-purple-50 text-purple-600 flex items-center justify-center text-2xl mx-auto mb-3">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <h3 class="text-base font-bold text-slate-800">No exam marks entered for <?= htmlspecialchars($subject['name']) ?> yet</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Scores will appear here as soon as teachers evaluate your unit tests and term exams.</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Assessment Breakdown</h3>
                        <p class="text-xs text-slate-500">Track your performance over academic examinations</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-[10px] font-bold text-slate-600 uppercase tracking-wider border-b border-slate-200">
                                <th class="py-3 px-4">Examination</th>
                                <th class="py-3 px-4 text-center">Marks Obtained</th>
                                <th class="py-3 px-4 text-center">Max Marks</th>
                                <th class="py-3 px-4 text-center">Percentage</th>
                                <th class="py-3 px-4 text-center">Grade</th>
                                <th class="py-3 px-4">Teacher Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($grades as $g): ?>
                                <?php
                                    $pct = ($g['max_marks'] > 0) ? round(($g['marks_obtained'] / $g['max_marks']) * 100, 1) : 0;
                                    $badge = 'bg-slate-100 text-slate-800';
                                    if ($g['grade'] === 'A+' || $g['grade'] === 'A') $badge = 'bg-emerald-100 text-emerald-800 border border-emerald-300';
                                    elseif ($g['grade'] === 'B+' || $g['grade'] === 'B') $badge = 'bg-blue-100 text-blue-800 border border-blue-300';
                                    elseif ($g['grade'] === 'C' || $g['grade'] === 'D') $badge = 'bg-amber-100 text-amber-800 border border-amber-300';
                                    elseif ($g['grade'] === 'F') $badge = 'bg-rose-100 text-rose-800 border border-rose-300';
                                ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-3 px-4 font-bold text-slate-900"><?= htmlspecialchars($g['exam_name']) ?></td>
                                    <td class="py-3 px-4 text-center font-extrabold text-blue-700 text-sm"><?= $g['marks_obtained'] ?></td>
                                    <td class="py-3 px-4 text-center text-slate-500"><?= (int)$g['max_marks'] ?></td>
                                    <td class="py-3 px-4 text-center font-bold text-slate-800"><?= $pct ?>%</td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full font-black text-xs <?= $badge ?>">
                                            <?= htmlspecialchars($g['grade']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-slate-600 italic"><?= htmlspecialchars($g['remarks'] ?: 'Good performance') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
