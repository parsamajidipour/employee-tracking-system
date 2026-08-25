import 'dart:async';
import 'dart:convert';

import 'package:web_socket_channel/web_socket_channel.dart';
import 'package:web_socket_channel/status.dart' as ws_status;

import '../config.dart';

enum RealtimeConnectionState { disconnected, connecting, connected }

class RealtimeEvent {
  final String channel;
  final String name;
  final Map<String, dynamic> payload;

  RealtimeEvent({
    required this.channel,
    required this.name,
    required this.payload,
  });

  String get type => (payload['type'] as String?) ?? name;
}

const _reconnectDelays = <Duration>[
  Duration(seconds: 1),
  Duration(seconds: 2),
  Duration(seconds: 5),
  Duration(seconds: 10),
  Duration(seconds: 20),
  Duration(seconds: 30),
];

typedef ChannelAuthorizer = Future<String?> Function(
  String socketId,
  String channelName,
);

class RealtimeClient {
  RealtimeClient({this.authorizer});

  final ChannelAuthorizer? authorizer;

  final _events = StreamController<RealtimeEvent>.broadcast();
  final _states = StreamController<RealtimeConnectionState>.broadcast();
  final Set<String> _channels = <String>{};

  String? _socketId;

  WebSocketChannel? _channel;
  StreamSubscription<dynamic>? _subscription;
  Timer? _reconnectTimer;
  Timer? _activityTimer;
  int _attempt = 0;
  bool _wantsConnection = false;

  RealtimeConnectionState _state = RealtimeConnectionState.disconnected;

  Stream<RealtimeEvent> get events => _events.stream;

  Stream<RealtimeConnectionState> get states => _states.stream;

  RealtimeConnectionState get state => _state;

  void subscribe(String channel) {
    if (!_channels.add(channel)) return;
    if (_state == RealtimeConnectionState.connected) {
      unawaited(_sendSubscribe(channel));
    }
  }

  Future<void> _sendSubscribe(String channel) async {
    final socketId = _socketId;
    final needsAuth = channel.startsWith('private-') ||
        channel.startsWith('presence-');

    String? auth;
    if (needsAuth) {
      if (socketId == null || authorizer == null) return;
      auth = await authorizer!(socketId, channel);
      if (auth == null) {
        _scheduleReconnect();
        return;
      }
    }

    _send({
      'event': 'pusher:subscribe',
      'data': {
        'channel': channel,
        if (auth != null) 'auth': auth,
      },
    });
  }

  void connect() {
    if (!realtimeConfigured) return;
    _wantsConnection = true;
    if (_channel != null) return;
    _open();
  }

  Future<void> disconnect() async {
    _wantsConnection = false;
    _reconnectTimer?.cancel();
    _activityTimer?.cancel();
    _channels.clear();
    await _teardownSocket();
    _setState(RealtimeConnectionState.disconnected);
  }

  Future<void> dispose() async {
    await disconnect();
    await _events.close();
    await _states.close();
  }

  void _open() {
    _setState(RealtimeConnectionState.connecting);

    try {
      final channel = WebSocketChannel.connect(realtimeUri());
      _channel = channel;
      _subscription = channel.stream.listen(
        _onMessage,
        onError: (_) => _scheduleReconnect(),
        onDone: _scheduleReconnect,
        cancelOnError: true,
      );
    } catch (_) {
      _scheduleReconnect();
    }
  }

  void _onMessage(dynamic raw) {
    _resetActivityTimer();

    if (raw is! String) return;

    final Map<String, dynamic> frame;
    try {
      frame = jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      return;
    }

    final name = frame['event'] as String? ?? '';

    if (name == 'pusher:connection_established') {
      _attempt = 0;
      _socketId = _decodePayload(frame['data'])['socket_id'] as String?;
      _setState(RealtimeConnectionState.connected);
      for (final channel in _channels) {
        unawaited(_sendSubscribe(channel));
      }
      return;
    }

    if (name == 'pusher:ping') {
      _send({'event': 'pusher:pong', 'data': <String, dynamic>{}});
      return;
    }

    if (name.startsWith('pusher:') || name.startsWith('pusher_internal:')) {
      return;
    }

    _events.add(RealtimeEvent(
      channel: frame['channel'] as String? ?? '',
      name: name,
      payload: _decodePayload(frame['data']),
    ));
  }

  Map<String, dynamic> _decodePayload(dynamic data) {
    if (data is Map<String, dynamic>) return data;
    if (data is String && data.isNotEmpty) {
      try {
        final decoded = jsonDecode(data);
        if (decoded is Map<String, dynamic>) return decoded;
      } catch (_) {
      }
    }
    return <String, dynamic>{};
  }

  void _send(Map<String, dynamic> frame) {
    try {
      _channel?.sink.add(jsonEncode(frame));
    } catch (_) {
    }
  }

  void _resetActivityTimer() {
    _activityTimer?.cancel();
    _activityTimer = Timer(const Duration(seconds: 45), () {
      _send({'event': 'pusher:ping', 'data': <String, dynamic>{}});
      _activityTimer = Timer(const Duration(seconds: 15), _scheduleReconnect);
    });
  }

  Future<void> _teardownSocket() async {
    final subscription = _subscription;
    final channel = _channel;
    _subscription = null;
    _channel = null;
    _socketId = null;

    await subscription?.cancel();
    try {
      await channel?.sink.close(ws_status.normalClosure);
    } catch (_) {
    }
  }

  void _scheduleReconnect() {
    if (!_wantsConnection) return;
    if (_reconnectTimer?.isActive ?? false) return;

    _activityTimer?.cancel();
    _setState(RealtimeConnectionState.disconnected);
    unawaited(_teardownSocket());

    final delay = _reconnectDelays[
        _attempt < _reconnectDelays.length ? _attempt : _reconnectDelays.length - 1];
    _attempt++;

    _reconnectTimer = Timer(delay, () {
      if (_wantsConnection) _open();
    });
  }

  void _setState(RealtimeConnectionState state) {
    if (_state == state) return;
    _state = state;
    if (!_states.isClosed) _states.add(state);
  }
}
