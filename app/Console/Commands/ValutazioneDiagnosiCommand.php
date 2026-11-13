<?php

namespace App\Console\Commands;

use App\Support\Geo\MagazzinoGeo;
use App\Support\Valutazione\Anteprima;
use App\Support\Valutazione\Fotografia;
use App\Support\Valutazione\Grafici;
use App\Support\Valutazione\Guardia;
use App\Support\Valutazione\Impostazioni;
use App\Support\Valutazione\Mappe\Raccolta;
use App\Support\Valutazione\Report;
use App\Support\Valutazione\Report\Contesto;
use App\Support\Valutazione\Report\Pagine;
use App\Support\Valutazione\ReportFermo;
use App\Support\Valutazione\Stima\DatiNonValidi;
use App\Support\Valutazione\Stima\Normalizza;
use App\Support\Valutazione\Versioni;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * LA DIAGNOSI DEL MOTORE DEL REPORT (D-VAL-36): dal Toolkit, prima di tutto
 * il resto. Dice in italiano se il server ha ciò che serve al PDF e quanto
 * costa farlo:
 *  - PHP e le estensioni gd (mPDF la richiede), mbstring, zlib, imagick;
 *  - limiti di memoria e di tempo del comando (quelli della pagina web li
 *    misura l'anteprima dall'Admin) e la memoria per fare il PDF: se basta
 *    già o se il report sa alzarla da sé (lotto 1.3);
 *  - mPDF installato, i caratteri del report, il logo e le icone;
 *  - le cartelle: quella privata dei report e quella di lavoro di mPDF;
 *  - la guardia: se il segreto c'è (mai il contenuto);
 *  - i dati di esempio, le illustrazioni degli annunci, la tabella
 *    valutazioni e quella delle versioni dei testi (in sola lettura);
 *  - due PDF di prova, con tempo, memoria di picco e peso: il report coi
 *    dati e le illustrazioni d'esempio (dal lotto 2 tutte le sue pagine),
 *    poi lo stesso con una mappa pesante come quelle del fac-simile (circa
 *    900 KB di SVG) e un grafico. Il secondo resta nella cartella privata e
 *    si scarica da «Valutazione · Anteprima del report».
 *
 *  - dal lotto 3, il magazzino geografico: da dove si leggono fibra e
 *    mappe e lo stato del servizio su reesty (l'unica chiamata fuori dal
 *    server, al nostro servizio: D-VAL-89).
 *  - dal lotto 4, il PDF di prova 3 (D-VAL-102): il report d'esempio con
 *    tutte le mappe e la vista accese, qualunque cosa dica l'Admin, e il
 *    materiale chiesto davvero al magazzino (senza la memoria
 *    dell'anteprima): il tempo dei dati, poi tempo, memoria e peso del PDF.
 *    Resta nella cartella privata e si scarica dall'anteprima («Scarica il
 *    PDF di prova delle mappe»).
 *
 * Non scrive nel database, non chiama servizi a pagamento, non manda
 * email, non lancia processi.
 */
class ValutazioneDiagnosiCommand extends Command
{
    protected $signature = 'valutazione:diagnosi';

    protected $description = 'Valutazione: controlla che il server sappia fare il PDF del report e misura tempo, memoria e peso (sola lettura)';

    /** Quanto deve pesare la mappa di prova (byte di SVG), come una mappa del fac-simile. */
    public const PESO_MAPPA = 880_000;

    private bool $tuttoBene = true;

