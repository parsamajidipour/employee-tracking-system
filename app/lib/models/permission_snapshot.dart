/// A point-in-time read of every permission background tracking depends on.
/// Plain data, no logic — matches shift_window.dart's style. Produced by
/// PermissionService.currentSnapshot(), consumed by the onboarding screen
/// and (later) the home screen's permission-status section.
class PermissionSnapshot {
  final bool fineLocationGranted;
  final bool backgroundLocationGranted;
  final bool notificationsGranted;
  final bool batteryOptimizationExempt;

  PermissionSnapshot({
    required this.fineLocationGranted,
    required this.backgroundLocationGranted,
    required this.notificationsGranted,
    required this.batteryOptimizationExempt,
  });

  bool get allGranted =>
      fineLocationGranted &&
      backgroundLocationGranted &&
      notificationsGranted &&
      batteryOptimizationExempt;
}
