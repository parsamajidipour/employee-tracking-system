import 'dart:async';
import 'dart:convert';

import 'package:flutter_foreground_task/flutter_foreground_task.dart';
import 'package:permission_handler/permission_handler.dart';

import '../models/shift_window.dart';
import 'live_position_ping_service.dart';
import 'location_acquisition_service.dart';
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
  late final LocationAcquisitionService _acquisition = LocationAcquisitionService(
    onPointRecorded: _upload.runUploadCycle,
    onPositionPolled: _livePosition.ping,
  );

  @override
  Future<void> onStart(DateTime timestamp, TaskStarter starter) async {
    _acquisition.start();
    await _reconcileWindow();
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
