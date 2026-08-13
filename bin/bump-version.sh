#!/usr/bin/env bash
#
# Bump the plugin version everywhere it is declared:
#   - the "* Version:" plugin header in the main plugin file
#   - the {PLUGIN}_PLUGIN_VERSION constant in the main plugin file
#   - package.json "version"
#   - readme.txt "Stable tag"
#
# The main plugin file is auto-detected ( the root .php with a Plugin Name
# header ), so this script is identical across Church Plugins repos.
#
# Usage:
#   bin/bump-version.sh <x.y.z> [--beta [n]]
#
#   bin/bump-version.sh 1.0.0            → 1.0.0
#   bin/bump-version.sh 1.0.0 --beta     → 1.0.0-beta1
#   bin/bump-version.sh 1.0.0 --beta 2   → 1.0.0-beta2

set -euo pipefail
cd "$( dirname "${BASH_SOURCE[0]}" )/.."

usage() {
	echo "Usage: bin/bump-version.sh <x.y.z> [--beta [n]]" >&2
	exit 1
}

[ $# -ge 1 ] || usage
BASE="$1"
shift
[[ "${BASE}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Version must be x.y.z ( got: ${BASE} )" >&2; usage; }

SUFFIX=""
if [ $# -ge 1 ]; then
	case "$1" in
		--beta)
			N="${2:-1}"
			[[ "${N}" =~ ^[0-9]+$ ]] || { echo "--beta expects a number ( got: ${N} )" >&2; usage; }
			SUFFIX="-beta${N}"
			;;
		*)
			usage
			;;
	esac
fi

NEW="${BASE}${SUFFIX}"

MAIN=$( grep -l "^ \* Plugin Name:" ./*.php | head -1 )
[ -n "${MAIN}" ] || { echo "No main plugin file with a Plugin Name header found." >&2; exit 1; }

OLD=$( sed -n 's/^ \* Version:[[:space:]]*//p' "${MAIN}" )

sed -i '' "s/^ \* Version:.*/ * Version: ${NEW}/" "${MAIN}"
# The PLUGIN_VERSION constant's value sits alone on its own quoted line.
sed -i '' "s/^\([[:space:]]*\)'[0-9][0-9.]*\(-[A-Za-z0-9.]*\)\{0,1\}'\$/\1'${NEW}'/" "${MAIN}"
sed -i '' "s/\"version\": \"[^\"]*\"/\"version\": \"${NEW}\"/" package.json
sed -i '' "s/^Stable tag:.*/Stable tag: ${NEW}/" readme.txt

echo "Bumped ${OLD} → ${NEW}"
echo
grep -n "${NEW}" "${MAIN}" package.json readme.txt | grep -v "^readme.txt:[0-9]*:= "
