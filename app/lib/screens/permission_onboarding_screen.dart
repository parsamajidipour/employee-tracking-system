import 'package:flutter/material.dart';

import '../services/permission_service.dart';

/// One permission per step, explanation always shown before the request
/// button is live. "Continue" advances regardless of grant/deny outcome —
/// there is deliberately no forced retry loop here. A denied permission is
/// the home screen's problem to surface with a way to fix it later, not
/// this screen's to block on; this screen only ever runs once per install.
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
  static const _steps = _Step.values;

  int _stepIndex = 0;
  bool _requesting = false;

  _Step get _currentStep => _steps[_stepIndex];

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
          Icons.my_location,
          'Location',
          "This app records your location during working hours only, so your "
              'supervisor can see field employees on the live map. Outside working '
              'hours, nothing is recorded.',
          'Allow location',
        ),
      _Step.backgroundLocation => (
          Icons.location_on,
          'Location in the background',
          'Tracking has to keep working while the app is closed or the screen is '
              'off — the previous permission only covers while this screen is open. '
              'Android will ask again; choose "Allow all the time".',
          'Allow all the time',
        ),
      _Step.notifications => (
          Icons.notifications_active,
          'Notifications',
          'While tracking is active, a persistent notification stays visible so '
              "it's always obvious tracking is on — and just as obvious when it's "
              'off.',
          'Allow notifications',
        ),
      _Step.batteryOptimization => (
          Icons.battery_charging_full,
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
    final (icon, title, explanation, buttonLabel) = _stepContent(_currentStep);

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 360),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Step ${_stepIndex + 1} of ${_steps.length}',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.grey.shade600),
                  ),
                  const SizedBox(height: 16),
                  Icon(icon, size: 56),
                  const SizedBox(height: 16),
                  Text(
                    title,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 12),
                  Text(explanation, textAlign: TextAlign.center),
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: _requesting ? null : _requestCurrent,
                    child: _requesting
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : Text(buttonLabel),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
