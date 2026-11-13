<?php

namespace App\Support\Geo;

use App\Support\FibraPunto;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * IL MAGAZZINO GEOGRAFICO (lotto «Valutazione 3», D-VAL-73, D-VAL-74,
 * D-VAL-85): un'interfaccia sola per «la fibra di questo punto», «gli
 * oggetti di questo riquadro» e «com'è il magazzino».
 *
 * Due modi, scelti dall'Admin (di fabbrica «automatico»):
 *  - locale: i file sul server del sito (la fibra in storage/app/fibra,
 *    come su dev oggi; la copia OpenStreetMap in storage/app/geo);
 *  - remoto: il servizio su reesty (ClienteGeo);
 *  - automatico: locale se il file c'è sul server, altrimenti remoto.
 * Le risposte sono identiche nei due modi (stesso codice: LettoreFibra e
 * LettoreOsm).
 *
 * MAI UN ERRORE IN PAGINA: se qualcosa non va la risposta è null e
 * $ultimoMotivo dice perché (la scheda mostra «in lettura» come oggi).
 * Se reesty non risponde, per i minuti della pausa (di fabbrica 10) il sito
 * non gli chiede più niente, così le pagine non aspettano (D-VAL-85).
 * La fibra di un punto si ricorda per i giorni scelti (di fabbrica 7).
 */
final class MagazzinoGeo
{
    /** Perché l'ultima richiesta non è andata. */
    public static ?string $ultimoMotivo = null;

    /** La pausa della fibra (le schede); gli oggetti delle mappe hanno la loro, così un riquadro pesante non ferma la fibra. */
    public const PAUSA = 'geo:pausa';

    public const PAUSA_OGGETTI = 'geo:pausa:oggetti';

    /** Il tetto degli oggetti chiesti in una volta (il servizio ha il suo, D-VAL-91). */
    public const OGGETTI_MAX = 200000;

    /** Quanta memoria occupa un oggetto della mappa letto qui (misurato: circa 3,4 KB, arrotondato). */
    public const BYTE_PER_OGGETTO = 4096;

    // ------------------------------------------------------------- MODI

    /** Dove si legge la fibra adesso: «locale» o «remoto». */
    public static function modoFibra(): string
    {
        return match (ImpostazioniGeo::modo()) {
            'locale' => 'locale',
            'remoto' => 'remoto',
            default => FibraPunto::fileLocale() ? 'locale' : 'remoto',
        };
    }

    /** Dove si leggono gli oggetti della mappa adesso: «locale» o «remoto». */
    public static function modoOggetti(): string
    {
        return match (ImpostazioniGeo::modo()) {
            'locale' => 'locale',
            'remoto' => 'remoto',
            default => self::fileOsmLocali() !== [] ? 'locale' : 'remoto',
        };
    }

    /**
     * I file OpenStreetMap sul server del sito, delle regioni scelte (e il
     * mare, lotto 4, D-VAL-95: non è una regione da scegliere, c'è sempre).
     *
     * @return array<string, string> regione => percorso
     */
    public static function fileOsmLocali(): array
    {
        $file = [];
        foreach (glob(storage_path(trim((string) config('geo.cartella_osm', 'app/geo'), '/')).'/osm-*.gpkg') ?: [] as $percorso) {
            $regione = substr(basename($percorso, '.gpkg'), 4);
            if (! str_ends_with($regione, '-scorta') && in_array($regione, ImpostazioniGeo::regioniELMare(), true)) {
                $file[$regione] = $percorso;
            }
        }
        ksort($file);

        return $file;
    }

    // ------------------------------------------------------------- FIBRA

    /** La fibra è accesa dall'Admin per la scheda («scheda») o per il report («report»)? */
    public static function fibraAccesa(string $per = 'scheda'): bool
    {
        return ImpostazioniGeo::acceso($per === 'report' ? ImpostazioniGeo::FIBRA_REPORT : ImpostazioniGeo::FIBRA_SCHEDA);
    }

