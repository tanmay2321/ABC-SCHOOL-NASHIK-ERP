import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../../widgets/custom_button.dart';

class StudentLeaveScreen extends StatefulWidget {
  const StudentLeaveScreen({super.key});

  @override
  State<StudentLeaveScreen> createState() => _StudentLeaveScreenState();
}

class _StudentLeaveScreenState extends State<StudentLeaveScreen> {
  List<dynamic> _leaves = [];
  bool _isLoading = true;

  final _reasonController = TextEditingController();
  String _selectedType = 'Medical Leave';
  DateTime _fromDate = DateTime.now();
  DateTime _toDate = DateTime.now().add(const Duration(days: 1));

  @override
  void initState() {
    super.initState();
    _loadLeaves();
  }

  Future<void> _loadLeaves() async {
    final list = await ApiService().fetchLeaves();
    if (mounted) {
      setState(() {
        _leaves = list;
        _isLoading = false;
      });
    }
  }

  void _showApplyDialog() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setMState) => Padding(
          padding: EdgeInsets.only(
            left: 24,
            right: 24,
            top: 24,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Apply for Leave', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                  IconButton(onPressed: () => Navigator.pop(ctx), icon: const Icon(Icons.close)),
                ],
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _selectedType,
                decoration: const InputDecoration(labelText: 'Leave Category'),
                items: ['Medical Leave', 'Family Emergency', 'Academic Event', 'Casual Leave']
                    .map((e) => DropdownMenuItem(value: e, child: Text(e)))
                    .toList(),
                onChanged: (val) {
                  if (val != null) setMState(() => _selectedType = val);
                },
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () async {
                        final picked = await showDatePicker(
                          context: context,
                          initialDate: _fromDate,
                          firstDate: DateTime.now().subtract(const Duration(days: 7)),
                          lastDate: DateTime.now().add(const Duration(days: 60)),
                        );
                        if (picked != null) setMState(() => _fromDate = picked);
                      },
                      icon: const Icon(Icons.calendar_today, size: 14),
                      label: Text('From: ${_fromDate.toString().split(' ')[0]}', style: const TextStyle(fontSize: 11)),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () async {
                        final picked = await showDatePicker(
                          context: context,
                          initialDate: _toDate,
                          firstDate: _fromDate,
                          lastDate: DateTime.now().add(const Duration(days: 60)),
                        );
                        if (picked != null) setMState(() => _toDate = picked);
                      },
                      icon: const Icon(Icons.calendar_today, size: 14),
                      label: Text('To: ${_toDate.toString().split(' ')[0]}', style: const TextStyle(fontSize: 11)),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              TextField(
                controller: _reasonController,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Reason for Leave',
                  hintText: 'Describe details for teacher approval...',
                ),
              ),
              const SizedBox(height: 20),
              CustomButton(
                label: 'Submit Leave Request',
                onPressed: () async {
                  if (_reasonController.text.trim().isEmpty) return;
                  Navigator.pop(ctx);
                  await ApiService().applyLeave(
                    leaveType: _selectedType,
                    fromDate: _fromDate.toString().split(' ')[0],
                    toDate: _toDate.toString().split(' ')[0],
                    reason: _reasonController.text.trim(),
                  );
                  _reasonController.clear();
                  if (mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Leave application submitted to class teacher!'), backgroundColor: AppTheme.emerald),
                    );
                    _loadLeaves();
                  }
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('Leave Applications'),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _showApplyDialog,
        backgroundColor: AppTheme.navyDark,
        icon: const Icon(Icons.add, color: AppTheme.gold),
        label: const Text('Apply Leave', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : RefreshIndicator(
              onRefresh: _loadLeaves,
              color: AppTheme.navyDark,
              child: ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  if (_leaves.isEmpty)
                    const Center(child: Padding(padding: EdgeInsets.all(40), child: Text('No leave applications on record.')))
                  else
                    ..._leaves.map((l) {
                      final status = l['status'] ?? 'Pending';
                      Color color = AppTheme.amber;
                      Color bg = AppTheme.amberLight;
                      if (status == 'Approved') {
                        color = AppTheme.emerald;
                        bg = AppTheme.emeraldLight;
                      } else if (status == 'Rejected') {
                        color = AppTheme.rose;
                        bg = AppTheme.roseLight;
                      }

                      return Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: AppTheme.slate100),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(l['leave_type'] ?? 'Leave', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                  decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(10)),
                                  child: Text(
                                    status.toUpperCase(),
                                    style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.bold),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text(
                              'Duration: ${l['from_date']} to ${l['to_date']}',
                              style: const TextStyle(color: AppTheme.navyMedium, fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                            const SizedBox(height: 6),
                            Text(l['reason'] ?? '', style: const TextStyle(color: AppTheme.slate600, fontSize: 12)),
                            if (l['admin_remarks'] != null && l['admin_remarks'].toString().isNotEmpty) ...[
                              const Divider(height: 18),
                              Row(
                                children: [
                                  const Icon(Icons.rate_review_rounded, size: 14, color: AppTheme.slate400),
                                  const SizedBox(width: 6),
                                  Expanded(
                                    child: Text(
                                      'Remark: ${l['admin_remarks']}',
                                      style: const TextStyle(color: AppTheme.slate600, fontSize: 11, fontStyle: FontStyle.italic),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ],
                        ),
                      );
                    }),
                ],
              ),
            ),
    );
  }
}
