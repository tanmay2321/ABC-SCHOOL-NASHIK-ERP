import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';

class StudentTransportScreen extends StatefulWidget {
  const StudentTransportScreen({super.key});

  @override
  State<StudentTransportScreen> createState() => _StudentTransportScreenState();
}

class _StudentTransportScreenState extends State<StudentTransportScreen> {
  Map<String, dynamic>? _data;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadTransport();
  }

  Future<void> _loadTransport() async {
    final res = await ApiService().fetchTransport();
    if (mounted) {
      setState(() {
        _data = res;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final routes = _data?['routes'] as List<dynamic>? ?? [];
    final route = routes.isNotEmpty ? routes[0] : null;

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('School Bus Live Route Tracker'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : route == null
              ? const Center(child: Text('No transport route assigned.'))
              : ListView(
                  padding: const EdgeInsets.all(18),
                  children: [
                    // Bus Live Status Card
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
                              Text(
                                route['bus_no'] ?? 'MH-39-AZ-8446',
                                style: const TextStyle(color: AppTheme.goldLight, fontWeight: FontWeight.bold, fontSize: 13, letterSpacing: 1),
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                decoration: BoxDecoration(
                                  color: AppTheme.emerald.withValues(alpha: 0.2),
                                  borderRadius: BorderRadius.circular(10),
                                  border: Border.all(color: AppTheme.emerald),
                                ),
                                child: const Row(
                                  children: [
                                    Icon(Icons.gps_fixed_rounded, color: AppTheme.emerald, size: 12),
                                    SizedBox(width: 4),
                                    Text('LIVE GPS', style: TextStyle(color: AppTheme.emerald, fontSize: 9, fontWeight: FontWeight.bold)),
                                  ],
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 10),
                          Text(
                            route['route_name'] ?? 'City Center Route',
                            style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'Status: ${route['current_status']} • ${route['speed']}',
                            style: const TextStyle(color: Colors.white70, fontSize: 12),
                          ),
                          const Divider(color: Colors.white24, height: 24),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text('Next Stop:', style: TextStyle(color: Colors.white54, fontSize: 10)),
                                  Text(
                                    route['next_stop'] ?? '',
                                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12),
                                  ),
                                ],
                              ),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  const Text('Est. Arrival:', style: TextStyle(color: Colors.white54, fontSize: 10)),
                                  Text(
                                    route['eta'] ?? '07:45 AM',
                                    style: const TextStyle(color: AppTheme.gold, fontWeight: FontWeight.w900, fontSize: 14),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 20),

                    // Driver & Attendant Contacts
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: AppTheme.slate100),
                      ),
                      child: Row(
                        children: [
                          Container(
                            width: 42,
                            height: 42,
                            decoration: BoxDecoration(
                              color: AppTheme.indigoLight,
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: const Icon(Icons.person_pin_rounded, color: AppTheme.navyMedium),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Driver: ${route['driver_name']} (${route['driver_phone']})',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: AppTheme.slate900),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  'Attendant: ${route['attendant_name']} (${route['attendant_phone']})',
                                  style: const TextStyle(color: AppTheme.slate400, fontSize: 11),
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            onPressed: () {
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(content: Text('Dialing ${route['driver_phone']}...')),
                              );
                            },
                            icon: const Icon(Icons.phone_in_talk_rounded, color: AppTheme.emerald),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 24),
                    const Text(
                      'Bus Stop Route Timeline',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.slate900),
                    ),
                    const SizedBox(height: 12),

                    // Stops timeline
                    ...((route['stops'] as List<dynamic>? ?? []).map((st) {
                      final isDone = st['status'] == 'Completed';
                      final isApproaching = st['status'] == 'Approaching';

                      return Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(
                            color: isApproaching ? AppTheme.navyMedium : AppTheme.slate100,
                            width: isApproaching ? 1.5 : 1,
                          ),
                        ),
                        child: Row(
                          children: [
                            Icon(
                              isDone
                                  ? Icons.check_circle_rounded
                                  : (isApproaching ? Icons.radio_button_checked : Icons.radio_button_unchecked),
                              color: isDone ? AppTheme.emerald : (isApproaching ? AppTheme.navyMedium : AppTheme.slate400),
                              size: 20,
                            ),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Text(
                                st['name'] ?? '',
                                style: TextStyle(
                                  fontWeight: isApproaching ? FontWeight.bold : FontWeight.normal,
                                  fontSize: 13,
                                  color: AppTheme.slate900,
                                ),
                              ),
                            ),
                            Text(
                              st['time'] ?? '',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 12,
                                color: isApproaching ? AppTheme.navyMedium : AppTheme.slate400,
                              ),
                            ),
                          ],
                        ),
                      );
                    })),
                  ],
                ),
    );
  }
}
