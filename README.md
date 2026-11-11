# geo-copia

Le mappe di [cerca.re](https://cerca.re) partono da una copia di OpenStreetMap che si aggiorna da sola. Questo repository la fa: scarica l'Italia da Geofabrik, tiene solo gli strati che servono alle mappe e la porta sul servizio geografico di cerca.re.

Il repository è pubblico perché per GitHub i minuti dei repository pubblici sono gratuiti. Qui dentro c'è solo il programma che converte le mappe. Indirizzi, utenti e chiavi stanno nei Secrets e non compaiono mai nei registri.

## Che cosa fa da solo

- **Copia OpenStreetMap** (`copia-osm.yml`) parte da sola:
  - quando questi file arrivano su GitHub;
  - poi il 5 di gennaio, aprile, luglio e ottobre alle 03:00 UTC.

  Lavora le cinque regioni di Geofabrik (nord-ovest, nord-est, centro, sud, isole) tutte insieme. Per ogni regione:
  1. scarica l'estratto e ne controlla l'impronta;
  2. lo converte con `converti.py`;
  3. lo pesa: oltre la soglia si ferma e non carica niente;
  4. lo carica col nome provvisorio;
  5. chiede al servizio di controllarlo prima di usarlo. L'edizione precedente resta come scorta.

  Con «Run workflow» la si lancia anche a mano, per una regione sola o in sola misura.
- **Battito** (`battito.yml`), una volta al mese, scrive la data in `battito.txt`. Senza attività nel repository, GitHub spegne le corse a calendario dopo 60 giorni.
- **Fibra AGCOM** (`fibra-agcom.yml`) si lancia a mano quando arriva l'email di AGCOM con l'edizione nuova. Va scritta l'edizione e una delle due cose seguenti:
  - il link dell'email;
  - il nome del file caricato in `geo/dati/arrivi` dal File Manager di Aruba.

## Gli strati

Gli strati sono tutti in latitudine e longitudine (EPSG:4326), ognuno con il suo indice spaziale R-tree:

| Strato | Forma | Colonne |
|---|---|---|
| edifici | poligoni | id, piani, altezza |
| strade | linee | id, nome, tipo |
| acqua | poligoni | id, nome, tipo |
| corsi_acqua | linee | id, nome, tipo |
| verde | poligoni | id, nome, tipo |
| ferrovie | linee | id, tipo |
| confini | poligoni (comuni) | id, nome, istat |

Dati © contributori OpenStreetMap, licenza ODbL. Fibra: BroadbandMap di AGCOM, CC BY 4.0.

## Che cosa serve

**Secrets** (Settings → Secrets and variables → Actions → Secrets):
- `GEO_FTP_HOST`
- `GEO_FTP_UTENTE`
- `GEO_FTP_PASSWORD`
- `GEO_CHIAVE`, la stessa del repository del sito.

**Variabili**, tutte facoltative (Settings → Secrets and variables → Actions → Variables):

| Variabile | Di fabbrica | Che cosa decide |
|---|---|---|
| `GEO_FTP_TLS` | `si` | FTP cifrato. Altri valori: `si-senza-verifica`, `no`. |
| `GEO_PESO_MAX_GB` | `5` | Peso massimo di una regione. |
| `GEO_REGIONI` | tutte e cinque | Le regioni delle corse automatiche. |
| `GEO_SITO` | `https://portale.reesty.it` | L'indirizzo del sito che ospita il servizio. |
| `GEO_CARTELLE` | `geo geo-prova` | Dove cercare il servizio. |
| `GEO_DATI` | `geo/dati` | La cartella dei dati. |
| `GEO_FTP_RADICE` | vuota | La cartella in cui si entra con l'FTP, se non è la radice del sito. |