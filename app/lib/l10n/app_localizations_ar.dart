// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Arabic (`ar`).
class AppLocalizationsAr extends AppLocalizations {
  AppLocalizationsAr([String locale = 'ar']) : super(locale);

  @override
  String get appName => 'الفحص الذكي';

  @override
  String get language => 'اللغة';

  @override
  String get english => 'الإنجليزية';

  @override
  String get arabic => 'العربية';

  @override
  String get welcomeBack => 'مرحبًا بعودتك';

  @override
  String get loginInstruction =>
      'استخدم البريد الإلكتروني أو رقم الهاتف الذي زوّدك به مشرفك.';

  @override
  String get emailOrPhone => 'البريد الإلكتروني أو رقم الهاتف';

  @override
  String get emailExample => 'مثل jane@example.com';

  @override
  String get enterEmailOrPhone => 'أدخل بريدك الإلكتروني أو رقم هاتفك';

  @override
  String get password => 'كلمة المرور';

  @override
  String get enterPassword => 'أدخل كلمة المرور';

  @override
  String get showPassword => 'إظهار كلمة المرور';

  @override
  String get hidePassword => 'إخفاء كلمة المرور';

  @override
  String get copyPassword => 'نسخ كلمة المرور';

  @override
  String get passwordCopied => 'تم نسخ كلمة المرور.';

  @override
  String get signIn => 'تسجيل الدخول';

  @override
  String get serverUnreachable => 'تعذر الاتصال بالخادم. تحقق من اتصالك.';

  @override
  String get locationWorkingHoursOnly =>
      'لا يتم تسجيل موقعك إلا خلال ساعات عملك.';

  @override
  String get today => 'اليوم';

  @override
  String get inspections => 'الفحوصات';

  @override
  String get me => 'حسابي';

  @override
  String get profile => 'الملف الشخصي';

  @override
  String get fieldSurveyor => 'مساح ميداني';

  @override
  String get privacy => 'الخصوصية';

  @override
  String get whatIsRecorded => 'ماذا يتم تسجيله';

  @override
  String get privacyWorkingHours =>
      'لا يتم تسجيل موقعك إلا خلال نافذة ساعات عملك.';

  @override
  String get privacyOutsideWindow =>
      'خارج هذه النافذة، لا يتم تسجيل أو حفظ أو عرض أي بيانات لأي شخص.';

  @override
  String get privacyAudit =>
      'في كل مرة يفتح فيها المشرف سجلك، يتم تدوين ذلك في سجل التدقيق.';

  @override
  String get device => 'الجهاز';

  @override
  String get thisPhone => 'هذا الهاتف';

  @override
  String get liveUpdates => 'التحديثات المباشرة';

  @override
  String get permissions => 'الأذونات';

  @override
  String get allGranted => 'كلها ممنوحة';

  @override
  String get actionNeeded => 'يلزم إجراء';

  @override
  String get appVersion => 'إصدار التطبيق';

  @override
  String get deviceId => 'معرف الجهاز';

  @override
  String get openAppSettings => 'فتح إعدادات التطبيق';

  @override
  String get deviceSignInInfo =>
      'يظل هذا الجهاز مسجلاً للدخول. يمكن للمشرف فقط فصله عن حسابك.';

  @override
  String get selectLanguage => 'اختيار اللغة';

  @override
  String get retry => 'إعادة المحاولة';

  @override
  String get cancel => 'إلغاء';

  @override
  String get close => 'إغلاق';

  @override
  String get save => 'حفظ';

  @override
  String get later => 'لاحقًا';

  @override
  String get update => 'تحديث';

  @override
  String get loading => 'جارٍ التحميل…';

  @override
  String get locationPermissionError =>
      'يلزم السماح بالوصول إلى الموقع لاستخدام تطبيق العمل.';

  @override
  String get backgroundPermissionError =>
      'اختر «السماح طوال الوقت» ليستمر التتبع أثناء العمل الميداني.';

  @override
  String get notificationPermissionError =>
      'تلزم الإشعارات للمهام المسندة وحالة التتبع.';

  @override
  String get batteryPermissionError =>
      'يلزم استثناء التطبيق من تحسين البطارية لضمان تتبع المناوبات بموثوقية.';

