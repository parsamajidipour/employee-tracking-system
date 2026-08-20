import 'package:flutter/foundation.dart';

import '../config.dart';
import '../services/api_client.dart';
import '../services/auth_storage.dart';
import '../services/me_repository.dart';

enum AuthStatus { loading, signedOut, signedIn }

class AuthController extends ChangeNotifier {
  final AuthStorage storage;
  late final ApiClient apiClient;
  late final MeRepository meRepository;

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
  }

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
  }

  void clearRevokedMessage() {
    revokedMessage = null;
  }

  Future<void> handleUnauthorized() async {
    await storage.clearToken();
    revokedMessage =
        'This device was deactivated. Contact your administrator to sign in again.';
    status = AuthStatus.signedOut;
    notifyListeners();
  }

  Future<void> signOut() async {
    await storage.clearToken();
    status = AuthStatus.signedOut;
    notifyListeners();
  }
}
