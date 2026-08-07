import 'package:flutter/material.dart';

import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'screens/permission_onboarding_screen.dart';
import 'services/permission_service.dart';
import 'state/auth_controller.dart';

void main() {
  runApp(const SmartInspectionApp());
}

class SmartInspectionApp extends StatefulWidget {
  const SmartInspectionApp({super.key});

  @override
  State<SmartInspectionApp> createState() => _SmartInspectionAppState();
}

class _SmartInspectionAppState extends State<SmartInspectionApp> {
  final _authController = AuthController();

  @override
  void initState() {
    super.initState();
    _authController.initialize();
  }

  @override
  void dispose() {
    _authController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Smart Inspection',
      theme: ThemeData(useMaterial3: true, colorSchemeSeed: Colors.blue),
      home: ListenableBuilder(
        listenable: _authController,
        builder: (context, _) {
          if (_authController.status == AuthStatus.loading) {
            return const _SplashScreen();
          }
          if (_authController.status == AuthStatus.signedOut) {
            return LoginScreen(authController: _authController);
          }
          if (!_authController.onboardingCompleted) {
            return PermissionOnboardingScreen(
              permissionService: PermissionService(),
              onComplete: _authController.completeOnboarding,
            );
          }
          return HomeScreen(authController: _authController);
        },
      ),
    );
  }
}

class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(body: Center(child: CircularProgressIndicator()));
  }
}
