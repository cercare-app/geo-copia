#!/usr/bin/env python3
"""
I CAMPIONI PER LE PROVE (lotto «Valutazione 4», D-VAL-103).

Da un GeoPackage della copia (una regione, o il mare) ritaglia un quadrato
intorno a ogni posto di campioni.txt e lo scrive come GeoPackage piccolo,
con gli stessi strati, le stesse colonne e la stessa data. Servono a
provare le mappe del report su posti veri prima di consegnarle: non vanno
né sul sito né su reesty, restano fra i file scaricabili della corsa.

Un posto che non sta in questa regione non dà niente (nessun file).
Non ferma mai la copia: se qualcosa non va, lo scrive e finisce.

Uso: python3 campioni.py FILE.gpkg CAMPIONI.txt CARTELLA FONTE [LATO_KM]
     FONTE = il nome della regione (o «mare»), che finisce nel nome del file;
     LATO_KM = il lato del quadrato, di fabbrica 2.
campioni.txt: una riga per posto, «nome;latitudine;longitudine;nota»;
le righe che cominciano con # non contano.
"""
import math
import os
import re
import sqlite3
import struct
import sys

from converti import Scrittore

INVOLUCRO = {0: 0, 1: 32, 2: 48, 3: 48, 4: 64}  # byte del riquadro nell'intestazione del GeoPackage


def posti(percorso):
    out = []
    with open(percorso, encoding='utf-8') as f:
        for riga in f:
            riga = riga.strip()
            if not riga or riga.startswith('#'):
                continue
            parti = [p.strip() for p in riga.split(';')]
            if len(parti) < 3 or not re.fullmatch(r'[a-z0-9][a-z0-9-]{1,60}', parti[0]):
                print('Riga saltata (forma sbagliata): %s' % riga)
                continue
            try:
                lat, lon = float(parti[1]), float(parti[2])
            except ValueError:
                print('Riga saltata (coordinate): %s' % riga)
                continue
            if not (-89 < lat < 89 and -180 <= lon <= 180):
                print('Riga saltata (coordinate fuori dal mondo): %s' % riga)
                continue
            out.append((parti[0], lat, lon))
    return out


def wkb_di(blocco):
    """La forma senza l'intestazione del GeoPackage."""
    b = bytes(blocco)
    flag = b[3]
    return b[8 + INVOLUCRO.get((flag >> 1) & 7, 0):]


def riquadro_di_blocco(blocco):
    b = bytes(blocco)
    if (b[3] >> 1) & 7 == 0:
        return None
    minx, maxx, miny, maxy = struct.unpack_from('<dddd', b, 8)
    return (minx, miny, maxx, maxy)


def main():
    if len(sys.argv) not in (5, 6):
        print(__doc__)
        return 2
    sorgente, elenco, cartella, fonte = sys.argv[1:5]
    try:
        lato = float(sys.argv[5]) if len(sys.argv) == 6 else 2.0
    except ValueError:
        lato = 2.0
    lato = min(max(lato, 0.2), 10.0)
    if not re.fullmatch(r'[a-z0-9][a-z0-9-]{1,40}', fonte):
        print('Fonte «%s» non valida: nessun campione.' % fonte)
        return 0
    if not os.path.isfile(sorgente):
        print('Il file %s non c\'è: nessun campione.' % sorgente)
        return 0
    os.makedirs(cartella, exist_ok=True)

    db = sqlite3.connect('file:%s?mode=ro' % sorgente, uri=True)
    strati, data = {}, None
    for nome, tipo in db.execute('SELECT table_name, geometry_type_name FROM gpkg_geometry_columns ORDER BY table_name'):
        colonne = [(r[1], r[2] or 'TEXT') for r in db.execute('PRAGMA table_info("%s")' % nome.replace('"', '')) if r[1] not in ('fid', 'geom')]
        strati[nome] = (tipo, colonne)
    for (cambio,) in db.execute('SELECT last_change FROM gpkg_contents ORDER BY table_name LIMIT 1'):
        data = cambio
    if not strati:
        print('Il file %s non ha strati: nessun campione.' % sorgente)
        return 0

    for nome, lat, lon in posti(elenco):
        mezzo_lat = (lato / 2) / 110.574
        mezzo_lon = (lato / 2) / (111.320 * math.cos(math.radians(lat)))
        ovest, sud, est, nord = lon - mezzo_lon, lat - mezzo_lat, lon + mezzo_lon, lat + mezzo_lat
        righe = {}
        for strato, (_, colonne) in strati.items():
            elenco_colonne = ', '.join('t."%s"' % c for c, _ in colonne)
            sql = ('SELECT t.geom%s FROM "%s" t JOIN "rtree_%s_geom" r ON t.fid = r.id '
                   'WHERE r.maxx >= ? AND r.minx <= ? AND r.maxy >= ? AND r.miny <= ? ORDER BY t.fid'
                   % ((', ' + elenco_colonne) if colonne else '', strato, strato))
            righe[strato] = db.execute(sql, (ovest, est, sud, nord)).fetchall()
        totale = sum(len(r) for r in righe.values())
        if totale == 0:
            continue
        uscita = os.path.join(cartella, 'campione-%s-%s.gpkg' % (nome, fonte))
        scrittore = Scrittore(uscita, data or '2000-01-01T00:00:00.000Z', strati)
        for strato, elenco_righe in righe.items():
            for riga in elenco_righe:
                riquadro = riquadro_di_blocco(riga[0])
                if riquadro is None:
                    continue
                scrittore.aggiungi(strato, riquadro, wkb_di(riga[0]), riga[1:])
        scrittore.chiudi()
        print('Campione %s (%s): %d oggetti, %.1f MB' % (nome, fonte, totale, os.path.getsize(uscita) / 1048576))
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except Exception as e:  # i campioni non fermano mai la copia
        print('Campioni non fatti: %s' % e)
        sys.exit(0)