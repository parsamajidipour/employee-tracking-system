import 'package:flutter/material.dart';
import 'package:flutter_foreground_task/flutter_foreground_task.dart';

import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'screens/permission_onboarding_screen.dart';
import 'services/permission_service.dart';
import 'services/tracking_service_controller.dart';
import 'services/workmanager_bridge.dart';
import 'state/auth_controller.dart';

void main() {
  // Initializes the platform channel port TrackingTaskHandler and the main
  // isolate use to pass window updates and the 401 signal back and forth —
  // must happen before runApp(), same as the plugin's own example.
  FlutterForegroundTask.initCommunicationPort();
  runApp(const SmartInspectionApp());
}

class SmartInspectionApp extends StatefulWidget {
  const SmartInspectionApp({super.key});

  @override
  State<SmartInspectionApp> createState() => _SmartInspectionAppState();
}

class _SmartInspectionAppState extends State<SmartInspectionApp> {
  final _authController = AuthController();
  final _trackingServiceController = TrackingServiceController();

  @override
  void initState() {
    super.initState();
    _authController.initialize();
    _trackingServiceController.init();
    // Only a starting aid for a fully-killed app — see workmanager_bridge.dart's
    // docblock. Registered unconditionally at startup: it's a no-op query
    // against /me/window if there's no session yet, same as every other
    // resync trigger in this app.
    registerPeriodicWindowCheck();
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
          return HomeScreen(
            authController: _authController,
            trackingServiceController: _trackingServiceController,
          );
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
