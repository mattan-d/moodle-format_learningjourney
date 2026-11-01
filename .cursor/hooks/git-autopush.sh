#!/bin/bash
# Commit and push leftover changes when an agent turn ends.
set -u

echo '{}'

root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$root" || exit 0

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  exit 0
fi

if [ -d .git/rebase-merge ] || [ -d .git/rebase-apply ] || [ -f .git/MERGE_HEAD ]; then
  exit 0
fi

branch="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo HEAD)"
if [ "$branch" = "HEAD" ]; then
  exit 0
fi

if [ -n "$(git status --porcelain)" ]; then
  git add -A
  while IFS= read -r path; do
    case "$path" in
      *.env|.env|credentials.json|*/id_rsa|*.pem|*.key)
        git reset -q HEAD -- "$path" 2>/dev/null || true
        ;;
    esac
  done < <(git diff --cached --name-only)

  if [ -n "$(git diff --cached --name-only)" ]; then
    git commit -m "$(cat <<'EOF'
Save workspace changes after the agent request.

EOF
)" || exit 0
  fi
fi

if git rev-parse --abbrev-ref --symbolic-full-name '@{u}' >/dev/null 2>&1; then
  ahead="$(git rev-list --count '@{u}..HEAD' 2>/dev/null || echo 0)"
  if [ "$ahead" != "0" ]; then
    git push || exit 0
  fi
else
  git push -u origin HEAD || exit 0
fi

exit 0
