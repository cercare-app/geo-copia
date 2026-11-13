<?php

namespace App\Support\Valutazione\Mappe;

use App\Models\Setting;
use App\Support\Permessi;
use App\Support\Valutazione\Versioni;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * «VALUTAZIONE · MAPPE E TERRITORIO» (lotto 4, D-VAL-101): le scelte delle
 * mappe del report, della vista dall'alto, dei colori, dei controlli della
 * casa sulla mappa e del territorio.
 *
 *  - Ogni valore ha la sua chiave (valutazione_mappe_…) e si salva solo se
 *    è diverso dalla fabbrica: di fabbrica il report esce come il
 *    fac-simile senza toccare nulla.
 *  - Lo cambia chi ha la casella «Valutazione · Mappe e territorio»; ogni
 *    salvataggio diventa una versione (Versioni), come i testi.
 *  - Il report non legge mai di qui direttamente: legge la fotografia
 *    (normalizza() rimette in riga una fotografia vecchia o incompleta).
 *  - Le medie italiane e la fascia «vicino alla media» del territorio NON
 *    sono qui: restano quelle della scheda degli annunci (D-VAL-101
 *    rivista), così scheda e report non si contraddicono.
 */
final class ImpostazioniMappe
{
    public const PREFISSO = 'valutazione_mappe_';

    /** La casella dei ruoli. */
    public const PERMESSO = 'valutazione_mappe';

    /** Le mappe del report: chiave => nome. */
    public const MAPPE = [
        'copertina' => 'Copertina',
        'zona' => 'La zona',
        'vicine' => 'Le case vicine',
        'schede' => 'Le schede degli annunci',
        'vendute' => 'Le case vendute',
    ];

    /**
     * Le scelte di ogni mappa, di fabbrica come il fac-simile REV0. Solo
     * quelle elencate in CAMPI_MAPPA[mappa] si vedono e si cambiano.
     *  - raggio: metri dal centro al bordo, in larghezza;
     *  - allarga / raggio_max: la mappa si allarga da sola per contenere
     *    tutte le case (la zona: il suo poligono), fino a raggio_max;
     *  - nomi / nomi_max: i nomi delle vie e quanti al massimo;
     *  - anelli: i cerchi delle distanze; scala: la barra della scala.
     */
    public const FABBRICA_MAPPE = [
        'copertina' => ['raggio' => 330, 'allarga' => false, 'raggio_max' => 330, 'nomi' => true, 'nomi_max' => 14, 'anelli' => false, 'scala' => false],
        'zona' => ['raggio' => 1150, 'allarga' => true, 'raggio_max' => 1500, 'nomi' => true, 'nomi_max' => 10, 'anelli' => false, 'scala' => true],
        'vicine' => ['raggio' => 330, 'allarga' => true, 'raggio_max' => 1500, 'nomi' => true, 'nomi_max' => 14, 'anelli' => true, 'scala' => true],
        'schede' => ['raggio' => 150, 'allarga' => false, 'raggio_max' => 150, 'nomi' => true, 'nomi_max' => 3, 'anelli' => false, 'scala' => false],
        'vendute' => ['raggio' => 300, 'allarga' => true, 'raggio_max' => 1500, 'nomi' => true, 'nomi_max' => 14, 'anelli' => true, 'scala' => true],
    ];

    public const CAMPI_MAPPA = [
        'copertina' => ['raggio', 'nomi', 'nomi_max'],
        'zona' => ['raggio', 'raggio_max', 'nomi', 'nomi_max', 'scala'],
        'vicine' => ['raggio', 'allarga', 'raggio_max', 'nomi', 'nomi_max', 'anelli', 'scala'],
        'schede' => ['raggio', 'nomi', 'nomi_max'],
        'vendute' => ['raggio', 'allarga', 'raggio_max', 'nomi', 'nomi_max', 'anelli', 'scala'],
    ];

