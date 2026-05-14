#!/usr/bin/env bash
# =============================================================
#  SIARD — Gerenciador Docker de Produção
#  Projeto em /opt/siard  |  docker-compose.prod.yml
# =============================================================
set -euo pipefail

COMPOSE_FILE="/opt/siard/docker-compose.prod.yml"
PROJECT_DIR="/opt/siard"

# Nomes dos containers (projeto=siard → prefixo siard-)
APP="${APP_CONTAINER:-siard-web-1}"
PROXY="${PROXY_CONTAINER:-siard-proxy-1}"
DB="${DB_CONTAINER:-siard-db-1}"
QUEUE="${QUEUE_CONTAINER:-siard-queue-1}"
SCHEDULER="${SCHEDULER_CONTAINER:-siard-scheduler-1}"

# ─── Helpers ──────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

ok()   { echo -e "${GREEN}✔ $*${NC}"; }
err()  { echo -e "${RED}✖ $*${NC}"; }
info() { echo -e "${CYAN}ℹ $*${NC}"; }
warn() { echo -e "${YELLOW}⚠ $*${NC}"; }

is_running() {
  docker ps --format '{{.Names}}' | grep -qx "$1" 2>/dev/null
}

require_running() {
  if ! is_running "$1"; then
    err "Container não está rodando: $1"
    return 1
  fi
}

pick_shell() {
  if docker exec "$1" sh -lc 'command -v bash >/dev/null 2>&1'; then
    echo bash
  else
    echo sh
  fi
}

# ─── Ações ────────────────────────────────────────────────────

enter() {
  local c="$1"
  require_running "$c" || return 1
  local sh; sh="$(pick_shell "$c")"
  info "Entrando em $c ($sh)..."
  docker exec -it "$c" "$sh"
}

logs() {
  local c="$1" lines="${2:-300}"
  require_running "$c" || return 1
  docker logs --tail="$lines" -f "$c"
}

artisan() {
  require_running "$APP" || return 1
  shift 0
  info "Rodando: php artisan $*"
  docker exec -it "$APP" php artisan "$@"
}

mysql_cli() {
  require_running "$DB" || return 1
  info "Abrindo MySQL CLI..."
  docker exec -it "$DB" mysql -u"${DB_USERNAME:-siard}" -p"${DB_PASSWORD:-}" "${DB_DATABASE:-siard}" 2>/dev/null \
    || docker exec -it "$DB" mysql -uroot -p
}

status() {
  echo -e "\n${BOLD}=== Status dos containers ===${NC}"
  docker ps --filter "name=siard-" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null || docker ps
}

recreate_proxy() {
  info "Recriando proxy para remontar Caddyfile atualizado..."
  docker compose -f "$COMPOSE_FILE" up -d --force-recreate --no-deps proxy
}

deploy() {
  echo -e "\n${BOLD}${BLUE}=== Deploy SIARD ===${NC}"
  cd "$PROJECT_DIR"

  info "1/6 — Pulling imagens base..."
  docker compose -f "$COMPOSE_FILE" pull --ignore-buildable

  info "2/6 — Build das imagens..."
  docker compose -f "$COMPOSE_FILE" build --no-cache

  info "3/6 — Subindo containers..."
  docker compose -f "$COMPOSE_FILE" up -d --remove-orphans
  recreate_proxy

  info "4/6 — Aguardando banco ficar saudável..."
  local tries=0
  until docker exec "$DB" mysqladmin ping -h127.0.0.1 --silent 2>/dev/null; do
    tries=$((tries+1))
    if [ "$tries" -ge 30 ]; then
      err "Banco não respondeu em 60s. Verifique os logs."; return 1
    fi
    sleep 2
  done
  ok "Banco pronto."

  info "5/6 — Migrations e cache..."
  docker exec "$APP" php artisan migrate --force --ansi
  docker exec "$APP" php artisan config:cache --ansi
  docker exec "$APP" php artisan route:cache --ansi
  docker exec "$APP" php artisan view:cache --ansi
  docker exec "$APP" php artisan storage:link --ansi 2>/dev/null || true

  info "6/6 — Reiniciando queue worker..."
  docker exec "$APP" php artisan queue:restart --ansi 2>/dev/null || true

  ok "Deploy concluído!"
  status
}

