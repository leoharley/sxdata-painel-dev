#!/usr/bin/env bash
# Publica uma versao nova do painel e/ou do app web na VPS de evolucao.
# Uso: sudo bash publicar.sh painel /tmp/painel.tgz
#      sudo bash publicar.sh app    /tmp/app-web.tgz
#
# Troca a pasta inteira (sem sobrar arquivo velho), preservando o que e do
# servidor: uploads, .env, configs, segredo dos tokens e logs.
set -euo pipefail
ALVO=$1
PACOTE=$2

case "$ALVO" in
    painel) DIR=/var/www/sxdata-painel ;;
    app)    DIR=/var/www/sxdata-app ;;
    *) echo "alvo deve ser painel ou app"; exit 1 ;;
esac

NOVO=$DIR.novo
rm -rf "$NOVO" && mkdir -p "$NOVO"
tar -xzf "$PACOTE" -C "$NOVO"

if [ "$ALVO" = painel ]; then
    # uploads pode ser grande: move em vez de copiar
    [ -d "$DIR/uploads" ] && mv "$DIR/uploads" "$NOVO/uploads"
    for item in .env application/config/config.php application/config/database.php \
                application/cache/api_token_secret.php; do
        if [ -e "$DIR/$item" ]; then
            mkdir -p "$(dirname "$NOVO/$item")"
            cp -a "$DIR/$item" "$NOVO/$item"
        fi
    done
    mkdir -p "$NOVO/uploads/audio" "$NOVO/uploads/audios" "$NOVO/uploads/photos" \
             "$NOVO/application/cache" "$NOVO/application/logs"
    chown -R root:root "$NOVO"
    # O PHP so escreve onde precisa
    chown -R nginx:nginx "$NOVO/uploads" "$NOVO/application/cache" "$NOVO/application/logs"
    chown root:nginx "$NOVO/.env" "$NOVO/application/config/database.php" 2>/dev/null || true
    chmod 640 "$NOVO/.env" "$NOVO/application/config/database.php" 2>/dev/null || true
else
    chown -R root:root "$NOVO"
fi
find "$NOVO" -type d -exec chmod a+rx {} +

rm -rf "$DIR.anterior"
[ -d "$DIR" ] && mv "$DIR" "$DIR.anterior"
mv "$NOVO" "$DIR"

restorecon -R "$DIR"
[ "$ALVO" = painel ] && systemctl reload php-fpm
echo "Publicado $ALVO em $DIR (versao anterior em $DIR.anterior)"
