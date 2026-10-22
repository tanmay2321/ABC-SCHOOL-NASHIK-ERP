import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';

class StudentAttendanceScreen extends StatefulWidget {
  const StudentAttendanceScreen({super.key});

  @override
  State<StudentAttendanceScreen> createState() => _StudentAttendanceScreenState();
}

class _StudentAttendanceScreenState extends State<StudentAttendanceScreen> {
  Map<String, dynamic>? _data;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadAttendance();
  }

  Future<void> _loadAttendance() async {
    final res = await ApiService().fetchAttendance();
    if (mounted) {
      setState(() {
        _data = res;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final summary = _data?['summary'] ?? {};
    final records = _data?['records'] as List<dynamic>? ?? [];

    final double pct = (summary['percentage'] is num)
        ? (summary['percentage'] as num).toDouble()
        : 92.0;

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('My Attendance Record'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : RefreshIndicator(
              onRefresh: _loadAttendance,
              color: AppTheme.navyDark,
              child: ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  // Attendance Summary Card
                  Container(
                    padding: const EdgeInsets.all(22),
                    decoration: BoxDecoration(
                      gradient: AppTheme.navyHeroGradient,
                      borderRadius: BorderRadius.circular(24),
                      boxShadow: [
                        BoxShadow(
                          color: AppTheme.navyDark.withValues(alpha: 0.2),
                          blurRadius: 18,
                          offset: const Offset(0, 8),
                        ),
                      ],
                    ),
                    child: Column(
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'OVERALL ATTENDANCE',
                                  style: TextStyle(
                                    color: AppTheme.goldLight,
                                    fontSize: 11,
                                    fontWeight: FontWeight.bold,
                                    letterSpacing: 1.2,
                                  ),
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  '$pct%',
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 34,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                const Text(
                                  'Minimum 75% required by Board',
                                  style: TextStyle(color: Colors.white60, fontSize: 11),
                                ),
                              ],
                            ),
                            // Circular Indicator
                            Stack(
                              alignment: Alignment.center,
                              children: [
                                SizedBox(
                                  width: 80,
                                  height: 80,
                                  child: CircularProgressIndicator(
                                    value: pct / 100,
                                    strokeWidth: 8,
                                    backgroundColor: Colors.white12,
                                    color: AppTheme.emerald,
                                  ),
                                ),
                                const Icon(Icons.check_rounded, color: AppTheme.emerald, size: 32),
                              ],
                            ),
                          ],
                        ),
                        const SizedBox(height: 20),
                        Container(
                          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceAround,
                            children: [
                              _statCol('Total Days', '${summary['total_days'] ?? 25}'),
                              _statCol('Present', '${summary['present_days'] ?? 23}', color: AppTheme.emerald),
                              _statCol('Absent', '${summary['absent_days'] ?? 1}', color: AppTheme.rose),
                              _statCol('Late', '${summary['late_days'] ?? 1}', color: AppTheme.amber),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 24),
                  const Text(
                    'Daily Attendance Log',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.slate900),
                  ),
                  const SizedBox(height: 12),

                  if (records.isEmpty)
                    const Center(child: Padding(padding: EdgeInsets.all(32), child: Text('No records found.')))
                  else
                    ...records.map((r) {
                      final status = r['status'] ?? 'Present';
                      Color color = AppTheme.emerald;
                      Color bg = AppTheme.emeraldLight;
                      IconData icon = Icons.check_circle_rounded;

                      if (status == 'Absent') {
                        color = AppTheme.rose;
                        bg = AppTheme.roseLight;
                        icon = Icons.cancel_rounded;
                      } else if (status == 'Late') {
                        color = AppTheme.amber;
                        bg = AppTheme.amberLight;
                        icon = Icons.access_time_rounded;
                      }

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
                              width: 38,
                              height: 38,
                              decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(12)),
                              child: Icon(icon, color: color, size: 20),
                            ),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    r['date'] ?? '',
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.slate900),
                                  ),
                                  Text(
                                    r['remarks'] ?? 'Regular attendance marked',
                                    style: const TextStyle(color: AppTheme.slate400, fontSize: 11),
                                  ),
                                ],
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(20)),
                              child: Text(
                                status.toUpperCase(),
                                style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.bold),
                              ),
                            ),
                          ],
                        ),
                      );
                    }),
                ],
              ),
            ),
    );
  }

  Widget _statCol(String label, String value, {Color color = Colors.white}) {
    return Column(
      children: [
        Text(value, style: TextStyle(color: color, fontSize: 16, fontWeight: FontWeight.bold)),
        const SizedBox(height: 2),
        Text(label, style: const TextStyle(color: Colors.white60, fontSize: 10)),
      ],
    );
  }
}
