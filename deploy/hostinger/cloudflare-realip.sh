#!/usr/bin/env bash
# Com o proxy do Cloudflare ligado, toda conexao chega de um IP do Cloudflare.
# Este script grava as faixas oficiais dele no nginx para que o IP real do
# visitante (cabecalho CF-Connecting-IP) apareca nos logs, no fail2ban e no
# limite de tentativas de login. Sem o proxy ligado, nao muda nada.
set -euo pipefail
OUT=/etc/nginx/conf.d/02-cloudflare-realip.conf
TMP=$(mktemp)
trap 'rm -f "$TMP"' EXIT

{
    echo "# Gerado por sxdata-cloudflare-realip (timer semanal). Nao editar."
    for lista in ips-v4 ips-v6; do
        curl -fsS --max-time 20 "https://www.cloudflare.com/$lista" | grep -E '^[0-9a-f:.]+/[0-9]+$' \
            | sed 's/^/set_real_ip_from /; s/$/;/'
    done
    echo "real_ip_header CF-Connecting-IP;"
} > "$TMP"

# Lista vazia ou cortada: mantem a anterior
[ "$(grep -c set_real_ip_from "$TMP")" -ge 10 ] || { echo "lista do Cloudflare incompleta"; exit 1; }
cmp -s "$TMP" "$OUT" 2>/dev/null && exit 0
install -m 644 "$TMP" "$OUT"
restorecon "$OUT" 2>/dev/null || true
nginx -t -q && systemctl reload nginx 2>/dev/null || true
