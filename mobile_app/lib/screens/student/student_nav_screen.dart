import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import 'student_home_tab.dart';
import 'student_lms_screen.dart';
import 'student_fees_screen.dart';
import 'student_timetable_screen.dart';
import 'student_profile_screen.dart';

class StudentNavScreen extends StatefulWidget {
  const StudentNavScreen({super.key});

  @override
  State<StudentNavScreen> createState() => _StudentNavScreenState();
}

class _StudentNavScreenState extends State<StudentNavScreen> {
  int _currentIndex = 0;

  final List<Widget> _tabs = const [
    StudentHomeTab(),
    StudentLmsScreen(),
    StudentFeesScreen(),
    StudentTimetableScreen(),
    StudentProfileScreen(),
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
          boxShadow: [
            BoxShadow(
              color: AppTheme.navyDark.withValues(alpha: 0.05),
              blurRadius: 16,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _navItem(0, Icons.grid_view_rounded, 'Dashboard'),
                _navItem(1, Icons.auto_stories_rounded, 'LMS Notes'),
                _navItem(2, Icons.account_balance_wallet_rounded, 'Fee Portal'),
                _navItem(3, Icons.calendar_month_rounded, 'Timetable'),
                _navItem(4, Icons.person_rounded, 'Profile'),
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
            Icon(
              icon,
              size: 20,
              color: isSelected ? AppTheme.gold : AppTheme.slate400,
            ),
            if (isSelected) ...[
              const SizedBox(width: 8),
              Text(
                label,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 12,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
