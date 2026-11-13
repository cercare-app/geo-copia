<?php

namespace App\Support\Valutazione;

/**
 * LA GUARDIA SUL NOME (D-VAL-22, D-VAL-42): nessun PDF esce se contiene una
 * parola vietata.
 *
 *  - Le parole del nome stanno SOLO nel segreto STIMA_PAROLE_VIETATE del
 *    .env (separate da virgole): mai nel repo, mai a video. Senza il
 *    segreto, o col segreto scritto male, la guardia non è pronta e non
 *    esce nessun PDF (pronta()).
 *  - Si cercano anche le parole generiche e le note interne (GENERICHE,
 *    NOTE_INTERNE), più quelle che l'Admin aggiunge: queste si possono
 *    nominare nei messaggi, le parole del segreto mai.
 *  - Dove si guarda: i testi dell'Admin uno per uno (nelleImpostazioni, per
 *    dire QUALE testo), l'HTML di ogni pagina, che contiene esattamente le
 *    parole stampate (nellHtml), e il PDF finito: i metadati e le parti in
 *    chiaro, come i link (nelPdf). Il testo del PDF finito non si legge in
 *    modo affidabile, perché i caratteri incorporati vi scrivono codici e
 *    non lettere: per questo il testo si controlla sull'HTML.
 *  - Il confronto ignora maiuscole, spazi doppi, trattini morbidi e
 *    caratteri invisibili; le parole del segreto si cercano anche «tutte
 *    attaccate» (senza spazi, trattini e punti).
 *  - I NOMI DI LUOGO (lotto 4, D-VAL-105 A): le parole generiche non
 *    contano quando fanno parte di un nome di luogo che il report stampa
 *    (l'indirizzo della casa, gli indirizzi di annunci e vendite, i nomi
 *    delle vie sulle mappe): una via che ne contiene una è una via, non un
 *    richiamo a chi fa la stima. Le parole del segreto invece si cercano
 *    sempre e ovunque, anche nei nomi di luogo.
 */
final class Guardia
{
    /** Le parole generiche (D-VAL-42): richiamano chi fa la stima. */
    public const GENERICHE = ['fornitore', 'partner', 'unione europea', 'versione del modello'];

    /** Le note interne che non devono finire in un report. */
    public const NOTE_INTERNE = ['provvisori', 'fac-simile', 'facsimile', 'in arrivo'];

    /** Le voci del PDF che contano come metadati. */
    public const METADATI = ['Title', 'Author', 'Subject', 'Keywords', 'Creator', 'Producer', 'Copyright'];

    /**
     * Perché la guardia NON è pronta, o null se è pronta.
     */
    public static function pronta(?string $segreto = null): ?string
    {
        $segreto ??= (string) config('valutazione.parole_vietate', '');
        $parole = self::divisa($segreto);
        if ($parole === []) {
            return 'Manca il segreto STIMA_PAROLE_VIETATE nel .env del server: senza, la guardia ferma tutto e nessun PDF esce.';
        }
        foreach ($parole as $parola) {
            if (mb_strlen(self::compatta($parola)) < Impostazioni::PAROLA_MINIMA) {
                return 'Il segreto STIMA_PAROLE_VIETATE è scritto male: ogni parola, separata da virgole, deve avere almeno '.Impostazioni::PAROLA_MINIMA.' lettere. Nessun PDF esce finché non è corretto.';
            }
        }

        return null;
    }

    /** Lo stato del segreto in parole, senza mai mostrarne il contenuto. */
    public static function statoSegreto(?string $segreto = null): string
    {
        $segreto ??= (string) config('valutazione.parole_vietate', '');
        $motivo = self::pronta($segreto);
        if ($motivo !== null) {
            return $motivo;
        }
        $n = count(self::divisa($segreto));

        return 'Presente nel .env: '.$n.' '.($n === 1 ? 'parola' : 'parole').' (non si mostrano).';
    }