    /** I numeri delle mappe: campo => [etichetta, minimo, massimo, unità]. */
    public const NUMERI_MAPPA = [
        'raggio' => ['Raggio', 50, 2000, 'm'],
        'raggio_max' => ['Fin dove si può allargare', 50, 2000, 'm'],
        'nomi_max' => ['Nomi delle vie al massimo', 0, 30, ''],
    ];

    public const SI_NO_MAPPA = [
        'allarga' => 'Si allarga per contenere tutte le case',
        'nomi' => 'Nomi delle vie',
        'anelli' => 'Cerchi delle distanze',
        'scala' => 'Barra della scala',
    ];

    /** La vista dall'alto: campo => [etichetta, fabbrica, minimo, massimo, unità]. */
    public const VISTA = [
        'larghezza' => ['Larghezza della vista', 300, 100, 1000, 'm'],
        'inclinazione' => ['Inclinazione', 40, 20, 70, '°'],
        'metri_piano' => ['Altezza di un piano', 3.2, 2.5, 4.5, 'm'],
        'piani_riserva' => ['Piani quando nei dintorni nessun edificio li dice', 4, 1, 20, ''],
        'nomi_max' => ['Nomi delle vie al massimo', 6, 0, 12, ''],
    ];

    /** I colori: campo => [etichetta, fabbrica]. Quelli del fac-simile (mappe.py e vista.py). */
    public const COLORI = [
        'fondo' => ['Fondo', '#F4F6FA'],
        'edifici' => ['Edifici', '#E3E8F0'],
        'verde' => ['Verde e parchi', '#D8EFDF'],
        'acqua' => ['Acqua e mare', '#CFE0FA'],
        'fiumi' => ['Fiumi e canali', '#B9D2F7'],
        'strade' => ['Strade', '#FFFFFF'],
        'bordi' => ['Bordo delle strade', '#D3DBE7'],
        'bordi_grandi' => ['Bordo delle strade grandi', '#BFCBDD'],
        'ferrovie' => ['Ferrovie', '#B4BDCB'],
        'nomi' => ['Nomi delle vie', '#6B7588'],
        'casa' => ['Segnaposto della casa', '#1E3A8A'],
        'alone' => ['Alone e edificio della casa', '#22A354'],
        'annunci' => ['Annunci in vendita', '#2563EB'],
        'vendite' => ['Case vendute', '#22A354'],
        'zona' => ['Confini della zona', '#22A354'],
        'tetti' => ['Vista: tetti', '#EEF1F6'],
        'muri_chiari' => ['Vista: muri al sole', '#CBD4E1'],
        'muri_scuri' => ['Vista: muri in ombra', '#AEBBCD'],
        'casa_tetto' => ['Vista: tetto della casa', '#BFE6CB'],
        'casa_muri_chiari' => ['Vista: muri della casa al sole', '#7CCB97'],
        'casa_muri_scuri' => ['Vista: muri della casa in ombra', '#4FAF72'],
    ];

    /** I controlli della casa sulla mappa: campo => [etichetta, fabbrica, minimo, massimo, unità]. */
    public const CONTROLLI = [
        'via_m' => ['Distanza massima dalla via dell\'indirizzo', 30, 5, 200, 'm'],
        'edificio_m' => ['Edificio della casa: il più vicino entro', 20, 0, 100, 'm'],
        'isolato_m2' => ['Oltre questa superficie è un isolato', 3000, 500, 100000, 'm²'],
    ];

    /**
     * L'anteprima dell'Admin: per quanti giorni ricorda i dati dell'esempio
     * per le mappe (D-VAL-102), così le anteprime dopo la prima non aspettano
     * il magazzino geografico. 0 = non ricorda: ogni anteprima li richiede.
     * Campo => [etichetta, fabbrica, minimo, massimo, unità].
     */
    public const ANTEPRIMA = [
        'giorni' => ['Per quanti giorni l\'anteprima ricorda i dati dell\'esempio', 7, 0, 30, 'giorni'],
    ];

