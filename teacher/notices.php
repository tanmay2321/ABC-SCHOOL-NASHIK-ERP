<?php
/**
 * Teacher: Notices & Circulars
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('teacher');

$notices = $pdo->query("SELECT * FROM notices WHERE audience IN ('all', 'teachers') ORDER BY id DESC")->fetchAll();
$page_title = 'Faculty Circulars & Notices';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <div class="mb-8">
        <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
            <span>Staff Communications</span>
            <span>&bull;</span>
            <span class="text-slate-400">Total: <?= count($notices) ?> Active Circulars</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
            School Notices &amp; Circulars
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
            Official announcements issued by Bellwether International School Administration for faculty and staff.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($notices as $n): ?>
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold 
                            <?= $n['category'] === 'Academic' ? 'bg-blue-100 text-blue-800' : ($n['category'] === 'Holiday' ? 'bg-amber-100 text-amber-800' : 'bg-purple-100 text-purple-800') ?>">
                            <?= htmlspecialchars($n['category']) ?>
                        </span>
                        <span class="text-[11px] text-slate-400">
                            <i class="fa-regular fa-clock mr-1"></i>
                            <?= date('d M Y', strtotime($n['created_at'])) ?>
                        </span>
                    </div>

                    <h3 class="text-sm font-bold text-slate-900 mb-2 leading-snug"><?= htmlspecialchars($n['title']) ?></h3>
                    <p class="text-xs text-slate-600 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($n['content']) ?></p>
                </div>

                <div class="pt-4 mt-6 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                    <span>Target: <strong class="text-slate-700 uppercase"><?= htmlspecialchars($n['audience']) ?></strong></span>
                    <i class="fa-solid fa-shield-check text-emerald-500" title="Verified by Administration"></i>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
