<?php

namespace App\Support\Valutazione\Report;

use App\Support\Valutazione\Cifre;
use App\Support\Valutazione\Disposizione;
use App\Support\Valutazione\Formato;
use App\Support\Valutazione\Guardia;
use App\Support\Valutazione\Mappe\Casa;
use App\Support\Valutazione\Mappe\Cornici;
use App\Support\Valutazione\Mappe\Geometria;
use App\Support\Valutazione\Mappe\ImpostazioniMappe;
use App\Support\Valutazione\Mappe\Materiale;
use App\Support\Valutazione\Mappe\Piana;
use App\Support\Valutazione\Mappe\Vista;
use App\Support\Valutazione\Segnaposti;
use App\Support\Valutazione\Testi;

/**
 * TUTTO CIÒ CHE UNA PAGINA DEL REPORT DEVE SAPERE: i dati puliti, i numeri
 * (Cifre), la fotografia di testi e impostazioni, le pagine accese coi loro
 * numeri e i valori dei segnaposti comuni. Le pagine leggono solo da qui.
 *
 * Dal lotto 2:
 *  - annunci e vendite sono già tagliati a quanti ne vuole l'Admin
 *    (D-VAL-57): lo stesso numero vale in tutte le pagine, «In breve»
 *    compresa; quelli senza via, prezzo e superficie si tolgono prima;
 *  - una pagina può occupare più fogli (le schede, tre per foglio) o
 *    nessuno (senza dati non esce, D-VAL-55): numeri di pagina, capitoli e
 *    indice contano i fogli veri;
 *  - le foto degli annunci arrivano da fuori (numero dell'annuncio, da 0 =>
 *    file): nell'anteprima le illustrazioni d'esempio, nei report veri
 *    quelle scaricate dalla generazione (lotto 6).
 *
 * Dal lotto 4:
 *  - il materiale delle mappe arriva da fuori, già letto (Mappe\Raccolta):
 *    mentre si compone il PDF non si chiama nessuno;
 *  - le mappe e la vista si disegnano una volta sola e si ricordano qui
 *    (una pagina chiede prima quanti fogli occupa, poi l'HTML);
 *  - si ricordano anche i nomi di luogo stampati (indirizzi, vie sulle
 *    mappe), per la guardia (D-VAL-105 A), e gli avvisi delle mappe che
 *    non si sono potute disegnare.
 */
final class Contesto
{
    /** @var array<string, mixed> i dati puliti, con annunci e vendite già tagliati */
    public readonly array $dati;

    public readonly Cifre $cifre;

    /** @var array<string, int> pagina => numero del suo primo foglio, nell'ordine */
    public readonly array $numeri;

    /** @var array<string, int> pagina => quanti fogli occupa */
    public readonly array $fogli;

    /** @var array<string, int> pagina => numero del capitolo */
    public readonly array $capitoli;

    /** @var array<string, array<string, mixed>>|null le cornici delle mappe, calcolate una volta */
    private ?array $cornici = null;

    /** @var array<string, string|null> le mappe già disegnate */
    private array $disegnate = [];

    /** @var array{svg: string, casa: ?string, via: array}|false|null la vista già disegnata (false: non ancora) */
    private array|false|null $vista = false;

    /** @var list<string> i nomi delle vie scritti sulle mappe */
    private array $nomiDisegnati = [];

    /** @var list<string> le mappe che non si sono potute disegnare, in parole nostre */
    private array $avvisiMappe = [];

    private ?array $impostazioniMappe = null;

    /**
     * @param  array<string, mixed>  $dati  i dati puliti (Stima\Normalizza)
     * @param  array<string, mixed>  $fotografia  testi e impostazioni (Fotografia)
     * @param  array<int, string>  $foto  numero dell'annuncio (da 0) => file della foto
     */
    public function __construct(array $dati, public readonly array $fotografia, public readonly bool $anteprima = false, public readonly array $foto = [], public readonly ?Materiale $mappe = null)
    {
        $disposizione = $this->disposizione();
        // via quelli senza niente da mostrare (D-VAL-55); l'annuncio ricorda il suo numero nei dati, per la foto
        $annunci = [];
        foreach (array_values($dati['comparables'] ?? []) as $i => $a) {
            if (is_array($a) && Confronti::utile($a, true)) {
                $annunci[] = ['_indice' => $i] + $a;
            }
        }
        $vendite = array_values(array_filter(array_values($dati['short'] ?? []), fn ($v): bool => is_array($v) && Confronti::utile($v, false)));
        $dati['comparables'] = array_slice($annunci, 0, $disposizione['annunci']);
        $dati['short'] = array_slice($vendite, 0, $disposizione['vendite']);
        $this->dati = $dati;
        $this->cifre = new Cifre($dati);
        $numeri = [];
        $fogli = [];
        $capitoli = [];
        $n = 0;
        $k = 0;
        foreach (Pagine::accese($fotografia) as $chiave) {
            $classe = Pagine::ELENCO[$chiave]['classe'];
            // le pagine del lotto 1 hanno sempre un foglio; le altre dicono quanti
            $quanti = method_exists($classe, 'fogli') ? $classe::fogli($this) : 1;
            if ($quanti < 1) {
                continue;
            }
            $numeri[$chiave] = $n + 1;
            $fogli[$chiave] = $quanti;
            $n += $quanti;
            if (Pagine::ELENCO[$chiave]['capitolo']) {
                $capitoli[$chiave] = ++$k;
            }
        }
        $this->numeri = $numeri;
        $this->fogli = $fogli;
        $this->capitoli = $capitoli;
    }

