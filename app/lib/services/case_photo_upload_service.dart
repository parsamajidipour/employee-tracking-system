import 'dart:io';

import 'package:flutter/foundation.dart';

import 'api_exception.dart';
import 'case_photo_queue_repository.dart';
import 'case_repository.dart';

class CasePhotoUploadService extends ChangeNotifier {
  static const _batchLimit = 20;

  final CasePhotoQueueRepository _repository;
  final CaseRepository _caseRepository;
  bool _running = false;

  CasePhotoUploadService({
    required CasePhotoQueueRepository repository,
    required CaseRepository caseRepository,
  })  : _repository = repository,
        _caseRepository = caseRepository;

  Future<bool> runUploadCycle() async {
    if (_running) return false;
    _running = true;

    var anyChange = false;
    try {
      final batch = await _repository.nextPendingBatch(limit: _batchLimit);

      for (final item in batch) {
        try {
          await _caseRepository.uploadPhoto(
            item.caseId,
            filePath: item.filePath,
            lat: item.lat,
            lng: item.lng,
            accuracyM: item.accuracyM,
            capturedAt: item.capturedAt,
          );
          await _repository.deleteId(item.id!);
          await _deleteFileQuietly(item.filePath);
          anyChange = true;
        } on ApiException catch (e) {
          if (e.isUnauthorized) break;
          if (e.statusCode == 409 || e.statusCode == 422) {
            await _repository.markPermanentFailure(item.id!, e.message);
            anyChange = true;
            continue;
          }
          break;
        } catch (_) {
          break;
        }
      }

      return anyChange;
    } finally {
      _running = false;
      if (anyChange) notifyListeners();
    }
  }

  Future<void> _deleteFileQuietly(String path) async {
    try {
      final file = File(path);
      if (await file.exists()) await file.delete();
    } catch (_) {}
  }
}
