<?php
/**
 * Shared Header for ABC School Nashik ERP
 */
require_once __DIR__ . '/auth.php';

$current_user = current_user();
$flash = get_flash();
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = $page_title ?? 'ABC School Nashik ERP';

$school_logo = get_setting($pdo, 'school_logo', '');
$school_name = get_setting($pdo, 'school_name', 'ABC School Nashik');

// Pending approvals count for badge (if admin)
$pending_count = 0;
if ($current_user && $current_user['role'] === 'admin') {
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
    <title><?= htmlspecialchars($page_title) ?> - ABC School Nashik ERP</title>
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
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <!-- Left: Logo & Mobile Toggle -->
                <div class="flex items-center space-x-3">
                    <button id="mobile-sidebar-toggle" type="button" class="lg:hidden p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 focus:outline-none">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                    
                    <a href="/" class="flex items-center space-x-3 group">
                        <?php if (!empty($school_logo) && file_exists(__DIR__ . '/../' . ltrim($school_logo, '/'))): ?>
                            <img src="<?= htmlspecialchars($school_logo) ?>" alt="School Logo" class="w-10 h-10 object-contain rounded-xl shadow-md p-1 bg-white border border-slate-200 group-hover:scale-105 transition-transform">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-navy-900 to-navy-600 flex items-center justify-center text-white shadow-md shadow-blue-500/20 group-hover:scale-105 transition-transform">
                                <i class="fa-solid fa-school text-lg text-amber-400"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <span class="text-base sm:text-lg font-bold tracking-tight text-navy-950 font-['Outfit'] block leading-tight truncate max-w-[180px] sm:max-w-xs"><?= htmlspecialchars($school_name) ?></span>
                            <span class="text-[10px] sm:text-xs font-semibold tracking-wider text-amber-600 uppercase block">Nashik • ERP Portal</span>
                        </div>
                    </a>
                </div>

                <!-- Right: Profile & Action Menu -->
                <div class="flex items-center space-x-4">
                    <!-- Quick notification badge -->
                    <?php if ($current_user && $current_user['role'] === 'admin' && $pending_count > 0): ?>
                        <a href="/admin/students.php?tab=pending" class="relative p-2 text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-xl transition-all" title="<?= $pending_count ?> Students Pending Approval">
                            <i class="fa-solid fa-user-clock text-lg"></i>
                            <span class="absolute top-1 right-1 flex h-4 w-4">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-4 w-4 bg-amber-500 text-[10px] font-bold text-white items-center justify-center"><?= $pending_count ?></span>
                            </span>
                        </a>
                    <?php endif; ?>

                    <!-- Role Badge & User Information -->
                    <?php if ($current_user): ?>
                        <div class="flex items-center space-x-3 pl-2 border-l border-slate-200">
                            <div class="hidden sm:block text-right">
                                <p class="text-sm font-semibold text-slate-900 leading-tight"><?= htmlspecialchars($current_user['name']) ?></p>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                                    <?= $current_user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : ($current_user['role'] === 'teacher' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800') ?>">
                                    <i class="fa-solid <?= $current_user['role'] === 'admin' ? 'fa-shield-halved' : ($current_user['role'] === 'teacher' ? 'fa-chalkboard-user' : 'fa-user-graduate') ?> text-[10px] mr-1"></i>
                                    <?= ucfirst($current_user['role']) ?>
                                </span>
                            </div>
                            
                            <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-navy-800 to-blue-600 text-white font-bold flex items-center justify-center text-sm shadow-sm">
                                <?= strtoupper(substr($current_user['name'], 0, 1)) ?>
                            </div>

                            <a href="/logout.php" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition" title="Logout">
                                <i class="fa-solid fa-arrow-right-from-bracket text-base"></i>
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
