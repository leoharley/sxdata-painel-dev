# VPS Hostinger do SXData

Servidor próprio do SXData: Hostinger KVM 2 (2 vCPU, 8 GB de RAM, 100 GB),
AlmaLinux 10, IP `189.24.17.46`. Ele substitui a VPS Oracle da evolução e
depois vai receber também a produção, que hoje está no cPanel compartilhado.

| Ambiente | Domínios | Pastas | Banco / usuário PHP |
|---|---|---|---|
| `evolucao` | painel.sxdata.online, app.sxdata.online (a raiz e o www redirecionam para o painel) | `/var/www/evolucao/{painel,app}` | `sxdata_evolucao` / `sxdata-evolucao` |
| `producao` | (ainda não migrada) | `/var/www/producao/{painel,app}` | `sxdata_producao` / `sxdata-producao` |

Cada ambiente tem seu próprio usuário Linux, pool PHP-FPM, banco e role no
PostgreSQL, pasta de sessões e logs (`/var/log/sxdata/<ambiente>`). Um
ambiente não lê os arquivos nem o banco do outro.

## Stack

- Nginx 1.26 com HTTP/2 e TLS do Let's Encrypt. A renovação é automática, por webroot, e funciona com ou sem o proxy do Cloudflare.
- PHP-FPM 8.3, um pool por ambiente.
- PostgreSQL 16, só em localhost, com acesso por senha (scram-sha-256).

## Segurança

- **SSH:** entra só o usuário `sxadmin`, por chave. Root e senha não entram mais. Se perder o acesso, use o console da VPS no hPanel.
- **Firewall (firewalld):** abre só as portas 22, 80 e 443. O Cockpit vem ligado na imagem e está desativado.
- **Fail2ban:** protege o SSH.
- **Atualizações:** as de segurança são aplicadas automaticamente pelo `dnf-automatic`.
- **SELinux em enforcing:** o PHP só escreve em `uploads/`, `application/cache`, `application/logs` e nas sessões.
- **Nginx:**
  - bloqueia código, configs e dumps;
  - não executa PHP em `uploads/`;
  - limita o login a 20 tentativas por minuto por IP;
  - envia os cabeçalhos HSTS, nosniff e X-Frame-Options.
- **Cloudflare:** as faixas de IP dele ficam em `/etc/nginx/conf.d/02-cloudflare-realip.conf`, atualizadas toda semana. Assim os logs, o fail2ban e o limite de login veem o IP real do visitante.

## Backups

O timer `sxdata-backup.timer` roda todo dia às 03:15 e grava, por ambiente,
o banco (`pg_dump -Fc`), os uploads e as configs em
`/var/backups/sxdata/<ambiente>/<data>/`, por 14 dias. Os snapshots da
Hostinger cobrem o disco inteiro.

## Scripts

| Script | Onde roda | Para quê |
|---|---|---|
| `setup-servidor.sh` | VPS, uma vez | pacotes, SSH, firewall, PHP, PostgreSQL, nginx base, timers |
| `ambiente.sh <amb> [dump] [.env]` | VPS | cria ou atualiza um ambiente (`ambientes/<amb>.env`) |
| `publicar.sh` → `sxdata-publicar <amb> painel\|app <tgz>` | VPS | troca a versão, preservando uploads e configs |
| `backup.sh` → `sxdata-backup` | VPS, pelo timer | backup diário |
| `cloudflare-realip.sh` | VPS, pelo timer | IPs do Cloudflare no nginx |
| `migrar-evolucao-da-oracle.sh` | esta máquina | migração da evolução a partir da Oracle |

Para publicar na evolução, o comando continua o mesmo:
`bash deploy/evolucao/publicar-local.sh painel|app|tudo`. Ele agora publica
na Hostinger, com a chave `~/.ssh/sxdata_hostinger` e o usuário `sxadmin`.

## Novo ambiente (produção)

1. Crie `ambientes/producao.env` com os domínios e o `CONFIG_PHP` da produção.
2. Coloque o certificado em `/etc/letsencrypt/live/<CERTIFICADO>`. Uma forma é apontar o DNS e rodar
   `certbot certonly --webroot -w /var/www/letsencrypt -d ...`, depois de um nginx temporário só na porta 80.
3. Gere o dump do banco com `pg_dump --no-owner --no-privileges` (SQL puro).
4. Rode `sudo bash ambiente.sh producao /caminho/dump.sql /caminho/.env`.
5. Publique o painel e o app com `sxdata-publicar producao ...` e copie os `uploads/`.

## Cloudflare

1. Adicione o domínio no Cloudflare e confira os registros importados. Os A de `@`, `www`, `painel` e `app` devem apontar para `189.24.17.46`.
2. Troque os nameservers na GoDaddy pelos dois que o Cloudflare indicar.
3. Em SSL/TLS, use o modo **Full (strict)**. O servidor tem certificado válido; o modo Flexible causa loop de redirecionamento.
4. Ligue o proxy (nuvem laranja) nos quatro registros.
