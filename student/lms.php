<?php
/**
 * Student LMS: Learning Management System & Courseware
 * Bellwether International School Nandurbar ERP
 * Interactive courseware portal showing enrolled subjects, allocated faculty, notes, and homework
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('student');

$student_user_id = $_SESSION['user_id'];
$sStmt = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
$sStmt->execute(['uid' => $student_user_id]);
$student = $sStmt->fetch();
$student_id = $student['id'] ?? 0;
$student_class = $student['class'] ?? '';

// Fetch all subjects for this student's class with allocated teacher details
$subjStmt = $pdo->prepare("
    SELECT s.*, 
           t.full_name as teacher_name, 
           t.department as teacher_department, 
           t.designation as teacher_designation,
           t.photo as teacher_photo,
           t.phone as teacher_phone,
           u.email as teacher_email
    FROM subjects s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE s.class = :cls
    ORDER BY s.name ASC
");
$subjStmt->execute(['cls' => $student_class]);
$subjects = $subjStmt->fetchAll();

// If no subjects found for specific section (e.g. "Class 10-A"), try grade fallback (e.g. "Grade 10")
if (empty($subjects) && preg_match('/(\d+)/', $student_class, $m)) {
    $grade_fallback = 'Grade ' . $m[1];
    $subjStmt->execute(['cls' => $grade_fallback]);
    $subjects = $subjStmt->fetchAll();
}

// Fetch counts for notes, assignments, and grades per subject for this student
$notes_counts = [];
$notesStmt = $pdo->prepare("SELECT subject, COUNT(*) as cnt FROM study_notes WHERE class = ? GROUP BY subject");
$notesStmt->execute([$student_class]);
while ($r = $notesStmt->fetch()) {
    $notes_counts[$r['subject']] = (int)$r['cnt'];
}

$asgn_counts = [];
$asgnStmt = $pdo->prepare("SELECT subject, COUNT(*) as cnt FROM assignments WHERE class = ? GROUP BY subject");
$asgnStmt->execute([$student_class]);
while ($r = $asgnStmt->fetch()) {
    $asgn_counts[$r['subject']] = (int)$r['cnt'];
}

$page_title = 'LMS Courseware & Subjects';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 mb-6 no-print">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
                <span>Learning Management System (LMS)</span>
                <span>&bull;</span>
                <span class="text-slate-400">Class: <?= htmlspecialchars($student_class) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                My Courseware &amp; Class Subjects
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Access your subject curriculum, chapter notes, teacher assignments, and lecture resources all in one place.
            </p>
        </div>

        <div class="flex items-center space-x-3">
            <span class="px-3.5 py-1.5 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-xs font-bold flex items-center space-x-1.5">
                <i class="fa-solid fa-graduation-cap text-blue-600"></i>
                <span>Enrolled in <?= htmlspecialchars($student_class) ?></span>
            </span>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8 no-print">
        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm flex items-center space-x-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-book-open"></i>
            </div>
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Total Subjects</span>
                <span class="text-2xl font-black text-slate-900 font-['Outfit']"><?= count($subjects) ?></span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm flex items-center space-x-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-file-pdf"></i>
            </div>
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Study Notes Available</span>
                <span class="text-2xl font-black text-slate-900 font-['Outfit']"><?= array_sum($notes_counts) ?></span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm flex items-center space-x-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl font-bold">
                <i class="fa-solid fa-book-bookmark"></i>
            </div>
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Active Assignments</span>
                <span class="text-2xl font-black text-slate-900 font-['Outfit']"><?= array_sum($asgn_counts) ?></span>
            </div>
        </div>
    </div>

    <!-- Subjects Grid -->
    <?php if (empty($subjects)): ?>
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-12 text-center">
            <div class="w-16 h-16 rounded-3xl bg-slate-100 text-slate-400 flex items-center justify-center text-2xl mx-auto mb-3">
                <i class="fa-solid fa-folder-open"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">No subjects configured for <?= htmlspecialchars($student_class) ?></h3>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">The Principal or Admin will allocate the curriculum subjects and faculty members shortly.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($subjects as $sub): ?>
                <?php
                    $note_cnt = $notes_counts[$sub['name']] ?? 0;
                    $asgn_cnt = $asgn_counts[$sub['name']] ?? 0;
                    $has_teacher = !empty($sub['teacher_name']);
                    $teacher_photo = $sub['teacher_photo'] ?? null;
                    $has_photo = !empty($teacher_photo) && file_exists(__DIR__ . '/../' . ltrim($teacher_photo, '/'));
                ?>
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between hover:shadow-lg transition-all group">
                    <div class="p-6">
                        <!-- Top Header with Code Badge & Max Marks -->
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <span class="px-3 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200 font-mono text-[11px] font-bold">
                                <?= htmlspecialchars($sub['code'] ?: 'SUB') ?>
                            </span>
                            <span class="text-xs text-slate-400 font-medium">
                                Max Marks: <strong class="text-slate-700"><?= (int)$sub['max_marks'] ?></strong>
                            </span>
                        </div>

                        <!-- Subject Title -->
                        <h2 class="text-xl font-black text-slate-900 font-['Outfit'] group-hover:text-blue-700 transition leading-snug">
                            <?= htmlspecialchars($sub['name']) ?>
                        </h2>

                        <!-- Allocated Teacher Card -->
                        <div class="mt-4 p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center space-x-3">
                            <?php if ($has_photo): ?>
                                <img src="<?= htmlspecialchars($teacher_photo) ?>" alt="Teacher" class="w-10 h-10 rounded-xl object-cover border border-amber-300 shadow-2xs shrink-0">
                            <?php elseif ($has_teacher): ?>
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-navy-900 to-blue-700 text-amber-400 font-bold flex items-center justify-center text-sm shadow-2xs shrink-0">
                                    <?= strtoupper(substr($sub['teacher_name'], 0, 1)) ?>
                                </div>
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-xl bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-sm shrink-0">
                                    <i class="fa-solid fa-user-xmark"></i>
                                </div>
                            <?php endif; ?>

                            <div class="min-w-0 flex-1">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 block">Allocated Faculty</span>
                                <?php if ($has_teacher): ?>
                                    <h3 class="text-xs font-bold text-slate-900 truncate"><?= htmlspecialchars($sub['teacher_name']) ?></h3>
                                    <p class="text-[10px] text-blue-700 truncate"><?= htmlspecialchars($sub['teacher_department']) ?> Department</p>
                                <?php else: ?>
                                    <p class="text-xs font-semibold text-amber-600 italic">Faculty to be allocated</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Syllabus preview snippet -->
                        <?php if (!empty($sub['syllabus_info'])): ?>
                            <p class="text-xs text-slate-500 mt-3 line-clamp-2 italic">
                                &ldquo;<?= htmlspecialchars($sub['syllabus_info']) ?>&rdquo;
                            </p>
                        <?php endif; ?>

                        <!-- Quick Badges: Notes & Assignments -->
                        <div class="grid grid-cols-2 gap-2 mt-4 pt-3 border-t border-slate-100 text-center">
                            <div class="p-2 rounded-xl bg-rose-50 border border-rose-100">
                                <span class="text-[10px] font-bold text-rose-600 uppercase tracking-wider block">Notes</span>
                                <span class="text-sm font-extrabold text-rose-800"><?= $note_cnt ?> Files</span>
                            </div>
                            <div class="p-2 rounded-xl bg-emerald-50 border border-emerald-100">
                                <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block">Homework</span>
                                <span class="text-sm font-extrabold text-emerald-800"><?= $asgn_cnt ?> Tasks</span>
                            </div>
                        </div>
                    </div>

                    <!-- Open Subject Courseware Action Button -->
                    <div class="p-4 bg-slate-50 border-t border-slate-100">
                        <a href="/student/subject.php?id=<?= $sub['id'] ?>" 
                           class="w-full py-2.5 px-4 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md transition flex items-center justify-center space-x-2 group-hover:bg-blue-600">
                            <span>Open Subject LMS</span>
                            <i class="fa-solid fa-arrow-right text-amber-400 text-xs transition-transform group-hover:translate-x-1"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
