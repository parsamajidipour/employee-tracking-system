import 'dart:async';
import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import '../l10n/l10n.dart';
import '../models/inspection_case.dart';
import '../models/queued_case_photo.dart';
import 'case_location_screen.dart';
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
  List<QueuedCasePhoto> _queuedPhotos = [];
  final ImagePicker _imagePicker = ImagePicker();
  int _handledPhotoUploadRevision = 0;

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
    _handledPhotoUploadRevision = widget
            .authController.casePhotoUploadService.lastUploadEvent?.revision ??
        0;
    widget.authController.casePhotoUploadService
        .addListener(_onPhotoQueueChanged);
    _fetch();
    _loadQueuedPhotos();
  }

  @override
  void dispose() {
    stopLiveRefresh();
    widget.authController.casePhotoUploadService
        .removeListener(_onPhotoQueueChanged);
    super.dispose();
  }

  void _onPhotoQueueChanged() {
    final event = widget.authController.casePhotoUploadService.lastUploadEvent;
    if (event != null && event.revision > _handledPhotoUploadRevision) {
      _handledPhotoUploadRevision = event.revision;
      if (event.caseId == widget.caseId && !event.photo.isGpsVerified) {
        unawaited(_showPhotoOutsideCase(event.photo.distanceFromCaseM));
      }
    }
    unawaited(_loadQueuedPhotos());
    unawaited(_fetch());
  }

  Future<void> _loadQueuedPhotos() async {
    final queued = await widget.authController.casePhotoQueueRepository
        .allForCase(widget.caseId);
    if (!mounted) return;
    setState(() => _queuedPhotos = queued);
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
      if (e.isForbidden || e.isNotFound) {
        Navigator.of(context).pop();
        return;
      }
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = context.l10n.notificationsServerError;
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
    final plannedAt = await showModalBottomSheet<DateTime>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => const _SchedulePickerSheet(),
    );
    if (plannedAt == null || !mounted) return;

    setState(() => _busy = true);
    try {
      final updated = await widget.authController.caseRepository
          .acceptCase(widget.caseId, plannedAt: plannedAt);
      if (!mounted) return;
      setState(() {
        _inspectionCase = updated;
        _busy = false;
      });
      _showSnack(context.l10n.acceptScheduled(formatDateTime(
        plannedAt,
        locale: Localizations.localeOf(context).toLanguageTag(),
      )));
      _fetch();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(context.l10n.changeNotSaved);
    }
  }

  Future<void> _reject() async {
    final note = await _promptForNote(
      title: context.l10n.rejectCase,
      confirmLabel: context.l10n.reject,
    );
    if (note == null) return;

    setState(() => _busy = true);
    try {
      await widget.authController.caseRepository
          .rejectCase(widget.caseId, note: note);
      if (!mounted) return;
      liveUpdates.bumpRevision();
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(context.l10n.changeNotSaved);
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
      _showSnack(context.l10n.inspectionStarted);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(context.l10n.changeNotSaved);
    }
  }

  Future<void> _complete() async {
    final note = await _promptForNote(
      title: context.l10n.completeCase,
      confirmLabel: context.l10n.complete,
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
      _showSnack(context.l10n.completedNotice);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(context.l10n.changeNotSaved);
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
          decoration: InputDecoration(hintText: context.l10n.noteOptional),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: Text(context.l10n.cancel),
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
    final l10n = context.l10n;
    if (!await Geolocator.isLocationServiceEnabled()) {
      _showError(l10n.photoLocationOff);
      return;
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      _showError(l10n.photoLocationPermission);
      return;
    }

    final photo = await _imagePicker.pickImage(
      source: ImageSource.camera,
      imageQuality: 84,
      maxWidth: 2400,
      maxHeight: 2400,
      requestFullMetadata: false,
    );
    if (photo == null || !mounted) return;

    if (await File(photo.path).length() > 10 * 1024 * 1024) {
      _showError(l10n.photoTooLarge);
      return;
    }

    setState(() => _busy = true);
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 20),
        ),
      );

      await widget.authController.casePhotoCaptureService.enqueue(
        caseId: widget.caseId,
        tempFilePath: photo.path,
        lat: position.latitude,
        lng: position.longitude,
        accuracyM: position.accuracy,
        capturedAt: DateTime.now(),
      );

      if (!mounted) return;
      setState(() => _busy = false);
      _showSnack(l10n.photoQueued);
      _loadQueuedPhotos();
    } on TimeoutException {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(l10n.gpsFixFailed);
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(l10n.gpsFixFailed);
    }
  }

  Future<void> _discardQueuedPhoto(QueuedCasePhoto photo) async {
    await widget.authController.casePhotoQueueRepository.deleteId(photo.id!);
    try {
      final file = File(photo.filePath);
      if (await file.exists()) await file.delete();
    } catch (_) {}
    _loadQueuedPhotos();
  }

  Future<void> _showPhotoOutsideCase(double? distanceM) async {
    if (!mounted) return;

    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        icon: Icon(Icons.error_outline, color: context.colors.danger, size: 36),
        title: Text(context.l10n.photoWrongLocationTitle),
        content: Text(context.l10n.photoWrongLocation(
          distanceM?.round().toString() ?? '—',
        )),
        actions: [
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: Text(context.l10n.close),
          ),
        ],
      ),
    );
  }

  Future<void> _openMap(InspectionCase inspectionCase) async {
    final target = await showModalBottomSheet<_MapTarget>(
      context: context,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) => _MapTargetSheet(
        showAppleMaps: Platform.isIOS,
      ),
    );
    if (target == null || !mounted) return;

    if (target == _MapTarget.inApp) {
      await Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => CaseLocationScreen(inspectionCase: inspectionCase),
      ));
      return;
    }

    final lat = inspectionCase.lat;
    final lng = inspectionCase.lng;
    final uri = switch (target) {
      _MapTarget.google => Uri.parse(
          'https://www.google.com/maps/dir/?api=1&destination=$lat,$lng',
        ),
      _MapTarget.waze =>
        Uri.parse('https://waze.com/ul?ll=$lat,$lng&navigate=yes'),
      _MapTarget.openStreetMap => Uri.parse(
          'https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route=;$lat,$lng',
        ),
      _MapTarget.apple =>
        Uri.parse('https://maps.apple.com/?daddr=$lat,$lng&dirflg=d'),
      _MapTarget.inApp => throw StateError('Handled above'),
    };

    try {
      final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!opened && mounted) _showError(context.l10n.mapOpenFailed);
    } catch (_) {
      if (mounted) _showError(context.l10n.mapOpenFailed);
    }
  }

  @override
  Widget build(BuildContext context) {
    final inspectionCase = _inspectionCase;

    return Scaffold(
      appBar: AppBar(
          title: Text(inspectionCase?.referenceNo ?? context.l10n.caseLabel)),
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
              index: 1,
              child: _DetailsCard(
                inspectionCase: inspectionCase,
                onOpenMap: () => _openMap(inspectionCase),
              )),
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
          if (_queuedPhotos.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.cardGap),
            FadeSlideIn(
              index: 4,
              child: _QueuedPhotosCard(
                queuedPhotos: _queuedPhotos,
                onDiscard: _discardQueuedPhoto,
              ),
            ),
          ],
          if ((inspectionCase.photos ?? []).isNotEmpty) ...[
            const SizedBox(height: AppSpacing.cardGap),
            FadeSlideIn(
              index: 5,
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

enum _MapTarget { inApp, google, waze, openStreetMap, apple }

class _SchedulePickerSheet extends StatefulWidget {
  const _SchedulePickerSheet();

  @override
  State<_SchedulePickerSheet> createState() => _SchedulePickerSheetState();
}

class _SchedulePickerSheetState extends State<_SchedulePickerSheet> {
  late DateTime _plannedAt;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    final remainder = now.minute % 5;
    final rounded =
        now.add(Duration(minutes: remainder == 0 ? 5 : 5 - remainder));
    _plannedAt = DateTime(
      rounded.year,
      rounded.month,
      rounded.day,
      rounded.hour,
      rounded.minute,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.screen,
        0,
        AppSpacing.screen,
        AppSpacing.lg,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(context.l10n.chooseInspectionTime,
              style: context.text.titleMedium),
          SizedBox(
            height: 220,
            child: CupertinoDatePicker(
              mode: CupertinoDatePickerMode.dateAndTime,
              initialDateTime: _plannedAt,
              minimumDate: DateTime.now().subtract(const Duration(minutes: 1)),
              maximumDate: DateTime.now().add(const Duration(days: 365)),
              minuteInterval: 5,
              use24hFormat: MediaQuery.alwaysUse24HourFormatOf(context),
              onDateTimeChanged: (value) => _plannedAt = value,
            ),
          ),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: Text(context.l10n.cancel),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: FilledButton(
                  onPressed: () => Navigator.of(context).pop(_plannedAt),
                  child: Text(context.l10n.confirmTime),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _MapTargetSheet extends StatelessWidget {
  const _MapTargetSheet({required this.showAppleMaps});

  final bool showAppleMaps;

  @override
  Widget build(BuildContext context) {
    final options = <({IconData icon, String label, _MapTarget target})>[
      (
        icon: Icons.directions_outlined,
        label: context.l10n.googleMaps,
        target: _MapTarget.google,
      ),
      (
        icon: Icons.navigation_outlined,
        label: context.l10n.waze,
        target: _MapTarget.waze,
      ),
      (
        icon: Icons.public_outlined,
        label: context.l10n.openStreetMap,
        target: _MapTarget.openStreetMap,
      ),
      if (showAppleMaps)
        (
          icon: Icons.map_rounded,
          label: context.l10n.appleMaps,
          target: _MapTarget.apple,
        ),
      (
        icon: Icons.map_outlined,
        label: context.l10n.mapInApp,
        target: _MapTarget.inApp,
      ),
    ];

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.md),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.screen,
              0,
              AppSpacing.screen,
              AppSpacing.sm,
            ),
            child: Text(context.l10n.chooseMapApp,
                style: context.text.titleMedium),
          ),
          for (final option in options)
            ListTile(
              leading: Icon(option.icon),
              title: Text(option.label),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => Navigator.of(context).pop(option.target),
            ),
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

  String _labelFor(BuildContext context, String status) => switch (status) {
        'pending' => context.l10n.awaitingAcceptance,
        'accepted' => context.l10n.scheduled,
        'overdue' => context.l10n.overdue,
        'in_progress' => context.l10n.inProgress,
        'completed' => context.l10n.completed,
        'rejected' => context.l10n.rejected,
        'cancelled' => context.l10n.cancelled,
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
              label: _labelFor(context, inspectionCase.status),
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
  const _DetailsCard({
    required this.inspectionCase,
    required this.onOpenMap,
  });

  final InspectionCase inspectionCase;
  final VoidCallback onOpenMap;

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
                    Text(context.l10n.property,
                        style: context.text.labelMedium),
                    const SizedBox(height: 2),
                    Text(
                      !inspectionCase.hasPropertyAddress
                          ? context.l10n.locationNotProvided
                          : inspectionCase.propertyAddress,
                      style: context.text.bodyLarge,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: onOpenMap,
              icon: const Icon(Icons.directions_outlined),
              label: Text(context.l10n.openPropertyMap),
            ),
          ),
          if (inspectionCase.plannedAt != null) ...[
            const SizedBox(height: AppSpacing.lg),
            Divider(color: colors.border, height: 1),
            const SizedBox(height: AppSpacing.lg),
            _InfoRow(
              icon: Icons.event_outlined,
              label: context.l10n.planned,
              value: formatDateTime(inspectionCase.plannedAt!,
                  locale: Localizations.localeOf(context).toLanguageTag()),
            ),
          ],
          if (inspectionCase.notes != null &&
              inspectionCase.notes!.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.lg),
            Divider(color: colors.border, height: 1),
            const SizedBox(height: AppSpacing.lg),
            _InfoRow(
              icon: Icons.notes_outlined,
              label: context.l10n.notes,
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
        child: Text(context.l10n.accept),
      ));
      widgets.add(const SizedBox(height: AppSpacing.sm));
      widgets.add(OutlinedButton(
        onPressed: busy ? null : onReject,
        child: Text(context.l10n.reject),
      ));
    } else if (status == 'accepted' || status == 'overdue') {
      widgets.add(FilledButton(
        onPressed: busy ? null : onStart,
        child: Text(context.l10n.startInspection),
      ));
    } else if (status == 'in_progress') {
      final hasVerifiedPhoto =
          inspectionCase.photos?.any((photo) => photo.isGpsVerified) ?? false;
      widgets.add(FilledButton(
        onPressed: busy ? null : onCapturePhoto,
        child: Text(context.l10n.takeGpsPhoto),
      ));
      widgets.add(const SizedBox(height: AppSpacing.sm));
      widgets.add(OutlinedButton(
        onPressed: busy || !hasVerifiedPhoto ? null : onComplete,
        child: Text(context.l10n.completeInspection),
      ));
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
                context.l10n.noCaseActions,
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
                ? context.l10n.assignmentResponse
                : status == 'accepted' || status == 'overdue'
                    ? context.l10n.readyForInspection
                    : context.l10n.siteVerification,
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

  String _eventTitle(BuildContext context, CaseStatusEvent event) {
    final note = event.note?.toLowerCase() ?? '';
    if (event.toStatus == 'pending' && note.contains('assigned')) {
      return context.l10n.surveyorAssigned;
    }
    if (event.fromStatus == null && event.toStatus == 'pending') {
      return context.l10n.caseReceived;
    }
    return switch (event.toStatus) {
      'accepted' => context.l10n.assignmentAcceptedScheduled,
      'in_progress' => context.l10n.inspectionStarted,
      'overdue' => context.l10n.inspectionBecameOverdue,
      'completed' => context.l10n.completed,
      'rejected' => context.l10n.rejected,
      'cancelled' => context.l10n.caseCancelled,
      _ => context.l10n.caseUpdated,
    };
  }

  String? _eventNote(BuildContext context, CaseStatusEvent event) {
    final note = event.note?.trim();
    if (note == null || note.isEmpty) return null;

    final fixed = switch (note) {
      'Case created.' => context.l10n.eventCaseCreated,
      'Accepted by surveyor.' => context.l10n.eventAccepted,
      'Rejected by surveyor.' => context.l10n.eventRejected,
      'Inspection started.' => context.l10n.eventInspectionStarted,
      'Planned inspection time passed.' => context.l10n.eventOverdue,
      'Inspection completed.' => context.l10n.eventCompleted,
      'Cancelled by management.' => context.l10n.eventCancelled,
      _ => null,
    };
    if (fixed != null) return fixed;

    final reassigned =
        RegExp(r'^Reassigned from (.+) to (.+)\.$').firstMatch(note);
    if (reassigned != null) {
      return context.l10n.eventReassigned(
        reassigned.group(1)!,
        reassigned.group(2)!,
      );
    }

    final assigned = RegExp(r'^Assigned to (.+)\.$').firstMatch(note);
    if (assigned != null) {
      return context.l10n.eventAssignedTo(assigned.group(1)!);
    }

    return note;
  }

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(context.l10n.history, style: context.text.titleMedium),
          const SizedBox(height: AppSpacing.lg),
          for (var i = 0; i < events.length; i++) ...[
            if (i > 0) const SizedBox(height: AppSpacing.md),
            Builder(
              builder: (context) {
                final event = events[i];
                final note = _eventNote(context, event);

                return Row(
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
                            _eventTitle(context, event),
                            style: context.text.bodyLarge,
                          ),
                          Text(
                            '${event.actorName == 'System' ? context.l10n.system : event.actorName} · ${formatDateTime(event.createdAt, locale: Localizations.localeOf(context).toLanguageTag())}',
                            style: context.text.bodySmall,
                          ),
                          if (note != null)
                            Padding(
                              padding: const EdgeInsets.only(top: 2),
                              child: Text(
                                note,
                                style: context.text.bodyMedium,
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                );
              },
            ),
          ],
        ],
      ),
    );
  }
}

