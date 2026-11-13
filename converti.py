#!/usr/bin/env python3
"""
LA COPIA DI OPENSTREETMAP PER CERCA.RE (lotto «Valutazione 3», D-VAL-70, D-VAL-71).

Legge un estratto di OpenStreetMap (.osm.pbf di Geofabrik, o .osm per le
prove) e scrive un GeoPackage con solo gli strati che servono alle mappe
del report, ognuno col suo indice spaziale R-tree, in latitudine e
longitudine (EPSG:4326):

  edifici      poligoni   id, piani, altezza, tipo, piani_tetto
  strade       linee      id, nome, tipo, altri_nomi, galleria, livello
  acqua        poligoni   id, nome, tipo
  corsi_acqua  linee      id, nome, tipo
  verde        poligoni   id, nome, tipo
  ferrovie     linee      id, tipo, galleria, livello
  confini      poligoni   id, nome, istat   (comuni: admin_level 8)

Lotto «Valutazione 4» (D-VAL-94): il tipo di edificio (per togliere
tettoie e pensiline dalla vista dall'alto), i piani del tetto, gli altri
nomi delle vie (nome italiano, tedesco, ufficiale…, separati da «;»: per
trovare la via dell'indirizzo), la galleria e il livello di strade e
ferrovie (per disegnare le gallerie più chiare), i campi sportivi nel verde.

Scrive anche conteggi.json (oggetti per strato), che il servizio su reesty
ricontrolla prima di usare il file. Niente luoghi e negozi: i dintorni
vengono da un'altra fonte (lotto 5). Dati © contributori OpenStreetMap,
licenza ODbL.

Uso: python3 converti.py ESTRATTO.osm.pbf USCITA.gpkg CONTEGGI.json

Lo stesso estratto dà sempre lo stesso file, byte per byte (la data scritta
dentro è quella dell'estratto): così il servizio riconosce una copia rifatta.
"""
import json
import math
import os
import sqlite3
import struct
import sys
import time

import osmium

SRS = 4326

# strato: (tipo di forma, [(colonna, tipo SQL)])
STRATI = {
    'edifici': ('MULTIPOLYGON', [('id', 'INTEGER'), ('piani', 'INTEGER'), ('altezza', 'REAL'), ('tipo', 'TEXT'), ('piani_tetto', 'INTEGER')]),
    'strade': ('LINESTRING', [('id', 'INTEGER'), ('nome', 'TEXT'), ('tipo', 'TEXT'), ('altri_nomi', 'TEXT'), ('galleria', 'TEXT'), ('livello', 'INTEGER')]),
    'acqua': ('MULTIPOLYGON', [('id', 'INTEGER'), ('nome', 'TEXT'), ('tipo', 'TEXT')]),
    'corsi_acqua': ('LINESTRING', [('id', 'INTEGER'), ('nome', 'TEXT'), ('tipo', 'TEXT')]),
    'verde': ('MULTIPOLYGON', [('id', 'INTEGER'), ('nome', 'TEXT'), ('tipo', 'TEXT')]),
    'ferrovie': ('LINESTRING', [('id', 'INTEGER'), ('tipo', 'TEXT'), ('galleria', 'TEXT'), ('livello', 'INTEGER')]),
    'confini': ('MULTIPOLYGON', [('id', 'INTEGER'), ('nome', 'TEXT'), ('istat', 'TEXT')]),
}

STRADE_NO = {'proposed', 'construction', 'abandoned', 'platform', 'raceway', 'bus_stop', 'elevator', 'corridor', 'razed'}
CORSI = {'river', 'stream', 'canal', 'drain', 'ditch'}
FERROVIE = {'rail', 'light_rail', 'subway', 'tram', 'narrow_gauge', 'monorail', 'funicular'}
VERDE_LEISURE = {'park', 'garden', 'nature_reserve', 'recreation_ground', 'golf_course', 'playground', 'dog_park', 'pitch'}
VERDE_LANDUSE = {'grass', 'forest', 'meadow', 'recreation_ground', 'village_green', 'allotments', 'orchard', 'vineyard', 'cemetery', 'flowerbed'}
VERDE_NATURAL = {'wood', 'scrub', 'grassland', 'heath'}

