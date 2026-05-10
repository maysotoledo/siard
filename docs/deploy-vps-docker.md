# Deploy do SIARD em VPS com Docker

Este deploy usa Docker Compose com:

- Caddy recebendo `80/443` e emitindo HTTPS automaticamente.
- Container `web` com Nginx + PHP-FPM.
- Containers separados para `queue` e `scheduler`.
- MySQL interno com volume persistente.
- Ollama interno para os recursos de IA.

## 1. DNS

No painel do seu domínio, crie um registro `A` apontando para o IP da VPS:

```txt
comprovante-pix.site -> IP_DA_VPS
```

A porta `80` e a porta `443` precisam estar liberadas no firewall da VPS.

Em Ubuntu com UFW:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

## 2. Código no VPS

```bash
git clone SEU_REPOSITORIO_GIT siard
cd siard
```

Copie o `.env.production` gerado na sua máquina local para o VPS como `.env`:

```bash
scp .env.production root@IP_DO_VPS:/opt/siard/.env
```

Depois confira o `.env` no VPS:

```bash
nano .env
```

Preencha pelo menos:

- `APP_URL`
- `APP_DOMAIN`
- `APP_TIMEZONE=America/Sao_Paulo`
- `APP_KEY`
- `DB_PASSWORD`
- `DB_ROOT_PASSWORD`
- `OLLAMA_URL=http://ollama:11434`
- `OLLAMA_MODEL=llama3.2:3b`
- SMTP (`MAIL_*`)
- Mercado Pago (`MERCADO_PAGO_*`)

Gere uma chave para `APP_KEY`:

```bash
openssl rand -base64 32
```

No `.env`, use o valor assim:

```env
APP_KEY=base64:VALOR_GERADO
```

## 3. Build e primeira subida

```bash
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d db
docker compose -f docker-compose.prod.yml run --rm web php artisan migrate --force
docker compose -f docker-compose.prod.yml run --rm web php artisan storage:link
docker compose -f docker-compose.prod.yml run --rm web php artisan optimize
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec ollama ollama pull llama3.2:3b
```

Se sua VPS usa o binário antigo, troque `docker compose` por `docker-compose`.

## 4. Conferir logs

```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs -f proxy web queue scheduler ollama
```

## 5. Atualizar depois

```bash
git pull
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml run --rm web php artisan migrate --force
docker compose -f docker-compose.prod.yml run --rm web php artisan optimize
docker compose -f docker-compose.prod.yml up -d
```

## 6. Ollama

O container do Ollama sobe junto com o compose de produção. Para baixar ou atualizar o modelo local:

```bash
docker compose -f docker-compose.prod.yml exec ollama ollama pull llama3.2:3b
```

Se a VPS for pequena, deixe o `OLLAMA_URL` apontando para outro servidor com Ollama.
