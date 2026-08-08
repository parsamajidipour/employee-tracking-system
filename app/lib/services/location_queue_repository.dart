import '../models/queued_location_point.dart';
import 'location_queue_database.dart';

class LocationQueueRepository {
  Future<void> insert(QueuedLocationPoint point) async {
    final db = await openLocationQueueDatabase();
    await db.insert(locationPointsTable, {
      'lat': point.lat,
      'lng': point.lng,
      'accuracy_m': point.accuracyM,
      'speed_mps': point.speedMps,
      'heading_deg': point.headingDeg,
      'battery_pct': point.batteryPct,
      'is_mocked': point.isMocked ? 1 : 0,
      'recorded_at': point.recordedAt.toIso8601String(),
    });
  }

  Future<List<QueuedLocationPoint>> nextBatch({int limit = 500}) async {
    final db = await openLocationQueueDatabase();
    final rows = await db.query(locationPointsTable, orderBy: 'id ASC', limit: limit);
    return rows.map(_fromRow).toList();
  }

  Future<void> deleteIds(List<int> ids) async {
    if (ids.isEmpty) return;
    final db = await openLocationQueueDatabase();
    final placeholders = List.filled(ids.length, '?').join(',');
    await db.delete(locationPointsTable, where: 'id IN ($placeholders)', whereArgs: ids);
  }

  Future<void> purgeOlderThan(DateTime cutoff) async {
    final db = await openLocationQueueDatabase();
    await db.delete(locationPointsTable, where: 'recorded_at < ?', whereArgs: [cutoff.toIso8601String()]);
  }

  Future<int> count() async {
    final db = await openLocationQueueDatabase();
    final result = await db.rawQuery('SELECT COUNT(*) AS count FROM $locationPointsTable');
    return result.first['count'] as int;
  }

  QueuedLocationPoint _fromRow(Map<String, dynamic> row) => QueuedLocationPoint(
        id: row['id'] as int,
        lat: row['lat'] as double,
        lng: row['lng'] as double,
        accuracyM: row['accuracy_m'] as double?,
        speedMps: row['speed_mps'] as double?,
        headingDeg: row['heading_deg'] as double?,
        batteryPct: row['battery_pct'] as int?,
        isMocked: (row['is_mocked'] as int) == 1,
        recordedAt: DateTime.parse(row['recorded_at'] as String),
      );
}
