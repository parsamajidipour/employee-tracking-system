import 'package:flutter/foundation.dart';

import '../config.dart';
import '../services/api_client.dart';
import '../services/auth_storage.dart';
import '../services/me_repository.dart';

enum AuthStatus { loading, signedOut, signedIn }

/// The single source of truth for "is this device logged in", and the
/// only place that decides what a 401 means for the rest of the app. The
/// root widget listens to this and swaps between LoginScreen and
/// HomeScreen — there's no separate router or navigation stack to keep in
/// sync with it.
class AuthController extends ChangeNotifier {
  final AuthStorage storage;
  late final ApiClient apiClient;
  late final MeRepository meRepository;

  AuthStatus status = AuthStatus.loading;

  /// Whether the guided permission-onboarding flow has run at least once —
  /// see AuthStorage.onboardingCompleted()'s docblock. Independent of
  /// AuthStatus: an already-onboarded device that signs out and back in
  /// does not see onboarding again.
  bool onboardingCompleted = false;

  /// Set only by handleUnauthorized(), shown once on the login screen,
  /// then cleared — see clearRevokedMessage().
  String? revokedMessage;

  AuthController({AuthStorage? storage}) : storage = storage ?? AuthStorage() {
    apiClient = ApiClient(
      baseUrl: apiBaseUrl,
      storage: this.storage,
      onUnauthorized: handleUnauthorized,
    );
    meRepository = MeRepository(apiClient: apiClient, storage: this.storage);
  }

  /// Called once at app start. Doesn't itself verify the stored token
  /// against the server — the home screen's first /me/window call does
  /// that naturally, and a 401 there routes back here the same way any
  /// later revoke would. "Relaunch skips login" means exactly this: no
  /// extra round trip before landing on the home screen.
  Future<void> initialize() async {
    final token = await storage.token();
    onboardingCompleted = await storage.onboardingCompleted();
    status = token != null ? AuthStatus.signedIn : AuthStatus.signedOut;
    notifyListeners();
  }

  Future<void> completeOnboarding() async {
    await storage.markOnboardingCompleted();
    onboardingCompleted = true;
    notifyListeners();
  }

  Future<void> login(String username, String password) async {
    final deviceIdentifier = await storage.deviceIdentifier();

    final json = await apiClient.postJsonUnauthenticated('/api/v1/device/login', {
      'username': username,
      'password': password,
      'device_identifier': deviceIdentifier,
      'device_name': 'Android device',
    });

    final token = json['token'] as String;
    await storage.saveToken(token);
    status = AuthStatus.signedIn;
    notifyListeners();
  }

  void clearRevokedMessage() {
    revokedMessage = null;
    // No notifyListeners(): the login screen clears its own locally-read
    // copy of this on the next build; re-notifying here would just be an
    // extra rebuild for no visible change.
  }

  /// Public so the background upload path (a separate isolate, with its own
  /// AuthStorage instance) can trigger the exact same sign-out after it
  /// clears the token and stops the service itself — see
  /// track_upload_service.dart and main.dart's task-data listener. This is
  /// the one place "revoked" turns into app state, called either directly
  /// (this isolate's own 401) or via that cross-isolate signal.
  Future<void> handleUnauthorized() async {
    await storage.clearToken();
    revokedMessage =
        'This device was deactivated. Contact your administrator to sign in again.';
    status = AuthStatus.signedOut;
    notifyListeners();
  }
}
