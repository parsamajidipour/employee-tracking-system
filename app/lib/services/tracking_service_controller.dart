import 'dart:convert';

import 'package:flutter_foreground_task/flutter_foreground_task.dart';

import '../models/shift_window.dart';
import 'foreground_task_handler.dart';

/// The main-isolate-facing half of the foreground service. Starting is
/// this side's job — the running service (TrackingTaskHandler) takes over
/// deciding when to stop the moment it exists, since only something
/// already running can reliably act on a schedule with no external wake-up
/// needed.
class TrackingServiceController {
  static const _channelId = 'tracking_channel';

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
        // 30s: established here for step 4's upload cycle to reuse, not a
        // second timer. The window self-check that actually matters for
        // correctness is the *scheduled stop Timer* inside
        // TrackingTaskHandler, not this tick's frequency — this interval
        // only governs how often schedule *changes* get noticed.
        eventAction: ForegroundTaskEventAction.repeat(30000),
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

  /// Called with an already-fetched window (never fetches itself — see
  /// window_sync_service.dart's docblock on why the app-foreground trigger
  /// shouldn't cost a second request for the same information).
  ///
  /// If the service isn't running, this is the *only* place tracking gets
  /// started. If it is running, the fresh window is forwarded into it so
  /// the running service's own stop schedule updates immediately, rather
  /// than waiting up to its own next periodic tick.
  Future<void> applyWindowDecision(ShiftWindow? current) async {
    if (await isRunning()) {
      FlutterForegroundTask.sendDataToTask(
        current == null ? noWindowMarker : jsonEncode(current.toJson()),
      );
      return;
    }

    if (current != null) {
      await startService();
    }
  }
}
