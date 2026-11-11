#!/usr/bin/env bash
# UNA REGIONE DI OPENSTREETMAP, DA GEOFABRIK A REESTY (lotto «Valutazione 3», D-VAL-70, D-VAL-88).
# Uso: bash fai-regione.sh REGIONE   (centro, isole, nord-est, nord-ovest, sud)
#  1. scarica l'estratto di Geofabrik e controlla la sua impronta md5;
#  2. lo converte (converti.py): un GeoPackage con gli strati che servono;
#  3. se pesa più della soglia (GEO_PESO_MAX_GB, di fabbrica 5) si ferma in rosso senza caricare;
#  4. se CARICA è «false» si ferma qui (solo misura);
#  5. lo carica su reesty col nome provvisorio e chiede al servizio di controllarlo e metterlo al suo posto.
set -euo pipefail
R="$1"
[[ "$R" =~ ^[a-z0-9][a-z0-9-]{1,40}$ ]] || { echo "FERMO: regione «$R» non valida."; exit 1; }
PESO_MAX="${GEO_PESO_MAX_GB:-5}"
[[ "$PESO_MAX" =~ ^[0-9]+([.][0-9]+)?$ ]] || { echo "FERMO: la variabile GEO_PESO_MAX_GB vale «$PESO_MAX»: serve un numero."; exit 1; }
FONTE="${GEO_GEOFABRIK:-https://download.geofabrik.de/europe/italy}"
S="${GITHUB_STEP_SUMMARY:-/dev/null}"
LAVORO="${RUNNER_TEMP:-/tmp}/osm-$R"; mkdir -p "$LAVORO"; cd "$LAVORO"
echo "### Regione $R" >> "$S"

inizio=$(date +%s)
curl --fail --silent --show-error --location --retry 3 -R -o "$R.osm.pbf" "$FONTE/$R-latest.osm.pbf"
curl --fail --silent --show-error --location --retry 3 -o "$R.md5" "$FONTE/$R-latest.osm.pbf.md5"
[[ "$(md5sum "$R.osm.pbf" | cut -d' ' -f1)" == "$(cut -d' ' -f1 "$R.md5")" ]] || { echo "FERMO: l'estratto di $R è arrivato rovinato (md5)."; echo "- FERMO: estratto rovinato (md5)" >> "$S"; exit 1; }
EDIZIONE=$(date -u -r "$R.osm.pbf" +%Y-%m-%d)
echo "- Estratto Geofabrik del $EDIZIONE: $(( $(stat -c %s "$R.osm.pbf") / 1048576 )) MB, scaricato in $(( $(date +%s) - inizio )) s" >> "$S"

inizio=$(date +%s)
python3 "$GITHUB_WORKSPACE/converti.py" "$R.osm.pbf" "osm-$R.gpkg" conteggi.json | tee -a "$S"
rm -f "$R.osm.pbf"
BYTE=$(stat -c %s "osm-$R.gpkg")
echo "- Conversione: $(( $(date +%s) - inizio )) s · conteggi $(cat conteggi.json)" >> "$S"
if python3 -c "import sys; sys.exit(0 if $BYTE <= float('$PESO_MAX') * 1024**3 else 1)"; then :; else
  echo "FERMO: osm-$R.gpkg pesa $(( BYTE / 1048576 )) MB, oltre la soglia di $PESO_MAX GB: non carico niente."
  echo "- FERMO: $(( BYTE / 1048576 )) MB, oltre la soglia di $PESO_MAX GB (variabile GEO_PESO_MAX_GB): non caricato" >> "$S"
  exit 1
fi
if [[ "${CARICA:-true}" != "true" ]]; then
  echo "- Solo misura: non caricato" >> "$S"
  exit 0
fi
bash "$GITHUB_WORKSPACE/carica.sh" "osm-$R.gpkg" "osm-$R"
python3 "$GITHUB_WORKSPACE/cliente.py" controlla "osm-$R" "osm-$R.gpkg" "$EDIZIONE" conteggi.json | tee -a "$S"