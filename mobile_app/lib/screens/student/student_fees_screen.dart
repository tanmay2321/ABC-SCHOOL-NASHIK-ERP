import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../../widgets/custom_button.dart';

class StudentFeesScreen extends StatefulWidget {
  const StudentFeesScreen({super.key});

  @override
  State<StudentFeesScreen> createState() => _StudentFeesScreenState();
}

class _StudentFeesScreenState extends State<StudentFeesScreen> {
  Map<String, dynamic>? _feeData;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadFees();
  }

  Future<void> _loadFees() async {
    final res = await ApiService().fetchFees();
    if (mounted) {
      setState(() {
        _feeData = res;
        _isLoading = false;
      });
    }
  }

  void _showPaymentModal(dynamic invoice) {
    String selectedMode = 'UPI';
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModalState) => Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Online Fee Payment Gateway',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.slate900),
                  ),
                  IconButton(onPressed: () => Navigator.pop(ctx), icon: const Icon(Icons.close)),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                'Settling: ${invoice['title']} (${invoice['invoice_no']})',
                style: const TextStyle(fontSize: 12, color: AppTheme.slate600),
              ),
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppTheme.slate50,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Total Amount Payable:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                    Text(
                      '₹${invoice['total_amount']}',
                      style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: AppTheme.navyDark),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              const Text('Select Payment Mode:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
              const SizedBox(height: 10),
              ...['UPI (GooglePay / PhonePe / Paytm)', 'Credit / Debit Card', 'Net Banking'].map((mode) {
                final isSelected = selectedMode == mode.split(' ')[0];
                return InkWell(
                  onTap: () => setModalState(() => selectedMode = mode.split(' ')[0]),
                  child: Container(
                    margin: const EdgeInsets.only(bottom: 8),
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    decoration: BoxDecoration(
                      color: isSelected ? AppTheme.navyMedium.withValues(alpha: 0.08) : Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: isSelected ? AppTheme.navyMedium : AppTheme.slate200,
                        width: isSelected ? 1.5 : 1,
                      ),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(mode, style: TextStyle(fontSize: 13, fontWeight: isSelected ? FontWeight.bold : FontWeight.normal)),
                        Icon(
                          isSelected ? Icons.radio_button_checked : Icons.radio_button_off,
                          color: isSelected ? AppTheme.navyMedium : AppTheme.slate400,
                          size: 18,
                        ),
                      ],
                    ),
                  ),
                );
              }),
              const SizedBox(height: 20),
              CustomButton(
                label: 'Pay ₹${invoice['total_amount']} Securely',
                icon: Icons.lock_rounded,
                onPressed: () async {
                  Navigator.pop(ctx);
                  final payRes = await ApiService().payFee(
                    invoiceId: invoice['id'] is int ? invoice['id'] : int.tryParse(invoice['id'].toString()) ?? 101,
                    amount: (invoice['total_amount'] is num) ? (invoice['total_amount'] as num).toDouble() : 26000.0,
                    paymentMode: selectedMode,
                  );
                  if (mounted) {
                    _showReceiptDialog(payRes['receipt'] ?? {});
                    _loadFees();
                  }
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showReceiptDialog(Map<String, dynamic> r) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 56,
              height: 56,
              decoration: const BoxDecoration(color: AppTheme.emeraldLight, shape: BoxShape.circle),
              child: const Icon(Icons.check_circle_rounded, color: AppTheme.emerald, size: 36),
            ),
            const SizedBox(height: 12),
            const Text(
              'Payment Successful!',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.slate900),
            ),
            const Text('Official Fee Receipt Generated', style: TextStyle(color: AppTheme.slate400, fontSize: 12)),
            const Divider(height: 24),
            _receiptRow('Receipt No:', r['receipt_no'] ?? 'REC-2026-0982'),
            _receiptRow('Amount Paid:', '₹${r['amount_paid'] ?? 26000}'),
            _receiptRow('Payment Mode:', r['payment_mode'] ?? 'UPI'),
            _receiptRow('Txn Ref ID:', r['transaction_ref'] ?? 'TXN88923412'),
            _receiptRow('Date:', DateTime.now().toString().split('.')[0]),
            const SizedBox(height: 18),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => Navigator.pop(ctx),
                child: const Text('Done & Print Receipt'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _receiptRow(String k, String v) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(k, style: const TextStyle(color: AppTheme.slate400, fontSize: 12)),
          Text(v, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: AppTheme.slate900)),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final summary = _feeData?['summary'] ?? {};
    final invoices = _feeData?['invoices'] as List<dynamic>? ?? [];
    final payments = _feeData?['payments'] as List<dynamic>? ?? [];

    final double dueAmount = (summary['due_amount'] is num) ? (summary['due_amount'] as num).toDouble() : 26000.0;
    final double paidAmount = (summary['total_paid'] is num) ? (summary['total_paid'] as num).toDouble() : 13000.0;

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('Fee Accounting & Online Payment'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : RefreshIndicator(
              onRefresh: _loadFees,
              color: AppTheme.navyDark,
              child: ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  // Dues Header Banner
                  Container(
                    padding: const EdgeInsets.all(22),
                    decoration: BoxDecoration(
                      gradient: dueAmount > 0
                          ? const LinearGradient(colors: [Color(0xFF991B1B), Color(0xFFDC2626)])
                          : AppTheme.emeraldGradient,
                      borderRadius: BorderRadius.circular(24),
                      boxShadow: [
                        BoxShadow(
                          color: (dueAmount > 0 ? AppTheme.rose : AppTheme.emerald).withValues(alpha: 0.25),
                          blurRadius: 18,
                          offset: const Offset(0, 8),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'OUTSTANDING DUES',
                              style: TextStyle(color: Colors.white70, fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 1.2),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.2),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Text(
                                dueAmount > 0 ? 'PAYMENT DUE' : 'FULLY SETTLED',
                                style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          '₹${dueAmount.toStringAsFixed(0)}',
                          style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Total Paid to Date: ₹${paidAmount.toStringAsFixed(0)} • Academic Year 2026-27',
                          style: const TextStyle(color: Colors.white70, fontSize: 11),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 24),
                  const Text(
                    'Fee Invoices & Breakdown',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.slate900),
                  ),
                  const SizedBox(height: 12),

                  ...invoices.map((inv) {
                    final isPaid = inv['status'] == 'Paid';
                    return Container(
                      margin: const EdgeInsets.only(bottom: 14),
                      padding: const EdgeInsets.all(18),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(22),
                        border: Border.all(color: AppTheme.slate100),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                inv['invoice_no'] ?? '',
                                style: const TextStyle(color: AppTheme.navyMedium, fontWeight: FontWeight.bold, fontSize: 12),
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                decoration: BoxDecoration(
                                  color: isPaid ? AppTheme.emeraldLight : AppTheme.roseLight,
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Text(
                                  inv['status'] ?? 'Unpaid',
                                  style: TextStyle(
                                    color: isPaid ? AppTheme.emerald : AppTheme.rose,
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Text(
                            inv['title'] ?? '',
                            style: const TextStyle(color: AppTheme.slate900, fontSize: 15, fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 10),
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(color: AppTheme.slate50, borderRadius: BorderRadius.circular(14)),
                            child: Column(
                              children: [
                                _breakdownRow('Tuition Fee', '₹${inv['tuition_fee'] ?? 0}'),
                                _breakdownRow('Laboratory Fee', '₹${inv['lab_fee'] ?? 0}'),
                                _breakdownRow('Library & Digital LMS', '₹${inv['library_fee'] ?? 0}'),
                                _breakdownRow('Sports & Physical Dev', '₹${inv['sports_fee'] ?? 0}'),
                                const Divider(height: 14),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    const Text('Total Amount:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                                    Text('₹${inv['total_amount']}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14, color: AppTheme.navyDark)),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 12),
                          if (!isPaid)
                            SizedBox(
                              width: double.infinity,
                              child: ElevatedButton.icon(
                                onPressed: () => _showPaymentModal(inv),
                                icon: const Icon(Icons.payment_rounded, size: 16),
                                label: const Text('Pay Online Now'),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: AppTheme.navyDark,
                                  foregroundColor: Colors.white,
                                  padding: const EdgeInsets.symmetric(vertical: 10),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                ),
                              ),
                            ),
                        ],
                      ),
                    );
                  }),

                  if (payments.isNotEmpty) ...[
                    const SizedBox(height: 18),
                    const Text(
                      'Payment Receipts History',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.slate900),
                    ),
                    const SizedBox(height: 10),
                    ...payments.map((p) => Container(
                          margin: const EdgeInsets.only(bottom: 8),
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: AppTheme.slate100),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(p['receipt_no'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                                  Text(p['invoice_title'] ?? '', style: const TextStyle(color: AppTheme.slate400, fontSize: 11)),
                                ],
                              ),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text('₹${p['amount_paid']}', style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.emerald, fontSize: 13)),
                                  Text(p['payment_mode'] ?? 'UPI', style: const TextStyle(color: AppTheme.slate400, fontSize: 10)),
                                ],
                              ),
                            ],
                          ),
                        )),
                  ],
                ],
              ),
            ),
    );
  }

  Widget _breakdownRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppTheme.slate600, fontSize: 11)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: AppTheme.slate900)),
        ],
      ),
    );
  }
}
