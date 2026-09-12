import 'dart:convert';

import 'package:flutter_foreground_task/flutter_foreground_task.dart';

import '../models/shift_window.dart';
import '../l10n/stored_localizations.dart';
import 'auth_storage.dart';
import 'foreground_task_handler.dart';

class TrackingServiceController {
  static const _channelId = 'tracking_channel';

  bool _busy = false;

  Future<void> init() async {
    final l10n = await storedLocalizations(AuthStorage());
    FlutterForegroundTask.init(
      androidNotificationOptions: AndroidNotificationOptions(
        channelId: _channelId,
        channelName: l10n.trackingChannel,
        channelDescription: l10n.trackingChannelDescription,
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
    final l10n = await storedLocalizations(AuthStorage());
    await FlutterForegroundTask.startService(
      notificationTitle: l10n.appName,
      notificationText: l10n.trackingActive,
      serviceTypes: const [ForegroundServiceTypes.location],
      callback: startCallback,
    );
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
