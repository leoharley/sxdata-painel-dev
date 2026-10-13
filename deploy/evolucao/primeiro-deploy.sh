#!/usr/bin/env bash
# Primeira publicacao: configs do servidor + painel + app. Rodar apos setup.sh.
set -euo pipefail
D=/tmp/sxdata-deploy
P=/var/www/sxdata-painel

mkdir -p $P/application/config
cp $D/config.php $P/application/config/config.php
sed "s/__DB_PASSWORD__/$(cat /root/.sxdata_db_password)/" $D/database.php.tpl > $P/application/config/database.php

if [ ! -f $P/.env ]; then
    cat > $P/.env <<ENV
# SXData - ambiente de evolucao (sxdata.online)
OPENAI_API_KEY=
API_TOKEN_SECRET=$(head -c 64 /dev/urandom | sha256sum | cut -d' ' -f1)
ENV
fi

install -m 755 $D/publicar.sh /usr/local/bin/sxdata-publicar
/usr/local/bin/sxdata-publicar painel $D/painel.tgz
/usr/local/bin/sxdata-publicar app $D/app-web.tgz
