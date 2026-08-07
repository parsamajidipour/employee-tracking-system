import 'package:permission_handler/permission_handler.dart';

import '../models/permission_snapshot.dart';

/// Everything permission-related goes through here, including location —
/// not split between geolocator's own (more limited) permission calls and
/// permission_handler. geolocator has no concept of notification permission
/// or battery-optimization exemption at all, so permission_handler is
/// required regardless; routing location through it too avoids two
/// competing permission-request code paths.
///
/// Fine location and background location must be requested as two
/// *separate* calls — Android rejects (or silently no-ops) a request for
/// background location before foreground location has already been
/// granted. `requestFineLocation()` and `requestBackgroundLocation()` are
/// kept as distinct methods for exactly this reason; nothing calls the
/// second before the first has resolved.
class PermissionService {
  Future<PermissionStatus> requestFineLocation() => Permission.location.request();

  Future<PermissionStatus> requestBackgroundLocation() => Permission.locationAlways.request();

  /// permission_handler reports this as already-granted on pre-API-33
  /// devices with no manual version check needed here.
  Future<PermissionStatus> requestNotifications() => Permission.notification.request();

  Future<PermissionStatus> requestBatteryOptimizationExemption() =>
      Permission.ignoreBatteryOptimizations.request();

  /// Status-only, no prompts — for the onboarding screen's later steps and
  /// the home screen's ongoing "what's missing" display.
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

  /// Once a permission is permanently denied, no in-app dialog can prompt
  /// again — Android requires going to system settings.
  Future<bool> openSettings() => openAppSettings();
}
