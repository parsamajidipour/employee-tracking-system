import 'package:flutter/widgets.dart';

import 'live_updates.dart';

mixin LiveRefresh<T extends StatefulWidget> on State<T> {
  LiveUpdates get liveUpdates;

  void onLiveUpdate();

  int _seenRevision = 0;

  void startLiveRefresh() {
    _seenRevision = liveUpdates.revision;
    liveUpdates.addListener(_handleLiveUpdate);
  }

  void stopLiveRefresh() {
    liveUpdates.removeListener(_handleLiveUpdate);
  }

  void _handleLiveUpdate() {
    if (!mounted) return;

    if (liveUpdates.revision != _seenRevision) {
      _seenRevision = liveUpdates.revision;
      onLiveUpdate();
      return;
    }

    setState(() {});
  }
}
