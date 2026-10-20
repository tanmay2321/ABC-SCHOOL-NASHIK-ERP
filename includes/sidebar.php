<?php
/**
 * Role-Based Navigation Sidebar
 * ABC School Nashik ERP System
 */

$user = current_user();
$role = $user['role'] ?? 'student';
$current_uri = $_SERVER['REQUEST_URI'];

// Define navigation items per role
$nav_items = [];

if ($role === 'admin') {
    $nav_items = [
        [
            'title' => 'Dashboard Overview',
            'url' => '/admin/index.php',
            'icon' => 'fa-solid fa-chart-pie',
            'active' => strpos($current_uri, '/admin/index.php') !== false || $current_uri === '/admin/'
        ],
        [
            'title' => 'Teachers Management',
            'url' => '/admin/teachers.php',
            'icon' => 'fa-solid fa-chalkboard-user',
            'active' => strpos($current_uri, '/admin/teachers.php') !== false
        ],
        [
            'title' => 'Student Approvals & Roster',
            'url' => '/admin/students.php',
            'icon' => 'fa-solid fa-users-gear',
            'badge' => $pending_count > 0 ? $pending_count : null,
            'active' => strpos($current_uri, '/admin/students.php') !== false
        ],
        [
            'title' => 'Fee Accounting & Invoices',
            'url' => '/admin/fees.php',
            'icon' => 'fa-solid fa-receipt',
            'active' => strpos($current_uri, '/admin/fees.php') !== false
        ],
        [
            'title' => 'Notice Board',
            'url' => '/admin/notices.php',
            'icon' => 'fa-solid fa-bullhorn',
            'active' => strpos($current_uri, '/admin/notices.php') !== false
        ],
        [
            'title' => 'Class Timetables',
            'url' => '/admin/timetable.php',
            'icon' => 'fa-solid fa-calendar-days',
            'active' => strpos($current_uri, '/admin/timetable.php') !== false
        ],
        [
            'title' => 'Leave Approvals',
            'url' => '/admin/leaves.php',
            'icon' => 'fa-solid fa-envelope-open-text',
            'active' => strpos($current_uri, '/admin/leaves.php') !== false
        ],
        [
            'title' => 'Database Explorer',
            'url' => '/admin/database.php',
            'icon' => 'fa-solid fa-database',
            'active' => strpos($current_uri, '/admin/database.php') !== false
        ],
        [
            'title' => 'System Settings',
            'url' => '/admin/settings.php',
            'icon' => 'fa-solid fa-sliders',
            'active' => strpos($current_uri, '/admin/settings.php') !== false
        ]
    ];
} elseif ($role === 'teacher') {
    $nav_items = [
        [
            'title' => 'Teacher Dashboard',
            'url' => '/teacher/index.php',
            'icon' => 'fa-solid fa-chart-line',
            'active' => strpos($current_uri, '/teacher/index.php') !== false || $current_uri === '/teacher/'
        ],
        [
            'title' => 'Class Students',
            'url' => '/teacher/students.php',
            'icon' => 'fa-solid fa-user-graduate',
            'active' => strpos($current_uri, '/teacher/students.php') !== false
        ],
        [
            'title' => 'Attendance Register',
            'url' => '/teacher/attendance.php',
            'icon' => 'fa-solid fa-clipboard-user',
            'active' => strpos($current_uri, '/teacher/attendance.php') !== false
        ],
        [
            'title' => 'Marks & Assessment',
            'url' => '/teacher/marks.php',
            'icon' => 'fa-solid fa-file-pen',
            'active' => strpos($current_uri, '/teacher/marks.php') !== false
        ],
        [
            'title' => 'Assignments & Homework',
            'url' => '/teacher/assignments.php',
            'icon' => 'fa-solid fa-book-bookmark',
            'active' => strpos($current_uri, '/teacher/assignments.php') !== false
        ],
        [
            'title' => 'Teaching Timetable',
            'url' => '/teacher/timetable.php',
            'icon' => 'fa-solid fa-calendar-week',
            'active' => strpos($current_uri, '/teacher/timetable.php') !== false
        ],
        [
            'title' => 'Apply for Leave',
            'url' => '/teacher/leaves.php',
            'icon' => 'fa-solid fa-calendar-minus',
            'active' => strpos($current_uri, '/teacher/leaves.php') !== false
        ],
        [
            'title' => 'School Notices',
            'url' => '/teacher/notices.php',
            'icon' => 'fa-solid fa-bullhorn',
            'active' => strpos($current_uri, '/teacher/notices.php') !== false
        ]
    ];
} elseif ($role === 'student') {
    $nav_items = [
        [
            'title' => 'Student Portal',
            'url' => '/student/index.php',
            'icon' => 'fa-solid fa-house-chimney-user',
            'active' => strpos($current_uri, '/student/index.php') !== false || $current_uri === '/student/'
        ],
        [
            'title' => 'Fee Portal & Receipts',
            'url' => '/student/fees.php',
            'icon' => 'fa-solid fa-wallet',
            'active' => strpos($current_uri, '/student/fees.php') !== false
        ],
        [
            'title' => 'Homework & Assignments',
            'url' => '/student/assignments.php',
            'icon' => 'fa-solid fa-book-open-reader',
            'active' => strpos($current_uri, '/student/assignments.php') !== false
        ],
        [
            'title' => 'My Attendance',
            'url' => '/student/attendance.php',
            'icon' => 'fa-solid fa-calendar-check',
            'active' => strpos($current_uri, '/student/attendance.php') !== false
        ],
        [
            'title' => 'Report Card & Grades',
            'url' => '/student/report-card.php',
            'icon' => 'fa-solid fa-award',
            'active' => strpos($current_uri, '/student/report-card.php') !== false
        ],
        [
            'title' => 'Class Timetable',
            'url' => '/student/timetable.php',
            'icon' => 'fa-solid fa-calendar-days',
            'active' => strpos($current_uri, '/student/timetable.php') !== false
        ],
        [
            'title' => 'Apply for Leave',
            'url' => '/student/leaves.php',
            'icon' => 'fa-solid fa-notes-medical',
            'active' => strpos($current_uri, '/student/leaves.php') !== false
        ],
        [
            'title' => 'Circulars & Notices',
            'url' => '/student/notices.php',
            'icon' => 'fa-solid fa-newspaper',
            'active' => strpos($current_uri, '/student/notices.php') !== false
        ]
    ];
}
?>

