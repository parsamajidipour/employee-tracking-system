import '../config.dart';
import '../models/shift_window.dart';
import '../utils/format.dart';
import 'api_client.dart';
import 'auth_storage.dart';

/// A pure fetch, callable from any isolate (this app's background task
/// handler, workmanager's periodic callback, or the main UI isolate all
/// build their own AuthStorage/ApiClient here — see this app's established
/// pattern of never assuming a shared singleton across isolate boundaries).
///
/// Deliberately not routed through MeRepository: that class falls back to
/// a cached window on failure, which is right for the home screen's
/// display but wrong here — a failed fetch and "the server confirmed no
/// window" are different signals a caller must be able to tell apart (a
/// failure must never move a scheduled stop; "no window" must). So this
/// throws on failure instead of ever substituting a cached value, and
/// callers decide what a failure means for them.
Future<ShiftWindow?> fetchCurrentWindow() async {
  final storage = AuthStorage();
  final apiClient = ApiClient(
    baseUrl: apiBaseUrl,
    storage: storage,
    // A 401 here has nowhere useful to go — this isolate can't navigate
    // the UI. The upload path (steps 4/5) runs far more often and is a
    // much stronger revocation signal; that's where real 401 handling
    // lives. Here the exception just propagates to this function's caller.
    onUnauthorized: () async {},
  );

  final today = formatDateParam(DateTime.now());
  final json = await apiClient.getJson('/api/v1/me/window?date=$today');
  final current = json['current'];
  return current == null ? null : ShiftWindow.fromJson(current as Map<String, dynamic>);
}
