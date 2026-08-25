import 'package:flutter/material.dart';

import '../models/app_notification.dart';
import '../models/inspection_case.dart';
import '../services/api_exception.dart';
import '../state/auth_controller.dart';
import '../state/live_refresh.dart';
import '../state/live_updates.dart';
import '../theme/app_theme.dart';
import '../utils/format.dart';
import '../widgets/app_card.dart';
import '../widgets/fade_slide_in.dart';
import '../widgets/live_dot.dart';
import 'case_detail_screen.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key, required this.authController});

  final AuthController authController;

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen>
    with LiveRefresh<NotificationsScreen> {
  CaseUnseenCount? _count;
  String? _error;

  @override
  LiveUpdates get liveUpdates => widget.authController.liveUpdates;

  @override
  void onLiveUpdate() {
    _fetchCount();
  }

  @override
  void initState() {
    super.initState();
    startLiveRefresh();
    _fetchCount();
    liveUpdates.refreshInbox();
    WidgetsBinding.instance
        .addPostFrameCallback((_) => liveUpdates.markAllRead());
  }

  @override
  void dispose() {
    stopLiveRefresh();
    super.dispose();
  }

  Future<void> _refresh() async {
    await Future.wait([liveUpdates.refreshInbox(), _fetchCount()]);
  }

  Future<void> _fetchCount() async {
    try {
      final count =
          await widget.authController.caseRepository.fetchUnseenCount();
      if (!mounted) return;
      setState(() {
        _count = count;
        _error = null;
      });
    } on ApiException catch (e) {
      if (!mounted || e.isUnauthorized) return;
      setState(() => _error = e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = 'Could not reach the server.');
    }
  }

  Future<void> _openCase(int id) async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => CaseDetailScreen(
        authController: widget.authController,
        caseId: id,
      ),
    ));
    _fetchCount();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: AppSpacing.screen),
            child: Center(child: LiveDot(state: liveUpdates.connectionState)),
          ),
        ],
      ),
      body: SafeArea(child: _buildBody()),
    );
  }

  Widget _buildBody() {
    final inbox = liveUpdates.notifications;
    final pending = _count?.pending ?? 0;

    return RefreshIndicator(
      onRefresh: _refresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.screen,
          AppSpacing.lg,
          AppSpacing.screen,
          AppSpacing.huge,
        ),
        children: [
          if (_error != null) ...[
            FadeSlideIn(child: _ErrorBanner(message: _error!)),
            const SizedBox(height: AppSpacing.cardGap),
          ],
          if (pending > 0) ...[
            FadeSlideIn(
              child: _PendingSummary(pending: pending),
            ),
            const SizedBox(height: AppSpacing.cardGap),
          ],
          if (inbox.isEmpty)
            const FadeSlideIn(child: _EmptyInbox())
          else
            for (var i = 0; i < inbox.length; i++)
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.cardGap),
                child: FadeSlideIn(
                  index: i + 1,
                  child: _NotificationTile(
                    notification: inbox[i],
                    onTap: inbox[i].caseId == null
                        ? null
                        : () => _openCase(inbox[i].caseId!),
                  ),
                ),
              ),
        ],
      ),
    );
  }
}

class _PendingSummary extends StatelessWidget {
  const _PendingSummary({required this.pending});

  final int pending;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return AppCard(
      child: Row(
        children: [
          IconTile(
            icon: Icons.assignment_late_outlined,
            color: colors.warning,
            background: colors.warning.withValues(alpha: 0.12),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '$pending case${pending == 1 ? '' : 's'} awaiting your response',
                  style: context.text.titleMedium,
                ),
                const SizedBox(height: 2),
                Text(
                  'Open the Inspections tab to accept or reject them.',
                  style: context.text.bodySmall,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({required this.notification, required this.onTap});

  final AppNotification notification;
  final VoidCallback? onTap;

  IconData get _icon => switch (notification.type) {
        'case.assigned' => Icons.assignment_ind_outlined,
        'case.created' => Icons.note_add_outlined,
        'case.cancelled' => Icons.cancel_outlined,
        _ => Icons.notifications_active_outlined,
      };

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return AppCard(
      onTap: onTap,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconTile(icon: _icon),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        notification.title,
                        style: context.text.titleMedium,
                        maxLines: 2,
                      ),
                    ),
                    if (!notification.read) ...[
                      const SizedBox(width: AppSpacing.sm),
                      Container(
                        width: 8,
                        height: 8,
                        decoration: BoxDecoration(
                          color: colors.primary,
                          shape: BoxShape.circle,
                        ),
                      ),
                    ],
                  ],
                ),
                if (notification.message.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(notification.message, style: context.text.bodySmall),
                ],
                const SizedBox(height: AppSpacing.sm),
                Text(
                  formatRelative(notification.createdAt),
                  style: context.text.labelSmall
                      ?.copyWith(color: colors.textTertiary),
                ),
              ],
            ),
          ),
          if (onTap != null)
            Icon(Icons.chevron_right, color: colors.textTertiary),
        ],
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        color: colors.danger.withValues(alpha: 0.1),
        borderRadius: AppRadii.controlRadius,
        border: Border.all(color: colors.danger.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          Icon(Icons.error_outline, size: 18, color: colors.danger),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              message,
              style: context.text.bodySmall?.copyWith(color: colors.danger),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyInbox extends StatelessWidget {
  const _EmptyInbox();

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Center(
      child: Padding(
        padding: const EdgeInsets.only(top: AppSpacing.huge),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            IconTile(
              icon: Icons.notifications_none_outlined,
              size: 64,
              color: colors.textSecondary,
              background: colors.surfaceMuted,
            ),
            const SizedBox(height: AppSpacing.lg),
            Text('You are all caught up', style: context.text.titleMedium),
            const SizedBox(height: AppSpacing.sm),
            Text(
              'New assignments and updates from the office appear here as they happen.',
              style: context.text.bodyMedium,
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}
