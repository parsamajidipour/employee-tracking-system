import 'package:workmanager/workmanager.dart';

import 'tracking_service_controller.dart';
import 'window_sync_service.dart';

const _windowCheckTaskName = 'window-check';

/// Only a *starting* aid, for when the app process was fully killed and
/// nothing else could notice a window had opened — flutter_foreground_task
/// alone can't wake anything from nothing. Stopping is unaffected by any
/// of this: a running service always schedules and fires its own exact
/// stop (see TrackingTaskHandler), which needs no external wake-up at all.
/// Android enforces a ~15-minute floor on periodic tasks regardless of
/// what's requested here.
@pragma('vm:entry-point')
void callbackDispatcher() {
  Workmanager().executeTask((taskName, inputData) async {
    if (taskName != _windowCheckTaskName) return true;

    try {
      final window = await fetchCurrentWindow();
      await TrackingServiceController().applyWindowDecision(window);
    } catch (_) {
      // Nothing to act on if the fetch itself failed — try again next tick.
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
