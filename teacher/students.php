<?php
/**
 * Teacher: Class Students Directory
 * ABC School Nashik ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('teacher');

$selected_class = $_GET['class'] ?? 'all';

// Fetch distinct classes
$classes = $pdo->query("SELECT DISTINCT class FROM students ORDER BY class ASC")->fetchAll(PDO::FETCH_COLUMN);

// Fetch students
$query = "
    SELECT s.*, u.email, u.status
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE u.status = 'approved'
";
$params = [];

if ($selected_class !== 'all' && !empty($selected_class)) {
    $query .= " AND s.class = :class";
    $params['class'] = $selected_class;
}

$query .= " ORDER BY s.class ASC, s.roll_no ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();
?>

<?php 
$page_title = 'Class Students Roster';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php'; 
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
                <span>Faculty Classroom Access</span>
                <span>&bull;</span>
                <span class="text-slate-400">Total: <?= count($students) ?> Students Listed</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Student Directory &amp; Class Rosters
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Browse student profiles, parent/guardian contact info, and academic class assignments.
            </p>
        </div>

        <!-- Class Filter Pill Buttons -->
        <div class="flex items-center space-x-2 overflow-x-auto pb-2 sm:pb-0">
            <a href="/teacher/students.php?class=all" class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $selected_class === 'all' ? 'bg-navy-800 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100' ?>">
                All Classes
            </a>
            <?php foreach ($classes as $c): ?>
                <a href="/teacher/students.php?class=<?= urlencode($c) ?>" class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $selected_class === $c ? 'bg-navy-800 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100' ?>">
                    <?= htmlspecialchars($c) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Search Input -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="relative w-full sm:w-80">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
            </div>
            <input type="text" id="teacherStudentSearch" onkeyup="filterTable('teacherStudentSearch', 'classStudentsTable')" placeholder="Search student name, roll, code..." 
                   class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
        </div>
        <div class="text-xs text-slate-500">
            Filtered by: <strong class="text-slate-800"><?= htmlspecialchars($selected_class === 'all' ? 'All Classes' : $selected_class) ?></strong>
        </div>
    </div>

    <!-- Students Table -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table id="classStudentsTable" class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3 px-4 sm:px-6">Student Name</th>
                        <th class="py-3 px-4">Student ID</th>
                        <th class="py-3 px-4">Class</th>
                        <th class="py-3 px-4">Roll No</th>
                        <th class="py-3 px-4">Guardian Name</th>
                        <th class="py-3 px-4">Contact Phone</th>
                        <th class="py-3 px-4 sm:px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                No students found for this selection.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($students as $s): ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-4 sm:px-6">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 font-bold text-xs flex items-center justify-center shrink-0">
                                        <?= strtoupper(substr($s['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900"><?= htmlspecialchars($s['full_name']) ?></div>
                                        <div class="text-[11px] text-slate-500"><?= htmlspecialchars($s['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-mono font-bold text-blue-700">
                                <?= htmlspecialchars($s['student_code']) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                                    <?= htmlspecialchars($s['class']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-800">
                                <?= htmlspecialchars($s['roll_no'] ?: 'N/A') ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-700">
                                <?= htmlspecialchars($s['guardian_name'] ?: 'N/A') ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600">
                                <?= htmlspecialchars($s['phone'] ?: 'N/A') ?>
                            </td>
                            <td class="py-3.5 px-4 sm:px-6 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    <a href="/teacher/marks.php?student_id=<?= $s['id'] ?>" class="px-2.5 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-semibold text-xs transition" title="Add Grade">
                                        <i class="fa-solid fa-file-pen mr-1"></i> Marks
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
