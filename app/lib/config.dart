/// Build-time configuration — never a hardcoded host in source.
///
/// Override with `--dart-define=API_BASE_URL=http://host:port` on
/// `flutter run`/`flutter build`. The default matches this repo's own dev
/// stack (`api/.env`'s API_PORT) as reachable from an Android emulator,
/// where `localhost` refers to the emulator itself, not the host machine —
/// `10.0.2.2` is the documented emulator alias for the host's loopback. A
/// physical device on the same network needs the host's real LAN IP
/// instead, passed via the same --dart-define at build time.
const String apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:58000',
);
