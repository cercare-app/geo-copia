<?php

namespace App\Support\Valutazione\Report;

use App\Support\Valutazione\Formato;
use App\Support\Valutazione\Grafici;
use App\Support\Valutazione\Impostazioni;
use App\Support\Valutazione\Segnaposti;

/**
 * PAGINA 1 · LA COPERTINA (D-VAL-40): l'indirizzo, le caselle della casa,
 * la carta del valore con la forchetta disegnata e il prezzo al metro
 * quadro, le tre caselle a destra (affitto, ristrutturata, da
 * ristrutturare), in fondo la data, il codice, i contatti e «© … · Powered
 * by». Dal lotto 4 la mappa in cima (riquadro «cop_mappa», acceso di
 * fabbrica); spenta o senza dati, la pagina si impagina senza, col logo.
 *
 * Scritta per mPDF: blocchi galleggianti e niente tabelle con stile dentro
 * (mPDF dentro una cella ignora sfondi, bordi e angoli dei blocchi).
 */
final class Copertina
{
    /** Larghezza utile della pagina (210 − 2 × 16 mm). */
    public const LARGHEZZA = 178;

    public static function html(Contesto $c): string
    {
        $d = $c->dati;
        $cifre = $c->cifre;
        $logo = Grafici::file((string) config('valutazione.logo'));
        $indirizzo = Segnaposti::sicuro(trim($d['input']['street'].' '.($d['input']['house_number'] ?? '')));

        // con la mappa (lotto 4): la mappa in cima, a filo del foglio, che sfuma nel bianco; il logo
        // in una carta bianca sopra; il testo comincia sotto la mappa (in mPDF i blocchi a posizione
        // assoluta si disegnano DOPO il resto: se il testo stesse sotto la mappa, la mappa lo coprirebbe)
        $mappa = $c->riquadro('cop_mappa') ? $c->mappa('copertina', 0.0) : null;
        if ($mappa !== null) {
            $h = '<div class="cop-mappa"><img src="'.Grafici::comeImmagine(self::velo($mappa)).'" style="width: 210mm; height: 118mm" alt=""></div>'
                .'<div class="cop-logo-carta"><img class="cop-logo-p" src="'.$logo.'" alt="Cerca.Re"></div>'
                .'<div class="cop-spazio"></div>';
        } else {
            $h = '<div class="cop-testa"><img class="cop-logo" src="'.$logo.'" alt="Cerca.Re"></div>';
        }
        $occhiello = $c->testo('cop_occhiello');
        if ($occhiello !== null && $occhiello !== '') {
            $h .= '<p class="cop-k"'.($mappa !== null ? ' style="margin-top: 0"' : '').'><img class="cop-tratto" src="'.Grafici::comeImmagine(Grafici::trattino()).'" alt=""> '.$occhiello.'</p>';
        }
        $h .= '<h1>'.$indirizzo.'</h1>';
        $h .= '<p class="cop-citta">'.Segnaposti::sicuro($c->citta()).'</p>';

        if ($c->riquadro('cop_caselle')) {
            $h .= self::caselle($c);
        }

        // la carta del valore e le tre caselle a destra
        $lato = [];
        if ($c->riquadro('cop_affitto') && $cifre->affitto() !== null) {
            $lato[] = [$c->testo('cop_affitto'), Formato::euro($cifre->affitto()), $c->testo('cop_al_mese')];
        }
        if ($c->riquadro('cop_ristrutturata') && $cifre->ristrutturata() !== null) {
            $lato[] = [$c->testo('cop_ristrutturata'), Formato::euro($cifre->ristrutturata()), null];
        }
        if ($c->riquadro('cop_da_ristrutturare') && $cifre->daRistrutturare() !== null) {
            $lato[] = [$c->testo('cop_da_ristrutturare'), Formato::euro($cifre->daRistrutturare()), null];
        }
        $larghezzaCarta = $lato === [] ? self::LARGHEZZA - 18.6 : 100.0;

        $carta = '<img class="cc-riga" src="'.Grafici::comeImmagine(Grafici::riga($larghezzaCarta)).'" alt="">'
            .'<p class="cc-k">'.($c->testo('cop_valore') ?? '').'</p>'
            .'<p class="cc-n">'.Segnaposti::sicuro(Formato::numero($cifre->valore())).'<span class="cc-eu"> €</span></p>';
        $mq = $c->testo('cop_valore_mq', [
            'valore_mq' => Formato::euroMq($cifre->valoreMq()),
            'minimo_mq' => Formato::euroMq($cifre->minimoMq()),
            'massimo_mq' => Formato::euroMq($cifre->massimoMq()),
        ]);
        if ($mq !== null && $mq !== '') {
            $carta .= '<p class="cc-mq">'.$mq.'</p>';
        }
        if ($c->riquadro('cop_forchetta')) {
            $svg = Grafici::forchetta(
                $cifre->posizione(),
                Formato::euro($cifre->minimo()),
                Formato::euro($cifre->massimo()),
                html_entity_decode(strip_tags((string) $c->testo('cop_minimo')), ENT_QUOTES, 'UTF-8'),
                html_entity_decode(strip_tags((string) $c->testo('cop_massimo')), ENT_QUOTES, 'UTF-8'),
                $larghezzaCarta,
            );
            $carta .= '<div class="cc-barra"><img src="'.Grafici::comeImmagine($svg).'" alt=""></div>';
        }

        $h .= '<div class="cop-riga">';
        $h .= '<div class="cop-carta" style="width: '.self::mm($larghezzaCarta).'">'.$carta.'</div>';
        if ($lato !== []) {
            $h .= '<div class="cop-lato">';
            foreach ($lato as [$titolo, $numero, $unita]) {
                $h .= '<div class="cl"><p class="ct-k">'.($titolo ?? '').'</p><p class="ct-n">'.Segnaposti::sicuro($numero)
                    .($unita !== null && $unita !== '' ? '<span class="ct-u"> '.$unita.'</span>' : '').'</p></div>';
            }
            $h .= '</div>';
        }
        $h .= '<div class="pulisci"></div></div>';

        return $h.self::piede($c);
    }

