import 'dart:async';

import 'package:flutter/material.dart';

import '../models/inspection_case.dart';
import '../models/permission_snapshot.dart';
import '../models/shift_window.dart';
import '../models/window_snapshot.dart';
import '../services/api_exception.dart';
import '../services/app_update_service.dart';
import '../services/auth_storage.dart';
import '../services/location_queue_repository.dart';
import '../services/permission_service.dart';
import '../services/realtime_client.dart';
import '../services/tracking_service_controller.dart';
import '../state/auth_controller.dart';
import '../state/live_refresh.dart';
import '../state/live_updates.dart';
import '../theme/app_theme.dart';
import '../utils/format.dart';
import '../widgets/app_card.dart';
import '../widgets/fade_slide_in.dart';
import '../widgets/live_dot.dart';
import '../widgets/status_pill.dart';
import '../widgets/update_dialog.dart';
import 'case_detail_screen.dart';
import 'notifications_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.authController,
    required this.trackingServiceController,
    this.onOpenCases,
  });

  final AuthController authController;
  final TrackingServiceController trackingServiceController;
  final void Function(String? statusFilter)? onOpenCases;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen>
    with WidgetsBindingObserver, LiveRefresh<HomeScreen> {
  final LocationQueueRepository _queueRepository = LocationQueueRepository();
  final AuthStorage _authStorage = AuthStorage();
  final PermissionService _permissionService = PermissionService();
  late final AppUpdateService _updateService =
      AppUpdateService(apiClient: widget.authController.apiClient);

  WindowSnapshot? _snapshot;
  String? _error;
  bool _loading = false;
  bool _checkingForUpdate = false;
  bool _updateDialogVisible = false;
  Timer? _ticker;

  bool? _serviceRunning;
  int? _queueDepth;
  DateTime? _lastUploadAt;
  PermissionSnapshot? _permissionSnapshot;
  List<InspectionCase> _cases = const [];
  String _displayName = '';

  @override
  LiveUpdates get liveUpdates => widget.authController.liveUpdates;

  @override
  void onLiveUpdate() {
    _fetchCases();
    _fetchWindow();
    _checkForUpdate();
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    startLiveRefresh();

    _ticker = Timer.periodic(const Duration(seconds: 15), (_) {
      if (mounted) setState(() {});
      _fetchWindow();
      _refreshServiceDerivedState();
    });

    _fetchWindow();
    _refreshServiceDerivedState();
    _fetchCases();
    _fetchIdentity();
    _checkForUpdate();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    stopLiveRefresh();
    _ticker?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _fetchWindow();
      _refreshServiceDerivedState();
      _fetchCases();
      _checkForUpdate();
    }
  }

  Future<void> _fetchIdentity() async {
    final identity = await widget.authController.meRepository.fetchIdentity();
    if (!mounted || identity == null) return;
    setState(() => _displayName = identity.name);
  }

  Future<void> _checkForUpdate() async {
    if (_checkingForUpdate || _updateDialogVisible) return;
    _checkingForUpdate = true;

    try {
      final info = await _updateService.checkForUpdate();
      if (info == null || !mounted) return;

      _updateDialogVisible = true;
      await showUpdateDialog(context,
          info: info, updateService: _updateService);
    } finally {
      _checkingForUpdate = false;
      _updateDialogVisible = false;
    }
  }

  Future<void> _fetchCases() async {
    try {
      final cases = await widget.authController.caseRepository.fetchCases();
      if (!mounted) return;
      setState(() => _cases = cases);
    } catch (_) {}
  }

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

  Future<void> _fetchWindow() async {
    setState(() => _loading = true);

    try {
      final snapshot = await widget.authController.meRepository.fetchWindow();
      if (!mounted) return;

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
      if (!mounted) return;
      if (e.isUnauthorized) {
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
        _error =
            'Could not reach the server, and no previous data is cached yet.';
        _loading = false;
      });
    }
  }

  Future<void> _refreshAll() => Future.wait([
        _fetchWindow(),
        _refreshServiceDerivedState(),
        _fetchCases(),
      ]);

  void _openCases([String? statusFilter]) =>
      widget.onOpenCases?.call(statusFilter);

  Future<void> _openNotifications() async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) =>
          NotificationsScreen(authController: widget.authController),
    ));
  }

  Future<void> _openCase(int id) async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => CaseDetailScreen(
        authController: widget.authController,
        caseId: id,
      ),
    ));
    _fetchCases();
  }

  int _countWhere(bool Function(InspectionCase) test) =>
      _cases.where(test).length;

  InspectionCase? get _nextVisit {
    final upcoming = _cases
        .where((c) => c.status == 'accepted' || c.status == 'in_progress')
        .toList()
      ..sort((a, b) {
        final aAt = a.plannedAt;
        final bAt = b.plannedAt;
        if (aAt == null && bAt == null) return 0;
        if (aAt == null) return 1;
        if (bAt == null) return -1;
        return aAt.compareTo(bAt);
      });

    return upcoming.isEmpty ? null : upcoming.first;
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
      return _EmptyState(message: _error, onRetry: _fetchWindow);
    }

    final permissions = _permissionSnapshot;
    final needsAttention = permissions != null && !permissions.allGranted;
    final nextVisit = _nextVisit;

    var index = 0;
    int step() => index++;

    return RefreshIndicator(
      onRefresh: _refreshAll,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.screen,
          AppSpacing.md,
          AppSpacing.screen,
          AppSpacing.huge,
        ),
        children: [
          FadeSlideIn(
            index: step(),
            child: _HomeHeader(
              name: _displayName,
              connectionState: liveUpdates.connectionState,
              unread: liveUpdates.unreadCount,
              onOpenNotifications: _openNotifications,
            ),
          ),
          const SizedBox(height: AppSpacing.xl),
          FadeSlideIn(
            index: step(),
            child: const SectionHeader(
              overline: 'Field queue',
              title: 'Your inspections',
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          if (nextVisit != null) ...[
            FadeSlideIn(
              index: step(),
              child: _NextVisitCard(
                inspectionCase: nextVisit,
                onTap: () => _openCase(nextVisit.id),
              ),
            ),
            const SizedBox(height: AppSpacing.cardGap),
          ],
          FadeSlideIn(
            index: step(),
            child: _WorkloadTiles(
              pending: _countWhere((c) => c.status == 'pending'),
              scheduled: _countWhere((c) => c.status == 'accepted'),
              inProgress: _countWhere((c) => c.status == 'in_progress'),
              onOpen: _openCases,
            ),
          ),
          const SizedBox(height: AppSpacing.xxl),
          FadeSlideIn(
            index: step(),
            child: const SectionHeader(
              overline: 'Working hours',
              title: 'Tracking status',
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          FadeSlideIn(
            index: step(),
            child: _ShiftHero(
              current: snapshot.response.current,
              next: snapshot.response.next,
              serviceRunning: _serviceRunning,
            ),
          ),
          const SizedBox(height: AppSpacing.xxl),
          FadeSlideIn(
            index: step(),
            child: const SectionHeader(
              overline: 'Device',
              title: 'Connection & sync',
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          FadeSlideIn(
            index: step(),
            child: _SyncCard(
              queueDepth: _queueDepth,
              lastUploadAt: _lastUploadAt,
              snapshot: snapshot,
              connectionState: liveUpdates.connectionState,
            ),
          ),
          if (needsAttention) ...[
            const SizedBox(height: AppSpacing.cardGap),
            FadeSlideIn(
              index: step(),
              child: _PermissionCard(
                snapshot: permissions,
                permissionService: _permissionService,
                onChanged: _refreshServiceDerivedState,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _HomeHeader extends StatelessWidget {
  const _HomeHeader({
    required this.name,
    required this.connectionState,
    required this.unread,
    required this.onOpenNotifications,
  });

  final String name;
  final RealtimeConnectionState connectionState;
  final int unread;
  final VoidCallback onOpenNotifications;

  String _greeting() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
  }

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final firstName = name.isEmpty ? '' : name.split(' ').first;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(
                    _greeting().toUpperCase(),
                    style: context.text.labelSmall?.copyWith(
                      color: colors.primaryStrong,
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  LiveDot(state: connectionState),
                ],
              ),
              const SizedBox(height: AppSpacing.xs),
              Text(
                firstName.isEmpty ? 'Field surveyor' : firstName,
                style: context.text.headlineSmall ?? context.text.titleLarge,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
        IconButton(
          onPressed: onOpenNotifications,
          tooltip: 'Notifications',
          iconSize: 26,
          constraints: const BoxConstraints(minWidth: 48, minHeight: 48),
          icon: Badge(
            isLabelVisible: unread > 0,
            label: Text('$unread'),
            child: const Icon(Icons.notifications_outlined),
          ),
        ),
      ],
    );
  }
}

class _ShiftHero extends StatelessWidget {
  const _ShiftHero({
    required this.current,
    required this.next,
    required this.serviceRunning,
  });

  final ShiftWindow? current;
  final ShiftWindow? next;
  final bool? serviceRunning;

  @override
  Widget build(BuildContext context) {
    final onShift = current != null;

    return onShift
        ? _OnShiftHero(window: current!, serviceRunning: serviceRunning)
        : _OffShiftHero(next: next);
  }
}

class _OnShiftHero extends StatelessWidget {
  const _OnShiftHero({required this.window, required this.serviceRunning});

  final ShiftWindow window;
  final bool? serviceRunning;

  @override
  Widget build(BuildContext context) {
    final total = window.end.difference(window.start).inSeconds;
    final elapsed = DateTime.now().toUtc().difference(window.start).inSeconds;
    final progress =
        total <= 0 ? 0.0 : (elapsed / total).clamp(0.0, 1.0).toDouble();
    final remaining = window.end.difference(DateTime.now().toUtc());

    return Container(
      padding: const EdgeInsets.all(AppSpacing.card),
      decoration: const BoxDecoration(
        gradient: AppColors.brandGradient,
        borderRadius: AppRadii.cardRadius,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'On shift',
                  style: context.text.titleLarge?.copyWith(color: Colors.white),
                ),
              ),
              _HeroBadge(
                label: serviceRunning == true ? 'Recording' : 'Starting…',
                icon: serviceRunning == true
                    ? Icons.fiber_manual_record
                    : Icons.hourglass_top,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(
            '${formatTime(window.start)} – ${formatTime(window.end)}',
            style: context.text.bodyLarge?.copyWith(
              color: Colors.white.withValues(alpha: 0.86),
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          ClipRRect(
            borderRadius: AppRadii.pillRadius,
            child: TweenAnimationBuilder<double>(
              tween: Tween(begin: 0, end: progress),
              duration: context.motion(AppDurations.slow),
              curve: Curves.easeOutCubic,
              builder: (context, value, _) => LinearProgressIndicator(
                value: value,
                minHeight: 8,
                backgroundColor: Colors.white.withValues(alpha: 0.24),
                valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          Row(
            children: [
              Icon(
                Icons.shield_outlined,
                size: 16,
                color: Colors.white.withValues(alpha: 0.86),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  remaining.isNegative
                      ? 'Window closing.'
                      : 'Location recorded for another ${formatDuration(remaining)}.',
                  style: context.text.bodySmall?.copyWith(
                    color: Colors.white.withValues(alpha: 0.86),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _HeroBadge extends StatelessWidget {
  const _HeroBadge({required this.label, required this.icon});

  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.2),
        borderRadius: AppRadii.pillRadius,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: Colors.white),
          const SizedBox(width: AppSpacing.xs + 2),
          Text(
            label,
            style: context.text.labelMedium?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _OffShiftHero extends StatelessWidget {
  const _OffShiftHero({required this.next});

  final ShiftWindow? next;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              IconTile(
                icon: Icons.location_off_outlined,
                color: colors.textSecondary,
                background: colors.surfaceMuted,
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Off shift', style: context.text.titleLarge),
                    const SizedBox(height: 2),
                    Text(
                      next == null
                          ? 'Nothing scheduled yet'
                          : 'Next ${formatTime(next!.start)} – ${formatTime(next!.end)}',
                      style: context.text.bodyMedium,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          Divider(color: colors.border, height: 1),
          const SizedBox(height: AppSpacing.md),
          Row(
            children: [
              Icon(Icons.shield_outlined,
                  size: 16, color: colors.textSecondary),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  next == null
                      ? 'No location is recorded outside a working window.'
                      : 'No location is recorded until then. Starts ${formatCountdown(next!.start)}.',
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

class _WorkloadTiles extends StatelessWidget {
  const _WorkloadTiles({
    required this.pending,
    required this.scheduled,
    required this.inProgress,
    required this.onOpen,
  });

  final int pending;
  final int scheduled;
  final int inProgress;
  final void Function(String? statusFilter) onOpen;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Row(
      children: [
        Expanded(
          child: _WorkloadTile(
            value: pending,
            label: 'Awaiting\nresponse',
            tint: colors.warning,
            icon: Icons.mark_email_unread_outlined,
            onTap: () => onOpen('pending'),
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: _WorkloadTile(
            value: scheduled,
            label: 'Scheduled\nvisits',
            tint: colors.primaryStrong,
            icon: Icons.event_available_outlined,
            onTap: () => onOpen('accepted'),
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: _WorkloadTile(
            value: inProgress,
            label: 'In\nprogress',
            tint: colors.success,
            icon: Icons.directions_walk,
            onTap: () => onOpen('in_progress'),
          ),
        ),
      ],
    );
  }
}

class _WorkloadTile extends StatelessWidget {
  const _WorkloadTile({
    required this.value,
    required this.label,
    required this.tint,
    required this.icon,
    required this.onTap,
  });

  final int value;
  final String label;
  final Color tint;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Material(
      color: Colors.transparent,
      borderRadius: AppRadii.controlRadius,
      child: InkWell(
        onTap: onTap,
        borderRadius: AppRadii.controlRadius,
        child: Container(
          constraints: const BoxConstraints(minHeight: 116),
          padding: const EdgeInsets.all(AppSpacing.lg),
          decoration: BoxDecoration(
            color: colors.surface,
            borderRadius: AppRadii.controlRadius,
            border: Border.all(
              color: value > 0 ? tint.withValues(alpha: 0.4) : colors.border,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, size: 18, color: tint),
              const SizedBox(height: AppSpacing.xl),
              Text(
                '$value',
                style: context.text.headlineSmall?.copyWith(
                  color: value > 0 ? colors.textPrimary : colors.textTertiary,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                label,
                style: context.text.labelMedium,
                maxLines: 2,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NextVisitCard extends StatelessWidget {
  const _NextVisitCard({required this.inspectionCase, required this.onTap});

  final InspectionCase inspectionCase;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final plannedAt = inspectionCase.plannedAt;

    return AppCard(
      onTap: onTap,
      child: Row(
        children: [
          IconTile(
            icon: inspectionCase.status == 'in_progress'
                ? Icons.play_circle_outline
                : Icons.navigation_outlined,
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  inspectionCase.status == 'in_progress'
                      ? 'ON THE JOB'
                      : 'NEXT VISIT',
                  style: context.text.labelSmall?.copyWith(
                    color: colors.primaryStrong,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  inspectionCase.title,
                  style: context.text.titleMedium,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  plannedAt == null
                      ? inspectionCase.propertyAddress
                      : '${formatDateTime(plannedAt)} · ${inspectionCase.propertyAddress}',
                  style: context.text.bodySmall,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          Icon(Icons.chevron_right, color: colors.textTertiary),
        ],
      ),
    );
  }
}

class _SyncCard extends StatelessWidget {
  const _SyncCard({
    required this.queueDepth,
    required this.lastUploadAt,
    required this.snapshot,
    required this.connectionState,
  });

  final int? queueDepth;
  final DateTime? lastUploadAt;
  final WindowSnapshot snapshot;
  final RealtimeConnectionState connectionState;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final depth = queueDepth;
    final backingUp = depth != null && depth > 50;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text('Upload queue', style: context.text.titleMedium),
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
          const SizedBox(height: AppSpacing.lg),
          Divider(color: colors.border, height: 1),
          const SizedBox(height: AppSpacing.md),
          Row(
            children: [
              LiveDot(state: connectionState, compact: true),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  switch (connectionState) {
                    RealtimeConnectionState.connected =>
                      'Live updates on. New assignments arrive instantly.',
                    RealtimeConnectionState.connecting =>
                      'Reconnecting to live updates…',
                    RealtimeConnectionState.disconnected =>
                      'Live updates offline. Checking every 45s instead.',
                  },
                  style: context.text.bodySmall,
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Row(
            children: [
              Icon(Icons.sync, size: 14, color: colors.textTertiary),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  'Schedule synced ${formatRelative(snapshot.syncedAt)}',
                  style: context.text.bodySmall
                      ?.copyWith(color: colors.textTertiary),
                ),
              ),
              if (snapshot.stale)
                const StatusPill(
                    label: 'May be stale', tone: StatusTone.warning),
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
          'Shows new assignments the moment they arrive.',
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