  @override
  String get location => 'الموقع';

  @override
  String get locationPermissionExplanation =>
      'يسجل هذا التطبيق موقعك خلال ساعات العمل فقط، ليتمكن مشرفك من رؤية الموظفين الميدانيين على الخريطة المباشرة. لا يتم تسجيل شيء خارج ساعات العمل.';

  @override
  String get allowLocation => 'السماح بالموقع';

  @override
  String get backgroundLocation => 'الموقع في الخلفية';

  @override
  String get backgroundLocationExplanation =>
      'يجب أن يستمر التتبع أثناء إغلاق التطبيق أو إطفاء الشاشة؛ فالإذن السابق يغطي فترة فتح هذه الشاشة فقط. سيطلب Android الإذن مجددًا؛ اختر «السماح طوال الوقت».';

  @override
  String get allowAllTheTime => 'السماح طوال الوقت';

  @override
  String get notifications => 'الإشعارات';

  @override
  String get notificationsExplanation =>
      'عندما يكون التتبع نشطًا، يظل إشعار دائم ظاهرًا ليكون تشغيل التتبع وإيقافه واضحين دائمًا.';

  @override
  String get allowNotifications => 'السماح بالإشعارات';

  @override
  String get batteryOptimisation => 'تحسين البطارية';

  @override
  String get batteryExplanation =>
      'يمكن لنظام Android إيقاف العمل في الخلفية لتوفير البطارية، مما يوقف التتبع منتصف المناوبة بدون تنبيه. يحافظ الاستثناء على التتبع طوال نافذة ساعات العمل.';

  @override
  String get exemptBattery => 'الاستثناء من تحسين البطارية';

  @override
  String stepProgress(Object current, Object total) {
    return 'الخطوة $current من $total';
  }

  @override
  String get notificationsServerError => 'تعذر الاتصال بالخادم.';

  @override
  String pendingResponse(Object count) {
    return '$count حالة بانتظار ردك';
  }

  @override
  String get pendingResponseHint => 'افتح تبويب الفحوصات لقبولها أو رفضها.';

  @override
  String get allCaughtUp => 'لقد اطلعت على كل شيء';

  @override
  String get emptyNotifications =>
      'تظهر هنا المهام الجديدة وتحديثات المكتب فور وصولها.';

  @override
  String get propertyMap => 'خريطة العقار';

  @override
  String get fitLocations => 'إظهار العقار وموقعي معًا';

  @override
  String get turnOnGps => 'شغّل GPS لإظهار موقعك.';

  @override
  String get locationPermissionRequired => 'يلزم إذن الموقع لإظهار مكانك.';

  @override
  String get locationUnavailableMap =>
      'موقعك غير متاح مؤقتًا، ولكن العقار ما زال ظاهرًا.';

  @override
  String get property => 'العقار';

  @override
  String get you => 'أنت';

  @override
  String get locating => 'جارٍ تحديد الموقع…';

  @override
  String metersAway(Object distance) {
    return 'على بعد $distance م';
  }

  @override
  String kilometersAway(Object distance) {
    return 'على بعد $distance كم';
  }

  @override
  String get myCases => 'حالاتي';

  @override
  String get all => 'الكل';

  @override
  String get pending => 'معلقة';

  @override
  String get scheduled => 'مجدولة';

  @override
  String get overdue => 'متأخرة';

  @override
  String get inProgress => 'قيد التنفيذ';

  @override
  String get completed => 'مكتملة';

  @override
  String get rejected => 'مرفوضة';

  @override
  String get cancelled => 'ملغاة';

  @override
  String get awaitingAcceptance => 'بانتظار القبول';

  @override
  String plannedAt(Object date) {
    return 'مجدولة في $date';
  }

  @override
  String get nothingInFilter => 'لا يوجد شيء في هذا الفلتر';

  @override
  String get noCasesAssigned => 'لا توجد حالات مسندة';

  @override
  String get tryAnotherFilter => 'جرّب فلترًا آخر أو اسحب للأسفل للتحديث.';

  @override
  String get assignmentsAppearHere =>
      'تظهر هنا المهام الجديدة فور إرسالها من المكتب.';

