import 'dart:async';

import 'package:flutter/material.dart';

import '../models/inspection_case.dart';
import '../services/api_exception.dart';
import '../services/realtime_client.dart';
import '../state/auth_controller.dart';
import '../state/live_refresh.dart';
import '../state/live_updates.dart';
import '../theme/app_theme.dart';
import '../utils/format.dart';
import '../widgets/app_card.dart';
import '../widgets/fade_slide_in.dart';
import '../widgets/live_dot.dart';
import '../widgets/status_pill.dart';
import 'case_detail_screen.dart';

const _filters = <({String? value, String label})>[
  (value: null, label: 'All'),
  (value: 'pending', label: 'Pending'),
  (value: 'accepted', label: 'Scheduled'),
  (value: 'in_progress', label: 'In progress'),
  (value: 'completed', label: 'Completed'),
];

StatusTone caseStatusTone(String status) => switch (status) {
      'pending' => StatusTone.warning,
      'accepted' => StatusTone.idle,
      'in_progress' => StatusTone.active,
      'completed' => StatusTone.active,
      'rejected' => StatusTone.danger,
      'cancelled' => StatusTone.danger,
      _ => StatusTone.idle,
    };

String caseStatusLabel(String status) => switch (status) {
      'pending' => 'Pending',
      'accepted' => 'Scheduled',
      'in_progress' => 'In progress',
      'completed' => 'Completed',
      'rejected' => 'Rejected',
      'cancelled' => 'Cancelled',
      _ => status,
    };

class CasesScreen extends StatefulWidget {
  const CasesScreen({
    super.key,
    required this.authController,
    this.initialStatusFilter,
  });

  final AuthController authController;
  final String? initialStatusFilter;

  @override
  State<CasesScreen> createState() => _CasesScreenState();
}

class _CasesScreenState extends State<CasesScreen> with LiveRefresh<CasesScreen> {
  List<InspectionCase>? _cases;
  String? _error;
  bool _loading = false;
  String? _filter;
  Timer? _ticker;

  @override
  LiveUpdates get liveUpdates => widget.authController.liveUpdates;

  @override
  void onLiveUpdate() {
    _fetch();
  }

  @override
  void initState() {
    super.initState();
    _filter = widget.initialStatusFilter;
    startLiveRefresh();
    _fetch();
    _ticker = Timer.periodic(const Duration(seconds: 60), (_) => _fetch());
  }

