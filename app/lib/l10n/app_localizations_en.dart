// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'Smart Inspection';

  @override
  String get language => 'Language';

  @override
  String get english => 'English';

  @override
  String get arabic => 'Arabic';

  @override
  String get welcomeBack => 'Welcome back';

  @override
  String get loginInstruction =>
      'Use the email or phone number your supervisor gave you.';

  @override
  String get emailOrPhone => 'Email or phone number';

  @override
  String get emailExample => 'e.g. jane@example.com';

  @override
  String get enterEmailOrPhone => 'Enter your email or phone number';

  @override
  String get password => 'Password';

  @override
  String get enterPassword => 'Enter your password';

  @override
  String get showPassword => 'Show password';

  @override
  String get hidePassword => 'Hide password';

  @override
  String get copyPassword => 'Copy password';

  @override
  String get passwordCopied => 'Password copied.';

  @override
  String get signIn => 'Sign in';

  @override
  String get serverUnreachable =>
      'Could not reach the server. Check your connection.';

  @override
  String get locationWorkingHoursOnly =>
      'Location is only recorded during your working hours.';

  @override
  String get today => 'Today';

  @override
  String get inspections => 'Inspections';

  @override
  String get me => 'Me';

  @override
  String get profile => 'Profile';

  @override
  String get fieldSurveyor => 'Field surveyor';

  @override
  String get privacy => 'Privacy';

  @override
  String get whatIsRecorded => 'What is recorded';

  @override
  String get privacyWorkingHours =>
      'Your location is recorded only during your working-hours window.';

  @override
  String get privacyOutsideWindow =>
      'Outside that window nothing is recorded, stored or shown to anyone.';

  @override
  String get privacyAudit =>
      'Every time a supervisor opens your history it is written to an audit log.';

  @override
  String get device => 'Device';

  @override
  String get thisPhone => 'This phone';

  @override
  String get liveUpdates => 'Live updates';

  @override
  String get permissions => 'Permissions';

  @override
  String get allGranted => 'All granted';

  @override
  String get actionNeeded => 'Action needed';

  @override
  String get appVersion => 'App version';

  @override
  String get deviceId => 'Device ID';

  @override
  String get openAppSettings => 'Open app settings';

  @override
  String get deviceSignInInfo =>
      'This device stays signed in. Only an administrator can release it from your account.';

  @override
  String get selectLanguage => 'Select language';

  @override
  String get retry => 'Retry';

  @override
  String get cancel => 'Cancel';

  @override
  String get close => 'Close';

  @override
  String get save => 'Save';

  @override
  String get later => 'Later';

  @override
  String get update => 'Update';

  @override
  String get loading => 'Loading…';

  @override
  String get locationPermissionError =>
      'Location access is required to use this work app.';

  @override
  String get backgroundPermissionError =>
      'Choose “Allow all the time” so tracking continues during field work.';

  @override
  String get notificationPermissionError =>
      'Notifications are required for assignments and tracking status.';

  @override
  String get batteryPermissionError =>
      'Battery exemption is required for reliable shift tracking.';

  @override
  String get location => 'Location';

  @override
  String get locationPermissionExplanation =>
      'This app records your location during working hours only, so your supervisor can see field employees on the live map. Outside working hours, nothing is recorded.';

  @override
  String get allowLocation => 'Allow location';

  @override
  String get backgroundLocation => 'Location in the background';

  @override
  String get backgroundLocationExplanation =>
      'Tracking has to keep working while the app is closed or the screen is off — the previous permission only covers while this screen is open. Android will ask again; choose \"Allow all the time\".';

  @override
  String get allowAllTheTime => 'Allow all the time';

  @override
  String get notifications => 'Notifications';

  @override
  String get notificationsExplanation =>
      'While tracking is active, a persistent notification stays visible so it\'s always obvious tracking is on — and just as obvious when it\'s off.';

  @override
  String get allowNotifications => 'Allow notifications';

  @override
  String get batteryOptimisation => 'Battery optimisation';

  @override
  String get batteryExplanation =>
      'Android can pause background work to save battery, which would stop tracking mid-shift without warning. Exempting this app keeps tracking reliable for the whole working-hours window.';

  @override
  String get exemptBattery => 'Exempt from battery optimisation';

  @override
  String stepProgress(Object current, Object total) {
    return 'STEP $current OF $total';
  }

  @override
  String get notificationsServerError => 'Could not reach the server.';

  @override
  String pendingResponse(Object count) {
    return '$count cases awaiting your response';
  }

  @override
  String get pendingResponseHint =>
      'Open the Inspections tab to accept or reject them.';

  @override
  String get allCaughtUp => 'You are all caught up';

  @override
  String get emptyNotifications =>
      'New assignments and updates from the office appear here as they happen.';

  @override
  String get propertyMap => 'Property map';

  @override
  String get fitLocations => 'Fit property and my location';

  @override
  String get turnOnGps => 'Turn on GPS to show your location.';

  @override
  String get locationPermissionRequired =>
      'Location permission is required to show where you are.';

  @override
  String get locationUnavailableMap =>
      'Your location is temporarily unavailable. The property is still shown.';

  @override
  String get property => 'Property';

  @override
  String get you => 'You';

  @override
  String get locating => 'Locating…';

  @override
  String metersAway(Object distance) {
    return '$distance m away';
  }

  @override
  String kilometersAway(Object distance) {
    return '$distance km away';
  }

  @override
  String get myCases => 'My cases';

  @override
  String get all => 'All';

  @override
  String get pending => 'Pending';

  @override
  String get scheduled => 'Scheduled';

  @override
  String get overdue => 'Overdue';

  @override
  String get inProgress => 'In progress';

  @override
  String get completed => 'Completed';

  @override
  String get rejected => 'Rejected';

  @override
  String get cancelled => 'Cancelled';

  @override
  String get awaitingAcceptance => 'Awaiting acceptance';

  @override
  String plannedAt(Object date) {
    return 'Planned $date';
  }

  @override
  String get nothingInFilter => 'Nothing in this filter';

  @override
  String get noCasesAssigned => 'No cases assigned';

  @override
  String get tryAnotherFilter => 'Try another filter, or pull down to refresh.';

  @override
  String get assignmentsAppearHere =>
      'New assignments arrive here the moment the office sends them.';

  @override
  String get nothingToShow => 'Nothing to show yet';

  @override
  String get pullDownWhenOnline =>
      'Pull down to try again once you are back online.';

  @override
  String get homeServerError =>
      'Could not reach the server, and no previous data is cached yet.';

  @override
  String get fieldQueue => 'Field queue';

  @override
  String get yourInspections => 'Your inspections';

  @override
  String get workingHours => 'Working hours';

  @override
  String get trackingStatus => 'Tracking status';

  @override
  String get connectionAndSync => 'Connection & sync';

  @override
  String get goodMorning => 'Good morning';

  @override
  String get goodAfternoon => 'Good afternoon';

  @override
  String get goodEvening => 'Good evening';

  @override
  String get onShift => 'On shift';

  @override
  String get recording => 'Recording';

  @override
  String get starting => 'Starting…';

  @override
  String get windowClosing => 'Window closing.';

  @override
  String locationRecordedFor(Object duration) {
    return 'Location recorded for another $duration.';
  }

  @override
  String get offShift => 'Off shift';

  @override
  String get nothingScheduled => 'Nothing scheduled yet';

  @override
  String nextWindow(Object end, Object start) {
    return 'Next $start – $end';
  }

  @override
  String get outsideWindowPrivacy =>
      'No location is recorded outside a working window.';

  @override
  String untilWindowPrivacy(Object countdown) {
    return 'No location is recorded until then. Starts $countdown.';
  }

  @override
  String get awaitingResponse => 'Awaiting\nresponse';

  @override
  String get scheduledVisits => 'Scheduled\nvisits';

  @override
  String get inProgressTwoLines => 'In\nprogress';

  @override
  String get onTheJob => 'ON THE JOB';

  @override
  String get nextVisit => 'NEXT VISIT';

  @override
  String get uploadQueue => 'Upload queue';

  @override
  String get backingUp => 'Backing up';

  @override
  String get healthy => 'Healthy';

  @override
  String get queuedPoints => 'Queued points';

  @override
  String get lastUpload => 'Last upload';

  @override
  String get never => 'Never';

  @override
  String get liveConnected =>
      'Live updates on. New assignments arrive instantly.';

  @override
  String get liveConnecting => 'Reconnecting to live updates…';

  @override
  String get liveOffline => 'Live updates offline. Checking every 45s instead.';

  @override
  String scheduleSynced(Object time) {
    return 'Schedule synced $time';
  }

  @override
  String get mayBeStale => 'May be stale';

  @override
  String get needsAttention => 'Needs attention';

  @override
  String get trackingUnreliable => 'Tracking cannot run reliably yet.';

  @override
  String get locationNeededWhy => 'Needed to record a point at all.';

  @override
  String get backgroundNeededWhy => 'Keeps tracking while the screen is off.';

  @override
  String get notificationsNeededWhy =>
      'Shows new assignments the moment they arrive.';

  @override
  String get batteryNeededWhy => 'Stops Android pausing the service.';

  @override
  String get grant => 'Grant';

  @override
  String get lessThanMinute => 'less than a minute';

  @override
  String minutesShort(Object count) {
    return '$count min';
  }

  @override
  String hoursShort(Object hours, Object minutes) {
    return '${hours}h ${minutes}m';
  }

  @override
  String daysShort(Object count) {
    return '${count}d';
  }

  @override
  String get now => 'now';

  @override
  String inDuration(Object duration) {
    return 'in $duration';
  }

  @override
  String get justNow => 'just now';

  @override
  String secondsAgo(Object count) {
    return '${count}s ago';
  }

  @override
  String minutesAgo(Object count) {
    return '${count}m ago';
  }

  @override
  String hoursAgo(Object count) {
    return '${count}h ago';
  }

  @override
  String get caseLabel => 'Case';

  @override
  String get changeNotSaved =>
      'Could not reach the server. Your change was not saved.';

  @override
  String acceptScheduled(Object date) {
    return 'Assignment accepted. Inspection scheduled for $date.';
  }

  @override
  String get rejectCase => 'Reject case';

  @override
  String get reject => 'Reject';

  @override
  String get rejectedNotice =>
      'Assignment rejected. Management has been notified.';

  @override
  String get inspectionStarted =>
      'Inspection started. GPS and site photo tools are ready.';

  @override
  String get completeCase => 'Complete case';

  @override
  String get complete => 'Complete';

  @override
  String get completedNotice =>
      'Inspection completed. Management has been notified.';

  @override
  String get noteOptional => 'Note (optional)';

  @override
  String get photoLocationOff =>
      'Turn location on before taking a photo — it must be stamped with GPS.';

  @override
  String get photoLocationPermission =>
      'Location permission is required before a photo can be uploaded.';

  @override
  String get photoTooLarge =>
      'The photo is larger than 10 MB. Retake it and try again.';

  @override
  String get photoQueued => 'Photo saved — uploading in the background';

  @override
  String get gpsFixFailed =>
      'Could not get a GPS fix. Move to an open area and retake.';

  @override
  String get openPropertyMap => 'Open property map';

  @override
  String get planned => 'Planned';

  @override
  String get notes => 'Notes';

  @override
  String get accept => 'Accept';

  @override
  String get startInspection => 'Start inspection';

  @override
  String get takeGpsPhoto => 'Take GPS-verified photo';

  @override
  String get completeInspection => 'Complete inspection';

  @override
  String get gpsPhotoRequired =>
      'A GPS-verified site photo is required before completion.';

  @override
  String get noCaseActions => 'No actions available for this case right now.';

  @override
  String get assignmentResponse => 'Assignment response';

  @override
  String get readyForInspection => 'Ready for inspection';

  @override
  String get siteVerification => 'Site verification';

  @override
  String get history => 'History';

  @override
  String get surveyorAssigned => 'Surveyor assigned';

  @override
  String get caseReceived => 'Case received';

  @override
  String get assignmentAcceptedScheduled => 'Assignment accepted and scheduled';

  @override
  String get inspectionBecameOverdue => 'Inspection became overdue';

  @override
  String get caseCancelled => 'Case cancelled';

  @override
  String get caseUpdated => 'Case updated';

  @override
  String get system => 'System';

  @override
  String get eventCaseCreated => 'Case created.';

  @override
  String eventAssignedTo(Object name) {
    return 'Assigned to $name.';
  }

  @override
  String eventReassigned(Object from, Object to) {
    return 'Reassigned from $from to $to.';
  }

  @override
  String get eventAccepted => 'Accepted by surveyor.';

  @override
  String get eventRejected => 'Rejected by surveyor.';

  @override
  String get eventInspectionStarted => 'Inspection started.';

  @override
  String get eventOverdue => 'Planned inspection time passed.';

  @override
  String get eventCompleted => 'Inspection completed.';

  @override
  String get eventCancelled => 'Cancelled by management.';

  @override
  String get photoUploadFailed => 'Photo could not be uploaded';

  @override
  String get closedBeforePhotoUpload =>
      'This case was closed before your photo could be uploaded.';

  @override
  String get keep => 'Keep';

  @override
  String get discard => 'Discard';

  @override
  String get uploading => 'Uploading';

  @override
  String get photoFailedTap => 'Failed — tap for details';

  @override
  String get uploadingEllipsis => 'Uploading…';

  @override
  String get sitePhotos => 'Site photos';

  @override
  String get updateAvailable => 'Update available';

  @override
  String versionLabel(Object version) {
    return 'Version $version';
  }

  @override
  String get mandatoryInstallReminder =>
      'This update is required — the app can\'t be used until it\'s installed. Tap Update and complete the install this time.';

  @override
  String get updateDownloadFailed =>
      'Could not download the update. Check your connection (and that installing from this app is allowed in Settings) and try again.';

  @override
  String get installing => 'Installing…';

  @override
  String downloadingPercent(Object percent) {
    return 'Downloading $percent%';
  }

  @override
  String get workingHoursTracking => 'Working hours tracking';

  @override
  String get live => 'Live';

  @override
  String get connecting => 'Connecting';

  @override
  String get offline => 'Offline';

  @override
  String get trackingActive => 'Tracking active';

  @override
  String get insideWorkingWindow => 'You are inside your working-hours window.';

  @override
  String get trackingOff => 'Tracking off';

  @override
  String get outsideWorkingHours =>
      'Outside working hours. No location is being recorded.';

  @override
  String get trackingUnknown => 'Tracking state unknown';

  @override
  String get trackingUnknownOffline =>
      'Offline. Cannot confirm whether tracking is on right now.';

  @override
  String get newCaseAssigned => 'New case assigned';

  @override
  String get newCaseCreated => 'New case created';

  @override
  String get caseStatusChanged => 'Case status changed';

  @override
  String get scheduleChanged => 'Your schedule changed';

  @override
  String get deviceAccessRevoked => 'Device access revoked';

  @override
  String get appUpdateAvailable => 'App update available';

  @override
  String get officeUpdate => 'Update from the office';

  @override
  String get openAppForDetails => 'Open the app for details.';

  @override
  String versionReady(Object version) {
    return 'Version $version is ready to install.';
  }

  @override
  String get shiftStarted => 'Shift started';

  @override
  String get shiftEnded => 'Shift ended';

  @override
  String get insideWindowNotice =>
      'You are now inside your working-hours window.';

  @override
  String get windowEndedNotice => 'Your working-hours window has ended.';

  @override
  String get alertsChannel => 'Alerts';

  @override
  String get alertsChannelDescription =>
      'Shift and update alerts shown when the app is not open.';

  @override
  String get trackingChannel => 'Location tracking';

  @override
  String get trackingChannelDescription =>
      'Shows when location tracking is active during working hours.';

  @override
  String get deviceDeactivated => 'This device was deactivated.';

  @override
  String get deviceDeactivatedContactAdmin =>
      'This device was deactivated. Contact your administrator to sign in again.';

  @override
  String somethingWentWrongCode(Object code) {
    return 'Something went wrong ($code).';
  }

  @override
  String get locationNotProvided => 'Location not provided';

  @override
  String get androidDevice => 'Android device';
}
