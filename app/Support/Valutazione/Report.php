<?php

namespace App\Support\Valutazione;

use App\Support\Valutazione\Mappe\Materiale;
use App\Support\Valutazione\Report\Contesto;
use App\Support\Valutazione\Report\Documento;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * IL MOTORE DEL REPORT (D-VAL-17, D-VAL-21, D-VAL-40, D-VAL-42): dai dati
 * puliti e dalla fotografia di testi e impostazioni al PDF, con mPDF.
 *
 *  0. la memoria si alza alla «Memoria per fare il PDF» (vuoto = 256M) se è
 *     più bassa, ovunque parta (pagina web, Toolkit, attività pianificata),
 *     e resta così fino alla fine della richiesta o del comando: rimetterla
 *     più bassa subito dopo rischierebbe di far cadere quello che viene
 *     dopo il PDF (salvarlo, allegarlo);
 *  1. la guardia deve essere pronta (il segreto nel .env), altrimenti niente;
 *  2. si compongono le pagine accese, in HTML (Report\Documento);
 *  3. la guardia controlla i testi dell'Admin uno per uno e l'HTML di ogni
 *     pagina: se trova una parola vietata il PDF non nasce, e si dice dove;
 *  4. mPDF scrive il PDF una pagina alla volta (con le mappe una pagina
 *     supera 1 MB di HTML: il limite di PCRE si alza per questo PDF e poi
 *     torna com'era), coi caratteri incorporati, i metadati a nome
 *     Cerca.Re (con il Copyright) e, se chiesta, la filigrana su ogni
 *     pagina («ANTEPRIMA — non valido»);
 *  5. la guardia controlla il PDF finito: metadati e parti in chiaro.
 *
 * È un servizio del cuore comune (sito = app): l'anteprima dell'Admin, la
 * diagnosi e domani la generazione vera (lotti 6–8) chiamano questo.
 *
 * Dal lotto 4: il materiale delle mappe arriva già letto (Mappe\Raccolta,
 * prima del PDF); la guardia non conta le parole generiche dentro i nomi
 * di luogo stampati (D-VAL-105 A); l'esito dice quali mappe non sono
 * uscite («avvisi») e com'è la casa sulla mappa («controllo», D-VAL-99).
 */
final class Report
{
    /** I caratteri del report (D-VAL-37): famiglia di mPDF => file in resources/fonts/valutazione. */
    public const CARATTERI = [
        'jakarta' => ['R' => 'PlusJakartaSans-Regular.ttf', 'B' => 'PlusJakartaSans-Bold.ttf'],
        'jakartasemi' => ['R' => 'PlusJakartaSans-SemiBold.ttf'],
        'jakartaxb' => ['R' => 'PlusJakartaSans-ExtraBold.ttf'],
        'plexmono' => ['R' => 'IBMPlexMono-Regular.ttf'],
    ];

    /** La filigrana dell'anteprima (D-VAL-41). */
    public const FILIGRANA_ANTEPRIMA = 'ANTEPRIMA — non valido';

    /** Quanto si vede la filigrana (0 = niente, 1 = pieno). */
    public const FILIGRANA_INTENSITA = 0.07;

    /**
     * Il limite di PCRE (pcre.backtrack_limit) per questo PDF: almeno tante
     * volte il pezzo di HTML più lungo. mPDF rifiuta un pezzo più lungo del
     * limite, e le sue regole e quelle della guardia lavorano su quei pezzi.
     */
    public const MARGINE_PCRE = 4;

    /**
     * Il PDF, se tutto torna, con la memoria alzata (vedi alzaMemoria()).
     *
     * @param  array<string, mixed>  $dati  i dati puliti (Stima\Normalizza)
     * @param  array<string, mixed>  $fotografia  testi e impostazioni (Fotografia)
     * @param  array<string, string>  $pagineInPiu  nome => HTML di pagine aggiunte in fondo (solo la diagnosi)
     * @param  array<int, string>  $foto  numero dell'annuncio (da 0) => file della sua foto (lotto 2)
     * @param  Materiale|null  $mappe  il materiale delle mappe (lotto 4); senza, il report esce senza mappe
     * @return array{pdf: string, pagine: int, attese: int, metadati: array<string, string>, avvisi: list<string>, controllo: ?array}
     *
     * @throws ReportFermo
     */
    public static function pdf(array $dati, array $fotografia, ?string $filigrana = null, array $pagineInPiu = [], array $foto = [], ?Materiale $mappe = null): array
    {
        self::alzaMemoria();

        return self::componi($dati, $fotografia, $filigrana, $pagineInPiu, $foto, $mappe);
    }

    /**
     * Alza memory_limit alla «Memoria per fare il PDF» (vuoto = 256M), solo
     * se oggi è più basso (-1 = senza limite: non si tocca). I comandi del
     * Toolkit e le attività pianificate non hanno i 256M dati al sito in
     * Plesk. Non si rimette più bassa dopo (vedi in cima). Se il server non
     * lascia alzarla, si prova con quella che c'è: la diagnosi lo dice.
     */
    private static function alzaMemoria(): void
    {
        try {
            $mega = Impostazioni::memoriaPdfMb();
        } catch (\Throwable) {
            $mega = Impostazioni::MEMORIA_FABBRICA_MB;
        }
        $ora = Impostazioni::byteDaIni((string) ini_get('memory_limit'));
        if ($ora !== -1 && $ora < $mega * 1024 * 1024) {
            @ini_set('memory_limit', $mega.'M');
        }
    }

    /**
     * Il PDF vero e proprio (vedi pdf()).
     *
     * @param  array<string, mixed>  $dati
     * @param  array<string, mixed>  $fotografia
     * @param  array<string, string>  $pagineInPiu
     * @param  array<int, string>  $foto
     * @return array{pdf: string, pagine: int, attese: int, metadati: array<string, string>, avvisi: list<string>, controllo: ?array}
     *
     * @throws ReportFermo
     */
    private static function componi(array $dati, array $fotografia, ?string $filigrana, array $pagineInPiu, array $foto, ?Materiale $mappe): array
    {
        $motivo = Guardia::pronta();
        if ($motivo !== null) {
            throw new ReportFermo([$motivo]);
        }
        if (! is_file((string) config('valutazione.logo'))) {
            throw new ReportFermo(['Manca il logo del report (public/cercare.png): senza, il PDF non esce.']);
        }

        $contesto = new Contesto($dati, $fotografia, $filigrana !== null, $foto, $mappe);
        $generiche = Guardia::generiche($fotografia);
        $pagine = Documento::pagine($contesto);
        foreach ($pagineInPiu as $nome => $html) {
            $pagine['in_piu_'.$nome] = [$nome, $html];
        }

        $pezzi = Documento::pezzi($contesto, $pagine);
        // i nomi di luogo stampati, dopo aver disegnato le mappe (D-VAL-105 A)
        $luoghi = $contesto->luoghi();
        $limitePrima = self::alzaLimite($pezzi);
        try {
            $problemi = Guardia::nelleImpostazioni($fotografia);
            $numero = 0;
            foreach ($pagine as [$nome, $html]) {
                $numero++;
                $problemi = [...$problemi, ...Guardia::nellHtml($html, 'Pagina '.$numero.' «'.$nome.'»', $generiche, null, $luoghi)];
            }
            $problemi = [...$problemi, ...Guardia::nellHtml(Documento::testataEPiede($contesto), 'Testata e piè di pagina', $generiche, null, $luoghi)];
            $metadati = self::metadati($contesto);
            foreach ($metadati as $voce => $valore) {
                $problemi = [...$problemi, ...Guardia::nellHtml(htmlspecialchars($valore), 'Metadati del PDF → '.$voce, $generiche, null, $luoghi)];
            }
            if ($problemi !== []) {
                throw new ReportFermo(array_values(array_unique($problemi)));
            }

            $mpdf = self::mpdf();
            $mpdf->SetTitle($metadati['Title']);
            $mpdf->SetAuthor($metadati['Author']);
            $mpdf->SetSubject($metadati['Subject']);
            $mpdf->SetKeywords($metadati['Keywords']);
            $mpdf->SetCreator($metadati['Creator']);
            $mpdf->AddCustomProperty('Copyright', $metadati['Copyright']);
            if ($filigrana !== null && $filigrana !== '') {
                $mpdf->SetWatermarkText($filigrana, self::FILIGRANA_INTENSITA);
                $mpdf->watermark_font = 'jakartaxb';
                $mpdf->showWatermarkText = true;
            }
            $mpdf->WriteHTML(Documento::CSS, HTMLParserMode::HEADER_CSS);
            $nomi = array_column(array_values($pagine), 0);
            foreach ($pezzi as $i => $pezzo) {
                $primaDi = (int) $mpdf->page;
                $mpdf->WriteHTML($pezzo, HTMLParserMode::HTML_BODY);
                // dalla seconda in poi ogni pezzo apre una pagina: se non c'è, mPDF l'ha persa
                if ($i > 0 && (int) $mpdf->page <= $primaDi) {
                    throw new ReportFermo(['La pagina '.($i + 1).' «'.($nomi[$i] ?? '?').'» non è stata scritta nel PDF.']);
                }
            }
            $pagineFatte = (int) $mpdf->page;
            $pdf = $mpdf->Output('', Destination::STRING_RETURN);

            $problemi = Guardia::nelPdf($pdf, $generiche, null, $luoghi);
            $scritti = Guardia::metadati($pdf);
            foreach (['Title', 'Author', 'Copyright'] as $voce) {
                if (trim($scritti[$voce] ?? '') === '') {
                    $problemi[] = 'Il PDF finito non ha il metadato «'.$voce.'».';
                }
            }
            if ($problemi !== []) {
                throw new ReportFermo($problemi);
            }

            return ['pdf' => $pdf, 'pagine' => $pagineFatte, 'attese' => count($pagine), 'metadati' => $scritti, 'avvisi' => $contesto->avvisiMappe(), 'controllo' => $contesto->controllo()];
        } finally {
            if ($limitePrima !== null) {
                ini_set('pcre.backtrack_limit', $limitePrima);
            }
        }
    }

    /**
     * Alza pcre.backtrack_limit se un pezzo di HTML lo chiede (una pagina con
     * le mappe supera il milione di caratteri). Restituisce il valore di
     * prima, da rimettere, o null se andava già bene.
     *
     * @param  list<string>  $pezzi
     *
     * @throws ReportFermo se il server non lascia alzarlo
     */
    private static function alzaLimite(array $pezzi): ?string
    {
        $piuLungo = max(array_map('strlen', [...$pezzi, '']));
        $serve = self::MARGINE_PCRE * $piuLungo;
        $prima = (string) ini_get('pcre.backtrack_limit');
        if ((int) $prima >= $serve) {
            return null;
        }
        if (ini_set('pcre.backtrack_limit', (string) $serve) === false) {
            throw new ReportFermo(['Una pagina del report ha '.number_format($piuLungo / 1024, 0, ',', '.').' KB di HTML e il server non lascia alzare il limite «pcre.backtrack_limit» (oggi '.$prima.'): va alzato almeno a '.$serve.' nelle impostazioni PHP di Plesk.']);
        }

        return $prima;
    }

    /**
     * I metadati del PDF, dalla fotografia (D-VAL-40): a nome Cerca.Re, con il Copyright.
     *
     * @return array{Title: string, Author: string, Subject: string, Keywords: string, Creator: string, Copyright: string}
     */
    public static function metadati(Contesto $c): array
    {
        $valori = $c->comuni();
        $v = fn (string $chiave): string => trim((string) Segnaposti::testo($c->impostazione($chiave), $valori));

        return [
            'Title' => $v('valutazione_pdf_titolo'),
            'Author' => $v('valutazione_pdf_autore'),
            'Subject' => $v('valutazione_pdf_soggetto'),
            'Keywords' => $v('valutazione_pdf_parole_chiave'),
            'Creator' => $v('valutazione_pdf_autore'),
            'Copyright' => $v('valutazione_pdf_copyright'),
        ];
    }

    /** mPDF pronto: A4, caratteri del report, cartella di lavoro privata. */
    public static function mpdf(): Mpdf
    {
        $cartella = (string) config('valutazione.mpdf_temp');
        if (! is_dir($cartella)) {
            @mkdir($cartella, 0775, true);
        }
        $base = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $caratteri = (new \Mpdf\Config\FontVariables())->getDefaults();

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_top' => 14,
            'margin_bottom' => 20,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => $cartella,
            'fontDir' => [...(array) $base['fontDir'], (string) config('valutazione.caratteri')],
            'fontdata' => $caratteri['fontdata'] + self::CARATTERI,
            'default_font' => 'jakarta',
            'default_font_size' => 9.2,
            'autoScriptToLang' => false,
            'autoLangToFont' => false,
            'useSubstitutions' => false,
            'mirrorMargins' => 0,
            // un'immagine PNG o JPG che non si legge o non c'è ferma il PDF invece
            // di sparire senza dire niente (un SVG rotto invece mPDF non lo segnala)
            'showImageErrors' => true,
        ]);
    }
}