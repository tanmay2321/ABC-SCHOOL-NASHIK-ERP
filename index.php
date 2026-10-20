<?php
/**
 * ABC School Nashik - Public Website & ERP Gateway
 */
require_once __DIR__ . '/includes/auth.php';

$notices = $pdo->query("SELECT * FROM notices WHERE audience IN ('all', 'students') ORDER BY id DESC LIMIT 4")->fetchAll();
$stats = get_school_stats($pdo);

$school_logo = get_setting($pdo, 'school_logo', '');
$school_name = get_setting($pdo, 'school_name', 'ABC School Nashik');
$school_phone = get_setting($pdo, 'school_phone', '+91 (0253) 234-5678');
$school_email = get_setting($pdo, 'school_email', 'contact@abcschoolnashik.edu');
$school_address = get_setting($pdo, 'school_address', 'Gangapur Road, Nashik, Maharashtra 422013');
$school_affiliation = get_setting($pdo, 'school_affiliation', 'CBSE Affiliation No: 113042');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ABC School Nashik - Official School Portal & ERP</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: {
                            50: '#f0f5ff',
                            100: '#e0ebff',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                            950: '#172554',
                        },
                        gold: {
                            400: '#fbbf24',
                            500: '#f59e0b',
                            600: '#d97706',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col font-sans">

    <!-- Top Info Bar -->
    <div class="bg-navy-950 text-slate-300 text-xs py-2 px-4 sm:px-8 border-b border-navy-800">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-2">
            <div class="flex items-center space-x-6">
                <span><i class="fa-solid fa-location-dot text-amber-400 mr-1.5"></i> Gangapur Road, Nashik, Maharashtra 422013</span>
                <span class="hidden md:inline"><i class="fa-solid fa-phone text-amber-400 mr-1.5"></i> +91 (0253) 234-5678</span>
                <span class="hidden lg:inline"><i class="fa-solid fa-envelope text-amber-400 mr-1.5"></i> contact@abcschoolnashik.edu</span>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-amber-400 font-medium"><i class="fa-solid fa-certificate mr-1"></i> CBSE Affiliation No: 113042</span>
                <span>&bull;</span>
                <a href="/login.php" class="text-white hover:text-amber-300 font-semibold transition">ERP Login &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Navigation Bar -->
    <nav class="bg-white/90 backdrop-blur-md sticky top-0 z-50 border-b border-slate-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Brand / Logo -->
                <a href="/" class="flex items-center space-x-3.5">
                    <?php if (!empty($school_logo) && file_exists(__DIR__ . '/' . ltrim($school_logo, '/'))): ?>
                        <img src="<?= htmlspecialchars($school_logo) ?>" alt="School Logo" class="w-12 h-12 object-contain rounded-2xl shadow-lg shadow-blue-500/10 p-1 bg-white border border-slate-200">
                    <?php else: ?>
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-navy-900 to-navy-700 flex items-center justify-center text-white shadow-lg shadow-blue-500/20">
                            <i class="fa-solid fa-school text-2xl text-amber-400"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <span class="text-xl sm:text-2xl font-extrabold tracking-tight text-navy-950 font-['Outfit'] block leading-none"><?= htmlspecialchars(strtoupper($school_name)) ?></span>
                        <span class="text-xs font-bold tracking-widest text-amber-600 uppercase block mt-1">Nashik • Excellence in Education</span>
                    </div>
                </a>

                <!-- Nav Actions -->
                <div class="flex items-center space-x-3 sm:space-x-4">
                    <a href="/register.php" class="inline-flex items-center px-4 py-2.5 rounded-xl text-sm font-semibold text-navy-800 bg-navy-50 hover:bg-navy-100 border border-navy-200 transition">
                        <i class="fa-solid fa-user-plus mr-2 text-navy-600"></i>
                        <span>Student Registration</span>
                    </a>
                    
                    <?php if (is_logged_in()): ?>
                        <?php 
                            $role = $_SESSION['role'];
                            $dashUrl = $role === 'admin' ? '/admin/index.php' : ($role === 'teacher' ? '/teacher/index.php' : '/student/index.php');
                        ?>
                        <a href="<?= $dashUrl ?>" class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-navy-800 hover:bg-navy-900 shadow-md shadow-navy-950/20 transition">
                            <i class="fa-solid fa-gauge mr-2 text-amber-400"></i> Go to Dashboard
                        </a>
                    <?php else: ?>
                        <a href="/login.php" class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-navy-800 to-navy-700 hover:from-navy-900 hover:to-navy-800 shadow-md shadow-navy-900/20 transition">
                            <i class="fa-solid fa-right-to-bracket mr-2 text-amber-400"></i> ERP Sign In
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="relative bg-gradient-to-b from-navy-950 via-navy-900 to-slate-900 text-white overflow-hidden py-16 sm:py-24">
        <!-- Background Grid Pattern -->
        <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#3b82f6_1px,transparent_1px)] [background-size:16px_16px]"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                
                <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                    <div class="inline-flex items-center space-x-2 px-3 py-1.5 rounded-full bg-navy-800/80 border border-blue-500/30 text-xs font-semibold text-blue-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        <span>Official Unified School ERP Platform</span>
                    </div>

                    <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight font-['Outfit'] leading-tight">
                        Empowering Future Leaders at <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-300 via-amber-200 to-yellow-400">ABC School Nashik</span>
                    </h1>

                    <p class="text-base sm:text-lg text-slate-300 max-w-2xl leading-relaxed">
                        A modern, integrated digital management system connecting administrators, teachers, and students. Experience seamless admissions, live academic tracking, automated grade reports, and secure role-based portals.
                    </p>

                    <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4 pt-2">
                        <a href="/login.php" class="w-full sm:w-auto inline-flex items-center justify-center px-7 py-3.5 rounded-xl font-bold text-navy-950 bg-gradient-to-r from-amber-400 to-yellow-300 hover:from-amber-300 hover:to-yellow-200 shadow-lg shadow-amber-500/25 transition-all transform hover:-translate-y-0.5">
                            <i class="fa-solid fa-arrow-right-to-bracket mr-2"></i> Access ERP Portal
                        </a>
                        <a href="/register.php" class="w-full sm:w-auto inline-flex items-center justify-center px-7 py-3.5 rounded-xl font-bold text-white bg-slate-800/80 hover:bg-slate-700/80 border border-slate-700 transition">
                            <i class="fa-solid fa-id-card mr-2 text-amber-400"></i> New Student Admission
                        </a>
                    </div>

                    <!-- Quick Demo Hint Pill -->
                    <div class="pt-2 text-xs text-slate-400 flex items-center justify-center lg:justify-start space-x-2">
                        <i class="fa-solid fa-lightbulb text-amber-400"></i>
                        <span>Pre-seeded accounts available for Admin, Teachers, and Students on the login page!</span>
                    </div>
                </div>

                <!-- Right Hero Card: Institutional Portals -->
                <div class="lg:col-span-5">
                    <div class="bg-slate-800/70 border border-slate-700/80 rounded-3xl p-6 sm:p-8 backdrop-blur-xl shadow-2xl space-y-4">
                        <div class="flex items-center justify-between pb-4 border-b border-slate-700">
                            <div>
                                <h3 class="text-lg font-bold text-white font-['Outfit']">Three Dedicated Dashboards</h3>
                                <p class="text-xs text-slate-400">Role-specific access control &amp; security</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                Live 2026-27
                            </span>
                        </div>

                        <!-- 1. Admin Portal -->
                        <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-700/50 hover:border-purple-500/50 transition group">
                            <div class="flex items-start space-x-3.5">
                                <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition">
                                    <i class="fa-solid fa-shield-halved text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-white">1. Administrator Dashboard</h4>
                                        <span class="text-[11px] text-purple-300 font-medium">Full Access</span>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1">Create teacher IDs, review &amp; approve pending student registrations, edit student/faculty details.</p>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Teacher Portal -->
                        <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-700/50 hover:border-blue-500/50 transition group">
                            <div class="flex items-start space-x-3.5">
                                <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition">
                                    <i class="fa-solid fa-chalkboard-user text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-white">2. Faculty &amp; Teacher Dashboard</h4>
                                        <span class="text-[11px] text-blue-300 font-medium">Academic Suite</span>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1">Manage class rosters, record daily attendance, enter student test &amp; exam grades, review notices.</p>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Student Portal -->
                        <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-700/50 hover:border-emerald-500/50 transition group">
                            <div class="flex items-start space-x-3.5">
                                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition">
                                    <i class="fa-solid fa-user-graduate text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-white">3. Student &amp; Parent Dashboard</h4>
                                        <span class="text-[11px] text-emerald-300 font-medium">Self Service</span>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1">Self-registration with Admin approval gate. View attendance percentage, report cards, and notices.</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </header>

    <!-- School Metrics Counter -->
    <section class="py-10 bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                
                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80">
                    <p class="text-3xl font-extrabold text-navy-900 font-['Outfit']"><?= $stats['total_students'] ?></p>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mt-1">Enrolled Students</p>
                </div>

                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80">
                    <p class="text-3xl font-extrabold text-blue-600 font-['Outfit']"><?= $stats['total_teachers'] ?></p>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mt-1">Qualified Faculty</p>
                </div>

                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80">
                    <p class="text-3xl font-extrabold text-amber-500 font-['Outfit']"><?= $stats['pending_students'] ?></p>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mt-1">Pending Approvals</p>
                </div>

                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80">
                    <p class="text-3xl font-extrabold text-emerald-600 font-['Outfit']">100%</p>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mt-1">CBSE Pass Rate</p>
                </div>

            </div>
        </div>
    </section>

    <!-- Latest Circulars & Notices Section -->
    <section class="py-16 bg-slate-50 flex-1">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 font-['Outfit']">Official School Notices &amp; Circulars</h2>
                    <p class="text-sm text-slate-500">Stay informed with updates from ABC School Administration, Nashik</p>
                </div>
                <a href="/login.php" class="text-sm font-semibold text-navy-700 hover:text-navy-900 flex items-center">
                    <span>View all announcements in ERP</span>
                    <i class="fa-solid fa-arrow-right ml-2 text-xs"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($notices as $notice): ?>
                    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="px-2.5 py-1 rounded-md text-[11px] font-bold 
                                    <?= $notice['category'] === 'Exam' ? 'bg-rose-100 text-rose-700' : ($notice['category'] === 'Holiday' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700') ?>">
                                    <?= htmlspecialchars($notice['category']) ?>
                                </span>
                                <span class="text-[11px] text-slate-400">
                                    <i class="fa-regular fa-clock mr-1"></i>
                                    <?= date('d M Y', strtotime($notice['created_at'])) ?>
                                </span>
                            </div>
                            <h3 class="text-sm font-bold text-slate-900 mb-2 leading-snug"><?= htmlspecialchars($notice['title']) ?></h3>
                            <p class="text-xs text-slate-600 line-clamp-3 leading-relaxed"><?= htmlspecialchars($notice['content']) ?></p>
                        </div>
                        <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                            <span>Audience: <strong class="text-slate-600 uppercase"><?= $notice['audience'] ?></strong></span>
                            <i class="fa-solid fa-shield-check text-emerald-500" title="Verified by Administration"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-navy-950 text-slate-400 text-sm py-12 border-t border-navy-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-3 gap-8">
            <div>
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <span class="text-lg font-bold text-white font-['Outfit']">ABC School Nashik</span>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Committed to holistic education, moral integrity, and technological innovation. Providing premier primary and senior secondary schooling in the heart of Nashik.
                </p>
            </div>

            <div>
                <h4 class="text-white font-semibold mb-3 font-['Outfit']">Quick Portal Links</h4>
                <ul class="space-y-2 text-xs">
                    <li><a href="/login.php" class="hover:text-amber-400 transition">&bull; Faculty &amp; Staff Login</a></li>
                    <li><a href="/register.php" class="hover:text-amber-400 transition">&bull; Student Admission Portal</a></li>
                    <li><a href="/login.php" class="hover:text-amber-400 transition">&bull; Student &amp; Parent Sign In</a></li>
                    <li><a href="/admin/index.php" class="hover:text-amber-400 transition">&bull; Administrative Center</a></li>
                </ul>
            </div>

            <div>
                <h4 class="text-white font-semibold mb-3 font-['Outfit']">Campus Contact</h4>
                <p class="text-xs text-slate-400 leading-relaxed mb-2">
                    ABC School Campus, Off Gangapur Road,<br>
                    Nashik, Maharashtra - 422013, India
                </p>
                <p class="text-xs text-slate-400">Phone: +91 (0253) 234-5678 / 5679</p>
                <p class="text-xs text-slate-400">Email: office@abcschoolnashik.edu</p>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 pt-6 border-t border-slate-800 text-xs text-center text-slate-500">
            &copy; <?= date('Y') ?> ABC Senior Secondary School Nashik. All Rights Reserved. ERP System Powered by PHP &amp; SQLite.
        </div>
    </footer>

</body>
</html>
