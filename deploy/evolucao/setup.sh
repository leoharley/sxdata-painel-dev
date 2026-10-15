#!/usr/bin/env bash
# Provisionamento da VPS de evolucao do SXData (Oracle Linux 9, 1 GB RAM).
# Idempotente: pode rodar de novo sem estragar o que ja foi feito.
# Uso: sudo bash setup.sh   (com os arquivos de deploy em /tmp/sxdata-deploy)
set -euo pipefail
D=/tmp/sxdata-deploy
PHP_STREAM=8.2
PG_STREAM=16

echo "== Fuso horario e swap extra (a imagem so traz ~1 GB; o dnf sozinho estoura a RAM)"
timedatectl set-timezone America/Sao_Paulo
if ! swapon --show | grep -q /swapfile2; then
    dd if=/dev/zero of=/swapfile2 bs=1M count=2048 status=none
    chmod 600 /swapfile2 && mkswap /swapfile2 >/dev/null && swapon /swapfile2
    grep -q '^/swapfile2' /etc/fstab || echo '/swapfile2 none swap sw 0 0' >> /etc/fstab
fi
echo 'vm.swappiness=10' > /etc/sysctl.d/99-sxdata.conf && sysctl -q -p /etc/sysctl.d/99-sxdata.conf

echo "== Pacotes"
dnf -y -q install oracle-epel-release-el9
dnf config-manager --set-enabled ol9_developer_EPEL   # vem desativado
dnf -y -q module enable php:$PHP_STREAM postgresql:$PG_STREAM
dnf -y -q install nginx php-fpm php-cli php-pgsql php-mbstring php-xml php-gd php-zip \
    php-intl php-bcmath php-process php-opcache postgresql-server postgresql-contrib \
    certbot python3-certbot-nginx policycoreutils-python-utils tar

echo "== Firewall"
firewall-cmd -q --permanent --add-service=http --add-service=https
firewall-cmd -q --reload

echo "== PHP"
cat > /etc/php.d/90-sxdata.ini <<'INI'
upload_max_filesize = 100M
post_max_size = 100M
memory_limit = 256M
max_execution_time = 300
date.timezone = America/Sao_Paulo
expose_php = Off
INI
# Pool proprio, enxuto para 1 GB de RAM
cat > /etc/php-fpm.d/sxdata.conf <<'POOL'
[sxdata]
user = nginx
group = nginx
listen = /run/php-fpm/sxdata.sock
listen.owner = nginx
listen.group = nginx
pm = ondemand
pm.max_children = 6
pm.process_idle_timeout = 30s
pm.max_requests = 300
php_admin_value[error_log] = /var/log/sxdata/php-error.log
php_admin_flag[log_errors] = on
POOL
[ -f /etc/php-fpm.d/www.conf ] && mv /etc/php-fpm.d/www.conf /etc/php-fpm.d/www.conf.desativado
systemctl enable -q --now php-fpm
systemctl restart php-fpm

echo "== PostgreSQL"
[ -f /var/lib/pgsql/data/PG_VERSION ] || postgresql-setup --initdb
cat > /var/lib/pgsql/data/conf.d.sxdata.conf <<'PG'
shared_buffers = 128MB
effective_cache_size = 384MB
work_mem = 4MB
maintenance_work_mem = 64MB
max_connections = 40
timezone = 'America/Sao_Paulo'
listen_addresses = 'localhost'
PG
chown postgres:postgres /var/lib/pgsql/data/conf.d.sxdata.conf
grep -q "conf.d.sxdata.conf" /var/lib/pgsql/data/postgresql.conf \
    || echo "include 'conf.d.sxdata.conf'" >> /var/lib/pgsql/data/postgresql.conf
# Conexoes TCP locais (o PHP usa localhost) por senha, nao ident
sed -i -E 's/^(host\s+all\s+all\s+(127\.0\.0\.1\/32|::1\/128)\s+)ident/\1scram-sha-256/' /var/lib/pgsql/data/pg_hba.conf
systemctl enable -q --now postgresql
systemctl restart postgresql

