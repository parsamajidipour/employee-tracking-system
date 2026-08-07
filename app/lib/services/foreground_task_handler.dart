import 'dart:async';
import 'dart:convert';

import 'package:flutter_foreground_task/flutter_foreground_task.dart';
import 'package:permission_handler/permission_handler.dart';

import '../models/shift_window.dart';
import 'location_acquisition_service.dart';
import 'track_upload_service.dart';
import 'window_sync_service.dart';

/// Sent instead of a JSON window payload when the main isolate's own fetch
/// found no current window — see TrackingServiceController.applyWindowDecision().
const noWindowMarker = '__no_window__';

/// The entry point Android/iOS invokes to start this task's own isolate.
/// Registering the handler is *all* this does — everything else happens
/// through TrackingTaskHandler's lifecycle callbacks below.
@pragma('vm:entry-point')
void startCallback() {
  FlutterForegroundTask.setTaskHandler(TrackingTaskHandler());
}

/// Owns the one thing this whole feature exists to get right: stopping
/// tracking at the *exact* instant a window closes, never on some later
/// poll. A scheduled Timer, not a periodic check, is what actually stops
/// the service — the periodic tick (onRepeatEvent) and the cross-isolate
/// message (onReceiveData) both only ever *update that Timer's target*,
/// via the same shrink-immediately/extend-only-after-success rule.
class TrackingTaskHandler extends TaskHandler {
  Timer? _stopTimer;
  final LocationAcquisitionService _acquisition = LocationAcquisitionService();
  final TrackUploadService _upload = TrackUploadService();

  @override
  Future<void> onStart(DateTime timestamp, TaskStarter starter) async {
    _acquisition.start();
    await _reconcileWindow();
  }

  @override
  void onRepeatEvent(DateTime timestamp) {
    _checkLocationPermissionStillGranted().then((granted) {
      // A permission revoked mid-session takes priority over the window
      // check on this same tick — acquisition cannot continue without it
      // regardless of what the window says, and there's no reason to wait
      // for either the next window check or the scheduled stop to notice.
      if (!granted) {
        _stopNow();
        return;
      }
      _reconcileWindow();
    });
    // Same 30s tick, not a second timer — see TrackingServiceController's
    // eventAction comment.
    _upload.runUploadCycle();
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
    } catch (_) {
      // Malformed payload — ignore; the next periodic tick recovers.
    }
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
      _applyFetchedWindow(window);
    } catch (_) {
      // A failed re-fetch changes nothing: the previously scheduled stop
      // still fires at its already-known instant. We never extend
      // tracking because we couldn't confirm a fetch — only a
      // *successful* fetch is allowed to move the stop time later. A
      // shortened window (including "no window at all") always applies
      // immediately once a successful fetch reveals it; an extension
      // only ever applies after a successful fetch confirms it.
    }
  }

  void _applyFetchedWindow(ShiftWindow? window) {
    if (window == null) {
      _stopNow();
      return;
    }
    _rescheduleStop(window.end);
  }

  void _rescheduleStop(DateTime newEnd) {
    _stopTimer?.cancel();

    final delay = newEnd.difference(DateTime.now());
    if (!delay.isNegative && delay > Duration.zero) {
      _stopTimer = Timer(delay, _stopNow);
    } else {
      _stopNow();
    }
  }

  void _stopNow() {
    _stopTimer?.cancel();
    _stopTimer = null;
    FlutterForegroundTask.stopService();
  }
}
