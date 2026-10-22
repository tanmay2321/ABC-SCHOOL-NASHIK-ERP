import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';

class TeacherLeavesScreen extends StatefulWidget {
  const TeacherLeavesScreen({super.key});

  @override
  State<TeacherLeavesScreen> createState() => _TeacherLeavesScreenState();
}

class _TeacherLeavesScreenState extends State<TeacherLeavesScreen> {
  List<dynamic> _leaves = [];
  bool _isLoading = true;

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

  Future<void> _updateLeave(int id, String status) async {
    await ApiService().updateLeaveStatus(applicationId: id, status: status);
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Leave request marked as $status!'),
          backgroundColor: status == 'Approved' ? AppTheme.emerald : AppTheme.rose,
        ),
      );
      _loadLeaves();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('Student Leave Applications'),
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
                    const Center(child: Padding(padding: EdgeInsets.all(40), child: Text('No pending leave requests.')))
                  else
                    ..._leaves.map((l) {
                      final status = l['status'] ?? 'Pending';
                      final isPending = status == 'Pending';
                      final id = l['id'] is int ? l['id'] : int.tryParse(l['id'].toString()) ?? 1;

                      return Container(
                        margin: const EdgeInsets.only(bottom: 14),
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
                                Text(
                                  l['applicant_name'] ?? l['username'] ?? 'Student',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.slate900),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: isPending ? AppTheme.amberLight : (status == 'Approved' ? AppTheme.emeraldLight : AppTheme.roseLight),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Text(
                                    status.toUpperCase(),
                                    style: TextStyle(
                                      color: isPending ? AppTheme.amber : (status == 'Approved' ? AppTheme.emerald : AppTheme.rose),
                                      fontSize: 10,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text(
                              '${l['leave_type']} • ${l['from_date']} to ${l['to_date']}',
                              style: const TextStyle(color: AppTheme.navyMedium, fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                            const SizedBox(height: 6),
                            Text(l['reason'] ?? '', style: const TextStyle(color: AppTheme.slate600, fontSize: 12)),
                            if (isPending) ...[
                              const Divider(height: 20),
                              Row(
                                children: [
                                  Expanded(
                                    child: OutlinedButton(
                                      onPressed: () => _updateLeave(id, 'Rejected'),
                                      style: OutlinedButton.styleFrom(
                                        foregroundColor: AppTheme.rose,
                                        side: const BorderSide(color: AppTheme.rose),
                                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                      ),
                                      child: const Text('Decline'),
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: ElevatedButton(
                                      onPressed: () => _updateLeave(id, 'Approved'),
                                      style: ElevatedButton.styleFrom(
                                        backgroundColor: AppTheme.emerald,
                                        foregroundColor: Colors.white,
                                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                      ),
                                      child: const Text('Approve Leave'),
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
