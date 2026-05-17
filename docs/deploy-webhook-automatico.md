# Deploy automatico via webhook

Este projeto inclui os mesmos componentes usados no VPS atual para fazer deploy automatico apos push na branch `main`.

## Arquivos versionados

- `.githooks/post-push`: hook local que chama o webhook depois de `git push`.
- `scripts/install-local-deploy-hook.sh`: instala o hook local neste clone.
- `scripts/vps/siard-deploy`: faz pull/reset em `/opt/siard-repo`, copia para `/opt/siard`, rebuilda containers e roda migrations/cache.
- `scripts/vps/siard-deploy-webhook`: servidor HTTP local que valida assinatura GitHub e chama o deploy.
- `scripts/vps/install-deploy-webhook.sh`: instala os binarios e o systemd service no VPS.
- `deploy/systemd/siard-deploy-webhook.service`: unit systemd.
- `deploy/systemd/siard-deploy-webhook.env.example`: variaveis de ambiente.

## Instalar no VPS

No VPS, com o repositorio em `/opt/siard`:

```bash
cd /opt/siard
sudo scripts/vps/install-deploy-webhook.sh
```

O instalador cria `/etc/siard-deploy-webhook.env` com um segredo novo e imprime esse segredo no final.

Confirme:

```bash
systemctl status siard-deploy-webhook.service --no-pager
curl http://127.0.0.1:9001/health
```

O Caddyfile de producao ja encaminha `/github` e `/health` para `host.docker.internal:9001`.

## Instalar o hook local

No seu clone local:

```bash
scripts/install-local-deploy-hook.sh https://siard.online/github main
```

Informe o segredo exibido pelo instalador do VPS. O segredo fica em `.git/config` local, fora do Git.

Depois disso, todo:

```bash
git push origin main
```

aciona o deploy no VPS.

## Webhook direto no GitHub

Como alternativa ao hook local, configure no GitHub:

- Payload URL: `https://SEU_DOMINIO/github`
- Content type: `application/json`
- Secret: valor de `SIARD_WEBHOOK_SECRET`
- Events: `Just the push event`
- Active: marcado

Com isso, pushes feitos por qualquer maquina tambem acionam o deploy.
