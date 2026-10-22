<?php
/**
 * REST API: Fee Accounting & Online Payment Gateway Simulator
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/cors.php';

$user = get_authenticated_user($pdo);
$role = $user['role'];
$profile = $user['profile'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $student_id = ($role === 'student') ? ($profile['id'] ?? 0) : (int)($_GET['student_id'] ?? 0);

    if ($student_id > 0) {
        // Fetch Invoices
        $invStmt = $pdo->prepare("SELECT * FROM fee_invoices WHERE student_id = :sid ORDER BY id DESC");
        $invStmt->execute(['sid' => $student_id]);
        $invoices = $invStmt->fetchAll();

        // Fetch Payments
        $payStmt = $pdo->prepare("SELECT p.*, i.title as invoice_title 
            FROM fee_payments p 
            JOIN fee_invoices i ON p.invoice_id = i.id 
            WHERE p.student_id = :sid 
            ORDER BY p.id DESC");
        $payStmt->execute(['sid' => $student_id]);
        $payments = $payStmt->fetchAll();

        $total_billed = 0;
        $total_paid = 0;
        foreach ($invoices as $inv) {
            $total_billed += (float)$inv['total_amount'];
            if ($inv['status'] === 'Paid') {
                $total_paid += (float)$inv['total_amount'];
            }
        }
        $due_amount = max(0, $total_billed - $total_paid);

        api_response([
            'summary' => [
                'total_billed' => $total_billed,
                'total_paid' => $total_paid,
                'due_amount' => $due_amount,
                'pending_invoices_count' => count(array_filter($invoices, fn($i) => $i['status'] === 'Unpaid'))
            ],
            'invoices' => $invoices,
            'payments' => $payments
        ]);

    } else {
        // Admin / Principal overview
        $invoices = $pdo->query("SELECT i.*, s.full_name as student_name, s.class, s.student_code 
            FROM fee_invoices i 
            JOIN students s ON i.student_id = s.id 
            ORDER BY i.id DESC LIMIT 50")->fetchAll();

        $total_billed = (float)$pdo->query("SELECT SUM(total_amount) FROM fee_invoices")->fetchColumn();
        $total_paid = (float)$pdo->query("SELECT SUM(amount_paid) FROM fee_payments")->fetchColumn();

        api_response([
            'summary' => [
                'total_billed' => $total_billed,
                'total_paid' => $total_paid,
                'total_due' => max(0, $total_billed - $total_paid)
            ],
            'invoices' => $invoices
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = get_json_input();
    $invoice_id = (int)($input['invoice_id'] ?? 0);
    $payment_mode = $input['payment_mode'] ?? 'UPI';
    $student_id = ($role === 'student') ? ($profile['id'] ?? 0) : (int)($input['student_id'] ?? 0);

    if ($invoice_id <= 0) {
        api_error('Valid Invoice ID is required.');
    }

    $invStmt = $pdo->prepare("SELECT * FROM fee_invoices WHERE id = :id LIMIT 1");
    $invStmt->execute(['id' => $invoice_id]);
    $invoice = $invStmt->fetch();

    if (!$invoice) {
        api_error('Fee Invoice not found.', 404);
    }

    if ($invoice['status'] === 'Paid') {
        api_error('This invoice has already been fully settled and paid.', 400);
    }

    $amount = (float)($input['amount'] ?? $invoice['total_amount']);
    $receipt_no = 'REC-' . date('Y') . '-' . rand(10000, 99999);
    $tx_ref = 'TXN' . strtoupper(substr(md5(uniqid()), 0, 10));

    try {
        $pdo->beginTransaction();

        // Record payment
        $payStmt = $pdo->prepare("INSERT INTO fee_payments 
            (receipt_no, invoice_id, student_id, amount_paid, payment_mode, transaction_ref, received_by) 
            VALUES (:rno, :iid, :sid, :amt, :mode, :tx, :recby)");
        $payStmt->execute([
            'rno' => $receipt_no,
            'iid' => $invoice_id,
            'sid' => $invoice['student_id'],
            'amt' => $amount,
            'mode' => $payment_mode,
            'tx' => $tx_ref,
            'recby' => $user['id']
        ]);

        // Update invoice status
        $new_status = ($amount >= (float)$invoice['total_amount']) ? 'Paid' : 'Partial';
        $upd = $pdo->prepare("UPDATE fee_invoices SET status = :st WHERE id = :id");
        $upd->execute(['st' => $new_status, 'id' => $invoice_id]);

        $pdo->commit();

        api_response([
            'message' => 'Fee payment processed successfully! Official receipt generated.',
            'receipt' => [
                'receipt_no' => $receipt_no,
                'invoice_no' => $invoice['invoice_no'],
                'invoice_title' => $invoice['title'],
                'amount_paid' => $amount,
                'payment_mode' => $payment_mode,
                'transaction_ref' => $tx_ref,
                'payment_date' => date('Y-m-d H:i:s'),
                'status' => 'Success'
            ]
        ], 201);

    } catch (Exception $e) {
        $pdo->rollBack();
        api_error('Payment failed: ' . $e->getMessage(), 500);
    }
}

api_error('Method Not Allowed', 405);
