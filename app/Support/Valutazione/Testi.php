<?php

namespace App\Support\Valutazione;

use App\Models\Setting;
use App\Support\Permessi;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * I TESTI DEL REPORT (D-VAL-15, D-VAL-43): ogni titolo, occhiello, frase,
 * nota e avvertenza delle pagine, dall'Admin → «Valutazione · Testi del
 * report». Qui le pagine del lotto 1 (copertina, «In breve», «Avvertenze e
 * diritti»); quelle del lotto 2 stanno in TestiPagine e si uniscono qui.
 * Qui sono definiti anche pagine e riquadri (PAGINE, RIQUADRI): dal lotto 2
 * li scrive «Valutazione · Pagine e riquadri» (Disposizione, D-VAL-59).
 *
 *  - I valori di fabbrica stanno qui: il testo del fac-simile, con l'uso
 *    personale (solo privati, D-VAL-49 e D-VAL-52) e i costi di gestione
 *    della casa (D-VAL-51). Sono provvisori finché il consulente non li
 *    legge (D-VAL-16).
 *  - Nell'Admin si salva solo ciò che è diverso dalla fabbrica: un campo
 *    vuoto, o uguale alla fabbrica, torna alla fabbrica.
 *  - I segnaposti ammessi sono quelli comuni (COMUNI) più quelli del testo;
 *    un segnaposto sconosciuto ferma il salvataggio.
 *  - Una parola vietata dalla guardia NON ferma il salvataggio: lo dice
 *    subito, e il report (anche l'anteprima) non si genera finché c'è.
 *  - Il report non legge mai di qui direttamente: legge la fotografia
 *    (Fotografia::adesso()), così un report venduto non cambia più.
 *  - Ogni salvataggio diventa una versione numerata (Versioni, D-VAL-60).
 */
final class Testi
{
    public const PREFISSO = 'valutazione_testo_';

    public const PREFISSO_RIQUADRO = 'valutazione_riquadro_';

    public const PREFISSO_PAGINA = 'valutazione_pagina_';

    public const AVVERTENZE = 'valutazione_avvertenze';

    /** I segnaposti che valgono in ogni testo. */
    public const COMUNI = ['anno', 'titolare', 'indirizzo', 'comune', 'codice', 'data', 'email', 'sito'];

    /** Le sezioni della pagina dell'Admin, nell'ordine. */
    public const SEZIONI = [
        'testata' => 'Testata e piè di ogni pagina',
        'copertina' => 'Pagina 1 · Copertina',
        'in_breve' => 'Pagina «In breve»',
        ...TestiMappe::SEZIONI,
        ...TestiPagine::SEZIONI,
        'avvertenze' => 'Pagina «Avvertenze e diritti»',
        'contatti' => 'Contatti',
    ];

    /** Lunghezza massima: righe, testi, caselle di «In breve» (hanno un'altezza fissa), la nota sotto le fonti. */
    public const MASSIMO = ['riga' => 200, 'testo' => 1500, 'casella' => 170, 'nota' => 200, 'righe' => 1500, 'paragrafi' => 4000];

    /**
     * I testi: nome => [sezione, etichetta, fabbrica, segnaposti propri,
     * segnaposti in grassetto, tipo]. Tipo: riga, testo, casella (una nota
     * di «In breve»), nota (una o due frasi su una riga, sotto le fonti di
     * «In breve»), righe (una voce per riga), paragrafi (separati da una
     * riga vuota).
     */
    public const TESTI = [
        'testata' => ['testata', 'Testata, a destra del logo', 'Report di mercato · Stima automatica', [], [], 'riga'],
        'piede_casa' => ['testata', 'Piè di pagina, a sinistra', '{indirizzo}, {comune} · Codice {codice} · {data}', [], [], 'riga'],

        'cop_occhiello' => ['copertina', 'Occhiello sopra l\'indirizzo', 'Report di mercato · Stima automatica del valore', [], [], 'riga'],
        'chip_appartamento' => ['copertina', 'Casella «tipologia»: appartamento', 'Appartamento', [], [], 'riga'],
        'chip_casa' => ['copertina', 'Casella «tipologia»: casa indipendente', 'Casa indipendente', [], [], 'riga'],
        'chip_superficie' => ['copertina', 'Casella «superficie»', '{superficie}', ['superficie'], [], 'riga'],
        'chip_piano' => ['copertina', 'Casella «piano»', '{piano}° piano', ['piano'], [], 'riga'],
        'chip_piano_terra' => ['copertina', 'Casella «piano» per il piano terra', 'Piano terra', [], [], 'riga'],
        'chip_locali' => ['copertina', 'Casella «locali»', '{locali} locali', ['locali'], [], 'riga'],
        'chip_zona' => ['copertina', 'Casella «zona»', 'Zona {zona_codice} · {zona_nome}', ['zona_codice', 'zona_nome'], [], 'riga'],
        'cop_valore' => ['copertina', 'Titolo del valore', 'Il valore stimato oggi', [], [], 'riga'],
        'cop_valore_mq' => ['copertina', 'Riga sotto il valore', 'pari a {valore_mq} · forchetta da {minimo_mq} a {massimo_mq}', ['valore_mq', 'minimo_mq', 'massimo_mq'], ['valore_mq'], 'riga'],
        'cop_minimo' => ['copertina', 'Barra della forchetta: a sinistra', 'Minimo', [], [], 'riga'],
        'cop_massimo' => ['copertina', 'Barra della forchetta: a destra', 'Massimo', [], [], 'riga'],
        'cop_affitto' => ['copertina', 'Casella a destra: affitto', 'Affitto stimato', [], [], 'riga'],
        'cop_al_mese' => ['copertina', 'Dopo l\'affitto (anche in «In breve»)', 'al mese', [], [], 'riga'],
        'cop_ristrutturata' => ['copertina', 'Casella a destra: ristrutturata', 'Ristrutturata a nuovo', [], [], 'riga'],
        'cop_da_ristrutturare' => ['copertina', 'Casella a destra: da ristrutturare', 'Da ristrutturare', [], [], 'riga'],
        'cop_creato' => ['copertina', 'In fondo a sinistra, prima riga', 'Creato il {data}', [], [], 'riga'],
        'cop_codice' => ['copertina', 'In fondo a sinistra, seconda riga', 'Codice {codice}', [], ['codice'], 'riga'],
        'cop_contatti' => ['copertina', 'In fondo a destra, prima riga', '{sito} · {email}', [], [], 'riga'],

        'breve_occhiello' => ['in_breve', 'Occhiello', 'In breve', [], [], 'riga'],
        'breve_titolo' => ['in_breve', 'Titolo (anche nell\'indice)', 'Il valore della casa, in un minuto', [], [], 'riga'],
        'breve_frase' => ['in_breve', 'Frase sotto il titolo', '{numeri} per orientarti. Nelle pagine che seguono trovi da dove vengono e come leggerli.', ['numeri'], [], 'testo'],
        'breve_valore_t' => ['in_breve', 'Casella 1 · titolo', 'Il valore più probabile oggi', [], [], 'riga'],
        'breve_valore_n' => ['in_breve', 'Casella 1 · nota', 'Il centro della forchetta di stima.', [], [], 'casella'],
        'breve_forchetta_t' => ['in_breve', 'Casella 2 · titolo', 'La forchetta', [], [], 'riga'],
        'breve_forchetta_n' => ['in_breve', 'Casella 2 · nota', 'Con buona probabilità il valore reale cade qui dentro.', [], [], 'casella'],
        'breve_vendite_t' => ['in_breve', 'Casella 3 · titolo', 'Pagati nelle vendite vicine', [], [], 'riga'],
        'breve_vendite_n' => ['in_breve', 'Casella 3 · nota', 'Media di {compravendite} entro {distanza}. La stima: {valore_mq}.', ['compravendite', 'distanza', 'valore_mq'], [], 'casella'],
        'breve_tempo_t' => ['in_breve', 'Casella 4 · titolo', 'Quanto resta in vendita una casa', [], [], 'riga'],
        'breve_tempo_n' => ['in_breve', 'Casella 4 · nota', 'Media degli annunci della zona nel {anno_tempi}.', ['anno_tempi'], [], 'casella'],
        'breve_affitto_t' => ['in_breve', 'Casella 5 · titolo', 'L\'affitto stimato', [], [], 'riga'],
        'breve_affitto_n' => ['in_breve', 'Casella 5 · nota', 'In zona rende tra il {rendimento_minimo} e il {rendimento_massimo} lordo l\'anno.', ['rendimento_minimo', 'rendimento_massimo'], [], 'casella'],
        'breve_quotazioni_t' => ['in_breve', 'Casella 6 · titolo', 'Le quotazioni della zona dal {anno_inizio}', ['anno_inizio'], [], 'riga'],
        'breve_quotazioni_n' => ['in_breve', 'Casella 6 · nota', '{vendute} case vendute in zona nel {anno_vendite}, {variazione} sul {anno_prima}.', ['vendute', 'anno_vendite', 'variazione', 'anno_prima'], [], 'casella'],
        'breve_fonti_titolo' => ['in_breve', 'Riquadro delle fonti · titolo', 'Da dove vengono i numeri', [], [], 'riga'],
        'breve_fonti' => ['in_breve', 'Riquadro delle fonti · voci (una per riga)', "Il valore: un modello di valutazione automatica che confronta la casa con grandi archivi di dati immobiliari.\nLe quotazioni e le compravendite: l'Osservatorio del Mercato Immobiliare dell'Agenzia delle Entrate.\nGli annunci simili, con foto e descrizioni: gli annunci immobiliari pubblicati online.\nIl territorio, i dintorni e le mappe: enti pubblici e OpenStreetMap, elaborati da Cerca.Re.", [], [], 'righe'],
        'breve_fonti_nota' => ['in_breve', 'Riquadro delle fonti · nota in fondo', 'Il dettaglio è a pagina {pagina_metodo}.', ['pagina_metodo'], [], 'riga'],
        'breve_gestione' => ['in_breve', 'Nota sui costi di gestione (sotto le fonti)', 'La stima non considera le spese condominiali e gli altri costi di gestione della casa, che possono incidere sul prezzo finale.', [], [], 'nota'],
        'breve_indice_titolo' => ['in_breve', 'Indice · titolo', 'Nel report', [], [], 'riga'],

        'avv_occhiello' => ['avvertenze', 'Occhiello', 'Le regole', [], [], 'riga'],
        'avv_titolo' => ['avvertenze', 'Titolo (anche nell\'indice)', 'Avvertenze e diritti', [], [], 'riga'],
        'avv_frase' => ['avvertenze', 'Frase sotto il titolo', 'Che cosa vale per questo report, a chi appartiene e come usarlo.', [], [], 'testo'],
        'avv_elenco_titolo' => ['avvertenze', 'Titolo delle avvertenze', 'Avvertenze', [], [], 'riga'],
        'diritti_titolo' => ['avvertenze', '«Diritti e proprietà» · titolo', 'Diritti e proprietà', [], [], 'riga'],
        'diritti' => ['avvertenze', '«Diritti e proprietà» · testo (paragrafi separati da una riga vuota)', "© {anno} {titolare}. Tutti i diritti riservati. Questo report, con i suoi testi, grafici, mappe, elaborazioni e la sua impaginazione, è un'opera di {titolare}, titolare di Cerca.Re, protetta dalla legge sul diritto d'autore (legge 22 aprile 1941, n. 633) e dalle norme sulle banche dati.\n\nIl report è concesso a chi l'ha acquistato, per uso personale. Senza autorizzazione scritta di {titolare} sono vietati la riproduzione, anche parziale, la pubblicazione, la distribuzione, la vendita o la cessione a terzi, in qualunque forma e con qualunque mezzo, e l'uso per costruire servizi, archivi o prodotti concorrenti. Cerca.Re è un marchio di {titolare}.\n\nI dati di terzi indicati in «Da dove vengono i dati» restano dei rispettivi titolari e sono usati secondo le loro licenze.", [], [], 'paragrafi'],
        'chiusa_titolo' => ['avvertenze', 'Riquadro in fondo · titolo', 'Domande su questo report?', [], [], 'riga'],
        'chiusa_testo' => ['avvertenze', 'Riquadro in fondo · testo', 'Scrivi a {email} indicando il codice {codice}.', [], ['email', 'codice'], 'testo'],

        'contatti_email' => ['contatti', 'Email dell\'assistenza ({email})', 'supporto@cerca.re', [], [], 'riga'],
        'contatti_sito' => ['contatti', 'Sito ({sito})', 'cerca.re', [], [], 'riga'],

        // le pagine del lotto 2
        ...TestiMappe::TESTI,
        ...TestiPagine::TESTI,
    ];

    /** Le avvertenze di fabbrica (fac-simile del 05/11; la terza, sui costi di gestione, di D-VAL-51; l'ultima, uso personale, di D-VAL-52). */
    public const AVVERTENZE_FABBRICA = [
        'Questo report contiene una stima automatica del valore di mercato, elaborata da un modello statistico sui dati inseriti da chi l\'ha richiesto e sui dati di mercato disponibili alla data di creazione. Non è una perizia e non è una valutazione eseguita da un tecnico abilitato: non sostituisce una perizia dove questa è richiesta, per esempio per un mutuo, una successione, una divisione o un contenzioso.',
        'La stima non tiene conto di ciò che non è stato dichiarato o che non si può rilevare a distanza: lo stato reale dell\'immobile e degli impianti, la conformità urbanistica e catastale, vincoli, servitù e ipoteche, l\'esposizione e la vista. Se i dati inseriti non sono esatti, la stima può non esserlo.',
        'La stima si basa sui valori di mercato della zona e sulle caratteristiche dichiarate della casa. Non considera i costi di gestione propri dell\'immobile, come le spese condominiali, il riscaldamento centralizzato o i lavori già deliberati dal condominio. Spese di gestione alte possono rendere la vendita più lenta e abbassare il prezzo finale: è bene tenerne conto leggendo la stima.',
        'I valori sono indicativi. Il prezzo di una compravendita dipende dalla trattativa e dal momento e può discostarsi, anche in misura rilevante, dalla stima e dalla sua forchetta.',
        'Gli annunci citati sono pubblicati da terzi e riportano prezzi richiesti; foto e descrizioni sono quelle degli annunci. Le compravendite riportano prezzi risultanti da atti. Cerca.Re non verifica le singole informazioni contenute negli annunci.',
        'Zona sismica, alluvioni e frane sono dati ufficiali che riguardano il comune, non l\'edificio. La copertura in fibra è quella tecnica dichiarata da AGCOM e non indica un servizio attivo né la velocità della singola linea. Distanze e tempi dei dintorni sono indicativi.',
        'Il report si riferisce alla data di creazione ed è destinato all\'uso personale di chi l\'ha acquistato.',
    ];

    /** Avvertenze: almeno una, al massimo venti. */
    public const AVVERTENZE_MASSIMO = 20;

    /**
     * I riquadri: nome => [sezione, etichetta, acceso di fabbrica, si può
     * accendere oggi, nota].
     */
    public const RIQUADRI = [
        'cop_mappa' => ['copertina', 'Mappa della copertina', true, true, 'Spenta, la copertina si impagina senza, col logo in alto.'],
        'cop_caselle' => ['copertina', 'Caselle della casa (tipologia, superficie, piano, locali, zona)', true, true, null],
        'cop_forchetta' => ['copertina', 'Barra della forchetta', true, true, null],
        'cop_affitto' => ['copertina', 'Casella «affitto stimato»', true, true, null],
        'cop_ristrutturata' => ['copertina', 'Casella «ristrutturata a nuovo»', true, true, null],
        'cop_da_ristrutturare' => ['copertina', 'Casella «da ristrutturare»', true, true, null],
        'breve_valore' => ['in_breve', 'Casella 1 · il valore', true, true, null],
        'breve_forchetta' => ['in_breve', 'Casella 2 · la forchetta', true, true, null],
        'breve_vendite' => ['in_breve', 'Casella 3 · le vendite vicine', true, true, null],
        'breve_tempo' => ['in_breve', 'Casella 4 · il tempo di vendita', true, true, null],
        'breve_affitto' => ['in_breve', 'Casella 5 · l\'affitto', true, true, null],
        'breve_quotazioni' => ['in_breve', 'Casella 6 · le quotazioni', true, true, null],
        'breve_fonti' => ['in_breve', 'Riquadro «Da dove vengono i numeri»', true, true, null],
        'breve_gestione' => ['in_breve', 'Nota sui costi di gestione', true, true, 'Sta in fondo al riquadro «Da dove vengono i numeri»; se quel riquadro è spento, sta da sola al suo posto.'],
        'breve_indice' => ['in_breve', 'Indice «Nel report»', true, true, null],
        'avv_chiusa' => ['avvertenze', 'Riquadro in fondo (domande, titolare, «Powered by»)', true, true, null],
        ...TestiMappe::RIQUADRI,
        ...TestiPagine::RIQUADRI,
    ];

    /** Le pagine che si possono spegnere (copertina e avvertenze ci sono sempre). */
    public const PAGINE = [
        'in_breve' => ['Pagina «In breve»', true],
        ...TestiMappe::PAGINE,
        ...TestiPagine::PAGINE,
    ];

    /**
     * Tutte le chiavi che si cambiano solo da qui (Sistema → Impostazioni non le elenca).
     *
     * @return list<string>
     */
    public static function chiavi(): array
    {
        return [
            ...array_map(fn (string $n): string => self::PREFISSO.$n, array_keys(self::TESTI)),
            ...array_map(fn (string $n): string => self::PREFISSO_RIQUADRO.$n, array_keys(self::RIQUADRI)),
            ...array_map(fn (string $n): string => self::PREFISSO_PAGINA.$n, array_keys(self::PAGINE)),
            self::AVVERTENZE,
        ];
    }

    /**
     * Tutto ciò che vale oggi: i testi, le avvertenze, i riquadri, le pagine.
     *
     * @return array{testi: array<string, string>, avvertenze: list<string>, riquadri: array<string, bool>, pagine: array<string, bool>}
     */
    public static function tutti(): array
    {
        $testi = [];
        foreach (self::TESTI as $nome => $definizione) {
            $scritto = (string) Setting::get(self::PREFISSO.$nome, '');
            $testi[$nome] = trim($scritto) !== '' ? $scritto : $definizione[2];
        }
        $riquadri = [];
        foreach (self::RIQUADRI as $nome => [, , $fabbrica, $disponibile]) {
            $scritto = Setting::get(self::PREFISSO_RIQUADRO.$nome, '');
            $riquadri[$nome] = $disponibile && ($scritto === '' || $scritto === null ? $fabbrica : $scritto === '1');
        }
        $pagine = [];
        foreach (self::PAGINE as $nome => [, $fabbrica]) {
            $scritto = Setting::get(self::PREFISSO_PAGINA.$nome, '');
            $pagine[$nome] = $scritto === '' || $scritto === null ? $fabbrica : $scritto === '1';
        }

        return ['testi' => $testi, 'avvertenze' => self::avvertenze(), 'riquadri' => $riquadri, 'pagine' => $pagine];
    }

    /** @return list<string> */
    public static function avvertenze(): array
    {
        $scritte = json_decode((string) Setting::get(self::AVVERTENZE, ''), true);
        if (! is_array($scritte)) {
            return self::AVVERTENZE_FABBRICA;
        }
        $pulite = array_values(array_filter(array_map(fn ($a): string => is_string($a) ? trim($a) : '', $scritte), fn (string $a): bool => $a !== ''));

        return $pulite === [] ? self::AVVERTENZE_FABBRICA : $pulite;
    }

    /**
     * I segnaposti che un testo accetta.
     *
     * @return list<string>
     */
    public static function ammessi(string $nome): array
    {
        return [...self::COMUNI, ...(self::TESTI[$nome][3] ?? [])];
    }

    /**
     * Salva testi e avvertenze, SOLO se cambiati e solo se tutto torna
     * (tutto o niente), e ne fa una versione. Pagine e riquadri li salva
     * Disposizione. Ritorna [nomi cambiati, avvisi della guardia].
     *
     * @param  array<string, mixed>  $stato  testi per nome e «avvertenze» (lista)
     * @return array{0: list<string>, 1: list<string>}
     */
    public static function scrivi(mixed $chi, array $stato, bool $versione = true): array
    {
        if (! Permessi::puo($chi, 'valutazione_testi')) {
            throw new DomainException('I testi del report li cambia chi ha la casella «Valutazione · Testi».');
        }
        $nuovi = [];
        $cambiate = [];

        foreach (self::TESTI as $nome => [$sezione, $etichetta, $fabbrica, , , $tipo]) {
            $valore = self::pulisci(array_key_exists($nome, $stato) ? (string) $stato[$nome] : '', $tipo);
            $limite = self::MASSIMO[$tipo];
            if (mb_strlen($valore) > $limite) {
                throw new DomainException('«'.$etichetta.'» ('.self::SEZIONI[$sezione].') è troppo lungo: al massimo '.$limite.' caratteri. Non ho salvato nulla.');
            }
            $sconosciuti = Segnaposti::sconosciuti($valore, self::ammessi($nome));
            if ($sconosciuti !== []) {
                throw new DomainException('In «'.$etichetta.'» ('.self::SEZIONI[$sezione].') il segnaposto {'.implode('}, {', $sconosciuti).'} non esiste. Quelli possibili: {'.implode('}, {', self::ammessi($nome)).'}. Non ho salvato nulla.');
            }
            $nuovi[self::PREFISSO.$nome] = ($valore === '' || $valore === $fabbrica) ? null : $valore;
            $cambiate[self::PREFISSO.$nome] = $etichetta.' ('.self::SEZIONI[$sezione].')';
        }

        $avvertenze = [];
        foreach (array_values(is_array($stato['avvertenze'] ?? null) ? $stato['avvertenze'] : []) as $i => $avvertenza) {
            $testo = self::pulisci(is_array($avvertenza) ? (string) ($avvertenza['testo'] ?? '') : (string) $avvertenza, 'testo');
            if ($testo === '') {
                continue;
            }
            if (mb_strlen($testo) > self::MASSIMO['testo']) {
                throw new DomainException('L\'avvertenza n. '.($i + 1).' è troppo lunga: al massimo '.self::MASSIMO['testo'].' caratteri. Non ho salvato nulla.');
            }
            $sconosciuti = Segnaposti::sconosciuti($testo, self::COMUNI);
            if ($sconosciuti !== []) {
                throw new DomainException('Nell\'avvertenza n. '.($i + 1).' il segnaposto {'.implode('}, {', $sconosciuti).'} non esiste. Quelli possibili: {'.implode('}, {', self::COMUNI).'}. Non ho salvato nulla.');
            }
            $avvertenze[] = $testo;
        }
        if ($avvertenze === []) {
            throw new DomainException('Le avvertenze non possono mancare: ne serve almeno una. Non ho salvato nulla.');
        }
        if (count($avvertenze) > self::AVVERTENZE_MASSIMO) {
            throw new DomainException('Al massimo '.self::AVVERTENZE_MASSIMO.' avvertenze. Non ho salvato nulla.');
        }
        $nuovi[self::AVVERTENZE] = $avvertenze === self::AVVERTENZE_FABBRICA ? null : json_encode($avvertenze, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $cambiate[self::AVVERTENZE] = 'Avvertenze';

        $prima = $versione ? Versioni::serveLaPrima() : null;
        $fatte = [];
        DB::transaction(function () use ($nuovi, $cambiate, &$fatte): void {
            foreach ($nuovi as $chiave => $valore) {
                $prima = Setting::query()->where('key', $chiave)->value('value');
                $prima = $prima === '' ? null : $prima;
                if ($prima !== $valore) {
                    Setting::updateOrCreate(['key' => $chiave], ['value' => $valore]);
                    $fatte[] = $cambiate[$chiave];
                }
            }
        });
        if ($versione && $fatte !== []) {
            Versioni::registra($chi, 'Testi del report', $fatte, $prima);
        }

        return [$fatte, Guardia::nelleImpostazioni(Fotografia::adesso())];
    }

    /** Il testo come si salva: righe pulite, niente spazi in testa e in coda. */
    public static function pulisci(string $testo, string $tipo): string
    {
        $testo = str_replace(["\r\n", "\r"], "\n", $testo);
        if ($tipo === 'riga' || $tipo === 'casella' || $tipo === 'nota') {
            return trim(preg_replace('/\s+/u', ' ', $testo) ?? '');
        }
        $righe = array_map(fn (string $r): string => trim(preg_replace('/[ \t]+/u', ' ', $r) ?? ''), explode("\n", $testo));
        if ($tipo === 'righe') {
            return implode("\n", array_values(array_filter($righe, fn (string $r): bool => $r !== '')));
        }

        // testo e paragrafi: al massimo una riga vuota fra due paragrafi
        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $righe)) ?? '');
    }

    /**
     * Le voci di un testo «righe» o i paragrafi di un testo «paragrafi».
     *
     * @return list<string>
     */
    public static function pezzi(string $testo, string $tipo): array
    {
        $separatore = $tipo === 'paragrafi' ? "/\n\s*\n/" : "/\n/";

        return array_values(array_filter(array_map('trim', preg_split($separatore, $testo) ?: []), fn (string $p): bool => $p !== ''));
    }
}