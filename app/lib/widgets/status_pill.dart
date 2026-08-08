import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

enum StatusTone { active, warning, danger, idle }

class StatusPill extends StatelessWidget {
  const StatusPill({
    super.key,
    required this.label,
    required this.tone,
    this.icon,
  });

  final String label;
  final StatusTone tone;
  final IconData? icon;

  Color _color(AppColors colors) => switch (tone) {
        StatusTone.active => colors.success,
        StatusTone.warning => colors.warning,
        StatusTone.danger => colors.danger,
        StatusTone.idle => colors.neutral,
      };

  IconData _icon() =>
      icon ??
      switch (tone) {
        StatusTone.active => Icons.check_circle_outline,
        StatusTone.warning => Icons.error_outline,
        StatusTone.danger => Icons.cancel_outlined,
        StatusTone.idle => Icons.pause_circle_outline,
      };

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final tint = _color(colors);

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: tint.withValues(alpha: 0.12),
        borderRadius: AppRadii.pillRadius,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(color: tint, shape: BoxShape.circle),
          ),
          const SizedBox(width: AppSpacing.sm),
          Icon(_icon(), size: 16, color: tint),
          const SizedBox(width: AppSpacing.xs + 2),
          Text(
            label,
            style: context.text.labelMedium?.copyWith(
              color: tint,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}
