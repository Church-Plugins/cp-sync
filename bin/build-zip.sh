#!/usr/bin/env bash
#
# Build a production-ready release zip: releases/cp-sync-{version}.zip
#
# Recipe:
#   1. Prune vendor/ to runtime dependencies. --no-scripts is required: the
#      composer post-install-cmd runs vendor/bin/mozart, which is a DEV
#      dependency and absent from a --no-dev install. Skipping it is safe —
#      mozart's prefixed output ( vendor/cp-sync/dependencies/ ) is already on
#      disk from the last dev install and composer leaves it alone.
#   2. Compile the settings SPA ( the only compiled JS in the plugin ).
#   3. Zip via wp-scripts plugin-zip, driven by the "files" allowlist in
#      package.json. The npm script then strips README.md and package.json,
#      which npm's packlist force-includes.
#   4. Rewrap the archive under a top-level cp-sync/ directory. wp-scripts
#      stores files at the archive root, so WordPress derives the install
#      folder from the ZIP FILENAME — fine for cp-sync.zip, wrong once the
#      filename carries a version suffix. With the cp-sync/ root inside the
#      archive, the plugin always installs as `cp-sync` regardless of what
#      the zip file is called.
#   5. Restore dev dependencies — always, even when a step fails.
#
# Usage: bin/build-zip.sh  ( from anywhere; output lands in releases/ )

set -euo pipefail
cd "$( dirname "${BASH_SOURCE[0]}" )/.."

if [ ! -d node_modules ]; then
	echo "node_modules missing — running npm install first." >&2
	npm install
fi

restore_dev_deps() {
	echo "Restoring dev dependencies..."
	composer install --quiet
}
trap restore_dev_deps EXIT

echo "Pruning vendor/ to runtime dependencies..."
composer install --no-dev --no-scripts --quiet

echo "Building the settings SPA..."
npm run build:wp

echo "Creating the zip..."
npm run plugin-zip

VERSION=$( node -p "require('./package.json').version" )
RELEASE_ZIP="releases/cp-sync-${VERSION}.zip"

echo "Rewrapping under a cp-sync/ root as ${RELEASE_ZIP}..."
mkdir -p releases
STAGE=$( mktemp -d )
mkdir "${STAGE}/cp-sync"
unzip -q cp-sync.zip -d "${STAGE}/cp-sync"
( cd "${STAGE}" && zip -qr wrapped.zip cp-sync )
mv "${STAGE}/wrapped.zip" "${RELEASE_ZIP}"
rm -f cp-sync.zip
rm -rf "${STAGE}"

echo
echo "Done: $( pwd )/${RELEASE_ZIP}"
