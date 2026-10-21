<?php
/**
 * Teacher Dashboard - Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('teacher');

$teacher_user_id = $_SESSION['user_id'];
$tStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = :uid LIMIT 1");
$tStmt->execute(['uid' => $teacher_user_id]);
$teacher_profile = $tStmt->fetch();

// Total approved students count
$total_students = $pdo->query("SELECT COUNT(*) FROM students s JOIN users u ON s.user_id = u.id WHERE u.status = 'approved'")->fetchColumn();

// Total marks entered by this teacher
$mStmt = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE teacher_id = :tid");
$mStmt->execute(['tid' => $teacher_user_id]);
$total_marks_entered = $mStmt->fetchColumn();

// Recent notices for teachers
$notices = $pdo->query("SELECT * FROM notices WHERE audience IN ('all', 'teachers') ORDER BY id DESC LIMIT 3")->fetchAll();

// Recent students
$recent_students = $pdo->query("
    SELECT s.*, u.email 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    WHERE u.status = 'approved' 
    ORDER BY s.id DESC LIMIT 4
")->fetchAll();

// Assignments stats for this teacher
$asgn_stats = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT a.id) as total_assignments,
        COUNT(s.id) as total_submissions,
        SUM(CASE WHEN s.status != 'Graded' THEN 1 ELSE 0 END) as pending_grades
    FROM assignments a
    LEFT JOIN assignment_submissions s ON s.assignment_id = a.id
    WHERE a.teacher_id = ?
");
$asgn_stats->execute([$teacher_user_id]);
$asgn_data = $asgn_stats->fetch();
$total_assignments = intval($asgn_data['total_assignments'] ?? 0);
$total_submissions = intval($asgn_data['total_submissions'] ?? 0);
$pending_grades = intval($asgn_data['pending_grades'] ?? 0);

// Today's lectures
$today_day = date('l');
$tname_param = '%' . ($teacher_profile['full_name'] ?? '') . '%';
$dept_param = '%' . ($teacher_profile['department'] ?? '') . '%';
$ttStmt = $pdo->prepare("SELECT COUNT(*) FROM timetables WHERE (teacher_name LIKE ? OR subject LIKE ?) AND day_of_week = ?");
$ttStmt->execute([$tname_param, $dept_param, $today_day]);
$today_lectures = intval($ttStmt->fetchColumn());

// Class Teacher Morning Attendance Check
$assigned_class = $teacher_profile['assigned_class'] ?? null;
$today_att_marked = false;
$today_present_count = 0;
$today_absent_count = 0;

if (!empty($assigned_class)) {
    $today_date = date('Y-m-d');
    $attCheck = $pdo->prepare("
        SELECT 
            COUNT(*) as total_marked,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE s.class = ? AND a.date = ?
    ");
    $attCheck->execute([$assigned_class, $today_date]);
    $attRow = $attCheck->fetch();
    if ($attRow && $attRow['total_marked'] > 0) {
        $today_att_marked = true;
        $today_present_count = (int)$attRow['present_count'];
        $today_absent_count = (int)$attRow['absent_count'];
    }
}

$flash = get_flash();
?>

<?php 
$page_title = 'Faculty Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php'; 
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Header Greeting -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
                <span>Faculty &amp; Academic Portal</span>
                <span>&bull;</span>
                <span class="text-slate-400"><?= date('l, d F Y') ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Welcome, <?= htmlspecialchars($teacher_profile['full_name'] ?? 'Teacher') ?>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Department of <strong><?= htmlspecialchars($teacher_profile['department'] ?? 'Academics') ?></strong> &bull; Teacher Code: <span class="font-mono text-blue-600 font-bold"><?= htmlspecialchars($teacher_profile['teacher_code'] ?? '') ?></span>
            </p>
        </div>

        <div class="flex items-center space-x-3">
            <a href="/teacher/attendance.php" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-semibold text-xs shadow-md shadow-navy-900/10 transition">
                <i class="fa-solid fa-clipboard-user mr-2 text-amber-400"></i> Mark Attendance
            </a>
            <a href="/teacher/marks.php" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-white hover:bg-slate-100 text-slate-700 font-semibold text-xs border border-slate-300 shadow-sm transition">
                <i class="fa-solid fa-file-pen mr-2 text-blue-600"></i> Enter Marks
            </a>
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

    <!-- Class Teacher Morning Roll Call Banner -->
    <?php if (!empty($assigned_class)): ?>
        <div class="mb-8 p-5 rounded-3xl <?= $today_att_marked ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-gradient-to-r from-amber-50 to-orange-50 border-amber-300 text-amber-950 shadow-sm' ?> border flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center space-x-3.5">
                <div class="w-12 h-12 rounded-2xl <?= $today_att_marked ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white' ?> flex items-center justify-center font-bold text-xl shadow-md">
                    <i class="fa-solid fa-chalkboard-user"></i>
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full <?= $today_att_marked ? 'bg-emerald-200 text-emerald-800' : 'bg-amber-200 text-amber-900 animate-pulse' ?>">
                            <?= $today_att_marked ? 'Attendance Done' : 'Action Required Today' ?>
                        </span>
                        <span class="text-xs font-bold <?= $today_att_marked ? 'text-emerald-700' : 'text-amber-700' ?>">Class Teacher: <?= htmlspecialchars($assigned_class) ?></span>
                    </div>
                    <h3 class="text-base font-extrabold mt-0.5 <?= $today_att_marked ? 'text-emerald-950' : 'text-amber-950' ?>">
                        <?= $today_att_marked ? "Today's Attendance Recorded for " . htmlspecialchars($assigned_class) : "Morning Roll Call for " . htmlspecialchars($assigned_class) . " is Pending" ?>
                    </h3>
                    <p class="text-xs <?= $today_att_marked ? 'text-emerald-700' : 'text-amber-800/90' ?>">
                        <?= $today_att_marked ? "Summary: {$today_present_count} Present &bull; {$today_absent_count} Absent on " . date('d M Y') : "As the Class Teacher, please take morning attendance at the start of the school day." ?>
                    </p>
                </div>
            </div>
            <div>
                <a href="/teacher/attendance.php?class=<?= urlencode($assigned_class) ?>" class="inline-flex items-center px-4 py-2.5 rounded-xl <?= $today_att_marked ? 'bg-white hover:bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-navy-800 hover:bg-navy-900 text-white shadow-md' ?> text-xs font-bold transition">
                    <i class="fa-solid fa-clipboard-user mr-2 text-amber-400"></i>
                    <?= $today_att_marked ? 'Review / Edit Register' : 'Take Morning Attendance Now' ?> &rarr;
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
        
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Enrolled Students</span>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="fa-solid fa-user-graduate text-lg"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 font-['Outfit'] mt-3"><?= $total_students ?></p>
            <div class="mt-2 text-xs text-blue-600 font-medium">
                <a href="/teacher/students.php" class="hover:underline flex items-center">
                    <span>View class directory &rarr;</span>
                </a>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Marks Records Submitted</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="fa-solid fa-file-circle-check text-lg"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 font-['Outfit'] mt-3"><?= $total_marks_entered ?></p>
            <div class="mt-2 text-xs text-emerald-600 font-medium">
                <a href="/teacher/marks.php" class="hover:underline flex items-center">
                    <span>Gradebook assessment &rarr;</span>
                </a>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Staff Circulars</span>
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <i class="fa-solid fa-bullhorn text-lg"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 font-['Outfit'] mt-3"><?= count($notices) ?></p>
            <div class="mt-2 text-xs text-purple-600 font-medium">
                <a href="/teacher/notices.php" class="hover:underline flex items-center">
                    <span>Read notices &rarr;</span>
                </a>
            </div>
        </div>

    </div>

    <!-- Quick Operations & Recent Students -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Quick Attendance & Marks Launcher -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Academic Actions</h3>
                <span class="text-xs text-slate-400">Classroom Suite</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <a href="/teacher/attendance.php" class="p-4 rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-50/40 border border-blue-200/60 hover:border-blue-400 transition group flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center mb-3 shadow-md shadow-blue-500/20 group-hover:scale-105 transition">
                            <i class="fa-solid fa-clipboard-user text-base"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-900">Take Daily Attendance</h4>
                        <p class="text-xs text-slate-500 mt-1">Record Present, Absent, or Late status for your assigned class.</p>
                    </div>
                    <span class="text-xs font-bold text-blue-700 mt-4 flex items-center">Open Register &rarr;</span>
                </a>

                <a href="/teacher/marks.php" class="p-4 rounded-2xl bg-gradient-to-br from-emerald-50 to-teal-50/40 border border-emerald-200/60 hover:border-emerald-400 transition group flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center mb-3 shadow-md shadow-emerald-500/20 group-hover:scale-105 transition">
                            <i class="fa-solid fa-award text-base"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-900">Enter Exam Marks</h4>
                        <p class="text-xs text-slate-500 mt-1">Submit test scores and subject marks with auto-calculated grades.</p>
                    </div>
                    <span class="text-xs font-bold text-emerald-700 mt-4 flex items-center">Open Gradebook &rarr;</span>
                </a>

                <a href="/teacher/assignments.php" class="p-4 rounded-2xl bg-gradient-to-br from-purple-50 to-pink-50/40 border border-purple-200/60 hover:border-purple-400 transition group flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-purple-600 text-white flex items-center justify-center mb-3 shadow-md shadow-purple-500/20 group-hover:scale-105 transition">
                            <i class="fa-solid fa-book-bookmark text-base"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-900">Homework &amp; Assignments</h4>
                        <p class="text-xs text-slate-500 mt-1"><?= $total_assignments ?> posted &bull; <?= $pending_grades ?> submissions pending review.</p>
                    </div>
                    <span class="text-xs font-bold text-purple-700 mt-4 flex items-center">Review Submissions &rarr;</span>
                </a>

                <a href="/teacher/timetable.php" class="p-4 rounded-2xl bg-gradient-to-br from-amber-50 to-orange-50/40 border border-amber-200/60 hover:border-amber-400 transition group flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-amber-600 text-white flex items-center justify-center mb-3 shadow-md shadow-amber-500/20 group-hover:scale-105 transition">
                            <i class="fa-solid fa-calendar-week text-base"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-900">Teaching Timetable</h4>
                        <p class="text-xs text-slate-500 mt-1">Check weekly schedule &bull; <?= $today_lectures ?> lecture(s) today (<?= $today_day ?>).</p>
                    </div>
                    <span class="text-xs font-bold text-amber-700 mt-4 flex items-center">View Schedule &rarr;</span>
                </a>
            </div>

            <!-- Teacher Info Box -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 text-xs text-slate-600 space-y-1 mt-4">
                <p class="font-bold text-slate-800">Faculty Credentials &amp; Verification</p>
                <p>Teacher ID: <strong><?= htmlspecialchars($teacher_profile['teacher_code'] ?? '') ?></strong> &bull; Joining Date: <?= htmlspecialchars($teacher_profile['joining_date'] ?? 'N/A') ?></p>
                <p>Phone: <?= htmlspecialchars($teacher_profile['phone'] ?? 'N/A') ?> &bull; Qualification: <?= htmlspecialchars($teacher_profile['qualification'] ?? 'N/A') ?></p>
            </div>
        </div>

        <!-- Student Roster Quick View -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                    <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Active Students</h3>
                    <a href="/teacher/students.php" class="text-xs font-bold text-blue-600 hover:text-blue-800">View Roster &rarr;</a>
                </div>

                <div class="space-y-3">
                    <?php foreach ($recent_students as $s): ?>
                        <div class="flex items-center justify-between p-3 rounded-2xl bg-slate-50 border border-slate-100">
                            <div class="flex items-center space-x-3">
                                <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-700 font-bold text-xs flex items-center justify-center">
                                    <?= strtoupper(substr($s['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-900"><?= htmlspecialchars($s['full_name']) ?></p>
                                    <p class="text-[11px] text-slate-500"><?= htmlspecialchars($s['class']) ?> &bull; Roll: <?= htmlspecialchars($s['roll_no'] ?: 'N/A') ?></p>
                                </div>
                            </div>
                            <span class="font-mono text-xs font-bold text-slate-500"><?= htmlspecialchars($s['student_code']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pt-4 mt-4 border-t border-slate-100">
                <a href="/teacher/students.php" class="w-full py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold text-xs flex items-center justify-center transition">
                    View Complete Class Directory
                </a>
            </div>
        </div>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
