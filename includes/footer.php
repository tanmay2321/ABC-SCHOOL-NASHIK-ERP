<?php
/**
 * Shared Footer for ABC School ERP
 */
$footer_school_name = isset($pdo) ? get_setting($pdo, 'school_name', 'ABC Senior Secondary School') : 'ABC Senior Secondary School';
$footer_school_city = isset($pdo) ? get_setting($pdo, 'school_city', 'Nashik') : 'Nashik';
?>
    </div> <!-- Close flex-1 flex overflow-hidden from header -->

    <!-- Mobile-Responsive Footer / Bottom Info -->
    <footer class="bg-white border-t border-slate-200 py-3 px-4 sm:px-6 lg:px-8 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2 no-print">
        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-1.5 text-center sm:text-left min-w-0">
            <span class="font-bold text-slate-800 break-words"><?= htmlspecialchars($footer_school_name) ?></span>
            <span class="text-slate-300 hidden sm:inline">&bull;</span>
            <span class="text-slate-500"><?= htmlspecialchars($footer_school_city) ?> Campus</span>
            <span class="text-slate-300 hidden sm:inline">&bull;</span>
            <span class="text-slate-400">AY 2026-2027</span>
        </div>
        <div class="flex items-center space-x-3 shrink-0">
            <span class="inline-flex items-center text-emerald-600 font-medium text-[11px]">
                <span class="w-2 h-2 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span> System Online
            </span>
            <span class="text-slate-400 text-[11px]"><?= htmlspecialchars($footer_school_city) ?>, India</span>
        </div>
    </footer>

    <!-- App Scripts -->
    <script src="/assets/js/app.js"></script>
</body>
</html>
