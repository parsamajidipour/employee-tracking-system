import 'dart:async';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';

import '../models/inspection_case.dart';
import '../services/api_exception.dart';
import '../state/auth_controller.dart';
import '../state/live_refresh.dart';
import '../state/live_updates.dart';
import '../theme/app_theme.dart';
import '../utils/format.dart';
import '../widgets/app_card.dart';
import '../widgets/fade_slide_in.dart';
import '../widgets/status_pill.dart';

class CaseDetailScreen extends StatefulWidget {
  const CaseDetailScreen({
    super.key,
    required this.authController,
    required this.caseId,
  });

  final AuthController authController;
  final int caseId;

  @override
  State<CaseDetailScreen> createState() => _CaseDetailScreenState();
}

class _CaseDetailScreenState extends State<CaseDetailScreen>
    with LiveRefresh<CaseDetailScreen> {
  InspectionCase? _inspectionCase;
  String? _error;
  bool _loading = false;
  bool _busy = false;
  final ImagePicker _imagePicker = ImagePicker();

  @override
  LiveUpdates get liveUpdates => widget.authController.liveUpdates;

  @override
  void onLiveUpdate() {
    _fetch();
  }

  @override
  void initState() {
    super.initState();
    startLiveRefresh();
    _fetch();
  }

  @override
  void dispose() {
    stopLiveRefresh();
    super.dispose();
  }

  Future<void> _fetch() async {
    setState(() => _loading = true);

    try {
      final inspectionCase =
          await widget.authController.caseRepository.fetchCase(widget.caseId);
      if (!mounted) return;
      setState(() {
        _inspectionCase = inspectionCase;
        _error = null;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
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

  void _showSnack(String message, {bool isError = false}) {
    if (!mounted) return;

    final colors = context.colors;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: isError ? colors.danger : null,
        duration: Duration(seconds: isError ? 6 : 3),
        content: Row(
          children: [
            Icon(
              isError ? Icons.error_outline : Icons.check_circle_outline,
              size: 18,
              color: Colors.white,
            ),
            const SizedBox(width: AppSpacing.sm),
            Expanded(child: Text(message)),
          ],
        ),
      ));
  }

  void _showError(String message) => _showSnack(message, isError: true);

  Future<void> _accept() async {
    final date = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime.now().subtract(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (date == null || !mounted) return;

    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.now(),
    );
    if (time == null || !mounted) return;

    final plannedAt = DateTime(
      date.year,
      date.month,
      date.day,
      time.hour,
      time.minute,
    );

    setState(() => _busy = true);
    try {
      final updated = await widget.authController.caseRepository
          .acceptCase(widget.caseId, plannedAt: plannedAt);
      if (!mounted) return;
      setState(() {
        _inspectionCase = updated;
        _busy = false;
      });
      _showSnack(
          'Assignment accepted. Inspection scheduled for ${formatDateTime(plannedAt)}.');
      _fetch();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError('Could not reach the server. Your change was not saved.');
    }
  }

  Future<void> _reject() async {
    final note = await _promptForNote(
      title: 'Reject case',
      confirmLabel: 'Reject',
    );
    if (note == null) return;

    setState(() => _busy = true);
    try {
      final updated = await widget.authController.caseRepository
          .rejectCase(widget.caseId, note: note);
      if (!mounted) return;
      setState(() {
        _inspectionCase = updated;
        _busy = false;
      });
      _showSnack('Assignment rejected. Management has been notified.');
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError('Could not reach the server. Your change was not saved.');
    }
  }

  Future<void> _start() async {
    setState(() => _busy = true);
    try {
      final updated =
          await widget.authController.caseRepository.startCase(widget.caseId);
      if (!mounted) return;
      setState(() {
        _inspectionCase = updated;
        _busy = false;
      });
      _showSnack('Inspection started. GPS and site photo tools are ready.');
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError('Could not reach the server. Your change was not saved.');
    }
  }

  Future<void> _complete() async {
    final note = await _promptForNote(
      title: 'Complete case',
      confirmLabel: 'Complete',
    );
    if (note == null) return;

    setState(() => _busy = true);
    try {
      final updated = await widget.authController.caseRepository
          .completeCase(widget.caseId, note: note);
      if (!mounted) return;
      setState(() {
        _inspectionCase = updated;
        _busy = false;
      });
      _showSnack('Inspection completed. Management has been notified.');
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError('Could not reach the server. Your change was not saved.');
    }
  }

  Future<String?> _promptForNote({
    required String title,
    required String confirmLabel,
  }) async {
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          maxLines: 3,
          decoration: const InputDecoration(hintText: 'Note (optional)'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () =>
                Navigator.of(dialogContext).pop(controller.text.trim()),
            child: Text(confirmLabel),
          ),
        ],
      ),
    );
  }

  Future<void> _capturePhoto() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      _showError(
          'Turn location on before taking a photo — it must be stamped with GPS.');
      return;
    }

    final permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      _showError(
          'Location permission is required before a photo can be uploaded.');
      return;
    }

    final photo = await _imagePicker.pickImage(source: ImageSource.camera);
    if (photo == null || !mounted) return;

    setState(() => _busy = true);
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 20),
        ),
      );

      final uploaded = await widget.authController.caseRepository.uploadPhoto(
        widget.caseId,
        filePath: photo.path,
        lat: position.latitude,
        lng: position.longitude,
        accuracyM: position.accuracy,
        capturedAt: DateTime.now(),
      );

      if (!mounted) return;
      setState(() => _busy = false);
      _showSnack(uploaded.isGpsVerified
          ? 'Photo uploaded — location verified'
          : 'Photo uploaded — location could not be verified, make sure GPS is on');
      _fetch();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(
          'No GPS lock. Turn on location and try again outdoors — the photo was not uploaded.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final inspectionCase = _inspectionCase;

    return Scaffold(
      appBar: AppBar(title: Text(inspectionCase?.referenceNo ?? 'Case')),
      body: SafeArea(child: _buildBody()),
    );
  }

  Widget _buildBody() {
    final inspectionCase = _inspectionCase;

    if (inspectionCase == null && _loading) {
      return const Center(child: CircularProgressIndicator(strokeWidth: 2.4));
    }

    if (inspectionCase == null) {
      return _EmptyState(message: _error, onRetry: _fetch);
    }

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
          FadeSlideIn(child: _Header(inspectionCase: inspectionCase)),
          const SizedBox(height: AppSpacing.cardGap),
          FadeSlideIn(
              index: 1, child: _DetailsCard(inspectionCase: inspectionCase)),
          const SizedBox(height: AppSpacing.cardGap),
          FadeSlideIn(
            index: 2,
            child: _ActionsCard(
              inspectionCase: inspectionCase,
              busy: _busy,
              onAccept: _accept,
              onReject: _reject,
              onStart: _start,
              onComplete: _complete,
              onCapturePhoto: _capturePhoto,
            ),
          ),
          if ((inspectionCase.statusEvents ?? []).isNotEmpty) ...[
            const SizedBox(height: AppSpacing.cardGap),
            FadeSlideIn(
              index: 3,
              child: _TimelineCard(events: inspectionCase.statusEvents!),
            ),
          ],
          if ((inspectionCase.photos ?? []).isNotEmpty) ...[
            const SizedBox(height: AppSpacing.cardGap),
            FadeSlideIn(
              index: 4,
              child: _PhotosCard(
                photos: inspectionCase.photos!,
                authController: widget.authController,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.inspectionCase});

  final InspectionCase inspectionCase;

  StatusTone _toneFor(String status) => switch (status) {
        'pending' => StatusTone.warning,
        'accepted' => StatusTone.idle,
        'overdue' => StatusTone.danger,
        'in_progress' => StatusTone.active,
        'completed' => StatusTone.active,
        'rejected' => StatusTone.danger,
        'cancelled' => StatusTone.danger,
        _ => StatusTone.idle,
      };

  String _labelFor(String status) => switch (status) {
        'pending' => 'Awaiting acceptance',
        'accepted' => 'Scheduled',
        'overdue' => 'Overdue',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        _ => status,
      };

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                inspectionCase.referenceNo.toUpperCase(),
                style: context.text.labelSmall
                    ?.copyWith(color: context.colors.primaryStrong),
              ),
            ),
            const SizedBox(width: AppSpacing.md),
            StatusPill(
              label: _labelFor(inspectionCase.status),
              tone: _toneFor(inspectionCase.status),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.md),
        Text(inspectionCase.title, style: context.text.titleLarge),
      ],
    );
  }
}

