import 'package:flutter/material.dart';

import '../services/realtime_client.dart';
import '../theme/app_theme.dart';

class LiveDot extends StatelessWidget {
  const LiveDot({super.key, required this.state, this.compact = false});

  final RealtimeConnectionState state;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    final (tint, label) = switch (state) {
      RealtimeConnectionState.connected => (colors.success, 'Live'),
      RealtimeConnectionState.connecting => (colors.warning, 'Connecting'),
      RealtimeConnectionState.disconnected => (colors.textTertiary, 'Offline'),
    };

    final dot = Container(
      width: 8,
      height: 8,
      decoration: BoxDecoration(color: tint, shape: BoxShape.circle),
    );

    if (compact) return dot;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        dot,
        const SizedBox(width: AppSpacing.sm),
        Text(
          label,
          style: context.text.labelMedium?.copyWith(
            color: tint,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}
