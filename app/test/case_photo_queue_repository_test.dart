import 'package:app/models/queued_case_photo.dart';
import 'package:app/services/case_photo_queue_repository.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:path/path.dart' as p;
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

QueuedCasePhoto _photo({int caseId = 1, String filePath = '/tmp/a.jpg'}) =>
    QueuedCasePhoto(
      caseId: caseId,
      filePath: filePath,
      lat: 23.6,
      lng: 58.5,
      accuracyM: 5.0,
      capturedAt: DateTime.utc(2026, 9, 1, 8),
      createdAt: DateTime.utc(2026, 9, 1, 8),
    );

void main() {
  setUpAll(() {
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;
  });

  late CasePhotoQueueRepository repository;

  setUp(() {
    databaseFactory = databaseFactoryFfi;
    repository = CasePhotoQueueRepository();
  });

  tearDown(() async {
    final path = p.join(await databaseFactory.getDatabasesPath(), 'case_photo_queue.db');
    await databaseFactory.deleteDatabase(path);
  });

  test('insert then nextPendingBatch returns the row', () async {
    final id = await repository.insert(_photo());

    final batch = await repository.nextPendingBatch();
    expect(batch, hasLength(1));
    expect(batch.single.id, id);
    expect(batch.single.status, casePhotoQueueStatusPending);
  });

  test('markPermanentFailure excludes the row from nextPendingBatch', () async {
    final id = await repository.insert(_photo());

    await repository.markPermanentFailure(id, 'case closed');

    final batch = await repository.nextPendingBatch();
    expect(batch, isEmpty);

    final all = await repository.allForCase(1);
    expect(all.single.status, casePhotoQueueStatusFailedPermanent);
    expect(all.single.failureReason, 'case closed');
  });

  test('deleteId removes the row', () async {
    final id = await repository.insert(_photo());

    await repository.deleteId(id);

    expect(await repository.nextPendingBatch(), isEmpty);
    expect(await repository.allForCase(1), isEmpty);
  });

  test('allForCase scopes by case id', () async {
    await repository.insert(_photo(caseId: 1));
    await repository.insert(_photo(caseId: 2));

    expect(await repository.allForCase(1), hasLength(1));
    expect(await repository.allForCase(2), hasLength(1));
    expect(await repository.allForCase(3), isEmpty);
  });
}