  @override
  String get nothingToShow => 'لا يوجد شيء لعرضه بعد';

  @override
  String get pullDownWhenOnline =>
      'اسحب للأسفل للمحاولة مجددًا بعد عودة الاتصال.';

  @override
  String get homeServerError =>
      'تعذر الاتصال بالخادم، ولا توجد بيانات سابقة مخزنة.';

  @override
  String get fieldQueue => 'قائمة الميدان';

  @override
  String get yourInspections => 'فحوصاتك';

  @override
  String get workingHours => 'ساعات العمل';

  @override
  String get trackingStatus => 'حالة التتبع';

  @override
  String get connectionAndSync => 'الاتصال والمزامنة';

  @override
  String get goodMorning => 'صباح الخير';

  @override
  String get goodAfternoon => 'طاب نهارك';

  @override
  String get goodEvening => 'مساء الخير';

  @override
  String get onShift => 'في المناوبة';

  @override
  String get recording => 'جارٍ التسجيل';

  @override
  String get starting => 'جارٍ البدء…';

  @override
  String get windowClosing => 'نافذة العمل توشك على الإغلاق.';

  @override
  String locationRecordedFor(Object duration) {
    return 'سيستمر تسجيل الموقع لمدة $duration.';
  }

  @override
  String get offShift => 'خارج المناوبة';

  @override
  String get nothingScheduled => 'لا توجد مناوبات مجدولة بعد';

  @override
  String nextWindow(Object end, Object start) {
    return 'التالية $start – $end';
  }

  @override
  String get outsideWindowPrivacy => 'لا يتم تسجيل الموقع خارج نافذة العمل.';

  @override
  String untilWindowPrivacy(Object countdown) {
    return 'لا يتم تسجيل الموقع حتى ذلك الوقت. تبدأ $countdown.';
  }

  @override
  String get awaitingResponse => 'بانتظار\nالرد';

  @override
  String get scheduledVisits => 'زيارات\nمجدولة';

  @override
  String get inProgressTwoLines => 'قيد\nالتنفيذ';

  @override
  String get onTheJob => 'في موقع العمل';

  @override
  String get nextVisit => 'الزيارة التالية';

  @override
  String get uploadQueue => 'قائمة الرفع';

  @override
  String get backingUp => 'تراكم البيانات';

  @override
  String get healthy => 'سليمة';

  @override
  String get queuedPoints => 'النقاط في الانتظار';

  @override
  String get lastUpload => 'آخر رفع';

  @override
  String get never => 'أبدًا';

  @override
  String get liveConnected =>
      'التحديثات المباشرة مفعلة، وتصل المهام الجديدة فورًا.';

  @override
  String get liveConnecting => 'جارٍ إعادة الاتصال بالتحديثات المباشرة…';

  @override
  String get liveOffline =>
      'التحديثات المباشرة غير متصلة. سيتم التحقق كل 45 ثانية.';

  @override
  String scheduleSynced(Object time) {
    return 'تمت مزامنة الجدول $time';
  }

  @override
  String get mayBeStale => 'قد تكون قديمة';

  @override
  String get needsAttention => 'يحتاج إلى انتباه';

  @override
  String get trackingUnreliable => 'لا يمكن تشغيل التتبع بموثوقية بعد.';

  @override
  String get locationNeededWhy => 'مطلوب لتسجيل أي نقطة موقع.';

  @override
  String get backgroundNeededWhy => 'يُبقي التتبع عاملاً أثناء إطفاء الشاشة.';

  @override
  String get notificationsNeededWhy => 'يعرض المهام الجديدة فور وصولها.';

  @override
  String get batteryNeededWhy => 'يمنع Android من إيقاف الخدمة.';

  @override
  String get grant => 'منح الإذن';

  @override
  String get lessThanMinute => 'أقل من دقيقة';

  @override
  String minutesShort(Object count) {
    return '$count دقيقة';
  }

  @override
  String hoursShort(Object hours, Object minutes) {
    return '$hours س $minutes د';
  }

  @override
  String daysShort(Object count) {
    return '$count يوم';
  }

  @override
  String get now => 'الآن';

  @override
  String inDuration(Object duration) {
    return 'خلال $duration';
  }

