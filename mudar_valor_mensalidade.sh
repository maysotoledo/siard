#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="${PROJECT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
ENV_FILE="${ENV_FILE:-$PROJECT_DIR/.env}"
COMPOSE_FILE="${COMPOSE_FILE:-$PROJECT_DIR/docker-compose.prod.yml}"
COMPOSE_PROJECT_DIR="$PROJECT_DIR"
APP_SERVICES="${APP_SERVICES:-web queue scheduler}"
APP_SERVICE="${APP_SERVICE:-web}"
ENV_KEY="${ENV_KEY:-PIX_TRACKER_MONTHLY_AMOUNT}"

info() { printf '[INFO] %s\n' "$*"; }
ok() { printf '[OK] %s\n' "$*"; }
fail() { printf '[ERRO] %s\n' "$*" >&2; exit 1; }

usage() {
  cat <<'USAGE'
Uso:
  ./mudar_valor_mensalidade.sh 19.90
  ./mudar_valor_mensalidade.sh

Variaveis opcionais:
  PROJECT_DIR=/opt/siard
  ENV_FILE=/opt/siard/.env
  COMPOSE_FILE=/opt/siard/docker-compose.prod.yml

O script altera PIX_TRACKER_MONTHLY_AMOUNT no .env, recria web/queue/scheduler
sem build e atualiza os caches do Laravel.
USAGE
}

normalize_amount() {
  local raw="$1"

  raw="${raw//[[:space:]]/}"
  raw="${raw//,/\.}"

  [[ "$raw" =~ ^[0-9]+(\.[0-9]{1,2})?$ ]] || return 1

  printf '%.2f\n' "$raw"
}

read_amount() {
  local raw="${1:-}"

  if [ -z "$raw" ]; then
    read -rp "Novo valor da mensalidade (ex: 19.90): " raw
  fi

  normalize_amount "$raw" || fail "Valor invalido. Use formato como 19.90 ou 19,90."
}

update_env_value() {
  local key="$1"
  local value="$2"
  local tmp

  [ -f "$ENV_FILE" ] || fail "Arquivo .env nao encontrado: $ENV_FILE"

  tmp="$(mktemp)"

  if grep -qE "^${key}=" "$ENV_FILE"; then
    awk -v key="$key" -v value="$value" '
      BEGIN { replaced = 0 }
      $0 ~ "^" key "=" && replaced == 0 {
        print key "=" value
        replaced = 1
        next
      }
      { print }
    ' "$ENV_FILE" > "$tmp"
  else
    cp "$ENV_FILE" "$tmp"
    printf '\n%s=%s\n' "$key" "$value" >> "$tmp"
  fi

  cp "$ENV_FILE" "$ENV_FILE.bak.$(date +%Y%m%d%H%M%S)"
  mv "$tmp" "$ENV_FILE"
}

compose() {
  docker compose -f "$COMPOSE_FILE" "$@"
}

main() {
  if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
    usage
    exit 0
  fi

  command -v docker >/dev/null 2>&1 || fail "Docker nao encontrado."
  [ -f "$COMPOSE_FILE" ] || fail "docker-compose.prod.yml nao encontrado: $COMPOSE_FILE"

  local amount
  amount="$(read_amount "${1:-}")"

  info "Atualizando $ENV_KEY=$amount em $ENV_FILE"
  update_env_value "$ENV_KEY" "$amount"

  cd "$COMPOSE_PROJECT_DIR"

  info "Recriando containers da aplicacao sem build: $APP_SERVICES"
  compose up -d --no-build --force-recreate $APP_SERVICES

  info "Limpando e recriando cache do Laravel"
  compose exec -T "$APP_SERVICE" php artisan optimize:clear
  compose exec -T "$APP_SERVICE" php artisan optimize

  info "Valor carregado dentro do container:"
  compose exec -T "$APP_SERVICE" printenv "$ENV_KEY" || true

  ok "Mensalidade atualizada para R$ ${amount/./,}."
}

main "$@"