    /** Le scelte delle mappe della fotografia (una fotografia vecchia vale la fabbrica). */
    public function impostazioniMappe(): array
    {
        return $this->impostazioniMappe ??= ImpostazioniMappe::normalizza($this->fotografia['mappe'] ?? null);
    }

    /** La pagina «Il posto» è accesa? (il territorio e la fibra servono solo lì) */
    public function posto(): bool
    {
        return in_array('posto', Pagine::accese($this->fotografia), true);
    }

    /**
     * Quali mappe sono accese: pagina accesa e riquadro acceso.
     *
     * @return array<string, bool>
     */
    public function mappeAccese(): array
    {
        $pagine = Pagine::accese($this->fotografia);
        $su = fn (string $pagina, string $riquadro): bool => in_array($pagina, $pagine, true) && $this->riquadro($riquadro);

        return [
            'copertina' => $this->riquadro('cop_mappa'),
            'vista' => $su('posto', 'posto_vista'),
            'zona' => $su('zona', 'zona_mappa'),
            'vicine' => $su('vicine', 'vicine_mappa'),
            'schede' => $su('schede', 'schede_mappa'),
            'vendute' => $su('vendute', 'vendute_mappa'),
        ];
    }

    /**
     * Le cornici delle mappe accese (Mappe\Cornici), coi dati già tagliati.
     * Il riquadro massimo è quello con cui si è letto il materiale.
     *
     * @return array<string, array<string, mixed>>
     */
    public function cornici(?float $latoKm = null): array
    {
        if ($latoKm !== null) {
            return Cornici::tutte($this->dati, $this->impostazioniMappe(), $this->mappeAccese(), $latoKm);
        }

        return $this->cornici ??= Cornici::tutte($this->dati, $this->impostazioniMappe(), $this->mappeAccese(), $this->mappe?->latoKm ?? 3.0);
    }

    /**
     * La mappa piana di una cornice (copertina, zona, vicine, vendute,
     * scheda_N) come SVG, o null se non si può disegnare (manca il
     * materiale, o non copre: mai una mappa a metà).
     */
    public function mappa(string $chiave, float $raggioAngoli = 3.0): ?string
    {
        if (array_key_exists($chiave, $this->disegnate)) {
            return $this->disegnate[$chiave];
        }
        $cornice = $this->cornici()[$chiave] ?? null;
        $svg = null;
        if ($cornice !== null && $this->mappe !== null) {
            $svg = Piana::svg($cornice, $this->mappe, $this->impostazioniMappe()['colori'], ['crediti' => $this->etichetta('mappe_crediti')], fn (string $nome): bool => Guardia::haParolaSegreta($nome), $raggioAngoli);
            $this->nomiDisegnati = [...$this->nomiDisegnati, ...Piana::$ultimiNomi];
        }
        if ($cornice !== null && $svg === null) {
            $nome = str_starts_with($chiave, 'scheda_') ? 'della scheda '.substr($chiave, 7) : ['copertina' => 'della copertina', 'zona' => 'della zona', 'vicine' => 'delle case vicine', 'vendute' => 'delle vendite'][$chiave] ?? $chiave;
            $this->avvisiMappe[] = 'La mappa '.$nome.' («'.$chiave.'») non è uscita: '.($this->mappe === null ? 'mancano i dati delle mappe' : 'il magazzino geografico non ha dato i dati per intero (vedi sopra)').'.';
        }

        return $this->disegnate[$chiave] = $svg;
    }

