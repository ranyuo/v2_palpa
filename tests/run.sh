#!/usr/bin/env bash
# Corre las pruebas de la intranet sobre una base desechable cuyo nombre termina en _test.
# Uso:
#   PORTAL_CONFIG_FILE=/ruta/config.test.php bash tests/run.sh            # pruebas PHP
#   PORTAL_CONFIG_FILE=/ruta/config.test.php bash tests/run.sh --browser  # pruebas de navegador (Playwright)
# Cada prueba se ejecuta sobre el estado que deja tests/intranet.php (y tests/mail.php cuando lo necesita),
# por eso se vuelven a preparar antes de cada una.
set -u
cd "$(dirname "$0")/.." || exit 1
config=${PORTAL_CONFIG_FILE:-}
if [ -z "$config" ] || [ "$(basename "$config")" != config.test.php ]; then
  echo "Define PORTAL_CONFIG_FILE con una ruta a config.test.php." >&2; exit 2
fi
fails=0
run() { # nombre, comando...
  local name=$1; shift
  local out; out=$("$@" 2>&1); local code=$?
  if [ $code -eq 0 ]; then printf 'PASA   %-36s %s\n' "$name" "$(printf '%s\n' "$out" | grep -v '^\s*$' | tail -1 | cut -c1-160)"
  else fails=$((fails+1)); printf 'FALLA  %s\n' "$name"; printf '%s\n' "$out" | tail -25; fi
}
prep() { for p in "$@"; do php "tests/$p.php" >/dev/null 2>&1 || { echo "No se pudo preparar la base con tests/$p.php" >&2; php "tests/$p.php" 2>&1 | tail -10; return 1; }; done; }

if [ "${1:-}" = --browser ]; then
  base=${PORTAL_BASE_URL:-http://127.0.0.1:8084}
  port=${base##*:}
  php -S "127.0.0.1:$port" -t public_html >/tmp/palpa-php-server.log 2>&1 &
  server=$!; trap 'kill $server 2>/dev/null' EXIT; sleep 1
  for t in tests/browser-*.cjs; do
    b=$(basename "$t")
    case "$b" in
      browser-areas.cjs) deps="intranet area-compatibility";;
      browser-qa-correcciones.cjs) deps="intranet qa-correcciones";;
      browser-mail-settings.cjs|browser-smtp-form.cjs) deps="intranet smtp-settings";;
      browser-numbering.cjs) deps="intranet numbering";;
      *) deps="intranet";;
    esac
    # shellcheck disable=SC2086
    prep $deps || { fails=$((fails+1)); continue; }
    run "$b" env PORTAL_BASE_URL="$base" node "$t"
  done
else
  run intranet.php php tests/intranet.php
  for t in tests/*.php; do
    b=$(basename "$t")
    [ "$b" = intranet.php ] && continue
    if [ "$b" = mail.php ]; then prep intranet || { fails=$((fails+1)); continue; }
    else prep intranet mail || { fails=$((fails+1)); continue; }; fi
    run "$b" php "$t"
  done
fi
echo "fallos: $fails"
[ $fails -eq 0 ]
