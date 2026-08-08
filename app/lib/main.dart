import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_foreground_task/flutter_foreground_task.dart';

import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'screens/permission_onboarding_screen.dart';
import 'services/permission_service.dart';
import 'services/track_upload_service.dart';
import 'services/tracking_service_controller.dart';
import 'services/workmanager_bridge.dart';
import 'state/auth_controller.dart';
import 'theme/app_theme.dart';
import 'widgets/brand_logo.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
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

    registerPeriodicWindowCheck();

    FlutterForegroundTask.addTaskDataCallback(_onTaskData);
  }

  void _onTaskData(Object data) {
    if (data == unauthorizedMarker) {
      _authController.handleUnauthorized();
    }
  }

  @override
  void dispose() {
    FlutterForegroundTask.removeTaskDataCallback(_onTaskData);
    _authController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Smart Inspection',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      themeMode: ThemeMode.system,
      scrollBehavior: const _AppScrollBehavior(),
      builder: (context, child) {
        final media = MediaQuery.of(context);

        return MediaQuery(
          data: media.copyWith(
            textScaler: media.textScaler.clamp(
              minScaleFactor: 0.9,
              maxScaleFactor: 2.0,
            ),
          ),
          child: child ?? const SizedBox.shrink(),
        );
      },
      home: ListenableBuilder(
        listenable: _authController,
        builder: (context, _) {
          return AnimatedSwitcher(
            duration: context.motion(AppDurations.slow),
            switchInCurve: Curves.easeOutCubic,
            child: _screenFor(_authController.status),
          );
        },
      ),
    );
  }

  Widget _screenFor(AuthStatus status) {
    if (status == AuthStatus.loading) {
      return const SplashScreen(key: ValueKey('splash'));
    }

    if (status == AuthStatus.signedOut) {
      return LoginScreen(
        key: const ValueKey('login'),
        authController: _authController,
      );
    }

    if (!_authController.onboardingCompleted) {
      return PermissionOnboardingScreen(
        key: const ValueKey('onboarding'),
        permissionService: PermissionService(),
        onComplete: _authController.completeOnboarding,
      );
    }

    return HomeScreen(
      key: const ValueKey('home'),
      authController: _authController,
      trackingServiceController: _trackingServiceController,
    );
  }
}

class _AppScrollBehavior extends MaterialScrollBehavior {
  const _AppScrollBehavior();

  @override
  ScrollPhysics getScrollPhysics(BuildContext context) {
    return const BouncingScrollPhysics(
      parent: AlwaysScrollableScrollPhysics(),
    );
  }

  @override
  Widget buildOverscrollIndicator(
    BuildContext context,
    Widget child,
    ScrollableDetails details,
  ) {
    return child;
  }
}

class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    SystemChrome.setSystemUIOverlayStyle(
      SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness:
            Theme.of(context).brightness == Brightness.dark
                ? Brightness.light
                : Brightness.dark,
      ),
    );

    return Scaffold(
      backgroundColor: colors.background,
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const BrandWordmark(logoSize: 88),
            const SizedBox(height: AppSpacing.xxxl),
            SizedBox(
              width: 24,
              height: 24,
              child: CircularProgressIndicator(
                strokeWidth: 2.4,
                color: colors.primary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
