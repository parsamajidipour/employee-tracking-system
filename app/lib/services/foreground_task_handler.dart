import 'dart:async';
import 'dart:convert';

import 'package:flutter_foreground_task/flutter_foreground_task.dart';
import 'package:permission_handler/permission_handler.dart';

import '../config.dart';
import '../models/shift_window.dart';
import 'api_client.dart';
import 'app_update_service.dart';
import 'auth_storage.dart';
import 'live_position_ping_service.dart';
import 'local_notification_service.dart';
import 'location_acquisition_service.dart';
import 'notification_repository.dart';
import 'track_upload_service.dart';
import 'window_sync_service.dart';

const noWindowMarker = '__no_window__';

@pragma('vm:entry-point')
void startCallback() {
  FlutterForegroundTask.setTaskHandler(TrackingTaskHandler());
}

class TrackingTaskHandler extends TaskHandler {
  Timer? _stopTimer;
  final TrackUploadService _upload = TrackUploadService();
  final LivePositionPingService _livePosition = LivePositionPingService();
  final AuthStorage _storage = AuthStorage();
  late final AppUpdateService _updateService = AppUpdateService(
    apiClient: ApiClient(
        baseUrl: apiBaseUrl, storage: _storage, onUnauthorized: () async {}),
  );
  late final NotificationRepository _notificationRepository =
      NotificationRepository(
    apiClient: ApiClient(
        baseUrl: apiBaseUrl, storage: _storage, onUnauthorized: () async {}),
  );
  late final LocationAcquisitionService _acquisition =
      LocationAcquisitionService(
    onPointRecorded: _onPointRecorded,
    onPositionPolled: _livePosition.ping,
  );

  bool _hadWindow = false;
  bool _everReconciled = false;
  bool _notificationCheckBusy = false;
  DateTime? _lastNotificationCheck;

  @override
  Future<void> onStart(DateTime timestamp, TaskStarter starter) async {
    _acquisition.start();
    await _reconcileWindow();
    unawaited(_checkForAssignedCaseNotificationsIfBackgrounded());
  }

  @override
  void onRepeatEvent(DateTime timestamp) {
    _checkLocationPermissionStillGranted().then((granted) {
      if (!granted) {
        _stopNow();
        return;
      }
      _reconcileWindow();
    });

    _upload.runUploadCycle();
    unawaited(_checkForAssignedCaseNotificationsIfBackgrounded());
  }

  Future<void> _onPointRecorded() async {
    final uploaded = await _upload.runUploadCycle();
    if (!uploaded) return;

    // Piggyback on a real upload instead of polling separately — keeps
    // this to roughly once per recorded point (~5 minutes), not a heavier
    // schedule of its own.
    await _checkForUpdateIfBackgrounded();
  }

  Future<void> _checkForUpdateIfBackgrounded() async {
    try {
      final info = await _updateService.checkForUpdate();
      if (info == null) return;

      final onForeground = await FlutterForegroundTask.isAppOnForeground;
      if (onForeground) return;

      await LocalNotificationService.show(
        id: 1001,
        title: 'Update available',
        body: 'Version ${info.versionName} is ready to install.',
      );
    } catch (_) {}
  }

  Future<void> _checkForAssignedCaseNotificationsIfBackgrounded() async {
    if (_notificationCheckBusy) return;

    final now = DateTime.now();
    final lastCheck = _lastNotificationCheck;
    if (lastCheck != null &&
        now.difference(lastCheck) < const Duration(seconds: 10)) {
      return;
    }
    _lastNotificationCheck = now;
    _notificationCheckBusy = true;

    try {
      final onForeground = await FlutterForegroundTask.isAppOnForeground;
      if (onForeground) return;

      final notified = await _storage.backgroundNotifiedNotifications();
      final inbox = await _notificationRepository.fetchInbox();
      var changed = false;

      for (final notification in inbox.notifications.reversed) {
        if (notification.read || notification.type != 'case.assigned') continue;
        if (!notified.add(notification.id)) continue;
        changed = true;

        await LocalNotificationService.show(
          id: notification.id.hashCode & 0x7fffffff,
          title: notification.title,
          body: notification.message.isEmpty
              ? 'Open the app for details.'
              : notification.message,
          payload: jsonEncode(notification.toPayload()),
        );
      }

      if (changed) {
        await _storage.saveBackgroundNotifiedNotifications(notified);
      }
    } catch (_) {
    } finally {
      _notificationCheckBusy = false;
    }
  }

  @override
  void onReceiveData(Object data) {
    if (data is! String) return;

    if (data == noWindowMarker) {
      _applyFetchedWindow(null);
      return;
    }

    try {
      final json = jsonDecode(data) as Map<String, dynamic>;
      _applyFetchedWindow(ShiftWindow.fromJson(json));
    } catch (_) {}
  }

  @override
  Future<void> onDestroy(DateTime timestamp, bool isTimeout) async {
    _stopTimer?.cancel();
    _stopTimer = null;
    await _acquisition.stop();
  }

  Future<bool> _checkLocationPermissionStillGranted() async {
    final fineLocation = await Permission.location.status;
    final backgroundLocation = await Permission.locationAlways.status;
    return fineLocation.isGranted && backgroundLocation.isGranted;
  }

  Future<void> _reconcileWindow() async {
    try {
      final window = await fetchCurrentWindow();
      await _applyFetchedWindow(window);
    } catch (_) {}
  }

  Future<void> _applyFetchedWindow(ShiftWindow? window) async {
    await _notifyOnWindowTransition(window);

    if (window == null) {
      _stopNow();
      return;
    }
    _rescheduleStop(window.end);
  }

  Future<void> _notifyOnWindowTransition(ShiftWindow? window) async {
    final hasWindow = window != null;

    // Only the transition matters, and only from the second reconcile
    // onward — the very first check on service start isn't a "change",
    // it's just discovering the current state.
    if (_everReconciled && hasWindow != _hadWindow) {
      final onForeground = await FlutterForegroundTask.isAppOnForeground;
      if (!onForeground) {
        await LocalNotificationService.show(
          id: hasWindow ? 1002 : 1003,
          title: hasWindow ? 'Shift started' : 'Shift ended',
          body: hasWindow
              ? 'You are now inside your working-hours window.'
              : 'Your working-hours window has ended.',
        );
      }
    }

    _hadWindow = hasWindow;
    _everReconciled = true;
  }

  void _rescheduleStop(DateTime newEnd) {
    _stopTimer?.cancel();

    final delay = newEnd.difference(DateTime.now());
    if (!delay.isNegative && delay > Duration.zero) {
      _stopTimer = Timer(delay, _onScheduledEnd);
    } else {
      _onScheduledEnd();
    }
  }

  // The window's own end time can arrive before the next 5s reconcile does
  // — this goes through the same transition-notify path as a reconcile
  // finding a closed window, so "shift ended" still fires reliably rather
  // than only when a reconcile happens to catch it first. Notification is
  // awaited before the service (and its isolate) actually stops.
  Future<void> _onScheduledEnd() async {
    await _notifyOnWindowTransition(null);
    _stopNow();
  }

  void _stopNow() {
    _stopTimer?.cancel();
    _stopTimer = null;
    FlutterForegroundTask.stopService();
  }
}