    /** Le abbreviazioni sciolte nel nome della via, di fabbrica (D-VAL-99). «S.» vale per San, Santo, Santa e Sant' (a parte). */
    public const ABBREVIAZIONI = "C.so = Corso\nP.za = Piazza\nP.zza = Piazza\nV.le = Viale\nP.le = Piazzale\nL.go = Largo\nStr. = Strada\nB.go = Borgo\nFraz. = Frazione\nV. = Via\nVc. = Vicolo\nVic. = Vicolo\nLoc. = Località\nC.da = Contrada\nL.re = Lungarno\nL.mare = Lungomare\nS.da = Strada\nS.S. = Strada Statale";

    /** Il territorio di «Il posto»: casella => etichetta (tutte accese di fabbrica). */
    public const TERRITORIO = [
        'sismica' => 'Zona sismica',
        'alluvioni' => 'Alluvioni',
        'frane' => 'Frane',
        'fibra' => 'Fibra nel punto della casa',
    ];

    /** Al massimo quante righe di abbreviazioni. */
    public const ABBREVIAZIONI_MASSIMO = 80;

    /**
     * Tutto, come vale oggi.
     *
     * @return array{mappe: array<string, array<string, int|bool>>, vista: array<string, float|int>, colori: array<string, string>, controlli: array<string, int>, anteprima: array<string, int>, abbreviazioni: string, territorio: array<string, bool>}
     */
    public static function tutte(): array
    {
        $grezzo = [];
        foreach (self::chiavi() as $chiave) {
            $v = Setting::get($chiave, '');
            if ($v !== null && $v !== '') {
                $grezzo[$chiave] = (string) $v;
            }
        }

        return self::daChiavi($grezzo);
    }

    /**
     * Una fotografia (anche vecchia, senza le mappe) rimessa in riga: ciò che
     * manca o è fuori dai limiti vale la fabbrica.
     *
     * @return array{mappe: array<string, array<string, int|bool>>, vista: array<string, float|int>, colori: array<string, string>, controlli: array<string, int>, anteprima: array<string, int>, abbreviazioni: string, territorio: array<string, bool>}
     */
    public static function normalizza(mixed $salvate): array
    {
        $s = is_array($salvate) ? $salvate : [];
        $chiavi = [];
        foreach (self::MAPPE as $mappa => $nome) {
            foreach (self::FABBRICA_MAPPE[$mappa] as $campo => $fabbrica) {
                $v = $s['mappe'][$mappa][$campo] ?? null;
                if ($v !== null) {
                    $chiavi[self::chiaveMappa($mappa, $campo)] = is_bool($v) ? ($v ? '1' : '0') : (string) $v;
                }
            }
        }
        foreach (['vista' => self::VISTA, 'controlli' => self::CONTROLLI, 'anteprima' => self::ANTEPRIMA, 'colori' => self::COLORI, 'territorio' => self::TERRITORIO] as $gruppo => $definizioni) {
            foreach ($definizioni as $campo => $x) {
                $v = $s[$gruppo][$campo] ?? null;
                if ($v !== null) {
                    $chiavi[self::PREFISSO.$gruppo.'_'.$campo] = is_bool($v) ? ($v ? '1' : '0') : (string) $v;
                }
            }
        }
        if (isset($s['abbreviazioni']) && is_string($s['abbreviazioni'])) {
            $chiavi[self::PREFISSO.'abbreviazioni'] = $s['abbreviazioni'];
        }

        return self::daChiavi($chiavi);
    }

    /**
     * Le chiavi delle impostazioni (per la cache e per Sistema → Impostazioni, che non le elenca).
     *
     * @return list<string>
     */
    public static function chiavi(): array
    {
        $chiavi = [];
        foreach (self::MAPPE as $mappa => $nome) {
            foreach (array_keys(self::FABBRICA_MAPPE[$mappa]) as $campo) {
                $chiavi[] = self::chiaveMappa($mappa, $campo);
            }
        }
        foreach (['vista' => self::VISTA, 'controlli' => self::CONTROLLI, 'anteprima' => self::ANTEPRIMA, 'colori' => self::COLORI, 'territorio' => self::TERRITORIO] as $gruppo => $definizioni) {
            foreach (array_keys($definizioni) as $campo) {
                $chiavi[] = self::PREFISSO.$gruppo.'_'.$campo;
            }
        }
        $chiavi[] = self::PREFISSO.'abbreviazioni';

        return $chiavi;
    }

