#!/usr/bin/env bash
# Fail closed on observed release-integrity metadata; no network or mutation occurs here.
set -euo pipefail

case "${1:-}" in
  source)
    if [[ "$#" -ne 3 || "$2" != refs/heads/main ]]; then
      echo 'Release refused: select the main branch when running Release on record.' >&2
      exit 1
    fi
    bash "${BASH_SOURCE[0]}" protected "$3"
    ;;
  protected)
    if [[ "$#" -ne 2 || "$2" != true ]]; then
      echo 'Release refused: main must be protected by an active branch rule or ruleset.' >&2
      echo 'In repository Settings > Rules > Rulesets, activate a branch ruleset targeting main.' >&2
      echo 'Also enable release immutability under Settings > General > Releases before publishing.' >&2
      echo 'Packagist linking does not configure either setting. See docs/releasing.md for recovery.' >&2
      exit 1
    fi
    ;;
  published)
    if [[ "$#" -ne 2 || ! "$2" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; then
      echo 'Release verification requires one exact stable SemVer version.' >&2
      exit 1
    fi
    if ! jq -es --arg tag "v$2" '
      length == 1 and (.[0] | type == "object" and .tag_name == $tag and .draft == false and .prerelease == false
      and .immutable == true and (.published_at | type == "string" and length > 0))
    ' >/dev/null; then
      echo 'Release refused: exact version must be published, stable and immutable.' >&2
      echo 'Enabling release immutability only affects future releases. Preserve an existing mutable release' >&2
      echo 'and record a new patch version; do not move its tag or republish it. See docs/releasing.md.' >&2
      exit 1
    fi
    ;;
  *)
    echo 'Usage: check-release-integrity.sh source refs/heads/main true | protected true | published VERSION < release.json' >&2
    exit 2
    ;;
esac