    /**
     * La vista dall'alto: svg, che cosa è colorato (edificio, isolato o
     * null) e la via dell'indirizzo; null se non c'è (niente materiale,
     * niente edifici).
     *
     * @return array{svg: string, casa: ?string, via: array}|null
     */
    public function vista(): ?array
    {
        if ($this->vista !== false) {
            return $this->vista;
        }
        $cornice = $this->cornici()['vista'] ?? null;
        $vista = null;
        if ($cornice !== null && $this->mappe !== null) {
            $vista = Vista::disegna($cornice, $this->mappe, $this->impostazioniMappe(), (string) ($this->dati['input']['street'] ?? ''),
                ['crediti' => $this->etichetta('mappe_crediti_vista'), 'nord' => $this->etichetta('mappe_nord')], fn (string $nome): bool => Guardia::haParolaSegreta($nome));
            if ($vista !== null) {
                $this->nomiDisegnati = [...$this->nomiDisegnati, ...$vista['nomi']];
            }
        }
        if ($cornice !== null && $vista === null && $this->mappe !== null && ! $this->mappe->copre(Geometria::riquadro($cornice['lat'], $cornice['lon'], $cornice['lettura'], $cornice['lettura']), true)) {
            $this->avvisiMappe[] = 'La vista dall\'alto non è uscita: il magazzino geografico non ha dato i dati per intero.';
        }

        return $this->vista = $vista;
    }

    /**
     * La mappa (o la vista) di un riquadro esce davvero? Per le fonti del
     * metodo: una mappa che non è uscita non si cita. Un riquadro che non
     * è una mappa: sempre sì.
     */
    public function mappaUscita(string $riquadro): bool
    {
        return match ($riquadro) {
            'cop_mappa' => $this->mappa('copertina') !== null,
            'zona_mappa' => $this->mappa('zona') !== null,
            'vicine_mappa' => $this->mappa('vicine') !== null,
            'vendute_mappa' => $this->mappa('vendute') !== null,
            'schede_mappa' => array_filter(Confronti::annunci($this), fn (array $a): bool => $this->mappa('scheda_'.$a['numero']) !== null) !== [],
            'posto_vista' => $this->vista() !== null,
            default => true,
        };
    }

    /**
     * Il controllo della casa sulla mappa (Mappe\Casa::controllo): edificio,
     * via dell'indirizzo, verdetto; null senza materiale che copra la casa.
     *
     * @return array<string, mixed>|null
     */
    public function controllo(): ?array
    {
        if ($this->mappe === null || ! is_numeric($this->dati['input']['latitude'] ?? null) || ! is_numeric($this->dati['input']['longitude'] ?? null)) {
            return null;
        }

        return Casa::controllo($this->mappe, (float) $this->dati['input']['latitude'], (float) $this->dati['input']['longitude'], (string) ($this->dati['input']['street'] ?? ''), $this->impostazioniMappe());
    }

    /**
     * I nomi di luogo stampati nel report (D-VAL-105 A): l'indirizzo e il
     * comune della casa, gli indirizzi di annunci e vendite, le vie scritte
     * sulle mappe. La guardia non conta le parole generiche dentro di loro.
     *
     * @return list<string>
     */
    public function luoghi(): array
    {
        $i = $this->dati['input'];
        $luoghi = [(string) ($i['street'] ?? ''), (string) ($i['town'] ?? ''), (string) ($this->dati['omi']['zona_descr'] ?? '')];
        foreach ($this->dati['comparables'] ?? [] as $a) {
            $luoghi = [...$luoghi, ...array_map('trim', explode(',', (string) ($a['indirizzo_pubblicita'] ?? '')))];
        }
        foreach ($this->dati['short'] ?? [] as $v) {
            $luoghi[] = (string) ($v['address'] ?? '');
            $luoghi[] = Formato::titolo((string) ($v['address'] ?? ''));
            $luoghi[] = (string) ($v['town'] ?? '');
        }
        $luoghi = [...$luoghi, ...$this->nomiDisegnati];

        return array_values(array_unique(array_filter(array_map('trim', $luoghi), fn (string $l): bool => mb_strlen($l) >= 3)));
    }

    /**
     * Che cosa non è uscito delle mappe, in parole nostre: gli errori della
     * raccolta e le mappe che non si sono potute disegnare.
     *
     * @return list<string>
     */
    public function avvisiMappe(): array
    {
        return array_values(array_unique([...($this->mappe?->errori ?? []), ...$this->avvisiMappe]));
    }

    /** Quante pagine ha il report. */
    public function totalePagine(): int
    {
        return array_sum($this->fogli);
    }

