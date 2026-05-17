#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${SIARD_APP_DIR:-/opt/siard}"
REPO_DIR="${SIARD_REPO_DIR:-/opt/siard-repo}"
REMOTE="${SIARD_GIT_REMOTE:-https://github.com/maysotoledo/siard.git}"
BRANCH="${SIARD_GIT_BRANCH:-main}"
SERVICE_FILE="/etc/systemd/system/siard-deploy-webhook.service"
ENV_FILE="/etc/siard-deploy-webhook.env"

if [[ "$(id -u)" != "0" ]]; then
  echo "Execute como root." >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"

install -m 0755 "$ROOT_DIR/scripts/vps/siard-deploy" /usr/local/bin/siard-deploy
install -m 0755 "$ROOT_DIR/scripts/vps/siard-deploy-webhook" /usr/local/bin/siard-deploy-webhook

if [[ ! -f "$ENV_FILE" ]]; then
  secret="$(openssl rand -hex 32)"
  cat > "$ENV_FILE" <<EOF_ENV
SIARD_WEBHOOK_SECRET=$secret
SIARD_DEPLOY_SCRIPT=/usr/local/bin/siard-deploy
SIARD_DEPLOY_LOG=/var/log/siard-deploy.log
SIARD_WEBHOOK_HOST=0.0.0.0
SIARD_WEBHOOK_PORT=9001
SIARD_APP_DIR=$APP_DIR
SIARD_REPO_DIR=$REPO_DIR
SIARD_GIT_REMOTE=$REMOTE
SIARD_GIT_BRANCH=$BRANCH
EOF_ENV
  chmod 0600 "$ENV_FILE"
else
  echo "$ENV_FILE ja existe; mantendo segredo atual."
fi

install -m 0644 "$ROOT_DIR/deploy/systemd/siard-deploy-webhook.service" "$SERVICE_FILE"

systemctl daemon-reload
systemctl enable --now siard-deploy-webhook.service
systemctl restart siard-deploy-webhook.service

echo "Webhook instalado."
echo "Service: siard-deploy-webhook.service"
echo "Env: $ENV_FILE"
echo "URL publica esperada: https://SEU_DOMINIO/github"
echo "Secret:"
grep '^SIARD_WEBHOOK_SECRET=' "$ENV_FILE" | cut -d= -f2-
