<?php
/**
 * Admin: Notice Board Management
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth(['admin', 'principal', 'vice_principal']);

$error = '';
$flash = get_flash();

// Handle Notices actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_notice') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $audience = trim($_POST['audience'] ?? 'all');
        $category = trim($_POST['category'] ?? 'Circular');

        if (empty($title) || empty($content)) {
            $error = 'Please provide both title and circular text.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO notices (title, content, audience, category, posted_by) VALUES (:t, :c, :a, :cat, :p)");
            $stmt->execute([
                't' => $title,
                'c' => $content,
                'a' => $audience,
                'cat' => $category,
                'p' => $_SESSION['user_id']
            ]);
            set_flash('success', 'New circular broadcasted successfully across chosen ERP channels!');
            header("Location: /admin/notices.php");
            exit();
        }
    } elseif ($action === 'delete_notice') {
        $notice_id = (int)($_POST['notice_id'] ?? 0);
        if ($notice_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM notices WHERE id = :id");
            $stmt->execute(['id' => $notice_id]);
            set_flash('success', 'Notice deleted successfully.');
            header("Location: /admin/notices.php");
            exit();
        }
    }
}

// Fetch notices
$notices = $pdo->query("SELECT * FROM notices ORDER BY id DESC")->fetchAll();
?>

<?php 
$page_title = 'Notice Board & Circulars';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php'; 
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-purple-600 uppercase tracking-wider mb-1">
                <span>Communications &amp; Circulars</span>
                <span>&bull;</span>
                <span class="text-slate-400">Total: <?= count($notices) ?> Announcements</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                School Notice Board
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Broadcast official circulars, examination schedules, event updates, and holiday lists to teachers and students.
            </p>
        </div>

        <button type="button" onclick="openModal('addNoticeModal')" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-purple-700 hover:bg-purple-800 text-white font-semibold text-xs shadow-md shadow-purple-900/10 transition">
            <i class="fa-solid fa-bullhorn mr-2 text-amber-300"></i> Broadcast Notice
        </button>
    </div>

    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-semibold flex items-center justify-between auto-dismiss-alert">
            <div class="flex items-center space-x-2.5">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                <span><?= $flash['message'] ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($notices as $n): ?>
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between hover:shadow-md transition">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold 
                            <?= $n['category'] === 'Exam' ? 'bg-rose-100 text-rose-700' : ($n['category'] === 'Holiday' ? 'bg-amber-100 text-amber-700' : 'bg-purple-100 text-purple-700') ?>">
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

                <div class="pt-4 mt-6 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                        <i class="fa-solid fa-users text-slate-400 mr-1"></i> Target: <span class="text-navy-900"><?= htmlspecialchars($n['audience']) ?></span>
                    </span>

                    <form method="POST" action="notices.php" onsubmit="return confirm('Delete this notice?');">
                        <input type="hidden" name="action" value="delete_notice">
                        <input type="hidden" name="notice_id" value="<?= $n['id'] ?>">
                        <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 text-xs transition" title="Delete notice">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</main>

<!-- ADD NOTICE MODAL -->
<div id="addNoticeModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-bullhorn text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">Broadcast New Notice</h3>
                    <p class="text-xs text-slate-500">Publish update across Bellwether International School portals</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('addNoticeModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="notices.php" class="space-y-4">
            <input type="hidden" name="action" value="create_notice">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Notice Title *</label>
                <input type="text" name="title" required placeholder="e.g. Science Exhibition Registration 2026" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-purple-600">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Audience</label>
                    <select name="audience" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                        <option value="all">Everyone (All)</option>
                        <option value="teachers">Teachers &amp; Staff Only</option>
                        <option value="students">Students &amp; Parents Only</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Category</label>
                    <select name="category" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                        <option value="Circular">General Circular</option>
                        <option value="Academic">Academic Notice</option>
                        <option value="Exam">Examination Schedule</option>
                        <option value="Event">Event / Sports</option>
                        <option value="Holiday">Holiday Notification</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Notice Content *</label>
                <textarea name="content" rows="4" required placeholder="Enter full announcement details here..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-purple-600"></textarea>
            </div>

            <div class="pt-4 flex items-center justify-end space-x-3">
                <button type="button" onclick="closeModal('addNoticeModal')" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-semibold text-xs">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-purple-700 hover:bg-purple-800 text-white font-bold text-xs shadow-md">
                    <i class="fa-solid fa-paper-plane mr-1.5"></i> Publish Notice
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
