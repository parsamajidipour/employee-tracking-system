const casePhotoQueueStatusPending = 'pending';
const casePhotoQueueStatusFailedPermanent = 'failed_permanent';

class QueuedCasePhoto {
  final int? id;
  final int caseId;
  final String filePath;
  final double lat;
  final double lng;
  final double? accuracyM;
  final DateTime capturedAt;
  final String status;
  final String? failureReason;
  final DateTime createdAt;

  QueuedCasePhoto({
    this.id,
    required this.caseId,
    required this.filePath,
    required this.lat,
    required this.lng,
    this.accuracyM,
    required this.capturedAt,
    this.status = casePhotoQueueStatusPending,
    this.failureReason,
    required this.createdAt,
  });

  bool get isFailedPermanent => status == casePhotoQueueStatusFailedPermanent;
}
