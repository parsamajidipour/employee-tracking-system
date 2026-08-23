import 'dart:async';

import 'package:flutter/material.dart';

import '../models/inspection_case.dart';
import '../services/api_exception.dart';
import '../state/auth_controller.dart';
import '../theme/app_theme.dart';
import '../utils/format.dart';
import '../widgets/app_card.dart';
import '../widgets/fade_slide_in.dart';
import '../widgets/status_pill.dart';
import 'case_detail_screen.dart';

class CasesScreen extends StatefulWidget {
  const CasesScreen({super.key, required this.authController});

  final AuthController authController;

  @override
  State<CasesScreen> createState() => _CasesScreenState();
}

class _CasesScreenState extends State<CasesScreen> {
  List<InspectionCase>? _cases;
  String? _error;
  bool _loading = false;
  Timer? _ticker;

  @override
  void initState() {
    super.initState();
    _fetch();
    _ticker = Timer.periodic(const Duration(seconds: 60), (_) => _fetch());
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My cases')),
      body: SafeArea(child: _buildBody()),
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

    if (cases.isEmpty) {
      return RefreshIndicator(
        onRefresh: _fetch,
        child: ListView(
          children: const [
            SizedBox(height: 120),
            _NoCasesState(),
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
        itemCount: cases.length,
        itemBuilder: (context, index) {
          final inspectionCase = cases[index];
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

class _CaseRow extends StatelessWidget {
  const _CaseRow({required this.inspectionCase, required this.onTap});

  final InspectionCase inspectionCase;
  final VoidCallback onTap;

  StatusTone _toneFor(String status) => switch (status) {
        'pending' => StatusTone.warning,
        'accepted' => StatusTone.idle,
        'in_progress' => StatusTone.active,
        'completed' => StatusTone.active,
        'rejected' => StatusTone.danger,
        'cancelled' => StatusTone.danger,
        _ => StatusTone.idle,
      };

  String _labelFor(String status) => switch (status) {
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        _ => status,
      };

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
                label: _labelFor(inspectionCase.status),
                tone: _toneFor(inspectionCase.status),
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
  const _NoCasesState();

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
              'No cases assigned',
              style: context.text.titleMedium,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              'New assignments will show up here.',
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
