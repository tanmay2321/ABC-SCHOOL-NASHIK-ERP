<?php
/**
 * Admin: System Settings
 * ABC School Nashik ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('admin');

$flash = get_flash();
$page_title = 'ERP System Settings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <div class="mb-8">
        <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
            <span>Configuration &amp; Administration</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
            School ERP Settings
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
            System status, institutional profiles, and access control settings for ABC School Nashik.
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- School Profile Details -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-sm space-y-6">
            <h3 class="text-base font-bold text-slate-900 font-['Outfit'] border-b border-slate-100 pb-3">
                Institution Profile
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Institution Name</label>
                    <input type="text" readonly value="ABC Senior Secondary School Nashik" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 font-medium">
                </div>
                <div>
                    <label class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Affiliation</label>
                    <input type="text" readonly value="CBSE Board Affiliation #113042" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 font-medium">
                </div>
                <div>
                    <label class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Campus Location</label>
                    <input type="text" readonly value="Gangapur Road, Nashik, Maharashtra 422013" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 font-medium">
                </div>
                <div>
                    <label class="block font-bold text-slate-500 uppercase tracking-wider mb-1">Academic Session</label>
                    <input type="text" readonly value="2026 - 2027 (Term 1 &amp; Term 2)" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 font-medium">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Admission &amp; Approval Policy</h4>
                <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-1">
                    <p class="font-bold"><i class="fa-solid fa-lock mr-1.5"></i> Strict Student Approval Policy Active</p>
                    <p class="text-slate-600">Students registering through the public portal are placed into <code>pending</code> status and cannot log in until approved by the school administrator.</p>
                </div>
            </div>
        </div>

        <!-- System & Database Information -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900 font-['Outfit'] border-b border-slate-100 pb-3 mb-4">
                    System Health
                </h3>

                <div class="space-y-4 text-xs">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-slate-500 font-medium">Backend Engine</span>
                        <span class="font-bold text-navy-900">PHP 8.2 (Built-in PDO)</span>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-slate-500 font-medium">Database</span>
                        <span class="font-bold text-navy-900">SQLite 3 (Zero Setup)</span>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-slate-500 font-medium">Authentication</span>
                        <span class="font-bold text-emerald-600">BCrypt Password Hashing</span>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-slate-500 font-medium">Security Gate</span>
                        <span class="font-bold text-amber-600">Role-Based Access Control</span>
                    </div>
                </div>
            </div>

            <div class="pt-6 mt-6 border-t border-slate-100">
                <a href="/logout.php" class="w-full py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs flex items-center justify-center transition">
                    <i class="fa-solid fa-power-off mr-2"></i> Log Out of Admin
                </a>
            </div>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
