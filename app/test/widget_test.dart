import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:permission_handler/permission_handler.dart';

import 'package:app/l10n/app_localizations.dart';
import 'package:app/models/permission_snapshot.dart';
import 'package:app/screens/login_screen.dart';
import 'package:app/screens/permission_onboarding_screen.dart';
import 'package:app/services/permission_service.dart';
import 'package:app/state/auth_controller.dart';
import 'package:app/state/locale_controller.dart';
import 'package:app/theme/app_theme.dart';

class _FakePermissionService extends PermissionService {
  bool fine = false;
  bool background = false;
  bool notifications = false;
  bool battery = false;

  @override
  Future<PermissionSnapshot> currentSnapshot() async => PermissionSnapshot(
        fineLocationGranted: fine,
        backgroundLocationGranted: background,
        notificationsGranted: notifications,
        batteryOptimizationExempt: battery,
      );

  @override
  Future<PermissionStatus> requestFineLocation() async {
    fine = true;
    return PermissionStatus.granted;
  }

  @override
  Future<PermissionStatus> requestBackgroundLocation() async {
    background = true;
    return PermissionStatus.granted;
  }

  @override
  Future<PermissionStatus> requestNotifications() async {
    notifications = true;
    return PermissionStatus.granted;
  }

  @override
  Future<PermissionStatus> requestBatteryOptimizationExemption() async {
    battery = true;
    return PermissionStatus.granted;
  }
}

void main() {
  testWidgets('locale persists and switches direction without a restart',
      (tester) async {
    FlutterSecureStorage.setMockInitialValues({});
    final controller = LocaleController();
    await controller.initialize();
    expect(controller.locale, const Locale('en'));

    await controller.setLocale(const Locale('ar'));
    final restored = LocaleController();
    await restored.initialize();
    expect(restored.locale, const Locale('ar'));

    await tester.pumpWidget(MaterialApp(
      locale: restored.locale,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      home: Builder(
        builder: (context) => Text(
          Directionality.of(context).name,
          textDirection: Directionality.of(context),
        ),
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('rtl'), findsOneWidget);

    await restored.setLocale(const Locale('en'));
    await tester.pumpWidget(MaterialApp(
      locale: restored.locale,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      home: Builder(
        builder: (context) => Text(
          Directionality.of(context).name,
          textDirection: Directionality.of(context),
        ),
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('ltr'), findsOneWidget);
  });

  testWidgets('login screen shows identifier and password fields',
      (tester) async {
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
    expect(find.byIcon(Icons.copy_outlined), findsNothing);
  });

  testWidgets(
      'permission onboarding walks through all four steps then completes',
      (tester) async {
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

  testWidgets(
      'permission onboarding skips straight through when everything is already granted',
      (tester) async {
    var completed = false;

    await tester.pumpWidget(MaterialApp(
      theme: AppTheme.light(),
      home: PermissionOnboardingScreen(
        permissionService: _AllGrantedPermissionService(),
        onComplete: () => completed = true,
      ),
    ));
    // Not pumpAndSettle: onComplete fires while this screen is still showing
    // its (indefinite) loading spinner in isolation — in the real app the
    // parent swaps this screen out immediately once onComplete fires.
    await tester.pump();
    await tester.pump();

    expect(completed, isTrue);
    expect(find.text('STEP 1 OF 4'), findsNothing);
  });
}

class _AllGrantedPermissionService extends PermissionService {
  @override
  Future<PermissionSnapshot> currentSnapshot() async => PermissionSnapshot(
        fineLocationGranted: true,
        backgroundLocationGranted: true,
        notificationsGranted: true,
        batteryOptimizationExempt: true,
      );
}
