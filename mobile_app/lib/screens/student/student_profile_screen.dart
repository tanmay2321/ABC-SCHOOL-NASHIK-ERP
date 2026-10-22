import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../login_screen.dart';

class StudentProfileScreen extends StatefulWidget {
  const StudentProfileScreen({super.key});

  @override
  State<StudentProfileScreen> createState() => _StudentProfileScreenState();
}

class _StudentProfileScreenState extends State<StudentProfileScreen> {
  bool _isSaving = false;

  void _showEditDialog() {
    final user = ApiService().currentUser;
    final phoneController = TextEditingController(text: user?.phone ?? '');
    final addressController = TextEditingController(text: user?.address ?? '');
    final bloodController = TextEditingController(text: user?.bloodGroup ?? 'O+');
    final passController = TextEditingController();

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          title: const Row(
            children: [
              Icon(Icons.edit_rounded, color: AppTheme.navyDark, size: 22),
              SizedBox(width: 8),
              Text('Edit Student Contact', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            ],
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: phoneController,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Emergency Phone',
                    prefixIcon: Icon(Icons.phone_rounded),
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: addressController,
                  decoration: const InputDecoration(
                    labelText: 'Residential Address',
                    prefixIcon: Icon(Icons.home_rounded),
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: bloodController,
                  decoration: const InputDecoration(
                    labelText: 'Blood Group',
                    prefixIcon: Icon(Icons.bloodtype_rounded),
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: passController,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'New Password (Optional)',
                    hintText: 'Leave empty to keep unchanged',
                    prefixIcon: Icon(Icons.lock_rounded),
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Cancel', style: TextStyle(color: AppTheme.slate500)),
            ),
            ElevatedButton(
              onPressed: _isSaving
                  ? null
                  : () async {
                      setDialogState(() => _isSaving = true);
                      final payload = <String, dynamic>{
                        'phone': phoneController.text.trim(),
                        'address': addressController.text.trim(),
                        'blood_group': bloodController.text.trim(),
                      };
                      if (passController.text.trim().isNotEmpty) {
                        payload['new_password'] = passController.text.trim();
                      }

                      final ok = await ApiService().updateProfile(payload);
                      setDialogState(() => _isSaving = false);
                      if (ctx.mounted) Navigator.pop(ctx);

                      if (mounted) {
                        setState(() {});
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(ok ? 'Profile updated successfully!' : 'Failed to update profile.'),
                            backgroundColor: ok ? AppTheme.emerald : AppTheme.rose,
                          ),
                        );
                      }
                    },
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.navyDark,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: _isSaving
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Text('Save Changes'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = ApiService().currentUser;
    final school = ApiService().schoolInfo;

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('Student Identity & Profile'),
        actions: [
          IconButton(
            icon: const Icon(Icons.edit_rounded, color: Colors.white),
            tooltip: 'Edit Profile',
            onPressed: _showEditDialog,
          ),
          IconButton(
            icon: const Icon(Icons.logout_rounded, color: AppTheme.roseLight),
            tooltip: 'Logout',
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
          const SizedBox(width: 8),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            // Avatar Header Card
            Container(
              padding: const EdgeInsets.all(22),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: AppTheme.slate100),
                boxShadow: [
                  BoxShadow(
                    color: AppTheme.navyDark.withValues(alpha: 0.05),
                    blurRadius: 16,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: Column(
                children: [
                  Stack(
                    alignment: Alignment.bottomRight,
                    children: [
                      Container(
                        width: 90,
                        height: 90,
                        decoration: BoxDecoration(
                          gradient: AppTheme.navyHeroGradient,
                          shape: BoxShape.circle,
                          border: Border.all(color: AppTheme.gold, width: 3),
                        ),
                        child: Center(
                          child: Text(
                            (user?.fullName.isNotEmpty ?? false) ? user!.fullName[0].toUpperCase() : 'S',
                            style: const TextStyle(color: AppTheme.gold, fontSize: 36, fontWeight: FontWeight.bold),
                          ),
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.all(5),
                        decoration: const BoxDecoration(
                          color: AppTheme.emerald,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(Icons.check, color: Colors.white, size: 14),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Text(
                    user?.fullName ?? 'Student',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: AppTheme.slate900, fontSize: 20, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 5),
                    decoration: BoxDecoration(
                      color: AppTheme.indigoLight,
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Text(
                      '${user?.studentClass ?? "Class 10-A"} • Roll No: #${user?.rollNo ?? "1"}',
                      style: const TextStyle(color: AppTheme.navyMedium, fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    onPressed: _showEditDialog,
                    icon: const Icon(Icons.edit_note_rounded, size: 18, color: AppTheme.navyDark),
                    label: const Text('Edit Details', style: TextStyle(color: AppTheme.navyDark, fontWeight: FontWeight.bold, fontSize: 12)),
                    style: OutlinedButton.styleFrom(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // Profile Fields
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: AppTheme.slate100),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Row(
                    children: [
                      Icon(Icons.school_rounded, color: AppTheme.navyDark, size: 20),
                      SizedBox(width: 8),
                      Text('Academic & Personal Details', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.slate900)),
                    ],
                  ),
                  const SizedBox(height: 16),
                  _field('Student Roll No', '#${user?.rollNo ?? "1"}'),
                  _field('Class & Division', user?.studentClass ?? 'Class 10-A'),
                  _field('Student Code', user?.userCode.isNotEmpty == true ? user!.userCode : 'STU-BWIS-2026'),
                  _field('Admission Number', user?.profile['admission_number']?.toString() ?? 'ADM-2026-084'),
                  _field('Official Email', user?.email ?? 'student@edu.in'),
                  _field('Blood Group', user?.bloodGroup ?? 'O+'),
                  _field('Guardian Name', user?.guardianName ?? 'Parent/Guardian'),
                  _field('Emergency Contact', user?.phone ?? '+91 98231 65432'),
                  _field('Residential Address', user?.address ?? 'Nandurbar, Maharashtra'),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // School Contact Info
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: AppTheme.slate100),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Row(
                    children: [
                      Icon(Icons.account_balance_rounded, color: AppTheme.navyDark, size: 20),
                      SizedBox(width: 8),
                      Text('Institution & Campus', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.slate900)),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(school?.name ?? 'BELLWETHER INTERNATIONAL SCHOOL NANDURBAR', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: AppTheme.navyDark)),
                  const SizedBox(height: 4),
                  Text('Helpline: ${school?.phone ?? "+91 6262848446"}', style: const TextStyle(fontSize: 11, color: AppTheme.slate600)),
                  Text('Email: ${school?.email ?? "bwis.nandurbar@gmail.com"}', style: const TextStyle(fontSize: 11, color: AppTheme.slate600)),
                  Text('Campus: ${school?.address ?? "Old Nalwa Road, Nandurbar - 425412"}', style: const TextStyle(fontSize: 11, color: AppTheme.slate600)),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // Logout Button
            SizedBox(
              width: double.infinity,
              height: 48,
              child: OutlinedButton.icon(
                onPressed: () async {
                  await ApiService().logout();
                  if (context.mounted) {
                    Navigator.of(context).pushAndRemoveUntil(
                      MaterialPageRoute(builder: (_) => const LoginScreen()),
                      (route) => false,
                    );
                  }
                },
                icon: const Icon(Icons.logout_rounded, color: AppTheme.rose),
                label: const Text('Sign Out from App', style: TextStyle(color: AppTheme.rose, fontWeight: FontWeight.bold)),
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: AppTheme.rose, width: 1.5),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _field(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppTheme.slate400, fontSize: 12)),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(color: AppTheme.slate900, fontWeight: FontWeight.bold, fontSize: 12),
            ),
          ),
        ],
      ),
    );
  }
}
