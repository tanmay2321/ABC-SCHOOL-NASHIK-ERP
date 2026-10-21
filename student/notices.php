<?php
/**
 * Student: Circulars & Notices
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('student');

$notices = $pdo->query("SELECT * FROM notices WHERE audience IN ('all', 'students') ORDER BY id DESC")->fetchAll();
$page_title = 'Student Circulars & Notices';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <div class="mb-8">
        <div class="flex items-center space-x-2 text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">
            <span>Student &amp; Parent Notice Board</span>
            <span>&bull;</span>
            <span class="text-slate-400">Total: <?= count($notices) ?> Active Circulars</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
            Circulars &amp; Announcements
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
            Official announcements, examination schedules, event updates, and holiday circulars.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($notices as $n): ?>
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between hover:shadow-md transition">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold 
                            <?= $n['category'] === 'Exam' ? 'bg-rose-100 text-rose-700' : ($n['category'] === 'Holiday' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-800') ?>">
                            <?= htmlspecialchars($n['category']) ?>
                        </span>
                        <span class="text-[11px] text-slate-400">
                            <i class="fa-regular fa-calendar mr-1"></i>
                            <?= date('d M Y', strtotime($n['created_at'])) ?>
                        </span>
                    </div>

                    <h3 class="text-sm font-bold text-slate-900 mb-2 leading-snug"><?= htmlspecialchars($n['title']) ?></h3>
                    <p class="text-xs text-slate-600 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($n['content']) ?></p>
                </div>

                <div class="pt-4 mt-6 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                    <span class="text-emerald-700 font-medium"><i class="fa-solid fa-circle-check text-emerald-500 mr-1"></i> Verified by School Office</span>
                    <i class="fa-solid fa-bullhorn text-slate-300"></i>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
