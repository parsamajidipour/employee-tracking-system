import 'package:flutter/material.dart';

import '../l10n/l10n.dart';
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
  State<PermissionOnboardingScreen> createState() =>
      _PermissionOnboardingScreenState();
}

enum _Step {
  fineLocation,
  backgroundLocation,
  notifications,
  batteryOptimization
}

class _PermissionOnboardingScreenState
    extends State<PermissionOnboardingScreen> {
  List<_Step> _steps = const [];
  int _stepIndex = 0;
  bool _requesting = false;
  bool _checking = true;
  String? _permissionError;
  bool _openSettingsRequired = false;

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
      _stepIndex = 0;
      _checking = false;
      _requesting = false;
      _permissionError = null;
      _openSettingsRequired = false;
    });
  }

  Future<void> _requestCurrent() async {
    setState(() {
      _requesting = true;
      _permissionError = null;
      _openSettingsRequired = false;
    });

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
    final snapshot = await widget.permissionService.currentSnapshot();
    if (!mounted) return;

    final granted = switch (_currentStep) {
      _Step.fineLocation => snapshot.fineLocationGranted,
      _Step.backgroundLocation => snapshot.backgroundLocationGranted,
      _Step.notifications => snapshot.notificationsGranted,
      _Step.batteryOptimization => snapshot.batteryOptimizationExempt,
    };

    if (granted) {
      _advance();
      return;
    }

    setState(() {
      _requesting = false;
      _permissionError = switch (_currentStep) {
        _Step.fineLocation => context.l10n.locationPermissionError,
        _Step.backgroundLocation => context.l10n.backgroundPermissionError,
        _Step.notifications => context.l10n.notificationPermissionError,
        _Step.batteryOptimization => context.l10n.batteryPermissionError,
      };
      _openSettingsRequired = true;
    });
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
    final l10n = context.l10n;
    return switch (step) {
      _Step.fineLocation => (
          Icons.my_location_outlined,
          l10n.location,
          l10n.locationPermissionExplanation,
          l10n.allowLocation,
        ),
      _Step.backgroundLocation => (
          Icons.location_on_outlined,
          l10n.backgroundLocation,
          l10n.backgroundLocationExplanation,
          l10n.allowAllTheTime,
        ),
      _Step.notifications => (
          Icons.notifications_active_outlined,
          l10n.notifications,
          l10n.notificationsExplanation,
          l10n.allowNotifications,
        ),
      _Step.batteryOptimization => (
          Icons.battery_charging_full_outlined,
          l10n.batteryOptimisation,
          l10n.batteryExplanation,
          l10n.exemptBattery,
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
                          color:
                              i <= _stepIndex ? colors.primary : colors.border,
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
                              context.l10n
                                  .stepProgress(_stepIndex + 1, _steps.length),
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
                            if (_permissionError != null) ...[
                              const SizedBox(height: AppSpacing.lg),
                              Container(
                                width: double.infinity,
                                padding: const EdgeInsets.all(AppSpacing.md),
                                decoration: BoxDecoration(
                                  color: colors.danger.withValues(alpha: 0.1),
                                  borderRadius: AppRadii.smallRadius,
                                ),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Icon(Icons.error_outline,
                                        size: 20, color: colors.danger),
                                    const SizedBox(width: AppSpacing.sm),
                                    Expanded(
                                      child: Text(
                                        _permissionError!,
                                        style: context.text.bodySmall
                                            ?.copyWith(color: colors.danger),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
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
                    if (_openSettingsRequired) ...[
                      const SizedBox(height: AppSpacing.sm),
                      OutlinedButton.icon(
                        onPressed: _requesting
                            ? null
                            : () async {
                                await widget.permissionService.openSettings();
                                await _loadOutstandingSteps();
                              },
                        icon: const Icon(Icons.settings_outlined),
                        label: Text(context.l10n.openAppSettings),
                      ),
                    ],
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
