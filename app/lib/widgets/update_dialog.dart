import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:permission_handler/permission_handler.dart';

import '../models/app_update_info.dart';
import '../services/app_update_service.dart';
import '../theme/app_theme.dart';
import 'app_card.dart';

Future<void> showUpdateDialog(
  BuildContext context, {
  required AppUpdateInfo info,
  required AppUpdateService updateService,
}) {
  return showDialog<void>(
    context: context,
    barrierDismissible: !info.isMandatory,
    barrierColor: Colors.black.withValues(alpha: 0.82),
    builder: (context) => UpdateDialog(info: info, updateService: updateService),
  );
}

class UpdateDialog extends StatefulWidget {
  const UpdateDialog({super.key, required this.info, required this.updateService});

  final AppUpdateInfo info;
  final AppUpdateService updateService;

  @override
  State<UpdateDialog> createState() => _UpdateDialogState();
}

enum _DownloadPhase { idle, downloading, installing, error }

class _UpdateDialogState extends State<UpdateDialog> {
  _DownloadPhase _phase = _DownloadPhase.idle;
  double _progress = 0;

  Future<void> _startUpdate() async {
    setState(() {
      _phase = _DownloadPhase.downloading;
      _progress = 0;
    });

    try {
      // Requested up front so the OS settings screen (if it needs to show
      // one) happens before we try to open the APK, not in the middle of
      // it — OpenFilex's install intent can otherwise hang indefinitely
      // waiting on a permission the user was never asked for.
      final installStatus = await Permission.requestInstallPackages.request();
      if (!installStatus.isGranted) {
        if (!mounted) return;
        setState(() => _phase = _DownloadPhase.error);
        return;
      }

      final file = await widget.updateService.downloadApk(
        widget.info,
        onProgress: (progress) {
          if (!mounted) return;
          setState(() => _progress = progress);
        },
      );

      if (!mounted) return;
      setState(() => _phase = _DownloadPhase.installing);

      final result = await OpenFilex.open(file.path).timeout(
        const Duration(seconds: 20),
        onTimeout: () => OpenResult(type: ResultType.error, message: 'timed out'),
      );

      if (!mounted) return;
      if (result.type != ResultType.done) {
        setState(() => _phase = _DownloadPhase.error);
        return;
      }
      Navigator.of(context).pop();
    } catch (_) {
      if (!mounted) return;
      setState(() => _phase = _DownloadPhase.error);
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final info = widget.info;
    final downloading = _phase == _DownloadPhase.downloading;
    final installing = _phase == _DownloadPhase.installing;
    final busy = downloading || installing;
    final notes = info.releaseNotes;

    return PopScope(
      canPop: !info.isMandatory,
      child: Dialog(
        backgroundColor: Colors.transparent,
        insetPadding: const EdgeInsets.all(AppSpacing.xxl),
        child: AppCard(
          padding: const EdgeInsets.all(AppSpacing.xxl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const IconTile(icon: Icons.system_update_alt_outlined),
              const SizedBox(height: AppSpacing.lg),
              Text('Update available', style: context.text.titleLarge),
              const SizedBox(height: AppSpacing.xs),
              Text('Version ${info.versionName}', style: context.text.bodyMedium),
              if (notes != null && notes.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.lg),
                Text(notes, style: context.text.bodyLarge),
              ],
              if (_phase == _DownloadPhase.error) ...[
                const SizedBox(height: AppSpacing.lg),
                Text(
                  'Could not download the update. Check your connection (and that '
                  'installing from this app is allowed in Settings) and try again.',
                  style: context.text.bodySmall?.copyWith(color: colors.danger),
                ),
              ],
              const SizedBox(height: AppSpacing.xl),
              FilledButton(
                onPressed: busy ? null : _startUpdate,
                child: busy
                    ? Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const SizedBox(
                            height: 18,
                            width: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2.4,
                              color: Colors.white,
                            ),
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Text(
                            installing
                                ? 'Installing…'
                                : 'Downloading ${(_progress * 100).round()}%',
                          ),
                        ],
                      )
                    : Text(_phase == _DownloadPhase.error ? 'Retry' : 'Update'),
              ),
              if (!info.isMandatory) ...[
                const SizedBox(height: AppSpacing.sm),
                TextButton(
                  onPressed: busy ? null : () => Navigator.of(context).pop(),
                  child: const Text('Later'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
