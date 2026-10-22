import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../../widgets/notification_modal.dart';
import 'student_id_card_screen.dart';
import 'student_lms_screen.dart';
import 'student_attendance_screen.dart';
import 'student_fees_screen.dart';
import 'student_marks_screen.dart';
import 'student_timetable_screen.dart';
import 'student_leave_screen.dart';
import 'student_profile_screen.dart';
import '../notices_screen.dart';

class StudentHomeTab extends StatefulWidget {
  const StudentHomeTab({super.key});

  @override
  State<StudentHomeTab> createState() => _StudentHomeTabState();
}

class _StudentHomeTabState extends State<StudentHomeTab> {
  Map<String, dynamic>? _dashboardData;
  int _unreadNotifs = 0;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadDashboard();
    _loadNotificationsCount();
  }

  Future<void> _loadDashboard() async {
    final data = await ApiService().fetchDashboard();
    if (mounted) {
      setState(() {
        _dashboardData = data;
        _isLoading = false;
      });
    }
  }

  Future<void> _loadNotificationsCount() async {
    final res = await ApiService().fetchNotifications();
    if (mounted) {
      setState(() {
        _unreadNotifs = res['unread_count'] as int? ?? 0;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ApiService().currentUser;
    final metrics = _dashboardData?['student_metrics'] ?? {};

    final double attendancePct = (metrics['attendance_percentage'] is num)
        ? (metrics['attendance_percentage'] as num).toDouble()
        : 92.4;
    final double feeDue = (metrics['fee_due'] is num)
        ? (metrics['fee_due'] as num).toDouble()
        : 26000.0;

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'BELLWETHER INTERNATIONAL SCHOOL',
              style: TextStyle(
                color: AppTheme.gold,
                fontSize: 10,
                fontWeight: FontWeight.w900,
                letterSpacing: 1.2,
              ),
            ),
            const Text(
              'BWIS Smart ERP',
              style: TextStyle(
                color: Colors.white,
                fontSize: 16,
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
        ),
        actions: [
          // Notification Bell with Unread Badge
          IconButton(
            icon: Stack(
              clipBehavior: Clip.none,
              children: [
                const Icon(Icons.notifications_rounded, color: Colors.white, size: 24),
                if (_unreadNotifs > 0)
                  Positioned(
                    right: -2,
                    top: -2,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: const BoxDecoration(
                        color: AppTheme.rose,
                        shape: BoxShape.circle,
                      ),
                      child: Text(
                        '$_unreadNotifs',
                        style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ),
              ],
            ),
            onPressed: () {
              NotificationModal.show(context, onDismiss: () {
                _loadNotificationsCount();
              });
            },
          ),
          // Profile Shortcut Avatar
          InkWell(
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentProfileScreen())),
            borderRadius: BorderRadius.circular(20),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 10),
              child: CircleAvatar(
                radius: 17,
                backgroundColor: AppTheme.gold,
                child: Text(
                  (user?.fullName.isNotEmpty ?? false) ? user!.fullName[0].toUpperCase() : 'S',
                  style: const TextStyle(color: AppTheme.navyDark, fontWeight: FontWeight.bold, fontSize: 14),
                ),
              ),
            ),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : RefreshIndicator(
              onRefresh: () async {
                await _loadDashboard();
                await _loadNotificationsCount();
              },
              color: AppTheme.navyDark,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // PROFILE SECTION ON DASHBOARD HEADER (Requested!)
                    InkWell(
                      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentProfileScreen())),
                      borderRadius: BorderRadius.circular(24),
                      child: Container(
                        padding: const EdgeInsets.all(18),
                        decoration: BoxDecoration(
                          gradient: AppTheme.navyHeroGradient,
                          borderRadius: BorderRadius.circular(24),
                          boxShadow: [
                            BoxShadow(
                              color: AppTheme.navyDark.withValues(alpha: 0.25),
                              blurRadius: 18,
                              offset: const Offset(0, 8),
                            ),
                          ],
                        ),
                        child: Row(
                          children: [
                            // Profile Picture with Online Status Ring
                            Stack(
                              alignment: Alignment.bottomRight,
                              children: [
                                Container(
                                  width: 64,
                                  height: 64,
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    border: Border.all(color: AppTheme.gold, width: 2.5),
                                    color: AppTheme.navyMedium,
                                  ),
                                  child: Center(
                                    child: Text(
                                      (user?.fullName.isNotEmpty ?? false) ? user!.fullName[0].toUpperCase() : 'S',
                                      style: const TextStyle(color: AppTheme.gold, fontSize: 26, fontWeight: FontWeight.bold),
                                    ),
                                  ),
                                ),
                                Container(
                                  padding: const EdgeInsets.all(3),
                                  decoration: const BoxDecoration(
                                    color: AppTheme.emerald,
                                    shape: BoxShape.circle,
                                  ),
                                  child: const Icon(Icons.check, size: 10, color: Colors.white),
                                ),
                              ],
                            ),
                            const SizedBox(width: 14),

                            // Student Name, Class, Division, Roll No Header Info
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          user?.fullName ?? 'Student',
                                          style: const TextStyle(
                                            color: Colors.white,
                                            fontSize: 17,
                                            fontWeight: FontWeight.bold,
                                          ),
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ),
                                      const Icon(Icons.chevron_right_rounded, color: Colors.white60, size: 20),
                                    ],
                                  ),
                                  const SizedBox(height: 6),
                                  Wrap(
                                    spacing: 6,
                                    runSpacing: 4,
                                    children: [
                                      // Class & Division Badge
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                        decoration: BoxDecoration(
                                          color: AppTheme.gold.withValues(alpha: 0.2),
                                          borderRadius: BorderRadius.circular(8),
                                          border: Border.all(color: AppTheme.gold.withValues(alpha: 0.5)),
                                        ),
                                        child: Text(
                                          user?.studentClass ?? 'Class 10-A',
                                          style: const TextStyle(color: AppTheme.goldLight, fontSize: 10, fontWeight: FontWeight.bold),
                                        ),
                                      ),
                                      // Roll No Badge
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                        decoration: BoxDecoration(
                                          color: Colors.white.withValues(alpha: 0.15),
                                          borderRadius: BorderRadius.circular(8),
                                        ),
                                        child: Text(
                                          'Roll #${user?.rollNo ?? "1"}',
                                          style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                                        ),
                                      ),
                                      // Student Code
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                        decoration: BoxDecoration(
                                          color: Colors.white.withValues(alpha: 0.1),
                                          borderRadius: BorderRadius.circular(8),
                                        ),
                                        child: Text(
                                          user?.userCode.isNotEmpty == true ? user!.userCode : 'STU-BWIS',
                                          style: const TextStyle(color: Colors.white70, fontSize: 10),
                                        ),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                    const SizedBox(height: 16),

                    // Quick Glance Mini Stats (Attendance & Fees)
                    Row(
                      children: [
                        Expanded(
                          child: InkWell(
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentAttendanceScreen())),
                            borderRadius: BorderRadius.circular(16),
                            child: Container(
                              padding: const EdgeInsets.all(14),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(color: AppTheme.slate100),
                              ),
                              child: Row(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.all(8),
                                    decoration: BoxDecoration(color: AppTheme.emeraldLight, borderRadius: BorderRadius.circular(10)),
                                    child: const Icon(Icons.check_circle_rounded, color: AppTheme.emerald, size: 20),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        const Text('Attendance', style: TextStyle(color: AppTheme.slate400, fontSize: 10, fontWeight: FontWeight.bold)),
                                        Text('$attendancePct%', style: const TextStyle(color: AppTheme.slate900, fontSize: 16, fontWeight: FontWeight.bold)),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: InkWell(
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentFeesScreen())),
                            borderRadius: BorderRadius.circular(16),
                            child: Container(
                              padding: const EdgeInsets.all(14),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(color: AppTheme.slate100),
                              ),
                              child: Row(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.all(8),
                                    decoration: BoxDecoration(color: AppTheme.amberLight, borderRadius: BorderRadius.circular(10)),
                                    child: const Icon(Icons.account_balance_wallet_rounded, color: AppTheme.amber, size: 20),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        const Text('Fee Status', style: TextStyle(color: AppTheme.slate400, fontSize: 10, fontWeight: FontWeight.bold)),
                                        Text(feeDue <= 0 ? 'Fully Paid' : '₹${feeDue.toInt()} Due', style: TextStyle(color: feeDue <= 0 ? AppTheme.emerald : AppTheme.rose, fontSize: 14, fontWeight: FontWeight.bold)),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 20),

                    // ALL ERP FEATURES HUB (Requested: Neat, clean, not complex, click to visit all)
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'ERP Modules & Services',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                            color: AppTheme.slate900,
                          ),
                        ),
                        Text(
                          'Tap to open',
                          style: TextStyle(fontSize: 11, color: AppTheme.slate400, fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    // Grid of all ERP modules
                    GridView.count(
                      crossAxisCount: 2,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 12,
                      childAspectRatio: 1.35,
                      children: [
                        _moduleCard(
                          title: 'Attendance',
                          subtitle: 'Daily Roll & History',
                          icon: Icons.calendar_today_rounded,
                          color: AppTheme.emerald,
                          bg: AppTheme.emeraldLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentAttendanceScreen())),
                        ),
                        _moduleCard(
                          title: 'LMS Curriculum',
                          subtitle: 'Subjects & Teachers',
                          icon: Icons.local_library_rounded,
                          color: AppTheme.navyDark,
                          bg: AppTheme.indigoLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentLmsScreen())),
                        ),
                        _moduleCard(
                          title: 'Homework & Notes',
                          subtitle: 'Assignments & Docs',
                          icon: Icons.assignment_rounded,
                          color: AppTheme.purple,
                          bg: AppTheme.purpleLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentLmsScreen())),
                        ),
                        _moduleCard(
                          title: 'Exam Results',
                          subtitle: 'Marks & Report Cards',
                          icon: Icons.assessment_rounded,
                          color: AppTheme.amber,
                          bg: AppTheme.amberLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentMarksScreen())),
                        ),
                        _moduleCard(
                          title: 'Fee Payments',
                          subtitle: 'Invoices & Receipts',
                          icon: Icons.receipt_long_rounded,
                          color: AppTheme.cyan,
                          bg: AppTheme.cyanLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentFeesScreen())),
                        ),
                        _moduleCard(
                          title: 'Digital ID Card',
                          subtitle: 'Student Identity Pass',
                          icon: Icons.badge_rounded,
                          color: AppTheme.gold,
                          bg: AppTheme.goldLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentIdCardScreen())),
                        ),
                        _moduleCard(
                          title: 'Class Timetable',
                          subtitle: 'Periods & Schedule',
                          icon: Icons.schedule_rounded,
                          color: AppTheme.navyMedium,
                          bg: AppTheme.indigoLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentTimetableScreen())),
                        ),
                        _moduleCard(
                          title: 'School Circulars',
                          subtitle: 'Notices & Alerts',
                          icon: Icons.campaign_rounded,
                          color: AppTheme.rose,
                          bg: AppTheme.roseLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NoticesScreen())),
                        ),
                        _moduleCard(
                          title: 'Leave Request',
                          subtitle: 'Medical & Absence',
                          icon: Icons.event_busy_rounded,
                          color: AppTheme.emerald,
                          bg: AppTheme.emeraldLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentLeaveScreen())),
                        ),
                        _moduleCard(
                          title: 'Student Profile',
                          subtitle: 'Records & Identity',
                          icon: Icons.person_rounded,
                          color: AppTheme.slate800,
                          bg: AppTheme.slate100,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentProfileScreen())),
                        ),
                      ],
                    ),

                    const SizedBox(height: 24),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _moduleCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
    required Color bg,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: AppTheme.slate100),
          boxShadow: [
            BoxShadow(
              color: AppTheme.navyDark.withValues(alpha: 0.04),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: bg,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(icon, color: color, size: 20),
                ),
                Icon(Icons.arrow_forward_rounded, color: AppTheme.slate300, size: 16),
              ],
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                    color: AppTheme.slate900,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: AppTheme.slate400,
                    fontSize: 10,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
