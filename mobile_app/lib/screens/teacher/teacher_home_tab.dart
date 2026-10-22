import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../../widgets/bento_card.dart';
import '../../widgets/section_header.dart';
import 'teacher_attendance_screen.dart';
import 'teacher_notes_screen.dart';
import 'teacher_assignments_screen.dart';
import 'teacher_leaves_screen.dart';
import 'teacher_marks_screen.dart';
import '../notices_screen.dart';
import '../login_screen.dart';

class TeacherHomeTab extends StatefulWidget {
  const TeacherHomeTab({super.key});

  @override
  State<TeacherHomeTab> createState() => _TeacherHomeTabState();
}

class _TeacherHomeTabState extends State<TeacherHomeTab> {
  Map<String, dynamic>? _dashData;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadDashboard();
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

  @override
  Widget build(BuildContext context) {
    final user = ApiService().currentUser;
    final metrics = _dashData?['teacher_metrics'] ?? {};
    final schedule = _dashData?['today_schedule'] as List<dynamic>? ?? [];

    final assignedClass = metrics['assigned_class'] ?? user?.assignedClass ?? 'Class 10';
    final bool attMarked = metrics['attendance_marked_today'] == true;

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'FACULTY WORKSPACE',
              style: TextStyle(color: AppTheme.gold, fontSize: 10, fontWeight: FontWeight.bold, letterSpacing: 1.2),
            ),
            Text(
              user?.fullName ?? 'Faculty Member',
              style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_none_rounded, color: Colors.white),
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NoticesScreen())),
          ),
          IconButton(
            icon: const Icon(Icons.logout_rounded, color: AppTheme.roseLight),
            onPressed: () async {
              await ApiService().logout();
              if (context.mounted) {
                Navigator.of(context).pushAndRemoveUntil(
                  MaterialPageRoute(builder: (_) => const LoginScreen()),
                  (route) => false,
                );
              }
            },
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : RefreshIndicator(
              onRefresh: _loadDashboard,
              color: AppTheme.navyDark,
              child: ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  // Class Teacher Highlight Card
                  Container(
                    padding: const EdgeInsets.all(22),
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
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: AppTheme.gold.withValues(alpha: 0.2),
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(color: AppTheme.gold),
                              ),
                              child: Text(
                                'CLASS EDUCATOR: $assignedClass',
                                style: const TextStyle(color: AppTheme.gold, fontSize: 10, fontWeight: FontWeight.bold),
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                color: attMarked ? AppTheme.emerald.withValues(alpha: 0.2) : AppTheme.amber.withValues(alpha: 0.2),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Row(
                                children: [
                                  Icon(
                                    attMarked ? Icons.check_circle_rounded : Icons.pending_rounded,
                                    size: 12,
                                    color: attMarked ? AppTheme.emerald : AppTheme.amber,
                                  ),
                                  const SizedBox(width: 4),
                                  Text(
                                    attMarked ? 'TODAY MARKED' : 'PENDING ROLL CALL',
                                    style: TextStyle(
                                      color: attMarked ? AppTheme.emerald : AppTheme.amber,
                                      fontSize: 9,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        Text(
                          '${metrics['class_students_count'] ?? 38} Enrolled Students',
                          style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Department: ${user?.department ?? "Science & Mathematics"}',
                          style: const TextStyle(color: Colors.white70, fontSize: 12),
                        ),
                        const SizedBox(height: 18),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherAttendanceScreen())),
                            icon: const Icon(Icons.fact_check_rounded, size: 16),
                            label: Text(attMarked ? 'Review / Update Roll Call' : 'Take Daily Attendance Now'),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppTheme.gold,
                              foregroundColor: AppTheme.navyDark,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                              padding: const EdgeInsets.symmetric(vertical: 12),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),

                  // Quick Action Bento Cards
                  Row(
                    children: [
                      Expanded(
                        child: BentoCard(
                          title: 'Upload Notes',
                          subtitle: 'Publish LMS Material',
                          icon: Icons.cloud_upload_rounded,
                          iconColor: AppTheme.cyan,
                          iconBg: AppTheme.cyanLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherNotesScreen())),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: BentoCard(
                          title: 'Homework',
                          subtitle: '${metrics['pending_grading'] ?? 2} To Review',
                          icon: Icons.assignment_turned_in_rounded,
                          iconColor: AppTheme.amber,
                          iconBg: AppTheme.amberLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherAssignmentsScreen())),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 12),

                  Row(
                    children: [
                      Expanded(
                        child: BentoCard(
                          title: 'Student Leaves',
                          subtitle: 'Approve Applications',
                          icon: Icons.how_to_reg_rounded,
                          iconColor: AppTheme.purple,
                          iconBg: AppTheme.purpleLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherLeavesScreen())),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: BentoCard(
                          title: 'Marksheet Entry',
                          subtitle: 'Grade Assessments',
                          icon: Icons.grade_rounded,
                          iconColor: AppTheme.rose,
                          iconBg: AppTheme.roseLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TeacherMarksScreen())),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),

                  Row(
                    children: [
                      Expanded(
                        child: BentoCard(
                          title: 'Notice Board',
                          subtitle: 'School Announcements',
                          icon: Icons.campaign_rounded,
                          iconColor: AppTheme.emerald,
                          iconBg: AppTheme.emeraldLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NoticesScreen())),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // Teaching Schedule Today
                  SectionHeader(title: 'My Teaching Schedule Today (${DateTime.now().weekday == 7 ? "Sunday" : "Active"})'),
                  if (schedule.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: AppTheme.slate100),
                      ),
                      child: const Text('No teaching lectures assigned for today.', style: TextStyle(color: AppTheme.slate600, fontSize: 12)),
                    )
                  else
                    ...schedule.map((p) {
                      return Container(
                        margin: const EdgeInsets.only(bottom: 10),
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(18),
                          border: Border.all(color: AppTheme.slate100),
                        ),
                        child: Row(
                          children: [
                            Container(
                              width: 36,
                              height: 36,
                              decoration: BoxDecoration(
                                color: AppTheme.navyDark,
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Center(
                                child: Text('P${p['period_number']}', style: const TextStyle(color: AppTheme.gold, fontWeight: FontWeight.bold, fontSize: 12)),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(p['subject'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.slate900)),
                                  Text('${p['class'] ?? "Class 10"} • ${p['room_no'] ?? "Room 101"}', style: const TextStyle(color: AppTheme.slate400, fontSize: 11)),
                                ],
                              ),
                            ),
                            Text(p['time_slot'] ?? '', style: const TextStyle(color: AppTheme.navyMedium, fontWeight: FontWeight.bold, fontSize: 11)),
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
