import 'dart:async';

import 'package:flutter/material.dart';

import '../models/permission_snapshot.dart';
import '../models/shift_window.dart';
import '../models/window_snapshot.dart';
import '../services/api_exception.dart';
import '../services/app_update_service.dart';
import '../services/auth_storage.dart';
import '../services/location_queue_repository.dart';
import '../services/permission_service.dart';
import '../services/tracking_service_controller.dart';
import '../state/auth_controller.dart';
import '../theme/app_theme.dart';
import '../utils/format.dart';
import '../widgets/app_card.dart';
import '../widgets/fade_slide_in.dart';
import '../widgets/status_pill.dart';
import '../widgets/tracking_status_banner.dart';
import '../widgets/update_dialog.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.authController,
    required this.trackingServiceController,
  });

  final AuthController authController;
  final TrackingServiceController trackingServiceController;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with WidgetsBindingObserver {
  final LocationQueueRepository _queueRepository = LocationQueueRepository();
  final AuthStorage _authStorage = AuthStorage();
  final PermissionService _permissionService = PermissionService();
  late final AppUpdateService _updateService =
      AppUpdateService(apiClient: widget.authController.apiClient);

  WindowSnapshot? _snapshot;
  String? _error;
  bool _loading = false;
  Timer? _ticker;

  bool? _serviceRunning;
  int? _queueDepth;
  DateTime? _lastUploadAt;
  PermissionSnapshot? _permissionSnapshot;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    _ticker = Timer.periodic(const Duration(seconds: 15), (_) {
      if (mounted) {
        setState(() {});
      }
      _fetch();
      _refreshServiceDerivedState();
    });
    _fetch();
    _refreshServiceDerivedState();
    _checkForUpdate();
  }

  Future<void> _checkForUpdate() async {
    final info = await _updateService.checkForUpdate();
    if (info == null || !mounted) return;

    await showUpdateDialog(context, info: info, updateService: _updateService);
  }

  Future<void> _confirmSignOut() async {
    final confirmed = await showDialog<bool>(
      context: context,
      barrierColor: Colors.black.withValues(alpha: 0.82),
      builder: (context) => Dialog(
        backgroundColor: Colors.transparent,
        insetPadding: const EdgeInsets.all(AppSpacing.xxl),
        child: AppCard(
          padding: const EdgeInsets.all(AppSpacing.xxl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Sign out?', style: context.text.titleLarge),
              const SizedBox(height: AppSpacing.xs),
              Text(
                'Tracking stops and you will need to sign in again to resume.',
                style: context.text.bodyMedium,
              ),
              const SizedBox(height: AppSpacing.xl),
              FilledButton(
                onPressed: () => Navigator.of(context).pop(true),
                child: const Text('Sign out'),
              ),
              const SizedBox(height: AppSpacing.sm),
              TextButton(
                onPressed: () => Navigator.of(context).pop(false),
                child: const Text('Cancel'),
              ),
            ],
          ),
        ),
      ),
    );

    if (confirmed != true || !mounted) return;

    await widget.trackingServiceController.stopService();
    await widget.authController.signOut();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _ticker?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _fetch();
      _refreshServiceDerivedState();
    }
  }

  Future<void> _refreshServiceDerivedState() async {
    final running = await widget.trackingServiceController.isRunning();
    final queueDepth = await _queueRepository.count();
    final lastUploadAt = await _authStorage.lastUploadAt();
    final permissionSnapshot = await _permissionService.currentSnapshot();

    if (!mounted) {
      return;
    }

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

      if (!mounted) {
        return;
      }

      setState(() {
        _snapshot = snapshot;
        _error = null;
        _loading = false;
      });

      if (!snapshot.stale) {
        widget.trackingServiceController
            .applyWindowDecision(snapshot.response.current);
      }
    } on ApiException catch (e) {
      if (!mounted) {
        return;
      }
      if (e.isUnauthorized) {
        setState(() => _loading = false);
        return;
      }
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error =
            'Could not reach the server, and no previous data is cached yet.';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(child: _buildBody()),
    );
  }

  Widget _buildBody() {
    final snapshot = _snapshot;

    if (snapshot == null && _loading) {
      return const Center(child: CircularProgressIndicator(strokeWidth: 2.4));
    }

    if (snapshot == null) {
      return _EmptyState(message: _error, onRetry: _fetch);
    }

    final trackingState = switch (_serviceRunning) {
      null => TrackingDisplayState.unknownOffline,
      true => TrackingDisplayState.active,
      false => TrackingDisplayState.off,
    };

    final permissions = _permissionSnapshot;
    final needsAttention = permissions != null && !permissions.allGranted;

    return RefreshIndicator(
      onRefresh: () => Future.wait([_fetch(), _refreshServiceDerivedState()]),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.screen,
          AppSpacing.lg,
          AppSpacing.screen,
          AppSpacing.huge,
        ),
        children: [
          FadeSlideIn(
            child: _Greeting(snapshot: snapshot, onSignOut: _confirmSignOut),
          ),
          const SizedBox(height: AppSpacing.xl),
          FadeSlideIn(
            index: 1,
            child: TrackingStatusBanner(state: trackingState),
          ),
          const SizedBox(height: AppSpacing.cardGap),
          FadeSlideIn(
            index: 2,
            child: _TodayCard(
              current: snapshot.response.current,
              next: snapshot.response.next,
            ),
          ),
          const SizedBox(height: AppSpacing.cardGap),
          FadeSlideIn(
            index: 3,
            child: _SyncCard(
              queueDepth: _queueDepth,
              lastUploadAt: _lastUploadAt,
            ),
          ),
          if (needsAttention) ...[
            const SizedBox(height: AppSpacing.cardGap),
            FadeSlideIn(
              index: 4,
              child: _PermissionCard(
                snapshot: permissions,
                permissionService: _permissionService,
                onChanged: _refreshServiceDerivedState,
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.xl),
          FadeSlideIn(index: 5, child: _LastSyncedRow(snapshot: snapshot)),
        ],
      ),
    );
  }
}