<!-- Sidebar Component -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 bg-slate-900 text-slate-300 transform -translate-x-full lg:translate-x-0 lg:static lg:inset-auto transition-transform duration-200 ease-in-out flex flex-col justify-between shrink-0 shadow-xl border-r border-slate-800">
    <div class="p-4 flex-1 flex flex-col overflow-y-auto">
        <!-- School Tagline / Branch -->
        <div class="px-3 py-3 mb-4 rounded-xl bg-slate-800/60 border border-slate-700/50 flex items-center space-x-3">
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center font-bold text-sm">
                <i class="fa-solid fa-building-columns"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-white">Nashik Main Campus</p>
                <p class="text-[11px] text-slate-400">CBSE Affiliation #113042</p>
            </div>
        </div>

        <!-- Navigation Group -->
        <div class="space-y-1">
            <p class="px-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Main Menu</p>
            <?php foreach ($nav_items as $item): ?>
                <a href="<?= $item['url'] ?>" 
                   class="flex items-center justify-between px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all group <?= $item['active'] ? 'bg-navy-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <div class="flex items-center space-x-3">
                        <i class="<?= $item['icon'] ?> text-base w-5 text-center <?= $item['active'] ? 'text-amber-300' : 'text-slate-400 group-hover:text-blue-400' ?>"></i>
                        <span><?= $item['title'] ?></span>
                    </div>
                    <?php if (isset($item['badge'])): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-500 text-navy-950">
                            <?= $item['badge'] ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Quick Switch or Portal Guide -->
        <div class="mt-8 pt-4 border-t border-slate-800">
            <p class="px-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Portal Access</p>
            <div class="px-3 py-2.5 rounded-xl bg-slate-800/40 border border-slate-700/40 text-xs text-slate-300">
                <div class="flex items-center space-x-2 text-amber-400 font-semibold mb-1">
                    <i class="fa-solid fa-shield-cat"></i>
                    <span>Role: <?= ucfirst($role) ?></span>
                </div>
                <p class="text-[11px] text-slate-400">Secure session established with ABC School Nashik secure gateway.</p>
            </div>
        </div>
    </div>

    <!-- User Profile Bottom Widget -->
    <div class="p-4 border-t border-slate-800 bg-slate-950/40">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-blue-600/20 border border-blue-500/30 text-blue-400 font-bold flex items-center justify-center text-sm shrink-0">
                    <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="truncate">
                    <p class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($user['name'] ?? 'Guest') ?></p>
                    <p class="text-[11px] text-slate-400 truncate"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                </div>
            </div>
            <a href="/logout.php" class="text-slate-400 hover:text-rose-400 p-1.5 rounded-lg hover:bg-slate-800 transition" title="Logout">
                <i class="fa-solid fa-power-off text-sm"></i>
            </a>
        </div>
    </div>
</aside>