class _QueuedPhotosCard extends StatelessWidget {
  const _QueuedPhotosCard({
    required this.queuedPhotos,
    required this.onDiscard,
  });

  final List<QueuedCasePhoto> queuedPhotos;
  final void Function(QueuedCasePhoto photo) onDiscard;

  Future<void> _showFailureDialog(
    BuildContext context,
    QueuedCasePhoto photo,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.photoUploadFailed),
        content:
            Text(photo.failureReason ?? context.l10n.closedBeforePhotoUpload),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(context.l10n.keep),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(context.l10n.discard),
          ),
        ],
      ),
    );
    if (confirmed == true) onDiscard(photo);
  }

  @override
  Widget build(BuildContext context) {
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(context.l10n.uploading, style: context.text.titleMedium),
          const SizedBox(height: AppSpacing.lg),
          Wrap(
            spacing: AppSpacing.md,
            runSpacing: AppSpacing.md,
            children: [
              for (final photo in queuedPhotos)
                _QueuedPhotoThumb(
                  photo: photo,
                  onTap: photo.isFailedPermanent
                      ? () => _showFailureDialog(context, photo)
                      : null,
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _QueuedPhotoThumb extends StatelessWidget {
  const _QueuedPhotoThumb({required this.photo, this.onTap});

  final QueuedCasePhoto photo;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return GestureDetector(
      onTap: onTap,
      child: SizedBox(
        width: 96,
        child: Column(
          children: [
            ClipRRect(
              borderRadius: AppRadii.smallRadius,
              child: SizedBox(
                width: 96,
                height: 96,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    Image.file(File(photo.filePath), fit: BoxFit.cover),
                    Container(color: Colors.black.withValues(alpha: 0.25)),
                    Center(
                      child: photo.isFailedPermanent
                          ? Icon(Icons.error_outline, color: colors.danger)
                          : const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              photo.isFailedPermanent
                  ? context.l10n.photoFailedTap
                  : context.l10n.uploadingEllipsis,
              textAlign: TextAlign.center,
              style: context.text.bodySmall,
            ),
          ],
        ),
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
          Text(context.l10n.sitePhotos, style: context.text.titleMedium),
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
          if (photo.isGpsVerified)
            Icon(Icons.verified_outlined, size: 14, color: colors.success)
          else
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.error_outline, size: 14, color: colors.danger),
                const SizedBox(width: 3),
                Flexible(
                  child: Text(
                    context.l10n.photoOutsideLocation,
                    maxLines: 2,
                    textAlign: TextAlign.center,
                    style: context.text.bodySmall?.copyWith(
                      color: colors.danger,
                      fontWeight: FontWeight.w600,
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
                context.l10n.nothingToShow,
                style: context.text.titleMedium,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                message ?? context.l10n.pullDownWhenOnline,
                style: context.text.bodyMedium,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: AppSpacing.xl),
              FilledButton(onPressed: onRetry, child: Text(context.l10n.retry)),
            ],
          ),
        ),
      ),
    );
  }
}
