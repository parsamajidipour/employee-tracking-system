import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';

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

enum _DownloadPhase { idle, downloading, error }

class _UpdateDialogState extends State<UpdateDialog> {
  _DownloadPhase _phase = _DownloadPhase.idle;

  Future<void> _startUpdate() async {
    setState(() => _phase = _DownloadPhase.downloading);

    try {
      final file = await widget.updateService.downloadApk(widget.info);
      await OpenFilex.open(file.path);
      if (!mounted) return;
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
                  'Could not download the update. Check your connection and try again.',
                  style: context.text.bodySmall?.copyWith(color: colors.danger),
                ),
              ],
              const SizedBox(height: AppSpacing.xl),
              FilledButton(
                onPressed: downloading ? null : _startUpdate,
                child: downloading
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.4,
                          color: Colors.white,
                        ),
                      )
                    : Text(_phase == _DownloadPhase.error ? 'Retry' : 'Update'),
              ),
              if (!info.isMandatory) ...[
                const SizedBox(height: AppSpacing.sm),
                TextButton(
                  onPressed: downloading ? null : () => Navigator.of(context).pop(),
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
