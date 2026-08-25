import 'dart:async';

import 'package:flutter/foundation.dart';

import '../models/app_notification.dart';
import '../services/local_notification_service.dart';
import '../services/me_repository.dart';
import '../services/notification_repository.dart';
import '../services/realtime_client.dart';

class LiveUpdates extends ChangeNotifier {
  final MeRepository meRepository;
  final NotificationRepository notificationRepository;
  final RealtimeClient client;

  LiveUpdates({
    required this.meRepository,
    required this.notificationRepository,
    required this.client,
  });

  NotificationInbox inbox = NotificationInbox.empty;

  RealtimeConnectionState connectionState = RealtimeConnectionState.disconnected;

  int revision = 0;

  StreamSubscription<RealtimeEvent>? _eventSubscription;
  StreamSubscription<RealtimeConnectionState>? _stateSubscription;
  Timer? _fallbackTimer;

  bool _started = false;
  bool _seeded = false;
  int? _userId;
  final Set<String> _announced = <String>{};

  List<AppNotification> get notifications => inbox.notifications;

  int get unreadCount => inbox.unreadCount;

  bool get isLive => connectionState == RealtimeConnectionState.connected;

  Future<void> start() async {
    if (_started) return;
    _started = true;

    _eventSubscription = client.events.listen(_onEvent);
    _stateSubscription = client.states.listen((state) {
      connectionState = state;
      notifyListeners();
    });

    _fallbackTimer = Timer.periodic(
      const Duration(seconds: 45),
      (_) => _tick(),
    );

    await _resolveChannel();
    await refreshInbox();
  }

  Future<void> stop() async {
    _started = false;
    _seeded = false;
    _fallbackTimer?.cancel();
    _fallbackTimer = null;
    await _eventSubscription?.cancel();
    await _stateSubscription?.cancel();
    _eventSubscription = null;
    _stateSubscription = null;
    _userId = null;
    _announced.clear();
    inbox = NotificationInbox.empty;
    await client.disconnect();
    connectionState = RealtimeConnectionState.disconnected;
    notifyListeners();
  }

  Future<void> _resolveChannel() async {
    _userId ??= await meRepository.fetchUserId();
    final id = _userId;
    if (id == null) return;

    client.subscribe('private-App.Models.User.$id');
    client.connect();
  }

  void _tick() {
    if (_userId == null || !isLive) {
      unawaited(_resolveChannel());
    }
    unawaited(refreshInbox());
    if (!isLive) bumpRevision();
  }

  void bumpRevision() {
    revision++;
    notifyListeners();
  }

  void _onEvent(RealtimeEvent event) {
    unawaited(refreshInbox());
    bumpRevision();
  }

  Future<void> refreshInbox() async {
    try {
      final fetched = await notificationRepository.fetchInbox();
      final previous = inbox;
      inbox = fetched;

      _announceNew(previous);
      notifyListeners();
    } catch (_) {
    }
  }

  void _announceNew(NotificationInbox previous) {
    if (!_seeded) {
      _seeded = true;
      _announced.addAll(inbox.notifications.map((n) => n.id));
      return;
    }

    for (final notification in inbox.notifications.reversed) {
      if (notification.read) continue;
      if (!_announced.add(notification.id)) continue;

      unawaited(LocalNotificationService.show(
        id: notification.id.hashCode & 0x7fffffff,
        title: notification.title,
        body: notification.message.isEmpty
            ? 'Open the app for details.'
            : notification.message,
      ));
    }
  }

  Future<void> markAllRead() async {
    if (unreadCount == 0) return;

    inbox = NotificationInbox(
      notifications: inbox.notifications,
      unreadCount: 0,
    );
    notifyListeners();

    try {
      await notificationRepository.markAllRead();
    } catch (_) {
    }
    await refreshInbox();
  }

  @override
  void dispose() {
    _fallbackTimer?.cancel();
    unawaited(_eventSubscription?.cancel());
    unawaited(_stateSubscription?.cancel());
    unawaited(client.dispose());
    super.dispose();
  }
}
