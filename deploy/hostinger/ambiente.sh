#!/usr/bin/env bash
# Cria (ou atualiza) um ambiente do SXData na VPS Hostinger: usuario proprio,
# pool PHP, banco, pastas, SELinux, configs do CodeIgniter e site no nginx.
# Rodar depois de setup-servidor.sh. Idempotente.
#
# Uso (como root, com a pasta deploy/ copiada para /tmp/sxdata-deploy):
#   bash ambiente.sh <ambiente> [dump.sql] [.env antigo]
#     <ambiente>  nome de ambientes/<ambiente>.env (ex.: evolucao, producao)
#     dump.sql    restaura so se o banco ainda nao existir; gere com
#                 pg_dump --no-owner --no-privileges (SQL puro)
#     .env antigo usado so se o ambiente ainda nao tiver .env
#
# O certificado (/etc/letsencrypt/live/$CERTIFICADO) precisa existir antes:
# copiado do servidor antigo ou emitido com certbot (ver README.md).
set -euo pipefail
H=$(cd "$(dirname "$0")" && pwd)   # deploy/hostinger
DEPLOY=$(dirname "$H")              # deploy/
AMB=${1:?informe o ambiente}
DUMP=${2:-}
ENV_ANTIGO=${3:-}
# shellcheck disable=SC1090
. "$H/ambientes/$AMB.env"

USR=sxdata-$AMB                     # usuario Linux do PHP deste ambiente
DB=sxdata_$AMB                      # banco e role do PostgreSQL
BASE=/var/www/$AMB
P=$BASE/painel
A=$BASE/app
VAR=/var/lib/sxdata/$AMB
LOG=/var/log/sxdata/$AMB
SOCK=/run/php-fpm/sxdata-$AMB.sock
SENHA_DB=/root/.sxdata_${AMB}_db_password
CERT=/etc/letsencrypt/live/$CERTIFICADO

[ -f "$CERT/fullchain.pem" ] || { echo "Falta o certificado $CERT (ver README.md)"; exit 1; }

echo "== [$AMB] Usuario e pastas"
id "$USR" &>/dev/null || useradd -r -M -d "$VAR" -s /sbin/nologin "$USR"
mkdir -p "$P" "$A" "$VAR/sessions" "$LOG"
chown "$USR:$USR" "$VAR/sessions" "$LOG" && chmod 700 "$VAR/sessions"
chmod 755 /var/www "$BASE"

echo "== [$AMB] Pool PHP"
cat > /etc/php-fpm.d/sxdata-$AMB.conf <<POOL
[sxdata-$AMB]
user = $USR
group = $USR
listen = $SOCK
listen.owner = nginx
listen.group = nginx
listen.mode = 0660
pm = ondemand
pm.max_children = $PHP_MAX_CHILDREN
pm.process_idle_timeout = 30s
pm.max_requests = 500
request_terminate_timeout = 320s
php_admin_value[error_log] = $LOG/php-error.log
php_admin_flag[log_errors] = on
php_admin_value[session.save_path] = $VAR/sessions
php_admin_value[upload_tmp_dir] = $VAR/tmp
php_admin_value[sys_temp_dir] = $VAR/tmp
POOL
install -d -m 700 -o "$USR" -g "$USR" "$VAR/tmp"

echo "== [$AMB] Banco"
[ -f "$SENHA_DB" ] || { head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 32 > "$SENHA_DB"; chmod 600 "$SENHA_DB"; }
SENHA=$(cat "$SENHA_DB")
cd /tmp
sudo -u postgres psql -v ON_ERROR_STOP=1 -q <<SQL
DO \$\$ BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '$DB') THEN
    CREATE ROLE $DB LOGIN PASSWORD '$SENHA';
  ELSE
    ALTER ROLE $DB PASSWORD '$SENHA';
  END IF;
END \$\$;
SQL
if ! sudo -u postgres psql -lqt | cut -d'|' -f1 | grep -qw "$DB"; then
    sudo -u postgres createdb -O "$DB" -E UTF8 -T template0 --locale=C.UTF-8 "$DB"
    sudo -u postgres psql -q -d "$DB" -c "REVOKE ALL ON SCHEMA public FROM PUBLIC"
    if [ -n "$DUMP" ]; then
        echo "== [$AMB] Restaurando $DUMP"
        # O dump deve ser gerado com --no-owner --no-privileges (o dono antigo,
        # sxdata ou usuarios do cPanel, nao existe aqui): tudo e criado ja
        # como o role deste ambiente. \restrict so existe no psql mais novo.
        sed -e '/^.restrict /d' -e '/^.unrestrict /d' "$DUMP" \
            | sudo -u postgres psql -v ON_ERROR_STOP=1 -q --single-transaction \
                -d "$DB" -c "SET ROLE $DB" -f - >/dev/null \
            || { sudo -u postgres dropdb "$DB"; echo "Falha ao restaurar o dump"; exit 1; }
    fi
fi

echo "== [$AMB] Configs do CodeIgniter"
mkdir -p "$P/application/config"
sed "s#^\$config\['sess_save_path'\].*#\$config['sess_save_path'] = '$VAR/sessions';#" \
    "$DEPLOY/$CONFIG_PHP" > "$P/application/config/config.php"
sed -e "s/__DB_PASSWORD__/$SENHA/" \
    -e "s/'username' => 'sxdata'/'username' => '$DB'/" \
    -e "s/'database' => 'sxdata_db'/'database' => '$DB'/" \
    "$DEPLOY/evolucao/database.php.tpl" > "$P/application/config/database.php"
