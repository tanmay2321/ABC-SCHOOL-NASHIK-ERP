import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';

class StudentTimetableScreen extends StatefulWidget {
  const StudentTimetableScreen({super.key});

  @override
  State<StudentTimetableScreen> createState() => _StudentTimetableScreenState();
}

class _StudentTimetableScreenState extends State<StudentTimetableScreen> {
  Map<String, dynamic>? _ttData;
  String _selectedDay = 'Monday';
  bool _isLoading = true;

  final List<String> _days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

  @override
  void initState() {
    super.initState();
    // Default to today if Mon-Sat
    final nowDay = DateTime.now().weekday;
    if (nowDay >= 1 && nowDay <= 6) {
      _selectedDay = _days[nowDay - 1];
    }
    _loadTimetable();
  }

  Future<void> _loadTimetable() async {
    final res = await ApiService().fetchTimetable();
    if (mounted) {
      setState(() {
        _ttData = res;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final daysMap = _ttData?['days'] as Map<String, dynamic>? ?? {};
    final currentDayPeriods = daysMap[_selectedDay] as List<dynamic>? ?? [];

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('Weekly Class Timetable'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : Column(
              children: [
                // Day selector chips
                Container(
                  color: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Row(
                      children: _days.map((d) {
                        final isSelected = _selectedDay == d;
                        return Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: ChoiceChip(
                            label: Text(d),
                            selected: isSelected,
                            onSelected: (val) => setState(() => _selectedDay = d),
                            selectedColor: AppTheme.navyDark,
                            labelStyle: TextStyle(
                              color: isSelected ? Colors.white : AppTheme.slate700,
                              fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                              fontSize: 12,
                            ),
                            backgroundColor: AppTheme.slate50,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                          ),
                        );
                      }).toList(),
                    ),
                  ),
                ),

                // Periods list
                Expanded(
                  child: currentDayPeriods.isEmpty
                      ? Center(child: Text('No classes scheduled for $_selectedDay.'))
                      : ListView.builder(
                          padding: const EdgeInsets.all(18),
                          itemCount: currentDayPeriods.length,
                          itemBuilder: (ctx, idx) {
                            final p = currentDayPeriods[idx];
                            return Container(
                              margin: const EdgeInsets.only(bottom: 12),
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(20),
                                border: Border.all(color: AppTheme.slate100),
                              ),
                              child: Row(
                                children: [
                                  Container(
                                    width: 44,
                                    height: 44,
                                    decoration: BoxDecoration(
                                      color: AppTheme.navyMedium.withValues(alpha: 0.1),
                                      borderRadius: BorderRadius.circular(14),
                                    ),
                                    alignment: Alignment.center,
                                    child: Text(
                                      '${p['period_number']}',
                                      style: const TextStyle(
                                        color: AppTheme.navyMedium,
                                        fontWeight: FontWeight.w900,
                                        fontSize: 18,
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
                                            fontWeight: FontWeight.bold,
                                            fontSize: 14,
                                          ),
                                        ),
                                        const SizedBox(height: 3),
                                        Text(
                                          '${p['teacher_name']} • ${p['room_no'] ?? "Room 101"}',
                                          style: const TextStyle(color: AppTheme.slate400, fontSize: 11),
                                        ),
                                      ],
                                    ),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: AppTheme.slate50,
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    child: Text(
                                      p['time_slot'] ?? '',
                                      style: const TextStyle(
                                        color: AppTheme.navyMedium,
                                        fontSize: 11,
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
              ],
            ),
    );
  }
}
