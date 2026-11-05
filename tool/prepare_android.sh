#!/usr/bin/env bash
set -euo pipefail
project_root="$(cd "$(dirname "$0")/.." && pwd)"
output_dir="${1:-$project_root/.generated/android_app}"
mkdir -p "$output_dir"
flutter create --empty --platforms=android --org com.wusdev --project-name cham_cong "$output_dir"
cp -R "$project_root/mobile/lib/." "$output_dir/lib/"
mkdir -p "$output_dir/test"
cp -R "$project_root/mobile/test/." "$output_dir/test/"
cp "$project_root/mobile/pubspec.yaml" "$project_root/mobile/analysis_options.yaml" "$output_dir/"
python3 - "$output_dir" <<'PY'
from pathlib import Path
import sys
root = Path(sys.argv[1])
manifest = root / 'android/app/src/main/AndroidManifest.xml'
s = manifest.read_text()
s = s.replace('<application', '<uses-permission android:name="android.permission.INTERNET"/>\n    <application', 1)
s = s.replace('android:label="cham_cong"', 'android:label="Chấm Công"')
s = s.replace('<application', '<application android:allowBackup="false"', 1)
manifest.write_text(s)
PY
cd "$output_dir"
flutter pub get
