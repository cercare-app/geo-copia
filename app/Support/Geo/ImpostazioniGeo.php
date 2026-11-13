<?php

namespace App\Support\Geo;

use App\Models\Setting;
use App\Support\Permessi;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * LE IMPOSTAZIONI DEL MAGAZZINO GEOGRAFICO (lotto «Valutazione 3», D-VAL-73,
 * D-VAL-75, D-VAL-76, D-VAL-81, D-VAL-85), dall'Admin → «Valutazione ·
 * Magazzino geografico» (casella «Valutazione · Magazzino geografico ⚠»).
 *
 * Ogni campo vuoto vale la fabbrica, che funziona senza toccare niente:
 *  - modo automatico: il file sul server se c'è (dev, la fibra di oggi),
 *    altrimenti il servizio su reesty (la produzione);
 *  - l'indirizzo del servizio: /geo in produzione, /geo-prova altrove;
 *  - attese 3 s (fibra) e 15 s (oggetti), memoria della fibra 7 giorni,
 *    riquadro massimo 3 km, tutte le regioni, avviso «copia vecchia» dopo
 *    3 mesi, fibra accesa per scheda e report, pausa di 10 minuti se reesty
 *    non risponde.
 *
 * Le righe si cambiano SOLO da qui (Sistema → Impostazioni non le elenca).
 */
final class ImpostazioniGeo
{
    public const MODO = 'geo_modo';

    public const INDIRIZZO = 'geo_indirizzo';

    public const ATTESA_FIBRA = 'geo_attesa_fibra';

    public const ATTESA_OGGETTI = 'geo_attesa_oggetti';

    public const MEMORIA_GIORNI = 'geo_memoria_giorni';

    public const RIQUADRO_KM = 'geo_riquadro_km';

    public const REGIONI = 'geo_regioni';

    public const SOGLIA_MESI = 'geo_soglia_mesi';

    public const FIBRA_SCHEDA = 'geo_fibra_scheda';

    public const FIBRA_REPORT = 'geo_fibra_report';

    public const PAUSA_MINUTI = 'geo_pausa_minuti';

    public const MODI = [
        'automatico' => 'Automatico: il file sul server se c\'è, altrimenti il servizio su reesty',
        'locale' => 'Locale: solo i file sul server del sito',
        'remoto' => 'Remoto: solo il servizio su reesty',
    ];

    /** Le regioni di Geofabrik (D-VAL-70): insieme coprono tutta l'Italia. */
    public const TUTTE_LE_REGIONI = [
        'nord-ovest' => 'Nord-Ovest',
        'nord-est' => 'Nord-Est',
        'centro' => 'Centro',
        'sud' => 'Sud',
        'isole' => 'Isole',
    ];

    /** I numeri: chiave => [etichetta, fabbrica, minimo, massimo, unità]. */
    public const NUMERI = [
        self::ATTESA_FIBRA => ['Attesa massima per la fibra', 3, 1, 30, 'secondi'],
        self::ATTESA_OGGETTI => ['Attesa massima per gli oggetti della mappa', 15, 1, 120, 'secondi'],
        self::MEMORIA_GIORNI => ['Per quanto si ricorda la fibra di un punto', 7, 0, 365, 'giorni'],
        self::RIQUADRO_KM => ['Riquadro massimo della mappa', 3, 0.2, 10, 'km di lato'],
        self::SOGLIA_MESI => ['Avviso «copia vecchia» dopo', 3, 1, 24, 'mesi'],
        self::PAUSA_MINUTI => ['Pausa se reesty non risponde', 10, 0, 1440, 'minuti'],
    ];

    /** @return list<string> */
    public static function chiavi(): array
    {
        return [self::MODO, self::INDIRIZZO, self::REGIONI, self::FIBRA_SCHEDA, self::FIBRA_REPORT, ...array_keys(self::NUMERI)];
    }

    public static function modo(): string
    {
        $scritto = (string) Setting::get(self::MODO, '');

        return isset(self::MODI[$scritto]) ? $scritto : 'automatico';
    }

    /** L'indirizzo di fabbrica: /geo in produzione, /geo-prova altrove (D-VAL-77). */
    public static function indirizzoDiFabbrica(): string
    {
        $dal = trim((string) config('geo.indirizzo', ''));
        if ($dal !== '') {
            return $dal;
        }

        return app()->environment('production') ? (string) config('geo.indirizzo_produzione') : (string) config('geo.indirizzo_prova');
    }

    public static function indirizzo(): string
    {
        $scritto = trim((string) Setting::get(self::INDIRIZZO, ''));

        return $scritto !== '' ? $scritto : self::indirizzoDiFabbrica();
    }

    public static function numero(string $chiave): float
    {
        [, $fabbrica, $minimo, $massimo] = self::NUMERI[$chiave];
        $scritto = str_replace(',', '.', trim((string) Setting::get($chiave, '')));
        if ($scritto === '' || ! is_numeric($scritto)) {
            return (float) $fabbrica;
        }
        $n = (float) $scritto;

        return $n >= $minimo && $n <= $massimo ? $n : (float) $fabbrica;
    }

    /** @return list<string> */
    public static function regioni(): array
    {
        $scritto = trim((string) Setting::get(self::REGIONI, ''));
        if ($scritto === '') {
            return array_keys(self::TUTTE_LE_REGIONI);
        }

        return array_values(array_filter(array_map('trim', explode(',', $scritto)), fn (string $r): bool => preg_match('/^[a-z0-9][a-z0-9-]{1,40}$/', $r) === 1));
    }

