<?php

namespace App\Support\Valutazione\Mappe;

use App\Models\Comune;
use App\Models\ComuneDato;
use App\Support\Geo\ImpostazioniGeo;
use App\Support\Geo\LettoreFibra;
use App\Support\Geo\MagazzinoGeo;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * LA RACCOLTA DEL MATERIALE DELLE MAPPE (lotto 4, D-VAL-102): prima di fare
 * il PDF, una volta per report, si legge dal magazzino geografico tutto
 * ciò che le mappe accese chiedono, il comune della casa e la fibra.
 *
 *  - Poche richieste, non una per mappa: una per l'intorno della casa (con
 *    gli edifici: copertina, vista, case vicine, vendute, le schede vicine),
 *    una per ogni mappa grande senza edifici (la zona, una mappa allargata
 *    oltre 700 m) che l'intorno non copre già, una per ogni scheda lontana,
 *    una piccolissima per il confine del comune.
 *  - Mai una mappa a metà (D-VAL-97): una risposta che non arriva, troncata
 *    o con una regione illeggibile non entra; una troncata si riprova una
 *    volta in quattro pezzi. Che cosa manca lo dice Materiale::$errori, e
 *    le mappe di quel riquadro non si disegnano.
 *  - Il comune: dal confine comunale che contiene la casa (codice ISTAT
 *    della copia: niente omonimi, D-VAL-100); di riserva nome e provincia,
 *    come la scheda degli annunci; se non si trova, nessun comune.
 *  - La fibra: dal magazzino, se è accesa per il report.
 *  - L'anteprima dall'Admin ricorda il materiale per i giorni scelti
 *    (Cache, di fabbrica 7): le anteprime dopo la prima non aspettano reesty.
 */
final class Raccolta
{
    /** Gli strati che le mappe disegnano. */
    public const STRATI = ['edifici', 'strade', 'acqua', 'corsi_acqua', 'verde', 'ferrovie', 'mare'];

    /** La chiave della cache dell'anteprima. */
    public const CACHE = 'valutazione:mappe:';

    /** Il materiale ricordato più grande, in byte (sotto i 16 MB della colonna della cache, con margine). */
    public const CACHE_MASSIMO = 12_000_000;

    /** Il mezzo lato (m) della richiesta del confine del comune. */
    public const CONFINE_M = 15.0;

    /**
     * Il materiale per le cornici date (Cornici::tutte()), coi dati del report.
     *
     * @param  array<string, array<string, mixed>>  $cornici
     * @param  array<string, mixed>  $dati  i dati puliti
     * @param  int  $giorniCache  0 = niente cache (i report veri); altrimenti per quanti giorni ricordare
     * @param  bool  $territorio  servono comune e fibra? («Il posto» accesa)
     */
    public static function per(array $cornici, array $dati, int $giorniCache = 0, bool $territorio = true, float $latoKm = 3.0): Materiale
    {
        $lat = $dati['input']['latitude'] ?? null;
        $lon = $dati['input']['longitude'] ?? null;
        if (! is_numeric($lat) || ! is_numeric($lon)) {
            return new Materiale(0.0, 0.0, [], [], ['mancano le coordinate della casa: niente mappe'], latoKm: $latoKm);
        }
        $lat = (float) $lat;
        $lon = (float) $lon;
        $richieste = self::richieste($cornici, $lat, $lon, $territorio);
        $chiave = self::CACHE.sha1((string) json_encode([$richieste, ImpostazioniGeo::regioni(), MagazzinoGeo::modoOggetti(), $territorio, (string) ($dati['input']['town'] ?? '')]));
        if ($giorniCache > 0) {
            try {
                $ricordato = Materiale::daArray(self::daCache(Cache::get($chiave)));
                if ($ricordato !== null) {
                    return $ricordato;
                }
            } catch (Throwable) {
                // senza cache si legge e basta
            }
        }

        $strati = [];
        $visti = [];
        $coperti = [];
        $errori = [];
        $edizioni = [];
        foreach ($richieste as $r) {
            $letti = self::leggi($r['riquadro'], $r['strati'], $errori, $r['perche']);
            if ($letti === null) {
                continue;
            }
            foreach ($letti['edizioni'] as $regione => $edizione) {
                $edizioni[(string) $regione] = $edizione;
            }
            foreach ($letti['strati'] as $strato => $oggetti) {
                foreach ($oggetti as $o) {
                    $chiaveOggetto = $strato.':'.($o['id'] ?? md5((string) json_encode($o['forma'] ?? null)));
                    if (isset($visti[$chiaveOggetto]) || ! is_array($o['forma'] ?? null)) {
                        continue;
                    }
                    $riquadro = Materiale::riquadroDi($o['forma']);
                    if ($riquadro === null) {
                        continue;
                    }
                    $visti[$chiaveOggetto] = true;
                    $o['r'] = $riquadro;
                    $strati[(string) $strato][] = $o;
                }
            }
            if ($r['perche'] !== 'comune') {
                $coperti[] = ['r' => $r['riquadro'], 'edifici' => in_array('edifici', $r['strati'], true)];
            }
        }

        $comune = null;
        $fibra = null;
        $fibraMotivo = null;
        $fibraEdizione = null;
        if ($territorio) {
            $comune = self::comune($strati['confini'] ?? [], $lat, $lon, $dati);
            unset($strati['confini']);
            [$fibra, $fibraMotivo, $fibraEdizione] = self::fibra($lat, $lon);
        }

        $materiale = new Materiale($lat, $lon, $strati, $coperti, array_values(array_unique($errori)), $comune, $fibra, $fibraMotivo, $fibraEdizione, $edizioni, $latoKm);
        // si ricorda solo un materiale completo: uno con errori (o la fibra non letta) si rilegge alla prossima anteprima
        if ($giorniCache > 0 && $errori === [] && ($fibra !== null || $fibraMotivo === 'spenta' || ! $territorio)) {
            try {
                $compresso = self::perCache($materiale->perArray());
                if ($compresso !== null) {
                    Cache::put($chiave, $compresso, now()->addDays($giorniCache));
                }
            } catch (Throwable) {
                // la cache è un comodo, non serve al PDF
            }
        }

        return $materiale;
    }

