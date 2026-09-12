import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_en.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
      : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
    delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
  ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('en'),
    Locale('ar')
  ];

  /// No description provided for @appName.
  ///
  /// In en, this message translates to:
  /// **'Smart Inspection'**
  String get appName;

  /// No description provided for @language.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get language;

  /// No description provided for @english.
  ///
  /// In en, this message translates to:
  /// **'English'**
  String get english;

  /// No description provided for @arabic.
  ///
  /// In en, this message translates to:
  /// **'Arabic'**
  String get arabic;

  /// No description provided for @welcomeBack.
  ///
  /// In en, this message translates to:
  /// **'Welcome back'**
  String get welcomeBack;

  /// No description provided for @loginInstruction.
  ///
  /// In en, this message translates to:
  /// **'Use the email or phone number your supervisor gave you.'**
  String get loginInstruction;

  /// No description provided for @emailOrPhone.
  ///
  /// In en, this message translates to:
  /// **'Email or phone number'**
  String get emailOrPhone;

  /// No description provided for @emailExample.
  ///
  /// In en, this message translates to:
  /// **'e.g. jane@example.com'**
  String get emailExample;

  /// No description provided for @enterEmailOrPhone.
  ///
  /// In en, this message translates to:
  /// **'Enter your email or phone number'**
  String get enterEmailOrPhone;

  /// No description provided for @password.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get password;

  /// No description provided for @enterPassword.
  ///
  /// In en, this message translates to:
  /// **'Enter your password'**
  String get enterPassword;

  /// No description provided for @showPassword.
  ///
  /// In en, this message translates to:
  /// **'Show password'**
  String get showPassword;

  /// No description provided for @hidePassword.
  ///
  /// In en, this message translates to:
  /// **'Hide password'**
  String get hidePassword;

  /// No description provided for @copyPassword.
  ///
  /// In en, this message translates to:
  /// **'Copy password'**
  String get copyPassword;

  /// No description provided for @passwordCopied.
  ///
  /// In en, this message translates to:
  /// **'Password copied.'**
  String get passwordCopied;

  /// No description provided for @signIn.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get signIn;

  /// No description provided for @serverUnreachable.
  ///
  /// In en, this message translates to:
  /// **'Could not reach the server. Check your connection.'**
  String get serverUnreachable;

  /// No description provided for @locationWorkingHoursOnly.
  ///
  /// In en, this message translates to:
  /// **'Location is only recorded during your working hours.'**
  String get locationWorkingHoursOnly;

  /// No description provided for @today.
  ///
  /// In en, this message translates to:
  /// **'Today'**
  String get today;

  /// No description provided for @inspections.
  ///
  /// In en, this message translates to:
  /// **'Inspections'**
  String get inspections;

  /// No description provided for @me.
  ///
  /// In en, this message translates to:
  /// **'Me'**
  String get me;

  /// No description provided for @profile.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get profile;

  /// No description provided for @fieldSurveyor.
  ///
  /// In en, this message translates to:
  /// **'Field surveyor'**
  String get fieldSurveyor;

  /// No description provided for @privacy.
  ///
  /// In en, this message translates to:
  /// **'Privacy'**
  String get privacy;

  /// No description provided for @whatIsRecorded.
  ///
  /// In en, this message translates to:
  /// **'What is recorded'**
  String get whatIsRecorded;

  /// No description provided for @privacyWorkingHours.
  ///
  /// In en, this message translates to:
  /// **'Your location is recorded only during your working-hours window.'**
  String get privacyWorkingHours;

  /// No description provided for @privacyOutsideWindow.
  ///
  /// In en, this message translates to:
  /// **'Outside that window nothing is recorded, stored or shown to anyone.'**
  String get privacyOutsideWindow;

  /// No description provided for @privacyAudit.
  ///
  /// In en, this message translates to:
  /// **'Every time a supervisor opens your history it is written to an audit log.'**
  String get privacyAudit;

  /// No description provided for @device.
  ///
  /// In en, this message translates to:
  /// **'Device'**
  String get device;

  /// No description provided for @thisPhone.
  ///
  /// In en, this message translates to:
  /// **'This phone'**
  String get thisPhone;

  /// No description provided for @liveUpdates.
  ///
  /// In en, this message translates to:
  /// **'Live updates'**
  String get liveUpdates;

  /// No description provided for @permissions.
  ///
  /// In en, this message translates to:
  /// **'Permissions'**
  String get permissions;

  /// No description provided for @allGranted.
  ///
  /// In en, this message translates to:
  /// **'All granted'**
  String get allGranted;

  /// No description provided for @actionNeeded.
  ///
  /// In en, this message translates to:
  /// **'Action needed'**
  String get actionNeeded;

  /// No description provided for @appVersion.
  ///
  /// In en, this message translates to:
  /// **'App version'**
  String get appVersion;

  /// No description provided for @deviceId.
  ///
  /// In en, this message translates to:
  /// **'Device ID'**
  String get deviceId;

  /// No description provided for @openAppSettings.
  ///
  /// In en, this message translates to:
  /// **'Open app settings'**
  String get openAppSettings;

  /// No description provided for @deviceSignInInfo.
  ///
  /// In en, this message translates to:
  /// **'This device stays signed in. Only an administrator can release it from your account.'**
  String get deviceSignInInfo;

  /// No description provided for @selectLanguage.
  ///
  /// In en, this message translates to:
  /// **'Select language'**
  String get selectLanguage;

  /// No description provided for @retry.
  ///
  /// In en, this message translates to:
  /// **'Retry'**
  String get retry;

  /// No description provided for @cancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get cancel;

  /// No description provided for @close.
  ///
  /// In en, this message translates to:
  /// **'Close'**
  String get close;

  /// No description provided for @save.
  ///
  /// In en, this message translates to:
  /// **'Save'**
  String get save;

  /// No description provided for @later.
  ///
  /// In en, this message translates to:
  /// **'Later'**
  String get later;

  /// No description provided for @update.
  ///
  /// In en, this message translates to:
  /// **'Update'**
  String get update;

  /// No description provided for @loading.
  ///
  /// In en, this message translates to:
  /// **'Loading…'**
  String get loading;

  /// No description provided for @locationPermissionError.
  ///
  /// In en, this message translates to:
  /// **'Location access is required to use this work app.'**
  String get locationPermissionError;

  /// No description provided for @backgroundPermissionError.
  ///
  /// In en, this message translates to:
  /// **'Choose “Allow all the time” so tracking continues during field work.'**
  String get backgroundPermissionError;

  /// No description provided for @notificationPermissionError.
  ///
  /// In en, this message translates to:
  /// **'Notifications are required for assignments and tracking status.'**
  String get notificationPermissionError;

  /// No description provided for @batteryPermissionError.
  ///
  /// In en, this message translates to:
  /// **'Battery exemption is required for reliable shift tracking.'**
  String get batteryPermissionError;

  /// No description provided for @location.
  ///
  /// In en, this message translates to:
  /// **'Location'**
  String get location;

  /// No description provided for @locationPermissionExplanation.
  ///
  /// In en, this message translates to:
  /// **'This app records your location during working hours only, so your supervisor can see field employees on the live map. Outside working hours, nothing is recorded.'**
  String get locationPermissionExplanation;

  /// No description provided for @allowLocation.
  ///
  /// In en, this message translates to:
  /// **'Allow location'**
  String get allowLocation;

  /// No description provided for @backgroundLocation.
  ///
  /// In en, this message translates to:
  /// **'Location in the background'**
  String get backgroundLocation;

  /// No description provided for @backgroundLocationExplanation.
  ///
  /// In en, this message translates to:
  /// **'Tracking has to keep working while the app is closed or the screen is off — the previous permission only covers while this screen is open. Android will ask again; choose \"Allow all the time\".'**
  String get backgroundLocationExplanation;

  /// No description provided for @allowAllTheTime.
  ///
  /// In en, this message translates to:
  /// **'Allow all the time'**
  String get allowAllTheTime;

  /// No description provided for @notifications.
  ///
  /// In en, this message translates to:
  /// **'Notifications'**
  String get notifications;

  /// No description provided for @notificationsExplanation.
  ///
  /// In en, this message translates to:
  /// **'While tracking is active, a persistent notification stays visible so it\'s always obvious tracking is on — and just as obvious when it\'s off.'**
  String get notificationsExplanation;

  /// No description provided for @allowNotifications.
  ///
  /// In en, this message translates to:
  /// **'Allow notifications'**
  String get allowNotifications;

  /// No description provided for @batteryOptimisation.
  ///
  /// In en, this message translates to:
  /// **'Battery optimisation'**
  String get batteryOptimisation;

  /// No description provided for @batteryExplanation.
  ///
  /// In en, this message translates to:
  /// **'Android can pause background work to save battery, which would stop tracking mid-shift without warning. Exempting this app keeps tracking reliable for the whole working-hours window.'**
  String get batteryExplanation;

  /// No description provided for @exemptBattery.
  ///
  /// In en, this message translates to:
  /// **'Exempt from battery optimisation'**
  String get exemptBattery;

  /// No description provided for @stepProgress.
  ///
  /// In en, this message translates to:
  /// **'STEP {current} OF {total}'**
  String stepProgress(Object current, Object total);

  /// No description provided for @notificationsServerError.
  ///
  /// In en, this message translates to:
  /// **'Could not reach the server.'**
  String get notificationsServerError;

  /// No description provided for @pendingResponse.
  ///
  /// In en, this message translates to:
  /// **'{count} cases awaiting your response'**
  String pendingResponse(Object count);

  /// No description provided for @pendingResponseHint.
  ///
  /// In en, this message translates to:
  /// **'Open the Inspections tab to accept or reject them.'**
  String get pendingResponseHint;

  /// No description provided for @allCaughtUp.
  ///
  /// In en, this message translates to:
  /// **'You are all caught up'**
  String get allCaughtUp;

  /// No description provided for @emptyNotifications.
  ///
  /// In en, this message translates to:
  /// **'New assignments and updates from the office appear here as they happen.'**
  String get emptyNotifications;

  /// No description provided for @propertyMap.
  ///
  /// In en, this message translates to:
  /// **'Property map'**
  String get propertyMap;

  /// No description provided for @fitLocations.
  ///
  /// In en, this message translates to:
  /// **'Fit property and my location'**
  String get fitLocations;

  /// No description provided for @turnOnGps.
  ///
  /// In en, this message translates to:
  /// **'Turn on GPS to show your location.'**
  String get turnOnGps;

  /// No description provided for @locationPermissionRequired.
  ///
  /// In en, this message translates to:
  /// **'Location permission is required to show where you are.'**
  String get locationPermissionRequired;

  /// No description provided for @locationUnavailableMap.
  ///
  /// In en, this message translates to:
  /// **'Your location is temporarily unavailable. The property is still shown.'**
  String get locationUnavailableMap;

  /// No description provided for @property.
  ///
  /// In en, this message translates to:
  /// **'Property'**
  String get property;

  /// No description provided for @you.
  ///
  /// In en, this message translates to:
  /// **'You'**
  String get you;

  /// No description provided for @locating.
  ///
  /// In en, this message translates to:
  /// **'Locating…'**
  String get locating;

  /// No description provided for @metersAway.
  ///
  /// In en, this message translates to:
  /// **'{distance} m away'**
  String metersAway(Object distance);

  /// No description provided for @kilometersAway.
  ///
  /// In en, this message translates to:
  /// **'{distance} km away'**
  String kilometersAway(Object distance);

  /// No description provided for @myCases.
  ///
  /// In en, this message translates to:
  /// **'My cases'**
  String get myCases;

  /// No description provided for @all.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get all;

  /// No description provided for @pending.
  ///
  /// In en, this message translates to:
  /// **'Pending'**
  String get pending;

  /// No description provided for @scheduled.
  ///
  /// In en, this message translates to:
  /// **'Scheduled'**
  String get scheduled;

  /// No description provided for @overdue.
  ///
  /// In en, this message translates to:
  /// **'Overdue'**
  String get overdue;

  /// No description provided for @inProgress.
  ///
  /// In en, this message translates to:
  /// **'In progress'**
  String get inProgress;

  /// No description provided for @completed.
  ///
  /// In en, this message translates to:
  /// **'Completed'**
  String get completed;

  /// No description provided for @rejected.
  ///
  /// In en, this message translates to:
  /// **'Rejected'**
  String get rejected;

  /// No description provided for @cancelled.
  ///
  /// In en, this message translates to:
  /// **'Cancelled'**
  String get cancelled;

  /// No description provided for @awaitingAcceptance.
  ///
  /// In en, this message translates to:
  /// **'Awaiting acceptance'**
  String get awaitingAcceptance;

  /// No description provided for @plannedAt.
  ///
  /// In en, this message translates to:
  /// **'Planned {date}'**
  String plannedAt(Object date);

  /// No description provided for @nothingInFilter.
  ///
  /// In en, this message translates to:
  /// **'Nothing in this filter'**
  String get nothingInFilter;

  /// No description provided for @noCasesAssigned.
  ///
  /// In en, this message translates to:
  /// **'No cases assigned'**
  String get noCasesAssigned;

  /// No description provided for @tryAnotherFilter.
  ///
  /// In en, this message translates to:
  /// **'Try another filter, or pull down to refresh.'**
  String get tryAnotherFilter;

  /// No description provided for @assignmentsAppearHere.
  ///
  /// In en, this message translates to:
  /// **'New assignments arrive here the moment the office sends them.'**
  String get assignmentsAppearHere;

  /// No description provided for @nothingToShow.
  ///
  /// In en, this message translates to:
  /// **'Nothing to show yet'**
  String get nothingToShow;

  /// No description provided for @pullDownWhenOnline.
  ///
  /// In en, this message translates to:
  /// **'Pull down to try again once you are back online.'**
  String get pullDownWhenOnline;

  /// No description provided for @homeServerError.
  ///
  /// In en, this message translates to:
  /// **'Could not reach the server, and no previous data is cached yet.'**
  String get homeServerError;

  /// No description provided for @fieldQueue.
  ///
  /// In en, this message translates to:
  /// **'Field queue'**
  String get fieldQueue;

  /// No description provided for @yourInspections.
  ///
  /// In en, this message translates to:
  /// **'Your inspections'**
  String get yourInspections;

  /// No description provided for @workingHours.
  ///
  /// In en, this message translates to:
  /// **'Working hours'**
  String get workingHours;

  /// No description provided for @trackingStatus.
  ///
  /// In en, this message translates to:
  /// **'Tracking status'**
  String get trackingStatus;

  /// No description provided for @connectionAndSync.
  ///
  /// In en, this message translates to:
  /// **'Connection & sync'**
  String get connectionAndSync;

  /// No description provided for @goodMorning.
  ///
  /// In en, this message translates to:
  /// **'Good morning'**
  String get goodMorning;

  /// No description provided for @goodAfternoon.
  ///
  /// In en, this message translates to:
  /// **'Good afternoon'**
  String get goodAfternoon;

  /// No description provided for @goodEvening.
  ///
  /// In en, this message translates to:
  /// **'Good evening'**
  String get goodEvening;

  /// No description provided for @onShift.
  ///
  /// In en, this message translates to:
  /// **'On shift'**
  String get onShift;

  /// No description provided for @recording.
  ///
  /// In en, this message translates to:
  /// **'Recording'**
  String get recording;

  /// No description provided for @starting.
  ///
  /// In en, this message translates to:
  /// **'Starting…'**
  String get starting;

  /// No description provided for @windowClosing.
  ///
  /// In en, this message translates to:
  /// **'Window closing.'**
  String get windowClosing;

  /// No description provided for @locationRecordedFor.
  ///
  /// In en, this message translates to:
  /// **'Location recorded for another {duration}.'**
  String locationRecordedFor(Object duration);

  /// No description provided for @offShift.
  ///
  /// In en, this message translates to:
  /// **'Off shift'**
  String get offShift;

  /// No description provided for @nothingScheduled.
  ///
  /// In en, this message translates to:
  /// **'Nothing scheduled yet'**
  String get nothingScheduled;

  /// No description provided for @nextWindow.
  ///
  /// In en, this message translates to:
  /// **'Next {start} – {end}'**
  String nextWindow(Object end, Object start);

  /// No description provided for @outsideWindowPrivacy.
  ///
  /// In en, this message translates to:
  /// **'No location is recorded outside a working window.'**
  String get outsideWindowPrivacy;

  /// No description provided for @untilWindowPrivacy.
  ///
  /// In en, this message translates to:
  /// **'No location is recorded until then. Starts {countdown}.'**
  String untilWindowPrivacy(Object countdown);

  /// No description provided for @awaitingResponse.
  ///
  /// In en, this message translates to:
  /// **'Awaiting\nresponse'**
  String get awaitingResponse;

  /// No description provided for @scheduledVisits.
  ///
  /// In en, this message translates to:
  /// **'Scheduled\nvisits'**
  String get scheduledVisits;

  /// No description provided for @inProgressTwoLines.
  ///
  /// In en, this message translates to:
  /// **'In\nprogress'**
  String get inProgressTwoLines;

  /// No description provided for @onTheJob.
  ///
  /// In en, this message translates to:
  /// **'ON THE JOB'**
  String get onTheJob;

  /// No description provided for @nextVisit.
  ///
  /// In en, this message translates to:
  /// **'NEXT VISIT'**
  String get nextVisit;

  /// No description provided for @uploadQueue.
  ///
  /// In en, this message translates to:
  /// **'Upload queue'**
  String get uploadQueue;

  /// No description provided for @backingUp.
  ///
  /// In en, this message translates to:
  /// **'Backing up'**
  String get backingUp;

  /// No description provided for @healthy.
  ///
  /// In en, this message translates to:
  /// **'Healthy'**
  String get healthy;

  /// No description provided for @queuedPoints.
  ///
  /// In en, this message translates to:
  /// **'Queued points'**
  String get queuedPoints;

  /// No description provided for @lastUpload.
  ///
  /// In en, this message translates to:
  /// **'Last upload'**
  String get lastUpload;

  /// No description provided for @never.
  ///
  /// In en, this message translates to:
  /// **'Never'**
  String get never;

  /// No description provided for @liveConnected.
  ///
  /// In en, this message translates to:
  /// **'Live updates on. New assignments arrive instantly.'**
  String get liveConnected;

  /// No description provided for @liveConnecting.
  ///
  /// In en, this message translates to:
  /// **'Reconnecting to live updates…'**
  String get liveConnecting;

  /// No description provided for @liveOffline.
  ///
  /// In en, this message translates to:
  /// **'Live updates offline. Checking every 45s instead.'**
  String get liveOffline;

  /// No description provided for @scheduleSynced.
  ///
  /// In en, this message translates to:
  /// **'Schedule synced {time}'**
  String scheduleSynced(Object time);

  /// No description provided for @mayBeStale.
  ///
  /// In en, this message translates to:
  /// **'May be stale'**
  String get mayBeStale;

  /// No description provided for @needsAttention.
  ///
  /// In en, this message translates to:
  /// **'Needs attention'**
  String get needsAttention;

  /// No description provided for @trackingUnreliable.
  ///
  /// In en, this message translates to:
  /// **'Tracking cannot run reliably yet.'**
  String get trackingUnreliable;

  /// No description provided for @locationNeededWhy.
  ///
  /// In en, this message translates to:
  /// **'Needed to record a point at all.'**
  String get locationNeededWhy;

  /// No description provided for @backgroundNeededWhy.
  ///
  /// In en, this message translates to:
  /// **'Keeps tracking while the screen is off.'**
  String get backgroundNeededWhy;

  /// No description provided for @notificationsNeededWhy.
  ///
  /// In en, this message translates to:
  /// **'Shows new assignments the moment they arrive.'**
  String get notificationsNeededWhy;

  /// No description provided for @batteryNeededWhy.
  ///
  /// In en, this message translates to:
  /// **'Stops Android pausing the service.'**
  String get batteryNeededWhy;

  /// No description provided for @grant.
  ///
  /// In en, this message translates to:
  /// **'Grant'**
  String get grant;

  /// No description provided for @lessThanMinute.
  ///
  /// In en, this message translates to:
  /// **'less than a minute'**
  String get lessThanMinute;

  /// No description provided for @minutesShort.
  ///
  /// In en, this message translates to:
  /// **'{count} min'**
  String minutesShort(Object count);

  /// No description provided for @hoursShort.
  ///
  /// In en, this message translates to:
  /// **'{hours}h {minutes}m'**
  String hoursShort(Object hours, Object minutes);

  /// No description provided for @daysShort.
  ///
  /// In en, this message translates to:
  /// **'{count}d'**
  String daysShort(Object count);

  /// No description provided for @now.
  ///
  /// In en, this message translates to:
  /// **'now'**
  String get now;

  /// No description provided for @inDuration.
  ///
  /// In en, this message translates to:
  /// **'in {duration}'**
  String inDuration(Object duration);

  /// No description provided for @justNow.
  ///
  /// In en, this message translates to:
  /// **'just now'**
  String get justNow;

  /// No description provided for @secondsAgo.
  ///
  /// In en, this message translates to:
  /// **'{count}s ago'**
  String secondsAgo(Object count);

  /// No description provided for @minutesAgo.
  ///
  /// In en, this message translates to:
  /// **'{count}m ago'**
  String minutesAgo(Object count);

  /// No description provided for @hoursAgo.
  ///
  /// In en, this message translates to:
  /// **'{count}h ago'**
  String hoursAgo(Object count);

  /// No description provided for @caseLabel.
  ///
  /// In en, this message translates to:
  /// **'Case'**
  String get caseLabel;

  /// No description provided for @changeNotSaved.
  ///
  /// In en, this message translates to:
  /// **'Could not reach the server. Your change was not saved.'**
  String get changeNotSaved;

  /// No description provided for @acceptScheduled.
  ///
  /// In en, this message translates to:
  /// **'Assignment accepted. Inspection scheduled for {date}.'**
  String acceptScheduled(Object date);

  /// No description provided for @rejectCase.
  ///
  /// In en, this message translates to:
  /// **'Reject case'**
  String get rejectCase;

  /// No description provided for @reject.
  ///
  /// In en, this message translates to:
  /// **'Reject'**
  String get reject;

  /// No description provided for @rejectedNotice.
  ///
  /// In en, this message translates to:
  /// **'Assignment rejected. Management has been notified.'**
  String get rejectedNotice;

  /// No description provided for @inspectionStarted.
  ///
  /// In en, this message translates to:
  /// **'Inspection started. GPS and site photo tools are ready.'**
  String get inspectionStarted;

  /// No description provided for @completeCase.
  ///
  /// In en, this message translates to:
  /// **'Complete case'**
  String get completeCase;

  /// No description provided for @complete.
  ///
  /// In en, this message translates to:
  /// **'Complete'**
  String get complete;

  /// No description provided for @completedNotice.
  ///
  /// In en, this message translates to:
  /// **'Inspection completed. Management has been notified.'**
  String get completedNotice;

  /// No description provided for @noteOptional.
  ///
  /// In en, this message translates to:
  /// **'Note (optional)'**
  String get noteOptional;

  /// No description provided for @photoLocationOff.
  ///
  /// In en, this message translates to:
  /// **'Turn location on before taking a photo — it must be stamped with GPS.'**
  String get photoLocationOff;

  /// No description provided for @photoLocationPermission.
  ///
  /// In en, this message translates to:
  /// **'Location permission is required before a photo can be uploaded.'**
  String get photoLocationPermission;

  /// No description provided for @photoTooLarge.
  ///
  /// In en, this message translates to:
  /// **'The photo is larger than 10 MB. Retake it and try again.'**
  String get photoTooLarge;

  /// No description provided for @photoQueued.
  ///
  /// In en, this message translates to:
  /// **'Photo saved — uploading in the background'**
  String get photoQueued;

  /// No description provided for @gpsFixFailed.
  ///
  /// In en, this message translates to:
  /// **'Could not get a GPS fix. Move to an open area and retake.'**
  String get gpsFixFailed;

  /// No description provided for @openPropertyMap.
  ///
  /// In en, this message translates to:
  /// **'Open property map'**
  String get openPropertyMap;

  /// No description provided for @planned.
  ///
  /// In en, this message translates to:
  /// **'Planned'**
  String get planned;

  /// No description provided for @notes.
  ///
  /// In en, this message translates to:
  /// **'Notes'**
  String get notes;

  /// No description provided for @accept.
  ///
  /// In en, this message translates to:
  /// **'Accept'**
  String get accept;

  /// No description provided for @startInspection.
  ///
  /// In en, this message translates to:
  /// **'Start inspection'**
  String get startInspection;

  /// No description provided for @takeGpsPhoto.
  ///
  /// In en, this message translates to:
  /// **'Take GPS-verified photo'**
  String get takeGpsPhoto;

  /// No description provided for @completeInspection.
  ///
  /// In en, this message translates to:
  /// **'Complete inspection'**
  String get completeInspection;

  /// No description provided for @gpsPhotoRequired.
  ///
  /// In en, this message translates to:
  /// **'A GPS-verified site photo is required before completion.'**
  String get gpsPhotoRequired;

  /// No description provided for @noCaseActions.
  ///
  /// In en, this message translates to:
  /// **'No actions available for this case right now.'**
  String get noCaseActions;

  /// No description provided for @assignmentResponse.
  ///
  /// In en, this message translates to:
  /// **'Assignment response'**
  String get assignmentResponse;

  /// No description provided for @readyForInspection.
  ///
  /// In en, this message translates to:
  /// **'Ready for inspection'**
  String get readyForInspection;

  /// No description provided for @siteVerification.
  ///
  /// In en, this message translates to:
  /// **'Site verification'**
  String get siteVerification;

  /// No description provided for @history.
  ///
  /// In en, this message translates to:
  /// **'History'**
  String get history;

  /// No description provided for @surveyorAssigned.
  ///
  /// In en, this message translates to:
  /// **'Surveyor assigned'**
  String get surveyorAssigned;

  /// No description provided for @caseReceived.
  ///
  /// In en, this message translates to:
  /// **'Case received'**
  String get caseReceived;

  /// No description provided for @assignmentAcceptedScheduled.
  ///
  /// In en, this message translates to:
  /// **'Assignment accepted and scheduled'**
  String get assignmentAcceptedScheduled;

  /// No description provided for @inspectionBecameOverdue.
  ///
  /// In en, this message translates to:
  /// **'Inspection became overdue'**
  String get inspectionBecameOverdue;

  /// No description provided for @caseCancelled.
  ///
  /// In en, this message translates to:
  /// **'Case cancelled'**
  String get caseCancelled;

  /// No description provided for @caseUpdated.
  ///
  /// In en, this message translates to:
  /// **'Case updated'**
  String get caseUpdated;

  /// No description provided for @system.
  ///
  /// In en, this message translates to:
  /// **'System'**
  String get system;

  /// No description provided for @eventCaseCreated.
  ///
  /// In en, this message translates to:
  /// **'Case created.'**
  String get eventCaseCreated;

  /// No description provided for @eventAssignedTo.
  ///
  /// In en, this message translates to:
  /// **'Assigned to {name}.'**
  String eventAssignedTo(Object name);

  /// No description provided for @eventReassigned.
  ///
  /// In en, this message translates to:
  /// **'Reassigned from {from} to {to}.'**
  String eventReassigned(Object from, Object to);

  /// No description provided for @eventAccepted.
  ///
  /// In en, this message translates to:
  /// **'Accepted by surveyor.'**
  String get eventAccepted;

  /// No description provided for @eventRejected.
  ///
  /// In en, this message translates to:
  /// **'Rejected by surveyor.'**
  String get eventRejected;

  /// No description provided for @eventInspectionStarted.
  ///
  /// In en, this message translates to:
  /// **'Inspection started.'**
  String get eventInspectionStarted;

  /// No description provided for @eventOverdue.
  ///
  /// In en, this message translates to:
  /// **'Planned inspection time passed.'**
  String get eventOverdue;

  /// No description provided for @eventCompleted.
  ///
  /// In en, this message translates to:
  /// **'Inspection completed.'**
  String get eventCompleted;

  /// No description provided for @eventCancelled.
  ///
  /// In en, this message translates to:
  /// **'Cancelled by management.'**
  String get eventCancelled;

  /// No description provided for @photoUploadFailed.
  ///
  /// In en, this message translates to:
  /// **'Photo could not be uploaded'**
  String get photoUploadFailed;

  /// No description provided for @closedBeforePhotoUpload.
  ///
  /// In en, this message translates to:
  /// **'This case was closed before your photo could be uploaded.'**
  String get closedBeforePhotoUpload;

  /// No description provided for @keep.
  ///
  /// In en, this message translates to:
  /// **'Keep'**
  String get keep;

  /// No description provided for @discard.
  ///
  /// In en, this message translates to:
  /// **'Discard'**
  String get discard;

  /// No description provided for @uploading.
  ///
  /// In en, this message translates to:
  /// **'Uploading'**
  String get uploading;

  /// No description provided for @photoFailedTap.
  ///
  /// In en, this message translates to:
  /// **'Failed — tap for details'**
  String get photoFailedTap;

  /// No description provided for @uploadingEllipsis.
  ///
  /// In en, this message translates to:
  /// **'Uploading…'**
  String get uploadingEllipsis;

  /// No description provided for @sitePhotos.
  ///
  /// In en, this message translates to:
  /// **'Site photos'**
  String get sitePhotos;

  /// No description provided for @updateAvailable.
  ///
  /// In en, this message translates to:
  /// **'Update available'**
  String get updateAvailable;

  /// No description provided for @versionLabel.
  ///
  /// In en, this message translates to:
  /// **'Version {version}'**
  String versionLabel(Object version);

  /// No description provided for @mandatoryInstallReminder.
  ///
  /// In en, this message translates to:
  /// **'This update is required — the app can\'t be used until it\'s installed. Tap Update and complete the install this time.'**
  String get mandatoryInstallReminder;

  /// No description provided for @updateDownloadFailed.
  ///
  /// In en, this message translates to:
  /// **'Could not download the update. Check your connection (and that installing from this app is allowed in Settings) and try again.'**
  String get updateDownloadFailed;

  /// No description provided for @installing.
  ///
  /// In en, this message translates to:
  /// **'Installing…'**
  String get installing;

  /// No description provided for @downloadingPercent.
  ///
  /// In en, this message translates to:
  /// **'Downloading {percent}%'**
  String downloadingPercent(Object percent);

  /// No description provided for @workingHoursTracking.
  ///
  /// In en, this message translates to:
  /// **'Working hours tracking'**
  String get workingHoursTracking;

  /// No description provided for @live.
  ///
  /// In en, this message translates to:
  /// **'Live'**
  String get live;

  /// No description provided for @connecting.
  ///
  /// In en, this message translates to:
  /// **'Connecting'**
  String get connecting;

  /// No description provided for @offline.
  ///
  /// In en, this message translates to:
  /// **'Offline'**
  String get offline;

  /// No description provided for @trackingActive.
  ///
  /// In en, this message translates to:
  /// **'Tracking active'**
  String get trackingActive;

  /// No description provided for @insideWorkingWindow.
  ///
  /// In en, this message translates to:
  /// **'You are inside your working-hours window.'**
  String get insideWorkingWindow;

  /// No description provided for @trackingOff.
  ///
  /// In en, this message translates to:
  /// **'Tracking off'**
  String get trackingOff;

  /// No description provided for @outsideWorkingHours.
  ///
  /// In en, this message translates to:
  /// **'Outside working hours. No location is being recorded.'**
  String get outsideWorkingHours;

  /// No description provided for @trackingUnknown.
  ///
  /// In en, this message translates to:
  /// **'Tracking state unknown'**
  String get trackingUnknown;

  /// No description provided for @trackingUnknownOffline.
  ///
  /// In en, this message translates to:
  /// **'Offline. Cannot confirm whether tracking is on right now.'**
  String get trackingUnknownOffline;

  /// No description provided for @newCaseAssigned.
  ///
  /// In en, this message translates to:
  /// **'New case assigned'**
  String get newCaseAssigned;

  /// No description provided for @newCaseCreated.
  ///
  /// In en, this message translates to:
  /// **'New case created'**
  String get newCaseCreated;

  /// No description provided for @caseStatusChanged.
  ///
  /// In en, this message translates to:
  /// **'Case status changed'**
  String get caseStatusChanged;

  /// No description provided for @scheduleChanged.
  ///
  /// In en, this message translates to:
  /// **'Your schedule changed'**
  String get scheduleChanged;

  /// No description provided for @deviceAccessRevoked.
  ///
  /// In en, this message translates to:
  /// **'Device access revoked'**
  String get deviceAccessRevoked;

  /// No description provided for @appUpdateAvailable.
  ///
  /// In en, this message translates to:
  /// **'App update available'**
  String get appUpdateAvailable;

  /// No description provided for @officeUpdate.
  ///
  /// In en, this message translates to:
  /// **'Update from the office'**
  String get officeUpdate;

  /// No description provided for @openAppForDetails.
  ///
  /// In en, this message translates to:
  /// **'Open the app for details.'**
  String get openAppForDetails;

  /// No description provided for @versionReady.
  ///
  /// In en, this message translates to:
  /// **'Version {version} is ready to install.'**
  String versionReady(Object version);

  /// No description provided for @shiftStarted.
  ///
  /// In en, this message translates to:
  /// **'Shift started'**
  String get shiftStarted;

  /// No description provided for @shiftEnded.
  ///
  /// In en, this message translates to:
  /// **'Shift ended'**
  String get shiftEnded;

  /// No description provided for @insideWindowNotice.
  ///
  /// In en, this message translates to:
  /// **'You are now inside your working-hours window.'**
  String get insideWindowNotice;

  /// No description provided for @windowEndedNotice.
  ///
  /// In en, this message translates to:
  /// **'Your working-hours window has ended.'**
  String get windowEndedNotice;

  /// No description provided for @alertsChannel.
  ///
  /// In en, this message translates to:
  /// **'Alerts'**
  String get alertsChannel;

  /// No description provided for @alertsChannelDescription.
  ///
  /// In en, this message translates to:
  /// **'Shift and update alerts shown when the app is not open.'**
  String get alertsChannelDescription;

  /// No description provided for @trackingChannel.
  ///
  /// In en, this message translates to:
  /// **'Location tracking'**
  String get trackingChannel;

  /// No description provided for @trackingChannelDescription.
  ///
  /// In en, this message translates to:
  /// **'Shows when location tracking is active during working hours.'**
  String get trackingChannelDescription;

  /// No description provided for @deviceDeactivated.
  ///
  /// In en, this message translates to:
  /// **'This device was deactivated.'**
  String get deviceDeactivated;

  /// No description provided for @deviceDeactivatedContactAdmin.
  ///
  /// In en, this message translates to:
  /// **'This device was deactivated. Contact your administrator to sign in again.'**
  String get deviceDeactivatedContactAdmin;

  /// No description provided for @somethingWentWrongCode.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong ({code}).'**
  String somethingWentWrongCode(Object code);

  /// No description provided for @locationNotProvided.
  ///
  /// In en, this message translates to:
  /// **'Location not provided'**
  String get locationNotProvided;

  /// No description provided for @androidDevice.
  ///
  /// In en, this message translates to:
  /// **'Android device'**
  String get androidDevice;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ar', 'en'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return AppLocalizationsAr();
    case 'en':
      return AppLocalizationsEn();
  }

  throw FlutterError(
      'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
      'an issue with the localizations generation tool. Please file an issue '
      'on GitHub with a reproducible sample app and the gen-l10n configuration '
      'that was used.');
}
