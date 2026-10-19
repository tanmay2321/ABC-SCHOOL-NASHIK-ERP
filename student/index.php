<?php
/**
 * Student Dashboard - ABC School Nashik ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('student');

$student_user_id = $_SESSION['user_id'];

// Fetch student profile
$sStmt = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
$sStmt->execute(['uid' => $student_user_id]);
$student = $sStmt->fetch();
$student_id = $student['id'] ?? 0;

// Calculate Attendance Stats
$attStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days
    FROM attendance
    WHERE student_id = :sid
");
$attStmt->execute(['sid' => $student_id]);
$att_stats = $attStmt->fetch();

$total_days = (int)($att_stats['total_days'] ?? 0);
$present_days = (int)($att_stats['present_days'] ?? 0);
$att_percent = ($total_days > 0) ? round(($present_days / $total_days) * 100, 1) : 100;

// Fetch Grades / Marks
$gStmt = $pdo->prepare("
    SELECT * FROM grades 
    WHERE student_id = :sid 
    ORDER BY id DESC LIMIT 5
");
$gStmt->execute(['sid' => $student_id]);
$grades = $gStmt->fetchAll();

// Calculate Average Marks
$avgStmt = $pdo->prepare("SELECT AVG(marks_obtained) as avg_marks FROM grades WHERE student_id = :sid");
$avgStmt->execute(['sid' => $student_id]);
$avg_marks = round((float)$avgStmt->fetchColumn(), 1);

// Recent Notices
$notices = $pdo->query("SELECT * FROM notices WHERE audience IN ('all', 'students') ORDER BY id DESC LIMIT 3")->fetchAll();

// Student Fee Pending Calculation
$total_invoiced_stu = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM fee_invoices WHERE student_id = ?");
$total_invoiced_stu->execute([$student_id]);
$stu_inv = floatval($total_invoiced_stu->fetchColumn());

$total_paid_stu = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_payments WHERE student_id = ?");
$total_paid_stu->execute([$student_id]);
$stu_paid = floatval($total_paid_stu->fetchColumn());
$stu_pending_fee = max(0, $stu_inv - $stu_paid);

// Homework / Assignments Pending Count
$asgn_due = $pdo->prepare("SELECT COUNT(*) FROM assignments a WHERE a.class = ? AND a.id NOT IN (SELECT assignment_id FROM assignment_submissions WHERE student_id = ?)");
$asgn_due->execute([$student['class'] ?? '', $student_id]);
$pending_homework = intval($asgn_due->fetchColumn());

$flash = get_flash();
?>

<?php 
$page_title = 'Student Portal';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php'; 
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Greeting Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">
                <span>Student Academic Center</span>
                <span>&bull;</span>
                <span class="text-slate-400">Class: <?= htmlspecialchars($student['class'] ?? 'N/A') ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Welcome, <?= htmlspecialchars($student['full_name'] ?? 'Student') ?>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Roll Number: <strong class="text-slate-800"><?= htmlspecialchars($student['roll_no'] ?: 'N/A') ?></strong> &bull; Student ID: <span class="font-mono text-blue-700 font-bold"><?= htmlspecialchars($student['student_code'] ?? '') ?></span>
            </p>
        </div>

        <div class="flex items-center space-x-3">
            <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                <i class="fa-solid fa-circle-check text-emerald-600 mr-1.5"></i> Admin Verified &amp; Approved
            </span>
            <a href="/student/report-card.php" class="inline-flex items-center px-4 py-2 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-semibold text-xs shadow-md shadow-navy-900/10 transition">
                <i class="fa-solid fa-file-invoice mr-2 text-amber-400"></i> My Report Card
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

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
        
        <!-- Attendance Percentage -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Attendance Rate</span>
                <div class="w-10 h-10 rounded-xl <?= $att_percent >= 75 ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' ?> flex items-center justify-center">
                    <i class="fa-solid fa-calendar-check text-lg"></i>
                </div>
            </div>
            <div class="flex items-baseline space-x-2 mt-3">
                <p class="text-3xl font-extrabold text-slate-900 font-['Outfit']"><?= $att_percent ?>%</p>
                <span class="text-xs font-bold <?= $att_percent >= 75 ? 'text-emerald-600' : 'text-amber-600' ?>">
                    <?= $att_percent >= 75 ? 'Compliant' : 'Needs Attention' ?>
                </span>
            </div>
            <div class="mt-2 text-xs text-blue-600 font-medium">
                <a href="/student/attendance.php" class="hover:underline flex items-center">
                    <span>View attendance log &rarr;</span>
                </a>
            </div>
        </div>

        <!-- Academic Average -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Average Score</span>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="fa-solid fa-award text-lg"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-slate-900 font-['Outfit'] mt-3"><?= $avg_marks > 0 ? $avg_marks . ' / 100' : 'N/A' ?></p>
            <div class="mt-2 text-xs text-blue-600 font-medium">
                <a href="/student/report-card.php" class="hover:underline flex items-center">
                    <span>View full report card &rarr;</span>
                </a>
            </div>
        </div>

        <!-- Academic Term -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Current Term</span>
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <i class="fa-solid fa-graduation-cap text-lg"></i>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-slate-900 font-['Outfit'] mt-3">Mid-Term 2026</p>
            <div class="mt-2 text-xs text-slate-500">
                <span>CBSE Syllabus &bull; Nashik Board</span>
            </div>
        </div>

    </div>

    <!-- Student ERP Modules: Fees, Homework, Timetable -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
        <!-- Fees Card -->
        <div class="p-5 rounded-2xl bg-gradient-to-br from-indigo-900 to-navy-900 text-white shadow-md flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between text-indigo-200">
                    <span class="text-xs font-bold uppercase tracking-wider">Fee Account</span>
                    <i class="fa-solid fa-receipt text-amber-400 text-lg"></i>
                </div>
                <div class="mt-2">
                    <?php if ($stu_pending_fee > 0): ?>
                        <div class="text-2xl font-black text-rose-300 font-['Outfit']">₹<?= number_format($stu_pending_fee) ?></div>
                        <div class="text-xs text-indigo-200">Pending Balance Dues</div>
                    <?php else: ?>
                        <div class="text-2xl font-black text-emerald-400 font-['Outfit']">All Cleared</div>
                        <div class="text-xs text-indigo-200">Paid: ₹<?= number_format($stu_paid) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-4 pt-2 border-t border-indigo-800/80">
                <a href="/student/fees.php" class="text-xs font-bold text-amber-400 hover:text-amber-300 flex items-center justify-between">
                    <span>Pay Fees &amp; Receipts</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Assignments Card -->
        <div class="bg-white p-5 rounded-2xl border <?= $pending_homework > 0 ? 'border-amber-300 bg-amber-50/20' : 'border-slate-200' ?> shadow-sm hover:shadow transition flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Homework &amp; Tasks</span>
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                        <i class="fa-solid fa-book-open-reader text-lg"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="text-2xl font-bold text-slate-900 font-['Outfit']"><?= $pending_homework ?> Tasks Due</div>
                    <div class="text-xs text-slate-500">Class homework &amp; worksheets</div>
                </div>
            </div>
            <div class="mt-4 pt-2 border-t border-slate-100">
                <a href="/student/assignments.php" class="text-xs font-bold text-purple-600 hover:text-purple-800 flex items-center justify-between">
                    <span>Submit Homework Files</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Timetable Card -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow transition flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Class Timetable</span>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                        <i class="fa-solid fa-calendar-days text-lg"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="text-2xl font-bold text-slate-900 font-['Outfit']"><?= htmlspecialchars($student['class'] ?? 'Class') ?></div>
                    <div class="text-xs text-slate-500">6 periods daily (Mon to Sat)</div>
                </div>
            </div>
            <div class="mt-4 pt-2 border-t border-slate-100">
                <a href="/student/timetable.php" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center justify-between">
                    <span>View Today's Lectures</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Two Columns: Recent Grades & School Notices -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Student Academic Record -->
        <div class="lg:col-span-7 bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                    <div class="flex items-center space-x-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-book-open"></i>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Recent Examination Marks</h3>
                    </div>
                    <a href="/student/report-card.php" class="text-xs font-bold text-emerald-600 hover:text-emerald-800">Print Report &rarr;</a>
                </div>

                <?php if (empty($grades)): ?>
                    <p class="text-xs text-slate-500 py-6 text-center">No grades published for your account yet.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($grades as $g): ?>
                            <div class="flex items-center justify-between p-3.5 rounded-2xl bg-slate-50 border border-slate-100">
                                <div>
                                    <div class="font-bold text-slate-900 text-xs"><?= htmlspecialchars($g['subject']) ?></div>
                                    <div class="text-[11px] text-slate-500"><?= htmlspecialchars($g['exam_name']) ?> &bull; Remarks: <?= htmlspecialchars($g['remarks'] ?: 'Satisfactory') ?></div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-black bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($g['grade']) ?>
                                    </span>
                                    <div class="text-[11px] font-bold text-slate-700 mt-0.5"><?= $g['marks_obtained'] ?> / <?= $g['max_marks'] ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="pt-4 mt-4 border-t border-slate-100">
                <a href="/student/report-card.php" class="w-full py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs flex items-center justify-center transition">
                    <i class="fa-solid fa-award mr-2 text-amber-500"></i> Open Complete Official Digital Report Card
                </a>
            </div>
        </div>

        <!-- Student Profile & Important Notices -->
        <div class="lg:col-span-5 space-y-6">
            
            <!-- Student Profile Summary -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-3 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900 font-['Outfit']">Student Enrollment Details</h3>
                    <span class="font-mono text-blue-600 font-bold"><?= htmlspecialchars($student['student_code'] ?? '') ?></span>
                </div>

                <div class="space-y-2 text-slate-600">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Full Name:</span>
                        <strong class="text-slate-900"><?= htmlspecialchars($student['full_name'] ?? '') ?></strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Class &amp; Roll:</span>
                        <span class="text-slate-900"><?= htmlspecialchars($student['class'] ?? '') ?> &bull; Roll: <?= htmlspecialchars($student['roll_no'] ?: 'N/A') ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Parent/Guardian:</span>
                        <span class="text-slate-900"><?= htmlspecialchars($student['guardian_name'] ?: 'N/A') ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Contact Phone:</span>
                        <span class="text-slate-900"><?= htmlspecialchars($student['phone'] ?: 'N/A') ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Blood Group:</span>
                        <span class="text-slate-900 font-semibold"><?= htmlspecialchars($student['blood_group'] ?: 'N/A') ?></span>
                    </div>
                </div>
            </div>

            <!-- Campus Notices Feed -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-3">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900 font-['Outfit']">School Circulars</h3>
                    <a href="/student/notices.php" class="text-xs font-bold text-purple-600 hover:text-purple-800">All Circulars &rarr;</a>
                </div>

                <div class="space-y-3">
                    <?php foreach ($notices as $n): ?>
                        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100 text-xs">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-[10px] uppercase text-purple-700 bg-purple-100 px-2 py-0.5 rounded"><?= htmlspecialchars($n['category']) ?></span>
                                <span class="text-[10px] text-slate-400"><?= date('d M Y', strtotime($n['created_at'])) ?></span>
                            </div>
                            <h4 class="font-bold text-slate-900"><?= htmlspecialchars($n['title']) ?></h4>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
