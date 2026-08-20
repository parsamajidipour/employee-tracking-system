import 'package:flutter_local_notifications/flutter_local_notifications.dart';

class LocalNotificationService {
  static final FlutterLocalNotificationsPlugin _plugin = FlutterLocalNotificationsPlugin();
  static bool _initialized = false;

  static const _channelId = 'alerts_channel';

  static Future<void> _ensureInitialized() async {
    if (_initialized) return;

    await _plugin.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      ),
    );

    const channel = AndroidNotificationChannel(
      _channelId,
      'Alerts',
      description: 'Shift and update alerts shown when the app is not open.',
      importance: Importance.high,
    );

    await _plugin
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    _initialized = true;
  }

  static Future<void> show({required int id, required String title, required String body}) async {
    try {
      await _ensureInitialized();
      await _plugin.show(
        id,
        title,
        body,
        const NotificationDetails(
          android: AndroidNotificationDetails(
            _channelId,
            'Alerts',
            channelDescription: 'Shift and update alerts shown when the app is not open.',
            importance: Importance.high,
            priority: Priority.high,
          ),
        ),
      );
    } catch (_) {
      // Best-effort: a missed alert should never take tracking down with it.
    }
  }
}
