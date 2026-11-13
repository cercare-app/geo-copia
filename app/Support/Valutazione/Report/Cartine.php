<?php

namespace App\Support\Valutazione\Report;

use App\Support\Valutazione\Formato;
use App\Support\Valutazione\Grafici;

/**
 * I PEZZI DELLE MAPPE NELLE PAGINE (lotto 4, D-VAL-97): la mappa con la sua
 * cornice, la legenda delle case vicine e la riga di chi resta fuori dalla
 * mappa perché troppo lontano (con la distanza vera, non il raggio).
 */
final class Cartine
{
    /** La mappa di una cornice come blocco della pagina; vuoto se non c'è. */
    public static function blocco(Contesto $c, string $chiave, string $riquadro): string
    {
        if (! $c->riquadro($riquadro)) {
            return '';
        }
        $svg = $c->mappa($chiave);

        return $svg === null ? '' : '<div class="mappa">'.Parti::img($svg).'</div>';
    }

    /** La legenda delle case vicine: annunci, vendite, la casa. */
    public static function legenda(Contesto $c): string
    {
        $colori = $c->impostazioniMappe()['colori'];
        $voci = [
            [Grafici::svg(3, 3, '<circle cx="1.5" cy="1.5" r="1.5" fill="'.$colori['annunci'].'"/>', 3), $c->testo('vicine_leg_annunci')],
            [Grafici::svg(3, 3, '<rect x="0" y="0" width="3" height="3" rx="0.7" ry="0.7" fill="'.$colori['vendite'].'"/>', 3), $c->testo('vicine_leg_vendite')],
            [Grafici::svg(3, 3, '<rect x="0" y="0" width="3" height="3" rx="0.7" ry="0.7" fill="'.$colori['casa'].'"/>', 3), $c->testo('vicine_leg_casa')],
        ];
        $pezzi = [];
        foreach ($voci as [$svg, $testo]) {
            if ($testo !== null && $testo !== '') {
                $pezzi[] = Parti::img($svg, 'leg-segno').' '.$testo;
            }
        }

        return $pezzi === [] ? '' : '<p class="leg-m">'.implode('&nbsp;&nbsp;&nbsp;&nbsp;', $pezzi).'</p>';
    }

    /**
     * Chi resta fuori dalla mappa, con la distanza: «L'annuncio 4 (a 1,8 km)…».
     * Vuoto se ci stanno tutti.
     */
    public static function fuori(Contesto $c, string $chiave): string
    {
        $cornice = $c->cornici()[$chiave] ?? null;
        if ($cornice === null || $c->mappa($chiave) === null) {
            return '';
        }
        $distanze = [
            'annuncio' => array_column(Confronti::annunci($c), 'distanza', 'numero'),
            'vendita' => array_column(Confronti::vendute($c), 'distanza', 'numero'),
        ];
        $righe = [];
        foreach (['annuncio' => ['mappa_fuori_annuncio', 'mappa_fuori_annunci'], 'vendita' => ['mappa_fuori_vendita', 'mappa_fuori_vendite']] as $tipo => [$uno, $piu]) {
            $numeri = $cornice['fuori'][$tipo] ?? [];
            if ($numeri === []) {
                continue;
            }
            $voci = array_map(fn (int $n): string => $n.(isset($distanze[$tipo][$n]) && $distanze[$tipo][$n] !== null ? ' (a '.Formato::distanza($distanze[$tipo][$n]).')' : ''), $numeri);
            $elenco = count($voci) === 1 ? $voci[0] : implode(', ', array_slice($voci, 0, -1)).' e '.end($voci);
            $testo = $c->testo(count($voci) === 1 ? $uno : $piu, ['elenco' => $elenco]);
            if ($testo !== null && $testo !== '') {
                $righe[] = $testo;
            }
        }

        return $righe === [] ? '' : '<p class="leg-m">'.implode(' ', $righe).'</p>';
    }
}