if [ ! -f "$P/.env" ]; then
    if [ -n "$ENV_ANTIGO" ]; then
        cp "$ENV_ANTIGO" "$P/.env"
    else
        printf '# SXData - ambiente %s\nOPENAI_API_KEY=\nAPI_TOKEN_SECRET=%s\n' \
            "$AMB" "$(head -c 64 /dev/urandom | sha256sum | cut -d' ' -f1)" > "$P/.env"
    fi
fi
chown root:"$USR" "$P/.env" "$P/application/config/database.php"
chmod 640 "$P/.env" "$P/application/config/database.php"

echo "== [$AMB] SELinux"
for p in "$P(\.novo|\.anterior)?/uploads(/.*)?" \
         "$P(\.novo|\.anterior)?/application/(cache|logs)(/.*)?" \
         "$VAR(/.*)?"; do
    semanage fcontext -a -t httpd_sys_rw_content_t "$p" 2>/dev/null \
        || semanage fcontext -m -t httpd_sys_rw_content_t "$p"
done
semanage fcontext -a -t httpd_log_t "$LOG(/.*)?" 2>/dev/null || true
restorecon -R /var/www "$VAR" "$LOG"

echo "== [$AMB] Site no nginx"
SSL="    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    ssl_certificate     $CERT/fullchain.pem;
    ssl_certificate_key $CERT/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
    include /etc/nginx/snippets/sxdata-seguranca.conf;"
# Desafio do Let's Encrypt (renovacao por webroot) e o resto vai para HTTPS
ACME='    location ^~ /.well-known/acme-challenge/ { root /var/www/letsencrypt; default_type text/plain; }'
install -d -m 755 /var/www/letsencrypt
{
cat <<NGX
# SXData - ambiente $AMB. Gerado por deploy/hostinger/ambiente.sh: nao editar
# aqui, edite o script e rode de novo.
# Painel + API: $DOMINIO_PAINEL   |   App web (PWA): $DOMINIO_APP

server {
    listen 80;
    listen [::]:80;
    server_name $DOMINIO_PAINEL $DOMINIO_APP $DOMINIOS_REDIRECIONA;
$ACME
    location / { return 301 https://\$host\$request_uri; }
}
NGX
if [ -n "$DOMINIOS_REDIRECIONA" ]; then
cat <<NGX

server {
$SSL
    server_name $DOMINIOS_REDIRECIONA;
    return 301 https://$DOMINIO_PAINEL\$request_uri;
}
NGX
fi
cat <<NGX

server {
$SSL
    server_name $DOMINIO_PAINEL;

    root $P;
    index index.php;

    # Fotos e audios enviados pelos aplicadores
    client_max_body_size 100M;

    access_log /var/log/nginx/$AMB-painel.access.log;
    error_log  /var/log/nginx/$AMB-painel.error.log;

    # Nada de codigo, configs, dumps ou metadados acessiveis pela web
    location ~ /\.                         { deny all; return 404; }
    location ~ ^/(application|system|vendor|sql|docs|tests|deploy|backups)(/|\$) { deny all; return 404; }
    location ~* \.(sql|md|rst|yml|lock|ppk|pem|env|log|tgz|gz|zip)\$ { deny all; return 404; }
    location = /composer.json              { deny all; return 404; }

    # Arquivos enviados: nunca executar PHP aqui. CORS liberado porque o app
    # web (outro subdominio) carrega essas imagens/audios via fetch.
    location ^~ /uploads/ {
        include /etc/nginx/snippets/sxdata-seguranca.conf;
        add_header Access-Control-Allow-Origin "*" always;
        location ~ \.php\$ { deny all; return 404; }
        try_files \$uri =404;
    }

    # Telas de login: limite de tentativas por IP (zona em 03-sxdata-limites.conf)
    location ~ ^/(admin/login|auth/login|api/auth/login)/?\$ {
        limit_req zone=sxdata_login burst=20 nodelay;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_pass unix:$SOCK;
        fastcgi_param CI_ENV production;
    }

    location / {
        try_files \$uri \$uri/ /index.php\$is_args\$args;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root/index.php;
        fastcgi_pass unix:$SOCK;
        fastcgi_param CI_ENV production;
        fastcgi_read_timeout 300;
    }

    # Qualquer outro .php direto e bloqueado (o CodeIgniter so usa index.php)
    location ~ \.php\$ { deny all; return 404; }

    location ~* \.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|ttf)\$ {
        expires 7d;
        try_files \$uri =404;
    }
}

server {
$SSL
    server_name $DOMINIO_APP;

    root $A;
    index index.html;

    access_log /var/log/nginx/$AMB-app.access.log;
    error_log  /var/log/nginx/$AMB-app.error.log;

    # Tudo sem cache (revalida pelo ETag, 304 quando nao mudou). Os nomes dos
    # arquivos do Flutter nao tem hash: se o Cloudflare ou o navegador
    # guardassem um main.dart.js velho, o service worker novo montaria o app
    # com pecas de versoes diferentes (ver aviso "Nova versao" no index.html).
    # O uso offline e garantido pelo service worker, nao por este cache.
    location / {
        include /etc/nginx/snippets/sxdata-seguranca.conf;
        add_header Cache-Control "no-cache";
        try_files \$uri \$uri/ /index.html;
    }
}
NGX
} > /etc/nginx/conf.d/sxdata-$AMB.conf

# Zona do limite de login, comum a todos os ambientes (20 por minuto por IP)
echo 'limit_req_zone $binary_remote_addr zone=sxdata_login:10m rate=20r/m;
limit_req_status 429;' > /etc/nginx/conf.d/03-sxdata-limites.conf

restorecon -R /etc/nginx
systemctl restart php-fpm
nginx -t && systemctl reload nginx
echo "== [$AMB] OK: painel em $P, app em $A, banco $DB, pool $USR"
