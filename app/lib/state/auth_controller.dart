import 'package:flutter/foundation.dart';

import '../config.dart';
import '../services/api_client.dart';
import '../services/auth_storage.dart';
import '../services/case_repository.dart';
import '../services/me_repository.dart';
import '../services/notification_repository.dart';
import '../services/realtime_client.dart';
import 'live_updates.dart';

enum AuthStatus { loading, signedOut, signedIn }

class AuthController extends ChangeNotifier {
  final AuthStorage storage;
  late final ApiClient apiClient;
  late final MeRepository meRepository;
  late final CaseRepository caseRepository;
  late final NotificationRepository notificationRepository;
  late final LiveUpdates liveUpdates;

  AuthStatus status = AuthStatus.loading;

  bool onboardingCompleted = false;

  String? revokedMessage;

  AuthController({AuthStorage? storage}) : storage = storage ?? AuthStorage() {
    apiClient = ApiClient(
      baseUrl: apiBaseUrl,
      storage: this.storage,
      onUnauthorized: handleUnauthorized,
    );
    meRepository = MeRepository(apiClient: apiClient, storage: this.storage);
    caseRepository = CaseRepository(apiClient: apiClient);
    notificationRepository = NotificationRepository(apiClient: apiClient);
    liveUpdates = LiveUpdates(
      meRepository: meRepository,
      notificationRepository: notificationRepository,
      client: RealtimeClient(authorizer: apiClient.authorizeChannel),
    );
  }

  Future<void> initialize() async {
    final token = await storage.token();
    onboardingCompleted = await storage.onboardingCompleted();
    status = token != null ? AuthStatus.signedIn : AuthStatus.signedOut;
    notifyListeners();

    if (status == AuthStatus.signedIn) {
      await liveUpdates.start();
    }
  }

  Future<void> completeOnboarding() async {
    await storage.markOnboardingCompleted();
    onboardingCompleted = true;
    notifyListeners();
  }

  Future<void> login(String identifier, String password) async {
    final deviceIdentifier = await storage.deviceIdentifier();

    final json = await apiClient.postJsonUnauthenticated('/api/v1/device/login', {
      'identifier': identifier,
      'password': password,
      'device_identifier': deviceIdentifier,
      'device_name': 'Android device',
    });

    final token = json['token'] as String;
    await storage.saveToken(token);
    status = AuthStatus.signedIn;
    notifyListeners();

    await liveUpdates.start();
  }

  void clearRevokedMessage() {
    revokedMessage = null;
  }

  Future<void> handleUnauthorized() async {
    await liveUpdates.stop();
    await storage.clearToken();
    revokedMessage =
        'This device was deactivated. Contact your administrator to sign in again.';
    status = AuthStatus.signedOut;
    notifyListeners();
  }

  @override
  void dispose() {
    liveUpdates.dispose();
    super.dispose();
  }
}