class _DetailsCard extends StatelessWidget {
  const _DetailsCard({required this.inspectionCase});

  final InspectionCase inspectionCase;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              IconTile(icon: Icons.location_on_outlined),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Property', style: context.text.labelMedium),
                    const SizedBox(height: 2),
                    Text(
                      inspectionCase.propertyAddress,
                      style: context.text.bodyLarge,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${inspectionCase.lat.toStringAsFixed(5)}, ${inspectionCase.lng.toStringAsFixed(5)}',
                      style: context.text.bodySmall,
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (inspectionCase.plannedAt != null) ...[
            const SizedBox(height: AppSpacing.lg),
            Divider(color: colors.border, height: 1),
            const SizedBox(height: AppSpacing.lg),
            _InfoRow(
              icon: Icons.event_outlined,
              label: 'Planned',
              value: formatDateTime(inspectionCase.plannedAt!),
            ),
          ],
          if (inspectionCase.notes != null &&
              inspectionCase.notes!.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.lg),
            Divider(color: colors.border, height: 1),
            const SizedBox(height: AppSpacing.lg),
            _InfoRow(
              icon: Icons.notes_outlined,
              label: 'Notes',
              value: inspectionCase.notes!,
            ),
          ],
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow(
      {required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: colors.textSecondary),
        const SizedBox(width: AppSpacing.sm),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: context.text.labelMedium),
              const SizedBox(height: 2),
              Text(value, style: context.text.bodyLarge),
            ],
          ),
        ),
      ],
    );
  }
}

