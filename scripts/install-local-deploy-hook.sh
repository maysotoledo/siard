#!/usr/bin/env bash
set -euo pipefail

WEBHOOK_URL="${1:-${SIARD_WEBHOOK_URL:-https://siard.online/github}}"
WEBHOOK_BRANCH="${2:-${SIARD_WEBHOOK_BRANCH:-main}}"
WEBHOOK_SECRET="${3:-${SIARD_WEBHOOK_SECRET:-}}"

if ! git rev-parse --show-toplevel >/dev/null 2>&1; then
  echo "Execute dentro de um repositorio Git." >&2
  exit 1
fi

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

if [[ ! -x .githooks/post-push ]]; then
  chmod +x .githooks/post-push
fi

if [[ -z "$WEBHOOK_SECRET" ]]; then
  read -rsp "SIARD webhook secret: " WEBHOOK_SECRET
  echo
fi

if [[ -z "$WEBHOOK_SECRET" ]]; then
  echo "Webhook secret vazio. Abortando." >&2
  exit 1
fi

git config --local core.hooksPath .githooks
git config --local siard.webhookUrl "$WEBHOOK_URL"
git config --local siard.webhookBranch "$WEBHOOK_BRANCH"
git config --local siard.webhookSecret "$WEBHOOK_SECRET"

echo "Hook local instalado."
echo "URL: $WEBHOOK_URL"
echo "Branch: $WEBHOOK_BRANCH"