    public static function chiaveMappa(string $mappa, string $campo): string
    {
        return self::PREFISSO.$mappa.'_'.$campo;
    }

    /**
     * Le abbreviazioni come coppie [abbreviazione, parola intera], dal testo
     * «C.so = Corso» (una per riga). Le righe che non tornano si saltano.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function coppie(string $testo): array
    {
        $coppie = [];
        foreach (preg_split('/\R/u', $testo) ?: [] as $riga) {
            $parti = explode('=', $riga, 2);
            if (count($parti) !== 2) {
                continue;
            }
            $corta = trim($parti[0]);
            $lunga = trim($parti[1]);
            if ($corta !== '' && $lunga !== '' && mb_strlen($corta) <= 20 && mb_strlen($lunga) <= 40) {
                $coppie[] = [$corta, $lunga];
            }
        }

        return $coppie;
    }

    /**
     * Salva tutto, SOLO se cambiato e solo se tutto torna (tutto o niente).
     * Ritorna i nomi di ciò che è cambiato.
     *
     * @param  array<string, mixed>  $stato  chiave dell'impostazione => valore scritto nel modulo
     * @return list<string>
     */
    public static function scrivi(mixed $chi, array $stato, bool $versione = true): array
    {
        if (! Permessi::puo($chi, self::PERMESSO)) {
            throw new DomainException('Mappe e territorio del report li cambia chi ha la casella «Valutazione · Mappe e territorio».');
        }
        $nuovi = [];
        $nomi = [];
        foreach (self::MAPPE as $mappa => $nomeMappa) {
            foreach (self::CAMPI_MAPPA[$mappa] as $campo) {
                $chiave = self::chiaveMappa($mappa, $campo);
                $fabbrica = self::FABBRICA_MAPPE[$mappa][$campo];
                if (is_bool($fabbrica)) {
                    $acceso = filter_var($stato[$chiave] ?? $fabbrica, FILTER_VALIDATE_BOOLEAN);
                    $nuovi[$chiave] = $acceso === $fabbrica ? null : ($acceso ? '1' : '0');
                    $nomi[$chiave] = $nomeMappa.' · '.self::SI_NO_MAPPA[$campo];

                    continue;
                }
                [$etichetta, $minimo, $massimo, $unita] = self::NUMERI_MAPPA[$campo];
                $n = self::numero($stato[$chiave] ?? '', false);
                if (trim((string) ($stato[$chiave] ?? '')) !== '' && ($n === null || $n < $minimo || $n > $massimo)) {
                    throw new DomainException('«'.$nomeMappa.' · '.$etichetta.'»: un numero intero da '.$minimo.' a '.self::cifra($massimo).($unita !== '' ? ' '.$unita : '').'. Non ho salvato nulla.');
                }
                $nuovi[$chiave] = ($n === null || (int) $n === $fabbrica) ? null : (string) (int) $n;
                $nomi[$chiave] = $nomeMappa.' · '.$etichetta;
            }
            $raggio = (int) ($nuovi[self::chiaveMappa($mappa, 'raggio')] ?? self::FABBRICA_MAPPE[$mappa]['raggio']);
            $raggioMax = (int) ($nuovi[self::chiaveMappa($mappa, 'raggio_max')] ?? self::FABBRICA_MAPPE[$mappa]['raggio_max']);
            if (in_array('raggio_max', self::CAMPI_MAPPA[$mappa], true) && $raggioMax < $raggio) {
                throw new DomainException('«'.$nomeMappa.'»: «Fin dove si può allargare» non può essere più piccolo del raggio ('.$raggio.' m). Non ho salvato nulla.');
            }
        }
        foreach (['vista' => self::VISTA, 'controlli' => self::CONTROLLI, 'anteprima' => self::ANTEPRIMA] as $gruppo => $definizioni) {
            foreach ($definizioni as $campo => [$etichetta, $fabbrica, $minimo, $massimo, $unita]) {
                $chiave = self::PREFISSO.$gruppo.'_'.$campo;
                $scritto = trim((string) ($stato[$chiave] ?? ''));
                $decimali = is_float($fabbrica);
                $n = self::numero($scritto, $decimali);
                if ($scritto !== '' && ($n === null || $n < $minimo || $n > $massimo)) {
                    throw new DomainException('«'.$etichetta.'»: un numero '.($decimali ? '' : 'intero ').'da '.self::cifra($minimo).' a '.self::cifra($massimo).($unita !== '' ? ' '.$unita : '').'. Non ho salvato nulla.');
                }
                $nuovi[$chiave] = ($n === null || abs($n - $fabbrica) < 1e-9) ? null : self::testoNumero($n);
                $nomi[$chiave] = ['vista' => 'Vista dall\'alto · ', 'controlli' => 'Controlli · ', 'anteprima' => 'Anteprima · '][$gruppo].$etichetta;
            }
        }
        foreach (self::COLORI as $campo => [$etichetta, $fabbrica]) {
            $chiave = self::PREFISSO.'colori_'.$campo;
            $scritto = strtoupper(trim((string) ($stato[$chiave] ?? '')));
            if ($scritto !== '' && preg_match('/^#[0-9A-F]{6}$/', $scritto) !== 1) {
                throw new DomainException('«Colori · '.$etichetta.'»: un colore nella forma #RRGGBB (per esempio #22A354). Non ho salvato nulla.');
            }
            $nuovi[$chiave] = ($scritto === '' || $scritto === $fabbrica) ? null : $scritto;
            $nomi[$chiave] = 'Colori · '.$etichetta;
        }
        foreach (self::TERRITORIO as $campo => $etichetta) {
            $chiave = self::PREFISSO.'territorio_'.$campo;
            $acceso = filter_var($stato[$chiave] ?? true, FILTER_VALIDATE_BOOLEAN);
            $nuovi[$chiave] = $acceso ? null : '0';
            $nomi[$chiave] = 'Territorio · '.$etichetta;
        }
        $chiave = self::PREFISSO.'abbreviazioni';
        $testo = trim(str_replace("\r", '', (string) ($stato[$chiave] ?? '')));
        $righe = array_values(array_filter(array_map('trim', explode("\n", $testo)), fn (string $r): bool => $r !== ''));
        if (count($righe) > self::ABBREVIAZIONI_MASSIMO) {
            throw new DomainException('«Abbreviazioni»: al massimo '.self::ABBREVIAZIONI_MASSIMO.' righe. Non ho salvato nulla.');
        }
        foreach ($righe as $i => $riga) {
            if (count(self::coppie($riga)) !== 1) {
                throw new DomainException('«Abbreviazioni», riga '.($i + 1).' («'.mb_substr($riga, 0, 40).'»): va scritta come «C.so = Corso». Non ho salvato nulla.');
            }
        }
        $pulito = implode("\n", $righe);
        $nuovi[$chiave] = ($pulito === '' || $pulito === self::ABBREVIAZIONI) ? null : $pulito;
        $nomi[$chiave] = 'Controlli · Abbreviazioni';

        $prima = $versione ? Versioni::serveLaPrima() : null;
        $fatte = [];
        DB::transaction(function () use ($nuovi, $nomi, &$fatte): void {
            foreach ($nuovi as $chiave => $valore) {
                $vecchio = Setting::query()->where('key', $chiave)->value('value');
                $vecchio = $vecchio === '' ? null : $vecchio;
                if ($vecchio !== $valore) {
                    Setting::updateOrCreate(['key' => $chiave], ['value' => $valore]);
                    $fatte[] = $nomi[$chiave];
                }
            }
        });
        if ($versione && $fatte !== []) {
            Versioni::registra($chi, 'Mappe e territorio', $fatte, $prima);
        }

        return $fatte;
    }