    /**
     * Il materiale per la cache, compresso: la cache sta nel database, in una
     * colonna da 16 MB al massimo, e gli edifici di un centro città pesano.
     * Null se nemmeno compresso ci sta (allora non si ricorda).
     */
    private static function perCache(array $materiale): ?string
    {
        $json = json_encode($materiale, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE);
        $compresso = $json === false ? false : gzdeflate($json, 6);
        if ($compresso === false) {
            return null;
        }
        $testo = base64_encode($compresso);

        return strlen($testo) <= self::CACHE_MASSIMO ? $testo : null;
    }

    /** Dalla cache al materiale in array; null se non c'è o non si legge. */
    private static function daCache(mixed $ricordato): ?array
    {
        if (! is_string($ricordato) || $ricordato === '') {
            return null;
        }
        $compresso = base64_decode($ricordato, true);
        $json = $compresso === false ? false : @gzinflate($compresso);
        $materiale = $json === false ? null : json_decode($json, true);

        return is_array($materiale) ? $materiale : null;
    }

    /**
     * Il materiale per un report già messo in Contesto (senza materiale):
     * le cornici delle mappe accese, col riquadro massimo del magazzino di
     * adesso. È il modo normale di chiamare la raccolta (anteprima, diagnosi,
     * domani la generazione).
     */
    public static function perContesto(\App\Support\Valutazione\Report\Contesto $c, int $giorniCache = 0): Materiale
    {
        $latoKm = ImpostazioniGeo::numero(ImpostazioniGeo::RIQUADRO_KM);

        return self::per($c->cornici($latoKm), $c->dati, $giorniCache, $c->posto(), $latoKm);
    }

