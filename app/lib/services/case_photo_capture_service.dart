import 'dart:async';
import 'dart:io';

import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:uuid/uuid.dart';

import '../models/queued_case_photo.dart';
import 'case_photo_queue_repository.dart';
import 'case_photo_upload_service.dart';

class CasePhotoCaptureService {
  final CasePhotoQueueRepository _queueRepository;
  final CasePhotoUploadService _uploadService;
  final Uuid _uuid = const Uuid();

  CasePhotoCaptureService({
    required CasePhotoQueueRepository queueRepository,
    required CasePhotoUploadService uploadService,
  })  : _queueRepository = queueRepository,
        _uploadService = uploadService;

  Future<void> enqueue({
    required int caseId,
    required String tempFilePath,
    required double lat,
    required double lng,
    double? accuracyM,
    required DateTime capturedAt,
  }) async {
    final persistedPath = await _persist(tempFilePath);

    await _queueRepository.insert(QueuedCasePhoto(
      caseId: caseId,
      filePath: persistedPath,
      lat: lat,
      lng: lng,
      accuracyM: accuracyM,
      capturedAt: capturedAt,
      createdAt: DateTime.now(),
    ));

    unawaited(_uploadService.runUploadCycle());
  }

  Future<String> _persist(String tempFilePath) async {
    final supportDir = await getApplicationSupportDirectory();
    final targetDir = Directory(p.join(supportDir.path, 'case_photos'));
    await targetDir.create(recursive: true);

    final extension = p.extension(tempFilePath);
    final targetPath = p.join(targetDir.path, '${_uuid.v4()}$extension');
    await File(tempFilePath).copy(targetPath);
    return targetPath;
  }
}
