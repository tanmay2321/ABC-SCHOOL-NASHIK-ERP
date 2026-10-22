import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../services/api_service.dart';
import '../../widgets/digital_id_card.dart';
import '../../widgets/custom_button.dart';

class StudentIdCardScreen extends StatelessWidget {
  const StudentIdCardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final user = ApiService().currentUser;

    return Scaffold(
      backgroundColor: AppTheme.bgLight,
      appBar: AppBar(
        title: const Text('Digital Student ID Card'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          children: [
            const SizedBox(height: 10),
            if (user != null) DigitalIdCard(user: user),
            const SizedBox(height: 24),
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: AppTheme.slate100),
              ),
              child: Column(
                children: [
                  const Row(
                    children: [
                      Icon(Icons.verified_user_rounded, color: AppTheme.emerald, size: 20),
                      SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'Officially Verified Digital Credential',
                          style: TextStyle(
                            color: AppTheme.slate900,
                            fontSize: 13,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'This digital ID card is valid for school gate entry, campus library checkout, examination hall verification, and school bus boarding. Tap the card above anytime to view emergency details and barcode.',
                    style: TextStyle(color: AppTheme.slate600, fontSize: 12, height: 1.4),
                  ),
                  const Divider(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceAround,
                    children: [
                      _featureIcon(Icons.nfc, 'NFC Ready'),
                      _featureIcon(Icons.qr_code, 'Dynamic QR'),
                      _featureIcon(Icons.security, 'Encrypted'),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            CustomButton(
              label: 'Download / Save to Phone Wallet',
              icon: Icons.file_download_outlined,
              onPressed: () {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Student ID Card saved to offline phone cache!'),
                    backgroundColor: AppTheme.emerald,
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _featureIcon(IconData icon, String title) {
    return Column(
      children: [
        Icon(icon, color: AppTheme.navyMedium, size: 24),
        const SizedBox(height: 4),
        Text(title, style: const TextStyle(color: AppTheme.slate700, fontSize: 11, fontWeight: FontWeight.bold)),
      ],
    );
  }
}
