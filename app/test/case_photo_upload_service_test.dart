import 'package:app/models/inspection_case.dart';
import 'package:app/models/queued_case_photo.dart';
import 'package:app/services/api_client.dart';
import 'package:app/services/api_exception.dart';
import 'package:app/services/auth_storage.dart';
import 'package:app/services/case_photo_queue_repository.dart';
import 'package:app/services/case_photo_upload_service.dart';
import 'package:app/services/case_repository.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:path/path.dart' as p;
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

class _FakeCaseRepository extends CaseRepository {
  _FakeCaseRepository({List<Object?>? responses})
      : responses = responses ?? [],
        super(
            apiClient: ApiClient(
          baseUrl: 'https://example.invalid',
          storage: AuthStorage(),
          onUnauthorized: () async {},
        ));

  final List<Object?> responses;
  final List<int> attemptedCaseIds = [];

  @override
  Future<CasePhoto> uploadPhoto(
    int id, {
    required String filePath,
    required double lat,
    required double lng,
    double? accuracyM,
    required DateTime capturedAt,
  }) async {
    attemptedCaseIds.add(id);
    final next = responses.removeAt(0);
    if (next is Exception) throw next;
    return CasePhoto.fromJson(next as Map<String, dynamic>);
  }
}

Map<String, dynamic> _photoJson({bool verified = true}) => {
      'id': 1,
      'url': '/photo.jpg',
      'is_gps_verified': verified,
      'captured_at': DateTime.utc(2026, 9, 1, 8).toIso8601String(),
    };

QueuedCasePhoto _queuedPhoto({int caseId = 1}) => QueuedCasePhoto(
      caseId: caseId,
      filePath: '/tmp/a.jpg',
      lat: 23.6,
      lng: 58.5,
      capturedAt: DateTime.utc(2026, 9, 1, 8),
      createdAt: DateTime.utc(2026, 9, 1, 8),
    );

void main() {
  setUpAll(() {
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;
  });

  late CasePhotoQueueRepository queueRepository;

  setUp(() {
    databaseFactory = databaseFactoryFfi;
    queueRepository = CasePhotoQueueRepository();
  });

  tearDown(() async {
    final path =
        p.join(await databaseFactory.getDatabasesPath(), 'case_photo_queue.db');
    await databaseFactory.deleteDatabase(path);
  });

  test('success deletes the row and reports a change', () async {
    final id = await queueRepository.insert(_queuedPhoto());
    final caseRepository = _FakeCaseRepository(responses: [_photoJson()]);
    final service = CasePhotoUploadService(
      repository: queueRepository,
      caseRepository: caseRepository,
    );

    var notified = false;
    service.addListener(() => notified = true);

    final result = await service.runUploadCycle();

    expect(result, isTrue);
    expect(notified, isTrue);
    expect(await queueRepository.nextPendingBatch(), isEmpty);
    expect(id, greaterThan(0));
    expect(caseRepository.attemptedCaseIds, [1]);
  });

  test('publishes the server GPS verification result', () async {
    await queueRepository.insert(_queuedPhoto(caseId: 7));
    final caseRepository = _FakeCaseRepository(
      responses: [
        {
          ..._photoJson(verified: false),
          'distance_from_case_m': 420.4,
        },
      ],
    );
    final service = CasePhotoUploadService(
      repository: queueRepository,
      caseRepository: caseRepository,
    );

    await service.runUploadCycle();

    expect(service.lastUploadEvent?.caseId, 7);
    expect(service.lastUploadEvent?.photo.isGpsVerified, isFalse);
    expect(service.lastUploadEvent?.photo.distanceFromCaseM, 420.4);
  });

  test(
      'a transient failure on item 1 leaves it pending and never attempts item 2',
      () async {
    await queueRepository.insert(_queuedPhoto(caseId: 1));
    await queueRepository.insert(_queuedPhoto(caseId: 2));
    final caseRepository = _FakeCaseRepository(responses: [
      Exception('network down'),
      _photoJson(),
    ]);
    final service = CasePhotoUploadService(
      repository: queueRepository,
      caseRepository: caseRepository,
    );

    final result = await service.runUploadCycle();

    expect(result, isFalse);
    expect(caseRepository.attemptedCaseIds, [1]);
    expect(await queueRepository.nextPendingBatch(), hasLength(2));
  });

  test('409 marks the item failed_permanent and proceeds to the next item',
      () async {
    await queueRepository.insert(_queuedPhoto(caseId: 1));
    await queueRepository.insert(_queuedPhoto(caseId: 2));
    final caseRepository = _FakeCaseRepository(responses: [
      ApiException(409, 'Case is no longer in progress.'),
      _photoJson(),
    ]);
    final service = CasePhotoUploadService(
      repository: queueRepository,
      caseRepository: caseRepository,
    );

    final result = await service.runUploadCycle();

    expect(result, isTrue);
    expect(caseRepository.attemptedCaseIds, [1, 2]);
    final remaining = await queueRepository.allForCase(1);
    expect(remaining.single.status, casePhotoQueueStatusFailedPermanent);
    expect(remaining.single.failureReason, 'Case is no longer in progress.');
    expect(await queueRepository.nextPendingBatch(), hasLength(0));
  });

  test('422 behaves the same as 409', () async {
    await queueRepository.insert(_queuedPhoto(caseId: 1));
    final caseRepository = _FakeCaseRepository(responses: [
      ApiException(422, 'Invalid coordinates.'),
    ]);
    final service = CasePhotoUploadService(
      repository: queueRepository,
      caseRepository: caseRepository,
    );

    final result = await service.runUploadCycle();

    expect(result, isTrue);
    final remaining = await queueRepository.allForCase(1);
    expect(remaining.single.status, casePhotoQueueStatusFailedPermanent);
  });

  test('401 stops the loop without mutating remaining rows', () async {
    await queueRepository.insert(_queuedPhoto(caseId: 1));
    await queueRepository.insert(_queuedPhoto(caseId: 2));
    final caseRepository = _FakeCaseRepository(responses: [
      ApiException(401, 'This device was deactivated.'),
      _photoJson(),
    ]);
    final service = CasePhotoUploadService(
      repository: queueRepository,
      caseRepository: caseRepository,
    );

    final result = await service.runUploadCycle();

    expect(result, isFalse);
    expect(caseRepository.attemptedCaseIds, [1]);
    expect(await queueRepository.nextPendingBatch(), hasLength(2));
  });

  test('concurrent runUploadCycle calls only run one cycle at a time',
      () async {
    await queueRepository.insert(_queuedPhoto(caseId: 1));
    final caseRepository = _FakeCaseRepository(responses: [_photoJson()]);
    final service = CasePhotoUploadService(
      repository: queueRepository,
      caseRepository: caseRepository,
    );

    final results = await Future.wait([
      service.runUploadCycle(),
      service.runUploadCycle(),
    ]);

    expect(results.where((r) => r).length, 1);
    expect(caseRepository.attemptedCaseIds, [1]);
  });
}
