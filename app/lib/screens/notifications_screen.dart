import 'dart:async';

import 'package:flutter/material.dart';

import '../models/inspection_case.dart';
import '../services/api_exception.dart';
import '../state/auth_controller.dart';
import '../theme/app_theme.dart';
import '../widgets/app_card.dart';
import '../widgets/fade_slide_in.dart';
import 'cases_screen.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key, required this.authController});

  final AuthController authController;

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  CaseUnseenCount? _count;
  String? _error;
  bool _loading = false;
  Timer? _ticker;

  @override
  void initState() {
    super.initState();
    _fetch();
    _ticker = Timer.periodic(const Duration(seconds: 30), (_) => _fetch());
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  Future<void> _fetch() async {
    if (!mounted) return;
    setState(() => _loading = true);
    try {
      final count = await widget.authController.caseRepository.fetchUnseenCount();
      if (!mounted) return;
      setState(() {
        _count = count;
        _error = null;
        _loading = false;
      });
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
        _error = 'Could not reach the server.';
        _loading = false;
      });
    }
  }

  Future<void> _openCases() async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => CasesScreen(authController: widget.authController),
    ));
    _fetch();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Notifications')),
      body: SafeArea(child: _buildBody()),
    );
  }

  Widget _buildBody() {
    final count = _count;

    if (count == null && _loading) {
      return const Center(child: CircularProgressIndicator(strokeWidth: 2.4));
    }

    if (count == null) {
      return _ErrorState(message: _error, onRetry: _fetch);
    }

    final hasAny = count.pending > 0 || count.unreadNotifications > 0;

    return RefreshIndicator(
      onRefresh: _fetch,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.screen,
          AppSpacing.lg,
          AppSpacing.screen,
          AppSpacing.huge,
        ),
        children: [
          if (!hasAny)
            FadeSlideIn(child: _EmptyNotifications())
          else ...[
            if (count.pending > 0)
              FadeSlideIn(
                child: _NotificationTile(
                  icon: Icons.assignment_late_outlined,
                  title: '${count.pending} case${count.pending == 1 ? '' : 's'} awaiting your response',
                  subtitle: 'Accept or reject before they fall behind schedule.',
                  onTap: _openCases,
                ),
              ),
            if (count.unreadNotifications > 0) ...[
              const SizedBox(height: AppSpacing.cardGap),
              FadeSlideIn(
                index: 1,
                child: _NotificationTile(
                  icon: Icons.notifications_active_outlined,
                  title: '${count.unreadNotifications} unread update${count.unreadNotifications == 1 ? '' : 's'}',
                  subtitle: 'New case assignments and changes.',
                  onTap: _openCases,
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return AppCard(
      onTap: onTap,
      child: Row(
        children: [
          IconTile(icon: icon, color: colors.warning, background: colors.warning.withValues(alpha: 0.12)),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: context.text.titleMedium),
                const SizedBox(height: 2),
                Text(subtitle, style: context.text.bodySmall),
              ],
            ),
          ),
          Icon(Icons.chevron_right, color: colors.textTertiary),
        ],
      ),
    );
  }
}

class _EmptyNotifications extends StatelessWidget {
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
              'New case assignments will show up here.',
              style: context.text.bodyMedium,
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String? message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xxl),
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
            Text('Nothing to show yet', style: context.text.titleMedium),
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
    );
  }
}