update() {
  echo -e "\n${BOLD}${BLUE}=== Update rápido (sem rebuild) ===${NC}"
  cd "$PROJECT_DIR"

  info "Reiniciando containers com a imagem atual..."
  docker compose -f "$COMPOSE_FILE" up -d --remove-orphans
  recreate_proxy

  info "Rodando migrations..."
  docker exec "$APP" php artisan migrate --force --ansi

  info "Limpando cache..."
  docker exec "$APP" php artisan optimize:clear --ansi
  docker exec "$APP" php artisan optimize --ansi

  info "Reiniciando queue..."
  docker exec "$APP" php artisan queue:restart --ansi 2>/dev/null || true

  ok "Update concluído!"
}

restart_all() {
  cd "$PROJECT_DIR"
  info "Reiniciando todos os containers..."
  docker compose -f "$COMPOSE_FILE" restart
  ok "Containers reiniciados."
  status
}

stop_all() {
  cd "$PROJECT_DIR"
  warn "Parando todos os containers..."
  docker compose -f "$COMPOSE_FILE" down
  ok "Containers parados."
}

clear_cache() {
  require_running "$APP" || return 1
  info "Limpando todos os caches..."
  docker exec "$APP" php artisan optimize:clear --ansi
  docker exec "$APP" php artisan optimize --ansi
  ok "Cache limpo e reaquecido."
}

# ─── Menu ─────────────────────────────────────────────────────
menu() {
  echo -e "\n${BOLD}${BLUE}╔══════════════════════════════════╗${NC}"
  echo -e "${BOLD}${BLUE}║       SIARD — Docker Manager     ║${NC}"
  echo -e "${BOLD}${BLUE}╚══════════════════════════════════╝${NC}"

  echo -e "\n${BOLD}── Acesso aos containers ──────────────${NC}"
  echo "  1) Shell APP  (PHP/Laravel)  [$APP]"
  echo "  2) Shell PROXY (Caddy)       [$PROXY]"
  echo "  3) Shell DB   (MySQL)        [$DB]"
  echo "  4) MySQL CLI"
  echo "  5) Shell QUEUE               [$QUEUE]"
  echo "  6) Shell SCHEDULER           [$SCHEDULER]"

  echo -e "\n${BOLD}── Logs ────────────────────────────────${NC}"
  echo "  7) Logs APP"
  echo "  8) Logs PROXY"
  echo "  9) Logs DB"
  echo " 10) Logs QUEUE"

  echo -e "\n${BOLD}── Laravel / Artisan ───────────────────${NC}"
  echo " 11) Migrate"
  echo " 12) Optimize (cache)"
  echo " 13) Limpar cache"
  echo " 14) Artisan (comando livre)"

  echo -e "\n${BOLD}── Deploy & Infra ──────────────────────${NC}"
  echo " 20) DEPLOY COMPLETO (build + migrate + cache)"
  echo " 21) Update rápido (sem rebuild)"
  echo " 22) Reiniciar todos os containers"
  echo " 23) Parar tudo"
  echo " 24) Status (docker ps)"

  echo -e "\n   0) Sair\n"
}

# ─── Loop principal ────────────────────────────────────────────
while true; do
  menu
  read -rp "$(echo -e "${BOLD}Escolha: ${NC}")" opt
  case "${opt:-}" in
    1)  enter "$APP" ;;
    2)  enter "$PROXY" ;;
    3)  enter "$DB" ;;
    4)  mysql_cli ;;
    5)  enter "$QUEUE" ;;
    6)  enter "$SCHEDULER" ;;
    7)  logs "$APP" ;;
    8)  logs "$PROXY" ;;
    9)  logs "$DB" ;;
    10) logs "$QUEUE" ;;
    11) artisan migrate --force ;;
    12) artisan optimize ;;
    13) clear_cache ;;
    14)
      read -rp "php artisan " cmd
      # shellcheck disable=SC2086
      artisan $cmd
      ;;
    20) deploy ;;
    21) update ;;
    22) restart_all ;;
    23) stop_all ;;
    24) status ;;
    0)  ok "Até logo!"; exit 0 ;;
    *)  warn "Opção inválida." ;;
  esac
  echo -e "\n${YELLOW}Pressione ENTER para continuar...${NC}"
  read -r
done
