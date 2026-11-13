<?php

namespace App\Support\Valutazione\Report;

use App\Support\DatiNascosti;
use App\Support\Geo\LettoreFibra;
use App\Support\Valutazione\Formato;
use App\Support\Valutazione\Grafici;
use App\Support\Valutazione\Segnaposti;

/**
 * «IL POSTO» (lotto 4, D-VAL-93, D-VAL-98, D-VAL-100): la vista dall'alto
 * con la sua didascalia, le coordinate della casa, il territorio in quattro
 * caselle (zona sismica, alluvioni, frane, fibra nel punto) e le fonti,
 * come la pagina 3 del fac-simile REV0.
 *
 *  - I conti del territorio sono quelli della scheda degli annunci
 *    (DatiNascosti): le stesse medie italiane, la stessa regola dei ±20%,
 *    le stesse parole della fibra. Scheda e report non si contraddicono.
 *  - Il comune è quello trovato da Mappe\Raccolta (dal confine che
 *    contiene la casa, di riserva dal nome); se non si trova, le tre
 *    caselle del comune dicono «n.d.» con la frase onesta.
 *  - La fibra segue l'interruttore «Fibra nel report» del magazzino: spenta,
 *    la sua casella non c'è.
 *  - Senza vista (spenta, o niente edifici intorno) la pagina resta col
 *    territorio; senza niente da mostrare non esce.
 *
 * Scritta per mPDF come le altre: blocchi galleggianti con la loro
 * larghezza e altezza, mai blocchi con sfondo dentro una cella; le pillole
 * sono piccoli SVG (mPDF non arrotonda i pezzi di testo).
 */
final class Posto
{
    /** I colori delle pillole (fac-simile, stile.css): sfondo, testo, bordo. */
    public const PILLOLE = [
        'ok' => ['#E3F4EA', '#166534', null],
        'media' => ['#FDF0D2', '#8A4B0B', null],
        'alta' => ['#FBE3E4', '#9F1D26', null],
        'neutra' => ['#FFFFFF', '#5B6577', '#E3E8F0'],
    ];

    public static function fogli(Contesto $c): int
    {
        return self::corpo($c) !== '' ? 1 : 0;
    }

    public static function html(Contesto $c): string
    {
        $frase = $c->vista() !== null ? 'posto_frase' : 'posto_frase_senza_vista';

        return Capo::html($c, 'posto', 'ico-h-italia.png', 'posto_occhiello', 'posto_titolo', $frase).self::corpo($c);
    }

    private static function corpo(Contesto $c): string
    {
        $h = '';
        $vista = $c->vista();
        if ($vista !== null) {
            $h .= '<div class="mappa">'.Parti::img($vista['svg']).'</div>';
            $colorato = match ($vista['casa']) {
                'edificio' => $c->testo('posto_vista_edificio'),
                'isolato' => $c->testo('posto_vista_isolato'),
                default => null,
            };
            $pezzi = array_filter([$colorato, $c->testo('posto_vista_nota')], fn (?string $x): bool => $x !== null && $x !== '');
            if ($pezzi !== []) {
                $colori = $c->impostazioniMappe()['colori'];
                $quadretto = $colorato !== null && $colorato !== ''
                    ? Parti::img(Grafici::svg(3, 3, '<rect x="0.15" y="0.15" width="2.7" height="2.7" rx="0.6" ry="0.6" fill="'.$colori['casa_tetto'].'" stroke="'.$colori['casa_muri_scuri'].'" stroke-width="0.3"/>', 3), 'leg-q').' '
                    : '';
                $h .= '<p class="leg-m">'.$quadretto.implode(' · ', $pezzi).'</p>';
            }
        }
        if ($c->riquadro('posto_coordinate')) {
            $i = $c->dati['input'];
            if (is_numeric($i['latitude'] ?? null) && is_numeric($i['longitude'] ?? null)) {
                $h .= Parti::p($c->testo('posto_coordinate', [
                    'latitudine' => number_format((float) $i['latitude'], 5, ',', ''),
                    'longitudine' => number_format((float) $i['longitude'], 5, ',', ''),
                ]), 'coord');
            }
        }
        if ($c->riquadro('posto_territorio')) {
            $caselle = self::caselle($c);
            if ($caselle !== []) {
                $comune = $c->mappe?->comune['nome'] ?? null;
                $titolo = $c->testo('posto_territorio_t');
                $h .= $titolo !== null && $titolo !== '' ? '<h3>'.$titolo.'</h3>' : '';
                $h .= Parti::p($c->testo('posto_territorio_frase', ['comune_territorio' => $comune ?? (string) ($c->dati['input']['town'] ?? '')]), 'testo');
                $h .= '<div class="terr">';
                foreach ($caselle as $k => $casella) {
                    $h .= self::casella($casella, $k % 2 === 1);
                    if ($k % 2 === 1) {
                        $h .= '<div class="pulisci"></div>';
                    }
                }
                $h .= '<div class="pulisci"></div></div>';
            }
        }
        if ($h !== '' && $c->riquadro('posto_fonti')) {
            $fonti = $c->testo('posto_fonti', ['edizione_fibra' => $c->mappe?->fibraEdizione]);
            if ($fonti !== null && $fonti !== '') {
                $logo = rtrim((string) config('valutazione.icone'), '/').'/agcom-broadbandmap.png';
                $h .= '<table class="fonti-t"><tr><td>'.$fonti.'</td>'
                    .(is_file($logo) ? '<td class="fonti-logo"><img class="agcom" src="'.Grafici::file($logo).'" alt="BroadbandMap di AGCOM, CC BY 4.0"></td>' : '')
                    .'</tr></table>';
            }
        }

        return $h;
    }

