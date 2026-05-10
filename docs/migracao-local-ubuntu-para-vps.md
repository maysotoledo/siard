# Migração do Ubuntu local para o VPS

Este roteiro assume que a produção atual roda em uma máquina Ubuntu local com Docker e que o domínio hoje chega nela via Cloudflare Tunnel.

## 1. Preparar o VPS

No VPS:

```bash
sudo apt update
sudo apt install -y git rsync
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

Clone o projeto:

```bash
cd /opt
git clone URL_DO_REPOSITORIO siard
cd /opt/siard
```

Na sua máquina local, envie o `.env.production` para o VPS como `.env`:

```bash
scp .env.production root@IP_DO_VPS:/opt/siard/.env
```

O `.env.production` deste projeto já está preparado para:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_TIMEZONE=America/Sao_Paulo`
- `APP_URL=https://siard.online`
- `APP_DOMAIN=siard.online`
- `DB_HOST=db`
- `DB_DATABASE=siard`
- `DB_USERNAME=siard`
- `DB_PASSWORD` e `DB_ROOT_PASSWORD` próprios do VPS
- mesmas chaves de SMTP, Mercado Pago e demais integrações do ambiente local

## 2. Exportar banco da produção local

Na máquina Ubuntu local atual, dentro da pasta do projeto atual:

```bash
docker compose ps
docker compose exec db mysqldump -u root -p NOME_DO_BANCO > siard-backup.sql
```

Se ela usa `docker-compose` antigo:

```bash
docker-compose ps
docker-compose exec db mysqldump -u root -p NOME_DO_BANCO > siard-backup.sql
```

Troque `NOME_DO_BANCO` pelo banco usado na produção local.

## 3. Copiar banco e arquivos para o VPS

Na sua máquina local:

```bash
scp siard-backup.sql root@IP_DO_VPS:/opt/siard/
rsync -avz storage/app/ root@IP_DO_VPS:/opt/siard/storage-app-backup/
```

Se o `storage/app` estiver dentro do container/volume Docker na máquina local, copie primeiro para fora do volume ou use `docker cp`.

## 4. Subir banco no VPS

No VPS:

```bash
cd /opt/siard
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d db
docker compose -f docker-compose.prod.yml exec -T db mysql -u root -p"$DB_ROOT_PASSWORD" siard < siard-backup.sql
```

Se a variável `$DB_ROOT_PASSWORD` não estiver carregada no shell, use:

```bash
set -a
source .env
set +a
docker compose -f docker-compose.prod.yml exec -T db mysql -u root -p"$DB_ROOT_PASSWORD" siard < siard-backup.sql
```

## 5. Restaurar storage

No VPS:

```bash
docker compose -f docker-compose.prod.yml up -d web
docker cp storage-app-backup/. "$(docker compose -f docker-compose.prod.yml ps -q web)":/var/www/storage/app/
docker compose -f docker-compose.prod.yml exec web chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
```

## 6. Rodar migrations e otimizar

```bash
docker compose -f docker-compose.prod.yml run --rm web php artisan migrate --force
docker compose -f docker-compose.prod.yml run --rm web php artisan storage:link
docker compose -f docker-compose.prod.yml run --rm web php artisan optimize
docker compose -f docker-compose.prod.yml up -d
```

## 7. Trocar o domínio

No Cloudflare Tunnel:

- Remova/desative o Tunnel que aponta para a máquina local, ou remova as rotas públicas dele.
- Configure o `cloudflared` no VPS com os hostnames públicos.
- Use `siard.online` para acesso ao sistema.
- Use `comprovante-pix.site` e `agenciadanoticia.online` para links públicos do IP Grabber.

O HTTPS público fica na borda da Cloudflare. O Caddy recebe tráfego HTTP interno vindo do túnel.

## 8. Conferir

```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs -f proxy web queue scheduler ollama
```

Acesse:

```txt
https://siard.online
```
