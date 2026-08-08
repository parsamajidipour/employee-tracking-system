

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