    /**
     * I valori di oggi per il modulo dell'Admin: chiave => valore com'è
     * scritto (vuoto = fabbrica, per i numeri; i sì e no sempre veri o falsi).
     *
     * @return array<string, string|bool>
     */
    public static function perIlModulo(?array $tutte = null): array
    {
        $t = $tutte ?? self::tutte();
        $valori = [];
        foreach (self::MAPPE as $mappa => $nome) {
            foreach (self::CAMPI_MAPPA[$mappa] as $campo) {
                $v = $t['mappe'][$mappa][$campo];
                $valori[self::chiaveMappa($mappa, $campo)] = is_bool($v) ? $v : ($v === self::FABBRICA_MAPPE[$mappa][$campo] ? '' : (string) $v);
            }
        }
        foreach (['vista' => self::VISTA, 'controlli' => self::CONTROLLI, 'anteprima' => self::ANTEPRIMA] as $gruppo => $definizioni) {
            foreach ($definizioni as $campo => $d) {
                $v = $t[$gruppo][$campo];
                $valori[self::PREFISSO.$gruppo.'_'.$campo] = abs($v - $d[1]) < 1e-9 ? '' : self::testoNumero($v);
            }
        }
        foreach (self::COLORI as $campo => $d) {
            $valori[self::PREFISSO.'colori_'.$campo] = $t['colori'][$campo] === $d[1] ? '' : $t['colori'][$campo];
        }
        foreach (self::TERRITORIO as $campo => $e) {
            $valori[self::PREFISSO.'territorio_'.$campo] = $t['territorio'][$campo];
        }
        $valori[self::PREFISSO.'abbreviazioni'] = $t['abbreviazioni'];

        return $valori;
    }

