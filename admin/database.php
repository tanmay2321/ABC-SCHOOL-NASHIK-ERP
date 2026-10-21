<?php
/**
 * Admin: Database Explorer & Schema Inspector
 * Bellwether International School Nandurbar ERP System
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('admin');

$flash = get_flash();
$page_title = 'Database Explorer';

$db_path = realpath(__DIR__ . '/../database.sqlite');
$db_size_kb = file_exists($db_path) ? round(filesize($db_path) / 1024, 2) : 0;

// List all user tables (exclude sqlite internal tables)
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);

// Selected table to inspect
$selected_table = $_GET['table'] ?? ($tables[0] ?? '');
if (!in_array($selected_table, $tables)) {
    $selected_table = $tables[0] ?? '';
}

// Table statistics
$table_stats = [];
foreach ($tables as $t) {
    $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    $table_stats[$t] = $count;
}

// Fetch columns and rows for selected table
$columns = [];
$rows = [];
$table_schema = '';
if (!empty($selected_table)) {
    $cols_stmt = $pdo->query("PRAGMA table_info(`$selected_table`)");
    $columns = $cols_stmt->fetchAll(PDO::FETCH_ASSOC);

    $schema_stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote($selected_table));
    $table_schema = $schema_stmt->fetchColumn() ?: '';

    $limit = 100;
    $rows_stmt = $pdo->query("SELECT * FROM `$selected_table` LIMIT $limit");
    $rows = $rows_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Quick SQLite integrity check
$integrity = 'OK';
try {
    $integrity = $pdo->query("PRAGMA integrity_check")->fetchColumn();
} catch (Exception $e) {
    $integrity = 'Error: ' . $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Top Header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-blue-600 uppercase tracking-wider mb-1">
                <i class="fa-solid fa-database"></i>
                <span>Database Administration</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                SQLite Database Explorer
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Inspect raw database tables, schema definitions, and row data in real-time.
            </p>
        </div>

        <div class="flex items-center space-x-3">
            <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse mr-2"></span>
                Integrity: <?= htmlspecialchars($integrity) ?>
            </span>
            <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                <i class="fa-regular fa-hard-drive mr-1.5 text-slate-400"></i>
                <?= $db_size_kb ?> KB
            </span>
        </div>
    </div>

    <!-- Storage Location Card -->
    <div class="mb-6 p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/80 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start space-x-3.5">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 text-lg">
                <i class="fa-solid fa-folder-tree"></i>
            </div>
            <div>
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Physical Database File Path</div>
                <code class="text-xs sm:text-sm font-mono font-bold text-navy-950 break-all select-all">
                    <?= htmlspecialchars($db_path) ?>
                </code>
            </div>
        </div>
        <div class="text-xs text-slate-500 sm:text-right shrink-0">
            Engine: <span class="font-bold text-slate-700">SQLite 3</span> (Zero Setup / Serverless)
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        
        <!-- Sidebar: Table List -->
        <div class="lg:col-span-1 bg-white rounded-3xl p-5 border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Database Tables</h3>
                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 font-bold text-slate-600">
                    <?= count($tables) ?>
                </span>
            </div>

            <div class="space-y-1">
                <?php foreach ($tables as $tbl): ?>
                    <a href="?table=<?= urlencode($tbl) ?>" 
                       class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold transition <?= $selected_table === $tbl ? 'bg-navy-900 text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>">
                        <span class="flex items-center truncate mr-2">
                            <i class="fa-solid fa-table text-xs mr-2 <?= $selected_table === $tbl ? 'text-amber-400' : 'text-slate-400' ?>"></i>
                            <span class="truncate"><?= htmlspecialchars($tbl) ?></span>
                        </span>
                        <span class="text-[11px] px-2 py-0.5 rounded-md <?= $selected_table === $tbl ? 'bg-navy-800 text-amber-300' : 'bg-slate-100 text-slate-600' ?>">
                            <?= $table_stats[$tbl] ?? 0 ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- External Viewer Tip Card -->
            <div class="mt-6 pt-5 border-t border-slate-100">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">GUI Viewer Option</h4>
                <p class="text-[11px] text-slate-500 leading-relaxed mb-3">
                    You can also open this SQLite file with the free desktop app <strong>DB Browser for SQLite</strong>.
                </p>
                <a href="https://sqlitebrowser.org/dl/" target="_blank" rel="noopener noreferrer" 
                   class="inline-flex items-center justify-center w-full py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                    <i class="fa-solid fa-arrow-up-right-from-square mr-1.5 text-xs text-blue-600"></i>
                    Get DB Browser
                </a>
            </div>
        </div>

        <!-- Table Data & Schema Viewer -->
        <div class="lg:col-span-3 space-y-6">
            
            <!-- Table Header Card -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 mb-4 gap-2">
                    <div>
                        <span class="text-[11px] font-bold text-amber-600 uppercase tracking-wider">Active Table</span>
                        <h2 class="text-xl font-bold text-slate-900 font-['Outfit'] flex items-center">
                            <i class="fa-solid fa-table-columns mr-2 text-navy-800"></i>
                            <?= htmlspecialchars($selected_table) ?>
                        </h2>
                    </div>
                    <div class="flex items-center space-x-2 text-xs">
                        <span class="px-3 py-1 rounded-full bg-blue-50 text-blue-700 font-bold border border-blue-200">
                            <?= count($columns) ?> Columns
                        </span>
                        <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 font-bold border border-slate-200">
                            <?= $table_stats[$selected_table] ?? 0 ?> Rows
                        </span>
                    </div>
                </div>

                <!-- Table Rows -->
                <?php if (empty($rows)): ?>
                    <div class="text-center py-12 bg-slate-50/70 rounded-2xl border border-dashed border-slate-200">
                        <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3 text-lg">
                            <i class="fa-solid fa-inbox"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700">This table is currently empty</h4>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">
                            No records have been inserted yet. As students register or teachers are added, records will populate here.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                                <tr>
                                    <?php foreach ($columns as $col): ?>
                                        <th class="py-3 px-3.5 whitespace-nowrap">
                                            <?= htmlspecialchars($col['name']) ?>
                                            <span class="text-[9px] text-slate-400 font-normal ml-0.5">(<?= htmlspecialchars($col['type']) ?>)</span>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-mono text-[11px]">
                                <?php foreach ($rows as $row): ?>
                                    <tr class="hover:bg-blue-50/40 transition">
                                        <?php foreach ($columns as $col): ?>
                                            <?php 
                                                $val = $row[$col['name']] ?? null; 
                                                $is_null = is_null($val);
                                                $is_pass = ($col['name'] === 'password');
                                            ?>
                                            <td class="py-2.5 px-3.5 whitespace-nowrap text-slate-700">
                                                <?php if ($is_null): ?>
                                                    <span class="text-slate-400 italic">NULL</span>
                                                <?php elseif ($is_pass): ?>
                                                    <span class="text-slate-400 select-none">•••••••••••• (Hashed)</span>
                                                <?php else: ?>
                                                    <span class="select-all"><?= htmlspecialchars((string)$val) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (($table_stats[$selected_table] ?? 0) > $limit): ?>
                        <p class="text-[11px] text-slate-400 mt-2 text-right">
                            Showing first <?= $limit ?> records.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Column Definition Summary -->
                <div class="mt-6 pt-5 border-t border-slate-100">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Columns &amp; Types</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                        <?php foreach ($columns as $col): ?>
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/70 text-xs">
                                <div class="font-bold text-slate-800 truncate"><?= htmlspecialchars($col['name']) ?></div>
                                <div class="text-[10px] text-slate-500 flex items-center justify-between mt-0.5">
                                    <span><?= htmlspecialchars($col['type'] ?: 'TEXT') ?></span>
                                    <?php if ($col['pk']): ?>
                                        <span class="text-amber-600 font-bold">PK</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- SQL Schema Definition -->
                <?php if (!empty($table_schema)): ?>
                    <div class="mt-6 pt-5 border-t border-slate-100">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">CREATE TABLE Statement</h3>
                        <pre class="p-4 rounded-xl bg-slate-900 text-slate-200 text-xs font-mono overflow-x-auto leading-relaxed"><?= htmlspecialchars($table_schema) ?></pre>
                    </div>
                <?php endif; ?>

            </div>

        </div>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
