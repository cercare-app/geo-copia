<?php

namespace App\Support\Valutazione\Report;

use App\Support\Valutazione\Grafici;

/**
 * IL DOCUMENTO PER mPDF (D-VAL-21, D-VAL-40): il foglio di stile, la
 * testata e il piè delle pagine interne, e le pagine in fila.
 *
 * Regole di mPDF rispettate (lette nel suo codice, versione 8.3.1):
 *  - dentro una cella di tabella i blocchi perdono sfondo, bordi e angoli:
 *    i riquadri arrotondati sono blocchi galleggianti (float) con la loro
 *    larghezza, mai celle;
 *  - un blocco a posizione assoluta deve stare al primo livello;
 *  - niente ::before, niente flex e grid; i pesi del carattere sono
 *    famiglie diverse (jakarta col suo grassetto, jakartasemi, jakartaxb,
 *    plexmono), perché mPDF conosce solo normale e grassetto;
 *  - mPDF applica le classi di un elemento in ordine alfabetico, non in
 *    quello del foglio: la classe che deve vincere ha il nome che viene
 *    dopo («tl-n» poi «tl-n-lungo»);
 *  - la copertina usa la pagina di base (senza testata né piè); le altre
 *    la pagina «corpo», con testata, piè e numero di pagina.
 */
