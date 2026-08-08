import 'package:permission_handler/permission_handler.dart';

import '../models/permission_snapshot.dart';

class PermissionService {
  Future<PermissionStatus> requestFineLocation() => Permission.location.request();

  Future<PermissionStatus> requestBackgroundLocation() => Permission.locationAlways.request();

  Future<PermissionStatus> requestNotifications() => Permission.notification.request();

  Future<PermissionStatus> requestBatteryOptimizationExemption() =>
      Permission.ignoreBatteryOptimizations.request();

  Future<PermissionSnapshot> currentSnapshot() async {
    final fineLocation = await Permission.location.status;
    final backgroundLocation = await Permission.locationAlways.status;
    final notifications = await Permission.notification.status;
    final batteryOptimization = await Permission.ignoreBatteryOptimizations.status;

    return PermissionSnapshot(
      fineLocationGranted: fineLocation.isGranted,
      backgroundLocationGranted: backgroundLocation.isGranted,
      notificationsGranted: notifications.isGranted,
      batteryOptimizationExempt: batteryOptimization.isGranted,
    );
  }

  Future<bool> openSettings() => openAppSettings();
}
