#!/usr/bin/env bash
set -euo pipefail
project_root="$(cd "$(dirname "$0")/.." && pwd)"
output_dir="${1:-$project_root/.generated/ios_app}"
mkdir -p "$output_dir"
flutter create --empty --platforms=ios --org com.wusdev --project-name cham_cong "$output_dir"
cp -R "$project_root/mobile/lib/." "$output_dir/lib/"
mkdir -p "$output_dir/test"
cp -R "$project_root/mobile/test/." "$output_dir/test/"
cp "$project_root/mobile/pubspec.yaml" "$project_root/mobile/analysis_options.yaml" "$output_dir/"
python3 - "$output_dir" <<'PY'
from pathlib import Path
import plistlib, re, sys
root = Path(sys.argv[1])
info = root / 'ios/Runner/Info.plist'
with info.open('rb') as f:
    data = plistlib.load(f)
data['CFBundleDisplayName'] = 'Chấm Công'
data['CFBundleName'] = 'Chấm Công'
with info.open('wb') as f:
    plistlib.dump(data, f, sort_keys=False)
project = root / 'ios/Runner.xcodeproj/project.pbxproj'
s = project.read_text()
s = re.sub(r'PRODUCT_BUNDLE_IDENTIFIER = [^;]+;',
           lambda m: 'PRODUCT_BUNDLE_IDENTIFIER = com.wusdev.chamcong' +
           ('.RunnerTests' if 'RunnerTests' in m.group(0) else '') + ';', s)
s = re.sub(r'IPHONEOS_DEPLOYMENT_TARGET = [^;]+;', 'IPHONEOS_DEPLOYMENT_TARGET = 15.0;', s)
project.write_text(s)
podfile = root / 'ios/Podfile'
if podfile.exists():
    s = podfile.read_text()
    s = re.sub(r"(?m)^#?\s*platform :ios, '[^']+'", "platform :ios, '15.0'", s)
    podfile.write_text(s)
PY
cd "$output_dir"
flutter pub get
