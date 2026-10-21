<?php
/**
 * Student: Fee Portal & Digital Receipts
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/../includes/auth.php';
require_auth('student');

$student_user_id = $_SESSION['user_id'];
$sStmt = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
$sStmt->execute(['uid' => $student_user_id]);
$student = $sStmt->fetch();
$student_id = $student['id'] ?? 0;

$flash = get_flash();
$receipt_to_view = null;

$school_logo = get_setting($pdo, 'school_logo', '');
$school_name = get_setting($pdo, 'school_name', 'BELLWETHER INTERNATIONAL SCHOOL NANDURBAR');

// Handle Online Fee Payment Simulation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'student_pay_online') {
    $invoice_id = (int)($_POST['invoice_id'] ?? 0);
    $pay_amount = (float)($_POST['pay_amount'] ?? 0);
    $pay_mode = trim($_POST['pay_mode'] ?? 'UPI');
    $upi_id = trim($_POST['upi_id'] ?? 'parent@upi');

    if ($invoice_id > 0 && $pay_amount > 0) {
        $invStmt = $pdo->prepare("SELECT * FROM fee_invoices WHERE id = ? AND student_id = ?");
        $invStmt->execute([$invoice_id, $student_id]);
        $inv = $invStmt->fetch();

        if ($inv) {
            $paidStmt = $pdo->prepare("SELECT SUM(amount_paid) FROM fee_payments WHERE invoice_id = ?");
            $paidStmt->execute([$invoice_id]);
            $already_paid = (float)$paidStmt->fetchColumn();

            $new_total_paid = $already_paid + $pay_amount;
            $new_status = ($new_total_paid >= $inv['total_amount']) ? 'Paid' : 'Partial';

            $receipt_no = 'REC-2026-' . rand(1000, 9999);
            $txn_ref = 'ONLINE/' . strtoupper($pay_mode) . '/' . rand(1000000, 9999999);

            $pStmt = $pdo->prepare("
                INSERT INTO fee_payments (receipt_no, invoice_id, student_id, amount_paid, payment_mode, transaction_ref, payment_date, received_by)
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 1)
            ");
            $pStmt->execute([$receipt_no, $invoice_id, $student_id, $pay_amount, $pay_mode, $txn_ref]);

            // Update invoice
            $pdo->prepare("UPDATE fee_invoices SET status = ? WHERE id = ?")->execute([$new_status, $invoice_id]);

            set_flash('success', "Payment of ₹" . number_format($pay_amount) . " confirmed! Receipt No: <strong>{$receipt_no}</strong> has been generated.");
            header("Location: /student/fees.php?view_receipt=" . urlencode($receipt_no));
            exit();
        }
    }
}

// Fetch Fee Invoices for this Student
$invoices = $pdo->prepare("
    SELECT f.*,
           IFNULL((SELECT SUM(amount_paid) FROM fee_payments WHERE invoice_id = f.id), 0) as paid_amount
    FROM fee_invoices f
    WHERE f.student_id = ?
    ORDER BY f.id DESC
");
$invoices->execute([$student_id]);
$all_invoices = $invoices->fetchAll();

// Calculations
$total_invoiced = 0;
$total_paid = 0;
foreach ($all_invoices as $inv) {
    $total_invoiced += $inv['total_amount'];
    $total_paid += $inv['paid_amount'];
}
$total_pending = max(0, $total_invoiced - $total_paid);

// Fetch All Payment Receipts
$pStmt = $pdo->prepare("
    SELECT p.*, f.title as invoice_title, f.invoice_no
    FROM fee_payments p
    JOIN fee_invoices f ON p.invoice_id = f.id
    WHERE p.student_id = ?
    ORDER BY p.id DESC
");
$pStmt->execute([$student_id]);
$all_payments = $pStmt->fetchAll();

// Check if viewing a specific receipt
$view_receipt_no = $_GET['view_receipt'] ?? '';
if (!empty($view_receipt_no)) {
    foreach ($all_payments as $p) {
        if ($p['receipt_no'] === $view_receipt_no) {
            $receipt_to_view = $p;
            break;
        }
    }
}

$page_title = 'My Fee Portal & Receipts';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 no-print">
        <div>
            <div class="flex items-center space-x-2 text-xs font-bold text-emerald-600 uppercase tracking-wider mb-1">
                <span>Fee Account &amp; Billing Portal</span>
                <span>&bull;</span>
                <span class="text-slate-400">Class: <?= htmlspecialchars($student['class'] ?? 'N/A') ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit']">
                Student Fee Portal
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Review your total academic fees, amount paid, pending balance, and download official payment receipts.
            </p>
        </div>

        <?php if ($total_pending > 0): ?>
            <button type="button" onclick="openPayModal()" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold text-xs shadow-md shadow-emerald-600/20 transition">
                <i class="fa-solid fa-credit-card mr-2 text-amber-300"></i> Pay Outstanding Dues Online
            </button>
        <?php else: ?>
            <span class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                <i class="fa-solid fa-circle-check text-emerald-600 mr-2"></i> All Fees Cleared! No Dues Pending
            </span>
        <?php endif; ?>
    </div>

    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-semibold flex items-center justify-between auto-dismiss-alert no-print">
            <div class="flex items-center space-x-2.5">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                <span><?= $flash['message'] ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8 no-print">
        
        <!-- Total Fees Invoiced -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-slate-400 tracking-wider">Total Academic Fees</span>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="fa-solid fa-receipt text-lg"></i>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-['Outfit'] mt-3">₹<?= number_format($total_invoiced) ?></p>
            <p class="text-[11px] text-slate-400 mt-1">Session 2026-27 total dues</p>
        </div>

        <!-- Total Fees Paid -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase text-emerald-600 tracking-wider">Total Fees Paid</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="fa-solid fa-circle-check text-lg"></i>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-emerald-600 font-['Outfit'] mt-3">₹<?= number_format($total_paid) ?></p>
            <p class="text-[11px] text-emerald-700 font-medium mt-1">Verified school payments</p>
        </div>

        <!-- Total Fees Pending (Crucial Highlight!) -->
        <div class="bg-white p-5 rounded-2xl border <?= $total_pending > 0 ? 'border-amber-300 bg-amber-50/30' : 'border-slate-200' ?> shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase <?= $total_pending > 0 ? 'text-amber-700' : 'text-slate-400' ?> tracking-wider">Remaining Balance</span>
                <div class="w-10 h-10 rounded-xl <?= $total_pending > 0 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-400' ?> flex items-center justify-center">
                    <i class="fa-solid <?= $total_pending > 0 ? 'fa-clock text-amber-600' : 'fa-check text-slate-500' ?> text-lg"></i>
                </div>
            </div>
            <div class="flex items-baseline space-x-2 mt-3">
                <p class="text-2xl sm:text-3xl font-extrabold <?= $total_pending > 0 ? 'text-amber-600' : 'text-slate-900' ?> font-['Outfit']">₹<?= number_format($total_pending) ?></p>
                <?php if ($total_pending > 0): ?>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-200 text-amber-900">Pending</span>
                <?php endif; ?>
            </div>
            <p class="text-[11px] <?= $total_pending > 0 ? 'text-amber-800' : 'text-slate-400' ?> font-medium mt-1">
                <?= $total_pending > 0 ? 'Payable before due date' : 'No balance due' ?>
            </p>
        </div>

    </div>

    <!-- Printable Official Receipt Card (if selected) -->
    <?php if ($receipt_to_view): ?>
        <div class="bg-white rounded-3xl border-2 border-slate-300 shadow-xl p-8 max-w-2xl mx-auto mb-8 print-page animate-fade-in">
            <div class="flex items-center justify-between pb-4 border-b-2 border-navy-950 mb-4">
                <div class="flex items-center space-x-3">
                    <?php if (!empty($school_logo) && file_exists(__DIR__ . '/../' . ltrim($school_logo, '/'))): ?>
                        <img src="<?= htmlspecialchars($school_logo) ?>" alt="School Logo" class="w-12 h-12 object-contain rounded-2xl bg-white p-1 border border-slate-200">
                    <?php else: ?>
                        <div class="w-12 h-12 rounded-2xl bg-navy-950 flex items-center justify-center text-white text-xl">
                            <i class="fa-solid fa-graduation-cap text-amber-400"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h2 class="text-base font-bold text-navy-950 font-['Outfit'] uppercase"><?= htmlspecialchars($school_name) ?></h2>
                        <p class="text-[11px] text-amber-600 font-semibold">Official Fee Payment Receipt</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[11px] font-mono font-bold text-blue-700"><?= htmlspecialchars($receipt_to_view['receipt_no']) ?></div>
                    <div class="text-[10px] text-slate-400"><?= date('d M Y, h:i A', strtotime($receipt_to_view['payment_date'])) ?></div>
                </div>
            </div>

            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200 text-xs grid grid-cols-2 gap-3 mb-4">
                <div>Student Name: <strong class="text-slate-900"><?= htmlspecialchars($student['full_name']) ?></strong></div>
                <div>Student ID: <span class="font-mono text-blue-700 font-bold"><?= htmlspecialchars($student['student_code']) ?></span></div>
                <div>Class &amp; Roll: <strong class="text-slate-900"><?= htmlspecialchars($student['class']) ?> (Roll <?= htmlspecialchars($student['roll_no'] ?: 'N/A') ?>)</strong></div>
                <div>Payment Mode: <span class="font-bold text-emerald-700"><?= htmlspecialchars($receipt_to_view['payment_mode']) ?></span></div>
                <div class="col-span-2">Transaction Ref: <span class="font-mono text-slate-600"><?= htmlspecialchars($receipt_to_view['transaction_ref'] ?: 'Direct Account Deposit') ?></span></div>
            </div>

            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-center mb-6">
                <span class="text-xs uppercase tracking-wider text-emerald-700 font-semibold block">Total Amount Received</span>
                <span class="text-3xl font-extrabold text-emerald-800 font-['Outfit']">₹<?= number_format($receipt_to_view['amount_paid']) ?></span>
                <span class="text-[11px] text-emerald-600 block mt-0.5">Paid toward: <?= htmlspecialchars($receipt_to_view['invoice_title']) ?></span>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-slate-200 text-xs text-slate-500">
                <div class="text-center">
                    <div class="w-10 h-10 rounded-full border border-dashed border-amber-600 flex items-center justify-center text-[8px] font-bold text-amber-700 mx-auto mb-1">
                        SEAL
                    </div>
                    <span>School Stamp</span>
                </div>
                <div class="text-center">
                    <div class="h-8 flex items-end justify-center italic text-slate-400 pb-1">Accounts Office</div>
                    <div class="border-t border-slate-300 pt-1 font-semibold text-slate-700">Cashier / Registrar Signature</div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3 no-print">
                <a href="/student/fees.php" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs">Close Receipt</a>
                <button type="button" onclick="window.print()" class="px-5 py-2 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs shadow-md">
                    <i class="fa-solid fa-print mr-1.5 text-amber-400"></i> Print Receipt
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Invoices Breakdown Table -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden mb-8 no-print">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Fee Invoices &amp; Breakdown</h3>
                <p class="text-xs text-slate-500">Itemized statement of school tuition, laboratories, and exam fees</p>
            </div>
            <span class="text-xs text-slate-400"><?= count($all_invoices) ?> Statement(s)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-200">
                        <th class="py-3 px-4 sm:px-6">Invoice #</th>
                        <th class="py-3 px-4">Fee Description</th>
                        <th class="py-3 px-4">Breakdown</th>
                        <th class="py-3 px-4">Total Amount</th>
                        <th class="py-3 px-4">Paid</th>
                        <th class="py-3 px-4">Pending</th>
                        <th class="py-3 px-4">Due Date</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 sm:px-6 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($all_invoices)): ?>
                        <tr>
                            <td colspan="9" class="py-8 text-center text-slate-500">
                                No fee statements published for your account yet.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($all_invoices as $inv): ?>
                        <?php $bal = max(0, $inv['total_amount'] - $inv['paid_amount']); ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-4 sm:px-6 font-mono font-bold text-blue-700">
                                <?= htmlspecialchars($inv['invoice_no']) ?>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-900">
                                <?= htmlspecialchars($inv['title']) ?>
                            </td>
                            <td class="py-3.5 px-4 text-[11px] text-slate-500">
                                <div>Tuition: ₹<?= number_format($inv['tuition_fee']) ?> &bull; Lab: ₹<?= number_format($inv['lab_fee']) ?></div>
                                <div>Library: ₹<?= number_format($inv['library_fee']) ?> &bull; Exam: ₹<?= number_format($inv['exam_fee']) ?></div>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-900">
                                ₹<?= number_format($inv['total_amount']) ?>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-emerald-600">
                                ₹<?= number_format($inv['paid_amount']) ?>
                            </td>
                            <td class="py-3.5 px-4 font-bold <?= $bal > 0 ? 'text-amber-600' : 'text-slate-400' ?>">
                                ₹<?= number_format($bal) ?>
                            </td>
                            <td class="py-3.5 px-4 text-slate-500">
                                <?= date('d M Y', strtotime($inv['due_date'])) ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <?php if ($inv['status'] === 'Paid'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-check text-[10px] mr-1"></i> Paid
                                    </span>
                                <?php elseif ($inv['status'] === 'Partial'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">
                                        <i class="fa-solid fa-clock text-[10px] mr-1"></i> Partial
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800">
                                        <i class="fa-solid fa-circle-exclamation text-[10px] mr-1"></i> Unpaid
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 sm:px-6 text-right">
                                <?php if ($bal > 0): ?>
                                    <button type="button" 
                                            onclick='openPaySingleModal(<?= json_encode($inv) ?>)'
                                            class="inline-flex items-center px-3 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition">
                                        <i class="fa-solid fa-wallet mr-1.5"></i> Pay ₹<?= number_format($bal) ?>
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs text-emerald-600 font-semibold"><i class="fa-solid fa-circle-check mr-1"></i> Cleared</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payment Receipts History -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 no-print">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 font-['Outfit']">Official Payment Receipts Log</h3>
                <p class="text-xs text-slate-500">Every payment made generates a digitally verified school receipt</p>
            </div>
        </div>

        <?php if (empty($all_payments)): ?>
            <p class="text-xs text-slate-400 py-6 text-center">No payment receipts issued yet.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($all_payments as $p): ?>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-xs flex flex-col justify-between space-y-3">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-mono font-bold text-blue-700"><?= htmlspecialchars($p['receipt_no']) ?></span>
                                <span class="px-2 py-0.5 rounded font-semibold bg-emerald-100 text-emerald-800 text-[10px]"><?= htmlspecialchars($p['payment_mode']) ?></span>
                            </div>
                            <div class="font-bold text-slate-900 mt-1">₹<?= number_format($p['amount_paid']) ?></div>
                            <div class="text-[11px] text-slate-500 truncate"><?= htmlspecialchars($p['invoice_title']) ?></div>
                        </div>

                        <div class="pt-2 border-t border-slate-200 flex items-center justify-between">
                            <span class="text-[10px] text-slate-400"><?= date('d M Y', strtotime($p['payment_date'])) ?></span>
                            <a href="/student/fees.php?view_receipt=<?= urlencode($p['receipt_no']) ?>" class="font-bold text-blue-600 hover:text-blue-800 flex items-center">
                                <span>View Receipt</span>
                                <i class="fa-solid fa-arrow-right ml-1 text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</main>

<!-- ONLINE FEE PAYMENT MODAL -->
<div id="studentPayModal" class="modal-backdrop fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200 animate-fade-in my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-shield-halved text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">School Online Fee Gateway</h3>
                    <p class="text-xs text-slate-500">Secure simulated payment with instant digital receipt</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('studentPayModal')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
        </div>

        <form method="POST" action="fees.php" class="space-y-4">
            <input type="hidden" name="action" value="student_pay_online">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Select Invoice to Pay *</label>
                <select name="invoice_id" id="modal_invoice_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                    <?php foreach ($all_invoices as $inv): ?>
                        <?php $bal = max(0, $inv['total_amount'] - $inv['paid_amount']); ?>
                        <?php if ($bal > 0): ?>
                            <option value="<?= $inv['id'] ?>" data-amount="<?= $bal ?>">
                                <?= htmlspecialchars($inv['title']) ?> (Due: ₹<?= number_format($bal) ?>)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Amount to Pay (₹) *</label>
                <input type="number" step="100" min="1" name="pay_amount" id="modal_pay_amount" required value="<?= $total_pending ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-base font-extrabold text-slate-900 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Payment Method</label>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <label class="p-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 flex items-center space-x-2 cursor-pointer">
                        <input type="radio" name="pay_mode" value="UPI" checked class="text-emerald-600">
                        <span class="font-bold text-slate-800">UPI / QR Code</span>
                    </label>
                    <label class="p-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 flex items-center space-x-2 cursor-pointer">
                        <input type="radio" name="pay_mode" value="Net Banking" class="text-emerald-600">
                        <span class="font-bold text-slate-800">Net Banking</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">UPI ID / Bank Virtual Account</label>
                <input type="text" name="upi_id" value="parent@okhdfcbank" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 bg-slate-50 text-xs">
            </div>

            <div class="p-3 rounded-xl bg-blue-50 border border-blue-200 text-blue-900 text-[11px] flex items-center space-x-2">
                <i class="fa-solid fa-lock text-blue-600 text-sm"></i>
                <span>Instant confirmation &amp; official receipt will be generated immediately.</span>
            </div>

            <div class="pt-4 flex items-center justify-end space-x-3">
                <button type="button" onclick="closeModal('studentPayModal')" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-semibold text-xs">Cancel</button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md">
                    <i class="fa-solid fa-check mr-1.5"></i> Complete Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openPayModal() {
        openModal('studentPayModal');
    }

    function openPaySingleModal(inv) {
        const bal = Math.max(0, inv.total_amount - inv.paid_amount);
        const sel = document.getElementById('modal_invoice_id');
        sel.value = inv.id;
        document.getElementById('modal_pay_amount').value = bal;
        openModal('studentPayModal');
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
