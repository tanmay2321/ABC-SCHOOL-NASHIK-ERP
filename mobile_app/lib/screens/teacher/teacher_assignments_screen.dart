import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';

class TeacherAssignmentsScreen extends StatefulWidget {
  const TeacherAssignmentsScreen({super.key});

  @override
  State<TeacherAssignmentsScreen> createState() => _TeacherAssignmentsScreenState();
}

class _TeacherAssignmentsScreenState extends State<TeacherAssignmentsScreen> {
  List<dynamic> _assignments = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadAssignments();
  }

  Future<void> _loadAssignments() async {
    final list = await ApiService().fetchAssignments();
    if (mounted) {
      setState(() {
        _assignments = list;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('Homework & Submissions Review'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : RefreshIndicator(
              onRefresh: _loadAssignments,
              color: AppTheme.navyDark,
              child: ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  if (_assignments.isEmpty)
                    const Center(child: Padding(padding: EdgeInsets.all(40), child: Text('No active assignments created.')))
                  else
                    ..._assignments.map((a) {
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
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(color: AppTheme.amberLight, borderRadius: BorderRadius.circular(8)),
                                  child: Text(a['subject'] ?? 'Subject', style: const TextStyle(color: AppTheme.amber, fontSize: 10, fontWeight: FontWeight.bold)),
                                ),
                                Text('Due: ${a['due_date'] ?? ""}', style: const TextStyle(color: AppTheme.slate400, fontSize: 11, fontWeight: FontWeight.bold)),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text(a['title'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.slate900)),
                            const SizedBox(height: 4),
                            Text(a['description'] ?? '', style: const TextStyle(color: AppTheme.slate600, fontSize: 12)),
                            const Divider(height: 18),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  'Max Score: ${a['max_marks'] ?? 25} pts',
                                  style: const TextStyle(color: AppTheme.slate600, fontSize: 12, fontWeight: FontWeight.bold),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                  decoration: BoxDecoration(color: AppTheme.emeraldLight, borderRadius: BorderRadius.circular(10)),
                                  child: const Text('Active Submissions', style: TextStyle(color: AppTheme.emerald, fontSize: 11, fontWeight: FontWeight.bold)),
                                ),
                              ],
                            ),
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
