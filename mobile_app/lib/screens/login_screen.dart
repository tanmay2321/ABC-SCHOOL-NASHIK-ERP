import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../services/api_service.dart';
import 'student/student_nav_screen.dart';
import 'teacher/teacher_nav_screen.dart';
import 'admin/admin_nav_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _loginController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;
  bool _isLoading = false;
  String? _errorMessage;
  bool? _isServerConnected;

  @override
  void initState() {
    super.initState();
    _checkServer();
  }

  Future<void> _checkServer() async {
    final ok = await ApiService().pingServer();
    if (mounted) {
      setState(() {
        _isServerConnected = ok;
      });
    }
  }

  @override
  void dispose() {
    _loginController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final res = await ApiService().login(
      _loginController.text.trim(),
      _passwordController.text,
    );

    if (!mounted) return;
    setState(() {
      _isLoading = false;
    });

    if (res['success'] == true) {
      final user = ApiService().currentUser!;
      Widget targetScreen;
      if (user.role == 'student') {
        targetScreen = const StudentNavScreen();
      } else if (user.role == 'teacher') {
        targetScreen = const TeacherNavScreen();
      } else {
        targetScreen = const AdminNavScreen();
      }

      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => targetScreen),
      );
    } else {
      setState(() {
        _errorMessage = res['error'] ?? 'Login failed. Please check credentials.';
      });
    }
  }

  void _showServerSettings() {
    final serverController = TextEditingController(text: ApiService().baseUrl);
    bool testing = false;
    String? testResult;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) => Padding(
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
                  const Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Live ERP Server Connection',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                      Text(
                        'Syncs simultaneously with website database',
                        style: TextStyle(fontSize: 11, color: AppTheme.slate600),
                      ),
                    ],
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(ctx),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              TextField(
                controller: serverController,
                decoration: InputDecoration(
                  labelText: 'API Endpoint URL',
                  hintText: 'http://192.168.31.187:8000/api',
                  prefixIcon: const Icon(Icons.dns_rounded),
                  suffixIcon: IconButton(
                    icon: testing
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.refresh_rounded),
                    tooltip: 'Test Connection',
                    onPressed: () async {
                      setSheetState(() {
                        testing = true;
                        testResult = null;
                      });
                      final ok = await ApiService().pingServer(serverController.text.trim());
                      setSheetState(() {
                        testing = false;
                        testResult = ok
                            ? 'Server is Online & Responding!'
                            : 'Could not connect. Verify PHP server is running on this IP.';
                      });
                    },
                  ),
                ),
              ),
              if (testResult != null) ...[
                const SizedBox(height: 8),
                Text(
                  testResult!,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: testResult!.contains('Online') ? AppTheme.emerald : AppTheme.rose,
                  ),
                ),
              ],
              const SizedBox(height: 14),
              const Text('Quick Presets for Testing:', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.slate600)),
              const SizedBox(height: 6),
              Wrap(
                spacing: 8,
                runSpacing: 6,
                children: [
                  ActionChip(
                    avatar: const Icon(Icons.wifi, size: 14, color: AppTheme.navyMedium),
                    label: const Text('Phone Wi-Fi (192.168.31.187)', style: TextStyle(fontSize: 11)),
                    onPressed: () => serverController.text = 'http://192.168.31.187:8000/api',
                  ),
                  ActionChip(
                    avatar: const Icon(Icons.laptop, size: 14, color: AppTheme.navyMedium),
                    label: const Text('Localhost (Chrome / PC)', style: TextStyle(fontSize: 11)),
                    onPressed: () => serverController.text = 'http://localhost:8000/api',
                  ),
                  ActionChip(
                    avatar: const Icon(Icons.phone_android, size: 14, color: AppTheme.navyMedium),
                    label: const Text('Android Emulator (10.0.2.2)', style: TextStyle(fontSize: 11)),
                    onPressed: () => serverController.text = 'http://10.0.2.2:8000/api',
                  ),
                ],
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.navyDark,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: () async {
                    await ApiService().setBaseUrl(serverController.text.trim());
                    await _checkServer();
                    if (ctx.mounted) Navigator.pop(ctx);
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text('ERP Server updated to: ${ApiService().baseUrl}')),
                      );
                    }
                  },
                  child: const Text('Save & Connect', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
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
      backgroundColor: AppTheme.navyDark,
      body: Stack(
        children: [
          // Background Gradient Spheres
          Positioned(
            top: -100,
            right: -100,
            child: Container(
              width: 320,
              height: 320,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [
                    AppTheme.gold.withValues(alpha: 0.18),
                    Colors.transparent,
                  ],
                ),
              ),
            ),
          ),
          Positioned(
            bottom: -80,
            left: -80,
            child: Container(
              width: 280,
              height: 280,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [
                    AppTheme.indigo.withValues(alpha: 0.2),
                    Colors.transparent,
                  ],
                ),
              ),
            ),
          ),

          SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    // Server Connection Indicator Pill
                    InkWell(
                      onTap: _showServerSettings,
                      borderRadius: BorderRadius.circular(20),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.08),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(
                            color: _isServerConnected == true
                                ? AppTheme.emerald.withValues(alpha: 0.6)
                                : (_isServerConnected == false
                                    ? AppTheme.rose.withValues(alpha: 0.6)
                                    : Colors.white24),
                          ),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 8,
                              height: 8,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: _isServerConnected == true
                                    ? AppTheme.emerald
                                    : (_isServerConnected == false ? AppTheme.rose : Colors.amber),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Text(
                              _isServerConnected == true
                                  ? 'Connected to Live ERP Server'
                                  : (_isServerConnected == false
                                      ? 'Server Disconnected • Tap to configure'
                                      : 'Connecting to Server...'),
                              style: TextStyle(
                                color: _isServerConnected == true
                                    ? AppTheme.emeraldLight
                                    : (_isServerConnected == false ? AppTheme.roseLight : Colors.white70),
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            const SizedBox(width: 6),
                            const Icon(Icons.settings, color: Colors.white60, size: 14),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),

                    // School Crest & Title
                    Container(
                      width: 80,
                      height: 80,
                      decoration: BoxDecoration(
                        color: Colors.transparent,
                        borderRadius: BorderRadius.circular(22),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.3),
                            blurRadius: 18,
                            offset: const Offset(0, 8),
                          ),
                        ],
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(22),
                        child: Image.asset(
                          'assets/images/logo.png',
                          fit: BoxFit.cover,
                          errorBuilder: (context, error, stackTrace) => Container(
                            color: AppTheme.navyDark,
                            child: const Center(
                              child: Icon(Icons.school_rounded, color: AppTheme.gold, size: 42),
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),
                    const Text(
                      'BELLWETHER INTERNATIONAL SCHOOL',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 1.2,
                      ),
                    ),
                    const Text(
                      'NANDURBAR • MAHARASHTRA',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: AppTheme.goldLight,
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        letterSpacing: 1.8,
                      ),
                    ),
                    const SizedBox(height: 4),
                    const Text(
                      'BWIS ERP • Smart Unified Cloud & Mobile',
                      style: TextStyle(color: Colors.white70, fontSize: 12),
                    ),
                    const SizedBox(height: 24),

                    // Login Card
                    Container(
                      padding: const EdgeInsets.all(26),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(28),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.25),
                            blurRadius: 30,
                            offset: const Offset(0, 12),
                          ),
                        ],
                      ),
                      child: Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Sign In to Your Account',
                              style: TextStyle(
                                color: AppTheme.slate900,
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const Text(
                              'Use your school email/username & password',
                              style: TextStyle(color: AppTheme.slate400, fontSize: 12),
                            ),
                            const SizedBox(height: 20),

                            if (_errorMessage != null) ...[
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: AppTheme.roseLight,
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: AppTheme.rose.withValues(alpha: 0.3)),
                                ),
                                child: Row(
                                  children: [
                                    const Icon(Icons.error_outline, color: AppTheme.rose, size: 18),
                                    const SizedBox(width: 8),
                                    Expanded(
                                      child: Text(
                                        _errorMessage!,
                                        style: const TextStyle(
                                          color: AppTheme.rose,
                                          fontSize: 12,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 16),
                            ],

                            // Username / Email Input
                            TextFormField(
                              controller: _loginController,
                              keyboardType: TextInputType.emailAddress,
                              decoration: const InputDecoration(
                                labelText: 'Email or Username',
                                hintText: 'Enter your ID (e.g. user@edu.in)',
                                prefixIcon: Icon(Icons.person_outline_rounded, color: AppTheme.navyMedium),
                              ),
                              validator: (val) => (val == null || val.trim().isEmpty) ? 'Enter your login ID' : null,
                            ),
                            const SizedBox(height: 16),

                            // Password Input
                            TextFormField(
                              controller: _passwordController,
                              obscureText: _obscurePassword,
                              decoration: InputDecoration(
                                labelText: 'Password',
                                hintText: '••••••••',
                                prefixIcon: const Icon(Icons.lock_outline_rounded, color: AppTheme.navyMedium),
                                suffixIcon: IconButton(
                                  icon: Icon(
                                    _obscurePassword ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                                    color: AppTheme.slate400,
                                  ),
                                  onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                                ),
                              ),
                              validator: (val) => (val == null || val.isEmpty) ? 'Enter your password' : null,
                            ),
                            const SizedBox(height: 24),

                            // Sign In Button
                            SizedBox(
                              width: double.infinity,
                              height: 52,
                              child: ElevatedButton(
                                onPressed: _isLoading ? null : _handleLogin,
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: AppTheme.navyDark,
                                  foregroundColor: Colors.white,
                                  elevation: 3,
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                                ),
                                child: _isLoading
                                    ? const SizedBox(
                                        width: 24,
                                        height: 24,
                                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5),
                                      )
                                    : const Row(
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [
                                          Text('Sign In', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                                          SizedBox(width: 8),
                                          Icon(Icons.arrow_forward_rounded, size: 18),
                                        ],
                                      ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                    const SizedBox(height: 20),

                    // Help & Campus Info
                    const Text(
                      'Bellwether International School • Nandurbar',
                      style: TextStyle(color: Colors.white60, fontSize: 11),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Helpline: +91 6262848446 • bwis.nandurbar@gmail.com',
                      style: TextStyle(color: Colors.white.withValues(alpha: 0.45), fontSize: 10),
                    ),

                    const SizedBox(height: 14),

                    // Server Settings Gear Button
                    TextButton.icon(
                      onPressed: _showServerSettings,
                      icon: const Icon(Icons.settings_ethernet_rounded, color: Colors.white60, size: 16),
                      label: Text(
                        'Server URL: ${ApiService().baseUrl}',
                        style: const TextStyle(color: Colors.white60, fontSize: 11),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
