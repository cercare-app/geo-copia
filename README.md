# geo-copia

Le mappe di [cerca.re](https://cerca.re) partono da una copia di OpenStreetMap che si aggiorna da sola. Questo repository la fa: scarica l'Italia da Geofabrik, tiene solo gli strati che servono alle mappe e la porta sul servizio geografico di cerca.re.

Il repository è pubblico perché per GitHub i minuti dei repository pubblici sono gratuiti. Qui dentro c'è solo il programma che converte le mappe. Indirizzi, utenti e chiavi stanno nei Secrets e non compaiono mai nei registri.

## Che cosa fa da solo

- **Copia OpenStreetMap** (`copia-osm.yml`) parte da sola:
  - quando questi file arrivano su GitHub;
  - poi il 5 di gennaio, aprile, luglio e ottobre alle 03:00 UTC.

  Lavora le cinque regioni di Geofabrik (nord-ovest, nord-est, centro, sud, isole) e il mare, tutti insieme. Per ogni regione:
  1. scarica l'estratto e ne controlla l'impronta;
  2. lo converte con `converti.py`;
  3. lo pesa: oltre la soglia si ferma e non carica niente;
  4. lo carica col nome provvisorio;
  5. chiede al servizio di controllarlo prima di usarlo. L'edizione precedente resta come scorta.

  Il **mare** fa lo stesso con i poligoni del mare di [osmdata.openstreetmap.de](https://osmdata.openstreetmap.de/data/water-polygons.html), ricavati da OpenStreetMap (in OpenStreetMap il mare è una linea di costa, non un'area). `mare.py` li ritaglia sull'Italia e li taglia a quadretti di circa 5 km.

  Alla fine della corsa, fra i file scaricabili, c'è il pacchetto **«campioni»**: un quadrato di 2 km intorno a ogni posto di `campioni.txt` (12 km per i luoghi, 300 km per gli aeroporti), per provare le mappe e i dintorni su posti veri. Resta 90 giorni, non va né sul sito né su reesty e non ferma mai la copia. In `campioni.txt` vanno solo posti pubblici, mai l'indirizzo di un cliente.

  Con «Run workflow» la si lancia anche a mano, per una regione sola o in sola misura.
- **Battito** (`battito.yml`), una volta al mese, scrive la data in `battito.txt`. Senza attività nel repository, GitHub spegne le corse a calendario dopo 60 giorni.
- **Fibra AGCOM** (`fibra-agcom.yml`) si lancia a mano quando arriva l'email di AGCOM con l'edizione nuova. Va scritta l'edizione e una delle due cose seguenti:
  - il link dell'email;
  - il nome del file caricato in `geo/dati/arrivi` dal File Manager di Aruba.

## Gli strati

Gli strati sono tutti in latitudine e longitudine (EPSG:4326), ognuno con il suo indice spaziale R-tree:

| Strato | Forma | Colonne |
|---|---|---|
| edifici | poligoni | id, piani, altezza, tipo, piani_tetto |
| strade | linee | id, nome, tipo, altri_nomi, galleria, livello |
| acqua | poligoni | id, nome, tipo |
| corsi_acqua | linee | id, nome, tipo |
| verde | poligoni | id, nome, tipo |
| ferrovie | linee | id, tipo, galleria, livello |
| confini | poligoni (comuni) | id, nome, istat |
| luoghi | punti | id, osm, chiave, nome, gestore, etichette |
| aeroporti | punti | id, osm, nome, nome_it, iata |
| mare (file `osm-mare`) | poligoni | id, tipo |

- `tipo` degli edifici è il valore di `building` (per esempio `roof` per tettoie e pensiline); `piani_tetto` viene da `roof:levels`.
- `altri_nomi` delle strade: nome italiano, tedesco, ufficiale, altri nomi, separati da «;».
- `galleria` è il valore di `tunnel` (per esempio `yes`, `building_passage`); `livello` quello di `layer`.
- Nel verde ci sono anche i campi sportivi.
- I **luoghi** sono quelli dei dintorni: supermercati e altri negozi di alimentari, farmacie, scuole e asili, ospedali, banche, bar e ristoranti, fermate di autobus e tram, stazioni e metropolitana, parchi, spiagge. Un luogo disegnato come area diventa il punto al suo centro. `osm` dice se viene da un nodo (`n`), da una via (`w`) o da una relazione (`r`); `chiave` è l'etichetta che lo fa entrare (per esempio `amenity=pharmacy`); `etichette` le altre che servono a riconoscerlo (per esempio `station=subway`, `dispensing=no`). Servono alla verifica dei dintorni, non alle mappe.
- Negli **aeroporti** ci sono tutti gli aerodromi; `iata` è il codice di tre lettere, solo per quelli che lo hanno.

Dati © contributori OpenStreetMap, licenza ODbL (anche il mare). Fibra: BroadbandMap di AGCOM, CC BY 4.0.

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
| `GEO_REGIONI` | tutte e cinque e il mare | Le regioni delle corse automatiche (`mare` per il mare). |
| `GEO_TENTATIVI` | `6` | Quante volte riprovare un download che non arriva intero. |
| `GEO_ATTESA_MINUTI` | `10` | I minuti fra un tentativo e l'altro. |
| `GEO_MARE_FONTE` | i poligoni del mare di osmdata | Da dove scaricare i poligoni del mare. |
| `GEO_MARE_RIQUADRO` | `6.5 35.3 18.6 47.1` | Il riquadro del mare da tenere: ovest, sud, est, nord. |
| `GEO_MARE_CELLA` | `0.05` | Il lato dei quadretti del mare, in gradi. |
| `GEO_CAMPIONI_KM` | `2` | Il lato dei campioni, in km. |
| `GEO_SITO` | `https://portale.reesty.it` | L'indirizzo del sito che ospita il servizio. |
| `GEO_CARTELLE` | `geo geo-prova` | Dove cercare il servizio. |
| `GEO_DATI` | `geo/dati` | La cartella dei dati. |
| `GEO_FTP_RADICE` | vuota | La cartella in cui si entra con l'FTP, se non è la radice del sito. |