if [ ! -f /root/.sxdata_db_password ]; then
    head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 32 > /root/.sxdata_db_password
    chmod 600 /root/.sxdata_db_password
fi
DBPASS=$(cat /root/.sxdata_db_password)
cd /tmp
sudo -u postgres psql -v ON_ERROR_STOP=1 -q <<SQL
DO \$\$ BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'sxdata') THEN
    CREATE ROLE sxdata LOGIN PASSWORD '$DBPASS';
  ELSE
    ALTER ROLE sxdata PASSWORD '$DBPASS';
  END IF;
END \$\$;
SQL
if ! sudo -u postgres psql -lqt | cut -d'|' -f1 | grep -qw sxdata_db; then
    sudo -u postgres createdb -O sxdata -E UTF8 -T template0 --locale=C.UTF-8 sxdata_db
    echo "== Restaurando dump da producao"
    # \restrict/\unrestrict so existem no psql mais novo; os GRANTs sao para
    # usuarios do cPanel da producao que nao existem aqui (o painel usa o dono)
    sed -e '/^.restrict /d' -e '/^.unrestrict /d' \
        -e '/ TO sxdata_db;$/d' -e '/ TO sxdata_dbuser;$/d' $D/dump.sql \
        | sudo -u postgres psql -v ON_ERROR_STOP=1 -q --single-transaction -d sxdata_db >/dev/null \
        || { sudo -u postgres dropdb sxdata_db; echo "Falha ao restaurar o dump"; exit 1; }
fi

echo "== Diretorios e SELinux"
mkdir -p /var/www/sxdata-painel /var/www/sxdata-app /var/lib/sxdata/sessions /var/log/sxdata
# /var/log/php-fpm e do apache: o pool roda como nginx e nao gravaria la
chown nginx:nginx /var/log/sxdata
chown nginx:nginx /var/lib/sxdata/sessions && chmod 700 /var/lib/sxdata/sessions
# Onde o PHP pode escrever
for p in '/var/www/sxdata-painel(\.novo|\.anterior)?/uploads(/.*)?' \
         '/var/www/sxdata-painel(\.novo|\.anterior)?/application/(cache|logs)(/.*)?' \
         '/var/lib/sxdata/sessions(/.*)?'; do
    semanage fcontext -a -t httpd_sys_rw_content_t "$p" 2>/dev/null \
        || semanage fcontext -m -t httpd_sys_rw_content_t "$p"
done
semanage fcontext -a -t httpd_sys_content_t '/var/www/sxdata-app(\.novo|\.anterior)?(/.*)?' 2>/dev/null || true
semanage fcontext -a -t httpd_log_t '/var/log/sxdata(/.*)?' 2>/dev/null || true
restorecon -R /var/www /var/lib/sxdata /var/log/sxdata
# PHP -> PostgreSQL local e -> API da OpenAI
setsebool -P httpd_can_network_connect_db on
setsebool -P httpd_can_network_connect on

echo "== Nginx"
cp $D/sxdata.conf /etc/nginx/conf.d/sxdata.conf
restorecon /etc/nginx/conf.d/sxdata.conf
sed -i 's/^\(\s*\)#\?\s*server_tokens .*/\1server_tokens off;/' /etc/nginx/nginx.conf
grep -q 'server_tokens off' /etc/nginx/nginx.conf || sed -i 's/^\(\s*\)sendfile\s.*/&\n\1server_tokens off;/' /etc/nginx/nginx.conf
nginx -t
systemctl enable -q --now nginx
systemctl reload nginx

echo "== OK: PHP $(php -r 'echo PHP_VERSION;'), $(sudo -u postgres psql -tAc 'select version()' | cut -d' ' -f1-2)"
