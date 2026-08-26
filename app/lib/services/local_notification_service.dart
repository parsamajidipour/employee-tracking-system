import 'dart:async';

import 'package:flutter_local_notifications/flutter_local_notifications.dart';

class LocalNotificationService {
  static final FlutterLocalNotificationsPlugin _plugin =
      FlutterLocalNotificationsPlugin();
  static final _taps = StreamController<String?>.broadcast();
  static bool _initialized = false;
  static String? _launchPayload;

  static const _channelId = 'alerts_channel';

  static Stream<String?> get taps => _taps.stream;

  static Future<void> initialize() => _ensureInitialized();

  static String? takeLaunchPayload() {
    final payload = _launchPayload;
    _launchPayload = null;
    return payload;
  }

  static Future<void> _ensureInitialized() async {
    if (_initialized) return;

    await _plugin.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      ),
      onDidReceiveNotificationResponse: (response) {
        _taps.add(response.payload);
      },
    );

    final launchDetails = await _plugin.getNotificationAppLaunchDetails();
    if (launchDetails?.didNotificationLaunchApp ?? false) {
      _launchPayload = launchDetails?.notificationResponse?.payload;
    }

    const channel = AndroidNotificationChannel(
      _channelId,
      'Alerts',
      description: 'Shift and update alerts shown when the app is not open.',
      importance: Importance.high,
    );

    await _plugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    _initialized = true;
  }

  static Future<void> show({
    required int id,
    required String title,
    required String body,
    String? payload,
  }) async {
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
            channelDescription:
                'Shift and update alerts shown when the app is not open.',
            importance: Importance.high,
            priority: Priority.high,
          ),
        ),
        payload: payload,
      );
    } catch (_) {
      // Best-effort: a missed alert should never take tracking down with it.
    }
  }
}