final class Documento
{
    public const CSS = <<<'CSS'
@page { margin-top: 14mm; margin-bottom: 20mm; margin-left: 16mm; margin-right: 16mm; margin-header: 0; margin-footer: 0; }
@page corpo { margin-top: 25mm; margin-bottom: 18mm; margin-left: 16mm; margin-right: 16mm; margin-header: 9mm; margin-footer: 8mm; odd-header-name: html_testa; even-header-name: html_testa; odd-footer-name: html_piede; even-footer-name: html_piede; }
body { font-family: jakarta; font-size: 9.2pt; line-height: 1.5; color: #111827; }
p { margin: 0; padding: 0; }
strong, b { font-family: jakartaxb; font-weight: normal; }
a { color: #1E3A8A; text-decoration: none; font-family: jakarta; font-weight: bold; }
.pulisci { clear: both; height: 0.1mm; font-size: 1pt; line-height: 1pt; }

table.testa { width: 100%; border-collapse: collapse; }
table.testa td { border-bottom: 0.3mm solid #E3E8F0; padding: 0 0 1.4mm 0; vertical-align: bottom; }
td.t-dx { text-align: right; font-family: plexmono; font-size: 6.4pt; letter-spacing: 0.8pt; text-transform: uppercase; color: #5B6577; }
img.t-logo { height: 8.2mm; }
table.piede { width: 100%; border-collapse: collapse; }
table.piede td { border-top: 0.3mm solid #E3E8F0; padding: 2mm 0 0 0; font-size: 6.8pt; color: #5B6577; vertical-align: top; }
td.p-dx { text-align: right; }
span.pn { font-family: plexmono; color: #111827; }

.cop-testa { height: 15mm; }
img.cop-logo { height: 13mm; }
p.cop-k { margin-top: 40mm; font-family: plexmono; font-size: 8pt; letter-spacing: 1.1pt; text-transform: uppercase; color: #5B6577; }
img.cop-tratto { width: 9mm; height: 1mm; vertical-align: middle; }
h1 { font-family: jakartaxb; font-weight: normal; font-size: 34pt; line-height: 1.05; letter-spacing: -1.2pt; margin: 3mm 0 1.4mm 0; color: #111827; }
p.cop-citta { font-family: jakartasemi; font-size: 15pt; color: #374151; }
div.chips { margin-top: 5mm; }
div.chip { float: left; padding: 0.8mm 3.2mm 0.8mm 3.2mm; margin: 0 1.4mm 1.6mm 0; border: 0.3mm solid #DBE7FE; border-radius: 3mm; background-color: #ffffff; font-family: jakartasemi; font-size: 8.4pt; line-height: 1.3; color: #1E3A8A; }
div.cop-riga { margin-top: 8mm; }
div.cop-carta { float: left; padding: 6mm 9mm 6mm 9mm; background-color: #ffffff; border: 0.3mm solid #E3E8F0; border-radius: 7mm; }
img.cc-riga { margin-bottom: 1mm; }
p.cc-k { font-family: plexmono; font-size: 7.4pt; letter-spacing: 0.9pt; text-transform: uppercase; color: #2563EB; margin-top: 3mm; }
p.cc-n { font-family: jakartaxb; font-size: 46pt; letter-spacing: -2pt; line-height: 1.1; color: #111827; margin-top: 1mm; }
span.cc-eu { font-size: 28pt; color: #2563EB; letter-spacing: 0; }
p.cc-mq { font-size: 9.6pt; color: #374151; margin-top: 0.6mm; }
div.cc-barra { margin-top: 6mm; }
div.cop-lato { float: right; width: 55mm; }
div.cl { background-color: #F6F8FC; border-radius: 4mm; padding: 3.4mm 4.2mm 3.4mm 4.2mm; margin-bottom: 2.6mm; }
p.ct-k { font-family: jakartasemi; font-size: 7.6pt; color: #5B6577; }
p.ct-n { font-family: jakartaxb; font-size: 15pt; letter-spacing: -0.3pt; margin-top: 0.8mm; color: #111827; }
span.ct-u { font-family: jakartasemi; font-size: 8pt; color: #5B6577; letter-spacing: 0; }
div.cop-piede { position: absolute; left: 16mm; bottom: 12mm; width: 178mm; }
table.cop-piede-t { width: 100%; border-collapse: collapse; }
table.cop-piede-t td { border-top: 0.3mm solid #DDE3EE; padding: 3mm 0 0 0; font-size: 7.6pt; color: #5B6577; vertical-align: top; }
table.cop-piede-t td.dx { text-align: right; }

div.capo { margin-bottom: 5mm; }
div.capo-ico { float: left; width: 13mm; }
img.capo-img { width: 13mm; height: 13mm; margin-top: 1mm; }
div.capo-testo { margin-left: 17mm; }
p.kick { font-family: plexmono; font-size: 7pt; letter-spacing: 0.8pt; text-transform: uppercase; color: #5B6577; }
img.pillola { vertical-align: middle; }
h2 { font-family: jakartaxb; font-weight: normal; font-size: 21pt; line-height: 1.12; letter-spacing: -0.5pt; margin: 1.6mm 0 1.6mm 0; color: #111827; }
p.lead { font-size: 10.2pt; color: #374151; line-height: 1.5; }

div.caselle { margin-bottom: 1mm; }
div.tile { float: left; width: 75mm; height: 23mm; padding: 6.4mm 6mm 5mm 6mm; margin-bottom: 3mm; background-color: #F6F8FC; border-radius: 4.5mm; }
div.tile-dx { float: right; }
div.tile-verde { background-color: #E9F6EE; }
p.tl-n { font-family: jakartaxb; font-size: 22pt; letter-spacing: -0.6pt; line-height: 1.1; color: #1E3A8A; }
p.tl-verde { color: #166534; }
p.tl-n-lungo { font-size: 17pt; }
p.tl-t { font-family: jakarta; font-weight: bold; font-size: 9.2pt; margin-top: 1.4mm; color: #111827; }
p.tl-d { font-size: 8.4pt; color: #5B6577; margin-top: 0.8mm; line-height: 1.4; }

div.pannelli { margin-top: 2mm; }
div.pannello { float: left; width: 78.4mm; border: 0.3mm solid #E3E8F0; border-radius: 4mm; padding: 4mm 4.4mm 4mm 4.4mm; background-color: #ffffff; }
div.pannello-dx { float: right; }
div.pannello-piatto { background-color: #F6F8FC; border: 0.3mm solid #F6F8FC; }
p.kicker { font-family: plexmono; font-size: 6.8pt; letter-spacing: 0.7pt; text-transform: uppercase; color: #2563EB; margin-bottom: 1.6mm; }
p.kicker-navy { color: #1E3A8A; }
ul.fonti { margin: 0; padding-left: 4.4mm; font-size: 8.6pt; color: #374151; }
li.fonte { margin-bottom: 1.6mm; }
p.piccolo { font-size: 7.4pt; color: #5B6577; margin-top: 1.4mm; }
p.nota-gestione { font-size: 8pt; line-height: 1.4; color: #374151; margin: 3mm 0 0 0; padding: 2.2mm 3mm 2.2mm 3mm; border-left: 0.8mm solid #2563EB; background-color: #ffffff; }
p.nota-sola { margin-top: 0; }
table.indice { width: 100%; border-collapse: collapse; }
table.indice td { padding: 1.05mm 0 1.05mm 0; border-bottom: 0.3mm solid #EEF1F6; font-family: jakartasemi; font-size: 8.6pt; color: #111827; }
table.indice td.i-n { width: 9mm; font-family: plexmono; font-size: 8pt; color: #2563EB; }
table.indice tr.ultima td { border-bottom: 0; }

div.avv { background-color: #F6F8FC; border-radius: 4mm; padding: 4mm 4.6mm 3mm 4.6mm; margin-bottom: 4mm; }
ol.avv-ol { margin: 0; padding-left: 4.4mm; font-size: 7.6pt; line-height: 1.42; color: #374151; }
li.avv-li { margin-bottom: 1.2mm; }
div.diritti { background-color: #F6F8FC; border-radius: 4mm; padding: 3.6mm 4.4mm 2.4mm 4.4mm; margin-bottom: 3mm; font-size: 6.9pt; line-height: 1.45; color: #3B4556; }
p.diritti-p { margin-bottom: 1.4mm; }
div.chiusa { border-top: 0.3mm solid #E3E8F0; padding-top: 4mm; }
div.ch-logo { float: left; width: 40mm; }
img.ch-img { height: 12mm; }
div.ch-testo { margin-left: 44mm; }
p.ch-t { font-family: jakartaxb; font-size: 11pt; color: #111827; }
p.ch-p { font-size: 9.2pt; color: #374151; }
p.societa { font-size: 6.9pt; color: #5B6577; margin-top: 1.2mm; }
p.powered { font-size: 6.9pt; color: #5B6577; margin-top: 0.6mm; }

h3 { font-family: jakartaxb; font-weight: normal; font-size: 12pt; letter-spacing: -0.2pt; margin: 4mm 0 1.6mm 0; color: #111827; }
p.testo { font-size: 8.8pt; color: #374151; margin-bottom: 2.6mm; }
p.nota { font-size: 7.4pt; line-height: 1.45; color: #5B6577; margin: 1.2mm 0 2mm 0; }
div.riquadro { border: 0.3mm solid #E3E8F0; border-radius: 4mm; padding: 3mm 4mm 2.6mm 4mm; margin-bottom: 3mm; background-color: #ffffff; }
div.riquadro-piatto { background-color: #F6F8FC; border: 0.3mm solid #F6F8FC; }
div.riquadro-verde { background-color: #E9F6EE; border: 0.3mm solid #E9F6EE; }
div.colonna { float: left; width: 87.5mm; }
div.colonna-dx { float: right; width: 87.5mm; }
p.g-t { font-family: jakarta; font-weight: bold; font-size: 8.8pt; color: #111827; margin-bottom: 1.6mm; }
span.badge { font-family: jakartaxb; font-size: 7.4pt; color: #166534; background-color: #E3F4EA; }
p.grande { font-family: jakartaxb; font-size: 15pt; letter-spacing: -0.3pt; line-height: 1.2; color: #111827; }
p.grande-verde { color: #166534; }
div.vuoto { height: 1mm; font-size: 1pt; line-height: 1pt; }

table.mx { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
table.mx th { font-family: jakarta; font-weight: bold; font-size: 8pt; text-align: center; padding: 0 0.6mm 1.4mm 0.6mm; color: #111827; vertical-align: bottom; }
table.mx td { font-size: 8pt; text-align: center; padding: 1.2mm 0.6mm 1.2mm 0.6mm; border-bottom: 0.3mm solid #EEF1F6; color: #111827; }
table.mx td.mx-v, table.mx th.mx-v { width: 31mm; text-align: left; font-family: jakartasemi; color: #5B6577; }
table.mx th.mx-casa { color: #166534; }
table.mx td.mx-casa { width: 21mm; background-color: #E9F6EE; font-family: jakartaxb; color: #166534; }
span.fr-su { font-size: 6.4pt; color: #2563EB; }
span.fr-giu { font-size: 6.4pt; color: #B45309; }
span.fr-ug { font-size: 6.4pt; color: #A7B0C0; }
p.sez { font-family: jakartaxb; font-size: 10pt; color: #111827; margin: 1.6mm 0 1.4mm 0; }
img.sez-pallino { vertical-align: middle; }
p.leg-x { font-size: 7.2pt; color: #5B6577; }

div.scheda { border: 0.3mm solid #E3E8F0; border-radius: 4.5mm; padding: 3mm 3.4mm 2.6mm 3.4mm; margin-bottom: 3mm; }
table.sc-top { width: 100%; border-collapse: collapse; }
table.sc-top td { vertical-align: top; padding: 0; }
td.sc-foto { width: 63mm; }
img.sc-img { width: 60mm; height: 40mm; }
td.sc-map { width: 43.4mm; }
img.sc-map-img { width: 40mm; height: 40mm; }
p.sc-t { font-family: jakartaxb; font-size: 10pt; color: #111827; }
img.sc-num { vertical-align: middle; }
p.sc-q { font-size: 7.2pt; color: #5B6577; margin: 0.4mm 0 1.2mm 0; }
p.sc-p { font-family: jakartaxb; font-size: 13.5pt; letter-spacing: -0.3pt; color: #111827; }
p.cf-m { font-size: 7.8pt; color: #5B6577; margin-top: 0.3mm; }
span.cf-su { font-family: jakarta; font-weight: bold; color: #166534; }
span.cf-giu { font-family: jakarta; font-weight: bold; color: #1E3A8A; }
table.sc-f { margin-top: 1.2mm; border-collapse: collapse; }
table.sc-f th { font-family: jakartasemi; font-weight: normal; font-size: 7pt; color: #5B6577; text-align: left; padding: 0.3mm 1.4mm 0.3mm 0; }
table.sc-f td { font-family: jakarta; font-weight: bold; font-size: 7.2pt; color: #111827; text-align: left; padding: 0.3mm 3.4mm 0.3mm 0; }
p.sc-d { font-size: 7.6pt; line-height: 1.4; color: #374151; margin-top: 2mm; }
p.sc-r { font-size: 6.4pt; color: #A7B0C0; margin-top: 0.8mm; }

table.confronti { width: 100%; border-collapse: collapse; margin-top: 1mm; }
table.confronti td { border-bottom: 0.3mm solid #E3E8F0; padding: 2.4mm 0 2.4mm 0; vertical-align: middle; }
table.confronti tr.primo td { border-top: 0.3mm solid #E3E8F0; }
table.confronti td.cf-n { width: 10mm; }
table.confronti td.cf-p { width: 50mm; text-align: right; }
p.cf-a { font-family: jakartaxb; font-size: 9.6pt; color: #111827; }
span.cf-dove { font-family: jakartasemi; font-size: 7.8pt; color: #5B6577; }
p.cf-f { font-size: 7.8pt; color: #5B6577; margin-top: 0.5mm; }
p.cf-id { font-family: plexmono; font-size: 6.2pt; color: #A7B0C0; margin-top: 0.4mm; }
p.cf-e { font-family: jakartaxb; font-size: 12pt; letter-spacing: -0.3pt; color: #111827; }

table.leg-t { border-collapse: collapse; }
table.leg-t td { padding: 0.8mm 1.6mm 0.8mm 0; font-size: 8pt; color: #374151; vertical-align: middle; }
table.leg-t td.ev { font-family: jakartaxb; color: #166534; }
table.leg-t td.dx { text-align: right; }
table.waf { width: 100%; border-collapse: collapse; }
table.waf td { vertical-align: middle; padding: 0; }
td.waf-q { width: 37mm; }

table.sintesi { width: 100%; border-collapse: separate; border-spacing: 0; }
div.sn { float: left; width: 49.3mm; margin-right: 3mm; background-color: #F6F8FC; border-radius: 4mm; padding: 3.4mm 4mm 3.4mm 4mm; }
div.sn-ultimo { margin-right: 0; background-color: #E9F6EE; }
p.s-n { font-family: jakartaxb; font-size: 13.5pt; letter-spacing: -0.3pt; color: #111827; }
p.s-n-verde { color: #166534; }
p.s-t { font-size: 7.6pt; color: #5B6577; margin-top: 0.6mm; }

table.dati { width: 100%; border-collapse: collapse; }
table.dati th { width: 33mm; font-family: jakartasemi; font-weight: normal; font-size: 8pt; color: #5B6577; text-align: left; padding: 1mm 0 1mm 0; border-bottom: 0.3mm solid #EEF1F6; vertical-align: top; }
table.dati td { font-family: jakarta; font-weight: bold; font-size: 8pt; color: #111827; padding: 1mm 0 1mm 0; border-bottom: 0.3mm solid #EEF1F6; }
table.dati tr.ultima th, table.dati tr.ultima td { border-bottom: 0; }

div.mappa { margin-bottom: 1.6mm; }
p.leg-m { font-size: 7.4pt; color: #5B6577; margin: 0 0 2.4mm 0; }
img.leg-q { vertical-align: middle; }
img.leg-segno { vertical-align: middle; }
p.coord { font-family: plexmono; font-size: 7.2pt; color: #5B6577; margin: 0 0 1mm 0; }
div.terr { margin-top: 1mm; }
div.tc { float: left; width: 79mm; height: 25mm; padding: 3.2mm 4mm 2.4mm 4mm; margin-bottom: 3mm; background-color: #F6F8FC; border-radius: 4mm; }
div.tc-dx { float: right; }
div.tc-i { float: left; width: 11mm; }
img.tc-img { width: 9mm; height: 9mm; }
div.tc-t { margin-left: 12.5mm; }
p.tc-k { font-family: jakarta; font-weight: bold; font-size: 7.8pt; color: #5B6577; }
p.tc-n { font-family: jakartaxb; font-size: 12.5pt; letter-spacing: -0.3pt; line-height: 1.15; color: #111827; margin-top: 0.4mm; }
p.tc-d { font-size: 7.4pt; line-height: 1.38; color: #5B6577; margin-top: 0.6mm; }
p.tc-p { margin-top: 1.4mm; }
table.fonti-t { width: 100%; border-collapse: collapse; margin-top: 1mm; }
table.fonti-t td { font-size: 6.8pt; line-height: 1.4; color: #5B6577; vertical-align: top; padding: 0; }
td.fonti-logo { width: 32mm; text-align: right; }
img.agcom { width: 28mm; }
div.cop-mappa { position: absolute; left: 0; top: 0; width: 210mm; height: 118mm; }
div.cop-logo-carta { position: absolute; left: 12mm; top: 10mm; width: 34mm; padding: 2.6mm 3.6mm 2.6mm 3.6mm; background-color: #ffffff; border-radius: 4mm; }
img.cop-logo-p { height: 11mm; }
div.cop-spazio { height: 104mm; }

div.lb { margin-bottom: 3.4mm; }
p.lb-t { font-family: jakartaxb; font-size: 10pt; color: #111827; margin-bottom: 0.8mm; }
p.lb-p { font-size: 8.8pt; color: #374151; }
CSS;

    /**
     * La testata e il piè delle pagine interne (in mPDF si definiscono una
     * volta e la pagina «corpo» li ripete).
     */
    public static function testataEPiede(Contesto $c): string
    {
        $logo = Grafici::file((string) config('valutazione.logo'));
        $testata = $c->testo('testata') ?? '';
        $casa = $c->testo('piede_casa') ?? '';
        $copyright = $c->libero($c->impostazione('valutazione_piede_copyright')) ?? '';

        return '<htmlpageheader name="testa"><table class="testa"><tr><td><img class="t-logo" src="'.$logo.'" alt="Cerca.Re"></td><td class="t-dx">'.$testata.'</td></tr></table></htmlpageheader>'
            .'<htmlpagefooter name="piede"><table class="piede"><tr><td>'.$casa.'</td><td class="p-dx">'.$copyright.'&nbsp;&nbsp;&nbsp;&nbsp;<span class="pn">{PAGENO} / {nbpg}</span></td></tr></table></htmlpagefooter>';
    }

    /**
     * Le pagine accese, in HTML, un foglio per voce: chiave => [nome, HTML]
     * (il secondo foglio delle schede è «schede_2»). La guardia le controlla
     * una per una, poi Report le passa a mPDF una per una (pezzi).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function pagine(Contesto $c): array
    {
        $fuori = [];
        foreach ($c->fogli as $chiave => $quanti) {
            $classe = Pagine::ELENCO[$chiave]['classe'];
            $nome = Pagine::ELENCO[$chiave]['nome'];
            $fuori[$chiave] = [$nome, $classe::html($c)];
            for ($foglio = 2; $foglio <= $quanti; $foglio++) {
                $fuori[$chiave.'_'.$foglio] = [$nome.' ('.$foglio.')', $classe::html($c, $foglio)];
            }
        }

        return $fuori;
    }

    /**
     * Il corpo per mPDF in pezzi, uno per pagina, da dargli uno alla volta:
     * il primo con testata e piè, gli altri col salto di pagina verso la
     * pagina «corpo» davanti.
     *
     * @param  array<string, array{0: string, 1: string}>  $pagine
     * @return list<string>
     */
    public static function pezzi(Contesto $c, array $pagine): array
    {
        $pezzi = [];
        foreach ($pagine as [, $pagina]) {
            $pezzi[] = ($pezzi === [] ? self::testataEPiede($c) : '<pagebreak page-selector="corpo" />').$pagina;
        }

        return $pezzi === [] ? [self::testataEPiede($c)] : $pezzi;
    }
}