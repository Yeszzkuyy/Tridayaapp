#!/usr/bin/env bash
# Aktifkan hook repo (.githooks) untuk semua worktree.
# Jalankan sekali per clone: scripts/setup-hooks.sh

set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

chmod +x .githooks/commit-msg .githooks/pre-commit 2>/dev/null || true
git config core.hooksPath .githooks

echo "✓ core.hooksPath = .githooks"
echo "  Hook aktif: commit-msg (format + body + wip), pre-commit (kredensial/file sisa)"
echo "  Cek: git config --get core.hooksPath"