class _ActionsCard extends StatelessWidget {
  const _ActionsCard({
    required this.inspectionCase,
    required this.busy,
    required this.onAccept,
    required this.onReject,
    required this.onStart,
    required this.onComplete,
    required this.onCapturePhoto,
  });

  final InspectionCase inspectionCase;
  final bool busy;
  final VoidCallback onAccept;
  final VoidCallback onReject;
  final VoidCallback onStart;
  final VoidCallback onComplete;
  final VoidCallback onCapturePhoto;

  @override
  Widget build(BuildContext context) {
    final status = inspectionCase.status;

    final widgets = <Widget>[];

    if (status == 'pending') {
      widgets.add(FilledButton(
        onPressed: busy ? null : onAccept,
        child: const Text('Accept'),
      ));
      widgets.add(const SizedBox(height: AppSpacing.sm));
      widgets.add(OutlinedButton(
        onPressed: busy ? null : onReject,
        child: const Text('Reject'),
      ));
    } else if (status == 'accepted' || status == 'overdue') {
      widgets.add(FilledButton(
        onPressed: busy ? null : onStart,
        child: const Text('Start inspection'),
      ));
    } else if (status == 'in_progress') {
      final hasVerifiedPhoto =
          inspectionCase.photos?.any((photo) => photo.isGpsVerified) ?? false;
      widgets.add(FilledButton(
        onPressed: busy ? null : onCapturePhoto,
        child: const Text('Take GPS-verified photo'),
      ));
      widgets.add(const SizedBox(height: AppSpacing.sm));
      widgets.add(OutlinedButton(
        onPressed: busy || !hasVerifiedPhoto ? null : onComplete,
        child: const Text('Complete inspection'),
      ));
      if (!hasVerifiedPhoto) {
        widgets.add(const SizedBox(height: AppSpacing.sm));
        widgets.add(Text(
          'A GPS-verified site photo is required before completion.',
          textAlign: TextAlign.center,
          style: context.text.bodySmall,
        ));
      }
    }

    if (widgets.isEmpty) {
      return AppCard(
        child: Row(
          children: [
            Icon(Icons.info_outline,
                size: 18, color: context.colors.textSecondary),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: Text(
                'No actions available for this case right now.',
                style: context.text.bodyMedium,
              ),
            ),
          ],
        ),
      );
    }

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            status == 'pending'
                ? 'Assignment response'
                : status == 'accepted' || status == 'overdue'
                    ? 'Ready for inspection'
                    : 'Site verification',
            style: context.text.titleMedium,
          ),
          const SizedBox(height: AppSpacing.lg),
          if (busy)
            const Padding(
              padding: EdgeInsets.only(bottom: AppSpacing.md),
              child: Center(
                child: SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2.2),
                ),
              ),
            ),
          ...widgets,
        ],
      ),
    );
  }
}

