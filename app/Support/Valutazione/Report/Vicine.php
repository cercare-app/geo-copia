<?php

namespace App\Support\Valutazione\Report;

use App\Support\Valutazione\Disegni;
use App\Support\Valutazione\Formato;

/**
 * PAGINA «LE CASE VICINE» (lotto 2, D-VAL-53): il grafico del prezzo al m²
 * chiesto (annunci, blu), pagato (vendite, verde) e la stima, e sotto i
 * tre numeri. Dal lotto 4 la mappa con le case numerate, la legenda e
 * chi resta fuori (riquadri «vicine_mappa» e «vicine_legenda»).
 */
final class Vicine
{
    public static function fogli(Contesto $c): int
    {
        return self::mappa($c) !== '' || self::corpo($c) !== '' ? 1 : 0;
    }

    /** La mappa con le case numerate, la legenda e chi resta fuori (lotto 4). */
    private static function mappa(Contesto $c): string
    {
        $blocco = Cartine::blocco($c, 'vicine', 'vicine_mappa');
        if ($blocco === '') {
            return '';
        }

        return $blocco.($c->riquadro('vicine_legenda') ? Cartine::legenda($c) : '').Cartine::fuori($c, 'vicine');
    }

    public static function html(Contesto $c): string
    {
        // la frase conta le case mostrate nelle pagine (anche quelle senza prezzo al m²)
        $annunci = count(Confronti::annunci($c));
        $vendite = count(Confronti::vendute($c));
        $numero = fn (int $n, bool $femminile): string => Formato::inParole($n, $femminile);
        // al singolare la frase è un testo suo: le parole che si accordano restano nell'Admin
        $frase = match (true) {
            $annunci > 0 && $vendite > 1 => 'vicine_frase',
            $annunci > 0 && $vendite === 1 => 'vicine_frase_una',
            $annunci > 1 => 'vicine_frase_annunci',
            $annunci === 1 => 'vicine_frase_un_annuncio',
            $vendite > 1 => 'vicine_frase_vendite',
            default => 'vicine_frase_una_vendita',
        };
        $valori = [
            'case' => Formato::maiuscola($numero($annunci + $vendite, true)).' case',
            'annunci' => ($frase === 'vicine_frase_annunci' ? Formato::maiuscola($numero($annunci, false)) : ($annunci === 1 ? 'un' : $numero($annunci, false))).' '.($annunci === 1 ? 'annuncio' : 'annunci'),
            'vendite' => ($frase === 'vicine_frase_vendite' ? Formato::maiuscola($numero($vendite, true)) : $numero($vendite, true)).' compravendite',
        ];

        return Capo::html($c, 'vicine', 'ico-m-compra.png', 'vicine_occhiello', 'vicine_titolo', $frase, $valori).self::mappa($c).self::corpo($c);
    }

    private static function corpo(Contesto $c): string
    {
        [$chiesti, $pagati] = self::prezzi($c);
        if ($chiesti === [] && $pagati === []) {
            return '';
        }
        $stima = $c->cifre->valoreMq();
        $h = '';
        if ($c->riquadro('vicine_grafico')) {
            $svg = Disegni::puntiDoppi($chiesti, $pagati, $stima, [
                'chiesti' => $c->etichetta('vicine_chiesti'),
                'pagati' => $c->etichetta('vicine_pagati'),
                'stima' => $c->etichetta('vicine_stima', ['valore_mq' => Formato::euroMq($stima)]),
                'media' => $c->etichetta('vicine_media'),
                'unita' => '€/m²',
            ]);
            if ($svg !== null) {
                $h .= Parti::riquadro($c->testo('vicine_grafico_t'), Parti::img($svg));
            }
        }
        if ($c->riquadro('vicine_numeri')) {
            $numeri = [];
            if ($chiesti !== []) {
                $numeri[] = [Formato::euroMq(array_sum($chiesti) / count($chiesti)), $c->testo('vicine_chiesti_n'), false];
            }
            if ($pagati !== []) {
                $numeri[] = [Formato::euroMq(array_sum($pagati) / count($pagati)), $c->testo('vicine_pagati_n'), false];
            }
            $numeri[] = [Formato::euroMq($stima), $c->testo('vicine_stima_n'), true];
            $h .= '<div class="vuoto"></div>';
            foreach ($numeri as $k => [$numero, $testo, $verde]) {
                $ultimo = $k === count($numeri) - 1;
                $h .= '<div class="sn'.($ultimo ? ' sn-ultimo' : '').'"><p class="s-n'.($verde ? ' s-n-verde' : '').'">'.Parti::t($numero).'</p>'.Parti::p($testo, 's-t').'</div>';
            }
            $h .= '<div class="pulisci"></div>';
        }

        return $h;
    }

    /**
     * I €/m² chiesti e pagati, per numero della casa (da 1).
     *
     * @return array{0: array<int, float>, 1: array<int, float>}
     */
    private static function prezzi(Contesto $c): array
    {
        $chiesti = [];
        foreach (Confronti::annunci($c) as $a) {
            if ($a['al_mq'] !== null) {
                $chiesti[$a['numero']] = $a['al_mq'];
            }
        }
        $pagati = [];
        foreach (Confronti::vendute($c) as $v) {
            if ($v['al_mq'] !== null) {
                $pagati[$v['numero']] = $v['al_mq'];
            }
        }

        return [$chiesti, $pagati];
    }
}