    /**
     * Ogni voce con il suo nome e il valore scritto per una persona
     * («Le case vicine · Raggio» => «330 m»): per le differenze fra le
     * versioni, nella pagina dei testi.
     *
     * @param  array<string, mixed>  $tutte  come tutte() o normalizza()
     * @return array<string, string>
     */
    public static function voci(array $tutte): array
    {
        $voci = [];
        $conUnita = fn (float|int $n, string $unita): string => (floor((float) $n) === (float) $n ? number_format((float) $n, 0, ',', '.') : self::cifra($n)).($unita !== '' ? ' '.$unita : '');
        foreach (self::MAPPE as $mappa => $nomeMappa) {
            foreach (self::CAMPI_MAPPA[$mappa] as $campo) {
                $v = $tutte['mappe'][$mappa][$campo];
                $voci[$nomeMappa.' · '.(self::SI_NO_MAPPA[$campo] ?? self::NUMERI_MAPPA[$campo][0])] = is_bool($v) ? ($v ? 'sì' : 'no') : $conUnita($v, self::NUMERI_MAPPA[$campo][3]);
            }
        }
        foreach (['vista' => [self::VISTA, 'Vista dall\'alto · '], 'controlli' => [self::CONTROLLI, 'Controlli · '], 'anteprima' => [self::ANTEPRIMA, 'Anteprima · ']] as $gruppo => [$definizioni, $prefisso]) {
            foreach ($definizioni as $campo => [$etichetta, , , , $unita]) {
                $voci[$prefisso.$etichetta] = $conUnita($tutte[$gruppo][$campo], $unita);
            }
        }
        foreach (self::COLORI as $campo => [$etichetta]) {
            $voci['Colori · '.$etichetta] = $tutte['colori'][$campo];
        }
        foreach (self::TERRITORIO as $campo => $etichetta) {
            $voci['Territorio · '.$etichetta] = $tutte['territorio'][$campo] ? 'accesa' : 'spenta';
        }
        $voci['Controlli · Abbreviazioni'] = $tutte['abbreviazioni'];

        return $voci;
    }

