#!/usr/bin/env bash
# Migra o ambiente de evolucao (sxdata.online) da VPS Oracle para a VPS
# Hostinger. Roda nesta maquina (Git Bash, na raiz do sxdata-painel-dev).
# NAO toca na producao (sxdata.com.br).
#
#   bash deploy/hostinger/migrar-evolucao-da-oracle.sh servidor   # 1x: setup-servidor.sh
#   bash deploy/hostinger/migrar-evolucao-da-oracle.sh tudo       # dados + painel + app
#   bash deploy/hostinger/migrar-evolucao-da-oracle.sh dados      # so ressincroniza banco/uploads
#
# "dados" APAGA o banco de evolucao da Hostinger e restaura o da Oracle: use
# para trazer o que mudou na Oracle ate a hora de trocar o DNS.
set -euo pipefail

ORACLE=opc@163.176.165.227
ORACLE_KEY=${ORACLE_KEY:-$HOME/.ssh/sxdata_vps.pem}
NOVA=${SXDATA_HOST:-sxadmin@189.24.17.46}
KEY=${SXDATA_KEY:-$HOME/.ssh/sxdata_hostinger}
AMB=evolucao
RAIZ=$(cd "$(dirname "$0")/../.." && pwd)
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

o()    { ssh -o BatchMode=yes -i "$ORACLE_KEY" "$ORACLE" "$@"; }
n()    { ssh -o BatchMode=yes -i "$KEY" "$NOVA" "$@"; }
para() { scp -q -i "$KEY" "$@" "$NOVA:/tmp/sxdata-migracao/"; }

enviar_deploy() {
    (cd "$RAIZ/deploy" && tar -czf "$TMP/deploy.tgz" evolucao hostinger)
    n "sudo rm -rf /tmp/sxdata-deploy /tmp/sxdata-migracao && mkdir -p -m 700 /tmp/sxdata-deploy /tmp/sxdata-migracao"
    para "$TMP/deploy.tgz"
    n "tar -xzf /tmp/sxdata-migracao/deploy.tgz -C /tmp/sxdata-deploy"
}

servidor() {
    enviar_deploy
    local chaves=("$(cat "$KEY.pub")")
    [ -f "$HOME/.ssh/id_ed25519.pub" ] && chaves+=("$(cat "$HOME/.ssh/id_ed25519.pub")")
    n "sudo bash /tmp/sxdata-deploy/hostinger/setup-servidor.sh $(printf "'%s' " "${chaves[@]}")"
}

baixar_oracle() {
    echo "== Copiando dados da Oracle"
    o "cd /tmp && sudo -u postgres pg_dump --no-owner --no-privileges -d sxdata_db" > "$TMP/dump.sql"
    o "sudo tar -czf - -C /var/www/sxdata-painel uploads" > "$TMP/uploads.tgz"
    o "sudo tar -czf - -C /var/www/sxdata-painel .env \$(sudo test -f /var/www/sxdata-painel/application/cache/api_token_secret.php && echo application/cache/api_token_secret.php)" > "$TMP/segredos.tgz"
    o "sudo tar -czf - -C /etc letsencrypt" > "$TMP/letsencrypt.tgz"
    o "sudo tar -czf - -C /var/www/sxdata-app ." > "$TMP/app-web.tgz"
    grep -q 'CREATE TABLE' "$TMP/dump.sql" || { echo "dump vazio"; exit 1; }
    echo "   dump $(du -h "$TMP/dump.sql" | cut -f1), uploads $(du -h "$TMP/uploads.tgz" | cut -f1), app $(du -h "$TMP/app-web.tgz" | cut -f1)"
    para "$TMP/dump.sql" "$TMP/uploads.tgz" "$TMP/segredos.tgz" "$TMP/letsencrypt.tgz" "$TMP/app-web.tgz"
}

# Certificado atual (vale ate jan/2027) e renovacao por webroot, que funciona
# com ou sem o proxy do Cloudflare
certificados() {
    n "sudo bash -s" <<'SH'
set -e
M=/tmp/sxdata-migracao
if [ ! -d /etc/letsencrypt/live ]; then tar -xzf $M/letsencrypt.tgz -C /etc; fi
R=/etc/letsencrypt/renewal/painel.sxdata.online.conf
sed -i -E 's/^authenticator = .*/authenticator = webroot/; /^installer = /d' $R
grep -q '^webroot_path' $R || sed -i '/^\[renewalparams\]/a webroot_path = /var/www/letsencrypt,' $R
grep -q '^\[\[webroot_map\]\]' $R || printf '[[webroot_map]]\npainel.sxdata.online = /var/www/letsencrypt\napp.sxdata.online = /var/www/letsencrypt\nsxdata.online = /var/www/letsencrypt\nwww.sxdata.online = /var/www/letsencrypt\n' >> $R
grep -q '^post_hook' $R || sed -i '/^\[renewalparams\]/a post_hook = systemctl reload nginx' $R
restorecon -R /etc/letsencrypt
SH
}

restaurar_dados() {
    n "sudo bash -s" <<SH
set -e
M=/tmp/sxdata-migracao
P=/var/www/$AMB/painel
mkdir -p \$P/uploads
tar -xzf \$M/uploads.tgz -C \$P
tar -xzf \$M/segredos.tgz -C \$M
if [ -f \$M/application/cache/api_token_secret.php ]; then
    install -D -m 600 -o sxdata-$AMB -g sxdata-$AMB \$M/application/cache/api_token_secret.php \$P/application/cache/api_token_secret.php
fi
chown -R sxdata-$AMB:sxdata-$AMB \$P/uploads
restorecon -R \$P
SH
}

ambiente() {
    n "sudo bash /tmp/sxdata-deploy/hostinger/ambiente.sh $AMB /tmp/sxdata-migracao/dump.sql /tmp/sxdata-migracao/.env.oracle"
}

painel() {
    echo "== Publicando o painel (HEAD $(cd "$RAIZ" && git log -1 --format=%h))"
    (cd "$RAIZ" && git archive --format=tar.gz -o "$TMP/painel.tgz" HEAD)
    para "$TMP/painel.tgz"
    n "sudo /usr/local/bin/sxdata-publicar $AMB painel /tmp/sxdata-migracao/painel.tgz"
}

app() {
    echo "== Publicando o app web (o mesmo build que esta na Oracle)"
    n "sudo /usr/local/bin/sxdata-publicar $AMB app /tmp/sxdata-migracao/app-web.tgz"
}

limpar() { n "sudo rm -rf /tmp/sxdata-migracao /tmp/sxdata-deploy"; }

case "${1:-}" in
    servidor) servidor ;;
    tudo)
        enviar_deploy
        baixar_oracle
        n "cd /tmp/sxdata-migracao && tar -xzf segredos.tgz .env && mv .env .env.oracle"
        certificados
        ambiente
        painel
        restaurar_dados
        app
        limpar ;;
    dados)
        enviar_deploy
        baixar_oracle
        n "cd /tmp && sudo -u postgres dropdb --if-exists --force sxdata_$AMB"
        n "cd /tmp/sxdata-migracao && tar -xzf segredos.tgz .env && mv .env .env.oracle"
        ambiente
        restaurar_dados
        limpar ;;
    *) echo "uso: $0 servidor|tudo|dados"; exit 1 ;;
esac
echo "== OK"
