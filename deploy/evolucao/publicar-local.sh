#!/usr/bin/env bash
# Publica no ambiente de evolucao (VPS Oracle, sxdata.online) a partir desta
# maquina. NAO toca na producao (painel.sxdata.com.br).
#
# Uso (Git Bash, na raiz do sxdata-painel-dev):
#   bash deploy/evolucao/publicar-local.sh painel   # commit atual (HEAD) do painel
#   bash deploy/evolucao/publicar-local.sh app      # build web do sxdata-app-dev
#   bash deploy/evolucao/publicar-local.sh tudo
#
# Variaveis opcionais: SXDATA_KEY (chave SSH), SXDATA_APP_DIR, FLUTTER.
set -euo pipefail

HOST=opc@163.176.165.227
API_URL=https://painel.sxdata.online/api
KEY=${SXDATA_KEY:-$HOME/.ssh/sxdata_vps.pem}
RAIZ=$(cd "$(dirname "$0")/../.." && pwd)
APP_DIR=${SXDATA_APP_DIR:-$RAIZ/../sxdata-app-dev}
FLUTTER=${FLUTTER:-flutter}
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

enviar() {  # enviar <alvo> <pacote>
    scp -q -i "$KEY" "$2" "$HOST:/tmp/sxdata-$1.tgz"
    ssh -i "$KEY" "$HOST" "sudo /usr/local/bin/sxdata-publicar $1 /tmp/sxdata-$1.tgz && rm -f /tmp/sxdata-$1.tgz"
}

publicar_painel() {
    cd "$RAIZ"
    if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
        echo "Aviso: ha alteracoes nao commitadas; sera publicado apenas o HEAD ($(git log -1 --format=%h))."
    fi
    git archive --format=tar.gz -o "$TMP/painel.tgz" HEAD
    enviar painel "$TMP/painel.tgz"
}

publicar_app() {
    # Compila numa copia fora da pasta "Evolução": o impellerc do Flutter nao
    # grava em caminhos com acento.
    mkdir -p "$TMP/app"
    (cd "$APP_DIR" && tar --exclude=./build --exclude=./.dart_tool --exclude=./.git \
        --exclude=./android --exclude=./ios --exclude=./linux --exclude=./macos --exclude=./windows \
        -cf - .) | tar -xf - -C "$TMP/app"
    cd "$TMP/app"
    # O git do SDK em D:\Flutter esta corrompido e o flutter nao descobre a
    # versao da engine sozinho; ela fica registrada no cache do proprio SDK.
    local sdk
    sdk=$(cd "$(dirname "$(command -v "$FLUTTER")")/.." && pwd)
    if [ -z "${FLUTTER_PREBUILT_ENGINE_VERSION:-}" ] && [ -f "$sdk/bin/cache/flutter.version.json" ]; then
        export FLUTTER_PREBUILT_ENGINE_VERSION=$(sed -n 's/.*"engineRevision": *"\([0-9a-f]*\)".*/\1/p' \
            "$sdk/bin/cache/flutter.version.json")
    fi
    # --no-tree-shake-icons: o font-subset.exe do SDK atual esta corrompido.
    "$FLUTTER" build web --release --no-tree-shake-icons --dart-define=API_BASE_URL=$API_URL
    "$sdk/bin/dart" run tool/patch_service_worker.dart build/web/flutter_service_worker.js
    grep -q "$API_URL" build/web/main.dart.js || { echo "Build sem a URL $API_URL"; exit 1; }
    (cd build/web && tar -czf "$TMP/app-web.tgz" .)
    enviar app "$TMP/app-web.tgz"
}

case "${1:-}" in
    painel) publicar_painel ;;
    app)    publicar_app ;;
    tudo)   publicar_painel; publicar_app ;;
    *) echo "uso: $0 painel|app|tudo"; exit 1 ;;
esac