  @override
  String get justNow => 'الآن';

  @override
  String secondsAgo(Object count) {
    return 'منذ $count ث';
  }

  @override
  String minutesAgo(Object count) {
    return 'منذ $count د';
  }

  @override
  String hoursAgo(Object count) {
    return 'منذ $count س';
  }

  @override
  String get caseLabel => 'الحالة';

  @override
  String get changeNotSaved => 'تعذر الاتصال بالخادم. لم يتم حفظ تغييرك.';

  @override
  String acceptScheduled(Object date) {
    return 'تم قبول المهمة وجدولة الفحص في $date.';
  }

  @override
  String get rejectCase => 'رفض الحالة';

  @override
  String get reject => 'رفض';

  @override
  String get rejectedNotice => 'تم رفض المهمة وإشعار الإدارة.';

  @override
  String get inspectionStarted =>
      'بدأ الفحص، وأصبحت أدوات GPS وصور الموقع جاهزة.';

  @override
  String get completeCase => 'إكمال الحالة';

  @override
  String get complete => 'إكمال';

  @override
  String get completedNotice => 'اكتمل الفحص وتم إشعار الإدارة.';

  @override
  String get noteOptional => 'ملاحظة (اختيارية)';

  @override
  String get photoLocationOff =>
      'شغّل الموقع قبل التقاط صورة، إذ يجب ختمها بإحداثيات GPS.';

  @override
  String get photoLocationPermission => 'يلزم إذن الموقع قبل رفع الصورة.';

  @override
  String get photoTooLarge =>
      'حجم الصورة أكبر من 10 ميغابايت. التقطها مجددًا وحاول.';

  @override
  String get photoQueued => 'تم حفظ الصورة وجارٍ رفعها في الخلفية';

  @override
  String get gpsFixFailed =>
      'تعذر تحديد موقع GPS. انتقل إلى مكان مفتوح وأعد التقاط الصورة.';

  @override
  String get openPropertyMap => 'فتح خريطة العقار';

  @override
  String get planned => 'مجدول';

  @override
  String get notes => 'ملاحظات';

  @override
  String get accept => 'قبول';

  @override
  String get startInspection => 'بدء الفحص';

  @override
  String get takeGpsPhoto => 'التقاط صورة موثقة بـ GPS';

  @override
  String get completeInspection => 'إكمال الفحص';

  @override
  String get gpsPhotoRequired => 'تلزم صورة موقع موثقة بـ GPS قبل الإكمال.';

  @override
  String get noCaseActions => 'لا توجد إجراءات متاحة لهذه الحالة حاليًا.';

  @override
  String get assignmentResponse => 'الرد على المهمة';

  @override
  String get readyForInspection => 'جاهز للفحص';

  @override
  String get siteVerification => 'التحقق من الموقع';

  @override
  String get history => 'السجل';

  @override
  String get surveyorAssigned => 'تم إسناد مساح';

  @override
  String get caseReceived => 'تم استلام الحالة';

  @override
  String get assignmentAcceptedScheduled => 'تم قبول المهمة وجدولتها';

  @override
  String get inspectionBecameOverdue => 'أصبح الفحص متأخرًا';

  @override
  String get caseCancelled => 'تم إلغاء الحالة';

  @override
  String get caseUpdated => 'تم تحديث الحالة';

  @override
  String get system => 'النظام';

  @override
  String get eventCaseCreated => 'تم إنشاء الحالة.';

  @override
  String eventAssignedTo(Object name) {
    return 'تم الإسناد إلى $name.';
  }

  @override
  String eventReassigned(Object from, Object to) {
    return 'تمت إعادة الإسناد من $from إلى $to.';
  }

  @override
  String get eventAccepted => 'قبِل المسّاح المهمة.';

  @override
  String get eventRejected => 'رفض المسّاح المهمة.';

  @override
  String get eventInspectionStarted => 'بدأ الفحص.';

  @override
  String get eventOverdue => 'تجاوز موعد الفحص المخطط.';

  @override
  String get eventCompleted => 'اكتمل الفحص.';

  @override
  String get eventCancelled => 'ألغت الإدارة الحالة.';

  @override
  String get photoUploadFailed => 'تعذر رفع الصورة';

