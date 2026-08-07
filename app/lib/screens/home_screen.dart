import 'dart:async';

import 'package:flutter/material.dart';

import '../models/permission_snapshot.dart';
import '../models/shift_window.dart';
import '../models/window_snapshot.dart';
import '../services/api_exception.dart';
import '../services/auth_storage.dart';
import '../services/location_queue_repository.dart';
import '../services/permission_service.dart';
import '../services/tracking_service_controller.dart';
import '../state/auth_controller.dart';
import '../utils/format.dart';
import '../widgets/tracking_status_banner.dart';

class HomeScreen extends StatefulWidget {
  final AuthController authController;
  final TrackingServiceController trackingServiceController;

  const HomeScreen({
    super.key,
    required this.authController,
    required this.trackingServiceController,
  });

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with WidgetsBindingObserver {
  final LocationQueueRepository _queueRepository = LocationQueueRepository();
  final AuthStorage _authStorage = AuthStorage();
  final PermissionService _permissionService = PermissionService();

  WindowSnapshot? _snapshot;
  String? _error;
  bool _loading = false;
  Timer? _ticker;

  // Fetched alongside the window, never queried synchronously mid-build —
  // null only until the very first resolution completes.
  bool? _serviceRunning;
  int? _queueDepth;
  DateTime? _lastUploadAt;
  PermissionSnapshot? _permissionSnapshot;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // Rebuilds every 15s purely so "last synced Xm ago" stays accurate
    // without needing a refetch — no network activity, no background
    // work, just redrawing already-held state against the current clock.
    _ticker = Timer.periodic(const Duration(seconds: 15), (_) {
      if (mounted) setState(() {});
      _refreshServiceDerivedState();
    });
    _fetch();
    _refreshServiceDerivedState();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _ticker?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // SPEC's "resync on app foreground" — covered here without any
    // scheduled/background infrastructure, since this only ever fires
    // while the app is already running in the foreground process.
    if (state == AppLifecycleState.resumed) {
      _fetch();
      _refreshServiceDerivedState();
    }
  }

  /// Everything here is a local fact (service state, on-device queue,
  /// on-device last-upload timestamp, permission status) — none of it
  /// needs the network round trip _fetch() makes, so it's kept separate
  /// and safe to call on its own, more often than a full window fetch.
  Future<void> _refreshServiceDerivedState() async {
    final running = await widget.trackingServiceController.isRunning();
    final queueDepth = await _queueRepository.count();
    final lastUploadAt = await _authStorage.lastUploadAt();
    final permissionSnapshot = await _permissionService.currentSnapshot();
    if (!mounted) return;
    setState(() {
      _serviceRunning = running;
      _queueDepth = queueDepth;
      _lastUploadAt = lastUploadAt;
      _permissionSnapshot = permissionSnapshot;
    });
  }

  Future<void> _fetch() async {
    setState(() => _loading = true);
    try {
      final snapshot = await widget.authController.meRepository.fetchWindow();
      if (!mounted) return;
      setState(() {
        _snapshot = snapshot;
        _error = null;
        _loading = false;
      });
      // Deliberately skipped for a stale (cached) snapshot: acting on cached
      // data to start/stop a real service would be the same "second
      // implementation of window logic" CLAUDE.md forbids for the resolver
      // itself — this must only ever act on a response the server just gave.
      if (!snapshot.stale) {
        widget.trackingServiceController.applyWindowDecision(snapshot.response.current);
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.isUnauthorized) {
        // AuthController already flipped to signedOut and the root widget
        // is about to unmount this screen — nothing to render here.
        setState(() => _loading = false);
        return;
      }
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'Could not reach the server, and no previous data is cached yet.';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Smart Inspection')),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    final snapshot = _snapshot;

    if (snapshot == null && _loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (snapshot == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.cloud_off, size: 48, color: Colors.grey.shade600),
              const SizedBox(height: 12),
              Text(_error ?? 'Nothing to show yet.', textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton(onPressed: _fetch, child: const Text('Retry')),
            ],
          ),
        ),
      );
    }

    // Service state, not window presence (user's amendment) — a stale
    // window is informational context on _LastSyncedRow below, never a
    // reason to flip this banner. unknownOffline here means only "the
    // service-status query itself hasn't resolved yet," e.g. the very
    // first frame — not "window data might be old."
    final trackingState = switch (_serviceRunning) {
      null => TrackingDisplayState.unknownOffline,
      true => TrackingDisplayState.active,
      false => TrackingDisplayState.off,
    };

    return RefreshIndicator(
      onRefresh: () => Future.wait([_fetch(), _refreshServiceDerivedState()]),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TrackingStatusBanner(state: trackingState),
          const SizedBox(height: 16),
          _WindowCard(title: 'Current window', window: snapshot.response.current, emptyText: 'No active window right now'),
          const SizedBox(height: 12),
          _WindowCard(title: 'Next window', window: snapshot.response.next, emptyText: 'No upcoming window scheduled'),
          const SizedBox(height: 16),
          _LastSyncedRow(snapshot: snapshot),
          const SizedBox(height: 16),
          _QueueStatusCard(queueDepth: _queueDepth, lastUploadAt: _lastUploadAt),
          const SizedBox(height: 16),
          if (_permissionSnapshot != null && !_permissionSnapshot!.allGranted)
            _PermissionWarningCard(
              snapshot: _permissionSnapshot!,
              permissionService: _permissionService,
              onChanged: _refreshServiceDerivedState,
            ),
        ],
      ),
    );
  }
}

