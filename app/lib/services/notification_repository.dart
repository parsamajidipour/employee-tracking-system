import '../models/app_notification.dart';
import 'api_client.dart';

class NotificationRepository {
  final ApiClient apiClient;

  NotificationRepository({required this.apiClient});

  Future<NotificationInbox> fetchInbox() async {
    final json = await apiClient.getJson('/api/v1/notifications');
    return NotificationInbox.fromJson(json);
  }

  Future<void> markAllRead() =>
      apiClient.postJson('/api/v1/notifications/read-all', {});

  Future<void> markRead(String id) =>
      apiClient.postJson('/api/v1/notifications/$id/read', {});
}