    /**
     * Le caselle del territorio accese: [icona, titolo, numero, nota, pillola, livello].
     *
     * @return list<array{0: string, 1: string, 2: string, 3: ?string, 4: ?string, 5: string}>
     */
    private static function caselle(Contesto $c): array
    {
        $accese = $c->impostazioniMappe()['territorio'];
        $m = $c->mappe;
        $comune = $m?->comune;
        $nd = $c->testo('posto_nd') ?? 'n.d.';
        $ndComune = $comune === null ? $c->testo('posto_nd_comune') : $c->testo('posto_nd_dato');
        $caselle = [];

        if ($accese['sismica']) {
            $zona = trim((string) ($comune['zona_sismica'] ?? ''));
            if ($zona !== '') {
                $cifra = substr($zona, 0, 1);
                [$titolo, $parole] = DatiNascosti::SISMICA[$cifra] ?? ['Zona '.$zona, 'classificazione regionale'];
                $caselle[] = ['ico-e-sismico.png', (string) $c->testo('posto_sismica_t'), Segnaposti::sicuro($zona === $cifra ? $titolo : $titolo.' ('.$zona.')'), Segnaposti::sicuro($parole), $c->etichetta('posto_sismica_pillola'), 'neutra'];
            } else {
                $caselle[] = ['ico-e-sismico.png', (string) $c->testo('posto_sismica_t'), $nd, $ndComune, null, 'neutra'];
            }
        }
        foreach ([['alluvioni', 'idr_pop_p2', 'nascosti.medie_italia.idr_pop_p2', 11.5], ['frane', 'frane_pop_p3p4', 'nascosti.medie_italia.frane_pop_p3p4', 2.2]] as [$nome, $campo, $chiaveMedia, $mediaFabbrica]) {
            if (! $accese[$nome]) {
                continue;
            }
            $titolo = (string) $c->testo('posto_'.$nome.'_t');
            $valore = $comune[$campo] ?? null;
            if (! is_numeric($valore)) {
                $caselle[] = ['ico-e-altimetrica.png', $titolo, $nd, $ndComune, null, 'neutra'];

                continue;
            }
            $media = (float) config($chiaveMedia, $mediaFabbrica);
            $valore = (float) $valore;
            $livello = self::livello($valore, $media);
            $numero = $valore <= 0.0
                ? (string) $c->testo('posto_nessuna_area')
                : (string) $c->testo('posto_'.$nome.'_n', ['percentuale' => self::percento($valore)]);
            $caselle[] = ['ico-e-altimetrica.png', $titolo, $numero, $c->testo('posto_'.$nome.'_d', ['media' => self::percento($media)]),
                $c->etichetta(match ($livello) { 'ok' => 'posto_sotto', 'alta' => 'posto_sopra', default => 'posto_vicino' }), $livello];
        }
        if ($accese['fibra'] && ($m === null || $m->fibraMotivo !== 'spenta')) {
            $titolo = (string) $c->testo('posto_fibra_t');
            $esito = $m?->fibra;
            if ($esito === null) {
                $caselle[] = ['ico-e-connettivita.png', $titolo, $nd, $c->testo('posto_nd_fibra'), null, 'neutra'];
            } else {
                $cella = $esito['cella'] ?? null;
                $vicino = null;
                if ($cella === null && ! empty($esito['vicine'])) {
                    $cella = $esito['vicine'][0];
                    $vicino = (int) round((float) ($cella['metri'] ?? 0));
                }
                if ($cella === null) {
                    $caselle[] = ['ico-e-connettivita.png', $titolo, (string) $c->testo('posto_fibra_nessuna'), $c->testo('posto_fibra_nessuna_d', ['metri' => LettoreFibra::RAGGIO.' m']), $c->etichetta('posto_fibra_pillola'), 'neutra'];
                } else {
                    [$valore] = DatiNascosti::tecnologiaFibra((string) ($cella['speed_down_exp_tech_text'] ?? ''), $cella['speed_down_exp'] ?? null);
                    $nota = $vicino === null ? $c->testo('posto_fibra_punto') : $c->testo('posto_fibra_vicino', ['metri' => $vicino.' m']);
                    $reti = DatiNascosti::reti((string) ($cella['infrastruttura_ftthb_all'] ?? ''));
                    $testoReti = $reti !== '' ? $c->testo('posto_fibra_reti', ['reti' => $reti]) : null;
                    $nota = implode(' · ', array_filter([$nota, $testoReti], fn (?string $x): bool => $x !== null && $x !== ''));
                    $caselle[] = ['ico-e-connettivita.png', $titolo, Segnaposti::sicuro($valore), $nota !== '' ? $nota : null, $c->etichetta('posto_fibra_pillola'), 'neutra'];
                }
            }
        }

        return $caselle;
    }

