#!/usr/bin/env bash
# Provisionamento base da VPS Hostinger do SXData (AlmaLinux 10, KVM 2:
# 2 vCPU, 8 GB RAM). Roda uma vez por servidor; cada ambiente (evolucao,
# producao) e criado depois com ambiente.sh.
# Idempotente: pode rodar de novo sem estragar o que ja foi feito.
#
# Uso (como root, com esta pasta copiada para /tmp/sxdata-hostinger):
#   bash setup-servidor.sh "<chave publica ssh>" ["<outra chave>" ...]
set -euo pipefail
D=$(cd "$(dirname "$0")" && pwd)
ADMIN=sxadmin
[ $# -ge 1 ] || { echo "informe ao menos uma chave publica SSH"; exit 1; }

echo "== Fuso, nome e swap"
timedatectl set-timezone America/Sao_Paulo
hostnamectl set-hostname sxdata-vps
if ! swapon --show | grep -q /swapfile; then
    fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile >/dev/null && swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
echo 'vm.swappiness=10' > /etc/sysctl.d/99-sxdata.conf && sysctl -q -p /etc/sysctl.d/99-sxdata.conf

echo "== Pacotes"
dnf -y -q install epel-release
dnf -y -q upgrade
dnf -y -q install nginx php-fpm php-cli php-pgsql php-mbstring php-xml php-gd php-zip \
    php-intl php-bcmath php-process php-opcache php-sodium \
    postgresql-server postgresql-contrib \
    certbot python3-certbot-nginx fail2ban-firewalld dnf-automatic \
    policycoreutils-python-utils firewalld tar rsync

echo "== Usuario administrador ($ADMIN) com chave SSH e sudo"
id $ADMIN &>/dev/null || useradd -m -s /bin/bash $ADMIN
install -d -m 700 -o $ADMIN -g $ADMIN /home/$ADMIN/.ssh
: > /home/$ADMIN/.ssh/authorized_keys
for k in "$@"; do echo "$k" >> /home/$ADMIN/.ssh/authorized_keys; done
chown $ADMIN:$ADMIN /home/$ADMIN/.ssh/authorized_keys && chmod 600 /home/$ADMIN/.ssh/authorized_keys
echo "$ADMIN ALL=(ALL) NOPASSWD: ALL" > /etc/sudoers.d/90-$ADMIN && chmod 440 /etc/sudoers.d/90-$ADMIN
restorecon -R /home/$ADMIN/.ssh

echo "== SSH: so chave, sem root"
cat > /etc/ssh/sshd_config.d/01-sxdata.conf <<'SSH'
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
MaxAuthTries 4
LoginGraceTime 30
X11Forwarding no
AllowUsers sxadmin
SSH
sshd -t
# So aplica se o administrador ja entra com a chave (evita trancar o servidor)
[ -s /home/$ADMIN/.ssh/authorized_keys ] && systemctl reload sshd

echo "== Firewall: so SSH, HTTP e HTTPS"
systemctl enable -q --now firewalld
firewall-cmd -q --permanent --remove-service=cockpit 2>/dev/null || true
firewall-cmd -q --permanent --remove-service=dhcpv6-client 2>/dev/null || true
firewall-cmd -q --permanent --add-service=ssh --add-service=http --add-service=https
firewall-cmd -q --reload
systemctl disable -q --now cockpit.socket 2>/dev/null || true

echo "== Fail2ban (SSH) e atualizacoes de seguranca automaticas"
cat > /etc/fail2ban/jail.d/sxdata.local <<'F2B'
[DEFAULT]
bantime = 1h
findtime = 10m
maxretry = 5
banaction = firewallcmd-rich-rules
[sshd]
enabled = true
backend = systemd
F2B
systemctl enable -q --now fail2ban && systemctl restart fail2ban
sed -i -E 's/^upgrade_type\s*=.*/upgrade_type = security/; s/^apply_updates\s*=.*/apply_updates = yes/' \
    /etc/dnf/automatic.conf
systemctl enable -q --now dnf-automatic.timer

echo "== PHP"
cat > /etc/php.d/90-sxdata.ini <<'INI'
upload_max_filesize = 100M
post_max_size = 100M
memory_limit = 256M
max_execution_time = 300
date.timezone = America/Sao_Paulo
expose_php = Off
; Editor de questionario grande: 157 perguntas e 261 opcoes passam de 1000
; campos, e o PHP descarta o excedente SEM erro
max_input_vars = 10000
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
INI
# Cada ambiente tem seu pool (ambiente.sh); o www padrao nao e usado
[ -f /etc/php-fpm.d/www.conf ] && mv /etc/php-fpm.d/www.conf /etc/php-fpm.d/www.conf.desativado
systemctl enable -q php-fpm

echo "== PostgreSQL (ajustado para 8 GB de RAM, varios ambientes)"
[ -f /var/lib/pgsql/data/PG_VERSION ] || postgresql-setup --initdb
cat > /var/lib/pgsql/data/conf.d.sxdata.conf <<'PG'
shared_buffers = 1GB
effective_cache_size = 4GB
work_mem = 16MB
maintenance_work_mem = 256MB
max_connections = 100
random_page_cost = 1.1
timezone = 'America/Sao_Paulo'
listen_addresses = 'localhost'
log_min_duration_statement = 2000
PG
chown postgres:postgres /var/lib/pgsql/data/conf.d.sxdata.conf
grep -q "conf.d.sxdata.conf" /var/lib/pgsql/data/postgresql.conf \
    || echo "include 'conf.d.sxdata.conf'" >> /var/lib/pgsql/data/postgresql.conf
# Conexoes TCP locais (o PHP usa localhost) por senha, nao ident
sed -i -E 's/^(host\s+all\s+all\s+(127\.0\.0\.1\/32|::1\/128)\s+)ident/\1scram-sha-256/' /var/lib/pgsql/data/pg_hba.conf
systemctl enable -q --now postgresql
systemctl restart postgresql
setsebool -P httpd_can_network_connect_db on
# Chamadas do PHP para a API da OpenAI e servicos publicos (IBGE etc.)
setsebool -P httpd_can_network_connect on

echo "== Nginx"
install -m 644 "$D/nginx/00-gzip.conf" "$D/nginx/01-wasm.conf" /etc/nginx/conf.d/
# O mime.types novo ja pode trazer o wasm: entao o 01-wasm.conf sobra
grep -q 'application/wasm' /etc/nginx/mime.types && rm -f /etc/nginx/conf.d/01-wasm.conf
sed -i 's/^\(\s*\)#\?\s*server_tokens .*/\1server_tokens off;/' /etc/nginx/nginx.conf
grep -q 'server_tokens off' /etc/nginx/nginx.conf || sed -i 's/^\(\s*\)sendfile\s.*/&\n\1server_tokens off;/' /etc/nginx/nginx.conf
mkdir -p /etc/nginx/snippets
install -m 644 "$D/nginx/seguranca.conf" /etc/nginx/snippets/sxdata-seguranca.conf
restorecon -R /etc/nginx
systemctl enable -q nginx

echo "== IPs reais atras do Cloudflare (atualiza toda semana)"
install -m 755 "$D/cloudflare-realip.sh" /usr/local/sbin/sxdata-cloudflare-realip
/usr/local/sbin/sxdata-cloudflare-realip || echo "Aviso: nao baixou a lista do Cloudflare agora"
cat > /etc/systemd/system/sxdata-cloudflare-realip.service <<'U'
[Unit]
Description=Atualiza as faixas de IP do Cloudflare no nginx
[Service]
Type=oneshot
ExecStart=/usr/local/sbin/sxdata-cloudflare-realip
U
cat > /etc/systemd/system/sxdata-cloudflare-realip.timer <<'U'
[Unit]
Description=Atualizacao semanal das faixas de IP do Cloudflare
[Timer]
OnCalendar=weekly
Persistent=true
[Install]
WantedBy=timers.target
U

echo "== Publicacao e backup"
install -m 755 "$D/publicar.sh" /usr/local/bin/sxdata-publicar
install -m 755 "$D/backup.sh" /usr/local/sbin/sxdata-backup
install -d -m 700 /var/backups/sxdata
cat > /etc/systemd/system/sxdata-backup.service <<'U'
[Unit]
Description=Backup diario dos bancos e uploads do SXData
After=postgresql.service
[Service]
Type=oneshot
ExecStart=/usr/local/sbin/sxdata-backup
U
cat > /etc/systemd/system/sxdata-backup.timer <<'U'
[Unit]
Description=Backup diario do SXData (03:15)
[Timer]
OnCalendar=*-*-* 03:15:00
Persistent=true
[Install]
WantedBy=timers.target
U
systemctl daemon-reload
systemctl enable -q --now sxdata-cloudflare-realip.timer sxdata-backup.timer
systemctl enable -q --now certbot-renew.timer 2>/dev/null || true

nginx -t && systemctl restart nginx
echo "== OK: $(. /etc/os-release; echo $PRETTY_NAME), PHP $(php -r 'echo PHP_VERSION;'), $(sudo -u postgres psql -tAc 'show server_version'), $(nginx -v 2>&1)"
