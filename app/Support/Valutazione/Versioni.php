<?php

namespace App\Support\Valutazione;

use App\Support\Permessi;
use App\Support\Valutazione\Mappe\ImpostazioniMappe;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * LE VERSIONI DEI TESTI (lotto 2, D-VAL-60): ogni salvataggio in «Testi
 * del report», in «Pagine e riquadri» e (dal lotto 4) in «Mappe e
 * territorio» diventa una versione numerata, con data e ora, chi e che cosa
 * è cambiato, e la copia intera di testi, avvertenze, pagine, riquadri,
 * disposizione e mappe di quel momento.
 *
 *  - Al primo salvataggio si conserva anche com'era prima (la versione 1),
 *    così si può sempre tornare indietro.
 *  - «Torna a questa versione» ne crea una nuova uguale a quella: niente si
 *    cancella mai.
 *  - Il report porta nella fotografia il numero della versione in uso.
 *  - Le versioni nate prima del lotto 4 non hanno le mappe: tornarci lascia
 *    le mappe come sono adesso.
 *  - Se la tabella non c'è ancora (migrazione non fatta), i testi si
 *    salvano lo stesso e la versione no: il registro del server lo dice.
 */
final class Versioni
{
    public const TABELLA = 'valutazione_versioni';

    /** Tutto ciò che si può cambiare da «Testi del report», da «Pagine e riquadri» e da «Mappe e territorio». */
    public static function istantanea(): array
    {
        return [...Testi::tutti(), 'disposizione' => Disposizione::tutte(), 'mappe' => ImpostazioniMappe::tutte()];
    }