    /**
     * Quanti annunci e vendite, righe del voce per voce, lunghezza delle
     * descrizioni (D-VAL-57, D-VAL-59); una fotografia del lotto 1 non li ha
     * e vale la fabbrica.
     *
     * @return array{annunci: int, vendite: int, righe_annunci: list<string>, righe_vendite: list<string>, descrizione: int}
     */
    public function disposizione(): array
    {
        $d = is_array($this->fotografia['disposizione'] ?? null) ? $this->fotografia['disposizione'] : [];
        $intero = fn (mixed $v, int $min, int $max, int $fabbrica): int => is_int($v) && $v >= $min && $v <= $max ? $v : $fabbrica;
        $righe = function (mixed $v, array $possibili): array {
            $buone = is_array($v) ? array_values(array_unique(array_filter($v, fn ($r): bool => in_array($r, $possibili, true)))) : [];

            return $buone === [] ? $possibili : $buone;
        };

        return [
            'annunci' => $intero($d['annunci'] ?? null, Disposizione::QUANTI_MINIMO, Disposizione::QUANTI_MASSIMO, Disposizione::QUANTI_MASSIMO),
            'vendite' => $intero($d['vendite'] ?? null, Disposizione::QUANTI_MINIMO, Disposizione::QUANTI_MASSIMO, Disposizione::QUANTI_MASSIMO),
            'righe_annunci' => $righe($d['righe_annunci'] ?? null, Disposizione::VOCI_ANNUNCI),
            'righe_vendite' => $righe($d['righe_vendite'] ?? null, Disposizione::VOCI_VENDITE),
            'descrizione' => $intero($d['descrizione'] ?? null, Disposizione::DESCRIZIONE_MINIMO, Disposizione::DESCRIZIONE_MASSIMO, Disposizione::DESCRIZIONE_FABBRICA),
        ];
    }

    /**
     * Un testo dell'Admin in chiaro (per i disegni SVG, che lo proteggono da
     * sé), coi segnaposti al loro posto; null se manca un valore.
     *
     * @param  array<string, string|null>  $valori
     */
    public function chiaro(string $nome, array $valori = []): ?string
    {
        return Segnaposti::testo($this->grezzo($nome), [...$this->comuni(), ...$valori]);
    }

    /** Come chiaro(), ma un valore che manca dà una stringa vuota (le etichette dei disegni). */
    public function etichetta(string $nome, array $valori = []): string
    {
        return $this->chiaro($nome, $valori) ?? '';
    }

    /** Il riquadro è acceso? */
    public function riquadro(string $nome): bool
    {
        return (bool) ($this->fotografia['riquadri'][$nome] ?? false);
    }

    /** Un'impostazione della fotografia. */
    public function impostazione(string $chiave): string
    {
        return (string) ($this->fotografia['impostazioni'][$chiave] ?? '');
    }

    /** Il testo grezzo dell'Admin, così com'è scritto. */
    public function grezzo(string $nome): string
    {
        return (string) ($this->fotografia['testi'][$nome] ?? Testi::TESTI[$nome][2] ?? '');
    }

    /**
     * Un testo dell'Admin in HTML, coi segnaposti al loro posto; null se
     * manca un valore che il testo usa (allora non si stampa).
     *
     * @param  array<string, string|null>  $valori  i segnaposti propri del testo
     */
    public function testo(string $nome, array $valori = []): ?string
    {
        return Segnaposti::html($this->grezzo($nome), [...$this->comuni(), ...$valori], Testi::TESTI[$nome][4] ?? []);
    }

    /**
     * Un testo qualunque (un'avvertenza, un'impostazione) in HTML, coi
     * segnaposti comuni.
     *
     * @param  list<string>  $grassetto
     */
    public function libero(string $testo, array $grassetto = []): ?string
    {
        return Segnaposti::html($testo, $this->comuni(), $grassetto);
    }

    /**
     * I segnaposti che valgono in ogni testo.
     *
     * @return array<string, string|null>
     */
    public function comuni(): array
    {
        $input = $this->dati['input'];
        $indirizzo = trim(((string) $input['street']).' '.((string) ($input['house_number'] ?? '')));

        return [
            'anno' => $this->cifre->data()->format('Y'),
            'titolare' => $this->impostazione('valutazione_titolare_nome'),
            'indirizzo' => $indirizzo,
            'comune' => (string) $input['town'],
            'codice' => $this->codice(),
            'data' => Formato::data($this->cifre->data()),
            'email' => (string) ($this->fotografia['testi']['contatti_email'] ?? Testi::TESTI['contatti_email'][2]),
            'sito' => (string) ($this->fotografia['testi']['contatti_sito'] ?? Testi::TESTI['contatti_sito'][2]),
        ];
    }

    /** Il codice del report: nei dati è lo «sku» (in produzione il numero della valutazione). */
    public function codice(): string
    {
        return (string) ($this->dati['input']['sku'] ?? '—');
    }

    /** «10126 Torino (TO)». */
    public function citta(): string
    {
        $i = $this->dati['input'];
        $citta = trim(((string) ($i['zip'] ?? '')).' '.((string) $i['town']));

        return $citta.(($i['province'] ?? null) !== null ? ' ('.$i['province'].')' : '');
    }
}