#!/usr/bin/env python3
"""
IL CLIENTE DEL SERVIZIO GEOGRAFICO DI CERCA.RE (lotto «Valutazione 3»).

Firma le richieste come il sito (stessa regola di App\\Support\\Geo\\Firma)
e chiama il servizio su reesty. Lo usano i workflow di questo repository,
dopo aver caricato un file via FTP col nome provvisorio «<nome>.gpkg.nuovo»:

  python3 cliente.py controlla NOME FILE.gpkg EDIZIONE [CONTEGGI.json]
  python3 cliente.py stato

Il servizio si cerca nelle cartelle di GEO_CARTELLE (di fabbrica «geo
geo-prova»: prima quella della produzione, poi quella di dev). Ambiente:
GEO_SITO (di fabbrica https://portale.reesty.it), GEO_CHIAVE (Secret).
Non stampa mai la chiave.
"""
import hashlib
import hmac
import http.client
import json
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

RISERVATI = {'_chiave', '_ora', '_firma', 'azione'}


def testo(azione, ora, parametri):
    righe = sorted((k, '%s=%s' % (k, urllib.parse.quote(str(v), safe=''))) for k, v in parametri.items() if k not in RISERVATI)
    return '%s\n%d\n%s' % (azione, ora, '&'.join(r for _, r in righe))


def firma(chiave, azione, parametri, ora=None):
    ora = int(time.time()) if ora is None else ora
    fuori = {k: str(v) for k, v in parametri.items()}
    fuori.update({'azione': azione, '_chiave': 'sito', '_ora': str(ora)})
    fuori['_firma'] = hmac.new(chiave.encode(), testo(azione, ora, fuori).encode(), hashlib.sha256).hexdigest()
    return fuori


def chiama(indirizzo, chiave, azione, parametri, attesa):
    """[codice, dati del servizio o None, si può provare un'altra cartella?]"""
    corpo = urllib.parse.urlencode(firma(chiave, azione, parametri), quote_via=urllib.parse.quote).encode()
    richiesta = urllib.request.Request(indirizzo, data=corpo, method='POST', headers={'Content-Type': 'application/x-www-form-urlencoded', 'User-Agent': 'cercare-geo-copia'})
    try:
        with urllib.request.urlopen(richiesta, timeout=attesa) as r:
            return r.status, leggi(r.read()), True
    except urllib.error.HTTPError as e:
        try:
            testo = e.read()
        except (http.client.HTTPException, OSError):
            testo = b''
        # una pagina d'errore del server (cartella senza il servizio): si può provare la cartella dopo,
        # ma non dopo un errore 5xx (il servizio può essere ancora al lavoro sul file)
        return e.code, leggi(testo), e.code < 500
    except urllib.error.URLError as e:
        rifiutata = isinstance(e.reason, ConnectionRefusedError)
        return 0, {'ok': False, 'motivo': 'il servizio non risponde (%s)' % e.reason}, rifiutata
    except (TimeoutError, http.client.HTTPException, OSError) as e:
        return 0, {'ok': False, 'motivo': 'il collegamento si è interrotto o è scaduto (%s)' % e.__class__.__name__}, False


def leggi(testo):
    try:
        dati = json.loads(testo.decode('utf-8'))
    except (ValueError, UnicodeDecodeError):
        return None
    return dati if isinstance(dati, dict) and 'ok' in dati else None


def servizio(chiave, azione, parametri, attesa=900):
    """Il servizio si cerca nelle cartelle in ordine; risponde solo se arriva il suo JSON (con «ok»).
    Se il servizio è occupato con un altro file (423) si aspetta e si riprova, fino a mezz'ora."""
    sito = os.environ.get('GEO_SITO', '').strip() or 'https://portale.reesty.it'
    cartelle = (os.environ.get('GEO_CARTELLE', '').strip() or 'geo geo-prova').split()
    ultimo = (0, {'ok': False, 'motivo': 'nessuna cartella'})
    for cartella in cartelle:
        for giro in range(60):
            codice, dati, altra = chiama('%s/%s/' % (sito.rstrip('/'), cartella.strip('/')), chiave, azione, parametri, attesa)
            if codice == 423 and dati is not None and giro < 59:
                print('Il servizio sta controllando un altro file: riprovo fra 30 secondi.')
                time.sleep(30)
                continue
            break
        if dati is not None and codice != 0:
            return cartella, codice, dati
        ultimo = (codice, dati if dati is not None else {'ok': False, 'motivo': 'in /%s non risponde il servizio (codice %s)' % (cartella, codice)})
        if not altra:
            break
    return None, ultimo[0], ultimo[1]


def impronta(percorso):
    h = hashlib.sha256()
    with open(percorso, 'rb') as f:
        for pezzo in iter(lambda: f.read(8 << 20), b''):
            h.update(pezzo)
    return h.hexdigest()


def main(argomenti):
    chiave = os.environ.get('GEO_CHIAVE', '')
    if len(chiave) < 32 or not chiave.isalnum():
        print('FERMO: il Secret GEO_CHIAVE manca o non va bene (da 32 a 200 lettere e numeri).')
        return 1
    if argomenti[:1] == ['stato']:
        cartella, codice, dati = servizio(chiave, 'stato', {}, 60)
        print(json.dumps({'cartella': cartella, 'codice': codice, **dati}, ensure_ascii=False, indent=1))
        return 0 if dati.get('ok') else 1
    if argomenti[:1] == ['controlla'] and len(argomenti) in (4, 5):
        nome, percorso, edizione = argomenti[1:4]
        parametri = {'file': nome, 'sha256': impronta(percorso), 'byte': os.path.getsize(percorso), 'edizione': edizione}
        if len(argomenti) == 5:
            with open(argomenti[4], encoding='utf-8') as f:
                parametri['conteggi'] = json.dumps(json.load(f), sort_keys=True)
        cartella, codice, dati = servizio(chiave, 'controlla', parametri)
        if dati.get('ok'):
            print('OK: %s al suo posto su reesty (servizio in /%s), edizione %s%s.' % (nome, cartella, dati.get('edizione'), ', la precedente resta come scorta' if dati.get('scorta') else ''))
            return 0
        print('FERMO: il servizio non ha accettato %s (codice %s): %s' % (nome, codice, dati.get('motivo')))
        return 1
    print(__doc__)
    return 2


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))