class _WindowCard extends StatelessWidget {
  final String title;
  final ShiftWindow? window;
  final String emptyText;

  const _WindowCard({required this.title, required this.window, required this.emptyText});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.labelLarge?.copyWith(color: Colors.grey.shade600)),
            const SizedBox(height: 8),
            if (window == null)
              Text(emptyText, style: TextStyle(color: Colors.grey.shade600))
            else
              Text(
                '${formatTime(window!.start)} – ${formatTime(window!.end)}',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
          ],
        ),
      ),
    );
  }
}

class _LastSyncedRow extends StatelessWidget {
  final WindowSnapshot snapshot;

  const _LastSyncedRow({required this.snapshot});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(Icons.sync, size: 16, color: Colors.grey.shade600),
        const SizedBox(width: 6),
        Text('Last synced ${formatRelative(snapshot.syncedAt)}', style: TextStyle(color: Colors.grey.shade600)),
        if (snapshot.stale) ...[
          const SizedBox(width: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
            decoration: BoxDecoration(
              color: Colors.amber.shade100,
              borderRadius: BorderRadius.circular(4),
            ),
            child: Text('possibly stale', style: TextStyle(color: Colors.amber.shade900, fontSize: 12)),
          ),
        ],
      ],
    );
  }
}

class _QueueStatusCard extends StatelessWidget {
  final int? queueDepth;
  final DateTime? lastUploadAt;

  const _QueueStatusCard({required this.queueDepth, required this.lastUploadAt});

  @override
  Widget build(BuildContext context) {
    final depth = queueDepth;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Icon(Icons.storage, color: Colors.grey.shade600),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(depth == null ? 'Queued points: —' : 'Queued points: $depth'),
                  const SizedBox(height: 2),
                  Text(
                    lastUploadAt == null
                        ? 'No upload yet'
                        : 'Last upload ${formatRelative(lastUploadAt!)}',
                    style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PermissionWarningCard extends StatelessWidget {
  final PermissionSnapshot snapshot;
  final PermissionService permissionService;
  final VoidCallback onChanged;

  const _PermissionWarningCard({
    required this.snapshot,
    required this.permissionService,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final missing = <(String, Future<void> Function())>[
      if (!snapshot.fineLocationGranted)
        ('Location', () => permissionService.requestFineLocation()),
      if (!snapshot.backgroundLocationGranted)
        ('Location in the background', () => permissionService.requestBackgroundLocation()),
      if (!snapshot.notificationsGranted)
        ('Notifications', () => permissionService.requestNotifications()),
      if (!snapshot.batteryOptimizationExempt)
        ('Battery optimisation exemption', () => permissionService.requestBatteryOptimizationExemption()),
    ];

    return Card(
      color: Colors.amber.shade50,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.warning_amber, color: Colors.amber.shade900),
                const SizedBox(width: 8),
                Text(
                  'Tracking needs attention',
                  style: TextStyle(color: Colors.amber.shade900, fontWeight: FontWeight.bold),
                ),
              ],
            ),
            const SizedBox(height: 12),
            for (final (label, request) in missing)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(
                  children: [
                    Expanded(child: Text(label)),
                    TextButton(
                      onPressed: () async {
                        await request();
                        onChanged();
                      },
                      child: const Text('Grant'),
                    ),
                  ],
                ),
              ),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: () async {
                  await permissionService.openSettings();
                  onChanged();
                },
                child: const Text('Open app settings'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
