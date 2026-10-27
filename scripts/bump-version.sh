#!/bin/bash
# Raises the app version in appinfo/info.xml and package.json (SemVer).
#   scripts/bump-version.sh patch   # Fehlerbehebung   0.1.0 -> 0.1.1
#   scripts/bump-version.sh minor   # neue Funktion    0.1.1 -> 0.2.0
#   scripts/bump-version.sh major   # Bruch / Abnahme  0.2.0 -> 1.0.0
# Afterwards: add a CHANGELOG.md entry, commit, then `git tag v<version>`.
set -euo pipefail
cd "$(dirname "$0")/.."

current=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml)
IFS=. read -r major minor patch <<< "$current"
case "${1:-}" in
    major) major=$((major + 1)); minor=0; patch=0 ;;
    minor) minor=$((minor + 1)); patch=0 ;;
    patch) patch=$((patch + 1)) ;;
    *) echo "usage: $0 major|minor|patch" >&2; exit 1 ;;
esac
next="$major.$minor.$patch"

sed -i "s:<version>$current</version>:<version>$next</version>:" appinfo/info.xml
npm version "$next" --no-git-tag-version --allow-same-version > /dev/null
echo "$current -> $next"
