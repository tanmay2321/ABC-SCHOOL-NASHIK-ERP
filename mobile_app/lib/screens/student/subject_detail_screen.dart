import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';

class SubjectDetailScreen extends StatefulWidget {
  final dynamic subject;
  const SubjectDetailScreen({super.key, required this.subject});

  @override
  State<SubjectDetailScreen> createState() => _SubjectDetailScreenState();
}

class _SubjectDetailScreenState extends State<SubjectDetailScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  List<dynamic> _notes = [];
  List<dynamic> _assignments = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _loadData();
  }

  Future<void> _loadData() async {
    final subName = widget.subject['name'] ?? '';
    final notes = await ApiService().fetchNotes(subject: subName);
    final assignments = await ApiService().fetchAssignments(subject: subName);

    if (mounted) {
      setState(() {
        _notes = notes;
        _assignments = assignments;
        _isLoading = false;
      });
    }
  }

  void _showSubmitDialog(dynamic a) {
    final remarksController = TextEditingController();
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Text('Submit Homework: ${a['title']}', style: const TextStyle(fontSize: 16)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Attach your homework solution PDF or notes to submit for teacher evaluation.',
              style: TextStyle(fontSize: 12, color: AppTheme.slate600),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: remarksController,
              decoration: const InputDecoration(
                labelText: 'Student Remarks / Notes',
                hintText: 'e.g. Completed all exercise proofs.',
              ),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppTheme.slate50,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppTheme.slate200),
              ),
              child: const Row(
                children: [
                  Icon(Icons.picture_as_pdf_rounded, color: AppTheme.rose),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text('solution_homework.pdf (2.1 MB)', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                  ),
                  Icon(Icons.check_circle_rounded, color: AppTheme.emerald, size: 18),
                ],
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              await ApiService().submitAssignment(
                assignmentId: a['id'] is int ? a['id'] : int.tryParse(a['id'].toString()) ?? 1,
                remarks: remarksController.text,
              );
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Homework submitted successfully to Teacher!'),
                    backgroundColor: AppTheme.emerald,
                  ),
                );
                _loadData();
              }
            },
            child: const Text('Submit Solution'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = widget.subject;

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: Text(s['name'] ?? 'Subject Courseware'),
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: AppTheme.gold,
          indicatorWeight: 3,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white60,
          labelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
          tabs: const [
            Tab(text: 'Study Notes'),
            Tab(text: 'Homework'),
            Tab(text: 'Syllabus'),
          ],
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : TabBarView(
              controller: _tabController,
              children: [
                _buildNotesTab(),
                _buildAssignmentsTab(),
                _buildSyllabusTab(),
              ],
            ),
    );
  }

  Widget _buildNotesTab() {
    if (_notes.isEmpty) {
      return const Center(child: Text('No study notes uploaded yet for this subject.'));
    }
    return ListView.builder(
      padding: const EdgeInsets.all(18),
      itemCount: _notes.length,
      itemBuilder: (ctx, idx) {
        final n = _notes[idx];
        return Container(
          margin: const EdgeInsets.only(bottom: 12),
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
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: AppTheme.roseLight,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(Icons.picture_as_pdf_rounded, color: AppTheme.rose),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          n['title'] ?? '',
                          style: const TextStyle(color: AppTheme.slate900, fontSize: 14, fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${n['teacher_name'] ?? "Faculty"} • ${n['created_at'] != null ? n['created_at'].toString().split(' ')[0] : ""}',
                          style: const TextStyle(color: AppTheme.slate400, fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              if (n['description'] != null && n['description'].toString().isNotEmpty) ...[
                const SizedBox(height: 10),
                Text(
                  n['description'] ?? '',
                  style: const TextStyle(color: AppTheme.slate600, fontSize: 12),
                ),
              ],
              const Divider(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    n['file_name'] ?? 'Notes.pdf',
                    style: const TextStyle(color: AppTheme.slate400, fontSize: 11, fontFamily: 'monospace'),
                  ),
                  TextButton.icon(
                    onPressed: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: Text('Downloading ${n['file_name'] ?? "notes.pdf"}...'),
                          backgroundColor: AppTheme.navyDark,
                        ),
                      );
                    },
                    icon: const Icon(Icons.download_rounded, size: 16, color: AppTheme.navyMedium),
                    label: const Text('Download', style: TextStyle(color: AppTheme.navyMedium, fontSize: 12, fontWeight: FontWeight.bold)),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildAssignmentsTab() {
    if (_assignments.isEmpty) {
      return const Center(child: Text('No active assignments published.'));
    }
    return ListView.builder(
      padding: const EdgeInsets.all(18),
      itemCount: _assignments.length,
      itemBuilder: (ctx, idx) {
        final a = _assignments[idx];
        final isSubmitted = a['submission_status'] == 'Submitted' || a['submission_status'] == 'Graded';

        return Container(
          margin: const EdgeInsets.only(bottom: 14),
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
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: isSubmitted ? AppTheme.emeraldLight : AppTheme.amberLight,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      isSubmitted ? 'SUBMITTED' : 'PENDING DUE',
                      style: TextStyle(
                        color: isSubmitted ? AppTheme.emerald : AppTheme.amber,
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  Text(
                    'Due: ${a['due_date'] ?? ""}',
                    style: const TextStyle(color: AppTheme.slate400, fontSize: 11, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                a['title'] ?? '',
                style: const TextStyle(color: AppTheme.slate900, fontSize: 15, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 6),
              Text(
                a['description'] ?? '',
                style: const TextStyle(color: AppTheme.slate600, fontSize: 12),
              ),
              const SizedBox(height: 12),
              if (isSubmitted && a['marks_obtained'] != null) ...[
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppTheme.emeraldLight.withValues(alpha: 0.5),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.check_circle_rounded, color: AppTheme.emerald, size: 18),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Score: ${a['marks_obtained']} / ${a['max_marks']} • ${a['teacher_feedback'] ?? "Graded"}',
                          style: const TextStyle(color: AppTheme.emerald, fontSize: 12, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ),
                ),
              ] else if (!isSubmitted) ...[
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: () => _showSubmitDialog(a),
                    icon: const Icon(Icons.upload_file_rounded, size: 16),
                    label: const Text('Submit Homework Solution'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.navyDark,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 10),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
              ],
            ],
          ),
        );
      },
    );
  }

  Widget _buildSyllabusTab() {
    final syllabus = widget.subject['syllabus_info'] ?? 'Syllabus details published by academic council.';
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: AppTheme.slate100),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.bookmark_rounded, color: AppTheme.navyMedium),
                SizedBox(width: 10),
                Text(
                  'Prescribed Syllabus & Course Topics',
                  style: TextStyle(color: AppTheme.slate900, fontSize: 16, fontWeight: FontWeight.bold),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Text(
              syllabus,
              style: const TextStyle(color: AppTheme.slate700, fontSize: 13, height: 1.6),
            ),
            const SizedBox(height: 20),
            const Text(
              'Textbook References & Standards',
              style: TextStyle(color: AppTheme.slate900, fontSize: 14, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            const Text(
              '• NCERT Official Curriculum Framework 2026-27\n• CBSE Board Examination Guidelines\n• Reference Companion Guide (Bellwether Academic Press)',
              style: TextStyle(color: AppTheme.slate600, fontSize: 12, height: 1.5),
            ),
          ],
        ),
      ),
    );
  }
}
