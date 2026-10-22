import 'dart:math';
import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../models/user_model.dart';

class DigitalIdCard extends StatefulWidget {
  final UserModel user;
  const DigitalIdCard({super.key, required this.user});

  @override
  State<DigitalIdCard> createState() => _DigitalIdCardState();
}

class _DigitalIdCardState extends State<DigitalIdCard> with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _animation;
  bool _showFront = true;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 600));
    _animation = Tween<double>(begin: 0, end: 1).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeInOutBack),
    );
  }

  void _flipCard() {
    if (_showFront) {
      _controller.forward();
    } else {
      _controller.reverse();
    }
    setState(() {
      _showFront = !_showFront;
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: _flipCard,
      child: AnimatedBuilder(
        animation: _animation,
        builder: (context, child) {
          final angle = _animation.value * pi;
          final isFront = angle <= (pi / 2);
          return Transform(
            transform: Matrix4.identity()
              ..setEntry(3, 2, 0.0015)
              ..rotateY(angle),
            alignment: Alignment.center,
            child: isFront ? _buildFrontSide() : Transform(
              transform: Matrix4.identity()..rotateY(pi),
              alignment: Alignment.center,
              child: _buildBackSide(),
            ),
          );
        },
      ),
    );
  }

  Widget _buildFrontSide() {
    return Container(
      width: double.infinity,
      height: 230,
      decoration: BoxDecoration(
        gradient: AppTheme.navyHeroGradient,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppTheme.gold.withValues(alpha: 0.6), width: 1.5),
        boxShadow: [
          BoxShadow(
            color: AppTheme.navyDark.withValues(alpha: 0.35),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Stack(
        children: [
          // Background Watermark / Hologram Pattern
          Positioned(
            right: -30,
            bottom: -30,
            child: Icon(
              Icons.school_rounded,
              size: 200,
              color: Colors.white.withValues(alpha: 0.04),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                // Header: School Name & Chip
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 34,
                          height: 34,
                          decoration: BoxDecoration(
                            gradient: AppTheme.goldGradient,
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(Icons.school, color: AppTheme.navyDark, size: 18),
                        ),
                        const SizedBox(width: 10),
                        const Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'BELLWETHER INTERNATIONAL',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 11,
                                fontWeight: FontWeight.w900,
                                letterSpacing: 0.8,
                              ),
                            ),
                            Text(
                              'SCHOOL NANDURBAR • SMART ID',
                              style: TextStyle(
                                color: AppTheme.goldLight,
                                fontSize: 9,
                                fontWeight: FontWeight.bold,
                                letterSpacing: 1.2,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: Colors.white24),
                      ),
                      child: const Row(
                        children: [
                          Icon(Icons.nfc, color: AppTheme.gold, size: 14),
                          SizedBox(width: 4),
                          Text('ACTIVE', style: TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.bold)),
                        ],
                      ),
                    ),
                  ],
                ),

                // Middle: Photo & Details
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    // Avatar / Photo
                    Container(
                      width: 72,
                      height: 72,
                      decoration: BoxDecoration(
                        color: AppTheme.navyCard,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: AppTheme.gold, width: 2),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.2),
                            blurRadius: 8,
                          ),
                        ],
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(14),
                        child: widget.user.photo != null
                            ? Image.network(widget.user.photo!, fit: BoxFit.cover, errorBuilder: (context, error, stackTrace) => _avatarFallback())
                            : _avatarFallback(),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            widget.user.fullName,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 16,
                              fontWeight: FontWeight.bold,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: 4),
                          Text(
                            widget.user.role == 'student'
                                ? '${widget.user.studentClass} • Roll No: ${widget.user.rollNo}'
                                : '${widget.user.department} • Faculty',
                            style: const TextStyle(
                              color: AppTheme.goldLight,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'ID: ${widget.user.userCode.isNotEmpty ? widget.user.userCode : "BWIS-${widget.user.id}"}',
                            style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.7),
                              fontSize: 11,
                              fontFamily: 'monospace',
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),

                // Bottom: Barcode & Tap Hint
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    // Simulated Barcode
                    Row(
                      children: List.generate(
                        24,
                        (index) => Container(
                          margin: const EdgeInsets.only(right: 2),
                          width: (index % 3 == 0) ? 3 : ((index % 2 == 0) ? 1.5 : 2),
                          height: 18,
                          color: Colors.white.withValues(alpha: 0.7),
                        ),
                      ),
                    ),
                    const Row(
                      children: [
                        Icon(Icons.touch_app, color: AppTheme.gold, size: 13),
                        SizedBox(width: 4),
                        Text(
                          'Tap to Flip Card',
                          style: TextStyle(color: Colors.white70, fontSize: 10, fontWeight: FontWeight.bold),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBackSide() {
    return Container(
      width: double.infinity,
      height: 230,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF0F172A), Color(0xFF1E293B), Color(0xFF0A1128)],
        ),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppTheme.gold.withValues(alpha: 0.6), width: 1.5),
        boxShadow: [
          BoxShadow(
            color: AppTheme.navyDark.withValues(alpha: 0.35),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            // Top: Magnetic Stripe
            Container(
              height: 32,
              width: double.infinity,
              decoration: BoxDecoration(
                color: Colors.black,
                borderRadius: BorderRadius.circular(6),
              ),
              alignment: Alignment.centerLeft,
              padding: const EdgeInsets.symmetric(horizontal: 10),
              child: const Text(
                'SECURITY ENCRYPTED • VERIFIED CHIP 2026',
                style: TextStyle(color: Colors.white30, fontSize: 9, letterSpacing: 1),
              ),
            ),

            // Middle: Emergency Info & QR Code
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _infoRow('Blood Group', widget.user.bloodGroup),
                      const SizedBox(height: 4),
                      _infoRow('Emergency Contact', widget.user.phone.isNotEmpty ? widget.user.phone : '+91 6262848446'),
                      const SizedBox(height: 4),
                      _infoRow('Guardian', widget.user.guardianName.isNotEmpty ? widget.user.guardianName : 'Office Record'),
                    ],
                  ),
                ),
                // Simulated QR Code
                Container(
                  width: 58,
                  height: 58,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: AppTheme.gold, width: 1.5),
                  ),
                  child: const Center(
                    child: Icon(Icons.qr_code_2, color: AppTheme.navyDark, size: 50),
                  ),
                ),
              ],
            ),

            // Bottom: Principal Stamp & Address
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Old Nalwa Road, Nandurbar - 425412',
                      style: TextStyle(color: Colors.white54, fontSize: 9),
                    ),
                    Text(
                      'Helpline: +91 6262848446',
                      style: TextStyle(color: AppTheme.goldLight, fontSize: 9, fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        border: Border.all(color: AppTheme.gold.withValues(alpha: 0.5)),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: const Text(
                        'Dr. R. K. Sharma',
                        style: TextStyle(
                          color: AppTheme.goldLight,
                          fontSize: 9,
                          fontStyle: FontStyle.italic,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    const SizedBox(height: 2),
                    const Text('Principal Signatory', style: TextStyle(color: Colors.white54, fontSize: 8)),
                  ],
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Row(
      children: [
        Text(
          '$label: ',
          style: const TextStyle(color: Colors.white54, fontSize: 10),
        ),
        Text(
          value,
          style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
        ),
      ],
    );
  }

  Widget _avatarFallback() {
    return Container(
      color: AppTheme.navyDark,
      child: Center(
        child: Text(
          widget.user.fullName.isNotEmpty ? widget.user.fullName[0].toUpperCase() : 'U',
          style: const TextStyle(color: AppTheme.gold, fontSize: 24, fontWeight: FontWeight.bold),
        ),
      ),
    );
  }
}