# gli altri nomi di una via, in quest'ordine (alt_name e simili possono averne più d'uno, separati da «;»)
ALTRI_NOMI = ('name:it', 'name:de', 'name:lld', 'name:fr', 'name:sl', 'official_name', 'alt_name', 'short_name', 'loc_name', 'old_name')

LOTTO = 20000


def numero(testo, intero=False):
    """«3», «3.5», «12 m» → numero; altrimenti None."""
    if not testo:
        return None
    t = testo.strip().lower().replace(',', '.').replace('m', '').strip()
    try:
        v = float(t)
    except ValueError:
        return None
    if not math.isfinite(v) or v < 0 or v > 1000:
        return None
    return int(round(v)) if intero else round(v, 2)


def altri_nomi(t):
    """Gli altri nomi della via, senza doppioni e senza il nome principale, separati da «;»; altrimenti None."""
    principale = (t.get('name') or '').strip()
    visti, nomi = {principale.lower()}, []
    for chiave in ALTRI_NOMI:
        for nome in (t.get(chiave) or '').split(';'):
            nome = nome.strip()
            if nome and nome.lower() not in visti and len(nome) <= 200:
                visti.add(nome.lower())
                nomi.append(nome)
    return ';'.join(nomi) if nomi else None


def galleria(t):
    """Il valore di «tunnel» (yes, building_passage, culvert…) se la via è in galleria; altrimenti None."""
    v = (t.get('tunnel') or '').strip()
    return v[:40] if v and v != 'no' else None


def livello(t):
    """Il livello («layer») come numero intero da -10 a 10; altrimenti None."""
    try:
        v = int((t.get('layer') or '').strip())
    except ValueError:
        return None
    return v if -10 <= v <= 10 else None


def wkb_linea(punti):
    return struct.pack('<BII', 1, 2, len(punti)) + b''.join(struct.pack('<dd', x, y) for x, y in punti)


def wkb_multipoligono(poligoni):
    parti = []
    for anelli in poligoni:
        corpo = struct.pack('<BII', 1, 3, len(anelli))
        for anello in anelli:
            corpo += struct.pack('<I', len(anello)) + b''.join(struct.pack('<dd', x, y) for x, y in anello)
        parti.append(corpo)
    return struct.pack('<BII', 1, 6, len(parti)) + b''.join(parti)


def blocco_gpkg(wkb, riquadro):
    """L'intestazione del GeoPackage: «GP», versione 0, little endian, riquadro [minx maxx miny maxy]."""
    minx, miny, maxx, maxy = riquadro
    return b'GP' + bytes([0, 0x03]) + struct.pack('<i', SRS) + struct.pack('<dddd', minx, maxx, miny, maxy) + wkb