    /**
     * Il piede della copertina: posizionato in fondo alla pagina (in mPDF
     * un blocco a posizione assoluta deve stare al primo livello).
     */
    private static function piede(Contesto $c): string
    {
        $sx = array_filter([$c->testo('cop_creato'), $c->testo('cop_codice')], fn (?string $t): bool => $t !== null && $t !== '');
        $copyright = '© '.Segnaposti::sicuro($c->comuni()['anno'].' '.$c->impostazione('valutazione_titolare_nome'));
        $powered = $c->impostazione(Impostazioni::POWERED_ACCESO) === '0' ? '' : trim($c->impostazione('valutazione_powered_testo'));
        if ($powered !== '') {
            $link = trim($c->impostazione('valutazione_powered_link'));
            $copyright .= ' · Powered by '.($link !== '' ? '<a href="'.Segnaposti::sicuro($link).'">'.Segnaposti::sicuro($powered).'</a>' : '<strong>'.Segnaposti::sicuro($powered).'</strong>');
        }
        $dx = array_filter([$c->testo('cop_contatti'), $copyright], fn (?string $t): bool => $t !== null && $t !== '');

        return '<div class="cop-piede"><table class="cop-piede-t"><tr><td>'.implode('<br>', $sx).'</td><td class="dx">'.implode('<br>', $dx).'</td></tr></table></div>';
    }

    /** Le caselle «a pillola» della casa, larghe quanto il loro testo. */
    private static function caselle(Contesto $c): string
    {
        $i = $c->dati['input'];
        $omi = $c->dati['omi'];
        $testi = [];
        $testi[] = match ($i['category'] ?? null) {
            'CONDOMINIUM' => $c->testo('chip_appartamento'),
            'SINGLE_FAMILY_HOUSE' => $c->testo('chip_casa'),
            default => null,
        };
        $testi[] = $c->testo('chip_superficie', ['superficie' => Formato::numero((float) $i['living_area']).' m²']);
        if (($i['floor'] ?? null) === 0) {
            $testi[] = $c->testo('chip_piano_terra');
        } elseif (($i['floor'] ?? null) !== null && $i['floor'] > 0) {
            $testi[] = $c->testo('chip_piano', ['piano' => (string) $i['floor']]);
        }
        if (($i['rooms'] ?? null) !== null && $i['rooms'] > 0) {
            $testi[] = $c->testo('chip_locali', ['locali' => (string) $i['rooms']]);
        }
        $testi[] = $c->testo('chip_zona', [
            'zona_codice' => $omi['codzona'] ?? null,
            'zona_nome' => ($omi['zona_descr'] ?? null) !== null ? Formato::titolo((string) $omi['zona_descr']) : null,
        ]);

        $h = '';
        foreach ($testi as $html) {
            if ($html === null || $html === '') {
                continue;
            }
            $semplice = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
            $larghezza = Grafici::larghezza($semplice, 8.4) + 0.8;
            $h .= '<div class="chip" style="width: '.self::mm($larghezza).'">'.$html.'</div>';
        }

        return $h === '' ? '' : '<div class="chips">'.$h.'<div class="pulisci"></div></div>';
    }

    /**
     * La mappa della copertina con il velo bianco in fondo (come il
     * fac-simile): un gradiente dal trasparente al bianco sugli ultimi 34 mm.
     */
    public static function velo(string $svg): string
    {
        $velo = '<defs><linearGradient id="velo" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff" stop-opacity="0"/>'
            .'<stop offset="0.7" stop-color="#ffffff" stop-opacity="0.85"/><stop offset="1" stop-color="#ffffff" stop-opacity="1"/></linearGradient></defs>'
            .'<rect x="0" y="84" width="210" height="34" fill="url(#velo)"/>';
        $fine = strrpos($svg, '</svg>');

        return $fine === false ? $svg : substr($svg, 0, $fine).$velo.'</svg>';
    }

    private static function mm(float $x): string
    {
        return number_format($x, 1, '.', '').'mm';
    }
}