import 'dart:async';

import 'package:battery_plus/battery_plus.dart';
import 'package:geolocator/geolocator.dart';

import '../models/queued_location_point.dart';
import 'location_queue_repository.dart';

class LocationAcquisitionService {
  static const _heartbeat = Duration(minutes: 5);

  final LocationQueueRepository _repository;
  final Future<void> Function()? _onPointRecorded;
  final Battery _battery = Battery();

  Timer? _heartbeatTimer;

  LocationAcquisitionService({LocationQueueRepository? repository, Future<void> Function()? onPointRecorded})
      : _repository = repository ?? LocationQueueRepository(),
        _onPointRecorded = onPointRecorded;

  void start() {
    _scheduleHeartbeat();
  }

  Future<void> stop() async {
    _heartbeatTimer?.cancel();
    _heartbeatTimer = null;
  }

  void _scheduleHeartbeat() {
    _heartbeatTimer?.cancel();
    _heartbeatTimer = Timer(_heartbeat, _onHeartbeat);
  }

  Future<void> _onHeartbeat() async {
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: AndroidSettings(accuracy: LocationAccuracy.high),
      );
      await _recordPosition(position);
    } catch (_) {
    } finally {
      _scheduleHeartbeat();
    }
  }

  Future<void> _recordPosition(Position position) async {
    final batteryPct = await _battery.batteryLevel;

    await _repository.insert(QueuedLocationPoint(
      lat: position.latitude,
      lng: position.longitude,
      accuracyM: position.accuracy,
      speedMps: position.speed,
      headingDeg: position.heading,
      batteryPct: batteryPct,
      isMocked: position.isMocked,
      recordedAt: position.timestamp,
    ));
    await _onPointRecorded?.call();
  }
}
