import 'package:flutter/material.dart';

import '../services/permission_service.dart';
import '../theme/app_theme.dart';
import '../widgets/app_card.dart';
import '../widgets/fade_slide_in.dart';

class PermissionOnboardingScreen extends StatefulWidget {
  final PermissionService permissionService;
  final VoidCallback onComplete;

  const PermissionOnboardingScreen({
    super.key,
    required this.permissionService,
    required this.onComplete,
  });

  @override
  State<PermissionOnboardingScreen> createState() => _PermissionOnboardingScreenState();
}

enum _Step { fineLocation, backgroundLocation, notifications, batteryOptimization }

class _PermissionOnboardingScreenState extends State<PermissionOnboardingScreen> {
  List<_Step> _steps = const [];
  int _stepIndex = 0;
  bool _requesting = false;
  bool _checking = true;

  _Step get _currentStep => _steps[_stepIndex];

  @override
  void initState() {
    super.initState();
    _loadOutstandingSteps();
  }

  Future<void> _loadOutstandingSteps() async {
    final snapshot = await widget.permissionService.currentSnapshot();
    final outstanding = [
      if (!snapshot.fineLocationGranted) _Step.fineLocation,
      if (!snapshot.backgroundLocationGranted) _Step.backgroundLocation,
      if (!snapshot.notificationsGranted) _Step.notifications,
      if (!snapshot.batteryOptimizationExempt) _Step.batteryOptimization,
    ];

    if (!mounted) return;

    if (outstanding.isEmpty) {
      widget.onComplete();
      return;
    }

    setState(() {
      _steps = outstanding;
      _checking = false;
    });
  }

  Future<void> _requestCurrent() async {
    setState(() => _requesting = true);

    switch (_currentStep) {
      case _Step.fineLocation:
        await widget.permissionService.requestFineLocation();
      case _Step.backgroundLocation:
        await widget.permissionService.requestBackgroundLocation();
      case _Step.notifications:
        await widget.permissionService.requestNotifications();
      case _Step.batteryOptimization:
        await widget.permissionService.requestBatteryOptimizationExemption();
    }

    if (!mounted) return;
    _advance();
  }

  void _advance() {
    if (_stepIndex + 1 >= _steps.length) {
      widget.onComplete();
      return;
    }
    setState(() {
      _stepIndex++;
      _requesting = false;
    });
  }

  (IconData, String, String, String) _stepContent(_Step step) {
    return switch (step) {
      _Step.fineLocation => (
          Icons.my_location_outlined,
          'Location',
          "This app records your location during working hours only, so your "
              'supervisor can see field employees on the live map. Outside working '
              'hours, nothing is recorded.',
          'Allow location',
        ),
      _Step.backgroundLocation => (
          Icons.location_on_outlined,
          'Location in the background',
          'Tracking has to keep working while the app is closed or the screen is '
              'off — the previous permission only covers while this screen is open. '
              'Android will ask again; choose "Allow all the time".',
          'Allow all the time',
        ),
      _Step.notifications => (
          Icons.notifications_active_outlined,
          'Notifications',
          'While tracking is active, a persistent notification stays visible so '
              "it's always obvious tracking is on — and just as obvious when it's "
              'off.',
          'Allow notifications',
        ),
      _Step.batteryOptimization => (
          Icons.battery_charging_full_outlined,
          'Battery optimisation',
          'Android can pause background work to save battery, which would stop '
              'tracking mid-shift without warning. Exempting this app keeps '
              'tracking reliable for the whole working-hours window.',
          'Exempt from battery optimisation',
        ),
    };
  }

  @override
  Widget build(BuildContext context) {
    if (_checking) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator(strokeWidth: 2.4)),
      );
    }

    final (icon, title, explanation, buttonLabel) = _stepContent(_currentStep);
    final colors = context.colors;

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.screen,
                AppSpacing.xl,
                AppSpacing.screen,
                AppSpacing.lg,
              ),
              child: Row(
                children: [
                  for (var i = 0; i < _steps.length; i++) ...[
                    Expanded(
                      child: AnimatedContainer(
                        duration: context.motion(AppDurations.base),
                        curve: Curves.easeOutCubic,
                        height: 6,
                        decoration: BoxDecoration(
                          color: i <= _stepIndex
                              ? colors.primary
                              : colors.border,
                          borderRadius: AppRadii.pillRadius,
                        ),
                      ),
                    ),
                    if (i != _steps.length - 1)
                      const SizedBox(width: AppSpacing.sm),
                  ],
                ],
              ),
            ),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.screen,
                ),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 420),
                    child: FadeSlideIn(
                      key: ValueKey(_stepIndex),
                      child: AppCard(
                        padding: const EdgeInsets.all(AppSpacing.xxl),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'STEP ${_stepIndex + 1} OF ${_steps.length}',
                              style: context.text.labelSmall?.copyWith(
                                color: colors.primaryStrong,
                              ),
                            ),
                            const SizedBox(height: AppSpacing.lg),
                            IconTile(icon: icon, size: 64),
                            const SizedBox(height: AppSpacing.lg),
                            Text(title, style: context.text.titleLarge),
                            const SizedBox(height: AppSpacing.md),
                            Text(explanation, style: context.text.bodyLarge),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.screen,
                AppSpacing.lg,
                AppSpacing.screen,
                AppSpacing.xxl,
              ),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    FilledButton(
                      onPressed: _requesting ? null : _requestCurrent,
                      child: _requesting
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.4,
                                color: Colors.white,
                              ),
                            )
                          : Text(buttonLabel),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    TextButton(
                      onPressed: _requesting ? null : _advance,
                      child: const Text('Not now'),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
