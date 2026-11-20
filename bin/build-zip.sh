#!/usr/bin/env bash
# Builds the files WordPress installs:
#   kbroo-studio.zip               the plugin, one kbroo-studio/ folder, without
#                                  the docs, the starter theme or git files
#   kbroo-hello-<version>.zip      the Kbroo Hello theme, one kbroo-hello/ folder
# Usage: bin/build-zip.sh [output-dir]   (default: build/)
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
out="$(mkdir -p "${1:-$root/build}" && cd "${1:-$root/build}" && pwd)"
stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT

mkdir "$stage/kbroo-studio"
tar -C "$root" \
	--exclude=./.git --exclude=./.github --exclude=./.gitignore \
	--exclude=./docs --exclude=./theme --exclude=./bin --exclude=./build \
	--exclude=./node_modules --exclude='*.log' --exclude='.DS_Store' \
	-cf - . | tar -C "$stage/kbroo-studio" -xf -

rm -f "$out/kbroo-studio.zip"
(cd "$stage" && zip -qr "$out/kbroo-studio.zip" kbroo-studio)
echo "$out/kbroo-studio.zip"

hello="$(sed -n 's/^Version: *//p' "$root/theme/kbroo-hello/style.css" | tr -d '[:space:]')"
rm -f "$out"/kbroo-hello-*.zip
(cd "$root/theme" && zip -qr "$out/kbroo-hello-$hello.zip" kbroo-hello -x '*.DS_Store' '*.log')
echo "$out/kbroo-hello-$hello.zip"