    /** Il file del mare (lotto 4, D-VAL-95): accanto alle regioni, sempre. */
    public const MARE = 'mare';

    /**
     * Le regioni scelte e il mare: i file da leggere per le mappe.
     *
     * @return list<string>
     */
    public static function regioniELMare(): array
    {
        return array_values(array_unique([...self::regioni(), self::MARE]));
    }

    public static function acceso(string $chiave): bool
    {
        return (string) Setting::get($chiave, '1') !== '0';
    }

    /**
     * I valori di oggi per il modulo dell'Admin.
     *
     * @return array<string, mixed>
     */
    public static function tutte(): array
    {
        $valori = [
            self::MODO => self::modo(),
            self::INDIRIZZO => trim((string) Setting::get(self::INDIRIZZO, '')),
            self::REGIONI => self::regioni(),
            self::FIBRA_SCHEDA => self::acceso(self::FIBRA_SCHEDA),
            self::FIBRA_REPORT => self::acceso(self::FIBRA_REPORT),
        ];
        foreach (array_keys(self::NUMERI) as $chiave) {
            $valori[$chiave] = rtrim(rtrim(number_format(self::numero($chiave), 2, '.', ''), '0'), '.');
        }

        return $valori;
    }

    /**
     * Salva tutto, SOLO se cambiato e solo se tutto torna (tutto o niente).
     * Ritorna i nomi cambiati.
     *
     * @param  array<string, mixed>  $stato
     * @return list<string>
     */
    public static function scrivi(mixed $chi, array $stato): array
    {
        if (! Permessi::puo($chi, 'valutazione_magazzino')) {
            throw new DomainException('Il magazzino geografico lo cambia chi ha la casella «Valutazione · Magazzino geografico».');
        }
        $nuovi = [];
        $nomi = [];

        $modo = (string) ($stato[self::MODO] ?? 'automatico');
        if (! isset(self::MODI[$modo])) {
            throw new DomainException('Il modo va scelto fra automatico, locale e remoto. Non ho salvato nulla.');
        }
        $nuovi[self::MODO] = $modo === 'automatico' ? null : $modo;
        $nomi[self::MODO] = 'Modo';

        $indirizzo = trim((string) ($stato[self::INDIRIZZO] ?? ''));
        if ($indirizzo !== '' && preg_match('#^https://[a-z0-9.-]+(:\d{2,5})?(/[A-Za-z0-9._~/-]*)?$#i', $indirizzo) !== 1) {
            throw new DomainException('L\'indirizzo del servizio deve cominciare con https:// (per esempio https://portale.reesty.it/geo/). Non ho salvato nulla.');
        }
        if ($indirizzo !== '' && ! str_ends_with($indirizzo, '/')) {
            $indirizzo .= '/';
        }
        $nuovi[self::INDIRIZZO] = ($indirizzo === '' || $indirizzo === self::indirizzoDiFabbrica()) ? null : $indirizzo;
        $nomi[self::INDIRIZZO] = 'Indirizzo del servizio';

        foreach (self::NUMERI as $chiave => [$etichetta, $fabbrica, $minimo, $massimo, $unita]) {
            $scritto = str_replace(',', '.', trim((string) ($stato[$chiave] ?? '')));
            if ($scritto === '') {
                $nuovi[$chiave] = null;
            } elseif (! is_numeric($scritto) || (float) $scritto < $minimo || (float) $scritto > $massimo) {
                throw new DomainException('«'.$etichetta.'» va da '.self::cifra((float) $minimo).' a '.self::cifra((float) $massimo).' '.$unita.'. Non ho salvato nulla.');
            } else {
                $n = (float) $scritto;
                $nuovi[$chiave] = $n == $fabbrica ? null : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
            }
            $nomi[$chiave] = $etichetta;
        }

        $regioni = array_values(array_intersect(array_keys(self::TUTTE_LE_REGIONI), array_map('strval', (array) ($stato[self::REGIONI] ?? []))));
        if ($regioni === []) {
            throw new DomainException('Scegli almeno una regione di OpenStreetMap. Non ho salvato nulla.');
        }
        $nuovi[self::REGIONI] = count($regioni) === count(self::TUTTE_LE_REGIONI) ? null : implode(',', $regioni);
        $nomi[self::REGIONI] = 'Regioni di OpenStreetMap';

        foreach ([self::FIBRA_SCHEDA => 'Fibra nella scheda degli annunci', self::FIBRA_REPORT => 'Fibra nel report'] as $chiave => $etichetta) {
            $nuovi[$chiave] = filter_var($stato[$chiave] ?? true, FILTER_VALIDATE_BOOLEAN) ? null : '0';
            $nomi[$chiave] = $etichetta;
        }

        $fatte = [];
        DB::transaction(function () use ($nuovi, $nomi, &$fatte): void {
            foreach ($nuovi as $chiave => $valore) {
                $prima = Setting::query()->where('key', $chiave)->value('value');
                $prima = $prima === '' ? null : $prima;
                if ($prima !== $valore) {
                    Setting::updateOrCreate(['key' => $chiave], ['value' => $valore]);
                    $fatte[] = $nomi[$chiave];
                }
            }
        });

        return $fatte;
    }

    public static function cifra(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }
}