import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import 'teacher_home_tab.dart';
import 'teacher_attendance_screen.dart';
import 'teacher_notes_screen.dart';
import 'teacher_assignments_screen.dart';
import 'teacher_leaves_screen.dart';

class TeacherNavScreen extends StatefulWidget {
  const TeacherNavScreen({super.key});

  @override
  State<TeacherNavScreen> createState() => _TeacherNavScreenState();
}

class _TeacherNavScreenState extends State<TeacherNavScreen> {
  int _currentIndex = 0;

  final List<Widget> _tabs = const [
    TeacherHomeTab(),
    TeacherAttendanceScreen(),
    TeacherNotesScreen(),
    TeacherAssignmentsScreen(),
    TeacherLeavesScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: _tabs,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border(top: BorderSide(color: AppTheme.slate100, width: 1.2)),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _navItem(0, Icons.dashboard_rounded, 'Dashboard'),
                _navItem(1, Icons.fact_check_rounded, 'Attendance'),
                _navItem(2, Icons.cloud_upload_rounded, 'Publish Notes'),
                _navItem(3, Icons.assignment_turned_in_rounded, 'Homework'),
                _navItem(4, Icons.how_to_reg_rounded, 'Leaves'),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _navItem(int index, IconData icon, String label) {
    final isSelected = _currentIndex == index;
    return InkWell(
      onTap: () => setState(() => _currentIndex = index),
      borderRadius: BorderRadius.circular(16),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? AppTheme.navyDark : Colors.transparent,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 20, color: isSelected ? AppTheme.gold : AppTheme.slate400),
            if (isSelected) ...[
              const SizedBox(width: 8),
              Text(
                label,
                style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
