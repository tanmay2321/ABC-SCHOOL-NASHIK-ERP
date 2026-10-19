<?php
/**
 * Student: Official Digital Report Card
 * ABC School Nashik ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('student');

$student_user_id = $_SESSION['user_id'];
$sStmt = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
$sStmt->execute(['uid' => $student_user_id]);
$student = $sStmt->fetch();
$student_id = $student['id'] ?? 0;

// Fetch Grades
$gStmt = $pdo->prepare("
    SELECT g.*, t.full_name as teacher_name
    FROM grades g
    LEFT JOIN users u ON g.teacher_id = u.id
    LEFT JOIN teachers t ON u.id = t.user_id
    WHERE g.student_id = :sid
    ORDER BY g.subject ASC
");
$gStmt->execute(['sid' => $student_id]);
$grades = $gStmt->fetchAll();

// Calculations
$total_obtained = 0;
$total_max = 0;
foreach ($grades as $g) {
    $total_obtained += $g['marks_obtained'];
    $total_max += $g['max_marks'];
}

$overall_pct = ($total_max > 0) ? round(($total_obtained / $total_max) * 100, 1) : 0;

$overall_grade = 'N/A';
$result_status = 'Incomplete';
if ($total_max > 0) {
    if ($overall_pct >= 90) { $overall_grade = 'A+'; $result_status = 'PASSED WITH OUTSTANDING DISTINCTION'; }
    elseif ($overall_pct >= 80) { $overall_grade = 'A'; $result_status = 'PASSED WITH FIRST CLASS DISTINCTION'; }
    elseif ($overall_pct >= 70) { $overall_grade = 'B+'; $result_status = 'PASSED FIRST CLASS'; }
    elseif ($overall_pct >= 60) { $overall_grade = 'B'; $result_status = 'PASSED SECOND CLASS'; }
    elseif ($overall_pct >= 50) { $overall_grade = 'C'; $result_status = 'PASSED'; }
    elseif ($overall_pct >= 35) { $overall_grade = 'D'; $result_status = 'PASSED (NEEDS IMPROVEMENT)'; }
    else { $overall_grade = 'F'; $result_status = 'FAILED'; }
}
?>

<?php 
$page_title = 'Official Student Report Card';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php'; 
?>

<main class="flex-1 overflow-y-auto bg-slate-100 p-4 sm:p-6 lg:p-8">

    <!-- Action Bar (Hidden on print) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 no-print">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
                <span>Academic Transcript</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 font-['Outfit']">
                Official Digital Academic Report Card
            </h1>
        </div>

        <button type="button" onclick="window.print()" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md transition">
            <i class="fa-solid fa-print mr-2 text-amber-400"></i> Print / Download PDF
        </button>
    </div>

    <!-- Official Report Card Sheet -->
    <div class="bg-white rounded-3xl border-2 border-slate-300 shadow-xl p-8 sm:p-12 max-w-4xl mx-auto print-page">
        
        <!-- School Crest & Header -->
        <div class="text-center pb-6 border-b-2 border-navy-900 mb-6">
            <div class="w-16 h-16 rounded-3xl bg-navy-950 flex items-center justify-center text-white shadow-lg mx-auto mb-3">
                <i class="fa-solid fa-graduation-cap text-3xl text-amber-400"></i>
            </div>
            <h2 class="text-2xl sm:text-3xl font-black text-navy-950 font-['Outfit'] tracking-tight uppercase">
                ABC Senior Secondary School Nashik
            </h2>
            <p class="text-xs font-bold text-amber-600 tracking-wider uppercase mt-0.5">
                CBSE Board Affiliated &bull; Affiliation No: 113042 &bull; School Code: 30129
            </p>
            <p class="text-xs text-slate-500 mt-1">
                Off Gangapur Road, Nashik, Maharashtra - 422013 | Phone: +91 (0253) 234-5678
            </p>
            
            <div class="mt-4 inline-block px-4 py-1.5 rounded-full bg-navy-900 text-white text-xs font-bold uppercase tracking-widest font-['Outfit']">
                Official Statement of Marks &bull; Academic Year 2026-2027
            </div>
        </div>

        <!-- Student Information Card -->
        <div class="bg-slate-50 rounded-2xl border border-slate-200 p-5 mb-8 text-xs">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <span class="block text-slate-400 uppercase text-[10px] font-bold">Student Name</span>
                    <strong class="text-slate-900 text-sm"><?= htmlspecialchars($student['full_name'] ?? 'N/A') ?></strong>
                </div>
                <div>
                    <span class="block text-slate-400 uppercase text-[10px] font-bold">Student ID</span>
                    <span class="font-mono font-bold text-blue-700 text-sm"><?= htmlspecialchars($student['student_code'] ?? 'N/A') ?></span>
                </div>
                <div>
                    <span class="block text-slate-400 uppercase text-[10px] font-bold">Class &amp; Section</span>
                    <strong class="text-slate-900 text-sm"><?= htmlspecialchars($student['class'] ?? 'N/A') ?></strong>
                </div>
                <div>
                    <span class="block text-slate-400 uppercase text-[10px] font-bold">Roll Number</span>
                    <strong class="text-slate-900 text-sm"><?= htmlspecialchars($student['roll_no'] ?: 'N/A') ?></strong>
                </div>
                <div>
                    <span class="block text-slate-400 uppercase text-[10px] font-bold">Father / Guardian</span>
                    <span class="text-slate-800"><?= htmlspecialchars($student['guardian_name'] ?: 'N/A') ?></span>
                </div>
                <div>
                    <span class="block text-slate-400 uppercase text-[10px] font-bold">Date of Birth</span>
                    <span class="text-slate-800"><?= htmlspecialchars($student['dob'] ?: 'N/A') ?></span>
                </div>
                <div>
                    <span class="block text-slate-400 uppercase text-[10px] font-bold">Blood Group</span>
                    <span class="text-slate-800 font-semibold"><?= htmlspecialchars($student['blood_group'] ?: 'N/A') ?></span>
                </div>
                <div>
                    <span class="block text-slate-400 uppercase text-[10px] font-bold">Date of Issue</span>
                    <span class="text-slate-800"><?= date('d F Y') ?></span>
                </div>
            </div>
        </div>

        <!-- Marks Table -->
        <div class="border border-slate-300 rounded-2xl overflow-hidden mb-8">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-navy-950 text-white font-bold uppercase tracking-wider text-[11px]">
                        <th class="py-3 px-4">Subject</th>
                        <th class="py-3 px-4">Exam Term</th>
                        <th class="py-3 px-4 text-center">Max Marks</th>
                        <th class="py-3 px-4 text-center">Marks Obtained</th>
                        <th class="py-3 px-4 text-center">Percentage</th>
                        <th class="py-3 px-4 text-center">Grade</th>
                        <th class="py-3 px-4">Teacher Remark</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php if (empty($grades)): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                No assessment records registered for this student yet.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($grades as $g): ?>
                        <?php $p = round(($g['marks_obtained'] / $g['max_marks']) * 100, 1); ?>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-bold text-slate-900"><?= htmlspecialchars($g['subject']) ?></td>
                            <td class="py-3 px-4 text-slate-600"><?= htmlspecialchars($g['exam_name']) ?></td>
                            <td class="py-3 px-4 text-center text-slate-600"><?= $g['max_marks'] ?></td>
                            <td class="py-3 px-4 text-center font-bold text-slate-900"><?= $g['marks_obtained'] ?></td>
                            <td class="py-3 px-4 text-center font-semibold text-blue-700"><?= $p ?>%</td>
                            <td class="py-3 px-4 text-center">
                                <span class="font-black px-2 py-0.5 rounded text-xs <?= in_array($g['grade'], ['A+', 'A']) ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' ?>">
                                    <?= htmlspecialchars($g['grade']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-500 italic"><?= htmlspecialchars($g['remarks'] ?: 'Satisfactory') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-100 border-t-2 border-slate-300 font-bold">
                    <tr>
                        <td colspan="2" class="py-3 px-4 text-slate-900 uppercase">Grand Total</td>
                        <td class="py-3 px-4 text-center text-slate-900"><?= $total_max ?></td>
                        <td class="py-3 px-4 text-center text-navy-950 text-sm"><?= $total_obtained ?></td>
                        <td class="py-3 px-4 text-center text-blue-700 text-sm"><?= $overall_pct ?>%</td>
                        <td class="py-3 px-4 text-center text-sm font-black text-emerald-700"><?= $overall_grade ?></td>
                        <td class="py-3 px-4 text-slate-600"><?= $result_status ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Grading Legend -->
        <div class="mb-8 p-3 rounded-xl bg-slate-50 border border-slate-200 text-[10px] text-slate-500 flex flex-wrap gap-4 justify-between">
            <span><strong>CBSE Grading Scale:</strong></span>
            <span>A+ (90-100%): Outstanding</span>
            <span>A (80-89%): Excellent</span>
            <span>B+ (70-79%): Very Good</span>
            <span>B (60-69%): Good</span>
            <span>C (50-59%): Average</span>
            <span>D (35-49%): Pass</span>
            <span>F (&lt;35%): Fail</span>
        </div>

        <!-- Official Signatures Block -->
        <div class="pt-8 border-t border-slate-300 grid grid-cols-3 gap-6 text-center text-xs">
            <div>
                <div class="h-12 flex items-end justify-center pb-1 text-slate-400 italic">
                    Mrs. Priya Kulkarni
                </div>
                <div class="border-t border-slate-400 pt-1 font-bold text-slate-700">Class Teacher Signature</div>
            </div>

            <div>
                <div class="h-12 flex items-center justify-center">
                    <div class="w-12 h-12 rounded-full border-2 border-dashed border-amber-600 flex items-center justify-center text-[9px] font-bold text-amber-700 uppercase">
                        SEAL
                    </div>
                </div>
                <div class="border-t border-slate-400 pt-1 font-bold text-slate-700">School Official Stamp</div>
            </div>

            <div>
                <div class="h-12 flex items-end justify-center pb-1 text-slate-400 italic">
                    Dr. Rajesh Sharma
                </div>
                <div class="border-t border-slate-400 pt-1 font-bold text-slate-700">Principal Signature</div>
            </div>
        </div>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