    /**
     * Le parole generiche di oggi: fabbrica, note interne e quelle in più dell'Admin.
     *
     * @param  array<string, mixed>|null  $fotografia
     * @return list<string>
     */
    public static function generiche(?array $fotografia = null): array
    {
        $inPiu = $fotografia !== null
            ? Impostazioni::paroleInPiu((string) ($fotografia['impostazioni'][Impostazioni::PAROLE_IN_PIU] ?? ''))
            : Impostazioni::paroleInPiu();

        return array_values(array_unique([...self::GENERICHE, ...self::NOTE_INTERNE, ...$inPiu]));
    }

    /**
     * I testi e le impostazioni della fotografia, uno per uno: dice QUALE
     * testo contiene una parola vietata.
     *
     * @param  array<string, mixed>  $fotografia
     * @return list<string>
     */
    public static function nelleImpostazioni(array $fotografia, ?string $segreto = null): array
    {
        $trovati = [];
        $motivo = self::pronta($segreto);
        if ($motivo !== null) {
            $trovati[] = $motivo;
        }
        $generiche = self::generiche($fotografia);
        foreach ($fotografia['testi'] ?? [] as $nome => $testo) {
            $definizione = Testi::TESTI[$nome] ?? null;
            $dove = $definizione !== null ? 'Testi del report → '.Testi::SEZIONI[$definizione[0]].' → «'.$definizione[1].'»' : 'Testi del report → '.$nome;
            $trovati = [...$trovati, ...self::nelTesto((string) $testo, $dove, $generiche, $segreto)];
        }
        foreach ($fotografia['avvertenze'] ?? [] as $i => $testo) {
            $trovati = [...$trovati, ...self::nelTesto((string) $testo, 'Testi del report → Avvertenze → n. '.($i + 1), $generiche, $segreto)];
        }
        foreach ($fotografia['impostazioni'] ?? [] as $chiave => $valore) {
            if (in_array($chiave, [Impostazioni::PAROLE_IN_PIU, Impostazioni::PREZZO, Impostazioni::POWERED_ACCESO], true)) {
                continue;
            }
            $dove = 'Impostazioni → «'.(Impostazioni::CAMPI[$chiave][0] ?? $chiave).'»';
            $trovati = [...$trovati, ...self::nelTesto((string) $valore, $dove, $generiche, $segreto)];
        }

        return $trovati;
    }

