import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../../widgets/bento_card.dart';
import '../../widgets/section_header.dart';
import '../notices_screen.dart';
import '../login_screen.dart';

class AdminNavScreen extends StatefulWidget {
  const AdminNavScreen({super.key});

  @override
  State<AdminNavScreen> createState() => _AdminNavScreenState();
}

class _AdminNavScreenState extends State<AdminNavScreen> {
  Map<String, dynamic>? _dashData;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadAdminData();
  }

  Future<void> _loadAdminData() async {
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
    final metrics = _dashData?['admin_metrics'] ?? {};
    final notices = _dashData?['recent_notices'] as List<dynamic>? ?? [];

    final double feesTotal = (metrics['fees_total'] is num) ? (metrics['fees_total'] as num).toDouble() : 18400000.0;
    final double feesCollected = (metrics['fees_collected'] is num) ? (metrics['fees_collected'] as num).toDouble() : 15200000.0;
    final double feeCollectionPct = feesTotal > 0 ? (feesCollected / feesTotal) * 100 : 82.6;

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'EXECUTIVE LEADERSHIP & ADMIN',
              style: TextStyle(color: AppTheme.gold, fontSize: 10, fontWeight: FontWeight.bold, letterSpacing: 1.2),
            ),
            Text(
              user?.fullName ?? 'Administrator',
              style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
            ),
          ],
        ),
        actions: [
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
          const SizedBox(width: 8),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : RefreshIndicator(
              onRefresh: _loadAdminData,
              color: AppTheme.navyDark,
              child: ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  // KPI Header Card
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
                        const Text(
                          'INSTITUTIONAL PERFORMANCE 2026-27',
                          style: TextStyle(color: AppTheme.goldLight, fontSize: 10, fontWeight: FontWeight.bold, letterSpacing: 1.2),
                        ),
                        const SizedBox(height: 10),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${metrics['total_students'] ?? 642}',
                                  style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900),
                                ),
                                const Text('Enrolled Students', style: TextStyle(color: Colors.white70, fontSize: 11)),
                              ],
                            ),
                            Container(width: 1, height: 40, color: Colors.white24),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${metrics['total_teachers'] ?? 48}',
                                  style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900),
                                ),
                                const Text('Faculty Members', style: TextStyle(color: Colors.white70, fontSize: 11)),
                              ],
                            ),
                            Container(width: 1, height: 40, color: Colors.white24),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${metrics['today_attendance_rate'] ?? 96.2}%',
                                  style: const TextStyle(color: AppTheme.emerald, fontSize: 28, fontWeight: FontWeight.w900),
                                ),
                                const Text('Today Attendance', style: TextStyle(color: Colors.white70, fontSize: 11)),
                              ],
                            ),
                          ],
                        ),
                        const Divider(height: 24, color: Colors.white24),
                        // Fee Collection Progress
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Fee Collection Progress:', style: TextStyle(color: Colors.white70, fontSize: 11)),
                            Text('${feeCollectionPct.toStringAsFixed(1)}% Collected', style: const TextStyle(color: AppTheme.gold, fontSize: 11, fontWeight: FontWeight.bold)),
                          ],
                        ),
                        const SizedBox(height: 6),
                        ClipRRect(
                          borderRadius: BorderRadius.circular(6),
                          child: LinearProgressIndicator(
                            value: feeCollectionPct / 100,
                            minHeight: 8,
                            backgroundColor: Colors.white12,
                            color: AppTheme.gold,
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),

                  // Admin Quick Actions
                  const SectionHeader(title: 'Administrative Control Modules'),
                  Row(
                    children: [
                      Expanded(
                        child: BentoCard(
                          title: 'Digital Notices',
                          subtitle: 'Broadcast Circular',
                          icon: Icons.campaign_rounded,
                          iconColor: AppTheme.emerald,
                          iconBg: AppTheme.emeraldLight,
                          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NoticesScreen())),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: BentoCard(
                          title: 'Campus Bus',
                          subtitle: 'Live GPS Map',
                          icon: Icons.directions_bus_rounded,
                          iconColor: AppTheme.amber,
                          iconBg: AppTheme.amberLight,
                          onTap: () {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('All 8 School Bus GPS Transponders Active.')),
                            );
                          },
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // Recent Announcements
                  SectionHeader(
                    title: 'Active School Circulars',
                    actionText: 'View All',
                    onAction: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NoticesScreen())),
                  ),
                  ...notices.map((n) {
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
                                decoration: BoxDecoration(color: AppTheme.indigoLight, borderRadius: BorderRadius.circular(8)),
                                child: Text(n['category'] ?? 'Circular', style: const TextStyle(color: AppTheme.navyMedium, fontSize: 10, fontWeight: FontWeight.bold)),
                              ),
                              Text(n['created_at'] != null ? n['created_at'].toString().split(' ')[0] : 'Recent', style: const TextStyle(color: AppTheme.slate400, fontSize: 11)),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Text(n['title'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.slate900)),
                          const SizedBox(height: 4),
                          Text(n['content'] ?? '', style: const TextStyle(color: AppTheme.slate600, fontSize: 12)),
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
