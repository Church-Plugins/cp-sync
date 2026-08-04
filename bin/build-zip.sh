#!/usr/bin/env bash
#
# Build a production-ready cp-sync.zip.
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
#   4. Restore dev dependencies — always, even when a step fails.
#
# Usage: bin/build-zip.sh  ( from anywhere; output lands in the plugin root )

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

echo
echo "Done: $( pwd )/cp-sync.zip"
