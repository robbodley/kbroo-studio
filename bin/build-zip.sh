#!/usr/bin/env bash
# Builds kbroo-studio.zip, the file WordPress installs: one kbroo-studio/
# folder with the plugin, without the docs, the starter theme or git files.
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
