#!/usr/bin/env bash
# IL MARE, DA OSMDATA A REESTY (lotto «Valutazione 4», D-VAL-95).
# Lo lancia il workflow «Copia OpenStreetMap» insieme alle cinque regioni («mare» nell'elenco).
#  1. scarica i poligoni del mare di osmdata.openstreetmap.de (dati OpenStreetMap, licenza ODbL)
#     e controlla che l'archivio sia intero; se non lo è, aspetta e riprova da solo, come le regioni
#     (GEO_TENTATIVI tentativi, GEO_ATTESA_MINUTI minuti l'uno dall'altro; di fabbrica 6 e 10);
#  2. li ritaglia sull'Italia e li taglia a quadretti (mare.py): osm-mare.gpkg;
#  3. se pesa più della soglia (GEO_PESO_MAX_GB, di fabbrica 5) si ferma in rosso senza caricare;
#  4. se CARICA è «false» si ferma qui (solo misura);
#  5. lo carica su reesty col nome provvisorio e chiede al servizio di controllarlo e metterlo al suo posto.
set -euo pipefail
PESO_MAX="${GEO_PESO_MAX_GB:-5}"
[[ "$PESO_MAX" =~ ^[0-9]+([.][0-9]+)?$ ]] || { echo "FERMO: la variabile GEO_PESO_MAX_GB vale «$PESO_MAX»: serve un numero."; exit 1; }
FONTE="${GEO_MARE_FONTE:-https://osmdata.openstreetmap.de/download/water-polygons-split-4326.zip}"
S="${GITHUB_STEP_SUMMARY:-/dev/null}"
LAVORO="${RUNNER_TEMP:-/tmp}/osm-mare"; mkdir -p "$LAVORO"; cd "$LAVORO"
echo "### Mare" >> "$S"

TENTATIVI="${GEO_TENTATIVI:-6}"; ATTESA="${GEO_ATTESA_MINUTI:-10}"
[[ "$TENTATIVI" =~ ^[1-9][0-9]?$ && "$ATTESA" =~ ^[0-9]{1,3}$ ]] || { echo "FERMO: GEO_TENTATIVI o GEO_ATTESA_MINUTI non sono numeri."; exit 1; }
inizio=$(date +%s)
for tentativo in $(seq 1 "$TENTATIVI"); do
  rm -rf acqua.zip aperto
  if curl --fail --silent --show-error --location --retry 3 -R -o acqua.zip "$FONTE" \
     && python3 -c "import sys, zipfile; z = zipfile.ZipFile('acqua.zip'); sys.exit(1 if z.testzip() is not None else 0)"; then
    break
  fi
  if [[ $tentativo -ge $TENTATIVI ]]; then
    echo "FERMO: i poligoni del mare non sono arrivati interi dopo $TENTATIVI tentativi."
    echo "- FERMO: archivio del mare non arrivato intero dopo $TENTATIVI tentativi; su reesty resta l'edizione di prima" >> "$S"
    exit 1
  fi
  echo "Tentativo $tentativo: l'archivio del mare non torna. Riprovo fra $ATTESA minuti."
  echo "- Tentativo $tentativo non riuscito (archivio non intero): riprovato dopo $ATTESA minuti" >> "$S"
  sleep $(( ATTESA * 60 ))
done
EDIZIONE=$(date -u -r acqua.zip +%Y-%m-%d)
DATA=$(date -u -r acqua.zip +%Y-%m-%dT%H:%M:%S.000Z)
echo "- Poligoni del mare del $EDIZIONE: $(( $(stat -c %s acqua.zip) / 1048576 )) MB, scaricati in $(( $(date +%s) - inizio )) s" >> "$S"
python3 -c "import zipfile; zipfile.ZipFile('acqua.zip').extractall('aperto')"
rm -f acqua.zip
SHP=$(find aperto -type f -iname '*.shp' | sort | head -1)
[[ -n "$SHP" ]] || { echo "FERMO: nell'archivio del mare non c'è un file .shp."; echo "- FERMO: nell'archivio non c'è un file .shp" >> "$S"; exit 1; }

inizio=$(date +%s)
python3 "$GITHUB_WORKSPACE/mare.py" "$SHP" osm-mare.gpkg conteggi.json "$DATA" | tee -a "$S"
rm -rf aperto
BYTE=$(stat -c %s osm-mare.gpkg)
echo "- Ritaglio: $(( $(date +%s) - inizio )) s · conteggi $(cat conteggi.json)" >> "$S"
if python3 -c "import sys; sys.exit(0 if $BYTE <= float('$PESO_MAX') * 1024**3 else 1)"; then :; else
  echo "FERMO: osm-mare.gpkg pesa $(( BYTE / 1048576 )) MB, oltre la soglia di $PESO_MAX GB: non carico niente."
  echo "- FERMO: $(( BYTE / 1048576 )) MB, oltre la soglia di $PESO_MAX GB (variabile GEO_PESO_MAX_GB): non caricato" >> "$S"
  exit 1
fi
if [[ "${CARICA:-true}" != "true" ]]; then
  echo "- Solo misura: non caricato" >> "$S"
  exit 0
fi
bash "$GITHUB_WORKSPACE/carica.sh" osm-mare.gpkg osm-mare
python3 "$GITHUB_WORKSPACE/cliente.py" controlla osm-mare osm-mare.gpkg "$EDIZIONE" conteggi.json | tee -a "$S"