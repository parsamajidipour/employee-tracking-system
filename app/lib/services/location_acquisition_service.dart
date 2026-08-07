import 'dart:async';

import 'package:battery_plus/battery_plus.dart';
import 'package:geolocator/geolocator.dart';

import '../models/queued_location_point.dart';
import 'location_queue_repository.dart';

/// The 30m-distance-filter + 60s-heartbeat acquisition SPEC.md §6 requires
/// — never a fixed high-frequency interval. Two sources feed the same
/// queue: a distance-filter stream (fires on real movement) and a
/// heartbeat Timer (fires even standing still, so a stationary employee
/// still produces periodic points). "Reduced frequency when stationary" is
/// satisfied by backing the heartbeat interval off — doubled each time the
/// heartbeat itself finds no meaningful movement since the last one,
/// capped, and reset the instant real movement is detected again (by
/// either source). No separate stationary-detection algorithm beyond that.
class LocationAcquisitionService {
  static const _baseHeartbeat = Duration(seconds: 60);
  static const _maxHeartbeat = Duration(minutes: 10);
  static const _stationaryThresholdMeters = 15.0;

  final LocationQueueRepository _repository;
  final Battery _battery = Battery();

  StreamSubscription<Position>? _distanceFilterSubscription;
  Timer? _heartbeatTimer;
  Duration _heartbeatInterval = _baseHeartbeat;
  Position? _lastPosition;

  LocationAcquisitionService({LocationQueueRepository? repository})
      : _repository = repository ?? LocationQueueRepository();

  void start() {
    _distanceFilterSubscription = Geolocator.getPositionStream(
      locationSettings: AndroidSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 30,
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
      // A single failed heartbeat isn't fatal — the next one, at whatever
      // interval was already scheduled, tries again.
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
  }
}
