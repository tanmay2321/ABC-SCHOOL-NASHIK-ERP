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
  String _selectedClass = 'Class 10-A';
  DateTime _selectedDate = DateTime.now();

  List<String> _classList = ApiService.standardClasses;

  String get _formattedDate =>
      '${_selectedDate.year}-${_selectedDate.month.toString().padLeft(2, '0')}-${_selectedDate.day.toString().padLeft(2, '0')}';

  String get _displayDate {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return '${_selectedDate.day} ${months[_selectedDate.month - 1]} ${_selectedDate.year}';
  }

  @override
  void initState() {
    super.initState();
    final user = ApiService().currentUser;
    final assigned = user?.profile['assigned_class'];
    if (assigned != null && assigned.toString().isNotEmpty) {
      _selectedClass = assigned.toString();
    }
    _loadClassesAndRoster();
  }

  Future<void> _loadClassesAndRoster() async {
    final classes = await ApiService().fetchClasses();
    if (mounted) {
      setState(() {
        _classList = classes;
        if (!_classList.contains(_selectedClass)) {
          _selectedClass = _classList.isNotEmpty ? _classList[0] : 'Class 10-A';
        }
      });
      _loadClassRoster();
    }
  }

  Future<void> _loadClassRoster() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService().fetchAttendance(studentClass: _selectedClass, date: _formattedDate);
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

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime(2024),
      lastDate: DateTime.now().add(const Duration(days: 1)),
      builder: (context, child) {
        return Theme(
          data: ThemeData.light().copyWith(
            colorScheme: const ColorScheme.light(
              primary: AppTheme.navyDark,
              onPrimary: Colors.white,
              onSurface: AppTheme.slate900,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null && picked != _selectedDate) {
      setState(() => _selectedDate = picked);
      _loadClassRoster();
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
        date: _formattedDate,
        attendance: payload,
      );

      if (!mounted) return;
      setState(() => _isSaving = false);

      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Attendance for $_selectedClass on $_displayDate saved & synced!'),
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
            const Text('DATE-WISE ATTENDANCE', style: TextStyle(color: AppTheme.gold, fontSize: 10, fontWeight: FontWeight.bold, letterSpacing: 1.2)),
            Text('Roll Call: $_selectedClass', style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Colors.white)),
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
          // Date Selector Bar (Teacher picks any date!)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: Colors.white,
              border: Border(bottom: BorderSide(color: AppTheme.slate200)),
            ),
            child: Row(
              children: [
                Expanded(
                  child: InkWell(
                    onTap: _pickDate,
                    borderRadius: BorderRadius.circular(12),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      decoration: BoxDecoration(
                        color: AppTheme.navyDark.withValues(alpha: 0.06),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppTheme.navyDark.withValues(alpha: 0.2)),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.calendar_month_rounded, color: AppTheme.navyDark, size: 18),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Attendance Date (Tap to change)', style: TextStyle(color: AppTheme.slate500, fontSize: 10, fontWeight: FontWeight.w600)),
                                Text(_displayDate, style: const TextStyle(color: AppTheme.navyDark, fontSize: 13, fontWeight: FontWeight.bold)),
                              ],
                            ),
                          ),
                          const Icon(Icons.arrow_drop_down, color: AppTheme.navyDark),
                        ],
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                TextButton(
                  onPressed: () {
                    setState(() => _selectedDate = DateTime.now());
                    _loadClassRoster();
                  },
                  style: TextButton.styleFrom(
                    backgroundColor: _formattedDate == DateTime.now().toString().split(' ')[0] ? AppTheme.goldLight : Colors.transparent,
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                  ),
                  child: const Text('Today', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: AppTheme.navyDark)),
                ),
              ],
            ),
          ),

          // Division & Bulk Action Bar
          Container(
            color: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  const Text('Class: ', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                  const SizedBox(width: 6),
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
                  const SizedBox(width: 14),
                  OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      side: const BorderSide(color: AppTheme.emerald),
                      visualDensity: VisualDensity.compact,
                    ),
                    icon: const Icon(Icons.check, size: 14, color: AppTheme.emerald),
                    label: const Text('All Present', style: TextStyle(color: AppTheme.emerald, fontSize: 11, fontWeight: FontWeight.bold)),
                    onPressed: () => _markAll(true),
                  ),
                  const SizedBox(width: 8),
                  OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      side: const BorderSide(color: AppTheme.rose),
                      visualDensity: VisualDensity.compact,
                    ),
                    icon: const Icon(Icons.close, size: 14, color: AppTheme.rose),
                    label: const Text('All Absent', style: TextStyle(color: AppTheme.rose, fontSize: 11, fontWeight: FontWeight.bold)),
                    onPressed: () => _markAll(false),
                  ),
                ],
              ),
            ),
          ),

          // Responsive Stats Bar
          Container(
            color: AppTheme.slate50,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Row(
              children: [
                _badge('TOTAL', '${_students.length}', AppTheme.navyMedium, AppTheme.indigoLight),
                const SizedBox(width: 8),
                _badge('PRESENT (TICKED)', '$presentCount', AppTheme.emerald, AppTheme.emeraldLight),
                const SizedBox(width: 8),
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
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                        itemCount: _students.length,
                        itemBuilder: (ctx, idx) {
                          final s = _students[idx];
                          final isPresent = (s['attendance_status'] ?? 'Present') == 'Present';

                          return Container(
                            margin: const EdgeInsets.only(bottom: 8),
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
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
                                // Tick Box
                                Checkbox(
                                  value: isPresent,
                                  activeColor: AppTheme.emerald,
                                  checkColor: Colors.white,
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
                                  onChanged: (val) => _toggleCheckbox(s, val),
                                ),
                                const SizedBox(width: 4),

                                // Roll Number
                                Container(
                                  width: 32,
                                  height: 32,
                                  decoration: BoxDecoration(
                                    color: AppTheme.slate100,
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  alignment: Alignment.center,
                                  child: Text(
                                    s['roll_no']?.toString().isNotEmpty == true ? s['roll_no'].toString() : '${idx + 1}',
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: AppTheme.navyDark),
                                  ),
                                ),
                                const SizedBox(width: 10),

                                // Student Details (Expanded to prevent overflow)
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        s['full_name'] ?? 'Student',
                                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.slate900),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                      Text(
                                        'Code: ${s['student_code'] ?? "N/A"}',
                                        style: const TextStyle(color: AppTheme.slate400, fontSize: 10),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(width: 8),

                                // Status Badge
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: isPresent ? AppTheme.emeraldLight : AppTheme.roseLight,
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Text(
                                    isPresent ? 'PRESENT' : 'ABSENT',
                                    style: TextStyle(
                                      color: isPresent ? AppTheme.emerald : AppTheme.rose,
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
            padding: const EdgeInsets.all(14),
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(top: BorderSide(color: AppTheme.slate200)),
            ),
            child: SafeArea(
              child: SizedBox(
                width: double.infinity,
                child: CustomButton(
                  label: _isSaving ? 'Submitting to Database...' : 'Save & Submit Attendance for $_displayDate',
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
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 6),
        decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(10)),
        child: FittedBox(
          fit: BoxFit.scaleDown,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: Column(
              children: [
                Text(value, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: color)),
                Text(label, style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.bold)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
