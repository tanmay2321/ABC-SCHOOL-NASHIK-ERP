import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../../widgets/bento_card.dart';
import '../../widgets/digital_id_card.dart';
import '../../widgets/section_header.dart';
import 'student_id_card_screen.dart';
import 'student_lms_screen.dart';
import 'student_attendance_screen.dart';
import 'student_fees_screen.dart';
import 'student_marks_screen.dart';
import 'student_timetable_screen.dart';
import 'student_transport_screen.dart';
import 'student_leave_screen.dart';
import '../notices_screen.dart';

class StudentHomeTab extends StatefulWidget {
  const StudentHomeTab({super.key});

  @override
  State<StudentHomeTab> createState() => _StudentHomeTabState();
}

class _StudentHomeTabState extends State<StudentHomeTab> {
  Map<String, dynamic>? _dashboardData;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadDashboard();
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

  @override
  Widget build(BuildContext context) {
    final user = ApiService().currentUser;
    final metrics = _dashboardData?['student_metrics'] ?? {};
    final schedule = _dashboardData?['today_schedule'] as List<dynamic>? ?? [];
    final notices = _dashboardData?['recent_notices'] as List<dynamic>? ?? [];

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
            Text(
              'Welcome, ${user?.fullName ?? "Student"}',
              style: const TextStyle(
                color: Colors.white,
                fontSize: 16,
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_none_rounded, color: Colors.white),
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NoticesScreen())),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : RefreshIndicator(
              onRefresh: _loadDashboard,
              color: AppTheme.navyDark,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Digital Smart ID Card
                    if (user != null) ...[
                      DigitalIdCard(user: user),
                      const SizedBox(height: 20),
                    ],

                    // Top Bento Metrics (Attendance Gauge & Fee Alert)
                    Row(
                      children: [
                        // Attendance Bento Card
                        Expanded(
                          child: BentoCard(
                            title: 'Attendance',
                            subtitle: 'Term Overview',
                            icon: Icons.pie_chart_rounded,
                            iconColor: AppTheme.emerald,
                            iconBg: AppTheme.emeraldLight,
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentAttendanceScreen())),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Text(
                                      '$attendancePct%',
                                      style: const TextStyle(
                                        color: AppTheme.slate900,
                                        fontSize: 22,
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                    const SizedBox(width: 6),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                      decoration: BoxDecoration(
                                        color: AppTheme.emeraldLight,
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: const Text('GOOD', style: TextStyle(color: AppTheme.emerald, fontSize: 9, fontWeight: FontWeight.bold)),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  'Present: ${metrics['attendance_present'] ?? 23} / ${metrics['attendance_total'] ?? 25} Days',
                                  style: const TextStyle(color: AppTheme.slate600, fontSize: 11),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),

                        // Fee Status Bento Card
                        Expanded(
                          child: BentoCard(
                            title: 'Fee Dues',
                            subtitle: 'Term 1 Balance',
                            icon: Icons.account_balance_wallet_rounded,
                            iconColor: feeDue > 0 ? AppTheme.rose : AppTheme.emerald,
                            iconBg: feeDue > 0 ? AppTheme.roseLight : AppTheme.emeraldLight,
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentFeesScreen())),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '₹${feeDue.toStringAsFixed(0)}',
                                  style: TextStyle(
                                    color: feeDue > 0 ? AppTheme.rose : AppTheme.emerald,
                                    fontSize: 20,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Row(
                                  children: [
                                    Text(
                                      feeDue > 0 ? 'Pay Online' : 'All Clear',
                                      style: TextStyle(
                                        color: feeDue > 0 ? AppTheme.rose : AppTheme.emerald,
                                        fontSize: 11,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                    const SizedBox(width: 4),
                                    Icon(
                                      Icons.arrow_forward_rounded,
                                      size: 12,
                                      color: feeDue > 0 ? AppTheme.rose : AppTheme.emerald,
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 20),

                    // Quick Actions Grid
                    const SectionHeader(title: 'Quick Actions & Services'),
                    GridView.count(
                      crossAxisCount: 4,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      mainAxisSpacing: 12,
                      crossAxisSpacing: 10,
                      childAspectRatio: 0.74,
                      children: [
                        _quickAction(
                          Icons.badge_rounded,
                          'Smart ID',
                          AppTheme.navyMedium,
                          AppTheme.indigoLight,
                          () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentIdCardScreen())),
                        ),
                        _quickAction(
                          Icons.auto_stories_rounded,
                          'LMS Notes',
                          AppTheme.cyan,
                          AppTheme.cyanLight,
                          () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentLmsScreen())),
                        ),
                        _quickAction(
                          Icons.assignment_rounded,
                          'Homework',
                          AppTheme.amber,
                          AppTheme.amberLight,
                          () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentLmsScreen())),
                        ),
                        _quickAction(
                          Icons.leaderboard_rounded,
                          'Marksheet',
                          AppTheme.purple,
                          AppTheme.purpleLight,
                          () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentMarksScreen())),
                        ),
                        _quickAction(
                          Icons.calendar_month_rounded,
                          'Timetable',
                          AppTheme.navyMedium,
                          AppTheme.indigoLight,
                          () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentTimetableScreen())),
                        ),
                        _quickAction(
                          Icons.directions_bus_rounded,
                          'Bus Tracker',
                          const Color(0xFFD97706),
                          const Color(0xFFFEF3C7),
                          () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentTransportScreen())),
                        ),
                        _quickAction(
                          Icons.time_to_leave_rounded,
                          'Apply Leave',
                          AppTheme.rose,
                          AppTheme.roseLight,
                          () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentLeaveScreen())),
                        ),
                        _quickAction(
                          Icons.campaign_rounded,
                          'Notices',
                          AppTheme.emerald,
                          AppTheme.emeraldLight,
                          () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NoticesScreen())),
                        ),
                      ],
                    ),

                    const SizedBox(height: 20),

                    // Today's Class Timetable Schedule
                    SectionHeader(
                      title: 'Today’s Period Schedule (${DateTime.now().weekday == 7 ? "Sunday" : "Active"})',
                      actionText: 'View Week',
                      onAction: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const StudentTimetableScreen())),
                    ),
                    if (schedule.isEmpty)
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: AppTheme.slate100),
                        ),
                        child: const Row(
                          children: [
                            Icon(Icons.info_outline, color: AppTheme.slate400),
                            SizedBox(width: 10),
                            Text('No active classes scheduled today.', style: TextStyle(color: AppTheme.slate600, fontSize: 13)),
                          ],
                        ),
                      )
                    else
                      Column(
                        children: schedule.take(4).map((p) {
                          final isFirst = p['period_number'] == 1;
                          return Container(
                            margin: const EdgeInsets.only(bottom: 10),
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(
                                color: isFirst ? AppTheme.navyMedium : AppTheme.slate100,
                                width: isFirst ? 1.5 : 1,
                              ),
                            ),
                            child: Row(
                              children: [
                                Container(
                                  width: 40,
                                  height: 40,
                                  decoration: BoxDecoration(
                                    color: isFirst ? AppTheme.navyDark : AppTheme.slate50,
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: Center(
                                    child: Text(
                                      'P${p['period_number']}',
                                      style: TextStyle(
                                        color: isFirst ? AppTheme.gold : AppTheme.slate700,
                                        fontWeight: FontWeight.bold,
                                        fontSize: 13,
                                      ),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 14),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        p['subject'] ?? '',
                                        style: const TextStyle(
                                          color: AppTheme.slate900,
                                          fontSize: 14,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        '${p['teacher_name']} • ${p['room_no'] ?? "Room 101"}',
                                        style: const TextStyle(color: AppTheme.slate400, fontSize: 11),
                                      ),
                                    ],
                                  ),
                                ),
                                Text(
                                  p['time_slot'] ?? '',
                                  style: const TextStyle(
                                    color: AppTheme.navyMedium,
                                    fontSize: 11,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ],
                            ),
                          );
                        }).toList(),
                      ),

                    const SizedBox(height: 20),

                    // Recent School Circulars & Notices
                    SectionHeader(
                      title: 'School Circulars & Digital Diary',
                      actionText: 'All Notices',
                      onAction: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NoticesScreen())),
                    ),
                    if (notices.isNotEmpty)
                      ...notices.take(2).map((n) {
                        return Container(
                          margin: const EdgeInsets.only(bottom: 10),
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
                                    decoration: BoxDecoration(
                                      color: AppTheme.indigoLight,
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Text(
                                      n['category'] ?? 'Notice',
                                      style: const TextStyle(
                                        color: AppTheme.navyMedium,
                                        fontSize: 10,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                  Text(
                                    n['created_at'] != null ? n['created_at'].toString().split(' ')[0] : 'Today',
                                    style: const TextStyle(color: AppTheme.slate400, fontSize: 11),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Text(
                                n['title'] ?? '',
                                style: const TextStyle(
                                  color: AppTheme.slate900,
                                  fontSize: 14,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                n['content'] ?? '',
                                style: const TextStyle(color: AppTheme.slate600, fontSize: 12),
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                          ),
                        );
                      }),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _quickAction(IconData icon, String label, Color color, Color bg, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: FittedBox(
        fit: BoxFit.scaleDown,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: bg,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(icon, color: color, size: 24),
              ),
              const SizedBox(height: 6),
              Text(
                label,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: AppTheme.slate900,
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
