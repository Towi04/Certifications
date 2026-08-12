#!/usr/bin/env bash
# Wrapper portable para el qpdf vendored (LD_LIBRARY_PATH + binario).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
export LD_LIBRARY_PATH="${ROOT}/lib${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
BIN="${ROOT}/bin/qpdf"
chmod u+x "$BIN" 2>/dev/null || true
exec "$BIN" "$@"