    /** Una casella: l'icona a sinistra, titolo, numero, nota e pillola. */
    private static function casella(array $casella, bool $aDestra): string
    {
        [$icona, $titolo, $numero, $nota, $pillola, $livello] = $casella;
        $percorso = rtrim((string) config('valutazione.icone'), '/').'/'.$icona;
        $ico = is_file($percorso) ? '<img class="tc-img" src="'.Grafici::file($percorso).'" alt="">' : '';
        $pill = $pillola !== null && $pillola !== '' ? '<p class="tc-p">'.Parti::img(self::pillola($pillola, $livello)).'</p>' : '';

        return '<div class="tc'.($aDestra ? ' tc-dx' : '').'"><div class="tc-i">'.$ico.'</div><div class="tc-t">'
            .'<p class="tc-k">'.$titolo.'</p><p class="tc-n">'.$numero.'</p>'.Parti::p($nota, 'tc-d').$pill.'</div></div>';
    }

    /** La pillola: testo in Plus Jakarta Sans SemiBold, larga quanto il testo. */
    public static function pillola(string $testo, string $livello): string
    {
        [$sfondo, $inchiostro, $bordo] = self::PILLOLE[$livello] ?? self::PILLOLE['neutra'];
        $corpo = 7.0 * Grafici::PT_MM;
        $alto = round($corpo * 1.75, 2);
        $largo = round(Grafici::larghezzaNelDisegno($testo, $corpo, Grafici::SANS_SEMI) + 2 * 2.4, 2);
        $c = '<rect x="0.15" y="0.15" width="'.Grafici::n($largo - 0.3).'" height="'.Grafici::n($alto - 0.3).'" rx="'.Grafici::n($alto / 2 - 0.15).'" ry="'.Grafici::n($alto / 2 - 0.15).'" fill="'.$sfondo.'"'
            .($bordo !== null ? ' stroke="'.$bordo.'" stroke-width="0.3"' : '').'/>'
            .Grafici::testo($largo / 2, $alto / 2 + $corpo * 0.36, $testo, $corpo, $inchiostro, Grafici::SANS_SEMI, 'middle');

        return Grafici::svg($largo, $alto, $c, $largo);
    }

    /** La regola della scheda (DatiNascosti::livello): ok sotto, media vicino (±20%), alta sopra. */
    public static function livello(float $valore, float $media): string
    {
        if ($valore <= 0.0) {
            return 'ok';
        }
        $scarto = $media > 0 ? ($valore - $media) / $media : 0.0;

        return $scarto > DatiNascosti::TOLLERANZA_MEDIA ? 'alta' : ($scarto < -DatiNascosti::TOLLERANZA_MEDIA ? 'ok' : 'media');
    }

    /** «4,3%». */
    private static function percento(float $x): string
    {
        return Formato::numero(round($x, 1), 1).'%';
    }
}