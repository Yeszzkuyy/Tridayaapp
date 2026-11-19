#!/usr/bin/env bash
# Gabungkan tumpukan commit (wip) di branch jadi SATU commit paket.
# Aturan: AGENTS.md §3 & §9.
#
# Pemakaian:
#   scripts/squash-wip.sh -m "feat(sales/meeting): ringkasan paket

#   - poin perubahan
#   - Diverifikasi: langkah cek"
#
#   scripts/squash-wip.sh --include-all -m "..."   # gabung semua commit, bukan hanya wip
#   scripts/squash-wip.sh --base main -m "..."     # ganti base pembanding
#   scripts/squash-wip.sh --dry-run -m "..."       # lihat saja, tidak mengubah apa pun

set -uo pipefail

MSG=""
BASE_REF="main"
INCLUDE_ALL=0
DRY_RUN=0

die() { echo "✗ $1" >&2; exit 1; }

usage() {
  sed -n '2,13p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
  exit 0
}

while [ $# -gt 0 ]; do
  case "$1" in
    -m|--message) MSG="${2:-}"; shift 2 ;;
    --base)       BASE_REF="${2:-}"; shift 2 ;;
    --include-all) INCLUDE_ALL=1; shift ;;
    --dry-run)    DRY_RUN=1; shift ;;
    -h|--help)    usage ;;
    *) die "opsi tidak dikenal: $1" ;;
  esac
done

BRANCH="$(git symbolic-ref --short -q HEAD 2>/dev/null || echo unknown)"
if [ "$BRANCH" = "main" ]; then
  die "jangan squash di branch main — squash dilakukan di branch feature/<divisi>-<topik>."
fi

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
  die "ada perubahan belum di-commit pada file terlacak. Commit atau 'git stash push -m \"...\"' dulu."
fi

BASE="$(git merge-base HEAD "$BASE_REF" 2>/dev/null)" || die "tidak menemukan base '$BASE_REF'."
COUNT="$(git rev-list --count "$BASE..HEAD")"
[ "$COUNT" -eq 0 ] && die "tidak ada commit di '$BRANCH' di atas '$BASE_REF'."

echo "Branch : $BRANCH"
echo "Base   : $BASE_REF ($(git log -1 --format=%h "$BASE"))"
echo "Commit : $COUNT"
echo
git log --oneline "$BASE..HEAD" | sed 's/^/    /'
echo

if [ "$INCLUDE_ALL" -eq 0 ]; then
  NONWIP="$(git log --format='%s' "$BASE..HEAD" | grep -v -E '^(wip\(|Merge )' || true)"
  if [ -n "$NONWIP" ]; then
    echo "Commit non-wip yang ikut tergabung:" >&2
    printf '%s\n' "$NONWIP" | sed 's/^/    /' >&2
    if [ "$DRY_RUN" -eq 1 ]; then
      echo "(dry-run) perlu --include-all untuk menggabung semuanya." >&2
      exit 0
    fi
    die "pakai --include-all kalau memang mau menggabung semuanya."
  fi
fi

[ -z "$MSG" ] && die "pesan paket wajib: -m \"<tipe>(<divisi>/<menu>): <ringkasan>\" + body."

if [ "$DRY_RUN" -eq 1 ]; then
  echo "(dry-run) tidak ada yang diubah."
  exit 0
fi

git reset --soft "$BASE" || die "git reset --soft gagal."
git commit -F - <<<"$MSG" || die "commit paket gagal (cek pesan lewat hook commit-msg)."

echo
echo "✓ jadi satu commit paket:"
git log --oneline -1 | sed 's/^/    /'
echo
echo "Push branch (riwayat berubah, perlu force-with-lease):"
echo "    git push --force-with-lease origin $BRANCH"
