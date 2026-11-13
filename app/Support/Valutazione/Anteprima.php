<?php

namespace App\Support\Valutazione;

use App\Models\User;
use App\Support\Valutazione\Mappe\Materiale;
use App\Support\Valutazione\Mappe\Raccolta;
use App\Support\Valutazione\Report\Contesto;
use App\Support\Valutazione\Stima\DatiNonValidi;
use App\Support\Valutazione\Stima\Normalizza;
use DomainException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * L'ANTEPRIMA DEL REPORT DALL'ADMIN (D-VAL-28, D-VAL-41): il PDF vero, fatto
 * dal server coi dati di esempio inventati da Cerca.Re, con «ANTEPRIMA — non
 * valido» su ogni pagina. Non chiama il servizio delle stime, non paga, non
 * crea righe nella tabella valutazioni, non manda email. Dal lotto 4 chiede
 * le mappe al nostro magazzino geografico (sotto).
 *
 *  - L'esempio di fabbrica è nel repo (database/data/valutazione/esempio.json);
 *    solo l'amministratore può caricarne un altro (per provare liste corte o
 *    vuote, dati mancanti) e tornare alla fabbrica.
 *  - Ogni anteprima passa dalla guardia, come un report vero.
 *  - Si misura tutto (tempo, memoria di picco, peso): è la misura del web di
 *    D-VAL-36, coi limiti veri della pagina. L'esito dell'ultima prova, buona
 *    o fermata, resta in ultima.json accanto al PDF.
 *  - Tutto sta nella cartella privata (storage/app/private/valutazioni/anteprima).
 *  - Le foto degli annunci (lotto 2) sono le cinque illustrazioni neutre del
 *    fac-simile (database/data/valutazione/foto): non sono foto vere.
 *  - L'esito dice anche con quale versione dei testi è nata (D-VAL-60).
 *  - Le mappe (lotto 4, D-VAL-102): il materiale si chiede al magazzino
 *    geografico come per un report vero, e si ricorda per i giorni scelti in
 *    «Mappe e territorio» (di fabbrica 7), così le anteprime dopo la prima
 *    non aspettano. Una mappa che non esce non ferma l'anteprima: l'esito
 *    dice quale e perché («avvisi delle mappe»).
 */
final class Anteprima
{
    public const PDF = 'anteprima.pdf';

    public const ULTIMA = 'ultima.json';

    public const CARICATO = 'esempio-caricato.json';

    public const DIAGNOSI = 'diagnosi.pdf';

    /** Il PDF di prova 3 della diagnosi: tutte le mappe e la vista (lotto 4). */
    public const DIAGNOSI_MAPPE = 'diagnosi-mappe.pdf';

    /** Il JSON d'esempio più grande che si accetta (byte). */
    public const MASSIMO_JSON = 2_000_000;

