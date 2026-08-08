import 'package:workmanager/workmanager.dart';

import 'tracking_service_controller.dart';
import 'window_sync_service.dart';

const _windowCheckTaskName = 'window-check';

@pragma('vm:entry-point')
void callbackDispatcher() {
  Workmanager().executeTask((taskName, inputData) async {
    if (taskName != _windowCheckTaskName) return true;

    try {
      final window = await fetchCurrentWindow();
      await TrackingServiceController().applyWindowDecision(window);
    } catch (_) {
    }

    return true;
  });
}

Future<void> registerPeriodicWindowCheck() async {
  await Workmanager().initialize(callbackDispatcher);
  await Workmanager().registerPeriodicTask(
    _windowCheckTaskName,
    _windowCheckTaskName,
    frequency: const Duration(minutes: 15),
  );
}
