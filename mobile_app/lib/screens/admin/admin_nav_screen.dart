import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../../widgets/bento_card.dart';
import '../../widgets/section_header.dart';
import '../login_screen.dart';

class AdminNavScreen extends StatefulWidget {
  const AdminNavScreen({super.key});

  @override
  State<AdminNavScreen> createState() => _AdminNavScreenState();
}

class _AdminNavScreenState extends State<AdminNavScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  Map<String, dynamic>? _dashData;
  List<dynamic> _students = [];
  List<dynamic> _teachers = [];
  Map<String, dynamic>? _academicData;
  Map<String, dynamic>? _settingsData;
  bool _isLoading = true;
  String _selectedClassFilter = 'All';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 5, vsync: this);
    _loadAllData();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadAllData() async {
    setState(() => _isLoading = true);
    try {
      final dashFuture = ApiService().fetchDashboard();
      final studentsFuture = ApiService().fetchStudents(
        studentClass: _selectedClassFilter == 'All' ? null : _selectedClassFilter,
      );
      final teachersFuture = ApiService().fetchTeachers();
      final academicFuture = ApiService().fetchAcademicYear();
      final settingsFuture = ApiService().fetchSettings();

      final results = await Future.wait([
        dashFuture,
        studentsFuture,
        teachersFuture,
        academicFuture,
        settingsFuture,
      ]);

      if (mounted) {
        setState(() {
          _dashData = results[0] as Map<String, dynamic>;
          _students = results[1] as List<dynamic>;
          _teachers = results[2] as List<dynamic>;
          _academicData = results[3] as Map<String, dynamic>;
          _settingsData = results[4] as Map<String, dynamic>;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error syncing data: $e'), backgroundColor: AppTheme.rose),
        );
      }
    }
  }

  // --- ACTIONS ---

  Future<void> _approveStudent(int userId, String name) async {
    try {
      await ApiService().approveStudent(userId);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Approved student: $name! They can now log in.'), backgroundColor: AppTheme.emerald),
        );
        _loadAllData();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error approving student: $e'), backgroundColor: AppTheme.rose),
        );
      }
    }
  }

  Future<void> _rejectStudent(int userId, String name) async {
    try {
      await ApiService().rejectStudent(userId);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Rejected registration for $name.'), backgroundColor: AppTheme.amber),
        );
        _loadAllData();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: AppTheme.rose),
        );
      }
    }
  }

  void _showAddStudentDialog() {
    final nameCtrl = TextEditingController();
    final emailCtrl = TextEditingController();
    final classCtrl = TextEditingController(text: 'Class 10');
    final rollCtrl = TextEditingController();
    final guardianCtrl = TextEditingController();
    final phoneCtrl = TextEditingController();
    final addressCtrl = TextEditingController(text: 'Nandurbar');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Enroll New Student', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Full Name *')),
              const SizedBox(height: 8),
              TextField(controller: emailCtrl, decoration: const InputDecoration(labelText: 'Email Address *', hintText: 'student@edu.in')),
              const SizedBox(height: 8),
              TextField(controller: classCtrl, decoration: const InputDecoration(labelText: 'Class *', hintText: 'Class 10')),
              const SizedBox(height: 8),
              TextField(controller: rollCtrl, decoration: const InputDecoration(labelText: 'Roll Number (optional)')),
              const SizedBox(height: 8),
              TextField(controller: guardianCtrl, decoration: const InputDecoration(labelText: 'Guardian Name')),
              const SizedBox(height: 8),
              TextField(controller: phoneCtrl, decoration: const InputDecoration(labelText: 'Phone Number')),
              const SizedBox(height: 8),
              TextField(controller: addressCtrl, decoration: const InputDecoration(labelText: 'Address')),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.navyDark, foregroundColor: Colors.white),
            onPressed: () async {
              if (nameCtrl.text.trim().isEmpty || emailCtrl.text.trim().isEmpty) return;
              try {
                await ApiService().createStudent({
                  'full_name': nameCtrl.text.trim(),
                  'email': emailCtrl.text.trim(),
                  'class': classCtrl.text.trim(),
                  'roll_no': rollCtrl.text.trim(),
                  'guardian_name': guardianCtrl.text.trim(),
                  'phone': phoneCtrl.text.trim(),
                  'address': addressCtrl.text.trim(),
                });
                if (ctx.mounted) Navigator.pop(ctx);
                _loadAllData();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Enrolled ${nameCtrl.text.trim()} successfully!'), backgroundColor: AppTheme.emerald),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Enrollment failed: $e'), backgroundColor: AppTheme.rose),
                  );
                }
              }
            },
            child: const Text('Enroll Student'),
          ),
        ],
      ),
    );
  }

  void _showAddTeacherDialog() {
    final nameCtrl = TextEditingController();
    final emailCtrl = TextEditingController();
    final deptCtrl = TextEditingController(text: 'Science & Mathematics');
    final desigCtrl = TextEditingController(text: 'Senior Faculty');
    final classCtrl = TextEditingController(text: 'Class 10');
    final phoneCtrl = TextEditingController(text: '+91 9876543210');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Add Faculty Member', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Full Name *', hintText: 'Prof. / Dr. / Mrs.')),
              const SizedBox(height: 8),
              TextField(controller: emailCtrl, decoration: const InputDecoration(labelText: 'Email Address *', hintText: 'teacher@edu.in')),
              const SizedBox(height: 8),
              TextField(controller: deptCtrl, decoration: const InputDecoration(labelText: 'Department')),
              const SizedBox(height: 8),
              TextField(controller: desigCtrl, decoration: const InputDecoration(labelText: 'Designation')),
              const SizedBox(height: 8),
              TextField(controller: classCtrl, decoration: const InputDecoration(labelText: 'Assigned Class')),
              const SizedBox(height: 8),
              TextField(controller: phoneCtrl, decoration: const InputDecoration(labelText: 'Phone Number')),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.navyDark, foregroundColor: Colors.white),
            onPressed: () async {
              if (nameCtrl.text.trim().isEmpty || emailCtrl.text.trim().isEmpty) return;
              try {
                await ApiService().createTeacher({
                  'full_name': nameCtrl.text.trim(),
                  'email': emailCtrl.text.trim(),
                  'department': deptCtrl.text.trim(),
                  'designation': desigCtrl.text.trim(),
                  'assigned_class': classCtrl.text.trim(),
                  'phone': phoneCtrl.text.trim(),
                });
                if (ctx.mounted) Navigator.pop(ctx);
                _loadAllData();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Faculty ${nameCtrl.text.trim()} added successfully!'), backgroundColor: AppTheme.emerald),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Failed: $e'), backgroundColor: AppTheme.rose),
                  );
                }
              }
            },
            child: const Text('Add Faculty'),
          ),
        ],
      ),
    );
  }

  void _showPromoteDialog() {
    final srcCtrl = TextEditingController(text: 'Class 9');
    final tgtCtrl = TextEditingController(text: 'Class 10');
    final yearCtrl = TextEditingController(text: '2027-2028');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.upgrade_rounded, color: AppTheme.gold),
            SizedBox(width: 8),
            Text('Promote Class Batch', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Promoting a class moves all its enrolled students into the next academic grade and updates their LMS dashboards immediately.',
              style: TextStyle(fontSize: 12, color: AppTheme.slate600),
            ),
            const SizedBox(height: 14),
            TextField(controller: srcCtrl, decoration: const InputDecoration(labelText: 'Source Class *', hintText: 'e.g. Class 9')),
            const SizedBox(height: 10),
            TextField(controller: tgtCtrl, decoration: const InputDecoration(labelText: 'Target Promoted Class *', hintText: 'e.g. Class 10')),
            const SizedBox(height: 10),
            TextField(controller: yearCtrl, decoration: const InputDecoration(labelText: 'Academic Year *', hintText: '2027-2028')),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.goldDark, foregroundColor: Colors.white),
            onPressed: () async {
              try {
                final res = await ApiService().shiftAcademicYear(
                  sourceClass: srcCtrl.text.trim(),
                  targetClass: tgtCtrl.text.trim(),
                  targetYear: yearCtrl.text.trim(),
                );
                if (ctx.mounted) Navigator.pop(ctx);
                _loadAllData();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(res['message'] ?? 'Batch promoted successfully!'), backgroundColor: AppTheme.emerald),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Promotion failed: $e'), backgroundColor: AppTheme.rose),
                  );
                }
              }
            },
            child: const Text('Execute Batch Promotion'),
          ),
        ],
      ),
    );
  }

  void _showBroadcastNoticeDialog() {
    final titleCtrl = TextEditingController();
    final contentCtrl = TextEditingController();
    String category = 'Circular';
    String audience = 'all';

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          title: const Text('Broadcast Official Notice', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(controller: titleCtrl, decoration: const InputDecoration(labelText: 'Notice Title *')),
                const SizedBox(height: 10),
                TextField(controller: contentCtrl, maxLines: 4, decoration: const InputDecoration(labelText: 'Notice Content *')),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: category,
                  decoration: const InputDecoration(labelText: 'Category'),
                  items: const [
                    DropdownMenuItem(value: 'Circular', child: Text('Official Circular')),
                    DropdownMenuItem(value: 'Examination', child: Text('Examination Alert')),
                    DropdownMenuItem(value: 'Holiday', child: Text('Holiday Announcement')),
                    DropdownMenuItem(value: 'Academic', child: Text('Academic Event')),
                  ],
                  onChanged: (v) => setDialogState(() => category = v!),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  initialValue: audience,
                  decoration: const InputDecoration(labelText: 'Audience'),
                  items: const [
                    DropdownMenuItem(value: 'all', child: Text('All (School-Wide)')),
                    DropdownMenuItem(value: 'students', child: Text('Students Only')),
                    DropdownMenuItem(value: 'teachers', child: Text('Teachers Only')),
                  ],
                  onChanged: (v) => setDialogState(() => audience = v!),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: AppTheme.navyDark, foregroundColor: Colors.white),
              onPressed: () async {
                if (titleCtrl.text.trim().isEmpty || contentCtrl.text.trim().isEmpty) return;
                try {
                  await ApiService().postNotice(
                    title: titleCtrl.text.trim(),
                    content: contentCtrl.text.trim(),
                    category: category,
                    audience: audience,
                  );
                  if (ctx.mounted) Navigator.pop(ctx);
                  _loadAllData();
                  if (mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Notice broadcasted to all web and mobile apps!'), backgroundColor: AppTheme.emerald),
                    );
                  }
                } catch (e) {
                  if (mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text('Failed: $e'), backgroundColor: AppTheme.rose),
                    );
                  }
                }
              },
              child: const Text('Broadcast Notice'),
            ),
          ],
        ),
      ),
    );
  }

  void _showSettingsDialog() {
    final s = _settingsData ?? {};
    final nameCtrl = TextEditingController(text: s['school_name'] ?? 'BELLWETHER INTERNATIONAL SCHOOL NANDURBAR');
    final cityCtrl = TextEditingController(text: s['school_city'] ?? 'Nandurbar');
    final phoneCtrl = TextEditingController(text: s['school_phone'] ?? '+91 6262848446');
    final emailCtrl = TextEditingController(text: s['school_email'] ?? 'bwis.nandurbar@gmail.com');
    final addressCtrl = TextEditingController(text: s['school_address'] ?? 'Old Nalwa Road, Nandurbar - 425412');
    final principalCtrl = TextEditingController(text: s['principal_name'] ?? 'Dr. R. K. Sharma (Principal)');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('School Settings & Identity', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'School Name *')),
              const SizedBox(height: 8),
              TextField(controller: cityCtrl, decoration: const InputDecoration(labelText: 'City *')),
              const SizedBox(height: 8),
              TextField(controller: phoneCtrl, decoration: const InputDecoration(labelText: 'Phone Number *')),
              const SizedBox(height: 8),
              TextField(controller: emailCtrl, decoration: const InputDecoration(labelText: 'Email Address *')),
              const SizedBox(height: 8),
              TextField(controller: addressCtrl, decoration: const InputDecoration(labelText: 'Address *')),
              const SizedBox(height: 8),
              TextField(controller: principalCtrl, decoration: const InputDecoration(labelText: 'Principal Name *')),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.navyDark, foregroundColor: Colors.white),
            onPressed: () async {
              try {
                await ApiService().updateSettings({
                  'school_name': nameCtrl.text.trim(),
                  'school_city': cityCtrl.text.trim(),
                  'school_phone': phoneCtrl.text.trim(),
                  'school_email': emailCtrl.text.trim(),
                  'school_address': addressCtrl.text.trim(),
                  'principal_name': principalCtrl.text.trim(),
                });
                if (ctx.mounted) Navigator.pop(ctx);
                _loadAllData();
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('School settings updated across website and app!'), backgroundColor: AppTheme.emerald),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Failed: $e'), backgroundColor: AppTheme.rose),
                  );
                }
              }
            },
            child: const Text('Save Changes'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = ApiService().currentUser;
    final metrics = _dashData?['admin_metrics'] ?? {};

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'BELLWETHER INTERNATIONAL SCHOOL',
              style: TextStyle(color: AppTheme.gold, fontSize: 10, fontWeight: FontWeight.bold, letterSpacing: 1.2),
            ),
            Text(
              user?.fullName ?? 'Administrator Portal',
              style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.bold),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.settings_outlined, color: Colors.white70),
            tooltip: 'School Branding & Settings',
            onPressed: _showSettingsDialog,
          ),
          IconButton(
            icon: const Icon(Icons.logout_rounded, color: AppTheme.roseLight),
            tooltip: 'Log Out',
            onPressed: () async {
              await ApiService().logout();
              if (context.mounted) {
                Navigator.of(context).pushAndRemoveUntil(
                  MaterialPageRoute(builder: (_) => const LoginScreen()),
                  (route) => false,
                );
              }
            },
          ),
        ],
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          indicatorColor: AppTheme.gold,
          labelColor: AppTheme.gold,
          unselectedLabelColor: Colors.white60,
          labelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
          tabs: const [
            Tab(icon: Icon(Icons.dashboard_rounded, size: 18), text: 'Overview'),
            Tab(icon: Icon(Icons.people_alt_rounded, size: 18), text: 'Students'),
            Tab(icon: Icon(Icons.badge_rounded, size: 18), text: 'Faculty'),
            Tab(icon: Icon(Icons.calendar_month_rounded, size: 18), text: 'Academic Year'),
            Tab(icon: Icon(Icons.campaign_rounded, size: 18), text: 'Notices'),
          ],
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.navyDark))
          : TabBarView(
              controller: _tabController,
              children: [
                _buildOverviewTab(metrics),
                _buildStudentsTab(),
                _buildFacultyTab(),
                _buildAcademicYearTab(),
                _buildNoticesTab(),
              ],
            ),
    );
  }

  // --- TAB 1: OVERVIEW DASHBOARD ---
  Widget _buildOverviewTab(Map<String, dynamic> metrics) {
    final double feesTotal = (metrics['fees_total'] is num) ? (metrics['fees_total'] as num).toDouble() : 0.0;
    final double feesCollected = (metrics['fees_collected'] is num) ? (metrics['fees_collected'] as num).toDouble() : 0.0;
    final double feeCollectionPct = feesTotal > 0 ? (feesCollected / feesTotal) * 100 : 0.0;
    final int pending = (metrics['pending_admissions'] is num) ? (metrics['pending_admissions'] as num).toInt() : 0;
    final notices = _dashData?['recent_notices'] as List<dynamic>? ?? [];

    return RefreshIndicator(
      onRefresh: _loadAllData,
      child: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          // Live Sync Status Card
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: AppTheme.emerald.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppTheme.emerald.withValues(alpha: 0.3)),
            ),
            child: Row(
              children: [
                const Icon(Icons.cloud_done_rounded, color: AppTheme.emerald, size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Live Database Connected • Changes update website and mobile instantly',
                    style: TextStyle(color: AppTheme.emerald.withValues(alpha: 0.9), fontSize: 11, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),

          // KPI Hero Card
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
                const Text(
                  'EXECUTIVE ERP OVERVIEW',
                  style: TextStyle(color: AppTheme.gold, fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 1.2),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Live Campus Operations',
                  style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    _heroStat('STUDENTS', '${metrics['total_students'] ?? _students.length}', Icons.groups_rounded),
                    _heroStat('FACULTY', '${metrics['total_teachers'] ?? _teachers.length}', Icons.school_rounded),
                    _heroStat('PENDING', '$pending', Icons.pending_actions_rounded),
                    _heroStat('ATTENDANCE', '${metrics['today_attendance_rate'] ?? 95}%', Icons.check_circle_rounded),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),

          // Fees Collection Bento Card
          BentoCard(
            title: 'Fee Collection Ledger',
            subtitle: 'Live Financial Tracking',
            icon: Icons.payments_rounded,
            iconColor: AppTheme.emerald,
            iconBg: AppTheme.emeraldLight,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Collected', style: TextStyle(color: AppTheme.slate400, fontSize: 11)),
                        Text('₹${feesCollected.toStringAsFixed(0)}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: AppTheme.emerald)),
                      ],
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        const Text('Total Billed', style: TextStyle(color: AppTheme.slate400, fontSize: 11)),
                        Text('₹${feesTotal.toStringAsFixed(0)}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: AppTheme.navyDark)),
                      ],
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: LinearProgressIndicator(
                    value: (feeCollectionPct / 100).clamp(0.0, 1.0),
                    backgroundColor: AppTheme.slate100,
                    valueColor: const AlwaysStoppedAnimation<Color>(AppTheme.emerald),
                    minHeight: 8,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),

          // Quick Action Buttons
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.navyDark,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                  icon: const Icon(Icons.person_add_rounded, size: 18),
                  label: const Text('Add Student', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                  onPressed: _showAddStudentDialog,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.goldDark,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                  icon: const Icon(Icons.campaign_rounded, size: 18),
                  label: const Text('Broadcast Notice', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                  onPressed: _showBroadcastNoticeDialog,
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),

          // Recent Digital Notices
          SectionHeader(
            title: 'Digital Circulars & Notices',
            actionText: 'View All',
            onAction: () => _tabController.animateTo(4),
          ),
          const SizedBox(height: 10),
          if (notices.isEmpty)
            const Text('No circulars posted yet.', style: TextStyle(color: AppTheme.slate400, fontSize: 12))
          else
            ...notices.take(3).map((n) => Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                    side: const BorderSide(color: AppTheme.slate200),
                  ),
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: AppTheme.navyDark.withValues(alpha: 0.08),
                      child: const Icon(Icons.article_outlined, color: AppTheme.navyDark, size: 20),
                    ),
                    title: Text(n['title'] ?? 'Notice', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                    subtitle: Text(n['content'] ?? '', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11)),
                    trailing: Text(n['category'] ?? 'Notice', style: const TextStyle(fontSize: 10, color: AppTheme.slate400)),
                  ),
                )),
        ],
      ),
    );
  }

  // --- TAB 2: STUDENTS ROSTER & APPROVALS ---
  Widget _buildStudentsTab() {
    return Column(
      children: [
        // Filter bar
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          color: Colors.white,
          child: Row(
            children: [
              const Text('Class: ', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
              const SizedBox(width: 8),
              DropdownButton<String>(
                value: _selectedClassFilter,
                underline: const SizedBox(),
                style: const TextStyle(color: AppTheme.navyDark, fontWeight: FontWeight.bold, fontSize: 12),
                items: ['All', 'Class 8', 'Class 9', 'Class 10', 'Class 11', 'Class 12']
                    .map((c) => DropdownMenuItem(value: c, child: Text(c)))
                    .toList(),
                onChanged: (v) {
                  setState(() => _selectedClassFilter = v!);
                  _loadAllData();
                },
              ),
              const Spacer(),
              ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.navyDark,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                icon: const Icon(Icons.add, size: 16),
                label: const Text('New Student', style: TextStyle(fontSize: 11)),
                onPressed: _showAddStudentDialog,
              ),
            ],
          ),
        ),

        Expanded(
          child: _students.isEmpty
              ? const Center(child: Text('No students found for this class.', style: TextStyle(color: AppTheme.slate400)))
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _students.length,
                  itemBuilder: (ctx, i) {
                    final s = _students[i];
                    final isPending = s['status'] == 'pending';
                    final isRejected = s['status'] == 'rejected';

                    return Card(
                      margin: const EdgeInsets.only(bottom: 12),
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                        side: BorderSide(
                          color: isPending ? AppTheme.amber.withValues(alpha: 0.5) : AppTheme.slate200,
                        ),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                CircleAvatar(
                                  backgroundColor: AppTheme.navyDark.withValues(alpha: 0.1),
                                  child: Text(
                                    s['roll_no']?.toString().isNotEmpty == true ? s['roll_no'].toString() : '#',
                                    style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.navyDark, fontSize: 12),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(s['full_name'] ?? 'Student', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                      Text('${s['class']} • Roll ${s['roll_no']} • ${s['student_code']}', style: const TextStyle(color: AppTheme.slate600, fontSize: 11)),
                                    ],
                                  ),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: isPending ? AppTheme.amberLight : (isRejected ? AppTheme.roseLight : AppTheme.emeraldLight),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Text(
                                    (s['status'] ?? 'approved').toString().toUpperCase(),
                                    style: TextStyle(
                                      color: isPending ? AppTheme.amberDark : (isRejected ? AppTheme.roseDark : AppTheme.emeraldDark),
                                      fontSize: 10,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            if (s['email'] != null) ...[
                              const SizedBox(height: 6),
                              Text('Email: ${s['email']} • Phone: ${s['phone'] ?? "N/A"}', style: const TextStyle(color: AppTheme.slate400, fontSize: 10)),
                            ],
                            if (isPending) ...[
                              const Divider(height: 18),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.end,
                                children: [
                                  TextButton(
                                    onPressed: () => _rejectStudent(s['user_id'], s['full_name']),
                                    child: const Text('Reject', style: TextStyle(color: AppTheme.rose, fontSize: 12)),
                                  ),
                                  const SizedBox(width: 8),
                                  ElevatedButton(
                                    style: ElevatedButton.styleFrom(backgroundColor: AppTheme.emerald, foregroundColor: Colors.white),
                                    onPressed: () => _approveStudent(s['user_id'], s['full_name']),
                                    child: const Text('Approve Admission', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                                  ),
                                ],
                              ),
                            ],
                          ],
                        ),
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }

  // --- TAB 3: FACULTY MANAGEMENT ---
  Widget _buildFacultyTab() {
    return Column(
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          color: Colors.white,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('Faculty Members (${_teachers.length})', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.navyDark,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                icon: const Icon(Icons.person_add_alt_1_rounded, size: 16),
                label: const Text('Add Faculty', style: TextStyle(fontSize: 11)),
                onPressed: _showAddTeacherDialog,
              ),
            ],
          ),
        ),
        Expanded(
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: _teachers.length,
            itemBuilder: (ctx, i) {
              final t = _teachers[i];
              return Card(
                margin: const EdgeInsets.only(bottom: 12),
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                  side: const BorderSide(color: AppTheme.slate200),
                ),
                child: ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: AppTheme.navyDark,
                    foregroundColor: AppTheme.gold,
                    child: Icon(Icons.school, size: 20),
                  ),
                  title: Text(t['full_name'] ?? 'Faculty', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                  subtitle: Text('${t['designation']} • ${t['department']}\nAssigned: ${t['assigned_class'] ?? "Unassigned"} • Phone: ${t['phone'] ?? "N/A"}', style: const TextStyle(fontSize: 11, color: AppTheme.slate600)),
                  isThreeLine: true,
                  trailing: IconButton(
                    icon: const Icon(Icons.edit_calendar_rounded, color: AppTheme.navyMedium),
                    tooltip: 'Assign Class',
                    onPressed: () {
                      final cCtrl = TextEditingController(text: t['assigned_class'] ?? 'Class 10');
                      showDialog(
                        context: context,
                        builder: (ctx2) => AlertDialog(
                          title: Text('Assign Class to ${t['full_name']}', style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                          content: TextField(controller: cCtrl, decoration: const InputDecoration(labelText: 'Assigned Class')),
                          actions: [
                            TextButton(onPressed: () => Navigator.pop(ctx2), child: const Text('Cancel')),
                            ElevatedButton(
                              onPressed: () async {
                                await ApiService().assignTeacherClass(t['id'], cCtrl.text.trim());
                                if (ctx2.mounted) Navigator.pop(ctx2);
                                _loadAllData();
                              },
                              child: const Text('Save'),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  // --- TAB 4: ACADEMIC YEAR & PROMOTION ---
  Widget _buildAcademicYearTab() {
    final year = _academicData?['current_academic_year'] ?? '2026-2027';
    final classes = _academicData?['classes'] as List<dynamic>? ?? [];

    return ListView(
      padding: const EdgeInsets.all(18),
      children: [
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [AppTheme.goldDark, AppTheme.gold],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(22),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('ACTIVE ACADEMIC CALENDAR', style: TextStyle(color: AppTheme.navyDark, fontSize: 11, fontWeight: FontWeight.w900, letterSpacing: 1.2)),
              const SizedBox(height: 6),
              Text(year, style: const TextStyle(color: AppTheme.navyDark, fontSize: 26, fontWeight: FontWeight.w900)),
              const SizedBox(height: 10),
              const Text(
                'All LMS courses, grading schemes, and report cards are locked to this active academic year.',
                style: TextStyle(color: AppTheme.navyDark, fontSize: 11),
              ),
              const SizedBox(height: 14),
              ElevatedButton.icon(
                style: ElevatedButton.styleFrom(backgroundColor: AppTheme.navyDark, foregroundColor: Colors.white),
                icon: const Icon(Icons.upgrade_rounded, size: 18),
                label: const Text('Shift Academic Year & Promote Students', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                onPressed: _showPromoteDialog,
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),

        const SectionHeader(title: 'Class Cohorts & Student Distribution'),
        const SizedBox(height: 10),

        if (classes.isEmpty)
          const Text('No classes enrolled yet.', style: TextStyle(color: AppTheme.slate400))
        else
          ...classes.map((c) => Card(
                margin: const EdgeInsets.only(bottom: 10),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                  side: const BorderSide(color: AppTheme.slate200),
                ),
                elevation: 0,
                child: ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: AppTheme.navyDark,
                    child: Icon(Icons.class_, color: Colors.white, size: 18),
                  ),
                  title: Text(c['class'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                  subtitle: Text('Suggested Next Grade: ${c['suggested_next_class']}', style: const TextStyle(fontSize: 11, color: AppTheme.slate600)),
                  trailing: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(color: AppTheme.slate100, borderRadius: BorderRadius.circular(12)),
                    child: Text('${c['student_count']} Students', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: AppTheme.navyDark)),
                  ),
                ),
              )),
      ],
    );
  }

  // --- TAB 5: NOTICES & CIRCULARS ---
  Widget _buildNoticesTab() {
    final notices = _dashData?['recent_notices'] as List<dynamic>? ?? [];

    return Scaffold(
      backgroundColor: Colors.transparent,
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: AppTheme.navyDark,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add),
        label: const Text('Post Circular'),
        onPressed: _showBroadcastNoticeDialog,
      ),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          const SectionHeader(title: 'Official School Circulars'),
          const SizedBox(height: 12),
          if (notices.isEmpty)
            const Text('No circulars broadcasted yet.', style: TextStyle(color: AppTheme.slate400))
          else
            ...notices.map((n) => Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                    side: const BorderSide(color: AppTheme.slate200),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                color: AppTheme.goldLight.withValues(alpha: 0.3),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Text(
                                n['category'] ?? 'Circular',
                                style: const TextStyle(color: AppTheme.goldDark, fontSize: 10, fontWeight: FontWeight.bold),
                              ),
                            ),
                            Text(
                              'Audience: ${(n['audience'] ?? "all").toString().toUpperCase()}',
                              style: const TextStyle(color: AppTheme.slate400, fontSize: 10),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(n['title'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                        const SizedBox(height: 6),
                        Text(n['content'] ?? '', style: const TextStyle(fontSize: 12, color: AppTheme.slate600)),
                      ],
                    ),
                  ),
                )),
        ],
      ),
    );
  }

  Widget _heroStat(String label, String value, IconData icon) {
    return Column(
      children: [
        Icon(icon, color: AppTheme.gold, size: 20),
        const SizedBox(height: 4),
        Text(value, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
        Text(label, style: const TextStyle(color: Colors.white60, fontSize: 9, fontWeight: FontWeight.w600)),
      ],
    );
  }
}