    public function handle(): int
    {
        $this->info('Diagnosi del motore del report della Valutazione');
        $this->line('');

        $this->line('PHP '.PHP_VERSION);
        $gd = extension_loaded('gd');
        $this->riga('Estensione gd (serve a mPDF)', $gd, $gd ? 'versione '.(gd_info()['GD Version'] ?? '?').(! empty(gd_info()['PNG Support']) ? ', PNG sì' : ', PNG NO') : 'MANCA: senza gd mPDF non parte. Il lotto si ferma qui e si decide insieme.');
        $this->riga('Estensione mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'sì' : 'MANCA');
        $this->riga('Estensione zlib (PDF compressi)', extension_loaded('zlib'), extension_loaded('zlib') ? 'sì' : 'manca: i PDF verranno più pesanti');
        $this->line('  Estensione imagick (per le mappe in immagine, se servirà): '.(extension_loaded('imagick') ? 'sì' : 'no'));
        $this->line('  Limite di memoria del comando: '.(ini_get('memory_limit') === '-1' ? 'senza limite' : ini_get('memory_limit')).' · limite di tempo del comando: '.(ini_get('max_execution_time') === '0' ? 'nessuno' : ini_get('max_execution_time').' s'));
        $this->line('  (Nelle pagine web i limiti sono quelli di Plesk: li misura «Genera l\'anteprima» dall\'Admin.)');
        $memoria = (string) ini_get('memory_limit');
        $nota = '';
        try {
            $voluta = Impostazioni::memoriaPdfMb();
        } catch (Throwable) {
            $voluta = Impostazioni::MEMORIA_FABBRICA_MB;
            $nota = ' (database non raggiungibile: uso quella di fabbrica)';
        }
        $giaBasta = Impostazioni::byteDaIni($memoria) === -1 || Impostazioni::byteDaIni($memoria) >= $voluta * 1024 * 1024;
        // la prova alza la memoria e la rimette subito: qui il comando usa
        // pochi MB, e così le prove PDF qui sotto provano davvero il report
        $alzabileMemoria = $giaBasta || @ini_set('memory_limit', $voluta.'M') !== false;
        if (! $giaBasta && $alzabileMemoria) {
            @ini_set('memory_limit', $memoria);
        }
        $this->riga('Memoria per fare il PDF: '.$voluta.'M'.$nota, $alzabileMemoria, $giaBasta
            ? 'basta già il limite di questo comando ('.($memoria === '-1' ? 'senza limite' : $memoria).')'
            : ($alzabileMemoria
                ? 'il report la alza da sé da '.$memoria.' quando fa il PDF (si cambia in «Valutazione · Impostazioni»)'
                : 'il server non lascia alzarla da '.$memoria.': il PDF si fa con quella che c\'è, e con un PDF pesante potrebbe non bastare'));
        $pcre = (string) ini_get('pcre.backtrack_limit');
        $alzabile = ini_set('pcre.backtrack_limit', (string) ((int) $pcre + 1)) !== false;
        ini_set('pcre.backtrack_limit', $pcre);
        $this->riga('Limite pcre.backtrack_limit: '.number_format((int) $pcre, 0, ',', '.'), $alzabile, $alzabile
            ? 'il report lo alza da sé, solo mentre fa il PDF, quando una pagina è più lunga (le pagine con le mappe)'
            : 'il server non lascia alzarlo: le pagine con le mappe non si potranno fare finché non si alza nelle impostazioni PHP di Plesk');
        $this->line('');

        $mpdf = class_exists(\Mpdf\Mpdf::class);
        $this->riga('mPDF installato', $mpdf, $mpdf ? 'versione '.\Mpdf\Mpdf::VERSION : 'NO: manca nel vendor (lo installa Actions da composer.json)');

        $cartellaCaratteri = (string) config('valutazione.caratteri');
        $mancano = [];
        foreach (Report::CARATTERI as $famiglia) {
            foreach ($famiglia as $file) {
                if (! is_file($cartellaCaratteri.'/'.$file)) {
                    $mancano[] = $file;
                }
            }
        }
        $this->riga('Caratteri del report', $mancano === [], $mancano === [] ? 'tutti in resources/fonts/valutazione' : 'mancano: '.implode(', ', $mancano));
        $logo = is_file((string) config('valutazione.logo'));
        $mancanti = array_values(array_filter(Pagine::ICONE, fn (string $f): bool => ! is_file(config('valutazione.icone').'/'.$f)));
        $icone = $mancanti === [];
        $this->riga('Logo e icone', $logo && $icone, $logo && $icone ? 'presenti' : 'manca '.(! $logo ? 'public/cercare.png ' : '').(! $icone ? 'in public/icons: '.implode(', ', $mancanti) : ''));
        $foto = count(Anteprima::foto());
        $this->riga('Illustrazioni d\'esempio degli annunci', $foto === 5, $foto === 5 ? '5 presenti' : $foto.' su 5 in database/data/valutazione/foto (le schede mostrano «Foto non disponibile»)');
        $this->line('');

        $this->riga('Cartella privata dei report', $this->scrivibile(Anteprima::disco()->path(trim((string) config('valutazione.cartella_anteprima'), '/'))), 'storage/app/private/'.config('valutazione.cartella_anteprima'));
        $this->riga('Cartella di lavoro di mPDF', $this->scrivibile((string) config('valutazione.mpdf_temp')), 'storage/framework/mpdf');
        $pronta = Guardia::pronta();
        $this->riga('Guardia sul nome', $pronta === null, Guardia::statoSegreto());
        try {
            $tabella = Schema::hasTable('valutazioni');
            $this->riga('Tabella valutazioni', $tabella, $tabella ? 'c\'è' : 'manca: lancia prima migrate --force');
            $versioni = Schema::hasTable(Versioni::TABELLA);
            $this->riga('Tabella delle versioni dei testi', $versioni, $versioni ? 'c\'è' : 'manca: lancia prima migrate --force (senza, i testi si salvano ma le versioni no)');
        } catch (Throwable) {
            $this->riga('Tabella valutazioni', false, 'database non raggiungibile');
        }
        $this->line('');

        $this->magazzino();

        try {
            [$grezzo, $quale] = Anteprima::esempio();
            $avvisi = [];
            $dati = Normalizza::pulisci($grezzo, $avvisi);
            $this->riga('Dati di esempio', true, 'quelli '.($quale === 'fabbrica' ? 'di fabbrica' : 'caricati dall\'amministratore').', puliti'.($avvisi === [] ? ' senza avvisi' : ' con '.count($avvisi).' avvisi: '.implode(' ', $avvisi)));
        } catch (DatiNonValidi $e) {
            $this->riga('Dati di esempio', false, $e->getMessage());

            return self::FAILURE;
        }

        // il PDF di prova di prima non deve restare scaricabile se oggi non se ne fa uno nuovo
        Anteprima::disco()->delete(Anteprima::percorso(Anteprima::DIAGNOSI));
        Anteprima::disco()->delete(Anteprima::percorso(Anteprima::DIAGNOSI_MAPPE));

        if (! $gd || ! $mpdf || $mancano !== [] || $pronta !== null) {
            $this->line('');
            $this->error('PDF di prova NON fatti: prima va sistemato ciò che è segnato qui sopra.');

            return self::FAILURE;
        }

        $this->line('');
        $this->misura('PDF di prova 1 · il report coi dati di esempio', $dati, []);
        $this->misura('PDF di prova 2 · il report + una mappa pesante e un grafico', $dati, ['Prova tecnica · mappa e grafico' => $this->paginaPesante()], Anteprima::DIAGNOSI);
        $this->misuraMappe($dati);

        $this->line('');
        if ($this->tuttoBene) {
            $this->info('Tutto a posto. I PDF di prova 2 e 3 si scaricano da «Valutazione · Anteprima del report».');

            return self::SUCCESS;
        }
        $this->error('Qualcosa non va: vedi le righe segnate qui sopra.');

        return self::FAILURE;
    }

    /**
     * Il PDF di prova 3: tutte le mappe e la vista accese, il materiale chiesto
     * davvero al magazzino. Dice quanto hanno preso i dati e quanto il PDF, e
     * quali mappe non sono uscite.
     *
     * @param  array<string, mixed>  $dati
     */
    private function misuraMappe(array $dati): void
    {
        $titolo = 'PDF di prova 3 · il report con tutte le mappe e la vista';
        try {
            $fotografia = Fotografia::adesso();
            foreach (['posto', 'zona', 'vicine', 'schede', 'vendute'] as $pagina) {
                $fotografia['pagine'][$pagina] = true;
            }
            foreach (['cop_mappa', 'posto_vista', 'zona_mappa', 'vicine_mappa', 'schede_mappa', 'vendute_mappa'] as $riquadro) {
                $fotografia['riquadri'][$riquadro] = true;
            }
            if (function_exists('memory_reset_peak_usage')) {
                memory_reset_peak_usage();
            }
            $inizio = hrtime(true);
            $materiale = Raccolta::perContesto(new Contesto($dati, $fotografia, true, Anteprima::foto()), 0);
            $secondiDati = (hrtime(true) - $inizio) / 1e9;
            $fatto = Report::pdf($dati, $fotografia, 'PROVA TECNICA — non valido', [], Anteprima::foto(), $materiale);
            $secondi = (hrtime(true) - $inizio) / 1e9;
            $memoria = memory_get_peak_usage(true) / 1048576;
            Anteprima::disco()->put(Anteprima::percorso(Anteprima::DIAGNOSI_MAPPE), $fatto['pdf']);
            $buono = $fatto['avvisi'] === [];
            $this->riga($titolo, $buono, number_format($secondi, 2, ',', '.').' s, di cui '.number_format($secondiDati, 2, ',', '.').' s per i dati delle mappe · memoria di picco '.number_format($memoria, 1, ',', '.').' MB · '.number_format(strlen($fatto['pdf']) / 1024, 0, ',', '.').' KB · '.$fatto['pagine'].' pagine'.($fatto['pagine'] !== $fatto['attese'] ? ' (attese '.$fatto['attese'].': un testo è andato a capo su una pagina in più)' : ''));
            foreach ($fatto['avvisi'] as $avviso) {
                $this->line('      '.$avviso);
            }
        } catch (ReportFermo $e) {
            $this->riga($titolo, false, 'fermato dalla guardia: '.implode(' · ', $e->luoghi));
        } catch (Throwable $e) {
            $this->riga($titolo, false, 'errore: '.$e->getMessage());
        }
    }

    /**
     * Fa un PDF di prova e dice quanto è costato.
     *
     * @param  array<string, mixed>  $dati
     * @param  array<string, string>  $pagineInPiu
     */
    private function misura(string $titolo, array $dati, array $pagineInPiu, ?string $salva = null): void
    {
        try {
            if (function_exists('memory_reset_peak_usage')) {
                memory_reset_peak_usage();
            }
            $inizio = hrtime(true);
            $fatto = Report::pdf($dati, Fotografia::adesso(), 'PROVA TECNICA — non valido', $pagineInPiu, Anteprima::foto());
            $secondi = (hrtime(true) - $inizio) / 1e9;
            $memoria = memory_get_peak_usage(true) / 1048576;
            if ($salva !== null) {
                Anteprima::disco()->put(Anteprima::percorso($salva), $fatto['pdf']);
            }
            $this->riga($titolo, true, number_format($secondi, 2, ',', '.').' s · memoria di picco '.number_format($memoria, 1, ',', '.').' MB · '.number_format(strlen($fatto['pdf']) / 1024, 0, ',', '.').' KB · '.$fatto['pagine'].' pagine'.($fatto['pagine'] !== $fatto['attese'] ? ' (attese '.$fatto['attese'].': un testo è andato a capo su una pagina in più)' : ''));
        } catch (ReportFermo $e) {
            $this->riga($titolo, false, 'fermato dalla guardia: '.implode(' · ', $e->luoghi));
        } catch (Throwable $e) {
            $this->riga($titolo, false, 'errore: '.$e->getMessage());
        }
    }

    /**
     * La pagina pesante: una mappa di prova fatta di migliaia di poligoni,
     * pesante come una mappa del fac-simile, e la barra della forchetta.
     */
    private function paginaPesante(): string
    {
        $svg = $this->mappaPesante();
        $grafico = Grafici::forchetta(0.42, '306.000 €', '537.000 €', 'Minimo', 'Massimo', 178);

        return '<div class="capo"><div class="capo-testo"><p class="kick">Prova tecnica</p><h2>Una mappa pesante e un grafico</h2>'
            .'<p class="lead">Mappa di prova di '.number_format(strlen($svg) / 1024, 0, ',', '.').' KB di SVG: serve solo a misurare il generatore.</p></div><div class="pulisci"></div></div>'
            .'<div><img src="'.Grafici::comeImmagine($svg).'" style="width: 178mm" alt=""></div>'
            .'<div style="margin-top: 6mm"><img src="'.Grafici::comeImmagine($grafico).'" alt=""></div>';
    }

    /** Migliaia di «edifici» a caso (sempre gli stessi), fino al peso di una mappa vera. */
    private function mappaPesante(): string
    {
        mt_srand(20261106);
        $corpo = '<rect x="0" y="0" width="1000" height="700" fill="#F3F1EC"/>';
        for ($i = 0; $i < 40; $i++) {
            $y = mt_rand(0, 700);
            $corpo .= '<path d="M0 '.$y.' L1000 '.($y + mt_rand(-120, 120)).'" stroke="#ffffff" stroke-width="'.mt_rand(4, 12).'" fill="none"/>';
        }
        while (strlen($corpo) < self::PESO_MAPPA) {
            $x = mt_rand(0, 9800) / 10;
            $y = mt_rand(0, 6800) / 10;
            $punti = [];
            $lati = mt_rand(4, 7);
            for ($k = 0; $k < $lati; $k++) {
                $angolo = 2 * M_PI * $k / $lati;
                $r = mt_rand(30, 90) / 10;
                $punti[] = number_format($x + $r * cos($angolo), 1, '.', '').' '.number_format($y + $r * sin($angolo), 1, '.', '');
            }
            $corpo .= '<path d="M'.implode(' L', $punti).' Z" fill="#E4E1DA" stroke="#CFCAC0" stroke-width="0.4"/>';
        }
        $corpo .= '<circle cx="500" cy="350" r="9" fill="#22A354" stroke="#ffffff" stroke-width="3"/>';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 700" width="178mm" height="124.6mm">'.$corpo.'</svg>';
    }

    /**
     * IL MAGAZZINO GEOGRAFICO (lotto «Valutazione 3», D-VAL-75, D-VAL-89):
     * da dove si leggono fibra e mappe, la chiave (mai il valore), lo stato
     * del servizio su reesty e le edizioni. Sono righe da guardare, ma non
     * cambiano l'esito della diagnosi del PDF: il report non le usa ancora.
     */
    private function magazzino(): void
    {
        $this->line('Magazzino geografico (fibra e mappe; non cambia l\'esito della diagnosi del PDF):');
        try {
            $stato = MagazzinoGeo::stato(true);
        } catch (Throwable $e) {
            $this->line('  NO  Magazzino geografico: non leggibile ('.$e->getMessage().')');
            $this->line('');

            return;
        }
        $dove = fn (string $modo): string => $modo === 'locale' ? 'dal file sul server' : 'da reesty';
        $this->line('  ·   Modo: '.$stato['modo'].' · fibra '.$dove($stato['modo_fibra']).' · mappe '.$dove($stato['modo_oggetti']).' · servizio '.$stato['indirizzo']);
        $this->line('  '.($stato['chiave'] ? 'OK ' : 'NO ').' Chiave GEO_CHIAVE nel .env: '.($stato['chiave'] ? 'presente' : 'manca o non va bene (da 32 a 200 lettere e numeri)'));
        $remoto = $stato['remoto'];
        if (! ($remoto['ok'] ?? false)) {
            $this->line('  NO  Servizio su reesty: '.($remoto['motivo'] ?? 'non chiesto'));
            $this->line('');

            return;
        }
        $d = $remoto['dati'];
        $this->line('  OK  Servizio su reesty: risponde in '.number_format((float) $remoto['millisecondi'], 0, ',', '.').' ms · PHP '.($d['php'] ?? '?').' · SQLite '.($d['sqlite'] ?? '?').' · indice spaziale '.(($d['rtree'] ?? false) ? 'sì' : 'NO'));
        $file = (array) ($d['file'] ?? []);
        if ($file === []) {
            $this->line('  ·   Su reesty nessun file ancora (la fibra la porta geo:fibra-a-reesty da dev, le mappe il workflow «Copia OpenStreetMap»)');
        }
        foreach ($file as $nome => $info) {
            $this->line('  ·   '.$nome.': edizione '.($info['edizione'] ?? '?').' · '.number_format(((int) ($info['byte'] ?? 0)) / 1048576, 1, ',', '.').' MB'.(is_array($info['scorta'] ?? null) ? ' · scorta '.($info['scorta']['edizione'] ?? '?') : ''));
        }
        foreach ((array) ($d['in_arrivo'] ?? []) as $nome => $info) {
            $this->line('  ·   In arrivo: '.$nome.' ('.number_format(((int) ($info['byte'] ?? 0)) / 1048576, 1, ',', '.').' MB finora)');
        }
        $this->line('');
    }

    private function scrivibile(string $cartella): bool
    {
        if (! is_dir($cartella)) {
            @mkdir($cartella, 0775, true);
        }

        return is_dir($cartella) && is_writable($cartella);
    }

    private function riga(string $cosa, bool $bene, string $come): void
    {
        if (! $bene) {
            $this->tuttoBene = false;
        }
        $this->line('  '.($bene ? 'OK ' : 'NO ').' '.$cosa.': '.$come);
    }
}