#!/usr/bin/env bash
# Publica uma versao nova do painel ou do app web de um ambiente na VPS
# Hostinger. Instalado como /usr/local/bin/sxdata-publicar.
# Uso: sudo /usr/local/bin/sxdata-publicar <ambiente> painel /tmp/painel.tgz
#      sudo /usr/local/bin/sxdata-publicar <ambiente> app    /tmp/app-web.tgz
#
# Troca a pasta inteira (sem sobrar arquivo velho), preservando o que e do
# servidor: uploads, .env, configs, segredo dos tokens e logs. A versao
# anterior fica em <pasta>.anterior para voltar rapido se precisar.
set -euo pipefail
AMB=${1:?ambiente}
ALVO=${2:?painel ou app}
PACOTE=${3:?pacote .tgz}
USR=sxdata-$AMB
id "$USR" &>/dev/null || { echo "ambiente $AMB nao existe (rode ambiente.sh)"; exit 1; }

case "$ALVO" in
    painel) DIR=/var/www/$AMB/painel ;;
    app)    DIR=/var/www/$AMB/app ;;
    *) echo "alvo deve ser painel ou app"; exit 1 ;;
esac

NOVO=$DIR.novo
rm -rf "$NOVO" && mkdir -p "$NOVO"
tar -xzf "$PACOTE" -C "$NOVO"

if [ "$ALVO" = painel ]; then
    # uploads pode ser grande: move em vez de copiar
    [ -d "$DIR/uploads" ] && rm -rf "$NOVO/uploads" && mv "$DIR/uploads" "$NOVO/uploads"
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
    chown -R "$USR:$USR" "$NOVO/uploads" "$NOVO/application/cache" "$NOVO/application/logs"
    chown root:"$USR" "$NOVO/.env" "$NOVO/application/config/database.php" 2>/dev/null || true
    chmod 640 "$NOVO/.env" "$NOVO/application/config/database.php" 2>/dev/null || true
else
    chown -R root:root "$NOVO"
fi
find "$NOVO" -type d -exec chmod a+rx {} +
find "$NOVO" -type f ! -perm -o=r ! -name .env ! -name database.php ! -name api_token_secret.php \
    -exec chmod a+r {} +

rm -rf "$DIR.anterior"
[ -d "$DIR" ] && mv "$DIR" "$DIR.anterior"
mv "$NOVO" "$DIR"

restorecon -R "$DIR"
# Limpa o opcache (o caminho e o mesmo, mas os arquivos mudaram)
[ "$ALVO" = painel ] && systemctl reload php-fpm
echo "Publicado $AMB/$ALVO em $DIR (versao anterior em $DIR.anterior)"