    /**
     * Le richieste al magazzino per le cornici: [riquadro, strati, perche].
     *
     * @return list<array{riquadro: array{0: float, 1: float, 2: float, 3: float}, strati: list<string>, perche: string}>
     */
    public static function richieste(array $cornici, float $lat, float $lon, bool $territorio): array
    {
        $conEdifici = array_values(self::STRATI);
        $senzaEdifici = array_values(array_diff(self::STRATI, ['edifici']));
        $richieste = [];

        // l'intorno della casa, con gli edifici: le cornici centrate sulla casa che li disegnano
        $mezzo = 0.0;
        foreach ($cornici as $c) {
            if ($c['edifici'] && abs($c['lat'] - $lat) < 1e-9 && abs($c['lon'] - $lon) < 1e-9) {
                $r = Cornici::daLeggere($c);
                [$e1, $n1] = Geometria::metri($r[3], $r[2], $lat, $lon);
                $mezzo = max($mezzo, abs($e1), abs($n1));
            }
        }
        if ($mezzo > 0) {
            $richieste[] = ['riquadro' => Geometria::riquadro($lat, $lon, $mezzo, $mezzo), 'strati' => $conEdifici, 'perche' => 'l\'intorno della casa'];
        }
        // le altre: prima quelle con gli edifici (le schede lontane), poi le grandi senza
        $altre = $cornici;
        uasort($altre, fn ($a, $b) => [$b['edifici'], $a['raggio']] <=> [$a['edifici'], $b['raggio']]);
        foreach ($altre as $nome => $c) {
            $r = Cornici::daLeggere($c);
            $coperta = false;
            foreach ($richieste as $q) {
                if ((! $c['edifici'] || in_array('edifici', $q['strati'], true)) && self::contiene($q['riquadro'], $r)) {
                    $coperta = true;

                    break;
                }
            }
            if (! $coperta) {
                $richieste[] = ['riquadro' => $r, 'strati' => $c['edifici'] ? $conEdifici : $senzaEdifici, 'perche' => self::perche((string) $nome)];
            }
        }
        if ($territorio) {
            $richieste[] = ['riquadro' => Geometria::riquadro($lat, $lon, self::CONFINE_M, self::CONFINE_M), 'strati' => ['confini'], 'perche' => 'comune'];
        }

        return $richieste;
    }

    /** Il riquadro $a contiene $b? */
    public static function contiene(array $a, array $b): bool
    {
        return $a[0] <= $b[0] + 1e-9 && $a[1] <= $b[1] + 1e-9 && $a[2] >= $b[2] - 1e-9 && $a[3] >= $b[3] - 1e-9;
    }

    private static function perche(string $nome): string
    {
        return match (true) {
            $nome === 'zona' => 'la mappa della zona',
            $nome === 'vicine' => 'la mappa delle case vicine',
            $nome === 'vendute' => 'la mappa delle vendite',
            $nome === 'copertina' => 'la mappa della copertina',
            $nome === 'vista' => 'la vista dall\'alto',
            str_starts_with($nome, 'scheda_') => 'la mappa della scheda '.substr($nome, 7),
            default => $nome,
        };
    }

    /**
     * Una richiesta al magazzino, intera: null (con l'errore) se non arriva,
     * se è troncata anche in quattro pezzi o se una regione non si legge.
     *
     * @param  list<string>  $errori
     * @return array{strati: array<string, list<array>>, edizioni: array<string, string|null>}|null
     */
    private static function leggi(array $riquadro, array $strati, array &$errori, string $perche, bool $aPezzi = false): ?array
    {
        $esito = MagazzinoGeo::oggetti($riquadro, $strati);
        $motivo = MagazzinoGeo::$ultimoMotivo;
        if ($esito === null) {
            $errori[] = 'Il magazzino geografico non ha dato '.($perche === 'comune' ? 'il confine del comune' : 'i dati per '.$perche).': '.($motivo ?? 'motivo non registrato').'.';

            return null;
        }
        if ($motivo !== null && str_starts_with($motivo, 'lettura parziale')) {
            $errori[] = 'Il magazzino geografico ha letto solo una parte dei dati per '.$perche.' ('.$motivo.'): la mappa non si disegna a metà.';

            return null;
        }
        if (! empty($esito['troncato'])) {
            if ($aPezzi) {
                $errori[] = 'Troppi oggetti per '.$perche.' anche a pezzi: la mappa non si disegna a metà.';

                return null;
            }
            [$o, $s, $e, $n] = $riquadro;
            $mx = ($o + $e) / 2;
            $my = ($s + $n) / 2;
            $insieme = ['strati' => [], 'edizioni' => []];
            foreach ([[$o, $s, $mx, $my], [$mx, $s, $e, $my], [$o, $my, $mx, $n], [$mx, $my, $e, $n]] as $pezzo) {
                $parte = self::leggi($pezzo, $strati, $errori, $perche, true);
                if ($parte === null) {
                    return null;
                }
                foreach ($parte['strati'] as $strato => $oggetti) {
                    $insieme['strati'][$strato] = [...($insieme['strati'][$strato] ?? []), ...$oggetti];
                }
                $insieme['edizioni'] += $parte['edizioni'];
            }

            return $insieme;
        }

        return ['strati' => is_array($esito['strati'] ?? null) ? $esito['strati'] : [], 'edizioni' => is_array($esito['edizioni'] ?? null) ? $esito['edizioni'] : []];
    }

