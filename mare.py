#!/usr/bin/env python3
"""
IL MARE PER LE MAPPE DI CERCA.RE (lotto «Valutazione 4», D-VAL-95).

In OpenStreetMap il mare non è un'area ma una linea di costa: i poligoni
del mare già pronti li prepara osmdata.openstreetmap.de («water polygons»,
WGS84, Shapefile), dagli stessi dati e con la stessa licenza ODbL.

Questo programma li ritaglia sul riquadro dell'Italia e li taglia a
quadretti (di fabbrica 0,05 gradi, circa 5 km): così il servizio su reesty
risponde con pezzi piccoli anche per una mappa di pochi metri. I pezzi
dello stesso quadretto si uniscono, perché in stampa non si vedano cuciture.
Scrive un GeoPackage con un solo strato, «mare» (id, tipo), con il suo
indice spaziale, e conteggi.json come converti.py.

Uso: python3 mare.py ACQUA.shp USCITA.gpkg CONTEGGI.json DATA
     DATA = la data dell'edizione, per esempio 2026-11-11T00:00:00.000Z
     Variabili facoltative: GEO_MARE_RIQUADRO («ovest sud est nord», di fabbrica
     «6.5 35.3 18.6 47.1») e GEO_MARE_CELLA (gradi, di fabbrica 0.05).

Lo stesso Shapefile dà sempre lo stesso file, byte per byte.
"""
import json
import math
import os
import sys
import time

import shapefile
import shapely
from shapely.geometry import shape

from converti import Scrittore, riquadro_di, wkb_multipoligono

STRATI = {'mare': ('MULTIPOLYGON', [('id', 'INTEGER'), ('tipo', 'TEXT')])}


def numeri(testo, quanti):
    parti = testo.split()
    if len(parti) != quanti:
        raise ValueError(testo)
    valori = [float(p) for p in parti]
    if not all(math.isfinite(v) for v in valori):
        raise ValueError(testo)
    return valori


def poligoni_di(g):
    """Le parti poligonali di una forma (le linee e i punti di un taglio si scartano)."""
    if g.is_empty:
        return []
    if g.geom_type == 'Polygon':
        return [g]
    if g.geom_type in ('MultiPolygon', 'GeometryCollection'):
        return [p for parte in g.geoms for p in poligoni_di(parte)]
    return []


def anelli_di(p):
    anelli = [[(round(x, 7), round(y, 7)) for x, y in p.exterior.coords]]
    anelli += [[(round(x, 7), round(y, 7)) for x, y in r.coords] for r in p.interiors]
    return [a for a in anelli if len(a) >= 4]


def main():
    if len(sys.argv) != 5:
        print(__doc__)
        return 2
    acqua, uscita, conteggi, data = sys.argv[1:]
    try:
        ovest, sud, est, nord = numeri(os.environ.get('GEO_MARE_RIQUADRO') or '6.5 35.3 18.6 47.1', 4)
        (cella,) = numeri(os.environ.get('GEO_MARE_CELLA') or '0.05', 1)
    except ValueError:
        print('FERMO: GEO_MARE_RIQUADRO o GEO_MARE_CELLA non sono numeri.')
        return 1
    if not (ovest < est and sud < nord and 0.005 <= cella <= 1):
        print('FERMO: riquadro o quadretto del mare non validi.')
        return 1
    inizio = time.time()

    # i pezzi di mare, quadretto per quadretto (chiave: colonna e riga nella griglia di tutto il mondo)
    pezzi = {}
    lettore = shapefile.Reader(acqua)
    letti = 0
    # le forme fuori dal riquadro si saltano senza leggerne i punti (l'archivio è di tutto il mondo)
    for forma in lettore.iterShapes(bbox=[ovest, sud, est, nord]):
        x0, y0, x1, y1 = forma.bbox
        if x1 < ovest or x0 > est or y1 < sud or y0 > nord:
            continue
        g = shape(forma.__geo_interface__)
        if not g.is_valid:
            g = shapely.make_valid(g)
        letti += 1
        c0, c1 = math.floor(max(x0, ovest) / cella), math.floor(min(x1, est) / cella)
        r0, r1 = math.floor(max(y0, sud) / cella), math.floor(min(y1, nord) / cella)
        for c in range(c0, c1 + 1):
            for r in range(r0, r1 + 1):
                qx0, qy0 = max(c * cella, ovest), max(r * cella, sud)
                qx1, qy1 = min((c + 1) * cella, est), min((r + 1) * cella, nord)
                if qx0 >= qx1 or qy0 >= qy1:
                    continue
                parte = shapely.clip_by_rect(g, qx0, qy0, qx1, qy1)
                if not parte.is_empty and parte.area > 0:
                    pezzi.setdefault((c, r), []).append(parte)

    scrittore = Scrittore(uscita, data, STRATI)
    numero = 0
    for chiave in sorted(pezzi):
        unito = shapely.union_all(pezzi[chiave]) if len(pezzi[chiave]) > 1 else pezzi[chiave][0]
        poligoni = [anelli_di(p) for p in poligoni_di(unito)]
        poligoni = [a for a in poligoni if a]
        if not poligoni:
            continue
        numero += 1
        scrittore.aggiungi('mare', riquadro_di([p for pol in poligoni for p in pol[0]]), wkb_multipoligono(poligoni), (numero, 'mare'))
    scrittore.chiudi()
    with open(conteggi, 'w', encoding='utf-8') as f:
        json.dump(scrittore.conteggi, f, ensure_ascii=False, sort_keys=True)
    print('Fatto in %d s: %d poligoni del mare letti, mare %d' % (time.time() - inizio, letti, scrittore.conteggi['mare']))
    print('Peso: %.1f MB' % (os.path.getsize(uscita) / 1048576))
    return 0


if __name__ == '__main__':
    sys.exit(main())