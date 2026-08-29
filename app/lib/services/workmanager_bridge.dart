import 'package:workmanager/workmanager.dart';

import 'tracking_service_controller.dart';
import 'window_sync_service.dart';

const _windowCheckTaskName = 'window-check';
const _dailyTrackingStartTaskName = 'daily-tracking-start';
const _dailyTrackingStartHour = 8;

@pragma('vm:entry-point')
void callbackDispatcher() {
  Workmanager().executeTask((taskName, inputData) async {
    if (taskName == _dailyTrackingStartTaskName) {
      try {
        final controller = TrackingServiceController()..init();
        if (!await controller.isRunning()) {
          await controller.startService();
        }
      } catch (_) {
      } finally {
        await _scheduleNextDailyTrackingStart();
      }

      return true;
    }

    if (taskName != _windowCheckTaskName) return true;

    try {
      final window = await fetchCurrentWindow();
      await TrackingServiceController().applyWindowDecision(window);
    } catch (_) {}

    return true;
  });
}

Future<void> registerPeriodicWindowCheck() async {
  await Workmanager().initialize(callbackDispatcher);
  await _scheduleNextDailyTrackingStart();
  await Workmanager().registerPeriodicTask(
    _windowCheckTaskName,
    _windowCheckTaskName,
    frequency: const Duration(minutes: 15),
  );
}

Future<void> _scheduleNextDailyTrackingStart() async {
  final now = DateTime.now();
  var nextStart = DateTime(
    now.year,
    now.month,
    now.day,
    _dailyTrackingStartHour,
  );

  if (!nextStart.isAfter(now)) {
    nextStart = nextStart.add(const Duration(days: 1));
  }

  await Workmanager().registerOneOffTask(
    _dailyTrackingStartTaskName,
    _dailyTrackingStartTaskName,
    existingWorkPolicy: ExistingWorkPolicy.replace,
    initialDelay: nextStart.difference(now),
    constraints: Constraints(networkType: NetworkType.not_required),
  );
}
