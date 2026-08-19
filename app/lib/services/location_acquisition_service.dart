import 'dart:async';

import 'package:battery_plus/battery_plus.dart';
import 'package:geolocator/geolocator.dart';

import '../models/queued_location_point.dart';
import 'location_queue_repository.dart';

class LocationAcquisitionService {
  static const _pollInterval = Duration(seconds: 10);
  static const _recordInterval = Duration(minutes: 5);

  final LocationQueueRepository _repository;
  final Future<void> Function()? _onPointRecorded;
  final Future<void> Function(Position position, int? batteryPct)? _onPositionPolled;
  final Battery _battery = Battery();

  Timer? _pollTimer;
  DateTime? _lastRecordedAt;

  LocationAcquisitionService({
    LocationQueueRepository? repository,
    Future<void> Function()? onPointRecorded,
    Future<void> Function(Position position, int? batteryPct)? onPositionPolled,
  })  : _repository = repository ?? LocationQueueRepository(),
        _onPointRecorded = onPointRecorded,
        _onPositionPolled = onPositionPolled;

  void start() {
    _lastRecordedAt = null;
    _schedulePoll();
  }

  Future<void> stop() async {
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  void _schedulePoll() {
    _pollTimer?.cancel();
    _pollTimer = Timer(_pollInterval, _onPoll);
  }

  Future<void> _onPoll() async {
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: AndroidSettings(accuracy: LocationAccuracy.high),
      );
      final batteryPct = await _battery.batteryLevel;

      await _onPositionPolled?.call(position, batteryPct);

      final due = _lastRecordedAt == null || position.timestamp.difference(_lastRecordedAt!) >= _recordInterval;
      if (due) {
        _lastRecordedAt = position.timestamp;
        await _recordPosition(position, batteryPct);
      }
    } catch (_) {
    } finally {
      _schedulePoll();
    }
  }

  Future<void> _recordPosition(Position position, int batteryPct) async {
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
