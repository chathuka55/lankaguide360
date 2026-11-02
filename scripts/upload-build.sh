#!/usr/bin/env bash
# Build the CSS/JS on this computer and replace public/build on the server.
# Usage (Git Bash, in the project folder):
#     bash scripts/upload-build.sh <ssh-target> [remote project folder]
# e.g. bash scripts/upload-build.sh lankaguide360@ssh-lankaguide360.alwaysdata.net
set -euo pipefail

target="${1:?Usage: bash scripts/upload-build.sh <ssh-target> [remote folder]}"
remote="${2:-lankaguide360}"
archive="$(mktemp -t lg-build-XXXXXX).tgz"

npm run build
tar -czf "$archive" -C public build
scp -q "$archive" "$target:lg-build.tgz"
ssh "$target" "cd $remote && rm -rf public/build.new && mkdir public/build.new && tar -xzf ~/lg-build.tgz -C public/build.new --strip-components=1 && rm -rf public/build && mv public/build.new public/build && rm ~/lg-build.tgz"
rm -f "$archive"
echo "Uploaded public/build to $target:$remote."