    /**
     * Il comune della casa coi suoi dati ufficiali, o null.
     *
     * @param  list<array<string, mixed>>  $confini
     * @return array<string, mixed>|null
     */
    public static function comune(array $confini, float $lat, float $lon, array $dati): ?array
    {
        $trovato = null;
        $come = null;
        try {
            // il confine che contiene la casa (il più piccolo, se più d'uno)
            $candidati = [];
            foreach ($confini as $c) {
                $istat = trim((string) ($c['istat'] ?? ''));
                if (preg_match('/^\d{6}$/', $istat) !== 1) {
                    continue;
                }
                foreach (Geometria::poligoni($c['forma'] ?? null) as $anelli) {
                    if (Geometria::dentroPoligono([$lon, $lat], $anelli)) {
                        $candidati[] = [abs(Geometria::areaConSegno($anelli[0])), $istat];
                    }
                }
            }
            sort($candidati);
            foreach ($candidati as [, $istat]) {
                $trovato = Comune::query()->where('codice_istat', $istat)->first();
                if ($trovato !== null) {
                    $come = 'confine';

                    break;
                }
            }
            if ($trovato === null) {
                $nome = trim((string) ($dati['input']['town'] ?? ''));
                $comuni = $nome !== '' ? Comune::perNome($nome) : collect();
                if ($comuni->count() === 1) {
                    $trovato = $comuni->first();
                } elseif ($comuni->count() > 1) {
                    $sigla = mb_strtoupper(trim((string) ($dati['input']['province'] ?? '')), 'UTF-8');
                    $stessi = $comuni->filter(fn ($c) => $sigla !== '' && mb_strtoupper((string) ($c->provincia_sigla ?? ''), 'UTF-8') === $sigla);
                    $trovato = $stessi->count() === 1 ? $stessi->first() : null;
                }
                $come = $trovato !== null ? 'nome' : null;
            }
            if ($trovato === null) {
                return null;
            }
            $dato = ComuneDato::perCodice((string) $trovato->codice_istat);

            return [
                'istat' => (string) $trovato->codice_istat,
                'nome' => (string) $trovato->nome,
                'provincia' => (string) ($trovato->provincia_sigla ?? ''),
                'come' => $come,
                'zona_sismica' => $dato?->zona_sismica !== null ? trim((string) $dato->zona_sismica) : null,
                'idr_pop_p2' => $dato?->idr_pop_p2 !== null ? (float) $dato->idr_pop_p2 : null,
                'frane_pop_p3p4' => $dato?->frane_pop_p3p4 !== null ? (float) $dato->frane_pop_p3p4 : null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * La fibra nel punto: [risposta, motivo se manca, edizione].
     *
     * @return array{0: ?array, 1: ?string, 2: ?string}
     */
    private static function fibra(float $lat, float $lon): array
    {
        try {
            if (! MagazzinoGeo::fibraAccesa('report')) {
                return [null, 'spenta', null];
            }
            $esito = MagazzinoGeo::fibra($lat, $lon, LettoreFibra::RAGGIO, 'report');
            if ($esito === null) {
                return [null, MagazzinoGeo::$ultimoMotivo ?? 'lettura non riuscita', null];
            }
            $edizione = null;
            if (MagazzinoGeo::modoFibra() === 'locale') {
                $edizione = trim((string) config('nascosti.fibra_punto.edizione', '')) ?: null;
            } else {
                $stato = MagazzinoGeo::stato(true);
                $edizione = $stato['remoto']['dati']['file']['fibra']['edizione'] ?? null;
            }

            return [$esito, null, is_string($edizione) && $edizione !== '' ? $edizione : (trim((string) config('nascosti.fibra_punto.edizione', '')) ?: null)];
        } catch (Throwable $e) {
            return [null, 'lettura non riuscita ('.$e->getMessage().')', null];
        }
    }
}