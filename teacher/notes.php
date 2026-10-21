<?php
/**
 * Teacher: Study Notes & Course Materials Manager
 * Bellwether International School Nandurbar ERP
 * Allows faculty to upload study materials, chapter PDFs, lecture notes, and update syllabus
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth(['teacher', 'admin', 'principal', 'vice_principal']);

$teacher_user_id = $_SESSION['user_id'];
$flash = get_flash();
$error = '';

// Fetch teacher profile if teacher
$tStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
$tStmt->execute([$teacher_user_id]);
$teacher_profile = $tStmt->fetch();
$teacher_id = $teacher_profile['id'] ?? null;
$assigned_class = $teacher_profile['assigned_class'] ?? null;

// Available classes
$classes = $pdo->query("SELECT DISTINCT class FROM subjects WHERE class IS NOT NULL AND class != '' ORDER BY class ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($classes)) {
    $classes = ['Class 10-A', 'Class 10-B', 'Class 9-A', 'Class 9-B', 'Grade 10', 'Grade 9', 'Grade 8', 'Grade 7'];
}

$selected_class = $_GET['class'] ?? ($assigned_class ?: $classes[0]);
$selected_subject = $_GET['subject'] ?? 'all';

// Fetch subjects for the selected class
$subjStmt = $pdo->prepare("SELECT * FROM subjects WHERE class = ? ORDER BY name ASC");
$subjStmt->execute([$selected_class]);
$class_subjects = $subjStmt->fetchAll();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Upload New Study Note
    if ($action === 'upload_note') {
        $class = trim($_POST['class'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($class) || empty($subject) || empty($title)) {
            set_flash('danger', 'Please provide Class, Subject, and Note Title.');
        } elseif (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            set_flash('danger', 'Please select a valid document or PDF file to upload.');
        } else {
            $tmp = $_FILES['attachment']['tmp_name'];
            $orig_name = $_FILES['attachment']['name'];
            $file_size = $_FILES['attachment']['size'];
            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

            $allowed_exts = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'png', 'jpg', 'jpeg'];
            if (!in_array($ext, $allowed_exts)) {
                set_flash('danger', 'Invalid file type. Allowed files: PDF, DOC, DOCX, PPT, PPTX, XLS, TXT, Images.');
            } elseif ($file_size > 25 * 1024 * 1024) { // 25 MB limit
                set_flash('danger', 'File size exceeds 25 MB limit.');
            } else {
                $dir = __DIR__ . '/../uploads/notes';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }

                $clean_name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', pathinfo($orig_name, PATHINFO_FILENAME));
                $new_filename = 'note_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $dest = $dir . '/' . $new_filename;

                if (move_uploaded_file($tmp, $dest)) {
                    $file_path = '/uploads/notes/' . $new_filename;

                    // Fallback teacher_id if admin uploaded
                    $effective_teacher_id = $teacher_id ?: 1;

                    $insStmt = $pdo->prepare("
                        INSERT INTO study_notes (teacher_id, class, subject, title, description, file_path, file_name, file_size, file_type, academic_year)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '2026-2027')
                    ");
                    $insStmt->execute([$effective_teacher_id, $class, $subject, $title, $description, $file_path, $orig_name, $file_size, $ext]);

                    set_flash('success', "Study note '<strong>" . htmlspecialchars($title) . "</strong>' uploaded successfully! Students can now access it in their LMS.");
                    header("Location: /teacher/notes.php?class=" . urlencode($class) . "&subject=" . urlencode($subject));
                    exit();
                } else {
                    set_flash('danger', 'Failed to save uploaded file on server.');
                }
            }
        }
    }

    // 2. Delete Study Note
    if ($action === 'delete_note') {
        $note_id = (int)($_POST['note_id'] ?? 0);
        if ($note_id > 0) {
            $chk = $pdo->prepare("SELECT file_path FROM study_notes WHERE id = ?");
            $chk->execute([$note_id]);
            $filePath = $chk->fetchColumn();

            if ($filePath) {
                $diskFile = __DIR__ . '/../' . ltrim($filePath, '/');
                if (file_exists($diskFile)) {
                    @unlink($diskFile);
                }
            }

            $pdo->prepare("DELETE FROM study_notes WHERE id = ?")->execute([$note_id]);
            set_flash('success', 'Study note deleted successfully.');
            header("Location: /teacher/notes.php?class=" . urlencode($selected_class) . "&subject=" . urlencode($selected_subject));
            exit();
        }
    }

    // 3. Update Syllabus & Curriculum Information
    if ($action === 'update_syllabus') {
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $syllabus_info = trim($_POST['syllabus_info'] ?? '');

        if ($subject_id > 0) {
            $upd = $pdo->prepare("UPDATE subjects SET syllabus_info = ? WHERE id = ?");
            $upd->execute([$syllabus_info, $subject_id]);
            set_flash('success', 'Syllabus and curriculum content updated! Students will see this in their LMS subject view.');
            header("Location: /teacher/notes.php?class=" . urlencode($selected_class) . "&tab=syllabus");
            exit();
        }
    }
}

// Fetch Notes
$query = "
    SELECT n.*, t.full_name as teacher_name, t.department
    FROM study_notes n
    LEFT JOIN teachers t ON n.teacher_id = t.id
    WHERE n.class = :cls
";
$params = ['cls' => $selected_class];

if ($selected_subject !== 'all' && !empty($selected_subject)) {
    $query .= " AND n.subject = :sub";
    $params['sub'] = $selected_subject;
}

$query .= " ORDER BY n.id DESC";
$nStmt = $pdo->prepare($query);
$nStmt->execute($params);
$notes = $nStmt->fetchAll();

$active_tab = $_GET['tab'] ?? 'notes'; // 'notes' or 'syllabus'

$page_title = 'Study Notes & Course Materials';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 mb-6 no-print">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
                <span>E-Learning &amp; Courseware</span>
                <span>&bull;</span>
                <span class="text-slate-400"><?= htmlspecialchars($selected_class) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Study Notes &amp; Course Materials
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Upload chapter PDFs, formulas, slides, reference files, and manage subject syllabus for students.
            </p>
        </div>

        <div class="flex items-center space-x-2.5">
            <button type="button" onclick="openModal('uploadNoteModal')" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md transition">
                <i class="fa-solid fa-cloud-arrow-up mr-2 text-amber-400"></i> Upload New Study Note
            </button>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl bg-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-50 border border-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-200 text-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-900 text-xs font-semibold flex items-center justify-between shadow-xs auto-dismiss-alert no-print">
            <div class="flex items-center space-x-2.5">
                <i class="fa-solid <?= $flash['type'] === 'danger' ? 'fa-triangle-exclamation' : 'fa-circle-check' ?> text-base"></i>
                <span><?= $flash['message'] ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Class & Subject Filter Bar -->
    <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm mb-6 no-print">
        <form method="GET" action="notes.php" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Standard / Grade</label>
                <select name="class" onchange="this.form.submit()" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= ($selected_class === $c) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?> <?= (!empty($assigned_class) && $assigned_class === $c) ? '⭐ (My Class)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Subject</label>
                <select name="subject" onchange="this.form.submit()" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
                    <option value="all">All Subjects (<?= count($class_subjects) ?> available)</option>
                    <?php foreach ($class_subjects as $s): ?>
                        <option value="<?= htmlspecialchars($s['name']) ?>" <?= ($selected_subject === $s['name']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center space-x-2">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
                <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-filter text-blue-600"></i>
                    <span>Apply Filter</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Tab Navigation: Notes List vs Syllabus Manager -->
    <div class="flex items-center space-x-2 mb-6 border-b border-slate-200 no-print">
        <a href="/teacher/notes.php?class=<?= urlencode($selected_class) ?>&subject=<?= urlencode($selected_subject) ?>&tab=notes" 
           class="px-5 py-3 border-b-2 font-bold text-xs transition flex items-center space-x-2 <?= $active_tab === 'notes' ? 'border-navy-800 text-navy-800 bg-white rounded-t-2xl shadow-xs' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-file-pdf text-rose-500"></i>
            <span>Uploaded Study Notes (<?= count($notes) ?>)</span>
        </a>
        <a href="/teacher/notes.php?class=<?= urlencode($selected_class) ?>&tab=syllabus" 
           class="px-5 py-3 border-b-2 font-bold text-xs transition flex items-center space-x-2 <?= $active_tab === 'syllabus' ? 'border-navy-800 text-navy-800 bg-white rounded-t-2xl shadow-xs' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
            <i class="fa-solid fa-book-bookmark text-amber-500"></i>
            <span>Syllabus &amp; Curriculum Manager</span>
        </a>
    </div>

    <?php if ($active_tab === 'notes'): ?>

        <!-- Notes Grid -->
        <?php if (empty($notes)): ?>
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-12 text-center">
                <div class="w-16 h-16 rounded-3xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl mx-auto mb-3">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <h3 class="text-base font-bold text-slate-800">No study notes uploaded yet</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Upload chapter summaries, question banks, or formula sheets for <?= htmlspecialchars($selected_class) ?>.</p>
                <button type="button" onclick="openModal('uploadNoteModal')" class="mt-4 px-5 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md transition inline-flex items-center space-x-2">
                    <i class="fa-solid fa-cloud-arrow-up text-amber-400"></i>
                    <span>Upload First Note</span>
                </button>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($notes as $note): ?>
                    <?php
                        $ext = strtolower($note['file_type']);
                        $icon = 'fa-file text-slate-500';
                        $badge_color = 'bg-slate-100 text-slate-700';
                        if ($ext === 'pdf') { $icon = 'fa-file-pdf text-rose-600'; $badge_color = 'bg-rose-50 text-rose-700 border border-rose-200'; }
                        elseif (in_array($ext, ['doc', 'docx'])) { $icon = 'fa-file-word text-blue-600'; $badge_color = 'bg-blue-50 text-blue-700 border border-blue-200'; }
                        elseif (in_array($ext, ['ppt', 'pptx'])) { $icon = 'fa-file-powerpoint text-amber-600'; $badge_color = 'bg-amber-50 text-amber-700 border border-amber-200'; }
                        elseif (in_array($ext, ['xls', 'xlsx'])) { $icon = 'fa-file-excel text-emerald-600'; $badge_color = 'bg-emerald-50 text-emerald-700 border border-emerald-200'; }
                        elseif (in_array($ext, ['png', 'jpg', 'jpeg'])) { $icon = 'fa-file-image text-purple-600'; $badge_color = 'bg-purple-50 text-purple-700 border border-purple-200'; }

                        $size_formatted = ($note['file_size'] > 1048576) 
                            ? round($note['file_size'] / 1048576, 2) . ' MB' 
                            : round($note['file_size'] / 1024, 1) . ' KB';
                    ?>
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $badge_color ?>">
                                    <?= htmlspecialchars($note['subject']) ?>
                                </span>
                                <span class="text-[11px] text-slate-400 font-mono">
                                    <?= strtoupper($note['file_type']) ?> &bull; <?= $size_formatted ?>
                                </span>
                            </div>

                            <h3 class="text-base font-bold text-slate-900 font-['Outfit'] leading-snug">
                                <?= htmlspecialchars($note['title']) ?>
                            </h3>

                            <?php if (!empty($note['description'])): ?>
                                <p class="text-xs text-slate-600 mt-2 line-clamp-2">
                                    <?= htmlspecialchars($note['description']) ?>
                                </p>
                            <?php endif; ?>

                            <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
                                <span>Uploaded by: <strong class="text-slate-700"><?= htmlspecialchars($note['teacher_name'] ?: 'Faculty') ?></strong></span>
                                <span><?= date('d M Y', strtotime($note['created_at'])) ?></span>
                            </div>
                        </div>

                        <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                            <a href="<?= htmlspecialchars($note['file_path']) ?>" download="<?= htmlspecialchars($note['file_name']) ?>" target="_blank" 
                               class="flex-1 py-2 px-3 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs text-center transition shadow-xs flex items-center justify-center space-x-1.5">
                                <i class="fa-solid fa-download text-amber-400"></i>
                                <span>Download File</span>
                            </a>

                            <form method="POST" action="notes.php" onsubmit="return confirm('Permanently delete this note?');" class="inline">
                                <input type="hidden" name="action" value="delete_note">
                                <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                                <button type="submit" class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" title="Delete Note">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($active_tab === 'syllabus'): ?>

        <!-- Syllabus & Curriculum Manager -->
        <div class="space-y-6">
            <div class="bg-blue-50 border border-blue-200 rounded-3xl p-5 text-xs text-blue-900 flex items-center space-x-3">
                <i class="fa-solid fa-circle-info text-blue-600 text-xl shrink-0"></i>
                <div>
                    <p class="font-bold text-sm text-blue-950">Curriculum &amp; Syllabus Overview for <?= htmlspecialchars($selected_class) ?></p>
                    <p class="text-blue-800/90 mt-0.5">Faculty can update the chapters, topics, and learning objectives for their allocated subjects. Any updates are reflected immediately in the student LMS.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6">
                <?php foreach ($class_subjects as $sub): ?>
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 mb-6">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">
                                    Code: <?= htmlspecialchars($sub['code'] ?: 'N/A') ?>
                                </span>
                                <h3 class="text-xl font-bold text-slate-900 font-['Outfit'] mt-1">
                                    <?= htmlspecialchars($sub['name']) ?>
                                </h3>
                                <p class="text-xs text-slate-500">Max Marks: <?= (int)$sub['max_marks'] ?> &bull; Passing Criteria: <?= (int)$sub['pass_marks'] ?> marks</p>
                            </div>
                        </div>

                        <form method="POST" action="notes.php" class="space-y-4">
                            <input type="hidden" name="action" value="update_syllabus">
                            <input type="hidden" name="subject_id" value="<?= $sub['id'] ?>">

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                    Syllabus Topics, Unit Breakdown &amp; Learning Objectives
                                </label>
                                <textarea name="syllabus_info" rows="4" 
                                          placeholder="e.g. Unit 1: Real Numbers & Polynomials; Unit 2: Linear Equations in Two Variables; Unit 3: Coordinate Geometry & Triangles..."
                                          class="w-full px-4 py-3 rounded-2xl border border-slate-300 bg-slate-50 text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600"><?= htmlspecialchars($sub['syllabus_info'] ?? '') ?></textarea>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="px-5 py-2 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md transition flex items-center space-x-1.5">
                                    <i class="fa-solid fa-floppy-disk text-amber-400"></i>
                                    <span>Save Syllabus</span>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php endif; ?>

</main>

<!-- Upload New Study Note Modal -->
<div id="uploadNoteModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-cloud-arrow-up text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Upload Study Note</h3>
                    <p class="text-xs text-slate-500">Upload learning document for students</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('uploadNoteModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="notes.php" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="upload_note">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Class / Standard *</label>
                    <select name="class" id="modal_class_select" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-xs font-semibold text-slate-800 focus:outline-none">
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= ($selected_class === $c) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Subject *</label>
                    <select name="subject" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-xs font-semibold text-slate-800 focus:outline-none">
                        <?php foreach ($class_subjects as $s): ?>
                            <option value="<?= htmlspecialchars($s['name']) ?>" <?= ($selected_subject === $s['name']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Note Title *</label>
                <input type="text" name="title" required placeholder="e.g. Chapter 4: Formula Sheet &amp; Important Proofs" 
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Topic / Instructions (Optional)</label>
                <textarea name="description" rows="2" placeholder="Briefly describe what this note covers..." 
                          class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-navy-600"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Select File (PDF, DOCX, PPTX, Images) *</label>
                <input type="file" name="attachment" required accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.png,.jpg,.jpeg"
                       class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                <p class="text-[11px] text-slate-400 mt-1">Max file size: 25 MB.</p>
            </div>

            <div class="pt-4 flex items-center justify-end space-x-3">
                <button type="button" onclick="closeModal('uploadNoteModal')" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-semibold text-xs">Cancel</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md">
                    Upload &amp; Broadcast
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
