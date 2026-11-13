<?php

namespace App\Support\Valutazione\Report;

use App\Support\Valutazione\Formato;
use App\Support\Valutazione\Grafici;
use App\Support\Valutazione\Segnaposti;

/**
 * PAGINA «LA ZONA» (lotto 2, D-VAL-53): le quattro caselle della zona OMI
 * (quotazioni, valore medio, case vendute, giorni in vendita) e la nota
 * sulle fonti. Dal lotto 4 la mappa con i confini della zona e la sua
 * didascalia (riquadro «zona_mappa»), prima delle caselle.
 */
final class Zona
{
    public static function fogli(Contesto $c): int
    {
        return self::mappa($c) !== '' || self::corpo($c) !== '' ? 1 : 0;
    }

    public static function html(Contesto $c): string
    {
        $omi = $c->dati['omi'];
        $valori = [
            'zona_codice' => $omi['codzona'] ?? null,
            'zona_nome' => ($omi['zona_descr'] ?? null) !== null ? Formato::titolo((string) $omi['zona_descr']) : null,
            'fascia' => ($omi['fascia'] ?? null) !== null ? mb_strtolower((string) $omi['fascia']) : null,
        ];
        // senza codice o nome della zona il titolo coi dati non si può scrivere: vale quello semplice
        $titolo = $c->testo('zona_titolo', $valori) !== null ? 'zona_titolo' : 'zona_titolo_semplice';

        return Capo::html($c, 'zona', 'ico-m-geo.png', 'zona_occhiello', $titolo, 'zona_frase', $valori).self::mappa($c).self::corpo($c);
    }

    /**
     * La mappa coi confini della zona e la didascalia (lotto 4, D-VAL-97);
     * vuoto se spenta o se non si può disegnare.
     */
    private static function mappa(Contesto $c): string
    {
        if (! $c->riquadro('zona_mappa')) {
            return '';
        }
        $svg = $c->mappa('zona');
        if ($svg === null) {
            return '';
        }
        $omi = $c->dati['omi'];
        $cornice = $c->cornici()['zona'] ?? [];
        $h = '<div class="mappa">'.Parti::img($svg).'</div>';
        if (($cornice['poligoni'] ?? []) !== []) {
            $didascalia = $c->testo('zona_mappa_didascalia', [
                'zona_codice' => $omi['codzona'] ?? null,
                'zona_nome' => ($omi['zona_descr'] ?? null) !== null ? Formato::titolo((string) $omi['zona_descr']) : null,
            ]);
            if (($cornice['oltre'] ?? false) === true) {
                $oltre = $c->testo('zona_mappa_oltre');
                $didascalia = implode(' · ', array_filter([$didascalia, $oltre], fn (?string $x): bool => $x !== null && $x !== ''));
            }
            if ($didascalia !== null && $didascalia !== '') {
                $colori = $c->impostazioniMappe()['colori'];
                $segno = Parti::img(Grafici::svg(4, 3, '<rect x="0.2" y="0.2" width="3.6" height="2.6" rx="0.6" ry="0.6" fill="'.$colori['zona'].'" fill-opacity="0.25" stroke="'.$colori['zona'].'" stroke-width="0.35" stroke-dasharray="0.8 0.5"/>', 4), 'leg-q');
                $h .= '<p class="leg-m">'.$segno.' '.$didascalia.'</p>';
            }
        }

        return $h;
    }

    /** Le caselle e la nota; vuoto se non c'è niente da mostrare. */
    private static function corpo(Contesto $c): string
    {
        $caselle = $c->riquadro('zona_caselle') ? self::caselle($c) : [];
        if ($caselle === []) {
            return '';
        }
        $h = '<div class="caselle">';
        foreach ($caselle as $k => [$numero, $titolo, $nota]) {
            $h .= '<div class="tile'.($k % 2 === 1 ? ' tile-dx' : '').'"><p class="tl-n'.(mb_strlen($numero) > 14 ? ' tl-n-lungo' : '').'">'.Segnaposti::sicuro($numero).'</p>'
                .'<p class="tl-t">'.$titolo.'</p>'.Parti::p($nota, 'tl-d').'</div>';
            if ($k % 2 === 1) {
                $h .= '<div class="pulisci"></div>';
            }
        }
        $h .= '<div class="pulisci"></div></div>';
        if ($c->riquadro('zona_fonte')) {
            $h .= Parti::p($c->testo('zona_fonte'), 'nota');
        }

        return $h;
    }

    /**
     * Le caselle che si possono stampare: [numero, titolo, nota].
     *
     * @return list<array{0: string, 1: string, 2: ?string}>
     */
    private static function caselle(Contesto $c): array
    {
        $serie = $c->dati['variazione_prezzo_immobile']['zona'] ?? [];
        $ultima = $serie !== [] ? $serie[count($serie) - 1] : null;
        $tutte = [];

        if ($ultima !== null && ($ultima['compr_min_zona'] ?? null) !== null && ($ultima['compr_max_zona'] ?? null) !== null) {
            $tutte[] = [Formato::numero($ultima['compr_min_zona']).' – '.Formato::euroMq($ultima['compr_max_zona']), 'zona_quotazioni', ['semestre' => Formato::semestre($ultima['semester'] ?? null)]];
        }
        $medio = $ultima['avg_zona'] ?? null;
        if (is_numeric($medio) && $medio > 0) {
            $tutte[] = [Formato::euroMq($medio), 'zona_medio', [
                'valore_mq' => Formato::euroMq($c->cifre->valoreMq()),
                'differenza' => Formato::percentuale(($c->cifre->valoreMq() / $medio - 1) * 100),
            ]];
        }
        $vendute = $c->cifre->compravenditeZona();
        if ($vendute !== null) {
            $tutte[] = [Formato::numero($vendute['vendute']), 'zona_vendute', ['anno_vendite' => (string) $vendute['anno']]];
        }
        $tempo = $c->cifre->tempo();
        if ($tempo !== null) {
            $tutte[] = [Formato::numero($tempo['giorni']).' '.(round($tempo['giorni']) == 1 ? 'giorno' : 'giorni'), 'zona_tempo', ['anno_tempi' => (string) $tempo['anno']]];
        }

        $fuori = [];
        foreach ($tutte as [$numero, $nome, $valori]) {
            $titolo = $c->testo($nome.'_t');
            if ($titolo === null || $titolo === '') {
                continue; // senza titolo il numero non si capisce
            }
            $fuori[] = [$numero, $titolo, $c->testo($nome.'_n', $valori)];
        }

        return $fuori;
    }
}