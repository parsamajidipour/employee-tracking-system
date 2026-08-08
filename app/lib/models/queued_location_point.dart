

class QueuedLocationPoint {
  final int? id;
  final double lat;
  final double lng;
  final double? accuracyM;
  final double? speedMps;
  final double? headingDeg;
  final int? batteryPct;
  final bool isMocked;
  final DateTime recordedAt;

  QueuedLocationPoint({
    this.id,
    required this.lat,
    required this.lng,
    this.accuracyM,
    this.speedMps,
    this.headingDeg,
    this.batteryPct,
    required this.isMocked,
    required this.recordedAt,
  });

  Map<String, dynamic> toApiJson() => {
        'lat': lat,
        'lng': lng,
        'accuracy_m': accuracyM,
        'speed_mps': speedMps,
        'heading_deg': headingDeg,
        'battery_pct': batteryPct,
        'is_mocked': isMocked,
        'recorded_at': recordedAt.toIso8601String(),
      };
}
