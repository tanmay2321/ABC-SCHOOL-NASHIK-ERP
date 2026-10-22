import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../../widgets/custom_button.dart';

class TeacherAssignmentsScreen extends StatefulWidget {
  const TeacherAssignmentsScreen({super.key});

  @override
  State<TeacherAssignmentsScreen> createState() => _TeacherAssignmentsScreenState();
}

class _TeacherAssignmentsScreenState extends State<TeacherAssignmentsScreen> {
  List<dynamic> _assignments = [];
  bool _isLoading = true;

  final _titleController = TextEditingController();
  final _descController = TextEditingController();
  final _dueController = TextEditingController(text: '2026-10-30');
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
      _loadAssignments();
    }
  }

  Future<void> _loadAssignments() async {
    setState(() => _isLoading = true);
    try {
      final list = await ApiService().fetchAssignments();
      if (mounted) {
        setState(() {
          _assignments = list;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  void _showCreateModal() {
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
                  const Text('Create New Homework / Assignment', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
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
                      items: ['Mathematics', 'Science & Physics', 'English Literature', 'Computer Applications']
                          .map((s) => DropdownMenuItem(value: s, child: Text(s)))
                          .toList(),
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
                  labelText: 'Assignment Title *',
                  hintText: 'e.g. Exercise 4.2 - Quadratic Equations',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _descController,
                maxLines: 2,
                decoration: const InputDecoration(
                  labelText: 'Instructions / Details',
                  hintText: 'Solve questions 1 through 10 in homework notebook...',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _dueController,
                decoration: const InputDecoration(
                  labelText: 'Due Date (YYYY-MM-DD)',
                  hintText: '2026-10-30',
                  prefixIcon: Icon(Icons.event_rounded),
                ),
              ),
              const SizedBox(height: 20),
              CustomButton(
                label: 'Assign to Students',
                icon: Icons.assignment_turned_in_rounded,
                onPressed: () async {
                  if (_titleController.text.trim().isEmpty) return;
                  Navigator.pop(ctx);
                  try {
                    await ApiService().createAssignment(
                      title: _titleController.text.trim(),
                      studentClass: _selectedClass,
                      subject: _selectedSubject,
                      description: _descController.text.trim(),
                      dueDate: _dueController.text.trim(),
                    );
                    _titleController.clear();
                    _descController.clear();
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Assignment created & synced to student LMS!'), backgroundColor: AppTheme.emerald),
                      );
                      _loadAssignments();
                    }
                  } catch (e) {
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text('Error: $e'), backgroundColor: AppTheme.rose),
                      );
                    }
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
        title: const Text('Homework & Assignments'),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _showCreateModal,
        backgroundColor: AppTheme.navyDark,
        icon: const Icon(Icons.add_task_rounded, color: AppTheme.gold),
        label: const Text('New Assignment', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : RefreshIndicator(
              onRefresh: _loadAssignments,
              color: AppTheme.navyDark,
              child: ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  if (_assignments.isEmpty)
                    const Center(child: Padding(padding: EdgeInsets.all(40), child: Text('No active assignments created.')))
                  else
                    ..._assignments.map((a) {
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
                                  decoration: BoxDecoration(color: AppTheme.amberLight, borderRadius: BorderRadius.circular(8)),
                                  child: Text(a['subject'] ?? 'Subject', style: const TextStyle(color: AppTheme.amber, fontSize: 10, fontWeight: FontWeight.bold)),
                                ),
                                Text('Due: ${a['due_date'] ?? ""}', style: const TextStyle(color: AppTheme.slate400, fontSize: 11, fontWeight: FontWeight.bold)),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text(a['title'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.slate900)),
                            const SizedBox(height: 4),
                            Text(a['description'] ?? '', style: const TextStyle(color: AppTheme.slate600, fontSize: 12)),
                            const Divider(height: 18),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  'Class: ${a['class'] ?? "Class 10"}',
                                  style: const TextStyle(color: AppTheme.slate600, fontSize: 12, fontWeight: FontWeight.bold),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                  decoration: BoxDecoration(color: AppTheme.emeraldLight, borderRadius: BorderRadius.circular(10)),
                                  child: const Text('Active in LMS', style: TextStyle(color: AppTheme.emerald, fontSize: 11, fontWeight: FontWeight.bold)),
                                ),
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