    /**
     * Una pagina del report: il testo che si vede (con e senza i tag) e
     * l'HTML stesso (link, testi alternativi, SVG), senza le immagini incorporate.
     *
     * @param  list<string>  $generiche
     * @return list<string>
     */
    public static function nellHtml(string $html, string $dove, array $generiche, ?string $segreto = null, array $luoghi = []): array
    {
        // i disegni SVG incorporati hanno testo (etichette, numeri): si leggono anche loro
        $svg = '';
        $quanti = preg_match_all('#data:image/svg\+xml;base64,([A-Za-z0-9+/=]+)#i', $html, $incorporati);
        if ($quanti === false) {
            // non dovrebbe succedere (lo schema non torna indietro), ma se non
            // si riesce a leggere non si lascia passare
            return [$dove.': non sono riuscito a leggere i disegni incorporati (errore '.preg_last_error().').'];
        }
        foreach ($incorporati[1] as $base64) {
            $svg .= ' '.(string) base64_decode($base64, true);
        }
        $html .= $svg;
        // le immagini incorporate (il logo, le icone) sono byte, non parole: fuori
        $grezzo = preg_replace('#data:[a-z0-9.+/-]+;base64,[A-Za-z0-9+/=\s]+#i', ' ', $html);
        $senzaStile = $grezzo === null ? null : preg_replace('#<(style|script)\b[^>]*>.*?</\1>#is', ' ', $grezzo);
        if ($grezzo === null || $senzaStile === null) {
            return [$dove.': non sono riuscito a leggere la pagina (errore '.preg_last_error().').'];
        }
        $attaccato = html_entity_decode(strip_tags($senzaStile), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $staccato = html_entity_decode(strip_tags(preg_replace('/</', ' <', $senzaStile) ?? $senzaStile), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $attributi = html_entity_decode($grezzo, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $trovati = [];
        foreach ([$staccato, $attaccato, $attributi] as $testo) {
            foreach (self::nelTesto($testo, $dove, $generiche, $segreto, $luoghi) as $t) {
                $trovati[$t] = true;
            }
        }

        return array_keys($trovati);
    }

    /**
     * Il PDF finito: i metadati (titolo, autore, soggetto, parole chiave,
     * Copyright…) e le parti in chiaro del file, come i link.
     *
     * @param  list<string>  $generiche
     * @return list<string>
     */
    public static function nelPdf(string $pdf, array $generiche, ?string $segreto = null, array $luoghi = []): array
    {
        $trovati = [];
        foreach (self::metadati($pdf) as $voce => $valore) {
            $trovati = [...$trovati, ...self::nelTesto($valore, 'Metadati del PDF → '.$voce, $generiche, $segreto, $luoghi)];
        }
        // le parti in chiaro (link, nomi dei caratteri, dizionari): solo le parole del segreto;
        // i flussi compressi (pagine, caratteri, immagini) sono byte a caso e restano fuori,
        // altrimenti una parola corta ci si troverebbe per caso
        $chiaro = strtolower(self::senzaFlussi($pdf));
        foreach (self::divisa($segreto ?? (string) config('valutazione.parole_vietate', '')) as $parola) {
            if ($parola !== '' && str_contains($chiaro, $parola)) {
                $trovati[] = 'Il file PDF contiene in chiaro una parola del nome vietato (dal segreto).';
                break;
            }
        }

        return array_values(array_unique($trovati));
    }

    /**
     * Il PDF senza i flussi («stream» … «endstream»), senza espressioni
     * regolari: un flusso può pesare megabyte.
     */
    private static function senzaFlussi(string $pdf): string
    {
        $fuori = '';
        $da = 0;
        while (($inizio = strpos($pdf, 'stream', $da)) !== false) {
            $dopo = $inizio + 6;
            $acapo = substr($pdf, $dopo, 2) === "\r\n" ? 2 : (($pdf[$dopo] ?? '') === "\n" ? 1 : 0);
            $prima = $inizio - 1;
            while ($prima >= 0 && str_contains(" \t\r\n", $pdf[$prima])) {
                $prima--;
            }
            if ($acapo === 0 || $prima < 1 || substr($pdf, $prima - 1, 2) !== '>>') {
                // un flusso comincia solo dopo il suo dizionario («>>») e con
                // un a capo: «endstream», «(upstream)» e simili non lo aprono
                $fuori .= substr($pdf, $da, $dopo - $da);
                $da = $dopo;

                continue;
            }
            $fine = strpos($pdf, 'endstream', $dopo + $acapo);
            if ($fine === false) {
                // flusso senza fine: nel dubbio il resto si legge tutto
                return $fuori.substr($pdf, $da);
            }
            $fuori .= substr($pdf, $da, $inizio - $da).' ';
            $da = $fine + 9;
        }

        return $fuori.substr($pdf, $da);
    }

    /**
     * I metadati del PDF: voce => testo (le stringhe UTF-16 decodificate).
     *
     * @return array<string, string>
     */
    public static function metadati(string $pdf): array
    {
        $fuori = [];
        $schema = '#/('.implode('|', self::METADATI).')\s*(\((?:\\\\.|[^\\\\)])*\)|<[0-9A-Fa-f\s]*>)#s';
        if (! preg_match_all($schema, $pdf, $trovati, PREG_SET_ORDER)) {
            return [];
        }
        foreach ($trovati as [, $voce, $stringa]) {
            if ($stringa[0] === '<') {
                $esadecimale = preg_replace('/\s+/', '', substr($stringa, 1, -1)) ?? '';
                $byte = (string) hex2bin(strlen($esadecimale) % 2 === 1 ? $esadecimale.'0' : $esadecimale);
            } else {
                $byte = self::letterale(substr($stringa, 1, -1));
            }
            $testo = str_starts_with($byte, "\xFE\xFF") ? (string) mb_convert_encoding(substr($byte, 2), 'UTF-8', 'UTF-16BE') : (mb_check_encoding($byte, 'UTF-8') ? $byte : (string) mb_convert_encoding($byte, 'UTF-8', 'ISO-8859-1'));
            $fuori[$voce] = isset($fuori[$voce]) ? $fuori[$voce].' · '.$testo : $testo;
        }

        return $fuori;
    }

    // ── I confronti ─────────────────────────────────────────────────────

    /**
     * @param  list<string>  $generiche
     * @return list<string>
     */
    private static function nelTesto(string $testo, string $dove, array $generiche, ?string $segreto, array $luoghi = []): array
    {
        $trovati = [];
        $normale = self::normale($testo);
        $compatto = self::compatta($testo);
        foreach (self::divisa($segreto ?? (string) config('valutazione.parole_vietate', '')) as $parola) {
            $p = self::normale($parola);
            $c = self::compatta($parola);
            if (($p !== '' && str_contains($normale, $p)) || ($c !== '' && str_contains($compatto, $c))) {
                $trovati[] = $dove.': contiene una parola del nome vietato (dal segreto).';
                break;
            }
        }
        // per le generiche si tolgono prima i nomi di luogo stampati (D-VAL-105 A), dal più lungo
        $senzaLuoghi = $normale;
        $nomi = array_values(array_filter(array_map(fn ($l): string => self::normale((string) $l), $luoghi), fn (string $l): bool => mb_strlen($l) >= 3));
        usort($nomi, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        foreach ($nomi as $nome) {
            $senzaLuoghi = str_replace($nome, ' ', $senzaLuoghi);
        }
        foreach ($generiche as $parola) {
            $p = self::normale($parola);
            if ($p !== '' && str_contains($senzaLuoghi, $p)) {
                $trovati[] = $dove.': contiene «'.$parola.'».';
            }
        }

        return $trovati;
    }

    /**
     * Il testo contiene una parola del segreto? Per le mappe (D-VAL-105 A):
     * una via che la contenesse non si scrive. Senza segreto, no (il PDF lo
     * ferma comunque pronta()).
     */
    public static function haParolaSegreta(string $testo, ?string $segreto = null): bool
    {
        $normale = self::normale($testo);
        $compatto = self::compatta($testo);
        foreach (self::divisa($segreto ?? (string) config('valutazione.parole_vietate', '')) as $parola) {
            $p = self::normale($parola);
            $c = self::compatta($parola);
            if (($p !== '' && str_contains($normale, $p)) || ($c !== '' && str_contains($compatto, $c))) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function divisa(string $segreto): array
    {
        $parole = array_map(fn (string $p): string => self::normale($p), preg_split('/[,;\n\r]+/u', $segreto) ?: []);

        return array_values(array_unique(array_filter($parole, fn (string $p): bool => $p !== '')));
    }

    /** Minuscolo, senza invisibili, spazi uniti (mb_strtolower rende «?» i byte non validi). */
    private static function normale(string $testo): string
    {
        $testo = preg_replace('/[\x{AD}\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $testo) ?? $testo;
        $testo = preg_replace('/[\s\x{A0}\x{202F}]+/u', ' ', $testo) ?? $testo;

        return trim(mb_strtolower($testo));
    }

    /** Solo lettere e cifre, minuscole: «Foo-Bar.it» → «foobarit». */
    private static function compatta(string $testo): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', self::normale($testo)) ?? '';
    }

    /** Una stringa letterale del PDF, con le sequenze \( \) \\ \n \r \t \ddd. */
    private static function letterale(string $s): string
    {
        return preg_replace_callback('/\\\\([nrtbf()\\\\]|[0-7]{1,3}|\r?\n)/', function (array $m): string {
            return match (true) {
                $m[1] === 'n' => "\n",
                $m[1] === 'r' => "\r",
                $m[1] === 't' => "\t",
                $m[1] === 'b' => "\x08",
                $m[1] === 'f' => "\x0C",
                ctype_digit($m[1]) => chr(octdec($m[1]) & 0xFF),
                $m[1][0] === "\r" || $m[1][0] === "\n" => '',
                default => $m[1],
            };
        }, $s) ?? $s;
    }
}