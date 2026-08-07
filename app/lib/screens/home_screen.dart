import 'dart:async';

import 'package:flutter/material.dart';

import '../models/shift_window.dart';
import '../models/window_snapshot.dart';
import '../services/api_exception.dart';
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
  WindowSnapshot? _snapshot;
  String? _error;
  bool _loading = false;
  Timer? _ticker;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // Rebuilds every 15s purely so "last synced Xm ago" stays accurate
    // without needing a refetch — no network activity, no background
    // work, just redrawing already-held state against the current clock.
    _ticker = Timer.periodic(const Duration(seconds: 15), (_) {
      if (mounted) setState(() {});
    });
    _fetch();
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
    if (state == AppLifecycleState.resumed) _fetch();
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

    final trackingState = snapshot.stale
        ? TrackingDisplayState.unknownOffline
        : (snapshot.response.current != null ? TrackingDisplayState.active : TrackingDisplayState.off);

    return RefreshIndicator(
      onRefresh: _fetch,
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
