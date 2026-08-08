import 'dart:async';

import 'package:battery_plus/battery_plus.dart';
import 'package:geolocator/geolocator.dart';

import '../models/queued_location_point.dart';
import 'location_queue_repository.dart';

class LocationAcquisitionService {
  static const _baseHeartbeat = Duration(seconds: 5);
  static const _maxHeartbeat = Duration(seconds: 15);
  static const _stationaryThresholdMeters = 5.0;

  final LocationQueueRepository _repository;
  final Future<void> Function()? _onPointRecorded;
  final Battery _battery = Battery();

  StreamSubscription<Position>? _distanceFilterSubscription;
  Timer? _heartbeatTimer;
  Duration _heartbeatInterval = _baseHeartbeat;
  Position? _lastPosition;

  LocationAcquisitionService({LocationQueueRepository? repository, Future<void> Function()? onPointRecorded})
      : _repository = repository ?? LocationQueueRepository(),
        _onPointRecorded = onPointRecorded;

  void start() {
    _distanceFilterSubscription = Geolocator.getPositionStream(
      locationSettings: AndroidSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 5,
      ),
    ).listen(_onMovementDetected);

    _scheduleHeartbeat();
  }

  Future<void> stop() async {
    await _distanceFilterSubscription?.cancel();
    _distanceFilterSubscription = null;
    _heartbeatTimer?.cancel();
    _heartbeatTimer = null;
  }

  void _onMovementDetected(Position position) {
    _heartbeatInterval = _baseHeartbeat;
    _recordPosition(position);
  }

  void _scheduleHeartbeat() {
    _heartbeatTimer?.cancel();
    _heartbeatTimer = Timer(_heartbeatInterval, _onHeartbeat);
  }

  Future<void> _onHeartbeat() async {
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: AndroidSettings(accuracy: LocationAccuracy.high),
      );

      final stationary = _lastPosition != null &&
          Geolocator.distanceBetween(
                _lastPosition!.latitude,
                _lastPosition!.longitude,
                position.latitude,
                position.longitude,
              ) <
              _stationaryThresholdMeters;

      _heartbeatInterval = stationary
          ? Duration(
              milliseconds: (_heartbeatInterval.inMilliseconds * 2)
                  .clamp(_baseHeartbeat.inMilliseconds, _maxHeartbeat.inMilliseconds),
            )
          : _baseHeartbeat;

      await _recordPosition(position);
    } catch (_) {
    } finally {
      _scheduleHeartbeat();
    }
  }

  Future<void> _recordPosition(Position position) async {
    _lastPosition = position;
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
