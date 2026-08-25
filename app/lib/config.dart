const String apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:18000',
);

const String reverbAppKey = String.fromEnvironment('REVERB_APP_KEY');

bool get realtimeConfigured => reverbAppKey.isNotEmpty;

const int reverbPort = int.fromEnvironment('REVERB_PORT', defaultValue: 8080);

const String _reverbHostOverride = String.fromEnvironment('REVERB_HOST');

Uri realtimeUri() {
  final api = Uri.parse(apiBaseUrl);
  final secure = api.scheme == 'https';

  return Uri(
    scheme: secure ? 'wss' : 'ws',
    host: _reverbHostOverride.isEmpty ? api.host : _reverbHostOverride,
    port: reverbPort,
    path: '/app/$reverbAppKey',
    queryParameters: const {
      'protocol': '7',
      'client': 'smart-inspection-android',
      'version': '1.0',
    },
  );
}