class _Greeting extends StatelessWidget {
  const _Greeting({required this.snapshot, required this.onSignOut});

  final WindowSnapshot snapshot;
  final VoidCallback onSignOut;

  @override
  Widget build(BuildContext context) {
    final onShift = snapshot.response.current != null;
    final colors = context.colors;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'TODAY',
                style: context.text.labelSmall?.copyWith(
                  color: colors.primaryStrong,
                ),
              ),
              const SizedBox(height: AppSpacing.xs),
              Text(
                onShift ? 'On shift' : 'Off shift',
                style: context.text.titleLarge,
              ),
            ],
          ),
        ),
        StatusPill(
          label: onShift ? 'In window' : 'Outside hours',
          tone: onShift ? StatusTone.active : StatusTone.idle,
        ),
        const SizedBox(width: AppSpacing.sm),
        IconButton(
          onPressed: onSignOut,
          tooltip: 'Sign out',
          icon: Icon(Icons.logout_outlined, color: colors.textSecondary),
        ),
      ],
    );
  }
}

class _TodayCard extends StatelessWidget {
  const _TodayCard({required this.current, required this.next});

  final ShiftWindow? current;
  final ShiftWindow? next;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final window = current ?? next;
    final isCurrent = current != null;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              IconTile(
                icon: isCurrent
                    ? Icons.play_circle_outline
                    : Icons.schedule_outlined,
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      isCurrent ? 'Current window' : 'Next window',
                      style: context.text.labelMedium,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      window == null
                          ? 'Nothing scheduled'
                          : '${formatTime(window.start)} – ${formatTime(window.end)}',
                      style: context.text.titleMedium,
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (window != null) ...[
            const SizedBox(height: AppSpacing.lg),
            _WindowProgress(window: window, active: isCurrent),
          ],
          const SizedBox(height: AppSpacing.lg),
          Divider(color: colors.border, height: 1),
          const SizedBox(height: AppSpacing.md),
          Row(
            children: [
              Icon(Icons.shield_outlined, size: 18, color: colors.textSecondary),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  isCurrent
                      ? 'Your location is recorded until the window ends.'
                      : 'No location is recorded outside your window.',
                  style: context.text.bodySmall,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _WindowProgress extends StatelessWidget {
  const _WindowProgress({required this.window, required this.active});

  final ShiftWindow window;
  final bool active;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final total = window.end.difference(window.start).inSeconds;
    final elapsed = DateTime.now().toUtc().difference(window.start).inSeconds;
    final progress =
        total <= 0 ? 0.0 : (elapsed / total).clamp(0.0, 1.0).toDouble();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ClipRRect(
          borderRadius: AppRadii.pillRadius,
          child: TweenAnimationBuilder<double>(
            tween: Tween(begin: 0, end: active ? progress : 0),
            duration: context.motion(AppDurations.slow),
            curve: Curves.easeOutCubic,
            builder: (context, value, _) => LinearProgressIndicator(
              value: value,
              minHeight: 8,
              backgroundColor: colors.surfaceMuted,
              valueColor: AlwaysStoppedAnimation<Color>(colors.primary),
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          active
              ? '${(progress * 100).round()}% of today\'s window elapsed'
              : 'Starts ${formatRelative(window.start)}',
          style: context.text.bodySmall,
        ),
      ],
    );
  }
}

class _SyncCard extends StatelessWidget {
  const _SyncCard({required this.queueDepth, required this.lastUploadAt});

  final int? queueDepth;
  final DateTime? lastUploadAt;

  @override
  Widget build(BuildContext context) {
    final depth = queueDepth;
    final backingUp = depth != null && depth > 50;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text('Sync', style: context.text.titleMedium),
              ),
              StatusPill(
                label: backingUp ? 'Backing up' : 'Healthy',
                tone: backingUp ? StatusTone.warning : StatusTone.active,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          Row(
            children: [
              Expanded(
                child: _Metric(
                  label: 'Queued points',
                  value: depth == null ? '—' : '$depth',
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: _Metric(
                  label: 'Last upload',
                  value: lastUploadAt == null
                      ? 'Never'
                      : formatRelative(lastUploadAt!),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        color: colors.surfaceMuted,
        borderRadius: AppRadii.controlRadius,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: context.text.labelMedium),
          const SizedBox(height: AppSpacing.xs),
          Text(
            value,
            style: context.text.titleMedium,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

class _LastSyncedRow extends StatelessWidget {
  const _LastSyncedRow({required this.snapshot});

  final WindowSnapshot snapshot;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(Icons.sync, size: 14, color: colors.textTertiary),
        const SizedBox(width: AppSpacing.sm),
        Flexible(
          child: Text(
            'Last synced ${formatRelative(snapshot.syncedAt)}',
            style: context.text.bodySmall?.copyWith(color: colors.textTertiary),
          ),
        ),
        if (snapshot.stale) ...[
          const SizedBox(width: AppSpacing.sm),
          const StatusPill(label: 'May be stale', tone: StatusTone.warning),
        ],
      ],
    );
  }
}

class _PermissionCard extends StatelessWidget {
  const _PermissionCard({
    required this.snapshot,
    required this.permissionService,
    required this.onChanged,
  });

  final PermissionSnapshot snapshot;
  final PermissionService permissionService;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    final missing = <(String, String, Future<void> Function())>[
      if (!snapshot.fineLocationGranted)
        (
          'Location',
          'Needed to record a point at all.',
          permissionService.requestFineLocation
        ),
      if (!snapshot.backgroundLocationGranted)
        (
          'Location in the background',
          'Keeps tracking while the screen is off.',
          permissionService.requestBackgroundLocation
        ),
      if (!snapshot.notificationsGranted)
        (
          'Notifications',
          'Shows the badge while tracking is on.',
          permissionService.requestNotifications
        ),
      if (!snapshot.batteryOptimizationExempt)
        (
          'Battery optimisation',
          'Stops Android pausing the service.',
          permissionService.requestBatteryOptimizationExemption
        ),
    ];

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              IconTile(
                icon: Icons.error_outline,
                color: colors.warning,
                background: colors.warning.withValues(alpha: 0.12),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Needs attention', style: context.text.titleMedium),
                    const SizedBox(height: 2),
                    Text(
                      'Tracking cannot run reliably yet.',
                      style: context.text.bodySmall,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          for (final (label, why, request) in missing)
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.sm),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(label, style: context.text.bodyLarge),
                        Text(why, style: context.text.bodySmall),
                      ],
                    ),
                  ),
                  const SizedBox(width: AppSpacing.md),
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
          const SizedBox(height: AppSpacing.xs),
          OutlinedButton(
            onPressed: () async {
              await permissionService.openSettings();
              onChanged();
            },
            child: const Text('Open app settings'),
          ),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.message, required this.onRetry});

  final String? message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xxl),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 360),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              IconTile(
                icon: Icons.cloud_off_outlined,
                size: 64,
                color: colors.textSecondary,
                background: colors.surfaceMuted,
              ),
              const SizedBox(height: AppSpacing.lg),
              Text(
                'Nothing to show yet',
                style: context.text.titleMedium,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                message ?? 'Pull down to try again once you are back online.',
                style: context.text.bodyMedium,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: AppSpacing.xl),
              FilledButton(onPressed: onRetry, child: const Text('Retry')),
            ],
          ),
        ),
      ),
    );
  }
}
