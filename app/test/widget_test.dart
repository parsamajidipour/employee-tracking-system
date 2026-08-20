import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:permission_handler/permission_handler.dart';

import 'package:app/screens/login_screen.dart';
import 'package:app/screens/permission_onboarding_screen.dart';
import 'package:app/services/permission_service.dart';
import 'package:app/state/auth_controller.dart';
import 'package:app/theme/app_theme.dart';

class _FakePermissionService extends PermissionService {
  @override
  Future<PermissionStatus> requestFineLocation() async => PermissionStatus.granted;

  @override
  Future<PermissionStatus> requestBackgroundLocation() async => PermissionStatus.granted;

  @override
  Future<PermissionStatus> requestNotifications() async => PermissionStatus.granted;

  @override
  Future<PermissionStatus> requestBatteryOptimizationExemption() async => PermissionStatus.granted;
}

void main() {
  testWidgets('login screen shows identifier and password fields', (tester) async {
    final authController = AuthController();

    await tester.pumpWidget(MaterialApp(
      theme: AppTheme.light(),
      home: LoginScreen(authController: authController),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Email or phone number'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
    expect(find.text('Welcome back'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Sign in'), findsOneWidget);
  });

  testWidgets('permission onboarding walks through all four steps then completes', (tester) async {
    var completed = false;

    await tester.pumpWidget(MaterialApp(
      theme: AppTheme.light(),
      home: PermissionOnboardingScreen(
        permissionService: _FakePermissionService(),
        onComplete: () => completed = true,
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('STEP 1 OF 4'), findsOneWidget);
    expect(find.text('Location'), findsOneWidget);

    for (final expectedStep in ['STEP 2 OF 4', 'STEP 3 OF 4', 'STEP 4 OF 4']) {
      await tester.tap(find.byType(FilledButton));
      await tester.pumpAndSettle();
      expect(find.text(expectedStep), findsOneWidget);
    }

    expect(completed, isFalse);
    await tester.tap(find.byType(FilledButton));

    await tester.pump();
    expect(completed, isTrue);
  });
}
