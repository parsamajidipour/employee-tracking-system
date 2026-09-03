#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-test}"
ENV_FILE="$(dirname "$0")/../.env"

read_env() {
    grep -E "^${1}=" "$ENV_FILE" | tail -1 | cut -d= -f2-
}

if [ "$MODE" = "prod" ]; then
    API_HOST="$(read_env SERVER_HOST)"
    API_PORT="$(read_env API_PORT)"
    API_SCHEME="$(read_env SERVER_SCHEME)"
else
    API_HOST="$(read_env LAN_HOST)"
    API_PORT="$(read_env API_PORT)"
    API_SCHEME="http"
fi

REPO="$(cd "$(dirname "$0")/.." && pwd)"
APP="$REPO/app"
WIN_COPY="/mnt/e/inspection-app"
WIN_COPY_DOS='E:\inspection-app'
FLUTTER_DOS='e:\flutter\bin\flutter.bat'
JDK_DOS='E:\Android\jdk-17.0.20+8'
SDK_DOS='E:\Android\sdk'

API_BASE="${API_SCHEME}://${API_HOST}:${API_PORT}"
REVERB_KEY="$(read_env REVERB_APP_KEY)"
REVERB_PUBLIC_PORT="$(read_env REVERB_PORT)"

echo "==> API base baked into the APK: $API_BASE"
echo "==> Reverb websocket baked into the APK: ${API_HOST}:${REVERB_PUBLIC_PORT}"

python3 - "$APP" "$API_HOST" <<'PY'
import sys, pathlib

app, host = sys.argv[1], sys.argv[2]
config = pathlib.Path(app) / "android/app/src/main/res/xml/network_security_config.xml"

config.write_text(f'''<?xml version="1.0" encoding="utf-8"?>
<network-security-config>
    <base-config cleartextTrafficPermitted="false">
        <trust-anchors>
            <certificates src="system" />
        </trust-anchors>
    </base-config>

    <domain-config cleartextTrafficPermitted="true">
        <domain includeSubdomains="false">localhost</domain>
        <domain includeSubdomains="false">10.0.2.2</domain>
        <domain includeSubdomains="false">{host}</domain>
    </domain-config>
</network-security-config>
''')
print(f"    cleartext allowed for: localhost, 10.0.2.2, {host}")
PY

find_flutter() {
    if command -v flutter >/dev/null 2>&1; then
        command -v flutter
    elif [ -x "${FLUTTER_ROOT:-}/bin/flutter" ]; then
        echo "${FLUTTER_ROOT}/bin/flutter"
    elif [ -x "$HOME/sdks/flutter/bin/flutter" ]; then
        echo "$HOME/sdks/flutter/bin/flutter"
    fi
}

FLUTTER="$(find_flutter)"

if [ -n "$FLUTTER" ]; then
    echo "==> Building release APK with $FLUTTER"

    export ANDROID_HOME="${ANDROID_HOME:-$HOME/Android/Sdk}"
    export ANDROID_SDK_ROOT="$ANDROID_HOME"

    if [ ! -f "$APP/android/key.properties" ]; then
        echo "!! android/key.properties is missing — release signing needs the production keystore" >&2
        exit 1
    fi

    (cd "$APP" && "$FLUTTER" pub get)
    (cd "$APP" && "$FLUTTER" build apk --release \
        --dart-define=API_BASE_URL="$API_BASE" \
        --dart-define=REVERB_APP_KEY="$REVERB_KEY" \
        --dart-define=REVERB_PORT="$REVERB_PUBLIC_PORT" \
        --target-platform android-arm,android-arm64)

    OUT="$APP/build/app/outputs/flutter-apk/app-release.apk"
else

echo "==> No local Flutter SDK found; building through the Windows toolchain"

echo "==> Stopping any Gradle daemon holding locks on the build directory"
(cd /mnt/c && cmd.exe /c 'taskkill /F /IM java.exe' >/dev/null 2>&1) || true
sleep 2

echo "==> Syncing app/ to a native Windows path (Gradle cannot build over \\\\wsl.localhost)"
chmod -R u+w "$WIN_COPY" 2>/dev/null || true
rm -rf "$WIN_COPY"
mkdir -p "$WIN_COPY"
tar --exclude=build --exclude=.dart_tool --exclude=.gradle -cf - -C "$APP" . | tar -xf - -C "$WIN_COPY"

cat > /mnt/e/Android/_build_apk.bat <<EOF
@echo off
set ANDROID_HOME=$SDK_DOS
set ANDROID_SDK_ROOT=$SDK_DOS
set JAVA_HOME=$JDK_DOS
cd /d $WIN_COPY_DOS
call $FLUTTER_DOS pub get
call $FLUTTER_DOS build apk --release --dart-define=API_BASE_URL=$API_BASE --dart-define=REVERB_APP_KEY=$REVERB_KEY --dart-define=REVERB_PORT=$REVERB_PUBLIC_PORT --target-platform android-arm,android-arm64
echo BUILD_EXIT=%ERRORLEVEL%
EOF

echo "==> Building release APK"
(cd /mnt/c && cmd.exe /c 'E:\Android\_build_apk.bat') 2>&1 | tr '\r' '\n' | tail -20

OUT="$WIN_COPY/build/app/outputs/flutter-apk/app-release.apk"
fi
if [ ! -f "$OUT" ]; then
    echo "!! Build failed: $OUT not found" >&2
    exit 1
fi

mkdir -p "$REPO/dist"
cp "$OUT" "$REPO/dist/smart-inspection.apk"

echo "==> Done: dist/smart-inspection.apk ($(du -h "$REPO/dist/smart-inspection.apk" | cut -f1))"
