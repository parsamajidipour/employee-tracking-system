import 'dart:convert';

import 'package:flutter_foreground_task/flutter_foreground_task.dart';

import '../models/shift_window.dart';
import 'foreground_task_handler.dart';

class TrackingServiceController {
  static const _channelId = 'tracking_channel';

  bool _busy = false;

  void init() {
    FlutterForegroundTask.init(
      androidNotificationOptions: AndroidNotificationOptions(
        channelId: _channelId,
        channelName: 'Location tracking',
        channelDescription: 'Shows when location tracking is active during working hours.',
        channelImportance: NotificationChannelImportance.LOW,
        priority: NotificationPriority.LOW,
      ),
      iosNotificationOptions: const IOSNotificationOptions(),
      foregroundTaskOptions: ForegroundTaskOptions(

        eventAction: ForegroundTaskEventAction.repeat(5000),
        autoRunOnBoot: false,
        allowWakeLock: true,
      ),
    );
  }

  Future<bool> isRunning() => FlutterForegroundTask.isRunningService;

  Future<void> startService() async {
    await FlutterForegroundTask.startService(
      notificationTitle: 'Smart Inspection',
      notificationText: 'Tracking active',
      serviceTypes: const [ForegroundServiceTypes.location],
      callback: startCallback,
    );
  }

  Future<void> stopService() async {
    await FlutterForegroundTask.stopService();
  }

  Future<void> applyWindowDecision(ShiftWindow? current) async {
    if (_busy) return;
    _busy = true;
    try {
      if (await isRunning()) {
        FlutterForegroundTask.sendDataToTask(
          current == null ? noWindowMarker : jsonEncode(current.toJson()),
        );
        return;
      }

      if (current != null) {
        await startService();
      }
    } finally {
      _busy = false;
    }
  }
}
