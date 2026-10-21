<?php
/**
 * Shared Header for Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/auth.php';

$current_user = current_user();
$flash = get_flash();
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = $page_title ?? 'Bellwether International School Nandurbar ERP';

$school_logo = get_setting($pdo, 'school_logo', '');
$school_name = get_setting($pdo, 'school_name', 'BELLWETHER INTERNATIONAL SCHOOL NANDURBAR');
$school_city = get_setting($pdo, 'school_city', 'Nandurbar');

// Pending approvals count for badge (if admin or principal)
$pending_count = 0;
if ($current_user && in_array($current_user['role'], ['admin', 'principal'])) {
    try {
        $pending_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'pending'")->fetchColumn();
    } catch(Exception $e) {
        $pending_count = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($school_name) ?></title>
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
    <!-- FontAwesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body class="h-full flex flex-col font-sans text-slate-800 antialiased bg-slate-50">

    <!-- Top Navigation Bar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm no-print">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <!-- Left: Logo & Mobile Toggle -->
                <div class="flex items-center space-x-3">
                    <button id="mobile-sidebar-toggle" type="button" class="lg:hidden p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                    
                    <a href="/" class="flex items-center space-x-2 sm:space-x-3 group min-w-0">
                        <?php if (!empty($school_logo) && file_exists(__DIR__ . '/../' . ltrim($school_logo, '/'))): ?>
                            <img src="<?= htmlspecialchars($school_logo) ?>" alt="School Logo" class="w-9 h-9 sm:w-10 sm:h-10 object-contain rounded-xl shadow-md p-1 bg-white border border-slate-200 group-hover:scale-105 transition-transform shrink-0">
                        <?php else: ?>
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-gradient-to-tr from-navy-900 to-navy-600 flex items-center justify-center text-white shadow-md shadow-blue-500/20 group-hover:scale-105 transition-transform shrink-0">
                                <i class="fa-solid fa-school text-base sm:text-lg text-amber-400"></i>
                            </div>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <span class="text-xs sm:text-sm md:text-base font-bold tracking-tight text-navy-950 font-['Outfit'] block leading-tight truncate max-w-[130px] xs:max-w-[180px] sm:max-w-xs md:max-w-md lg:max-w-xl" title="<?= htmlspecialchars($school_name) ?>"><?= htmlspecialchars($school_name) ?></span>
                            <span class="text-[9px] sm:text-[11px] font-semibold tracking-wider text-amber-600 uppercase block truncate"><?= htmlspecialchars($school_city) ?> • ERP Portal</span>
                        </div>
                    </a>
                </div>

                <!-- Right: Profile & Action Menu -->
                <div class="flex items-center space-x-2 sm:space-x-4 shrink-0">
                    <!-- Quick notification badge -->
                    <?php if ($current_user && in_array($current_user['role'], ['admin', 'principal']) && $pending_count > 0): ?>
                        <a href="/admin/students.php?tab=pending" class="relative p-2 text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-xl transition-all" title="<?= $pending_count ?> Students Pending Approval">
                            <i class="fa-solid fa-user-clock text-base sm:text-lg"></i>
                            <span class="absolute top-1 right-1 flex h-4 w-4">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-4 w-4 bg-amber-500 text-[10px] font-bold text-white items-center justify-center"><?= $pending_count ?></span>
                            </span>
                        </a>
                    <?php endif; ?>

                    <!-- Role Badge & User Information -->
                    <?php if ($current_user): ?>
                        <?php
                            $role_badge = 'bg-slate-100 text-slate-800';
                            $role_icon = 'fa-user';
                            $role_label = ucfirst($current_user['role']);
                            if ($current_user['role'] === 'admin') {
                                $role_badge = 'bg-purple-100 text-purple-800 border border-purple-200';
                                $role_icon = 'fa-shield-halved';
                                $role_label = 'Admin';
                            } elseif ($current_user['role'] === 'principal') {
                                $role_badge = 'bg-amber-100 text-amber-900 border border-amber-300';
                                $role_icon = 'fa-award';
                                $role_label = 'Principal';
                            } elseif ($current_user['role'] === 'vice_principal') {
                                $role_badge = 'bg-teal-100 text-teal-800 border border-teal-200';
                                $role_icon = 'fa-user-tie';
                                $role_label = 'Vice Principal';
                            } elseif ($current_user['role'] === 'teacher') {
                                $role_badge = 'bg-blue-100 text-blue-800 border border-blue-200';
                                $role_icon = 'fa-chalkboard-user';
                                $role_label = 'Teacher';
                            } elseif ($current_user['role'] === 'student') {
                                $role_badge = 'bg-emerald-100 text-emerald-800 border border-emerald-200';
                                $role_icon = 'fa-user-graduate';
                                $role_label = 'Student';
                            }

                            $profile_url = '#';
                            if ($current_user['role'] === 'teacher') $profile_url = '/teacher/profile.php';
                            elseif ($current_user['role'] === 'student') $profile_url = '/student/profile.php';
                            elseif (in_array($current_user['role'], ['admin', 'principal', 'vice_principal'])) $profile_url = '/admin/leadership.php';

                            $user_photo = $current_user['photo'] ?? null;
                            $has_photo = !empty($user_photo) && file_exists(__DIR__ . '/../' . ltrim($user_photo, '/'));
                        ?>
                        <div class="flex items-center space-x-2 sm:space-x-3 pl-2 border-l border-slate-200">
                            <a href="<?= $profile_url ?>" class="hidden md:block text-right min-w-0 max-w-[150px] group" title="View Profile">
                                <p class="text-xs font-bold text-slate-900 group-hover:text-blue-600 transition leading-tight truncate" title="<?= htmlspecialchars($current_user['name']) ?>"><?= htmlspecialchars($current_user['name']) ?></p>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold <?= $role_badge ?> mt-0.5">
                                    <i class="fa-solid <?= $role_icon ?> text-[9px] mr-1"></i>
                                    <?= $role_label ?>
                                </span>
                            </a>
                            
                            <a href="<?= $profile_url ?>" class="block group shrink-0" title="My Profile (<?= htmlspecialchars($current_user['name']) ?>)">
                                <?php if ($has_photo): ?>
                                    <img src="<?= htmlspecialchars($user_photo) ?>" alt="Profile" 
                                         class="w-9 h-9 sm:w-10 sm:h-10 rounded-full object-cover border-2 border-amber-400 shadow-sm group-hover:scale-105 group-hover:ring-2 group-hover:ring-blue-500 transition-all">
                                <?php else: ?>
                                    <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-gradient-to-tr from-navy-800 to-blue-600 text-white font-bold flex items-center justify-center text-xs sm:text-sm shadow-sm group-hover:scale-105 group-hover:ring-2 group-hover:ring-blue-500 transition-all">
                                        <?= strtoupper(substr($current_user['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </a>

                            <a href="/logout.php" class="p-1.5 sm:p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition" title="Logout">
                                <i class="fa-solid fa-arrow-right-from-bracket text-sm sm:text-base"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="/login.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-white bg-navy-700 hover:bg-navy-800 shadow-sm transition">
                            <i class="fa-solid fa-right-to-bracket mr-2"></i> Login
                        </a>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </header>

    <!-- Main Container: Sidebar + Content -->
    <div class="flex-1 flex overflow-hidden">
        <!-- Sidebar overlay for mobile -->
        <div id="sidebar-overlay" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-30 lg:hidden"></div>