    /**
     * Fa l'anteprima e la salva. Ritorna l'esito (quello di ultima.json).
     *
     * @return array<string, mixed>
     */
    public static function genera(mixed $chi, string $dove = 'pagina web'): array
    {
        $esito = [
            'quando' => now()->toIso8601String(),
            'chi' => $chi instanceof User ? (string) $chi->name : 'comando',
            'dove' => $dove,
            'esempio' => self::caricato() ? 'caricato' : 'fabbrica',
            'limiti' => ['memoria' => (string) ini_get('memory_limit'), 'secondi' => (string) ini_get('max_execution_time')],
        ];

        // il PDF di prima si toglie subito: se questa anteprima si ferma, non resta niente da scaricare
        self::disco()->delete(self::percorso(self::PDF));

        try {
            [$grezzo] = self::esempio();
            $avvisi = [];
            $dati = Normalizza::pulisci($grezzo, $avvisi);
            $esito['avvisi'] = $avvisi;

            if (function_exists('memory_reset_peak_usage')) {
                memory_reset_peak_usage();
            }
            $prima = memory_get_usage(true);
            $inizio = hrtime(true);
            $fotografia = Fotografia::adesso();
            $esito['versione_testi'] = $fotografia['versione_testi'] ?? null;
            $materiale = self::materiale($dati, $fotografia);
            $esito['secondi_mappe'] = round((hrtime(true) - $inizio) / 1e9, 2);
            $fatto = Report::pdf($dati, $fotografia, Report::FILIGRANA_ANTEPRIMA, [], self::foto(), $materiale);
            $esito['secondi'] = round((hrtime(true) - $inizio) / 1e9, 2);
            $esito['avvisi_mappe'] = $fatto['avvisi'];
            if ($materiale !== null) {
                // mappe tutte spente: niente materiale, niente da dire sulla casa
                $esito['controllo'] = $fatto['controllo'];
            }
            // il limite con cui il PDF è stato fatto: il report può averlo alzato
            $esito['limiti']['memoria'] = (string) ini_get('memory_limit');
            $esito['memoria_mb'] = round(memory_get_peak_usage(true) / 1048576, 1);
            $esito['memoria_in_piu_mb'] = round(max(0, memory_get_peak_usage(true) - $prima) / 1048576, 1);
            $esito['byte'] = strlen($fatto['pdf']);
            $esito['pagine'] = $fatto['pagine'];
            $esito['attese'] = $fatto['attese'];
            $esito['esito'] = 'ok';

            self::disco()->put(self::percorso(self::PDF), $fatto['pdf']);
        } catch (ReportFermo $e) {
            $esito['esito'] = 'fermata';
            $esito['motivi'] = $e->luoghi;
        } catch (DatiNonValidi $e) {
            $esito['esito'] = 'fermata';
            $esito['motivi'] = ['I dati di esempio non bastano: '.$e->getMessage()];
        } catch (Throwable $e) {
            Log::error('Valutazione: anteprima del report non riuscita', ['errore' => $e->getMessage(), 'file' => $e->getFile(), 'riga' => $e->getLine()]);
            $esito['esito'] = 'errore';
            $esito['motivi'] = ['Errore del generatore di PDF: il dettaglio è nel registro del server (storage/logs/laravel.log).'];
        }

        self::disco()->put(self::percorso(self::ULTIMA), (string) json_encode($esito, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return $esito;
    }

    /**
     * Il materiale delle mappe per l'anteprima, ricordato per i giorni scelti;
     * null se mappe, vista e «Il posto» sono tutte spente (niente da chiedere).
     *
     * @param  array<string, mixed>  $dati  i dati puliti
     * @param  array<string, mixed>  $fotografia
     */
    public static function materiale(array $dati, array $fotografia): ?Materiale
    {
        $contesto = new Contesto($dati, $fotografia, true, self::foto());
        if (! in_array(true, $contesto->mappeAccese(), true) && ! $contesto->posto()) {
            return null;
        }

        return Raccolta::perContesto($contesto, $contesto->impostazioniMappe()['anteprima']['giorni']);
    }

    /**
     * I dati di esempio in uso: quelli caricati dall'amministratore o quelli di fabbrica.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    public static function esempio(): array
    {
        if (self::caricato()) {
            $grezzo = json_decode((string) self::disco()->get(self::percorso(self::CARICATO)), true);
            if (is_array($grezzo)) {
                return [$grezzo, 'caricato'];
            }
        }
        $grezzo = json_decode((string) @file_get_contents((string) config('valutazione.esempio')), true);
        if (! is_array($grezzo)) {
            throw new DatiNonValidi('il file dei dati di esempio (database/data/valutazione/esempio.json) manca o non è un JSON valido.');
        }

        return [$grezzo, 'fabbrica'];
    }

    /**
     * Le illustrazioni d'esempio al posto delle foto degli annunci: la prima
     * per il primo annuncio e così via; quelle che non ci sono restano fuori
     * (la scheda mostra «Foto non disponibile»).
     *
     * @return array<int, string>
     */
    public static function foto(): array
    {
        $cartella = rtrim((string) config('valutazione.foto_esempio'), '/');
        $foto = [];
        for ($i = 0; $i < Disposizione::QUANTI_MASSIMO; $i++) {
            $file = $cartella.'/annuncio'.($i + 1).'.jpg';
            if ($cartella !== '' && is_file($file)) {
                $foto[$i] = $file;
            }
        }

        return $foto;
    }

    /**
     * Carica un altro JSON d'esempio (solo l'amministratore). Prima lo pulisce:
     * se non basta per un report, non lo salva. Ritorna gli avvisi della pulizia.
     *
     * @return list<string>
     */
    public static function carica(mixed $chi, string $json): array
    {
        if (! $chi instanceof User || ! $chi->isAdmin()) {
            throw new DomainException('Un altro JSON d\'esempio lo carica solo l\'amministratore.');
        }
        if (strlen($json) > self::MASSIMO_JSON) {
            throw new DomainException('Il JSON è troppo grande (al massimo 2 MB). Non l\'ho salvato.');
        }
        $grezzo = json_decode($json, true);
        if (! is_array($grezzo) || array_is_list($grezzo)) {
            throw new DomainException('Non è un JSON valido nella forma dei dati della stima (un oggetto con «input», «avm» e gli altri blocchi). Non l\'ho salvato.');
        }
        $avvisi = [];
        try {
            Normalizza::pulisci($grezzo, $avvisi);
        } catch (DatiNonValidi $e) {
            throw new DomainException('Questo JSON non basta per un report: '.$e->getMessage().' Non l\'ho salvato.');
        }
        self::disco()->put(self::percorso(self::CARICATO), (string) json_encode($grezzo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return $avvisi;
    }

    /** Torna all'esempio di fabbrica (solo l'amministratore). */
    public static function tornaAllaFabbrica(mixed $chi): void
    {
        if (! $chi instanceof User || ! $chi->isAdmin()) {
            throw new DomainException('All\'esempio di fabbrica torna solo l\'amministratore.');
        }
        self::disco()->delete(self::percorso(self::CARICATO));
    }

    public static function caricato(): bool
    {
        return self::disco()->exists(self::percorso(self::CARICATO));
    }

    /**
     * L'esito dell'ultima anteprima, o null se non ce n'è.
     *
     * @return array<string, mixed>|null
     */
    public static function ultima(): ?array
    {
        if (! self::disco()->exists(self::percorso(self::ULTIMA))) {
            return null;
        }
        $esito = json_decode((string) self::disco()->get(self::percorso(self::ULTIMA)), true);

        return is_array($esito) ? $esito : null;
    }

    /** C'è un PDF da scaricare? Solo con la guardia pronta: senza segreto non esce niente, nemmeno un PDF di prima. */
    public static function esiste(string $file = self::PDF): bool
    {
        return Guardia::pronta() === null && self::disco()->exists(self::percorso($file));
    }

    /** Il percorso di un file dell'anteprima sul disco privato. */
    public static function percorso(string $file): string
    {
        return trim((string) config('valutazione.cartella_anteprima'), '/').'/'.$file;
    }

    /** Il percorso vero sul server (per scaricarlo). */
    public static function percorsoVero(string $file): string
    {
        return self::disco()->path(self::percorso($file));
    }

    /** Il disco privato «local» (storage/app/private): mai pubblico. */
    public static function disco(): \Illuminate\Filesystem\FilesystemAdapter
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disco */
        $disco = Storage::disk('local');

        return $disco;
    }
}