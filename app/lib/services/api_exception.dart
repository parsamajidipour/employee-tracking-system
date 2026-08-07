/// Wraps every non-2xx API response. `message` is the server's own
/// `{"message": "..."}` body when present (login failures, device
/// conflicts, inactive-account, etc.) — always prefer showing this over a
/// generic string, since it's already written for an end user.
class ApiException implements Exception {
  final int? statusCode;
  final String message;

  ApiException(this.statusCode, this.message);

  /// True when this is the specific "your session no longer exists"
  /// signal — a 401 on a request that carried our own token. Never true
  /// for the login endpoint itself, which sends no token and where a 401
  /// just means "wrong username or password" (see DeviceAuthController's
  /// STATUSES map on the server).
  bool get isUnauthorized => statusCode == 401;

  @override
  String toString() => message;
}
