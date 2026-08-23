import '../models/inspection_case.dart';
import 'api_client.dart';

class CaseRepository {
  final ApiClient apiClient;

  CaseRepository({required this.apiClient});

  Future<List<InspectionCase>> fetchCases({String? status}) async {
    final query = status == null ? '' : '?status=$status';
    final json = await apiClient.getJsonList('/api/v1/me/cases$query');
    return json
        .map((e) => InspectionCase.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<CaseUnseenCount> fetchUnseenCount() async {
    final json = await apiClient.getJson('/api/v1/me/cases/unseen-count');
    return CaseUnseenCount.fromJson(json);
  }

  Future<InspectionCase> fetchCase(int id) async {
    final json = await apiClient.getJson('/api/v1/me/cases/$id');
    return InspectionCase.fromJson(json);
  }

  Future<InspectionCase> acceptCase(int id, {required DateTime plannedAt}) async {
    final json = await apiClient.postJson('/api/v1/me/cases/$id/accept', {
      'planned_at': plannedAt.toIso8601String(),
    });
    return InspectionCase.fromJson(json);
  }

  Future<InspectionCase> rejectCase(int id, {String? note}) async {
    final json = await apiClient.postJson('/api/v1/me/cases/$id/reject', {
      if (note != null && note.isNotEmpty) 'note': note,
    });
    return InspectionCase.fromJson(json);
  }

  Future<InspectionCase> startCase(int id) async {
    final json = await apiClient.postJson('/api/v1/me/cases/$id/start', {});
    return InspectionCase.fromJson(json);
  }

  Future<InspectionCase> completeCase(int id, {String? note}) async {
    final json = await apiClient.postJson('/api/v1/me/cases/$id/complete', {
      if (note != null && note.isNotEmpty) 'note': note,
    });
    return InspectionCase.fromJson(json);
  }

  Future<CasePhoto> uploadPhoto(
    int id, {
    required String filePath,
    required double lat,
    required double lng,
    double? accuracyM,
    required DateTime capturedAt,
  }) async {
    final json = await apiClient.postMultipart(
      '/api/v1/me/cases/$id/photos',
      {
        'lat': lat.toString(),
        'lng': lng.toString(),
        if (accuracyM != null) 'accuracy_m': accuracyM.toString(),
        'captured_at': capturedAt.toIso8601String(),
      },
      filePath: filePath,
      fileField: 'photo',
    );
    return CasePhoto.fromJson(json);
  }
}