class _TimelineCard extends StatelessWidget {
  const _TimelineCard({required this.events});

  final List<CaseStatusEvent> events;

  String _eventTitle(CaseStatusEvent event) {
    final note = event.note?.toLowerCase() ?? '';
    if (event.toStatus == 'pending' && note.contains('assigned')) {
      return 'Surveyor assigned';
    }
    if (event.fromStatus == null && event.toStatus == 'pending') {
      return 'Case received';
    }
    return switch (event.toStatus) {
      'accepted' => 'Assignment accepted and scheduled',
      'in_progress' => 'Inspection started',
      'overdue' => 'Inspection became overdue',
      'completed' => 'Inspection completed',
      'rejected' => 'Assignment rejected',
      'cancelled' => 'Case cancelled',
      _ => 'Case updated',
    };
  }

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('History', style: context.text.titleMedium),
          const SizedBox(height: AppSpacing.lg),
          for (var i = 0; i < events.length; i++) ...[
            if (i > 0) const SizedBox(height: AppSpacing.md),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 8,
                  height: 8,
                  margin: const EdgeInsets.only(top: 6),
                  decoration: BoxDecoration(
                    color: colors.primary,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _eventTitle(events[i]),
                        style: context.text.bodyLarge,
                      ),
                      Text(
                        '${events[i].actorName} · ${formatDateTime(events[i].createdAt)}',
                        style: context.text.bodySmall,
                      ),
                      if (events[i].note != null && events[i].note!.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 2),
                          child: Text(events[i].note!,
                              style: context.text.bodyMedium),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _PhotosCard extends StatelessWidget {
  const _PhotosCard({required this.photos, required this.authController});

  final List<CasePhoto> photos;
  final AuthController authController;

  @override
  Widget build(BuildContext context) {
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Site photos', style: context.text.titleMedium),
          const SizedBox(height: AppSpacing.lg),
          Wrap(
            spacing: AppSpacing.md,
            runSpacing: AppSpacing.md,
            children: [
              for (final photo in photos)
                _PhotoThumb(photo: photo, authController: authController),
            ],
          ),
        ],
      ),
    );
  }
}

class _PhotoThumb extends StatelessWidget {
  const _PhotoThumb({required this.photo, required this.authController});

  final CasePhoto photo;
  final AuthController authController;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return SizedBox(
      width: 96,
      child: Column(
        children: [
          ClipRRect(
            borderRadius: AppRadii.smallRadius,
            child: SizedBox(
              width: 96,
              height: 96,
              child: FutureBuilder<List<int>>(
                future: authController.apiClient.getBytes(photo.url),
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done) {
                    return Container(
                      color: colors.surfaceMuted,
                      child: const Center(
                        child: SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      ),
                    );
                  }
                  if (snapshot.hasError || !snapshot.hasData) {
                    return Container(
                      color: colors.surfaceMuted,
                      child: Icon(Icons.broken_image_outlined,
                          color: colors.textTertiary),
                    );
                  }
                  return Image.memory(
                    Uint8List.fromList(snapshot.data!),
                    fit: BoxFit.cover,
                  );
                },
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.xs),
          Icon(
            photo.isGpsVerified
                ? Icons.verified_outlined
                : Icons.warning_amber_outlined,
            size: 14,
            color: photo.isGpsVerified ? colors.success : colors.warning,
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
