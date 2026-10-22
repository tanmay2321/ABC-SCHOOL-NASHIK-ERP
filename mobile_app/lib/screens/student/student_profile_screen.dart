import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../login_screen.dart';

class StudentProfileScreen extends StatelessWidget {
  const StudentProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final user = ApiService().currentUser;
    final school = ApiService().schoolInfo;

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('My Identity & Profile'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout_rounded, color: AppTheme.roseLight),
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
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: AppTheme.slate100),
              ),
              child: Column(
                children: [
                  Container(
                    width: 84,
                    height: 84,
                    decoration: BoxDecoration(
                      gradient: AppTheme.navyHeroGradient,
                      shape: BoxShape.circle,
                      border: Border.all(color: AppTheme.gold, width: 2.5),
                    ),
                    child: Center(
                      child: Text(
                        (user?.fullName.isNotEmpty ?? false) ? user!.fullName[0].toUpperCase() : 'S',
                        style: const TextStyle(color: AppTheme.gold, fontSize: 32, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    user?.fullName ?? 'Student',
                    style: const TextStyle(color: AppTheme.slate900, fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: AppTheme.indigoLight,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      '${user?.studentClass ?? "Class 10"} • Roll No: ${user?.rollNo ?? "101"}',
                      style: const TextStyle(color: AppTheme.navyMedium, fontSize: 11, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // Profile Fields
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: AppTheme.slate100),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Academic & Personal Records', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                  const SizedBox(height: 14),
                  _field('Student Code', user?.userCode.isNotEmpty == true ? user!.userCode : 'STU-2026-1025'),
                  _field('Email Address', user?.email ?? 'student@edu.in'),
                  _field('Blood Group', user?.bloodGroup ?? 'O+'),
                  _field('Guardian Name', user?.guardianName ?? 'Rajesh Sharma'),
                  _field('Emergency Phone', user?.phone ?? '+91 98231 65432'),
                  _field('Residential Address', user?.address ?? 'Old Nalwa Road, Nandurbar'),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // School Contact Info
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: AppTheme.slate100),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('School Information', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
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
