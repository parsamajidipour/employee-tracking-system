import '../models/queued_case_photo.dart';
import 'case_photo_queue_database.dart';

class CasePhotoQueueRepository {
  Future<int> insert(QueuedCasePhoto photo) async {
    final db = await openCasePhotoQueueDatabase();
    return db.insert(casePhotoQueueTable, {
      'case_id': photo.caseId,
      'file_path': photo.filePath,
      'lat': photo.lat,
      'lng': photo.lng,
      'accuracy_m': photo.accuracyM,
      'captured_at': photo.capturedAt.toIso8601String(),
      'status': photo.status,
      'failure_reason': photo.failureReason,
      'created_at': photo.createdAt.toIso8601String(),
    });
  }

  Future<List<QueuedCasePhoto>> nextPendingBatch({int limit = 20}) async {
    final db = await openCasePhotoQueueDatabase();
    final rows = await db.query(
      casePhotoQueueTable,
      where: 'status = ?',
      whereArgs: [casePhotoQueueStatusPending],
      orderBy: 'id ASC',
      limit: limit,
    );
    return rows.map(_fromRow).toList();
  }

  Future<List<QueuedCasePhoto>> allForCase(int caseId) async {
    final db = await openCasePhotoQueueDatabase();
    final rows = await db.query(
      casePhotoQueueTable,
      where: 'case_id = ?',
      whereArgs: [caseId],
      orderBy: 'id ASC',
    );
    return rows.map(_fromRow).toList();
  }

  Future<void> markPermanentFailure(int id, String reason) async {
    final db = await openCasePhotoQueueDatabase();
    await db.update(
      casePhotoQueueTable,
      {
        'status': casePhotoQueueStatusFailedPermanent,
        'failure_reason': reason,
      },
      where: 'id = ?',
      whereArgs: [id],
    );
  }

  Future<void> deleteId(int id) async {
    final db = await openCasePhotoQueueDatabase();
    await db.delete(casePhotoQueueTable, where: 'id = ?', whereArgs: [id]);
  }

  QueuedCasePhoto _fromRow(Map<String, dynamic> row) => QueuedCasePhoto(
        id: row['id'] as int,
        caseId: row['case_id'] as int,
        filePath: row['file_path'] as String,
        lat: row['lat'] as double,
        lng: row['lng'] as double,
        accuracyM: row['accuracy_m'] as double?,
        capturedAt: DateTime.parse(row['captured_at'] as String),
        status: row['status'] as String,
        failureReason: row['failure_reason'] as String?,
        createdAt: DateTime.parse(row['created_at'] as String),
      );
}
