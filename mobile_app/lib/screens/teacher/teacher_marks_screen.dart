import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../../widgets/custom_button.dart';

class TeacherMarksScreen extends StatefulWidget {
  const TeacherMarksScreen({super.key});

  @override
  State<TeacherMarksScreen> createState() => _TeacherMarksScreenState();
}

class _TeacherMarksScreenState extends State<TeacherMarksScreen> {
  String _selectedClass = 'Class 10-A';
  String _selectedExam = 'Mid-Term Examination 2026';
  String _selectedSubject = 'Mathematics';
  List<Map<String, dynamic>> _records = [];
  bool _isLoading = true;
  bool _isSaving = false;

  List<String> _classList = ApiService.standardClasses;
  final List<String> _examList = [
    'Unit Test 1 (25 Marks)',
    'Mid-Term Examination 2026',
    'Unit Test 2 (25 Marks)',
    'Annual Board Preliminary',
  ];
  final List<String> _subjectList = [
    'Mathematics',
    'Science & Physics',
    'English Literature',
    'Computer Applications',
    'Social Science',
    'Hindi'
  ];

  final Map<int, TextEditingController> _controllers = {};

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
      _loadRoster();
    }
  }

  @override
  void dispose() {
    for (var c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _loadRoster() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService().fetchMarks(studentClass: _selectedClass, exam: _selectedExam);
      final list = (res['records'] as List<dynamic>? ?? []).map((e) => Map<String, dynamic>.from(e)).toList();

      _controllers.clear();
      for (var r in list) {
        final sid = r['student_id'] as int;
        final marks = r['marks_obtained']?.toString() ?? '';
        _controllers[sid] = TextEditingController(text: marks);
      }

      if (mounted) {
        setState(() {
          _records = list;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to load marks: $e'), backgroundColor: AppTheme.rose),
        );
      }
    }
  }

  Future<void> _saveMarks() async {
    setState(() => _isSaving = true);
    try {
      final List<Map<String, dynamic>> payload = [];
      for (var r in _records) {
        final sid = r['student_id'] as int;
        final text = _controllers[sid]?.text.trim() ?? '';
        if (text.isNotEmpty) {
          final val = double.tryParse(text) ?? 0.0;
          payload.add({
            'student_id': sid,
            'subject': _selectedSubject,
            'marks_obtained': val,
            'max_marks': _selectedExam.contains('25') ? 25 : 100,
            'remarks': 'Evaluated by Faculty',
          });
        }
      }

      if (payload.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please enter marks for at least one student.')),
        );
        setState(() => _isSaving = false);
        return;
      }

      final success = await ApiService().submitMarks(examName: _selectedExam, marks: payload);
      if (!mounted) return;
      setState(() => _isSaving = false);

      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Grades for ${payload.length} students recorded & synced to report cards!'),
            backgroundColor: AppTheme.emerald,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isSaving = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error saving marks: $e'), backgroundColor: AppTheme.rose),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('Marksheet Entry & Grading'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: Colors.white),
            tooltip: 'Reload Roster',
            onPressed: _loadRoster,
          ),
        ],
      ),
      body: Column(
        children: [
          // Filter Header (Responsive)
          Container(
            color: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        key: ValueKey('cls_$_selectedClass'),
                        initialValue: _classList.contains(_selectedClass) ? _selectedClass : _classList[0],
                        decoration: const InputDecoration(labelText: 'Class Division', isDense: true),
                        items: _classList.map((c) => DropdownMenuItem(value: c, child: Text(c, overflow: TextOverflow.ellipsis))).toList(),
                        onChanged: (v) {
                          if (v != null) {
                            setState(() => _selectedClass = v);
                            _loadRoster();
                          }
                        },
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        key: ValueKey('sub_$_selectedSubject'),
                        initialValue: _selectedSubject,
                        decoration: const InputDecoration(labelText: 'Subject', isDense: true),
                        items: _subjectList.map((s) => DropdownMenuItem(value: s, child: Text(s, overflow: TextOverflow.ellipsis))).toList(),
                        onChanged: (v) {
                          if (v != null) setState(() => _selectedSubject = v);
                        },
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                DropdownButtonFormField<String>(
                  key: ValueKey('ex_$_selectedExam'),
                  initialValue: _selectedExam,
                  decoration: const InputDecoration(labelText: 'Exam / Assessment', isDense: true),
                  items: _examList.map((e) => DropdownMenuItem(value: e, child: Text(e, overflow: TextOverflow.ellipsis))).toList(),
                  onChanged: (v) {
                    if (v != null) {
                      setState(() => _selectedExam = v);
                      _loadRoster();
                    }
                  },
                ),
              ],
            ),
          ),
          const Divider(height: 1, color: AppTheme.slate200),

          // Student Roster with Marks Input
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
                : _records.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.grade_outlined, size: 48, color: AppTheme.slate400),
                            const SizedBox(height: 8),
                            Text('No enrolled students in $_selectedClass.', style: const TextStyle(color: AppTheme.slate600)),
                          ],
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        itemCount: _records.length,
                        itemBuilder: (ctx, idx) {
                          final r = _records[idx];
                          final sid = r['student_id'] as int;
                          final ctrl = _controllers[sid];

                          return Container(
                            margin: const EdgeInsets.only(bottom: 8),
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: AppTheme.slate100),
                            ),
                            child: Row(
                              children: [
                                Container(
                                  width: 32,
                                  height: 32,
                                  decoration: BoxDecoration(
                                    color: AppTheme.navyDark.withValues(alpha: 0.08),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  alignment: Alignment.center,
                                  child: Text(
                                    r['roll_no']?.toString().isNotEmpty == true ? r['roll_no'].toString() : '${idx + 1}',
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: AppTheme.navyDark),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        r['full_name'] ?? 'Student',
                                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                      Text(
                                        'Code: ${r['student_code'] ?? "N/A"}',
                                        style: const TextStyle(fontSize: 10, color: AppTheme.slate400),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(width: 8),
                                SizedBox(
                                  width: 80,
                                  height: 42,
                                  child: TextField(
                                    controller: ctrl,
                                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                    textAlign: TextAlign.center,
                                    decoration: InputDecoration(
                                      hintText: 'Marks',
                                      isDense: true,
                                      contentPadding: const EdgeInsets.symmetric(vertical: 8),
                                      suffixText: _selectedExam.contains('25') ? '/25' : '/100',
                                      suffixStyle: const TextStyle(fontSize: 9, color: AppTheme.slate400),
                                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
          ),

          // Submit Button
          Container(
            padding: const EdgeInsets.all(14),
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(top: BorderSide(color: AppTheme.slate200)),
            ),
            child: SafeArea(
              child: SizedBox(
                width: double.infinity,
                child: CustomButton(
                  label: _isSaving ? 'Submitting Marksheet...' : 'Save & Submit Marksheet',
                  icon: Icons.check_circle_rounded,
                  isLoading: _isSaving,
                  onPressed: _saveMarks,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
