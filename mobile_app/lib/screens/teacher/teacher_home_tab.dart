import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../../widgets/notification_modal.dart';
import 'teacher_attendance_screen.dart';
import 'teacher_notes_screen.dart';
import 'teacher_assignments_screen.dart';
import 'teacher_leaves_screen.dart';
import 'teacher_marks_screen.dart';
import 'teacher_profile_screen.dart';
import '../notices_screen.dart';

class TeacherHomeTab extends StatefulWidget {
  const TeacherHomeTab({super.key});

  @override
  State<TeacherHomeTab> createState() => _TeacherHomeTabState();
}

class _TeacherHomeTabState extends State<TeacherHomeTab> {
  Map<String, dynamic>? _dashData;
  int _unreadNotifs = 0;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadDashboard();
    _loadNotifications();
  }

  Future<void> _loadDashboard() async {
    final res = await ApiService().fetchDashboard();
    if (mounted) {
      setState(() {
        _dashData = res;
        _isLoading = false;
      });
    }
  }

  Future<void> _loadNotifications() async {
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
    final metrics = _dashData?['teacher_metrics'] ?? {};

    final assignedClass = metrics['assigned_class'] ?? user?.assignedClass ?? 'Class 10-A';
    final dept = user?.profile['department']?.toString() ?? 'Academics & Science';

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'BELLWETHER INTERNATIONAL SCHOOL',
              style: TextStyle(color: AppTheme.gold, fontSize: 10, fontWeight: FontWeight.bold, letterSpacing: 1.2),
            ),
            const Text(
              'Faculty Workspace',
              style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
            ),
          ],
        ),
        actions: [
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
                _loadNotifications();
              });
            },
          ),
          InkWell(
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherProfileScreen())),
            borderRadius: BorderRadius.circular(20),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 10),
              child: CircleAvatar(
                radius: 17,
                backgroundColor: AppTheme.gold,
                child: Text(
                  (user?.fullName.isNotEmpty ?? false) ? user!.fullName[0].toUpperCase() : 'T',
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
                await _loadNotifications();
              },
              color: AppTheme.navyDark,
              child: ListView(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                children: [
                  // TEACHER PROFILE HEADER SECTION (Requested!)
                  InkWell(
                    onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherProfileScreen())),
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
                                    (user?.fullName.isNotEmpty ?? false) ? user!.fullName[0].toUpperCase() : 'T',
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
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        user?.fullName ?? 'Faculty Member',
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
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                      decoration: BoxDecoration(
                                        color: AppTheme.gold.withValues(alpha: 0.2),
                                        borderRadius: BorderRadius.circular(8),
                                        border: Border.all(color: AppTheme.gold.withValues(alpha: 0.5)),
                                      ),
                                      child: Text(
                                        'Class: $assignedClass',
                                        style: const TextStyle(color: AppTheme.goldLight, fontSize: 10, fontWeight: FontWeight.bold),
                                      ),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                      decoration: BoxDecoration(
                                        color: Colors.white.withValues(alpha: 0.15),
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                      child: Text(
                                        dept,
                                        style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
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

                  // Quick Action Highlight: Date-Wise Attendance Callout
                  InkWell(
                    onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherAttendanceScreen())),
                    borderRadius: BorderRadius.circular(18),
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(color: AppTheme.emerald.withValues(alpha: 0.3)),
                        boxShadow: [
                          BoxShadow(
                            color: AppTheme.emerald.withValues(alpha: 0.08),
                            blurRadius: 12,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: AppTheme.emeraldLight,
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: const Icon(Icons.how_to_reg_rounded, color: AppTheme.emerald, size: 24),
                          ),
                          const SizedBox(width: 14),
                          const Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Date-Wise Student Attendance',
                                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.slate900),
                                ),
                                SizedBox(height: 2),
                                Text(
                                  'Select any date to mark or update roll call for class divisions',
                                  style: TextStyle(color: AppTheme.slate500, fontSize: 11),
                                ),
                              ],
                            ),
                          ),
                          const Icon(Icons.arrow_forward_ios_rounded, color: AppTheme.emerald, size: 16),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 20),

                  // ALL ERP OPTIONS GRID FOR TEACHERS (Requested!)
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Faculty ERP Modules',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.slate900,
                        ),
                      ),
                      Text(
                        'Click to visit',
                        style: TextStyle(fontSize: 11, color: AppTheme.slate400, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),

                  GridView.count(
                    crossAxisCount: 2,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                    childAspectRatio: 1.35,
                    children: [
                      _card(
                        title: 'Date Roll Call',
                        subtitle: 'Mark & Update Attendance',
                        icon: Icons.calendar_month_rounded,
                        color: AppTheme.emerald,
                        bg: AppTheme.emeraldLight,
                        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherAttendanceScreen())),
                      ),
                      _card(
                        title: 'Homework & Sol.',
                        subtitle: 'Worksheets & Answer Key',
                        icon: Icons.assignment_rounded,
                        color: AppTheme.purple,
                        bg: AppTheme.purpleLight,
                        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherAssignmentsScreen())),
                      ),
                      _card(
                        title: 'Check Submissions',
                        subtitle: 'Grade Homework & Alert',
                        icon: Icons.grading_rounded,
                        color: AppTheme.amber,
                        bg: AppTheme.amberLight,
                        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherAssignmentsScreen())),
                      ),
                      _card(
                        title: 'Upload Notes',
                        subtitle: 'Study Material & Docs',
                        icon: Icons.menu_book_rounded,
                        color: AppTheme.cyan,
                        bg: AppTheme.cyanLight,
                        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherNotesScreen())),
                      ),
                      _card(
                        title: 'Enter Marks',
                        subtitle: 'Multi-Subject Marksheet',
                        icon: Icons.grade_rounded,
                        color: AppTheme.navyDark,
                        bg: AppTheme.indigoLight,
                        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherMarksScreen())),
                      ),
                      _card(
                        title: 'Leave Portal',
                        subtitle: 'Approve & Apply Leaves',
                        icon: Icons.event_available_rounded,
                        color: AppTheme.rose,
                        bg: AppTheme.roseLight,
                        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherLeavesScreen())),
                      ),
                      _card(
                        title: 'School Circulars',
                        subtitle: 'Notices & Announcements',
                        icon: Icons.campaign_rounded,
                        color: AppTheme.gold,
                        bg: AppTheme.goldLight,
                        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NoticesScreen())),
                      ),
                      _card(
                        title: 'Faculty Profile',
                        subtitle: 'Identity & Details',
                        icon: Icons.person_rounded,
                        color: AppTheme.slate800,
                        bg: AppTheme.slate100,
                        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherProfileScreen())),
                      ),
                    ],
                  ),

                  const SizedBox(height: 24),
                ],
              ),
            ),
    );
  }

  Widget _card({
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
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.slate900),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: const TextStyle(color: AppTheme.slate400, fontSize: 10),
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
