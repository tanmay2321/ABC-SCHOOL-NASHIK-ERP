import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../../widgets/custom_button.dart';

class TeacherNotesScreen extends StatefulWidget {
  const TeacherNotesScreen({super.key});

  @override
  State<TeacherNotesScreen> createState() => _TeacherNotesScreenState();
}

class _TeacherNotesScreenState extends State<TeacherNotesScreen> {
  List<dynamic> _notes = [];
  bool _isLoading = true;

  final _titleController = TextEditingController();
  final _descController = TextEditingController();
  String _selectedClass = 'Class 10-A';
  String _selectedSubject = 'Mathematics';
  List<String> _classList = ApiService.standardClasses;

  @override
  void initState() {
    super.initState();
    _initClasses();
  }

  Future<void> _initClasses() async {
    final classes = await ApiService().fetchClasses();
    if (mounted) {
      setState(() {
        _classList = classes;
        if (!_classList.contains(_selectedClass)) {
          _selectedClass = _classList.isNotEmpty ? _classList[0] : 'Class 10-A';
        }
      });
      _loadNotes();
    }
  }

  Future<void> _loadNotes() async {
    final list = await ApiService().fetchNotes(studentClass: _selectedClass);
    if (mounted) {
      setState(() {
        _notes = list;
        _isLoading = false;
      });
    }
  }

  void _showPublishModal() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setMState) => Padding(
          padding: EdgeInsets.only(
            left: 24,
            right: 24,
            top: 24,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Publish Study Notes to Class', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                  IconButton(onPressed: () => Navigator.pop(ctx), icon: const Icon(Icons.close)),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      initialValue: _classList.contains(_selectedClass) ? _selectedClass : _classList[0],
                      decoration: const InputDecoration(labelText: 'Target Class'),
                      items: _classList.map((c) => DropdownMenuItem(value: c, child: Text(c, overflow: TextOverflow.ellipsis))).toList(),
                      onChanged: (val) {
                        if (val != null) setMState(() => _selectedClass = val);
                      },
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      initialValue: _selectedSubject,
                      decoration: const InputDecoration(labelText: 'Subject'),
                      items: ['Mathematics', 'Science & Physics', 'English Literature', 'Computer Applications'].map((s) => DropdownMenuItem(value: s, child: Text(s))).toList(),
                      onChanged: (val) {
                        if (val != null) setMState(() => _selectedSubject = val);
                      },
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _titleController,
                decoration: const InputDecoration(
                  labelText: 'Title of Note / Module',
                  hintText: 'e.g. Chapter 4 Formulas & Practice Questions',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _descController,
                maxLines: 2,
                decoration: const InputDecoration(
                  labelText: 'Description / Instructions',
                  hintText: 'Key objectives and reference pages...',
                ),
              ),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppTheme.slate50,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppTheme.slate200),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.attach_file_rounded, color: AppTheme.navyMedium),
                    SizedBox(width: 8),
                    Expanded(child: Text('Curriculum_Notes.pdf (Attached)', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold))),
                    Icon(Icons.check_circle_rounded, color: AppTheme.emerald, size: 18),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              CustomButton(
                label: 'Publish Notes to Students',
                icon: Icons.cloud_upload_rounded,
                onPressed: () async {
                  if (_titleController.text.trim().isEmpty) return;
                  Navigator.pop(ctx);
                  await ApiService().uploadNote(
                    title: _titleController.text.trim(),
                    studentClass: _selectedClass,
                    subject: _selectedSubject,
                    description: _descController.text.trim(),
                  );
                  _titleController.clear();
                  _descController.clear();
                  if (mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Study notes published to student LMS!'), backgroundColor: AppTheme.emerald),
                    );
                    _loadNotes();
                  }
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('Study Notes Publisher'),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _showPublishModal,
        backgroundColor: AppTheme.navyDark,
        icon: const Icon(Icons.upload_file_rounded, color: AppTheme.gold),
        label: const Text('Publish Note', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : RefreshIndicator(
              onRefresh: _loadNotes,
              color: AppTheme.navyDark,
              child: ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  if (_notes.isEmpty)
                    const Center(child: Padding(padding: EdgeInsets.all(40), child: Text('No notes published yet.')))
                  else
                    ..._notes.map((n) {
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
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(color: AppTheme.cyanLight, borderRadius: BorderRadius.circular(8)),
                                  child: Text(n['subject'] ?? '', style: const TextStyle(color: AppTheme.cyan, fontSize: 10, fontWeight: FontWeight.bold)),
                                ),
                                Text(n['class'] ?? '', style: const TextStyle(color: AppTheme.slate400, fontSize: 11, fontWeight: FontWeight.bold)),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text(n['title'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.slate900)),
                            const SizedBox(height: 4),
                            Text(n['description'] ?? '', style: const TextStyle(color: AppTheme.slate600, fontSize: 12)),
                            const Divider(height: 18),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(n['file_name'] ?? 'Notes.pdf', style: const TextStyle(color: AppTheme.slate400, fontSize: 11, fontFamily: 'monospace')),
                                const Text('Published to LMS', style: TextStyle(color: AppTheme.emerald, fontSize: 11, fontWeight: FontWeight.bold)),
                              ],
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
