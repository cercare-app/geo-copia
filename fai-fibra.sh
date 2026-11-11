#!/usr/bin/env bash
# UN'EDIZIONE NUOVA DELLA FIBRA DI AGCOM SU REESTY (lotto «Valutazione 3», D-VAL-72, D-VAL-90).
# Lo lancia il workflow «Fibra AGCOM». Da UNA di queste due strade:
#  - LINK: il link del file scritto nell'email di AGCOM;
#  - FILE: il nome del file caricato dal File Manager di Aruba nella cartella <dati>/arrivi.
# Apre l'archivio (.7z o .zip; va bene anche il .gpkg già aperto), controlla
# che sia la fibra (la tabella e l'indice che il sito legge), lo carica come
# fibra.gpkg.nuovo e chiede al servizio di controllarlo e metterlo al suo posto:
# la fibra di prima resta come scorta.
set -euo pipefail
S="${GITHUB_STEP_SUMMARY:-/dev/null}"
ferma() { echo "FERMO: $*"; echo "- FERMO: $*" >> "$S"; exit 1; }
LINK="${LINK:-}"; FILE="${FILE:-}"; EDIZIONE="${EDIZIONE:-}"
[[ -n "$EDIZIONE" && ${#EDIZIONE} -le 60 ]] || ferma "scrivi l'edizione (per esempio Q3 2026)."
[[ -n "$LINK" || -n "$FILE" ]] || ferma "scrivi il link dell'email di AGCOM oppure il nome del file caricato su reesty in arrivi."
[[ -z "$LINK" || -z "$FILE" ]] || ferma "scrivi il link OPPURE il nome del file, non tutti e due."
LAVORO="${RUNNER_TEMP:-/tmp}/fibra"; rm -rf "$LAVORO"; mkdir -p "$LAVORO/aperto"; cd "$LAVORO"
echo "### Fibra AGCOM, edizione $EDIZIONE" >> "$S"

if [[ -n "$LINK" ]]; then
  [[ "$LINK" =~ ^https?:// ]] || ferma "il link deve cominciare con https://."
  curl --fail --silent --show-error --location --retry 3 -o archivio "$LINK" || ferma "il link non si scarica (scaduto?)."
  NOME_ARCHIVIO="${LINK%%\?*}"; NOME_ARCHIVIO="${NOME_ARCHIVIO##*/}"
else
  [[ "$FILE" =~ ^[A-Za-z0-9._-]{1,120}$ ]] || ferma "il nome del file può avere solo lettere, numeri, punti, trattini e trattini bassi."
  DATI="${GEO_DATI:-geo/dati}"; RADICE="${GEO_FTP_RADICE:-}"; TLS="${GEO_FTP_TLS:-si}"
  case "$TLS" in si) O=(--ssl-reqd);; si-senza-verifica) O=(--ssl-reqd --insecure);; *) O=();; esac
  NETRC="${RUNNER_TEMP:-/tmp}/netrc-geo"
  printf 'machine %s\nlogin %s\npassword %s\n' "$GEO_FTP_HOST" "$GEO_FTP_UTENTE" "$GEO_FTP_PASSWORD" > "$NETRC"; chmod 600 "$NETRC"
  curl --fail --silent --show-error --netrc-file "$NETRC" "${O[@]}" --retry 3 -o archivio "ftp://$GEO_FTP_HOST/${RADICE:+$RADICE/}$DATI/arrivi/$FILE" || { rm -f "$NETRC"; ferma "non trovo $FILE nella cartella arrivi su reesty."; }
  rm -f "$NETRC"
  NOME_ARCHIVIO="$FILE"
fi
echo "- Archivio: $(( $(stat -c %s archivio) / 1048576 )) MB" >> "$S"

if [[ "$(head -c 15 archivio)" == "SQLite format 3" ]]; then
  mv archivio aperto/fibra.gpkg
else
  command -v 7z > /dev/null || sudo apt-get install -y -qq p7zip-full > /dev/null
  7z x -y -oaperto archivio > /dev/null || ferma "l'archivio $NOME_ARCHIVIO non si apre."
  rm -f archivio
fi
GPKG=$(find aperto -type f -iname '*.gpkg' -printf '%s %p\n' | sort -nr | head -1 | cut -d' ' -f2-)
[[ -n "$GPKG" ]] || ferma "nell'archivio non c'è un file .gpkg."
python3 - "$GPKG" <<'P' || ferma "il file non è la fibra che il sito sa leggere (vedi sopra)."
import sqlite3, sys
db = sqlite3.connect('file:%s?mode=ro' % sys.argv[1], uri=True)
colonne = {r[1] for r in db.execute('PRAGMA table_info("grid20_speed_down")')}
attese = {'fid', 'grid_id_20m', 'speed_down_exp', 'speed_down_max', 'infrastruttura_ftthb_all', 'infrastruttura_vdsl_all',
          'speed_down_exp_tech_text', 'second_speed_down_exp_tech_text', 'infrastrutture_ftth', 'pro_com'}
mancano = sorted(attese - colonne)
indice = db.execute("SELECT count(*) FROM sqlite_master WHERE name = 'rtree_grid20_speed_down_SHAPE'").fetchone()[0]
if mancano or not indice:
    print('mancano:', ', '.join(mancano + ([] if indice else ['indice rtree_grid20_speed_down_SHAPE'])))
    sys.exit(1)
print('tracciato ok')
P
mv "$GPKG" fibra.gpkg
echo "- File della fibra: $(( $(stat -c %s fibra.gpkg) / 1048576 )) MB, tracciato giusto" >> "$S"
bash "$GITHUB_WORKSPACE/carica.sh" fibra.gpkg fibra
python3 "$GITHUB_WORKSPACE/cliente.py" controlla fibra fibra.gpkg "$EDIZIONE" | tee -a "$S"