    /** Com'è adesso, se non c'è ancora nessuna versione (da conservare come la prima); altrimenti null. */
    public static function serveLaPrima(): ?array
    {
        try {
            return DB::table(self::TABELLA)->count() === 0 ? self::istantanea() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Registra una versione nuova (e prima, se data, com'era prima).
     * Ritorna il suo numero, o null se non si è potuta registrare.
     *
     * @param  list<string>  $cambiate
     * @param  array<string, mixed>|null  $prima
     */
    public static function registra(mixed $chi, string $dove, array $cambiate, ?array $prima = null): ?int
    {
        try {
            if ($prima !== null && DB::table(self::TABELLA)->count() === 0) {
                DB::table(self::TABELLA)->insert(self::riga(null, 'Com\'era prima', [], $prima));
            }

            return (int) DB::table(self::TABELLA)->insertGetId(self::riga($chi, $dove, $cambiate, self::istantanea()));
        } catch (Throwable $e) {
            Log::error('Valutazione: versione dei testi non registrata', ['errore' => $e->getMessage()]);

            return null;
        }
    }

    /** Il numero della versione in uso (l'ultima); null se non ce n'è. */
    public static function ultima(): ?int
    {
        try {
            $n = DB::table(self::TABELLA)->max('id');

            return $n !== null ? (int) $n : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Le ultime versioni, dalla più recente.
     *
     * @return list<array{numero: int, quando: string, chi: string, dove: string, cambiate: list<string>}>
     */
    public static function elenco(int $quante = 30): array
    {
        try {
            $righe = DB::table(self::TABELLA)->orderByDesc('id')->limit($quante)->get(['id', 'created_at', 'chi', 'dove', 'cambiate']);
        } catch (Throwable) {
            return [];
        }
        $fuori = [];
        foreach ($righe as $r) {
            $r = (array) $r;
            $cambiate = json_decode((string) ($r['cambiate'] ?? ''), true);
            $fuori[] = [
                'numero' => (int) $r['id'],
                'quando' => (string) ($r['created_at'] ?? ''),
                'chi' => (string) ($r['chi'] ?? ''),
                'dove' => (string) ($r['dove'] ?? ''),
                'cambiate' => is_array($cambiate) ? array_values(array_map('strval', $cambiate)) : [],
            ];
        }

        return $fuori;
    }

    /**
     * Il contenuto di una versione; null se non c'è.
     *
     * @return array<string, mixed>|null
     */
    public static function contenuto(int $numero): ?array
    {
        try {
            $testo = DB::table(self::TABELLA)->where('id', $numero)->value('contenuto');
        } catch (Throwable) {
            return null;
        }
        $contenuto = json_decode((string) $testo, true);

        return is_array($contenuto) ? $contenuto : null;
    }

    /**
     * Torna a una versione: testi, avvertenze, pagine, riquadri,
     * disposizione e mappe diventano quelli di allora, e nasce una versione
     * nuova. Servono le caselle dei testi e di pagine e riquadri; se nella
     * versione le mappe erano diverse da oggi (dal lotto 4), anche quella
     * delle mappe.
     * Ritorna [cambiate, avvisi della guardia].
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    public static function ripristina(mixed $chi, int $numero): array
    {
        if (! Permessi::puo($chi, 'valutazione_testi') || ! Permessi::puo($chi, 'valutazione_pagine')) {
            throw new DomainException('Per tornare a una versione servono le caselle «Valutazione · Testi» e «Valutazione · Pagine e riquadri».');
        }
        $c = self::contenuto($numero);
        if ($c === null) {
            throw new DomainException('La versione n. '.$numero.' non c\'è. Non ho cambiato nulla.');
        }
        // le mappe di allora, se la versione le ha e sono diverse da oggi, nella forma del modulo dell'Admin
        $mappe = null;
        if (is_array($c['mappe'] ?? null)) {
            $allora = ImpostazioniMappe::normalizza($c['mappe']);
            $mappe = $allora !== ImpostazioniMappe::tutte() ? ImpostazioniMappe::perIlModulo($allora) : null;
        }
        if ($mappe !== null && ! Permessi::puo($chi, ImpostazioniMappe::PERMESSO)) {
            throw new DomainException('In quella versione le mappe erano diverse da oggi: per tornarci serve anche la casella «Valutazione · Mappe e territorio». Non ho cambiato nulla.');
        }
        $prima = self::serveLaPrima();
        $testi = ['avvertenze' => is_array($c['avvertenze'] ?? null) ? $c['avvertenze'] : Testi::AVVERTENZE_FABBRICA];
        foreach (is_array($c['testi'] ?? null) ? $c['testi'] : [] as $nome => $testo) {
            if (isset(Testi::TESTI[$nome]) && is_string($testo)) {
                $testi[$nome] = $testo;
            }
        }
        // la disposizione di allora, ripulita: valori fuori dai limiti di oggi tornano alla fabbrica
        $d = is_array($c['disposizione'] ?? null) ? $c['disposizione'] : [];
        $entro = fn (mixed $v, int $min, int $max, int $fabbrica): int => is_int($v) && $v >= $min && $v <= $max ? $v : $fabbrica;
        $righe = function (mixed $scelte, array $possibili): array {
            $buone = is_array($scelte) ? array_values(array_unique(array_filter($scelte, fn ($r): bool => is_string($r) && in_array($r, $possibili, true)))) : [];

            return $buone === [] ? $possibili : $buone;
        };
        $disposizione = [
            'pagine' => is_array($c['pagine'] ?? null) ? $c['pagine'] : [],
            'riquadri' => is_array($c['riquadri'] ?? null) ? $c['riquadri'] : [],
            'annunci' => $entro($d['annunci'] ?? null, Disposizione::QUANTI_MINIMO, Disposizione::QUANTI_MASSIMO, Disposizione::QUANTI_MASSIMO),
            'vendite' => $entro($d['vendite'] ?? null, Disposizione::QUANTI_MINIMO, Disposizione::QUANTI_MASSIMO, Disposizione::QUANTI_MASSIMO),
            'righe_annunci' => $righe($d['righe_annunci'] ?? null, Disposizione::VOCI_ANNUNCI),
            'righe_vendite' => $righe($d['righe_vendite'] ?? null, Disposizione::VOCI_VENDITE),
            'descrizione' => $entro($d['descrizione'] ?? null, Disposizione::DESCRIZIONE_MINIMO, Disposizione::DESCRIZIONE_MASSIMO, Disposizione::DESCRIZIONE_FABBRICA),
        ];
        // tutto in una transazione: se un pezzo non passa (testi di allora
        // troppo lunghi, segnaposti tolti, il database che si ferma) non
        // cambia niente, né i testi né pagine e riquadri
        try {
            $cambiate = DB::transaction(function () use ($chi, $testi, $disposizione, $mappe, $numero, $prima): array {
                [$cambiateTesti] = Testi::scrivi($chi, $testi, false);
                $cambiate = [...$cambiateTesti, ...Disposizione::scrivi($chi, $disposizione, false)];
                if ($mappe !== null) {
                    $cambiate = [...$cambiate, ...ImpostazioniMappe::scrivi($chi, $mappe, false)];
                }
                if ($cambiate !== []) {
                    self::registra($chi, 'Tornati alla versione n. '.$numero, $cambiate, $prima);
                }

                return $cambiate;
            });
        } catch (Throwable $e) {
            // il database è tornato com'era: la cache delle impostazioni (60 s) no, si svuota
            foreach ([...Testi::chiavi(), ...Disposizione::chiavi(), ...ImpostazioniMappe::chiavi()] as $chiave) {
                Cache::forget('setting.'.$chiave);
            }

            throw $e;
        }

        return [$cambiate, Guardia::nelleImpostazioni(Fotografia::adesso())];
    }

    /**
     * @param  list<string>  $cambiate
     * @param  array<string, mixed>  $contenuto
     * @return array<string, mixed>
     */
    private static function riga(mixed $chi, string $dove, array $cambiate, array $contenuto): array
    {
        return [
            'user_id' => is_object($chi) && isset($chi->id) ? (int) $chi->id : null,
            'chi' => is_object($chi) && isset($chi->name) ? mb_substr((string) $chi->name, 0, 190) : '',
            'dove' => mb_substr($dove, 0, 190),
            'cambiate' => json_encode(array_values($cambiate), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'contenuto' => json_encode($contenuto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
        ];
    }
}