    /** Si può chiedere la fibra? (accesa, e il file sul server o la chiave per reesty) */
    public static function fibraDisponibile(string $per = 'scheda'): bool
    {
        try {
            if (! self::fibraAccesa($per)) {
                return false;
            }

            return self::modoFibra() === 'locale' ? FibraPunto::fileLocale() : ClienteGeo::configurato();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * LA FIBRA DEL PUNTO, dal file o da reesty: la stessa risposta di
     * LettoreFibra::cerca(). Null = non si è potuto cercare ($ultimoMotivo).
     */
    public static function fibra(float $latitudine, float $longitudine, int $raggio = LettoreFibra::RAGGIO, string $per = 'scheda'): ?array
    {
        self::$ultimoMotivo = null;
        try {
            if (! self::fibraAccesa($per)) {
                self::$ultimoMotivo = 'la fibra è spenta dall\'Admin («Valutazione · Magazzino geografico»)';

                return null;
            }
            if (self::modoFibra() === 'locale') {
                $esito = LettoreFibra::cerca(FibraPunto::percorso(), $latitudine, $longitudine, $raggio);
                self::$ultimoMotivo = LettoreFibra::$ultimoMotivo;

                return $esito;
            }

            $raggio = max(0, min(LettoreFibra::RAGGIO_MAX, $raggio));
            $giorni = ImpostazioniGeo::numero(ImpostazioniGeo::MEMORIA_GIORNI);
            $chiave = 'geo:fibra:'.sha1(round($latitudine, 7).'|'.round($longitudine, 7).'|'.$raggio);
            if ($giorni > 0) {
                $ricordata = Cache::get($chiave);
                if (is_array($ricordata)) {
                    return $ricordata;
                }
            }
            $risposta = self::chiedi(self::PAUSA, 'fibra', ['lat' => self::coordinata($latitudine), 'lon' => self::coordinata($longitudine), 'raggio' => $raggio], ImpostazioniGeo::numero(ImpostazioniGeo::ATTESA_FIBRA));
            $esito = $risposta['dati']['risultato'] ?? null;
            if (! $risposta['ok'] || ! is_array($esito) || ! array_key_exists('cella', $esito) || ! is_array($esito['vicine'] ?? null)) {
                self::$ultimoMotivo ??= 'risposta della fibra non completa';

                return null;
            }
            if ($giorni > 0) {
                Cache::put($chiave, $esito, now()->addMinutes((int) round($giorni * 1440)));
            }

            return $esito;
        } catch (Throwable $e) {
            self::$ultimoMotivo = 'lettura della fibra non riuscita ('.$e->getMessage().')';

            return null;
        }
    }

    // ----------------------------------------------------------- OGGETTI

    /**
     * GLI OGGETTI DELLA MAPPA del riquadro [ovest, sud, est, nord], dal file
     * o da reesty: la stessa risposta di LettoreOsm::oggetti(). Strati vuoti
     * = tutti. Null = non si è potuto leggere ($ultimoMotivo).
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $riquadro
     * @param  list<string>  $strati
     */
    public static function oggetti(array $riquadro, array $strati = [], int $limite = self::OGGETTI_MAX): ?array
    {
        self::$ultimoMotivo = null;
        try {
            [$ovest, $sud, $est, $nord] = array_map('floatval', array_values($riquadro));
            if (! ($ovest < $est && $sud < $nord)) {
                self::$ultimoMotivo = 'riquadro non valido';

                return null;
            }
            $massimo = ImpostazioniGeo::numero(ImpostazioniGeo::RIQUADRO_KM);
            $lato = self::latoKm($ovest, $sud, $est, $nord);
            if ($lato > $massimo + 1e-9) {
                self::$ultimoMotivo = 'riquadro troppo grande ('.ImpostazioniGeo::cifra(round($lato, 2)).' km; al massimo '.ImpostazioniGeo::cifra($massimo).' km di lato, dall\'Admin)';

                return null;
            }
            $limite = max(1, min(self::OGGETTI_MAX, $limite));
            if (self::modoOggetti() === 'remoto') {
                $limite = self::limitePerMemoria($limite);
            }
            if (self::modoOggetti() === 'locale') {
                $esito = LettoreOsm::oggetti(self::fileOsmLocali(), [$ovest, $sud, $est, $nord], $strati, $limite);
                self::$ultimoMotivo = LettoreOsm::$ultimoMotivo;

                return $esito;
            }
            $risposta = self::chiedi(self::PAUSA_OGGETTI, 'oggetti', [
                'ovest' => self::coordinata($ovest), 'sud' => self::coordinata($sud), 'est' => self::coordinata($est), 'nord' => self::coordinata($nord),
                'strati' => implode(',', $strati), 'regioni' => implode(',', ImpostazioniGeo::regioniELMare()), 'limite' => $limite,
            ], ImpostazioniGeo::numero(ImpostazioniGeo::ATTESA_OGGETTI));
            $esito = $risposta['dati']['risultato'] ?? null;
            if (! $risposta['ok'] || ! is_array($esito) || ! is_array($esito['strati'] ?? null)) {
                self::$ultimoMotivo ??= 'risposta degli oggetti non completa';

                return null;
            }
            self::$ultimoMotivo = $risposta['dati']['avviso'] ?? null;
            $esito['edizioni'] = $risposta['dati']['edizioni'] ?? [];

            return $esito;
        } catch (Throwable $e) {
            self::$ultimoMotivo = 'lettura degli oggetti non riuscita ('.$e->getMessage().')';

            return null;
        }
    }

    /** Gli oggetti che la risposta di reesty può portare restando nella memoria di QUESTO server (circa 4 KB l'uno). */
    public static function limitePerMemoria(int $limite): int
    {
        $tetto = LettoreOsm::tettoMemoria();

        return $tetto === null ? $limite : max(1, min($limite, intdiv($tetto, self::BYTE_PER_OGGETTO)));
    }

    /** Il lato più lungo del riquadro, in chilometri (lo stesso conto del servizio). */
    public static function latoKm(float $ovest, float $sud, float $est, float $nord): float
    {
        $medio = deg2rad(($sud + $nord) / 2);

        return max(($est - $ovest) * 111.32 * cos($medio), ($nord - $sud) * 110.57);
    }

    // ------------------------------------------------------------- STATO

    /**
     * COM'È IL MAGAZZINO: modi, indirizzo, chiave, pausa, i file sul server
     * e, se $chiediAReesty, lo stato del servizio (senza guardare la pausa).
     */
    public static function stato(bool $chiediAReesty = true): array
    {
        $locale = ['fibra' => null, 'osm' => []];
        if (FibraPunto::fileLocale()) {
            $locale['fibra'] = ['byte' => (int) filesize(FibraPunto::percorso()), 'edizione' => (string) config('nascosti.fibra_punto.edizione', '')];
        }
        foreach (self::fileOsmLocali() as $regione => $percorso) {
            $locale['osm'][$regione] = ['byte' => (int) filesize($percorso), 'modificato' => date('c', (int) filemtime($percorso))];
        }
        $stato = [
            'modo' => ImpostazioniGeo::modo(),
            'modo_fibra' => self::modoFibra(),
            'modo_oggetti' => self::modoOggetti(),
            'indirizzo' => ImpostazioniGeo::indirizzo(),
            'chiave' => ClienteGeo::configurato(),
            'pausa' => self::pausa(),
            'pausa_oggetti' => self::pausa(self::PAUSA_OGGETTI),
            'locale' => $locale,
            'remoto' => null,
        ];
        if ($chiediAReesty) {
            $stato['remoto'] = ClienteGeo::chiama('stato', [], 10.0);
            if ($stato['remoto']['ok']) {
                Cache::forget(self::PAUSA);
                Cache::forget(self::PAUSA_OGGETTI);
                $stato['pausa'] = null;
                $stato['pausa_oggetti'] = null;
            }
        }

        return $stato;
    }

    /** Scambia su reesty l'edizione in uso e la scorta (D-VAL-87). */
    public static function ripristina(string $file): array
    {
        return ClienteGeo::chiama('ripristina', ['file' => $file], 900.0);
    }

    /** Fa controllare a reesty un file arrivato col nome provvisorio (geo:fibra-a-reesty). */
    public static function controlla(string $file, string $sha256, int $byte, string $edizione): array
    {
        return ClienteGeo::chiama('controlla', ['file' => $file, 'sha256' => $sha256, 'byte' => $byte, 'edizione' => $edizione], 900.0);
    }

    // ------------------------------------------------------------- PAUSA

    /** Se il sito è in pausa con reesty (di fabbrica per la fibra): fino a quando e perché; altrimenti null. */
    public static function pausa(string $quale = self::PAUSA): ?array
    {
        try {
            $pausa = Cache::get($quale);
        } catch (Throwable) {
            return null;
        }

        return is_array($pausa) && ($pausa['fino'] ?? 0) > time() ? $pausa : null;
    }

    /** Una richiesta a reesty che guarda la sua pausa e la mette se reesty non risponde. */
    private static function chiedi(string $chiavePausa, string $azione, array $parametri, float $attesa): array
    {
        $pausa = self::pausa($chiavePausa);
        if ($pausa !== null) {
            self::$ultimoMotivo = 'reesty non ha risposto da poco ('.$pausa['motivo'].'): si riprova dopo le '.date('H:i', (int) $pausa['fino']);

            return ['ok' => false, 'codice' => 0, 'dati' => null, 'motivo' => self::$ultimoMotivo, 'millisecondi' => 0.0, 'raggiungibile' => false];
        }
        $risposta = ClienteGeo::chiama($azione, $parametri, $attesa);
        if (! $risposta['ok']) {
            self::$ultimoMotivo = $risposta['motivo'];
            $minuti = ImpostazioniGeo::numero(ImpostazioniGeo::PAUSA_MINUTI);
            if (! $risposta['raggiungibile'] && $minuti > 0) {
                $fino = time() + (int) round($minuti * 60);
                Cache::put($chiavePausa, ['fino' => $fino, 'motivo' => (string) $risposta['motivo']], now()->addSeconds((int) round($minuti * 60)));
            }
        } elseif (($risposta['dati']['ok'] ?? false) !== true) {
            self::$ultimoMotivo = (string) ($risposta['dati']['motivo'] ?? 'risposta senza esito');
        }

        return $risposta;
    }

    /** Una coordinata come testo, a 7 decimali (circa 1 cm), senza notazione scientifica. */
    private static function coordinata(float $gradi): string
    {
        return rtrim(rtrim(number_format($gradi, 7, '.', ''), '0'), '.');
    }
}