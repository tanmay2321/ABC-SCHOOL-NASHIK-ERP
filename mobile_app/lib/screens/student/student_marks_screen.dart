import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';

class StudentMarksScreen extends StatefulWidget {
  const StudentMarksScreen({super.key});

  @override
  State<StudentMarksScreen> createState() => _StudentMarksScreenState();
}

class _StudentMarksScreenState extends State<StudentMarksScreen> {
  Map<String, dynamic>? _marksData;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadMarks();
  }

  Future<void> _loadMarks() async {
    final res = await ApiService().fetchMarks();
    if (mounted) {
      setState(() {
        _marksData = res;
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final exams = _marksData?['exams'] as List<dynamic>? ?? [];
    final double overall = (_marksData?['overall_percentage'] is num)
        ? (_marksData?['overall_percentage'] as num).toDouble()
        : 91.4;

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('Examination & Report Cards'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : RefreshIndicator(
              onRefresh: _loadMarks,
              color: AppTheme.navyDark,
              child: ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  // Overall GPA Banner
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
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'CUMULATIVE PERFORMANCE',
                              style: TextStyle(color: AppTheme.goldLight, fontSize: 10, fontWeight: FontWeight.bold, letterSpacing: 1.2),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              '$overall%',
                              style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w900),
                            ),
                            const SizedBox(height: 4),
                            const Text('Grade A+ • Outstanding Scholar', style: TextStyle(color: Colors.white70, fontSize: 11)),
                          ],
                        ),
                        Container(
                          width: 68,
                          height: 68,
                          decoration: BoxDecoration(
                            gradient: AppTheme.goldGradient,
                            shape: BoxShape.circle,
                          ),
                          child: const Center(
                            child: Icon(Icons.workspace_premium_rounded, color: AppTheme.navyDark, size: 36),
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 24),

                  if (exams.isEmpty)
                    const Center(child: Padding(padding: EdgeInsets.all(32), child: Text('No examination records found.')))
                  else
                    ...exams.map((ex) {
                      final double exPct = (ex['percentage'] is num) ? (ex['percentage'] as num).toDouble() : 0.0;
                      final subjects = ex['subjects'] as List<dynamic>? ?? [];

                      return Container(
                        margin: const EdgeInsets.only(bottom: 20),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(24),
                          border: Border.all(color: AppTheme.slate100),
                          boxShadow: [
                            BoxShadow(
                              color: AppTheme.navyDark.withValues(alpha: 0.03),
                              blurRadius: 14,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Column(
                          children: [
                            // Exam Header
                            Container(
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                color: AppTheme.slate50,
                                borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
                              ),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    ex['exam_name'] ?? 'Assessment',
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.slate900),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: AppTheme.emeraldLight,
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: Text(
                                      '$exPct% • Rank 1',
                                      style: const TextStyle(color: AppTheme.emerald, fontSize: 11, fontWeight: FontWeight.bold),
                                    ),
                                  ),
                                ],
                              ),
                            ),

                            // Subjects table
                            Padding(
                              padding: const EdgeInsets.all(16),
                              child: Column(
                                children: subjects.map((sub) {
                                  return Padding(
                                    padding: const EdgeInsets.only(bottom: 12),
                                    child: Row(
                                      children: [
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                sub['subject'] ?? '',
                                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.slate900),
                                              ),
                                              if (sub['remarks'] != null)
                                                Text(
                                                  sub['remarks'],
                                                  style: const TextStyle(color: AppTheme.slate400, fontSize: 10),
                                                ),
                                            ],
                                          ),
                                        ),
                                        Text(
                                          '${sub['marks_obtained']} / ${sub['max_marks']}',
                                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.slate900),
                                        ),
                                        const SizedBox(width: 10),
                                        Container(
                                          width: 32,
                                          height: 24,
                                          decoration: BoxDecoration(
                                            color: AppTheme.indigoLight,
                                            borderRadius: BorderRadius.circular(6),
                                          ),
                                          alignment: Alignment.center,
                                          child: Text(
                                            sub['grade'] ?? 'A',
                                            style: const TextStyle(color: AppTheme.navyMedium, fontWeight: FontWeight.bold, fontSize: 11),
                                          ),
                                        ),
                                      ],
                                    ),
                                  );
                                }).toList(),
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
}
