#!/usr/bin/env bash
# Backup diario de todos os ambientes do SXData nesta VPS (timer
# sxdata-backup.timer, 03:15). Instalado como /usr/local/sbin/sxdata-backup.
#
# Por ambiente: banco (pg_dump -Fc) + uploads + .env/configs, em
# /var/backups/sxdata/<ambiente>/AAAA-MM-DD_HHMM/. Guarda 14 dias.
# Os snapshots semanais da Hostinger cobrem o disco inteiro; este cobre o
# "apaguei sem querer ontem". Para restaurar o banco:
#   sudo -u postgres pg_restore --clean --if-exists --no-owner --role=sxdata_<amb> \
#       -d sxdata_<amb> banco.dump
set -euo pipefail
RAIZ=/var/backups/sxdata
DIAS=14
QUANDO=$(date +%F_%H%M)
cd /tmp

for env in /var/www/*/painel/.env; do
    [ -e "$env" ] || continue
    P=$(dirname "$env")
    AMB=$(basename "$(dirname "$P")")
    DEST=$RAIZ/$AMB/$QUANDO
    install -d -m 700 "$DEST"
    # A pasta e do root (700): o postgres escreve pela saida padrao
    sudo -u postgres pg_dump -Fc -d "sxdata_$AMB" > "$DEST/banco.dump.tmp" && mv "$DEST/banco.dump.tmp" "$DEST/banco.dump"
    tar -czf "$DEST/uploads.tgz" -C "$P" uploads
    tar -czf "$DEST/configs.tgz" -C "$P" .env application/config/config.php application/config/database.php \
        $( [ -f "$P/application/cache/api_token_secret.php" ] && echo application/cache/api_token_secret.php )
    chmod 600 "$DEST"/*
    find "$RAIZ/$AMB" -mindepth 1 -maxdepth 1 -type d -mtime +$DIAS -exec rm -rf {} +
    echo "backup $AMB: $(du -sh "$DEST" | cut -f1) em $DEST"
done