    /**
     * Da chiave => testo salvato a valori pronti, con la fabbrica dove manca o non torna.
     *
     * @param  array<string, string>  $chiavi
     * @return array{mappe: array<string, array<string, int|bool>>, vista: array<string, float|int>, colori: array<string, string>, controlli: array<string, int>, anteprima: array<string, int>, abbreviazioni: string, territorio: array<string, bool>}
     */
    private static function daChiavi(array $chiavi): array
    {
        $mappe = [];
        foreach (self::MAPPE as $mappa => $nome) {
            foreach (self::FABBRICA_MAPPE[$mappa] as $campo => $fabbrica) {
                $scritto = $chiavi[self::chiaveMappa($mappa, $campo)] ?? null;
                if (is_bool($fabbrica)) {
                    $mappe[$mappa][$campo] = $scritto === null ? $fabbrica : $scritto === '1';

                    continue;
                }
                [, $minimo, $massimo] = self::NUMERI_MAPPA[$campo];
                $n = $scritto === null ? null : self::numero($scritto, false);
                $mappe[$mappa][$campo] = $n !== null && $n >= $minimo && $n <= $massimo ? (int) $n : $fabbrica;
            }
            if ($mappe[$mappa]['raggio_max'] < $mappe[$mappa]['raggio']) {
                $mappe[$mappa]['raggio_max'] = $mappe[$mappa]['raggio'];
            }
        }
        $numeri = function (array $definizioni, string $gruppo) use ($chiavi): array {
            $fuori = [];
            foreach ($definizioni as $campo => [, $fabbrica, $minimo, $massimo]) {
                $scritto = $chiavi[self::PREFISSO.$gruppo.'_'.$campo] ?? null;
                $n = $scritto === null ? null : self::numero($scritto, is_float($fabbrica));
                $fuori[$campo] = $n !== null && $n >= $minimo && $n <= $massimo ? (is_float($fabbrica) ? (float) $n : (int) $n) : $fabbrica;
            }

            return $fuori;
        };
        $colori = [];
        foreach (self::COLORI as $campo => [, $fabbrica]) {
            $scritto = strtoupper((string) ($chiavi[self::PREFISSO.'colori_'.$campo] ?? ''));
            $colori[$campo] = preg_match('/^#[0-9A-F]{6}$/', $scritto) === 1 ? $scritto : $fabbrica;
        }
        $territorio = [];
        foreach (self::TERRITORIO as $campo => $e) {
            $territorio[$campo] = ($chiavi[self::PREFISSO.'territorio_'.$campo] ?? '1') !== '0';
        }
        $abbreviazioni = trim((string) ($chiavi[self::PREFISSO.'abbreviazioni'] ?? ''));

        return [
            'mappe' => $mappe,
            'vista' => $numeri(self::VISTA, 'vista'),
            'colori' => $colori,
            'controlli' => $numeri(self::CONTROLLI, 'controlli'),
            'anteprima' => $numeri(self::ANTEPRIMA, 'anteprima'),
            'abbreviazioni' => $abbreviazioni === '' ? self::ABBREVIAZIONI : $abbreviazioni,
            'territorio' => $territorio,
        ];
    }

    /** «3,2», «3.2», «40» → numero; con $decimali falso solo interi. Altrimenti null. */
    private static function numero(mixed $valore, bool $decimali): float|int|null
    {
        if (is_int($valore)) {
            return $valore;
        }
        if (is_float($valore)) {
            return $decimali ? $valore : (floor($valore) === $valore ? (int) $valore : null);
        }
        $testo = str_replace(',', '.', trim((string) $valore));
        if ($testo === '') {
            return null;
        }
        if (preg_match($decimali ? '/^\d{1,6}(\.\d{1,3})?$/' : '/^\d{1,6}$/', $testo) !== 1) {
            return null;
        }

        return $decimali ? (float) $testo : (int) $testo;
    }

    private static function testoNumero(float|int $n): string
    {
        return is_int($n) || floor($n) === $n ? (string) (int) $n : rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    }

    private static function cifra(float|int $n): string
    {
        return str_replace('.', ',', self::testoNumero($n));
    }
}