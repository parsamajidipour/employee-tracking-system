#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

readonly BASEMAP_DIR=api/storage/app/basemap
readonly BASEMAP_FILE="$BASEMAP_DIR/oman.pmtiles"
readonly PMTILES_VERSION=1.31.2
readonly OMAN_BBOX=52.0000004,16.4649608,60.0545770,26.7026780
readonly MIN_VALID_SIZE=20000000

file_size() {
  stat -c%s "$1" 2>/dev/null || stat -f%z "$1" 2>/dev/null || echo 0
}

if [ -s "$BASEMAP_FILE" ]; then
  existing_size="$(file_size "$BASEMAP_FILE")"
  if [ "$existing_size" -ge "$MIN_VALID_SIZE" ]; then
    exit 0
  fi
  echo "Existing basemap is ${existing_size} bytes, below the ${MIN_VALID_SIZE}-byte sanity floor — treating it as a truncated extract and re-fetching." >&2
fi

if [ "$(uname -m)" != x86_64 ]; then
  echo "Automatic basemap installation currently requires an x86_64 VPS." >&2
  exit 1
fi

tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

curl --fail --silent --show-error --location \
  "https://github.com/protomaps/go-pmtiles/releases/download/v${PMTILES_VERSION}/go-pmtiles_${PMTILES_VERSION}_Linux_x86_64.tar.gz" \
  -o "$tmp_dir/pmtiles.tar.gz"
tar -xzf "$tmp_dir/pmtiles.tar.gz" -C "$tmp_dir"

mkdir -p "$BASEMAP_DIR"
for offset in 0 1 2; do
  build_date="$(date -u -d "${offset} day ago" +%Y%m%d)"
  if "$tmp_dir/pmtiles" extract \
    "https://build.protomaps.com/${build_date}.pmtiles" \
    "$tmp_dir/oman.pmtiles" \
    --bbox="$OMAN_BBOX" \
    --maxzoom=14; then
    extracted_size="$(file_size "$tmp_dir/oman.pmtiles")"
    if [ "$extracted_size" -lt "$MIN_VALID_SIZE" ]; then
      echo "Extract from ${build_date} produced only ${extracted_size} bytes (below the ${MIN_VALID_SIZE}-byte floor) — treating as a failed extract and trying the next date." >&2
      rm -f "$tmp_dir/oman.pmtiles"
      continue
    fi
    mv "$tmp_dir/oman.pmtiles" "$BASEMAP_FILE"
    chmod 644 "$BASEMAP_FILE"
    exit 0
  fi
  rm -f "$tmp_dir/oman.pmtiles"
done

echo "No Protomaps planet build for the last three UTC dates produced a basemap of at least ${MIN_VALID_SIZE} bytes." >&2
exit 1
