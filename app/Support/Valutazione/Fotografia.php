<?php

namespace App\Support\Valutazione;

use App\Support\Cassa;
use App\Support\Valutazione\Mappe\ImpostazioniMappe;

/**
 * LA FOTOGRAFIA DI TESTI E IMPOSTAZIONI (D-VAL-18): il report si fa SEMPRE
 * da una fotografia, mai leggendo l'Admin a metà strada. La valutazione
 * venduta la conserva (colonna `fotografia`, lotti 6–8), così il suo PDF
 * si può rifare identico anche dopo che i testi sono cambiati.
 */
final class Fotografia
{
    /** Cambia quando cambia la forma della fotografia (2: lotto 2, con disposizione e versione dei testi; 3: lotto 4, con le mappe). */
    public const VERSIONE = 3;

    /**
     * Testi, avvertenze, riquadri, pagine, disposizione (quanti annunci,
     * righe del voce per voce…), il numero della versione dei testi,
     * impostazioni e aliquota di adesso, e dal lotto 4 mappe e territorio
     * (Mappe\ImpostazioniMappe).
     *
     * @return array<string, mixed>
     */
    public static function adesso(): array
    {
        return [
            'versione' => self::VERSIONE,
            ...Testi::tutti(),
            'disposizione' => Disposizione::tutte(),
            'mappe' => ImpostazioniMappe::tutte(),
            'versione_testi' => Versioni::ultima(),
            'impostazioni' => Impostazioni::tutte(),
            'aliquota' => Cassa::aliquota(),
        ];
    }
}