  @override
  String get closedBeforePhotoUpload =>
      'أُغلقت الحالة قبل التمكن من رفع صورتك.';

  @override
  String get keep => 'إبقاء';

  @override
  String get discard => 'حذف';

  @override
  String get uploading => 'جارٍ الرفع';

  @override
  String get photoFailedTap => 'فشل — انقر للتفاصيل';

  @override
  String get uploadingEllipsis => 'جارٍ الرفع…';

  @override
  String get sitePhotos => 'صور الموقع';

  @override
  String get updateAvailable => 'يتوفر تحديث';

  @override
  String versionLabel(Object version) {
    return 'الإصدار $version';
  }

  @override
  String get mandatoryInstallReminder =>
      'هذا التحديث إلزامي، ولا يمكن استخدام التطبيق حتى تثبيته. اضغط على تحديث وأكمل التثبيت هذه المرة.';

  @override
  String get updateDownloadFailed =>
      'تعذر تنزيل التحديث. تحقق من اتصالك ومن السماح بالتثبيت من هذا التطبيق في الإعدادات، ثم حاول مجددًا.';

  @override
  String get installing => 'جارٍ التثبيت…';

  @override
  String downloadingPercent(Object percent) {
    return 'جارٍ التنزيل $percent%';
  }

  @override
  String get workingHoursTracking => 'تتبع ساعات العمل';

  @override
  String get live => 'مباشر';

  @override
  String get connecting => 'جارٍ الاتصال';

  @override
  String get offline => 'غير متصل';

  @override
  String get trackingActive => 'التتبع نشط';

  @override
  String get insideWorkingWindow => 'أنت داخل نافذة ساعات عملك.';

  @override
  String get trackingOff => 'التتبع متوقف';

  @override
  String get outsideWorkingHours =>
      'أنت خارج ساعات العمل، ولا يتم تسجيل الموقع.';

  @override
  String get trackingUnknown => 'حالة التتبع غير معروفة';

  @override
  String get trackingUnknownOffline =>
      'لا يوجد اتصال، لذلك لا يمكن التأكد من حالة التتبع الآن.';

  @override
  String get newCaseAssigned => 'تم إسناد حالة جديدة';

  @override
  String get newCaseCreated => 'تم إنشاء حالة جديدة';

  @override
  String get caseStatusChanged => 'تغيرت حالة الفحص';

  @override
  String get scheduleChanged => 'تغير جدولك';

  @override
  String get deviceAccessRevoked => 'تم إلغاء وصول الجهاز';

  @override
  String get appUpdateAvailable => 'يتوفر تحديث للتطبيق';

  @override
  String get officeUpdate => 'تحديث من المكتب';

  @override
  String get openAppForDetails => 'افتح التطبيق للتفاصيل.';

  @override
  String versionReady(Object version) {
    return 'الإصدار $version جاهز للتثبيت.';
  }

  @override
  String get shiftStarted => 'بدأت المناوبة';

  @override
  String get shiftEnded => 'انتهت المناوبة';

  @override
  String get insideWindowNotice => 'أنت الآن داخل نافذة ساعات عملك.';

  @override
  String get windowEndedNotice => 'انتهت نافذة ساعات عملك.';

  @override
  String get alertsChannel => 'التنبيهات';

  @override
  String get alertsChannelDescription =>
      'تنبيهات المناوبات والتحديثات التي تظهر عندما لا يكون التطبيق مفتوحًا.';

  @override
  String get trackingChannel => 'تتبع الموقع';

  @override
  String get trackingChannelDescription =>
      'يوضح متى يكون تتبع الموقع نشطًا أثناء ساعات العمل.';

  @override
  String get deviceDeactivated => 'تم إلغاء تفعيل هذا الجهاز.';

  @override
  String get deviceDeactivatedContactAdmin =>
      'تم إلغاء تفعيل هذا الجهاز. تواصل مع المسؤول لتسجيل الدخول مجددًا.';

  @override
  String somethingWentWrongCode(Object code) {
    return 'حدث خطأ ما ($code).';
  }

  @override
  String get locationNotProvided => 'لم يتم توفير الموقع';

  @override
  String get androidDevice => 'جهاز أندرويد';
}
