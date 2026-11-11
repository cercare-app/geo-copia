#!/usr/bin/env bash
# CARICA UN FILE SU REESTY VIA FTP (lotto «Valutazione 3», D-VAL-86), col nome provvisorio.
# Uso: bash carica.sh FILE.gpkg NOME   →   <cartella dei dati>/NOME.gpkg.nuovo
# Se il viaggio si interrompe riprende da dove era arrivato. Non stampa mai
# indirizzo, utente o password dell'FTP.
set -euo pipefail
FILE="$1"; NOME="$2"
DATI="${GEO_DATI:-geo/dati}"
RADICE="${GEO_FTP_RADICE:-}"
TLS="${GEO_FTP_TLS:-si}"
ferma() { echo "FERMO: $*"; echo "- FERMO: $*" >> "${GITHUB_STEP_SUMMARY:-/dev/null}"; exit 1; }
[[ "$NOME" =~ ^(fibra|osm-[a-z0-9][a-z0-9-]{1,40})$ ]] || ferma "nome «$NOME» non valido."
[[ "$DATI" =~ ^[a-z0-9][a-z0-9/-]{0,80}$ && "$DATI" != *..* ]] || ferma "la variabile GEO_DATI non va bene."
[[ -z "$RADICE" || ( "$RADICE" =~ ^[A-Za-z0-9._/-]{1,80}$ && "$RADICE" != *..* ) ]] || ferma "la variabile GEO_FTP_RADICE non va bene."
[[ -n "${GEO_FTP_HOST:-}" && -n "${GEO_FTP_UTENTE:-}" && -n "${GEO_FTP_PASSWORD:-}" ]] || ferma "mancano i Secrets GEO_FTP_HOST, GEO_FTP_UTENTE o GEO_FTP_PASSWORD (Settings → Secrets and variables → Actions)."
case "$TLS" in
  si) OPZ_TLS=(--ssl-reqd) ;;
  si-senza-verifica) OPZ_TLS=(--ssl-reqd --insecure) ;;
  no) OPZ_TLS=() ;;
  *) ferma "la variabile GEO_FTP_TLS vale «$TLS»: deve essere si, si-senza-verifica oppure no." ;;
esac
NETRC="${RUNNER_TEMP:-/tmp}/netrc-geo"
printf 'machine %s\nlogin %s\npassword %s\n' "$GEO_FTP_HOST" "$GEO_FTP_UTENTE" "$GEO_FTP_PASSWORD" > "$NETRC"; chmod 600 "$NETRC"
URL="ftp://$GEO_FTP_HOST/${RADICE:+$RADICE/}$DATI/$NOME.gpkg.nuovo"
inizio=$(date +%s)
for giro in 1 2 3 4 5; do
  # il primo giro parte da zero (un .nuovo vecchio si sovrascrive), gli altri riprendono
  if [[ $giro -eq 1 ]]; then RIPRESA=(); else RIPRESA=(-C -); fi
  if esito=$(curl --fail --silent --show-error --netrc-file "$NETRC" "${OPZ_TLS[@]}" --ftp-create-dirs --connect-timeout 30 "${RIPRESA[@]}" -T "$FILE" "$URL" 2>&1); then
    rm -f "$NETRC"
    secondi=$(( $(date +%s) - inizio ))
    echo "Caricato $NOME.gpkg.nuovo ($(( $(stat -c %s "$FILE") / 1048576 )) MB in $secondi s)."
    echo "- Caricato $NOME.gpkg.nuovo: $(( $(stat -c %s "$FILE") / 1048576 )) MB in $secondi s" >> "${GITHUB_STEP_SUMMARY:-/dev/null}"
    exit 0
  fi
  esito="${esito//$GEO_FTP_HOST/(server FTP)}"
  if [[ "$TLS" == "si" && "$esito" == *SSL* ]]; then
    rm -f "$NETRC"
    ferma "l'FTP cifrato non è andato ($esito). Se il certificato dell'FTP di Aruba ha un altro nome, metti la variabile GEO_FTP_TLS a si-senza-verifica; se Aruba non accetta l'FTP cifrato, a no."
  fi
  echo "Giro $giro non riuscito ($esito): riprovo fra 30 secondi."
  sleep 30
done
rm -f "$NETRC"
ferma "il caricamento di $NOME non è riuscito dopo 5 giri: $esito"