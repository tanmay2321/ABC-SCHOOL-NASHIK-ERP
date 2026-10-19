<?php
/**
 * Admin: Fee Accounting & Invoice Management
 * ABC School Nashik ERP
 * Full administrative control over school fee structures, collections, invoices, and receipts
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('admin');

$error = '';
$flash = get_flash();
$active_tab = $_GET['status'] ?? 'all';

// Handle Form Submissions: Create Invoice, Record Payment, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_invoice') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        $title = trim($_POST['title'] ?? 'Annual Academic Fee');
        $tuition = (float)($_POST['tuition_fee'] ?? 0);
        $lab = (float)($_POST['lab_fee'] ?? 0);
        $library = (float)($_POST['library_fee'] ?? 0);
        $sports = (float)($_POST['sports_fee'] ?? 0);
        $exam = (float)($_POST['exam_fee'] ?? 0);
        $due_date = trim($_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days')));

        $total = $tuition + $lab + $library + $sports + $exam;

        if ($student_id <= 0 || $total <= 0) {
            $error = 'Please select a student and provide valid fee amounts.';
        } else {
            $inv_no = 'INV-2026-' . rand(100, 999);
            $stmt = $pdo->prepare("
                INSERT INTO fee_invoices (invoice_no, student_id, title, tuition_fee, lab_fee, library_fee, sports_fee, exam_fee, total_amount, due_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Unpaid')
            ");
            $stmt->execute([$inv_no, $student_id, $title, $tuition, $lab, $library, $sports, $exam, $total, $due_date]);
            set_flash('success', "Fee Invoice <strong>{$inv_no}</strong> for ₹" . number_format($total) . " created successfully!");
            header("Location: /admin/fees.php");
            exit();
        }
    } elseif ($action === 'record_payment') {
        $invoice_id = (int)($_POST['invoice_id'] ?? 0);
        $amount_paid = (float)($_POST['amount_paid'] ?? 0);
        $payment_mode = trim($_POST['payment_mode'] ?? 'UPI');
        $transaction_ref = trim($_POST['transaction_ref'] ?? '');

        if ($invoice_id <= 0 || $amount_paid <= 0) {
            $error = 'Please enter a valid payment amount.';
        } else {
            // Get invoice info
            $invStmt = $pdo->prepare("SELECT * FROM fee_invoices WHERE id = ?");
            $invStmt->execute([$invoice_id]);
            $inv = $invStmt->fetch();

            if ($inv) {
                // Calculate total already paid
                $paidStmt = $pdo->prepare("SELECT SUM(amount_paid) FROM fee_payments WHERE invoice_id = ?");
                $paidStmt->execute([$invoice_id]);
                $already_paid = (float)$paidStmt->fetchColumn();

                $new_total_paid = $already_paid + $amount_paid;
                $new_status = ($new_total_paid >= $inv['total_amount']) ? 'Paid' : 'Partial';

                $receipt_no = 'REC-2026-' . rand(1000, 9999);
                $pStmt = $pdo->prepare("
                    INSERT INTO fee_payments (receipt_no, invoice_id, student_id, amount_paid, payment_mode, transaction_ref, payment_date, received_by)
                    VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)
                ");
                $pStmt->execute([$receipt_no, $invoice_id, $inv['student_id'], $amount_paid, $payment_mode, $transaction_ref, $_SESSION['user_id']]);

                // Update invoice status
                $uStmt = $pdo->prepare("UPDATE fee_invoices SET status = ? WHERE id = ?");
                $uStmt->execute([$new_status, $invoice_id]);

                set_flash('success', "Payment of ₹" . number_format($amount_paid) . " recorded successfully! Receipt No: <strong>{$receipt_no}</strong>.");
                header("Location: /admin/fees.php");
                exit();
            }
        }
    } elseif ($action === 'delete_invoice') {
        $inv_id = (int)($_POST['invoice_id'] ?? 0);
        if ($inv_id > 0) {
            $pdo->prepare("DELETE FROM fee_invoices WHERE id = ?")->execute([$inv_id]);
            set_flash('success', 'Fee invoice and related transaction records deleted.');
            header("Location: /admin/fees.php");
            exit();
        }
    }
}

// Stats calculation
$total_invoiced = (float)$pdo->query("SELECT SUM(total_amount) FROM fee_invoices")->fetchColumn();
$total_collected = (float)$pdo->query("SELECT SUM(amount_paid) FROM fee_payments")->fetchColumn();
$total_pending = max(0, $total_invoiced - $total_collected);
$collection_rate = ($total_invoiced > 0) ? round(($total_collected / $total_invoiced) * 100, 1) : 0;

// Fetch Invoices based on filter
$where = "";
$params = [];
if ($active_tab === 'paid') {
    $where = "WHERE f.status = 'Paid'";
} elseif ($active_tab === 'partial') {
    $where = "WHERE f.status = 'Partial'";
} elseif ($active_tab === 'unpaid') {
    $where = "WHERE f.status = 'Unpaid'";
}

$invoices = $pdo->query("
    SELECT f.*, s.full_name as student_name, s.student_code, s.class, s.roll_no,
           IFNULL((SELECT SUM(amount_paid) FROM fee_payments WHERE invoice_id = f.id), 0) as paid_amount
    FROM fee_invoices f
    JOIN students s ON f.student_id = s.id
    {$where}
    ORDER BY f.id DESC
")->fetchAll();

// Fetch approved students for new invoice dropdown
$students = $pdo->query("
    SELECT s.id, s.full_name, s.class, s.roll_no, s.student_code
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE u.status = 'approved'
    ORDER BY s.class ASC, s.full_name ASC
")->fetchAll();

// Recent payment transactions
$recent_payments = $pdo->query("
    SELECT p.*, s.full_name as student_name, s.student_code, f.invoice_no
    FROM fee_payments p
    JOIN students s ON p.student_id = s.id
    JOIN fee_invoices f ON p.invoice_id = f.id
    ORDER BY p.id DESC LIMIT 6
")->fetchAll();

$page_title = 'Fee Accounting & Invoices';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">
                <span>Accounts &amp; Finance Management</span>
                <span>&bull;</span>
                <span class="text-slate-400">Academic Year 2026-27</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Fee Billing &amp; Collection Register
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Full administrative access: generate student fee bills, record offline/online collections, and issue receipts.
            </p>
        </div>

        <div class="flex items-center space-x-3">
            <button type="button" onclick="openModal('createInvoiceModal')" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-semibold text-xs shadow-md shadow-navy-900/10 transition">
                <i class="fa-solid fa-file-invoice-dollar mr-2 text-amber-400"></i> Generate Fee Invoice
            </button>
            <button type="button" onclick="openModal('recordPaymentModal')" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-md shadow-emerald-700/10 transition">
                <i class="fa-solid fa-hand-holding-dollar mr-2"></i> Record Payment
            </button>
        </div>
    </div>

    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl bg-<?= $flash['type'] === 'success' ? 'emerald' : 'amber' ?>-50 border border-<?= $flash['type'] === 'success' ? 'emerald' : 'amber' ?>-200 text-<?= $flash['type'] === 'success' ? 'emerald' : 'amber' ?>-900 text-xs font-semibold flex items-center justify-between auto-dismiss-alert">
            <div class="flex items-center space-x-2.5">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-amber-600' ?> text-base"></i>
                <span><?= $flash['message'] ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center space-x-2.5">
            <i class="fa-solid fa-triangle-exclamation text-base"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- KPI Accounting Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Total Invoiced</span>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="fa-solid fa-file-invoice text-lg"></i>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit'] mt-3">₹<?= number_format($total_invoiced) ?></p>
            <p class="text-[11px] text-slate-400 mt-1">Across all student classes</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-emerald-600 tracking-wider">Total Collected</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="fa-solid fa-circle-check text-lg"></i>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-emerald-600 font-['Outfit'] mt-3">₹<?= number_format($total_collected) ?></p>
            <p class="text-[11px] text-emerald-700 font-medium mt-1"><?= $collection_rate ?>% collection efficiency</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border <?= $total_pending > 0 ? 'border-amber-300 bg-amber-50/20' : 'border-slate-200' ?> shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-amber-600 tracking-wider">Outstanding Dues</span>
                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center">
                    <i class="fa-solid fa-clock text-lg"></i>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-amber-600 font-['Outfit'] mt-3">₹<?= number_format($total_pending) ?></p>
            <p class="text-[11px] text-amber-800 font-medium mt-1">Pending parent payments</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-purple-600 tracking-wider">Receipts Issued</span>
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <i class="fa-solid fa-receipt text-lg"></i>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit'] mt-3"><?= count($recent_payments) ?></p>
            <p class="text-[11px] text-purple-600 font-medium mt-1">Digital payment receipts</p>
        </div>

    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm mb-6 flex flex-col md:flex-row items-center justify-between gap-4">
        
        <div class="flex items-center space-x-2 overflow-x-auto w-full md:w-auto">
            <a href="/admin/fees.php?status=all" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $active_tab === 'all' ? 'bg-navy-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                All Invoices
            </a>
            <a href="/admin/fees.php?status=unpaid" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $active_tab === 'unpaid' ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                Unpaid Dues
            </a>
            <a href="/admin/fees.php?status=partial" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $active_tab === 'partial' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                Partially Paid
            </a>
            <a href="/admin/fees.php?status=paid" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $active_tab === 'paid' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                Fully Cleared
            </a>
        </div>

        <div class="relative w-full md:w-72">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
            </div>
            <input type="text" id="feeSearch" onkeyup="filterTable('feeSearch', 'feesTable')" placeholder="Search student, invoice, class..." 
                   class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden mb-8">
        <div class="overflow-x-auto">
            <table id="feesTable" class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3 px-4 sm:px-6">Invoice #</th>
                        <th class="py-3 px-4">Student Info</th>
                        <th class="py-3 px-4">Fee Description</th>
                        <th class="py-3 px-4">Total Amount</th>
                        <th class="py-3 px-4">Paid Amount</th>
                        <th class="py-3 px-4">Balance Due</th>
                        <th class="py-3 px-4">Due Date</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 sm:px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="9" class="py-8 text-center text-slate-500">
                                No fee invoices found under this filter.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($invoices as $inv): ?>
                        <?php 
                            $balance = max(0, $inv['total_amount'] - $inv['paid_amount']);
                        ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-4 sm:px-6 font-mono font-bold text-blue-700">
                                <?= htmlspecialchars($inv['invoice_no']) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900"><?= htmlspecialchars($inv['student_name']) ?></div>
                                <div class="text-[11px] text-slate-500"><?= htmlspecialchars($inv['class']) ?> &bull; Roll: <?= htmlspecialchars($inv['roll_no'] ?: 'N/A') ?></div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-700">
                                <div class="font-medium"><?= htmlspecialchars($inv['title']) ?></div>
                                <div class="text-[10px] text-slate-400">Tuition: ₹<?= number_format($inv['tuition_fee']) ?> &bull; Lab: ₹<?= number_format($inv['lab_fee']) ?></div>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-900">
                                ₹<?= number_format($inv['total_amount']) ?>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-emerald-600">
                                ₹<?= number_format($inv['paid_amount']) ?>
                            </td>
                            <td class="py-3.5 px-4 font-bold <?= $balance > 0 ? 'text-rose-600' : 'text-slate-400' ?>">
                                ₹<?= number_format($balance) ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-500">
                                <?= date('d M Y', strtotime($inv['due_date'])) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <?php if ($inv['status'] === 'Paid'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-check text-[10px] mr-1"></i> Fully Paid
                                    </span>
                                <?php elseif ($inv['status'] === 'Partial'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">
                                        <i class="fa-solid fa-clock-rotate-left text-[10px] mr-1"></i> Partial
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800">
                                        <i class="fa-solid fa-circle-exclamation text-[10px] mr-1"></i> Unpaid
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 sm:px-6 text-right">
                                <div class="flex items-center justify-end space-x-1.5">
                                    <?php if ($balance > 0): ?>
                                        <button type="button" 
                                                onclick='quickPayModal(<?= json_encode($inv) ?>)'
                                                class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-bold text-xs transition" title="Record Payment">
                                            <i class="fa-solid fa-plus-circle mr-1"></i> Pay
                                        </button>
                                    <?php endif; ?>

                                    <form method="POST" action="fees.php" onsubmit="return confirm('Delete this fee invoice?');">
                                        <input type="hidden" name="action" value="delete_invoice">
                                        <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                        <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg text-xs transition" title="Delete Invoice">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Payments Log -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Recent Payment Receipts Audit</h3>
            <span class="text-xs text-slate-400">Verified by Administration</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($recent_payments as $rp): ?>
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 text-xs space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-mono font-bold text-blue-700"><?= htmlspecialchars($rp['receipt_no']) ?></span>
                        <span class="px-2 py-0.5 rounded font-semibold bg-emerald-100 text-emerald-800"><?= htmlspecialchars($rp['payment_mode']) ?></span>
                    </div>
                    <div>
                        <div class="font-bold text-slate-900"><?= htmlspecialchars($rp['student_name']) ?></div>
                        <div class="text-[11px] text-slate-500">Ref: <?= htmlspecialchars($rp['transaction_ref'] ?: 'N/A') ?></div>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-slate-200">
                        <span class="text-slate-400"><?= date('d M Y, h:i A', strtotime($rp['payment_date'])) ?></span>
                        <span class="font-bold text-slate-900 text-sm">₹<?= number_format($rp['amount_paid']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<!-- CREATE INVOICE MODAL -->
<div id="createInvoiceModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-file-invoice text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">Generate Student Fee Invoice</h3>
                    <p class="text-xs text-slate-500">Create itemized fee breakdown for academic term</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('createInvoiceModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="fees.php" class="space-y-4">
            <input type="hidden" name="action" value="create_invoice">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Select Student *</label>
                <select name="student_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                    <option value="">-- Choose Student --</option>
                    <?php foreach ($students as $stu): ?>
                        <option value="<?= $stu['id'] ?>"><?= htmlspecialchars($stu['full_name']) ?> (<?= htmlspecialchars($stu['class']) ?> &bull; Roll: <?= htmlspecialchars($stu['roll_no'] ?: 'N/A') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Fee Invoice Title *</label>
                <input type="text" name="title" required value="Annual Academic Fee 2026-27 (Term 1)" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Tuition Fee (₹)</label>
                    <input type="number" step="100" name="tuition_fee" value="28000" class="w-full px-3 py-2 rounded-xl border border-slate-300 bg-slate-50">
                </div>
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Lab / Practical Fee (₹)</label>
                    <input type="number" step="100" name="lab_fee" value="4500" class="w-full px-3 py-2 rounded-xl border border-slate-300 bg-slate-50">
                </div>
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Library Fee (₹)</label>
                    <input type="number" step="100" name="library_fee" value="2000" class="w-full px-3 py-2 rounded-xl border border-slate-300 bg-slate-50">
                </div>
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Sports &amp; Extracurricular (₹)</label>
                    <input type="number" step="100" name="sports_fee" value="2500" class="w-full px-3 py-2 rounded-xl border border-slate-300 bg-slate-50">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">CBSE Exam Fee (₹)</label>
                    <input type="number" step="100" name="exam_fee" value="3000" class="w-full px-3 py-2 rounded-xl border border-slate-300 bg-slate-50">
                </div>
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Payment Due Date</label>
                    <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" class="w-full px-3 py-2 rounded-xl border border-slate-300 bg-slate-50">
                </div>
            </div>

            <div class="pt-4 flex items-center justify-end space-x-3">
                <button type="button" onclick="closeModal('createInvoiceModal')" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-semibold text-xs">Cancel</button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md">
                    <i class="fa-solid fa-plus-circle mr-1.5 text-amber-400"></i> Issue Invoice
                </button>
            </div>
        </form>
    </div>
</div>

<!-- RECORD PAYMENT MODAL -->
<div id="recordPaymentModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-hand-holding-dollar text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">Record Fee Payment</h3>
                    <p class="text-xs text-slate-500">Collect fee and generate official receipt</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('recordPaymentModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="fees.php" class="space-y-4">
            <input type="hidden" name="action" value="record_payment">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Select Fee Invoice *</label>
                <select name="invoice_id" id="payment_invoice_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                    <option value="">-- Choose Invoice to Pay --</option>
                    <?php foreach ($invoices as $inv): ?>
                        <?php $bal = max(0, $inv['total_amount'] - $inv['paid_amount']); ?>
                        <?php if ($bal > 0): ?>
                            <option value="<?= $inv['id'] ?>" data-balance="<?= $bal ?>">
                                <?= htmlspecialchars($inv['invoice_no']) ?> - <?= htmlspecialchars($inv['student_name']) ?> (Due: ₹<?= number_format($bal) ?>)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Amount to Pay (₹) *</label>
                    <input type="number" step="100" min="1" name="amount_paid" id="payment_amount" required placeholder="e.g. 15000" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Payment Mode *</label>
                    <select name="payment_mode" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none font-semibold">
                        <option value="UPI">UPI / QR Payment</option>
                        <option value="Cash">Cash (Counter Deposit)</option>
                        <option value="Net Banking">Net Banking (NEFT/RTGS)</option>
                        <option value="Card">Debit / Credit Card</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Transaction Ref / Cheque No / Notes</label>
                <input type="text" name="transaction_ref" placeholder="e.g. UPI/261019/389102" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
            </div>

            <div class="pt-4 flex items-center justify-end space-x-3">
                <button type="button" onclick="closeModal('recordPaymentModal')" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-semibold text-xs">Cancel</button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md">
                    <i class="fa-solid fa-receipt mr-1.5"></i> Confirm &amp; Generate Receipt
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function quickPayModal(inv) {
        const bal = Math.max(0, inv.total_amount - inv.paid_amount);
        const sel = document.getElementById('payment_invoice_id');
        sel.value = inv.id;
        document.getElementById('payment_amount').value = bal;
        openModal('recordPaymentModal');
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
