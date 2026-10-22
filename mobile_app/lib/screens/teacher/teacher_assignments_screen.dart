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
  final _dueController = TextEditingController();
  final _marksController = TextEditingController(text: '20');
  final _attachmentController = TextEditingController();
  final _solutionController = TextEditingController();
  bool _releaseSolution = false;

  String _selectedClass = 'Class 10-A';
  String _selectedSubject = 'Mathematics';
  List<String> _classList = ApiService.standardClasses;

  final List<String> _subjects = [
    'Mathematics',
    'Science & Physics',
    'Chemistry',
    'Biology',
    'English Literature',
    'Social Science & History',
    'Hindi',
    'Marathi',
    'Computer Applications',
  ];

  @override
  void initState() {
    super.initState();
    final defaultDue = DateTime.now().add(const Duration(days: 7));
    _dueController.text =
        '${defaultDue.year}-${defaultDue.month.toString().padLeft(2, '0')}-${defaultDue.day.toString().padLeft(2, '0')}';
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
    _attachmentController.text = 'Worksheet_${_selectedSubject.replaceAll(" ", "_")}.pdf';
    _solutionController.clear();
    _releaseSolution = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setMState) => Padding(
          padding: EdgeInsets.only(
            left: 22,
            right: 22,
            top: 22,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 24,
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.add_task_rounded, color: AppTheme.navyDark, size: 22),
                        SizedBox(width: 8),
                        Text('Create Homework & Solution', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.slate900)),
                      ],
                    ),
                    IconButton(onPressed: () => Navigator.pop(ctx), icon: const Icon(Icons.close)),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: _classList.contains(_selectedClass) ? _selectedClass : _classList[0],
                        decoration: const InputDecoration(labelText: 'Target Class / Division'),
                        items: _classList.map((c) => DropdownMenuItem(value: c, child: Text(c, overflow: TextOverflow.ellipsis))).toList(),
                        onChanged: (val) {
                          if (val != null) setMState(() => _selectedClass = val);
                        },
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: _subjects.contains(_selectedSubject) ? _selectedSubject : _subjects[0],
                        decoration: const InputDecoration(labelText: 'Subject'),
                        items: _subjects.map((s) => DropdownMenuItem(value: s, child: Text(s, overflow: TextOverflow.ellipsis))).toList(),
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
                    labelText: 'Instructions / Homework Questions',
                    hintText: 'Solve questions 1 through 10 in homework notebook...',
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      flex: 3,
                      child: TextField(
                        controller: _dueController,
                        decoration: const InputDecoration(
                          labelText: 'Due Date (YYYY-MM-DD)',
                          prefixIcon: Icon(Icons.event_rounded),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      flex: 2,
                      child: TextField(
                        controller: _marksController,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(
                          labelText: 'Max Marks',
                          prefixIcon: Icon(Icons.grade_rounded),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                // Attachment Document Field
                TextField(
                  controller: _attachmentController,
                  decoration: const InputDecoration(
                    labelText: 'Assignment Document / Worksheet (.pdf)',
                    prefixIcon: Icon(Icons.attach_file_rounded),
                    hintText: 'e.g. Homework_Sheet_Ch4.pdf',
                  ),
                ),
                const SizedBox(height: 12),

                // Solution Field
                TextField(
                  controller: _solutionController,
                  maxLines: 2,
                  decoration: const InputDecoration(
                    labelText: 'Official Solution / Answer Key (Optional)',
                    prefixIcon: Icon(Icons.lightbulb_outline_rounded),
                    hintText: 'Step-by-step solution notes or answer guide...',
                  ),
                ),
                const SizedBox(height: 8),

                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Release Solution to Students Now', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                  subtitle: const Text('If off, students only see solution after you release it.', style: TextStyle(fontSize: 11, color: AppTheme.slate500)),
                  value: _releaseSolution,
                  activeThumbColor: AppTheme.emerald,
                  onChanged: (val) => setMState(() => _releaseSolution = val),
                ),

                const SizedBox(height: 16),
                CustomButton(
                  label: 'Publish Assignment & Notify Students',
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
                        maxMarks: double.tryParse(_marksController.text.trim()) ?? 20,
                        attachmentPath: _attachmentController.text.trim(),
                        solutionNotes: _solutionController.text.trim(),
                        releaseSolution: _releaseSolution,
                      );
                      _titleController.clear();
                      _descController.clear();
                      if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Assignment created & students notified!'), backgroundColor: AppTheme.emerald),
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
      ),
    );
  }

  void _showSolutionModal(Map<String, dynamic> assignment) {
    final solController = TextEditingController(text: assignment['solution_notes'] ?? '');
    bool release = (assignment['solution_released'] == 1 || assignment['solution_released'] == true);

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSState) => Padding(
          padding: EdgeInsets.only(
            left: 22,
            right: 22,
            top: 22,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.lightbulb_rounded, color: AppTheme.amber, size: 22),
                      const SizedBox(width: 8),
                      Text('Official Solution: ${assignment['title']}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                    ],
                  ),
                  IconButton(onPressed: () => Navigator.pop(ctx), icon: const Icon(Icons.close)),
                ],
              ),
              const SizedBox(height: 12),
              TextField(
                controller: solController,
                maxLines: 4,
                decoration: const InputDecoration(
                  labelText: 'Official Solution & Answer Notes',
                  hintText: 'Enter complete solution, key steps, or reference answers...',
                ),
              ),
              const SizedBox(height: 12),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Release Solution to Students', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                subtitle: const Text('Students will receive an in-app alert that solutions are available.', style: TextStyle(fontSize: 11, color: AppTheme.slate500)),
                value: release,
                activeThumbColor: AppTheme.emerald,
                onChanged: (val) => setSState(() => release = val),
              ),
              const SizedBox(height: 16),
              CustomButton(
                label: 'Save & Release Solution',
                icon: Icons.check_circle_rounded,
                onPressed: () async {
                  Navigator.pop(ctx);
                  await ApiService().uploadAssignmentSolution(
                    assignmentId: assignment['id'],
                    solutionNotes: solController.text.trim(),
                    releaseSolution: release,
                  );
                  _loadAssignments();
                  if (mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Solution uploaded & notifications sent!'), backgroundColor: AppTheme.emerald),
                    );
                  }
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showSubmissionsModal(Map<String, dynamic> assignment) async {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
      builder: (ctx) {
        return FutureBuilder<List<dynamic>>(
          future: ApiService().fetchAssignmentSubmissions(assignment['id']),
          builder: (ctx, snapshot) {
            final subs = snapshot.data ?? [];
            return Container(
              height: MediaQuery.of(ctx).size.height * 0.75,
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          'Submissions: ${assignment['title']}',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.slate900),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      IconButton(onPressed: () => Navigator.pop(ctx), icon: const Icon(Icons.close)),
                    ],
                  ),
                  Text('Class: ${assignment['class']} • Max Marks: ${assignment['max_marks']}', style: const TextStyle(color: AppTheme.slate500, fontSize: 12)),
                  const Divider(height: 20),
                  Expanded(
                    child: snapshot.connectionState == ConnectionState.waiting
                        ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
                        : subs.isEmpty
                            ? const Center(child: Text('No student has submitted this assignment yet.'))
                            : ListView.builder(
                                itemCount: subs.length,
                                itemBuilder: (ctx, idx) {
                                  final s = subs[idx];
                                  final isGraded = s['status'] == 'Graded';

                                  return Container(
                                    margin: const EdgeInsets.only(bottom: 12),
                                    padding: const EdgeInsets.all(14),
                                    decoration: BoxDecoration(
                                      color: AppTheme.bgLight,
                                      borderRadius: BorderRadius.circular(16),
                                      border: Border.all(color: AppTheme.slate200),
                                    ),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          children: [
                                            CircleAvatar(
                                              radius: 16,
                                              backgroundColor: AppTheme.navyDark,
                                              child: Text((s['student_name']?[0] ?? 'S').toUpperCase(), style: const TextStyle(color: Colors.white, fontSize: 12)),
                                            ),
                                            const SizedBox(width: 10),
                                            Expanded(
                                              child: Column(
                                                crossAxisAlignment: CrossAxisAlignment.start,
                                                children: [
                                                  Text(s['student_name'] ?? 'Student', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                                                  Text('Roll: #${s['roll_no'] ?? "-"} • Submitted: ${s['submitted_at'] ?? "Recent"}', style: const TextStyle(color: AppTheme.slate400, fontSize: 11)),
                                                ],
                                              ),
                                            ),
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                              decoration: BoxDecoration(
                                                color: isGraded ? AppTheme.emeraldLight : AppTheme.amberLight,
                                                borderRadius: BorderRadius.circular(8),
                                              ),
                                              child: Text(
                                                isGraded ? '${s['marks_obtained']} / ${assignment['max_marks']}' : 'PENDING CHECK',
                                                style: TextStyle(
                                                  color: isGraded ? AppTheme.emerald : AppTheme.amber,
                                                  fontWeight: FontWeight.bold,
                                                  fontSize: 10,
                                                ),
                                              ),
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 10),
                                        Text('Student Remarks: ${s['student_remarks'] ?? "Submitted homework"}', style: const TextStyle(fontSize: 12, color: AppTheme.slate700)),
                                        if (isGraded) ...[
                                          const SizedBox(height: 6),
                                          Text('Teacher Feedback: ${s['teacher_feedback'] ?? ""}', style: const TextStyle(fontSize: 11, color: AppTheme.emerald, fontWeight: FontWeight.w600)),
                                        ],
                                        const SizedBox(height: 10),
                                        Align(
                                          alignment: Alignment.centerRight,
                                          child: ElevatedButton.icon(
                                            onPressed: () => _gradeStudentDialog(s, assignment),
                                            style: ElevatedButton.styleFrom(
                                              backgroundColor: AppTheme.navyDark,
                                              foregroundColor: Colors.white,
                                              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                                              visualDensity: VisualDensity.compact,
                                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                            ),
                                            icon: const Icon(Icons.check_circle_outline, size: 14),
                                            label: Text(isGraded ? 'Update Grade' : 'Check & Grade', style: const TextStyle(fontSize: 11)),
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
          },
        );
      },
    );
  }

  void _gradeStudentDialog(Map<String, dynamic> submission, Map<String, dynamic> assignment) {
    final marksC = TextEditingController(text: submission['marks_obtained']?.toString() ?? '18');
    final feedC = TextEditingController(text: submission['teacher_feedback']?.toString() ?? 'Well solved. Excellent work!');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Text('Grade: ${submission['student_name']}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: marksC,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: 'Marks (Out of ${assignment['max_marks']})',
                prefixIcon: const Icon(Icons.grade_rounded),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: feedC,
              decoration: const InputDecoration(
                labelText: 'Teacher Feedback / Remarks',
                prefixIcon: Icon(Icons.comment_rounded),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              final m = double.tryParse(marksC.text.trim()) ?? 0;
              await ApiService().gradeSubmission(
                submissionId: submission['id'],
                marks: m,
                feedback: feedC.text.trim(),
              );
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Graded! Student has received an in-app alert.'), backgroundColor: AppTheme.emerald),
                );
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.navyDark, foregroundColor: Colors.white),
            child: const Text('Save & Alert Student'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('Homework, Solutions & Grading'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: Colors.white),
            onPressed: _loadAssignments,
          ),
        ],
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
                padding: const EdgeInsets.all(16),
                children: [
                  if (_assignments.isEmpty)
                    Center(
                      child: Padding(
                        padding: const EdgeInsets.all(40),
                        child: Column(
                          children: [
                            const Icon(Icons.assignment_outlined, size: 54, color: AppTheme.slate400),
                            const SizedBox(height: 12),
                            const Text('No homework assignments created yet.', style: TextStyle(color: AppTheme.slate600)),
                            const SizedBox(height: 16),
                            ElevatedButton.icon(
                              onPressed: _showCreateModal,
                              icon: const Icon(Icons.add),
                              label: const Text('Create First Assignment'),
                              style: ElevatedButton.styleFrom(backgroundColor: AppTheme.navyDark, foregroundColor: Colors.white),
                            ),
                          ],
                        ),
                      ),
                    )
                  else
                    ..._assignments.map((a) {
                      final hasSolution = (a['solution_notes'] != null && a['solution_notes'].toString().isNotEmpty) || (a['solution_path'] != null);
                      final solReleased = (a['solution_released'] == 1 || a['solution_released'] == true);

                      return Container(
                        margin: const EdgeInsets.only(bottom: 14),
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: AppTheme.slate100),
                          boxShadow: [
                            BoxShadow(
                              color: AppTheme.navyDark.withValues(alpha: 0.04),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
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
                                  decoration: BoxDecoration(color: AppTheme.indigoLight, borderRadius: BorderRadius.circular(10)),
                                  child: Text(a['subject'] ?? 'Subject', style: const TextStyle(color: AppTheme.navyMedium, fontSize: 11, fontWeight: FontWeight.bold)),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(color: AppTheme.amberLight, borderRadius: BorderRadius.circular(8)),
                                  child: Text('Due: ${a['due_date'] ?? ""}', style: const TextStyle(color: AppTheme.amber, fontSize: 11, fontWeight: FontWeight.bold)),
                                ),
                              ],
                            ),
                            const SizedBox(height: 10),
                            Text(a['title'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: AppTheme.slate900)),
                            const SizedBox(height: 4),
                            Text(a['description'] ?? '', style: const TextStyle(color: AppTheme.slate600, fontSize: 12, height: 1.3)),
                            const SizedBox(height: 12),

                            // Document & Solution Badges
                            Wrap(
                              spacing: 8,
                              runSpacing: 6,
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                  decoration: BoxDecoration(color: AppTheme.slate100, borderRadius: BorderRadius.circular(8)),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      const Icon(Icons.school_rounded, size: 12, color: AppTheme.navyDark),
                                      const SizedBox(width: 4),
                                      Text(a['class'] ?? 'Class 10', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.navyDark)),
                                    ],
                                  ),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                  decoration: BoxDecoration(color: AppTheme.slate100, borderRadius: BorderRadius.circular(8)),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      const Icon(Icons.grade_rounded, size: 12, color: AppTheme.amber),
                                      const SizedBox(width: 4),
                                      Text('${a['max_marks'] ?? 20} Marks', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.slate800)),
                                    ],
                                  ),
                                ),
                                if (hasSolution)
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: solReleased ? AppTheme.emeraldLight : AppTheme.roseLight,
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Icon(Icons.lightbulb_rounded, size: 12, color: solReleased ? AppTheme.emerald : AppTheme.rose),
                                        const SizedBox(width: 4),
                                        Text(solReleased ? 'Solution Released' : 'Solution Draft', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: solReleased ? AppTheme.emerald : AppTheme.rose)),
                                      ],
                                    ),
                                  ),
                              ],
                            ),

                            const Divider(height: 20),

                            // Action Buttons
                            Row(
                              children: [
                                Expanded(
                                  child: OutlinedButton.icon(
                                    onPressed: () => _showSolutionModal(a),
                                    icon: const Icon(Icons.lightbulb_outline_rounded, size: 16, color: AppTheme.amber),
                                    label: Text(hasSolution ? 'Edit Solution' : 'Add Solution', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.slate800)),
                                    style: OutlinedButton.styleFrom(
                                      padding: const EdgeInsets.symmetric(vertical: 8),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: ElevatedButton.icon(
                                    onPressed: () => _showSubmissionsModal(a),
                                    icon: const Icon(Icons.grading_rounded, size: 16),
                                    label: const Text('Check Submissions', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: AppTheme.navyDark,
                                      foregroundColor: Colors.white,
                                      padding: const EdgeInsets.symmetric(vertical: 8),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                    ),
                                  ),
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
