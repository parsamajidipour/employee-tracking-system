import '../config.dart';
import '../models/shift_window.dart';
import '../utils/format.dart';
import 'api_client.dart';
import 'auth_storage.dart';

Future<ShiftWindow?> fetchCurrentWindow() async {
  final storage = AuthStorage();
  final apiClient = ApiClient(
    baseUrl: apiBaseUrl,
    storage: storage,

    onUnauthorized: () async {},
  );

  final today = formatDateParam(DateTime.now());
  final json = await apiClient.getJson('/api/v1/me/window?date=$today');
  final current = json['current'];
  return current == null ? null : ShiftWindow.fromJson(current as Map<String, dynamic>);
}
