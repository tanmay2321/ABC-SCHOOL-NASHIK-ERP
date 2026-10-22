import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../../widgets/custom_button.dart';

class TeacherAttendanceScreen extends StatefulWidget {
  const TeacherAttendanceScreen({super.key});

  @override
  State<TeacherAttendanceScreen> createState() => _TeacherAttendanceScreenState();
}

class _TeacherAttendanceScreenState extends State<TeacherAttendanceScreen> {
  List<Map<String, dynamic>> _students = [];
  bool _isLoading = true;
  bool _isSaving = false;
  final String _class = 'Class 10';
  final String _date = DateTime.now().toString().split(' ')[0];

  @override
  void initState() {
    super.initState();
    _loadClassRoster();
  }

  Future<void> _loadClassRoster() async {
    final res = await ApiService().fetchAttendance(studentClass: _class, date: _date);
    if (mounted) {
      final list = res['students'] as List<dynamic>? ?? [];
      setState(() {
        _students = list.map((e) => Map<String, dynamic>.from(e)).toList();
        _isLoading = false;
      });
    }
  }

  void _markAllPresent() {
    setState(() {
      for (var s in _students) {
        s['attendance_status'] = 'Present';
      }
    });
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Marked all students as Present!'), duration: Duration(seconds: 1)),
    );
  }

  void _toggleStatus(Map<String, dynamic> s) {
    setState(() {
      final cur = s['attendance_status'] ?? 'Present';
      if (cur == 'Present') {
        s['attendance_status'] = 'Absent';
      } else if (cur == 'Absent') {
        s['attendance_status'] = 'Late';
      } else {
        s['attendance_status'] = 'Present';
      }
    });
  }

  Future<void> _saveAttendance() async {
    setState(() => _isSaving = true);
    final payload = _students.map((s) => {
      'student_id': s['id'],
      'status': s['attendance_status'] ?? 'Present',
      'remarks': s['attendance_remarks'] ?? '',
    }).toList();

    final success = await ApiService().submitAttendance(
      studentClass: _class,
      date: _date,
      attendance: payload,
    );

    if (!mounted) return;
    setState(() => _isSaving = false);

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Class attendance saved & synced to school database!'),
          backgroundColor: AppTheme.emerald,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final int presentCount = _students.where((s) => (s['attendance_status'] ?? 'Present') == 'Present').length;
    final int absentCount = _students.where((s) => s['attendance_status'] == 'Absent').length;
    final int lateCount = _students.where((s) => s['attendance_status'] == 'Late').length;

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: Text('Roll Call: $_class'),
        actions: [
          TextButton(
            onPressed: _markAllPresent,
            child: const Text('All Present', style: TextStyle(color: AppTheme.gold, fontWeight: FontWeight.bold)),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : Column(
              children: [
                // Top stats ticker
                Container(
                  color: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceAround,
                    children: [
                      _badge('TOTAL', '${_students.length}', AppTheme.navyMedium, AppTheme.indigoLight),
                      _badge('PRESENT', '$presentCount', AppTheme.emerald, AppTheme.emeraldLight),
                      _badge('ABSENT', '$absentCount', AppTheme.rose, AppTheme.roseLight),
                      _badge('LATE', '$lateCount', AppTheme.amber, AppTheme.amberLight),
                    ],
                  ),
                ),
                const Divider(height: 1, color: AppTheme.slate200),

                // Student list
                Expanded(
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _students.length,
                    itemBuilder: (ctx, idx) {
                      final s = _students[idx];
                      final status = s['attendance_status'] ?? 'Present';

                      Color color = AppTheme.emerald;
                      Color bg = AppTheme.emeraldLight;
                      if (status == 'Absent') {
                        color = AppTheme.rose;
                        bg = AppTheme.roseLight;
                      } else if (status == 'Late') {
                        color = AppTheme.amber;
                        bg = AppTheme.amberLight;
                      }

                      return InkWell(
                        onTap: () => _toggleStatus(s),
                        borderRadius: BorderRadius.circular(18),
                        child: Container(
                          margin: const EdgeInsets.only(bottom: 10),
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(18),
                            border: Border.all(
                              color: status == 'Absent' ? AppTheme.rose.withValues(alpha: 0.5) : AppTheme.slate100,
                              width: status == 'Absent' ? 1.5 : 1,
                            ),
                          ),
                          child: Row(
                            children: [
                              Container(
                                width: 38,
                                height: 38,
                                decoration: BoxDecoration(
                                  color: AppTheme.slate50,
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                alignment: Alignment.center,
                                child: Text(
                                  s['roll_no']?.toString() ?? '${idx + 1}',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.slate700),
                                ),
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      s['full_name'] ?? 'Student',
                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.slate900),
                                    ),
                                    Text(
                                      s['student_code'] ?? '',
                                      style: const TextStyle(color: AppTheme.slate400, fontSize: 11, fontFamily: 'monospace'),
                                    ),
                                  ],
                                ),
                              ),
                              AnimatedContainer(
                                duration: const Duration(milliseconds: 150),
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                                decoration: BoxDecoration(
                                  color: bg,
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: color.withValues(alpha: 0.5)),
                                ),
                                child: Text(
                                  status.toUpperCase(),
                                  style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.bold),
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),

                // Save button
                Container(
                  color: Colors.white,
                  padding: const EdgeInsets.all(16),
                  child: SafeArea(
                    child: CustomButton(
                      label: 'Save & Submit Attendance ($presentCount Present)',
                      icon: Icons.cloud_done_rounded,
                      isLoading: _isSaving,
                      onPressed: _saveAttendance,
                    ),
                  ),
                ),
              ],
            ),
    );
  }

  Widget _badge(String label, String count, Color color, Color bg) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(12)),
      child: Column(
        children: [
          Text(count, style: TextStyle(color: color, fontSize: 14, fontWeight: FontWeight.bold)),
          Text(label, style: TextStyle(color: color.withValues(alpha: 0.8), fontSize: 9, fontWeight: FontWeight.bold)),
        ],
      ),
    );
  }
}