  @override
  void didUpdateWidget(CasesScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.initialStatusFilter != oldWidget.initialStatusFilter) {
      setState(() => _filter = widget.initialStatusFilter);
    }
  }

  @override
  void dispose() {
    stopLiveRefresh();
    _ticker?.cancel();
    super.dispose();
  }

  Future<void> _fetch() async {
    if (!mounted) return;
    setState(() => _loading = true);

    try {
      final cases = await widget.authController.caseRepository.fetchCases();
      if (!mounted) return;
      setState(() {
        _cases = cases;
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

  Future<void> _openCase(InspectionCase inspectionCase) async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => CaseDetailScreen(
        authController: widget.authController,
        caseId: inspectionCase.id,
      ),
    ));
    _fetch();
  }

  int _countFor(String? status) {
    final cases = _cases;
    if (cases == null) return 0;
    if (status == null) return cases.length;
    return cases.where((c) => c.status == status).length;
  }

  @override
  Widget build(BuildContext context) {
    final connectionState = liveUpdates.connectionState;

    return Scaffold(
      appBar: AppBar(
        title: const Text('My cases'),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: AppSpacing.screen),
            child: Center(
              child: LiveDot(
                state: connectionState,
                compact: connectionState == RealtimeConnectionState.connected,
              ),
            ),
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(56),
          child: _FilterBar(
            selected: _filter,
            countFor: _countFor,
            onSelected: (value) => setState(() => _filter = value),
          ),
        ),
      ),
      body: SafeArea(top: false, child: _buildBody()),
    );
  }

  Widget _buildBody() {
    final cases = _cases;

    if (cases == null && _loading) {
      return const Center(child: CircularProgressIndicator(strokeWidth: 2.4));
    }

    if (cases == null) {
      return _EmptyState(message: _error, onRetry: _fetch);
    }

    final visible = _filter == null
        ? cases
        : cases.where((c) => c.status == _filter).toList();

    if (visible.isEmpty) {
      return RefreshIndicator(
        onRefresh: _fetch,
        child: ListView(
          children: [
            const SizedBox(height: 100),
            _NoCasesState(filtered: _filter != null),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _fetch,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.screen,
          AppSpacing.lg,
          AppSpacing.screen,
          AppSpacing.huge,
        ),
        itemCount: visible.length,
        itemBuilder: (context, index) {
          final inspectionCase = visible[index];
          return Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.cardGap),
            child: FadeSlideIn(
              index: index,
              child: _CaseRow(
                inspectionCase: inspectionCase,
                onTap: () => _openCase(inspectionCase),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _FilterBar extends StatelessWidget {
  const _FilterBar({
    required this.selected,
    required this.countFor,
    required this.onSelected,
  });

  final String? selected;
  final int Function(String?) countFor;
  final void Function(String?) onSelected;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return SizedBox(
      height: 56,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.screen,
          vertical: AppSpacing.sm,
        ),
        itemCount: _filters.length,
        separatorBuilder: (_, __) => const SizedBox(width: AppSpacing.sm),
        itemBuilder: (context, index) {
          final filter = _filters[index];
          final active = filter.value == selected;
          final count = countFor(filter.value);

          return Material(
            color: Colors.transparent,
            borderRadius: AppRadii.pillRadius,
            child: InkWell(
              onTap: () => onSelected(filter.value),
              borderRadius: AppRadii.pillRadius,
              child: Container(
                constraints: const BoxConstraints(minHeight: 40),
                padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.lg,
                  vertical: AppSpacing.sm,
                ),
                decoration: BoxDecoration(
                  color: active ? colors.primarySoft : colors.surface,
                  borderRadius: AppRadii.pillRadius,
                  border: Border.all(
                    color: active ? colors.primary : colors.border,
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      filter.label,
                      style: context.text.labelMedium?.copyWith(
                        color: active
                            ? colors.primaryStrong
                            : colors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (count > 0) ...[
                      const SizedBox(width: AppSpacing.sm),
                      Text(
                        '$count',
                        style: context.text.labelMedium?.copyWith(
                          color: active
                              ? colors.primaryStrong
                              : colors.textTertiary,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _CaseRow extends StatelessWidget {
  const _CaseRow({required this.inspectionCase, required this.onTap});

  final InspectionCase inspectionCase;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final isPriority =
        inspectionCase.priority == 'urgent' || inspectionCase.priority == 'high';

    return AppCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      inspectionCase.referenceNo,
                      style: context.text.labelSmall?.copyWith(
                        color: colors.textTertiary,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Row(
                      children: [
                        if (isPriority) ...[
                          Container(
                            width: 8,
                            height: 8,
                            decoration: BoxDecoration(
                              color: inspectionCase.priority == 'urgent'
                                  ? colors.danger
                                  : colors.warning,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: AppSpacing.sm),
                        ],
                        Expanded(
                          child: Text(
                            inspectionCase.title,
                            style: context.text.titleMedium,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      inspectionCase.propertyAddress,
                      style: context.text.bodySmall,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              StatusPill(
                label: caseStatusLabel(inspectionCase.status),
                tone: caseStatusTone(inspectionCase.status),
              ),
            ],
          ),
          if (inspectionCase.plannedAt != null) ...[
            const SizedBox(height: AppSpacing.md),
            Divider(color: colors.border, height: 1),
            const SizedBox(height: AppSpacing.md),
            Row(
              children: [
                Icon(Icons.event_outlined, size: 16, color: colors.textSecondary),
                const SizedBox(width: AppSpacing.sm),
                Text(
                  'Planned ${formatDateTime(inspectionCase.plannedAt!)}',
                  style: context.text.bodySmall,
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _NoCasesState extends StatelessWidget {
  const _NoCasesState({required this.filtered});

  final bool filtered;

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
              icon: Icons.assignment_turned_in_outlined,
              size: 64,
              color: colors.textSecondary,
              background: colors.surfaceMuted,
            ),
            const SizedBox(height: AppSpacing.lg),
            Text(
              filtered ? 'Nothing in this filter' : 'No cases assigned',
              style: context.text.titleMedium,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              filtered
                  ? 'Try another filter, or pull down to refresh.'
                  : 'New assignments arrive here the moment the office sends them.',
              style: context.text.bodyMedium,
              textAlign: TextAlign.center,
            ),
          ],
        ),
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
