<?php

namespace App\Support\Valutazione\Report;

use App\Support\Valutazione\Formato;
use App\Support\Valutazione\Grafici;

/**
 * PAGINA «LE CASE VENDUTE, UNA PER UNA» (lotto 2, D-VAL-57): una riga per
 * compravendita, col numero verde, la via, la distanza, il comune, la
 * superficie catastale (e stimata), il piano, la categoria, il mese della
 * vendita, il riferimento, il prezzo pagato e il prezzo al m² rispetto
 * alla stima. Dal lotto 4, sopra l'elenco, la mappa delle vendite
 * (riquadro «vendute_mappa») e chi resta fuori perché troppo lontano.
 */
final class Vendute
{
    public static function fogli(Contesto $c): int
    {
        return Confronti::vendute($c) !== [] ? 1 : 0;
    }

    public static function html(Contesto $c): string
    {
        $vendute = Confronti::vendute($c);
        $n = count($vendute);
        $h = Capo::html($c, 'vendute', 'ico-costi.png', 'vendute_occhiello', 'vendute_titolo', $n === 1 ? 'vendute_frase_una' : 'vendute_frase', [
            'vendite' => Formato::maiuscola(Formato::inParole($n, true)).' compravendite',
        ]);
        // la mappa delle vendite e chi resta fuori (lotto 4)
        $mappa = Cartine::blocco($c, 'vendute', 'vendute_mappa');
        if ($mappa !== '') {
            $h .= $mappa.Cartine::fuori($c, 'vendute');
        }
        $righe = '';
        foreach ($vendute as $k => $v) {
            $righe .= self::riga($c, $v, $k === 0);
        }
        $h .= '<table class="confronti">'.$righe.'</table>';
        if ($c->riquadro('vendute_nota')) {
            $h .= Parti::p($c->testo('vendute_nota'), 'nota');
        }

        return $h;
    }

    private static function riga(Contesto $c, array $v, bool $primo): string
    {
        $dove = array_filter([
            $v['distanza'] !== null ? 'a '.Formato::distanza($v['distanza']) : null,
            $v['comune'] !== null ? Parti::t($v['comune']) : null,
        ]);
        $titolo = '<p class="cf-a">'.Parti::t($v['via'] ?? '—').($dove !== [] ? ' <span class="cf-dove">'.implode(' · ', $dove).'</span>' : '').'</p>';

        $superficie = null;
        if ($v['mq'] !== null) {
            $superficie = $c->testo('vendute_catastali', ['superficie' => Formato::numero($v['mq']).' m²']);
            if ($superficie !== null && $v['mq_stimati'] !== null && round($v['mq_stimati']) != round($v['mq'])) {
                $stimati = $c->testo('vendute_stimati', ['superficie' => Formato::numero($v['mq_stimati'])]);
                $superficie .= $stimati !== null && $stimati !== '' ? ' '.$stimati : '';
            }
        }
        $piano = match (true) {
            $v['piano'] === null => null,
            $v['piano_testo'] === 'rialzato' => $c->testo('vendute_rialzato'),
            $v['piano'] === 0 => $c->testo('vendute_terra'),
            default => $c->testo('vendute_piano', ['piano' => (string) $v['piano']]),
        };
        $categoria = $v['categoria'] !== null ? $v['categoria'].($v['categoria_parole'] !== null ? ' · '.$v['categoria_parole'] : '') : null;
        $mese = Formato::meseAnno($v['quando']);
        $fatti = array_filter([
            $superficie,
            $piano,
            $categoria !== null ? Parti::t($categoria) : null,
            $mese !== null ? $c->testo('vendute_quando', ['mese' => $mese]) : null,
        ], fn (?string $x): bool => $x !== null && $x !== '');

        $centro = $titolo.Parti::p(implode(' · ', $fatti), 'cf-f');
        if ($c->riquadro('vendute_rif') && $v['codice'] !== null) {
            $centro .= Parti::p($c->testo('vendute_rif', ['numero' => $v['codice']]), 'cf-id');
        }
        $prezzo = $v['prezzo'] !== null ? '<p class="cf-e">'.Formato::euro($v['prezzo']).'</p>' : '';
        if ($v['al_mq'] !== null) {
            $prezzo .= '<p class="cf-m">'.Formato::euroMq($v['al_mq']).Parti::sullaStima($c, 'vendute_sulla_stima', $v['differenza']).'</p>';
        }

        return '<tr'.($primo ? ' class="primo"' : '').'><td class="cf-n">'.Parti::pallino($v['numero'], Grafici::VERDE, 7).'</td><td>'.$centro.'</td><td class="cf-p">'.$prezzo.'</td></tr>';
    }
}