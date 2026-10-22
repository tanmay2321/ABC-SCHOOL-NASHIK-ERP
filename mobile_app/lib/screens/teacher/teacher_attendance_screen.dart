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
  String _selectedClass = 'Class 10';
  final String _date = DateTime.now().toString().split(' ')[0];

  final List<String> _classList = ['Class 8', 'Class 9', 'Class 10', 'Class 11', 'Class 12'];

  @override
  void initState() {
    super.initState();
    final user = ApiService().currentUser;
    final assigned = user?.profile['assigned_class'];
    if (assigned != null && assigned.toString().isNotEmpty) {
      _selectedClass = assigned.toString();
    }
    _loadClassRoster();
  }

  Future<void> _loadClassRoster() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService().fetchAttendance(studentClass: _selectedClass, date: _date);
      if (mounted) {
        final list = res['students'] as List<dynamic>? ?? [];
        setState(() {
          _students = list.map((e) => Map<String, dynamic>.from(e)).toList();
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error loading roster: $e'), backgroundColor: AppTheme.rose),
        );
      }
    }
  }

  void _markAll(bool present) {
    setState(() {
      for (var s in _students) {
        s['attendance_status'] = present ? 'Present' : 'Absent';
      }
    });
  }

  void _toggleCheckbox(Map<String, dynamic> s, bool? isChecked) {
    setState(() {
      s['attendance_status'] = (isChecked == true) ? 'Present' : 'Absent';
    });
  }

  Future<void> _saveAttendance() async {
    setState(() => _isSaving = true);
    try {
      final payload = _students.map((s) => {
        'student_id': s['id'],
        'status': s['attendance_status'] ?? 'Present',
        'remarks': s['attendance_remarks'] ?? '',
      }).toList();

      final success = await ApiService().submitAttendance(
        studentClass: _selectedClass,
        date: _date,
        attendance: payload,
      );

      if (!mounted) return;
      setState(() => _isSaving = false);

      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Attendance for $_selectedClass saved & synced to school database!'),
            backgroundColor: AppTheme.emerald,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isSaving = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to save attendance: $e'), backgroundColor: AppTheme.rose),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final int presentCount = _students.where((s) => (s['attendance_status'] ?? 'Present') == 'Present').length;
    final int absentCount = _students.where((s) => s['attendance_status'] == 'Absent').length;

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('DAILY ATTENDANCE', style: TextStyle(color: AppTheme.gold, fontSize: 10, fontWeight: FontWeight.bold, letterSpacing: 1.2)),
            Text('Roll Call: $_selectedClass', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white)),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: Colors.white),
            tooltip: 'Reload Roster',
            onPressed: _loadClassRoster,
          ),
        ],
      ),
      body: Column(
        children: [
          // Top Filter Bar: Class Dropdown & Quick Actions
          Container(
            color: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: Row(
              children: [
                const Text('Class: ', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                const SizedBox(width: 8),
                DropdownButton<String>(
                  value: _classList.contains(_selectedClass) ? _selectedClass : _classList[0],
                  underline: const SizedBox(),
                  style: const TextStyle(color: AppTheme.navyDark, fontWeight: FontWeight.bold, fontSize: 13),
                  items: _classList.map((c) => DropdownMenuItem(value: c, child: Text(c))).toList(),
                  onChanged: (v) {
                    if (v != null) {
                      setState(() => _selectedClass = v);
                      _loadClassRoster();
                    }
                  },
                ),
                const Spacer(),
                OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    side: const BorderSide(color: AppTheme.emerald),
                  ),
                  onPressed: () => _markAll(true),
                  child: const Text('All Present', style: TextStyle(color: AppTheme.emerald, fontSize: 11, fontWeight: FontWeight.bold)),
                ),
                const SizedBox(width: 6),
                OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    side: const BorderSide(color: AppTheme.rose),
                  ),
                  onPressed: () => _markAll(false),
                  child: const Text('All Absent', style: TextStyle(color: AppTheme.rose, fontSize: 11, fontWeight: FontWeight.bold)),
                ),
              ],
            ),
          ),

          // Stats Bar
          Container(
            color: AppTheme.slate50,
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _badge('TOTAL', '${_students.length}', AppTheme.navyMedium, AppTheme.indigoLight),
                _badge('PRESENT (TICKED)', '$presentCount', AppTheme.emerald, AppTheme.emeraldLight),
                _badge('ABSENT', '$absentCount', AppTheme.rose, AppTheme.roseLight),
              ],
            ),
          ),
          const Divider(height: 1, color: AppTheme.slate200),

          // Student List with Checkboxes (Checked = Present, Unchecked = Absent)
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
                : _students.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.people_outline, size: 48, color: AppTheme.slate400),
                            const SizedBox(height: 8),
                            Text('No enrolled students in $_selectedClass.', style: const TextStyle(color: AppTheme.slate600)),
                          ],
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: _students.length,
                        itemBuilder: (ctx, idx) {
                          final s = _students[idx];
                          final isPresent = (s['attendance_status'] ?? 'Present') == 'Present';

                          return Container(
                            margin: const EdgeInsets.only(bottom: 10),
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(
                                color: isPresent ? AppTheme.emerald.withValues(alpha: 0.3) : AppTheme.rose.withValues(alpha: 0.4),
                                width: isPresent ? 1 : 1.5,
                              ),
                            ),
                            child: Row(
                              children: [
                                // Checkbox: Tick = Present, No tick = Absent (Requested Feature)
                                Transform.scale(
                                  scale: 1.15,
                                  child: Checkbox(
                                    value: isPresent,
                                    activeColor: AppTheme.emerald,
                                    checkColor: Colors.white,
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
                                    onChanged: (val) => _toggleCheckbox(s, val),
                                  ),
                                ),
                                const SizedBox(width: 6),

                                // Roll Number
                                Container(
                                  width: 34,
                                  height: 34,
                                  decoration: BoxDecoration(
                                    color: AppTheme.slate100,
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  alignment: Alignment.center,
                                  child: Text(
                                    s['roll_no']?.toString() ?? '${idx + 1}',
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: AppTheme.navyDark),
                                  ),
                                ),
                                const SizedBox(width: 12),

                                // Student Details
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        s['full_name'] ?? 'Student',
                                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.slate900),
                                      ),
                                      Text(
                                        'Code: ${s['student_code'] ?? "N/A"}',
                                        style: const TextStyle(color: AppTheme.slate400, fontSize: 11),
                                      ),
                                    ],
                                  ),
                                ),

                                // Status Badge
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: isPresent ? AppTheme.emeraldLight : AppTheme.roseLight,
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Text(
                                    isPresent ? 'PRESENT' : 'ABSENT',
                                    style: TextStyle(
                                      color: isPresent ? AppTheme.emeraldDark : AppTheme.roseDark,
                                      fontSize: 10,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
          ),

          // Bottom Save Button
          Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(top: BorderSide(color: AppTheme.slate200)),
            ),
            child: SafeArea(
              child: SizedBox(
                width: double.infinity,
                child: CustomButton(
                  label: _isSaving ? 'Submitting to Database...' : 'Save & Submit Attendance',
                  icon: Icons.cloud_upload_rounded,
                  isLoading: _isSaving,
                  onPressed: _saveAttendance,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _badge(String label, String value, Color color, Color bg) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(12)),
      child: Column(
        children: [
          Text(value, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: color)),
          Text(label, style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}
