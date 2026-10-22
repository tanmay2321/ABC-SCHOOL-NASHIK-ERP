import 'package:flutter/material.dart';
import 'theme/app_theme.dart';
import 'services/api_service.dart';
import 'screens/login_screen.dart';
import 'screens/student/student_nav_screen.dart';
import 'screens/teacher/teacher_nav_screen.dart';
import 'screens/admin/admin_nav_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await ApiService().init();
  runApp(const BwisErpApp());
}

class BwisErpApp extends StatelessWidget {
  const BwisErpApp({super.key});

  @override
  Widget build(BuildContext context) {
    Widget homeScreen = const LoginScreen();
    if (ApiService().isLoggedIn) {
      final role = ApiService().currentUser!.role;
      if (role == 'student') {
        homeScreen = const StudentNavScreen();
      } else if (role == 'teacher') {
        homeScreen = const TeacherNavScreen();
      } else {
        homeScreen = const AdminNavScreen();
      }
    }

    return MaterialApp(
      title: 'BWIS Smart ERP',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      home: homeScreen,
    );
  }
}