class Scrittore:
    def __init__(self, percorso, data, strati=None):
        self.data = data
        self.strati = STRATI if strati is None else strati
        if os.path.exists(percorso):
            os.remove(percorso)
        self.db = sqlite3.connect(percorso)
        self.db.execute('PRAGMA journal_mode = OFF')
        self.db.execute('PRAGMA synchronous = OFF')
        self.db.execute('PRAGMA application_id = 1196444487')  # «GPKG»
        self.db.execute('PRAGMA user_version = 10301')
        self.db.executescript('''
            CREATE TABLE gpkg_spatial_ref_sys (srs_name TEXT NOT NULL, srs_id INTEGER PRIMARY KEY, organization TEXT NOT NULL,
              organization_coordsys_id INTEGER NOT NULL, definition TEXT NOT NULL, description TEXT);
            CREATE TABLE gpkg_contents (table_name TEXT NOT NULL PRIMARY KEY, data_type TEXT NOT NULL, identifier TEXT UNIQUE,
              description TEXT DEFAULT '', last_change DATETIME NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
              min_x DOUBLE, min_y DOUBLE, max_x DOUBLE, max_y DOUBLE, srs_id INTEGER);
            CREATE TABLE gpkg_geometry_columns (table_name TEXT NOT NULL, column_name TEXT NOT NULL, geometry_type_name TEXT NOT NULL,
              srs_id INTEGER NOT NULL, z TINYINT NOT NULL, m TINYINT NOT NULL, PRIMARY KEY (table_name, column_name));
            CREATE TABLE gpkg_extensions (table_name TEXT, column_name TEXT, extension_name TEXT NOT NULL, definition TEXT NOT NULL,
              scope TEXT NOT NULL, UNIQUE (table_name, column_name, extension_name));
        ''')
        self.db.executemany('INSERT INTO gpkg_spatial_ref_sys VALUES (?,?,?,?,?,?)', [
            ('Undefined cartesian SRS', -1, 'NONE', -1, 'undefined', None),
            ('Undefined geographic SRS', 0, 'NONE', 0, 'undefined', None),
            ('WGS 84 geodetic', 4326, 'EPSG', 4326,
             'GEOGCS["WGS 84",DATUM["WGS_1984",SPHEROID["WGS 84",6378137,298.257223563,AUTHORITY["EPSG","7030"]],'
             'AUTHORITY["EPSG","6326"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.0174532925199433,'
             'AUTHORITY["EPSG","9122"]],AXIS["Latitude",NORTH],AXIS["Longitude",EAST],AUTHORITY["EPSG","4326"]]', None),
        ])
        self.code = {}
        self.conteggi = {}
        self.riquadri = {}
        for nome, (tipo, colonne) in self.strati.items():
            definizione = ', '.join('"%s" %s' % (c, t) for c, t in colonne)
            self.db.execute('CREATE TABLE "%s" (fid INTEGER PRIMARY KEY AUTOINCREMENT, geom %s, %s)' % (nome, tipo, definizione))
            self.db.execute('INSERT INTO gpkg_geometry_columns VALUES (?, ?, ?, ?, 0, 0)', (nome, 'geom', tipo, SRS))
            self.code[nome] = []
            self.conteggi[nome] = 0
            self.riquadri[nome] = [math.inf, math.inf, -math.inf, -math.inf]

    def aggiungi(self, strato, riquadro, wkb, valori):
        self.code[strato].append((blocco_gpkg(wkb, riquadro),) + tuple(valori))
        r = self.riquadri[strato]
        r[0], r[1], r[2], r[3] = min(r[0], riquadro[0]), min(r[1], riquadro[1]), max(r[2], riquadro[2]), max(r[3], riquadro[3])
        self.conteggi[strato] += 1
        if len(self.code[strato]) >= LOTTO:
            self.scarica(strato)

    def scarica(self, strato):
        righe = self.code[strato]
        if not righe:
            return
        colonne = ['geom'] + [c for c, _ in self.strati[strato][1]]
        self.db.executemany('INSERT INTO "%s" (%s) VALUES (%s)' % (strato, ', '.join('"%s"' % c for c in colonne), ', '.join('?' * len(colonne))), righe)
        self.code[strato] = []

    def chiudi(self):
        for nome, (tipo, _) in self.strati.items():
            self.scarica(nome)
            r = self.riquadri[nome]
            vuoto = not math.isfinite(r[0])
            self.db.execute('INSERT INTO gpkg_contents (table_name, data_type, identifier, last_change, min_x, min_y, max_x, max_y, srs_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                            (nome, 'features', nome, self.data, None if vuoto else r[0], None if vuoto else r[1], None if vuoto else r[2], None if vuoto else r[3], SRS))
            indice = 'rtree_%s_geom' % nome
            self.db.execute('CREATE VIRTUAL TABLE "%s" USING rtree(id, minx, maxx, miny, maxy)' % indice)
            # il riquadro di ogni forma sta nella sua intestazione (byte 8-39): si legge da lì
            self.db.create_function('rq', 2, lambda b, i: struct.unpack_from('<d', b, 8 + 8 * i)[0])
            self.db.execute('INSERT INTO "%s" SELECT fid, rq(geom, 0), rq(geom, 1), rq(geom, 2), rq(geom, 3) FROM "%s"' % (indice, nome))
            self.db.execute("INSERT INTO gpkg_extensions VALUES (?, 'geom', 'gpkg_rtree_index', 'http://www.geopackage.org/spec120/#extension_rtree', 'write-only')", (nome,))
        self.db.commit()
        self.db.execute('VACUUM')
        self.db.close()


def riquadro_di(anelli_o_punti):
    xs = [p[0] for p in anelli_o_punti]
    ys = [p[1] for p in anelli_o_punti]
    return (min(xs), min(ys), max(xs), max(ys))


class Lettore(osmium.SimpleHandler):
    def __init__(self, scrittore):
        super().__init__()
        self.s = scrittore

    @staticmethod
    def punti_via(w):
        punti = []
        for n in w.nodes:
            if not n.location.valid():
                return None
            punti.append((round(n.location.lon, 7), round(n.location.lat, 7)))
        return punti if len(punti) >= 2 else None

    def way(self, w):
        t = w.tags
        strada = t.get('highway')
        corso = t.get('waterway')
        ferrovia = t.get('railway')
        if t.get('area') == 'yes':
            return
        voglio = (strada and strada not in STRADE_NO) or (corso in CORSI) or (ferrovia in FERROVIE)
        if not voglio:
            return
        punti = self.punti_via(w)
        if punti is None:
            return
        wkb = wkb_linea(punti)
        rq = riquadro_di(punti)
        if strada and strada not in STRADE_NO:
            self.s.aggiungi('strade', rq, wkb, (w.id, t.get('name'), strada, altri_nomi(t), galleria(t), livello(t)))
        if corso in CORSI:
            self.s.aggiungi('corsi_acqua', rq, wkb, (w.id, t.get('name'), corso))
        if ferrovia in FERROVIE:
            self.s.aggiungi('ferrovie', rq, wkb, (w.id, ferrovia, galleria(t), livello(t)))

    def area(self, a):
        t = a.tags
        edificio = t.get('building')
        natural = t.get('natural')
        landuse = t.get('landuse')
        leisure = t.get('leisure')
        acqua = natural == 'water' or 'water' in t or landuse in ('reservoir', 'basin') or t.get('waterway') == 'riverbank'
        verde = leisure in VERDE_LEISURE or landuse in VERDE_LANDUSE or natural in VERDE_NATURAL
        confine = t.get('boundary') == 'administrative' and t.get('admin_level') == '8'
        edificio = edificio is not None and edificio != 'no'
        if not (edificio or acqua or verde or confine):
            return
        poligoni = []
        try:
            for esterno in a.outer_rings():
                anelli = [[(round(n.lon, 7), round(n.lat, 7)) for n in esterno]]
                for interno in a.inner_rings(esterno):
                    anelli.append([(round(n.lon, 7), round(n.lat, 7)) for n in interno])
                if len(anelli[0]) >= 4:
                    poligoni.append(anelli)
        except osmium.InvalidLocationError:
            return
        if not poligoni:
            return
        wkb = wkb_multipoligono(poligoni)
        rq = riquadro_di([p for pol in poligoni for p in pol[0]])
        ident = a.orig_id() * (1 if a.from_way() else -1)  # le relazioni con segno meno: non si confondono con le vie
        if edificio:
            piani = numero(t.get('building:levels'), intero=True)
            self.s.aggiungi('edifici', rq, wkb, (ident, piani, numero(t.get('height')), (t.get('building') or '')[:40], numero(t.get('roof:levels'), intero=True)))
        if acqua:
            self.s.aggiungi('acqua', rq, wkb, (ident, t.get('name'), t.get('water') or natural or landuse or 'acqua'))
        if verde:
            self.s.aggiungi('verde', rq, wkb, (ident, t.get('name'), leisure or landuse or natural))
        if confine:
            self.s.aggiungi('confini', rq, wkb, (ident, t.get('name'), t.get('ref:ISTAT')))


def main():
    if len(sys.argv) != 4:
        print(__doc__)
        return 2
    estratto, uscita, conteggi = sys.argv[1:]
    inizio = time.time()
    # la data dell'estratto (quella di Geofabrik, conservata da curl -R): sempre la stessa per lo stesso estratto
    data = time.strftime('%Y-%m-%dT%H:%M:%S.000Z', time.gmtime(os.path.getmtime(estratto)))
    scrittore = Scrittore(uscita, data)
    Lettore(scrittore).apply_file(estratto, locations=True, idx='flex_mem')
    scrittore.chiudi()
    with open(conteggi, 'w', encoding='utf-8') as f:
        json.dump(scrittore.conteggi, f, ensure_ascii=False, sort_keys=True)
    secondi = time.time() - inizio
    print('Fatto in %d s: %s' % (secondi, ', '.join('%s %d' % (k, v) for k, v in sorted(scrittore.conteggi.items()))))
    print('Peso: %.1f MB' % (os.path.getsize(uscita) / 1048576))
    return 0


if __name__ == '__main__':
    sys.exit(main())