import 'package:flutter/material.dart';

enum TrackingDisplayState { active, off, unknownOffline }

/// The one thing this screen must never get wrong: these are
/// company-issued phones employees take home, so "off" has to be at least
/// as unmistakable as "active" — not the usual pattern where the common
/// state gets a bold treatment and the other one fades into a grey
/// afterthought. Same size, same weight, same prominence for every state
/// here; only the color and icon change.
///
/// unknownOffline exists for exactly one reason: a stale cached window
/// must never render as a confident "active" or "off" claim. Whether
/// tracking is really on or off right now is server truth this app hasn't
/// heard yet — showing anything more definite than "unknown" here would
/// be reassuring someone (or alarming them) with a guess dressed up as a
/// fact.
class TrackingStatusBanner extends StatelessWidget {
  final TrackingDisplayState state;

  const TrackingStatusBanner({super.key, required this.state});

  @override
  Widget build(BuildContext context) {
    final (color, icon, title, subtitle) = switch (state) {
      TrackingDisplayState.active => (
          Colors.green.shade700,
          Icons.location_on,
          'Tracking active',
          "You're inside your working-hours window.",
        ),
      TrackingDisplayState.off => (
          const Color(0xFF37474F), // blue-grey 800 — deliberate, not red: this is the expected state outside working hours, not an error
          Icons.location_off,
          'Tracking off',
          'Outside working hours — no location is being recorded.',
        ),
      TrackingDisplayState.unknownOffline => (
          Colors.amber.shade800,
          Icons.wifi_off,
          'Tracking state unknown',
          "Offline — can't confirm whether tracking is on or off right now.",
        ),
    };

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(12)),
      child: Row(
        children: [
          Icon(icon, color: Colors.white, size: 36),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 4),
                Text(subtitle, style: const TextStyle(color: Colors.white)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
