<?php

namespace App\Support\Valutazione\Report;

use App\Support\Valutazione\Testi;
use App\Support\Valutazione\TestiPagine;

/**
 * PAGINA «COME LEGGERE QUESTO REPORT» (lotto 2, D-VAL-61): quattro blocchi
 * (che cos'è, il valore e la forchetta, i confronti, le zone e le
 * quotazioni) e «Da dove vengono i dati». Ogni fonte è legata alle pagine
 * e ai riquadri che la usano: si stampa solo se almeno uno di loro c'è.
 * Così territorio, vista dall'alto, mappe e dintorni compaiono da soli coi
 * lotti 4 e 5.
 */
final class Metodo
{
    public static function fogli(Contesto $c): int
    {
        return self::corpo($c) !== '' ? 1 : 0;
    }

    public static function html(Contesto $c): string
    {
        return Capo::html($c, 'metodo', 'ico-analisi.png', 'metodo_occhiello', 'metodo_titolo', 'metodo_frase').self::corpo($c);
    }

    private static function corpo(Contesto $c): string
    {
        $h = '';
        if ($c->riquadro('metodo_blocchi')) {
            $blocchi = [];
            foreach (['metodo_cosa', 'metodo_forchetta', 'metodo_confronti', 'metodo_zone'] as $nome) {
                $titolo = $c->testo($nome.'_t');
                $testo = $c->testo($nome);
                if ($testo !== null && $testo !== '') {
                    $blocchi[] = '<div class="lb">'.Parti::p($titolo, 'lb-t').'<p class="lb-p">'.$testo.'</p></div>';
                }
            }
            $meta = (int) ceil(count($blocchi) / 2);
            $h .= Parti::colonne(implode('', array_slice($blocchi, 0, $meta)), implode('', array_slice($blocchi, $meta)));
        }
        if ($c->riquadro('metodo_fonti')) {
            $voci = '';
            foreach (self::fonti($c) as $html) {
                $voci .= '<li class="fonte">'.$html.'</li>';
            }
            if ($voci !== '') {
                $h .= '<div class="riquadro">'.Parti::p($c->testo('metodo_fonti_t'), 'kicker').'<ul class="fonti">'.$voci.'</ul></div>';
            }
        }

        return $h;
    }

    /**
     * Le fonti da stampare, in HTML (D-VAL-61). Le pagine che si guardano
     * sono quelle accese con qualcosa da mostrare; per non girare in tondo
     * (questa pagina è fra loro) si guarda la fotografia e i dati, non i
     * numeri di pagina.
     *
     * @return list<string>
     */
    public static function fonti(Contesto $c): array
    {
        $presenti = [];
        foreach (Pagine::accese($c->fotografia) as $chiave) {
            if ($chiave === Pagine::METODO) {
                continue;
            }
            $classe = Pagine::ELENCO[$chiave]['classe'];
            if (! method_exists($classe, 'fogli') || $classe::fogli($c) > 0) {
                $presenti[$chiave] = true;
            }
        }
        $fuori = [];
        foreach (TestiPagine::FONTI as $nome => [$pagine, $riquadri]) {
            // un riquadro conta se è acceso, la sua pagina c'è e, se è una mappa, la mappa è uscita davvero
            $serve = array_filter($pagine, fn (string $p): bool => isset($presenti[$p])) !== []
                || array_filter($riquadri, fn (string $r): bool => $c->riquadro($r) && isset($presenti[Testi::RIQUADRI[$r][0] ?? '']) && $c->mappaUscita($r)) !== [];
            $html = $serve ? $c->testo($nome) : null;
            if ($html !== null && $html !== '') {
                $fuori[] = $html;
            }
        }

        return $fuori;
    }
}