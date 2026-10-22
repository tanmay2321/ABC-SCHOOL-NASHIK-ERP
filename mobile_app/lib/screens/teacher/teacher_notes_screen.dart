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
  final _fileNameController = TextEditingController();
  String _selectedClass = 'Class 10-A';
  String _selectedSubject = 'Mathematics';
  String _docType = 'PDF Document';
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
    _fileNameController.text = '${_titleController.text.isNotEmpty ? _titleController.text.trim().replaceAll(" ", "_") : "study_material"}.pdf';

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
                        Icon(Icons.upload_file_rounded, color: AppTheme.navyDark, size: 22),
                        SizedBox(width: 8),
                        Text('Upload Study Notes & Material', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.slate900)),
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
                    labelText: 'Title of Note / Module',
                    hintText: 'e.g. Chapter 4 Formulas & Practice Questions',
                  ),
                  onChanged: (val) {
                    setMState(() {
                      _fileNameController.text = '${val.trim().replaceAll(" ", "_")}_notes.pdf';
                    });
                  },
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _descController,
                  maxLines: 2,
                  decoration: const InputDecoration(
                    labelText: 'Topic Overview / Study Instructions',
                    hintText: 'Key objectives, reference textbook pages, exam hints...',
                  ),
                ),
                const SizedBox(height: 14),

                // Document Attachment Section
                const Text('Document & Attachment Settings', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.slate800)),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      flex: 2,
                      child: DropdownButtonFormField<String>(
                        initialValue: _docType,
                        decoration: const InputDecoration(labelText: 'Doc Type'),
                        items: ['PDF Document', 'Worksheet', 'Handwritten Notes', 'Presentation Slides', 'Summary Guide']
                            .map((d) => DropdownMenuItem(value: d, child: Text(d, style: const TextStyle(fontSize: 12)))).toList(),
                        onChanged: (val) {
                          if (val != null) setMState(() => _docType = val);
                        },
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      flex: 3,
                      child: TextField(
                        controller: _fileNameController,
                        decoration: const InputDecoration(
                          labelText: 'File Name (.pdf)',
                          prefixIcon: Icon(Icons.description_rounded, size: 18),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppTheme.emeraldLight,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: AppTheme.emerald.withValues(alpha: 0.3)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.verified_rounded, color: AppTheme.emerald, size: 20),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Will be published to all enrolled $_selectedClass students with instant app notification.',
                          style: const TextStyle(fontSize: 11, color: AppTheme.emerald, fontWeight: FontWeight.w600),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                CustomButton(
                  label: 'Publish Document to LMS',
                  icon: Icons.cloud_upload_rounded,
                  onPressed: () async {
                    if (_titleController.text.trim().isEmpty) return;
                    Navigator.pop(ctx);
                    await ApiService().uploadNote(
                      title: _titleController.text.trim(),
                      studentClass: _selectedClass,
                      subject: _selectedSubject,
                      description: _descController.text.trim(),
                      fileName: _fileNameController.text.trim().isNotEmpty ? _fileNameController.text.trim() : 'notes.pdf',
                    );
                    _titleController.clear();
                    _descController.clear();
                    _loadNotes();
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Study Notes published & students notified!'), backgroundColor: AppTheme.emerald),
                      );
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('Faculty Study Notes & LMS'),
        actions: [
          IconButton(
            icon: const Icon(Icons.add_circle_outline_rounded, color: AppTheme.goldLight),
            tooltip: 'Upload New Note',
            onPressed: _showPublishModal,
          ),
        ],
      ),
      body: Column(
        children: [
          // Class Filter Bar
          Container(
            color: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                const Text('Select Division: ', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                const SizedBox(width: 10),
                Expanded(
                  child: DropdownButton<String>(
                    value: _classList.contains(_selectedClass) ? _selectedClass : _classList[0],
                    isExpanded: true,
                    underline: const SizedBox(),
                    style: const TextStyle(color: AppTheme.navyDark, fontWeight: FontWeight.bold, fontSize: 13),
                    items: _classList.map((c) => DropdownMenuItem(value: c, child: Text(c))).toList(),
                    onChanged: (v) {
                      if (v != null) {
                        setState(() => _selectedClass = v);
                        _loadNotes();
                      }
                    },
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1, color: AppTheme.slate200),

          // Notes List
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
                : _notes.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.menu_book_rounded, size: 54, color: AppTheme.slate400),
                            const SizedBox(height: 12),
                            Text('No study notes uploaded for $_selectedClass yet.', style: const TextStyle(color: AppTheme.slate600)),
                            const SizedBox(height: 16),
                            ElevatedButton.icon(
                              onPressed: _showPublishModal,
                              icon: const Icon(Icons.upload_file_rounded),
                              label: const Text('Upload First Note'),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppTheme.navyDark,
                                foregroundColor: Colors.white,
                              ),
                            ),
                          ],
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: _notes.length,
                        itemBuilder: (ctx, idx) {
                          final n = _notes[idx];
                          final fileName = n['file_name'] ?? 'Notes_Document.pdf';

                          return Container(
                            margin: const EdgeInsets.only(bottom: 12),
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(18),
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
                                  children: [
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                      decoration: BoxDecoration(
                                        color: AppTheme.indigoLight,
                                        borderRadius: BorderRadius.circular(10),
                                      ),
                                      child: Text(
                                        n['subject'] ?? 'Subject',
                                        style: const TextStyle(color: AppTheme.navyMedium, fontSize: 11, fontWeight: FontWeight.bold),
                                      ),
                                    ),
                                    const Spacer(),
                                    Text(
                                      n['created_at'] != null ? n['created_at'].toString().split(' ')[0] : 'Today',
                                      style: const TextStyle(color: AppTheme.slate400, fontSize: 11),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 10),
                                Text(
                                  n['title'] ?? 'Title',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: AppTheme.slate900),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  n['description'] ?? '',
                                  style: const TextStyle(color: AppTheme.slate600, fontSize: 12, height: 1.3),
                                ),
                                const SizedBox(height: 12),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                  decoration: BoxDecoration(
                                    color: AppTheme.bgLight,
                                    borderRadius: BorderRadius.circular(12),
                                    border: Border.all(color: AppTheme.slate200),
                                  ),
                                  child: Row(
                                    children: [
                                      const Icon(Icons.picture_as_pdf_rounded, color: AppTheme.rose, size: 20),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: Text(
                                          fileName,
                                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.slate800),
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ),
                                      const Icon(Icons.check_circle_rounded, color: AppTheme.emerald, size: 16),
                                    ],
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
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _showPublishModal,
        backgroundColor: AppTheme.navyDark,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.upload_file_rounded),
        label: const Text('Publish Notes'),
      ),
    );
  }
}
