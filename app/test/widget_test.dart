import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:permission_handler/permission_handler.dart';

import 'package:app/screens/login_screen.dart';
import 'package:app/screens/permission_onboarding_screen.dart';
import 'package:app/services/permission_service.dart';
import 'package:app/state/auth_controller.dart';

/// Overrides every request with a canned grant — never touches a real
/// platform channel, which plain `PermissionService()` would.
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
  testWidgets('login screen shows username and password fields', (tester) async {
    // AuthController() alone doesn't touch storage or the network —
    // LoginScreen's initState only reads/clears the plain revokedMessage
    // field — so this needs no plugin mocking.
    final authController = AuthController();

    await tester.pumpWidget(MaterialApp(home: LoginScreen(authController: authController)));

    expect(find.text('Username'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
  });

  testWidgets('permission onboarding walks through all four steps then completes', (tester) async {
    var completed = false;

    await tester.pumpWidget(MaterialApp(
      home: PermissionOnboardingScreen(
        permissionService: _FakePermissionService(),
        onComplete: () => completed = true,
      ),
    ));

    expect(find.text('Step 1 of 4'), findsOneWidget);
    expect(find.text('Location'), findsOneWidget);

    for (final expectedStep in ['Step 2 of 4', 'Step 3 of 4', 'Step 4 of 4']) {
      await tester.tap(find.byType(FilledButton));
      await tester.pumpAndSettle();
      expect(find.text(expectedStep), findsOneWidget);
    }

    expect(completed, isFalse);
    await tester.tap(find.byType(FilledButton));
    // pump(), not pumpAndSettle(): onComplete() fires with no screen swap in
    // this test (a real app would navigate away), so the in-flight request's
    // spinner is still animating and would never let pumpAndSettle finish.
    await tester.pump();
    expect(completed, isTrue);
  });
}
