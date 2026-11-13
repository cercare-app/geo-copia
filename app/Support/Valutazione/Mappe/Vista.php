<?php

namespace App\Support\Valutazione\Mappe;

use App\Support\Valutazione\Grafici;

/**
 * LA VISTA DALL'ALTO (lotto 4, D-VAL-98): gli edifici di OpenStreetMap in
 * rilievo, visti da sud con l'inclinazione scelta (di fabbrica 40°), larga
 * di fabbrica 300 m, il nord in alto. Rifà il prototipo vista.py, per mPDF.
 *
 * Le regole di precisione del 05/11, tutte:
 *  1. l'edificio della casa (Casa::edificio): contiene le coordinate, il
 *     più piccolo; altrimenti il più vicino entro la soglia; oltre, niente;
 *  2. oltre la soglia dell'isolato la didascalia lo dice (INFO «isolato»);
 *  3. il segnaposto alla quota del tetto dell'edificio colorato (a terra
 *     se non c'è);
 *  4. i nomi delle vie solo su un tratto che si vede davvero (non coperto
 *     da pareti o tetti), prima le vie più vicine alla casa, al massimo
 *     quanti ne dice l'Admin; ogni cartellino non tocca il segnaposto, la
 *     scala, il nord, i crediti né il bordo; mai su una via diversa;
 *  5. la via dell'indirizzo si scrive sempre accanto al segnaposto, lungo
 *     la via (a destra, se non c'è posto a sinistra, poi più in là), anche
 *     se gli edifici la coprono; se nei dati non c'è, niente;
 *  6. il controllo dei 30 m lo fa Casa::via/esito;
 *  7. nord in alto, la scala vale in larghezza (in profondità è scorciata).
 * In più (lotto 4): tettoie e pensiline fuori, i piani del tetto sommati,
 * il mare, le strade in galleria non si disegnano (è la superficie). Se nel
 * riquadro non c'è nessun edificio, la vista non c'è (null).
 *
 * Le altezze: dall'altezza in metri o dal numero di piani (più i piani del
 * tetto); altrimenti la mediana dei piani degli edifici entro 80 m; se
 * non ce ne sono, i piani di riserva (di fabbrica 4).
 *
 * I cortili senza clipPath (mPDF non lo conosce): per ogni edificio, dal
 * più lontano, prima il fondo dei cortili a terra e le loro pareti interne,
 * poi le pareti esterne, poi il tetto col buco (evenodd): ciò che si vede
 * dal buco resta, il resto lo copre il tetto.
 */
final class Vista
{
    /** La luce, da sud-ovest (vista.py). */
    public const LUCE = [-0.55, -0.83];

    /** Gli edifici entro 80 m danno i piani a chi non li ha. */
    public const VICINI_M = 80.0;

    /**
     * La vista, coi dati per la didascalia; null se il materiale non basta o
     * se non c'è nessun edificio.
     *
     * @param  array<string, mixed>  $c  la cornice della vista
     * @param  array<string, mixed>  $imp  ImpostazioniMappe::normalizza()
     * @param  array{crediti: string, nord: string}  $testi
     * @param  callable(string): bool  $vietato
     * @return array{svg: string, casa: ?string, via: array{trovata: bool, nome: ?string, metri: ?float}, nomi: list<string>}|null
     */
    public static function disegna(array $c, Materiale $m, array $imp, string $indirizzo, array $testi, callable $vietato): ?array
    {
        $lettura = Geometria::riquadro($c['lat'], $c['lon'], $c['lettura'], $c['lettura']);
        if (! $m->copre($lettura, true)) {
            return null;
        }
        $v = $imp['vista'];
        $colori = $imp['colori'];
        $w = (float) $c['w'];
        $h = (float) $c['h'];
        $larghezza = (float) $v['larghezza'];
        $s = $w / $larghezza;
        $ct = cos(deg2rad((float) $v['inclinazione']));
        $st = sin(deg2rad((float) $v['inclinazione']));
        $lat0 = (float) $c['lat'];
        $lon0 = (float) $c['lon'];
        $metri = fn (array $q): array => Geometria::metri((float) $q[1], (float) $q[0], $lat0, $lon0);
        $pagina = fn (float $x, float $y, float $z = 0.0): array => [$x * $s + $w / 2, -($y * $ct + $z * $st) * $s + $h / 2];
        $n = fn (float $x): string => Geometria::n($x);
        $punti = fn (array $anello): string => implode(' ', array_map(fn ($p) => $n($p[0]).','.$n($p[1]), $anello));

        // gli edifici del riquadro, con altezza
        $edifici = [];
        foreach ($m->oggetti('edifici', $lettura) as $o) {
            if (in_array((string) ($o['tipo'] ?? ''), Casa::NON_EDIFICI, true)) {
                continue;
            }
            $piani = null;
            if (is_numeric($o['altezza'] ?? null) && (float) $o['altezza'] > 0) {
                $piani = (float) $o['altezza'] / (float) $v['metri_piano'];
            } elseif (is_numeric($o['piani'] ?? null) && (float) $o['piani'] > 0) {
                $piani = (float) $o['piani'] + (is_numeric($o['piani_tetto'] ?? null) ? max(0.0, (float) $o['piani_tetto']) : 0.0);
            }
            foreach (Geometria::poligoni($o['forma']) as $anelli) {
                $mm = array_map(fn ($anello) => self::senzaChiusura(array_map($metri, $anello)), $anelli);
                if (count($mm[0]) < 3) {
                    continue;
                }
                $cx = array_sum(array_column($mm[0], 0)) / count($mm[0]);
                $cy = array_sum(array_column($mm[0], 1)) / count($mm[0]);
                if (abs($cx) > $larghezza * 0.75 || abs($cy) > $larghezza * 0.6) {
                    continue;
                }
                $edifici[] = ['m' => $mm[0], 'inn' => array_values(array_filter(array_slice($mm, 1), fn ($k) => count($k) >= 3)), 'piani' => $piani, 'c' => [$cx, $cy], 'id' => $o['id'] ?? null, 'anelli' => $anelli];
            }
        }
        if ($edifici === []) {
            return null;
        }
        $noti = array_values(array_filter($edifici, fn ($e) => $e['piani'] !== null));
        foreach ($edifici as &$e) {
            if ($e['piani'] === null) {
                $vicini = [];
                foreach ($noti as $k) {
                    if (hypot($k['c'][0] - $e['c'][0], $k['c'][1] - $e['c'][1]) < self::VICINI_M) {
                        $vicini[] = $k['piani'];
                    }
                }
                $e['piani'] = $vicini !== [] ? self::mediana($vicini) : (float) $v['piani_riserva'];
            }
            $e['z'] = $e['piani'] * (float) $v['metri_piano'];
        }
        unset($e);

        // l'edificio della casa (regola 1) e se è un isolato (regola 2)
        $casaEd = Casa::edificio(array_values($m->oggetti('edifici', $lettura)), $lat0, $lon0, (float) $imp['controlli']['edificio_m'], (float) $imp['controlli']['isolato_m2']);
        $casaZ = 0.0;
        foreach ($edifici as &$e) {
            $e['casa'] = $casaEd !== null && $e['anelli'] === $casaEd['anelli'];
            if ($e['casa']) {
                $casaZ = $e['z'];
            }
        }
        unset($e);
        usort($edifici, fn ($a, $b) => max(array_column($b['m'], 1)) <=> max(array_column($a['m'], 1)));

        // a terra: fondo, verde, acqua e mare, strade (non le gallerie)
        $corpo = '<rect x="0" y="0" width="'.$n($w).'" height="'.$n($h).'" fill="'.$colori['fondo'].'"/>';
        $aTerra = function (array $oggetti, string $colore) use ($metri, $pagina, $w, $h): string {
            $d = [];
            foreach ($oggetti as $o) {
                foreach (Geometria::poligoni($o['forma']) as $anelli) {
                    $pezzo = '';
                    foreach ($anelli as $anello) {
                        $mm = array_map(fn ($q) => $pagina(...$metri($q)), $anello);
                        $tagliato = Geometria::ritagliaAnello($mm, -3, -3, $w + 3, $h + 3);
                        if ($tagliato !== []) {
                            $pezzo .= Geometria::percorso(Geometria::decimi($tagliato, true), true);
                        }
                    }
                    if ($pezzo !== '') {
                        $d[] = $pezzo;
                    }
                }
            }

            return Piana::percorsi($d, 'fill="'.$colore.'" fill-rule="evenodd"');
        };
        $corpo .= $aTerra($m->oggetti('verde', $lettura), $colori['verde']);
        $corpo .= $aTerra([...$m->oggetti('mare', $lettura), ...$m->oggetti('acqua', $lettura)], $colori['acqua']);
        $strade = [];
        $vieConNome = [];
        // tutte le vie disegnate col nome, di ogni tipo: la via dell'indirizzo si scrive anche se è
        // pedonale, una calle o una scalinata (regola 5); le altre solo dei tipi con nome (regola 4)
        $tutteConNome = [];
        foreach ($m->oggetti('strade', $lettura) as $o) {
            $tipo = (string) ($o['tipo'] ?? '');
            if (! isset(Piana::LARGHEZZE[$tipo]) || Piana::sotto($o)) {
                continue;
            }
            foreach (Geometria::linee($o['forma']) as $linea) {
                $lm = array_map($metri, $linea);
                $strade[] = [$tipo, $lm];
                if (is_string($o['nome'] ?? null) && trim($o['nome']) !== '') {
                    $tutteConNome[(string) $o['nome']][] = $lm;
                    if (in_array($tipo, Etichette::CON_NOME, true)) {
                        $vieConNome[(string) $o['nome']][] = $lm;
                    }
                }
            }
        }
        foreach ([[$colori['bordi'], 1.6], [$colori['strade'], 0.0]] as [$colore, $extra]) {
            $perSpessore = [];
            foreach ($strade as [$tipo, $lm]) {
                $largo = max(0.4, (Piana::LARGHEZZE[$tipo] + $extra) * $s * (in_array($tipo, ['footway', 'path', 'cycleway', 'service', 'steps'], true) ? 0.85 : 1));
                $mm = array_map(fn ($p) => $pagina($p[0], $p[1]), $lm);
                foreach (Geometria::ritagliaLinea($mm, -3, -3, $w + 3, $h + 3) as $pezzo) {
                    $perSpessore[$n($largo)][] = Geometria::percorso(Geometria::decimi($pezzo, false), false);
                }
            }
            foreach ($perSpessore as $spessore => $d) {
                $corpo .= Piana::percorsi(array_values(array_filter($d)), 'fill="none" stroke="'.$colore.'" stroke-width="'.$spessore.'" stroke-linecap="round" stroke-linejoin="round"');
            }
        }

        // gli edifici, dal più lontano
        $sagome = [];
        foreach ($edifici as $e) {
            $z = $e['z'];
            $esterno = self::orienta($e['m'], true);
            $cortili = array_map(fn ($k) => self::orienta($k, false), $e['inn']);
            [$cl, $cs, $ctetto] = $e['casa'] ? [$colori['casa_muri_chiari'], $colori['casa_muri_scuri'], $colori['casa_tetto']] : [$colori['muri_chiari'], $colori['muri_scuri'], $colori['tetti']];
            $tetto = array_map(fn ($p) => $pagina($p[0], $p[1], $z), $esterno);
            $terra = array_map(fn ($p) => $pagina($p[0], $p[1]), $esterno);
            $xs = [...array_column($tetto, 0), ...array_column($terra, 0)];
            $ys = [...array_column($tetto, 1), ...array_column($terra, 1)];
            if (max($xs) < -5 || min($xs) > $w + 5 || max($ys) < -5 || min($ys) > $h + 5) {
                continue;
            }
            $sagome[] = ['b' => [min($xs), min($ys), max($xs), max($ys)], 'poligoni' => [$terra, $tetto, ...self::quadri($esterno, $z, $pagina)]];
            foreach ($cortili as $k) {
                $corpo .= '<polygon points="'.$punti(array_map(fn ($p) => $pagina($p[0], $p[1]), $k)).'" fill="'.$colori['fondo'].'"/>';
                $corpo .= self::pareti($k, $z, $cl, $cs, $pagina);
            }
            $corpo .= self::pareti($esterno, $z, $cl, $cs, $pagina);
            $d = 'M'.implode(' L', array_map(fn ($p) => $n($p[0]).' '.$n($p[1]), $tetto)).'Z';
            foreach ($cortili as $k) {
                $d .= 'M'.implode(' L', array_map(fn ($p) => $n($p[0]).' '.$n($p[1]), array_map(fn ($p) => $pagina($p[0], $p[1], $z), $k))).'Z';
            }
            $corpo .= '<path d="'.$d.'" fill-rule="evenodd" fill="'.$ctetto.'" stroke="'.($e['casa'] ? '#2F8F57' : '#9AA8BC').'" stroke-width="'.($e['casa'] ? '0.32' : '0.16').'" stroke-linejoin="round"/>';
        }

        // il segnaposto sul tetto della casa (regola 3), la scala, il nord, i crediti
        [$px, $py] = $pagina(0.0, 0.0, $casaZ);
        $metriScala = Tratti::metriScala($larghezza / 2);
        $lungoScala = $metriScala * $s;
        [$scala, $ingombroScala] = Tratti::scala($h, $metriScala, $lungoScala, 2.7);
        [$crediti, $ingombroCrediti] = Tratti::crediti($w, $h, $testi['crediti']);
        $vietate = [[$px - 5.5, $py - 17, $px + 5.5, $py + 3], $ingombroScala, [$w - 15, 3, $w - 3, 15], $ingombroCrediti];

        // la via dell'indirizzo, dai dati (regola 5) e i nomi (regola 4)
        $via = Casa::via($m->oggetti('strade', $lettura), $lat0, $lon0, $indirizzo, ImpostazioniMappe::coppie((string) $imp['abbreviazioni']));
        if ($via['trovata'] && ! isset($vieConNome[$via['nome']]) && isset($tutteConNome[$via['nome']])) {
            $vieConNome[$via['nome']] = $tutteConNome[$via['nome']];
        }
        $nomiMax = (int) $v['nomi_max'];
        $scritti = [];
        if ($nomiMax > 0) {
            [$nomiSvg, $scritti] = self::nomi($vieConNome, $via, $sagome, $vietate, $w, $h, $pagina, $nomiMax, $colori['nomi'], $vietato);
            $corpo .= $nomiSvg;
        }
        $corpo .= '<circle cx="'.$n($px).'" cy="'.$n($py).'" r="5.5" fill="'.$colori['alone'].'" fill-opacity="0.18"/>'.Tratti::segnaposto($px, $py, $colori['casa'], 1.2);
        $corpo .= $scala.Tratti::nord($w, $testi['nord']).$crediti.Tratti::angoli($w, $h, 4.0);

        return [
            'svg' => Tratti::svg($w, $h, $corpo),
            'casa' => $casaEd === null ? null : ($casaEd['isolato'] ? 'isolato' : 'edificio'),
            'via' => ['trovata' => $via['trovata'], 'nome' => $via['nome'], 'metri' => $via['metri']],
            'nomi' => $scritti,
        ];
    }

    /** Le pareti che guardano verso chi osserva (vista.py, pareti), dalla più lontana. */
    private static function pareti(array $anello, float $z, string $chiaro, string $scuro, callable $pagina): string
    {
        $pareti = [];
        $n = count($anello);
        for ($i = 0; $i < $n; $i++) {
            $a = $anello[$i];
            $b = $anello[($i + 1) % $n];
            $nx = $b[1] - $a[1];
            $ny = -($b[0] - $a[0]);
            if ($ny >= 0) {
                continue;
            }
            $l = hypot($nx, $ny) ?: 1.0;
            $luce = max(0.0, ($nx * self::LUCE[0] + $ny * self::LUCE[1]) / $l);
            $pareti[] = [($a[1] + $b[1]) / 2, $a, $b, $luce > 0.45 ? $chiaro : $scuro];
        }
        usort($pareti, fn ($x, $y) => $y[0] <=> $x[0]);
        $svg = '';
        // le pareti vicine dello stesso colore, in fila, in un percorso solo
        $d = '';
        $colore = null;
        foreach ($pareti as [, $a, $b, $col]) {
            if ($colore !== null && $col !== $colore) {
                $svg .= '<path d="'.$d.'" fill="'.$colore.'" stroke="'.$colore.'" stroke-width="0.08" stroke-linejoin="round"/>';
                $d = '';
            }
            $colore = $col;
            $q = [$pagina($a[0], $a[1]), $pagina($b[0], $b[1]), $pagina($b[0], $b[1], $z), $pagina($a[0], $a[1], $z)];
            $d .= 'M'.implode(' L', array_map(fn ($p) => Geometria::n($p[0]).' '.Geometria::n($p[1]), $q)).'Z';
        }
        if ($d !== '') {
            $svg .= '<path d="'.$d.'" fill="'.$colore.'" stroke="'.$colore.'" stroke-width="0.08" stroke-linejoin="round"/>';
        }

        return $svg;
    }

    /** I quadrilateri delle pareti (tutte), per sapere che cosa coprono. */
    private static function quadri(array $anello, float $z, callable $pagina): array
    {
        $q = [];
        $n = count($anello);
        for ($i = 0; $i < $n; $i++) {
            $a = $anello[$i];
            $b = $anello[($i + 1) % $n];
            $q[] = [$pagina($a[0], $a[1]), $pagina($b[0], $b[1]), $pagina($b[0], $b[1], $z), $pagina($a[0], $a[1], $z)];
        }

        return $q;
    }

    /** L'anello nel verso voluto (antiorario coi metri che salgono verso nord), senza il punto di chiusura. */
    private static function orienta(array $anello, bool $antiorario): array
    {
        $anello = self::senzaChiusura($anello);

        return (Geometria::areaConSegno($anello) > 0) === $antiorario ? $anello : array_reverse($anello);
    }

    private static function senzaChiusura(array $anello): array
    {
        if (count($anello) > 1 && $anello[0] == $anello[count($anello) - 1]) {
            array_pop($anello);
        }

        return array_values($anello);
    }

    private static function mediana(array $valori): float
    {
        sort($valori);
        $k = count($valori);

        return $k % 2 ? (float) $valori[intdiv($k, 2)] : ($valori[$k / 2 - 1] + $valori[$k / 2]) / 2;
    }

    /**
     * I nomi delle vie (regole 4 e 5), in cartellini bianchi.
     *
     * @param  array<string, list<list<array{0: float, 1: float}>>>  $vie  nome => linee in metri
     * @return array{0: string, 1: list<string>} il disegno e i nomi scritti
     */
    private static function nomi(array $vie, array $via, array $sagome, array $vietate, float $w, float $h, callable $pagina, int $massimo, string $colore, callable $vietato): array
    {
        $scritti = [];
        $corpo = 2.4;
        $coperto = function (float $x, float $y) use ($sagome): bool {
            foreach ($sagome as $sg) {
                [$a, $b, $c, $d] = $sg['b'];
                if ($x < $a || $x > $c || $y < $b || $y > $d) {
                    continue;
                }
                foreach ($sg['poligoni'] as $poli) {
                    if (Geometria::dentro([$x, $y], $poli)) {
                        return true;
                    }
                }
            }

            return false;
        };
        $libero = function (array $ingombro) use ($vietate, $w, $h): bool {
            if (! Etichette::dentro($ingombro, $w, $h, 1.5)) {
                return false;
            }
            foreach ($vietate as $r) {
                if (Etichette::siToccano($ingombro, Etichette::scatola($r))) {
                    return false;
                }
            }

            return true;
        };
        $fuori = '';
        $presi = [];
        $lungo = fn (string $nome): float => Grafici::larghezzaNelDisegno($nome, $corpo, Grafici::SANS_SEMI) + 3;
        $angoloDi = function (float $dx, float $dy): float {
            $a = rad2deg(atan2($dy, $dx));

            return $a > 90 ? $a - 180 : ($a < -90 ? $a + 180 : $a);
        };
        $vicino = [];
        foreach ($vie as $nome => $linee) {
            $vicino[$nome] = INF;
            foreach ($linee as $l) {
                foreach ($l as $p) {
                    $vicino[$nome] = min($vicino[$nome], hypot($p[0], $p[1]));
                }
            }
        }

        // la via dell'indirizzo, accanto al segnaposto, lungo la via
        if ($via['trovata'] && isset($vie[$via['nome']]) && ! $vietato((string) $via['nome'])) {
            $nome = (string) $via['nome'];
            $l = $lungo($nome);
            $migliore = null;
            foreach ($vie[$nome] as $linea) {
                for ($i = 1, $k = count($linea); $i < $k; $i++) {
                    $a = $linea[$i - 1];
                    $b = $linea[$i];
                    $dx = $b[0] - $a[0];
                    $dy = $b[1] - $a[1];
                    $l2 = $dx * $dx + $dy * $dy ?: 1.0;
                    $t = max(0.0, min(1.0, ((0 - $a[0]) * $dx + (0 - $a[1]) * $dy) / $l2));
                    $q = [$a[0] + $t * $dx, $a[1] + $t * $dy];
                    $d = hypot($q[0], $q[1]);
                    if ($migliore === null || $d < $migliore[0]) {
                        $migliore = [$d, $q, $dx, $dy];
                    }
                }
            }
            if ($migliore !== null) {
                [, $q, $dx, $dy] = $migliore;
                $pa = $pagina($q[0], $q[1]);
                $pb = $pagina($q[0] + $dx, $q[1] + $dy);
                $vx = $pb[0] - $pa[0];
                $vy = $pb[1] - $pa[1];
                $lv = hypot($vx, $vy) ?: 1.0;
                [$vx, $vy] = [$vx / $lv, $vy / $lv];
                if ($vx < 0) {
                    [$vx, $vy] = [-$vx, -$vy];
                }
                $angolo = $angoloDi($vx, $vy);
                foreach ([$l / 2 + 7, -($l / 2 + 7), $l / 2 + 14, -($l / 2 + 14)] as $spostamento) {
                    $cx = $pa[0] + $vx * $spostamento;
                    $cy = $pa[1] + $vy * $spostamento;
                    $ingombro = [$cx, $cy, $l / 2, 1.9, $angolo];
                    if ($libero($ingombro)) {
                        $fuori .= self::cartellino($nome, $cx, $cy, $angolo, $l, $corpo, $colore);
                        $presi[] = $ingombro;
                        $scritti[] = $nome;

                        break;
                    }
                }
            }
            unset($vie[$nome]);
        }

        // le altre, dalla più vicina alla casa, sul tratto visibile più vicino
        uksort($vie, fn ($a, $b) => [$vicino[$a], $a] <=> [$vicino[$b], $b]);
        foreach ($vie as $nome => $linee) {
            if (count($presi) >= $massimo) {
                break;
            }
            if ($vietato((string) $nome)) {
                continue;
            }
            $l = $lungo((string) $nome);
            $candidati = [];
            foreach ($linee as $linea) {
                // i tratti dritti sulla pagina (come le mappe piane), non i singoli pezzi dei dati:
                // nei centri storici le vie sono fatte di tanti pezzi corti
                $sullaPagina = array_map(fn (array $p): array => $pagina($p[0], $p[1]), $linea);
                foreach (Etichette::trattiIndici($sullaPagina, 0.3) as [$i0, $i1]) {
                    $a = $sullaPagina[$i0];
                    $b = $sullaPagina[$i1];
                    $lunghezza = hypot($b[0] - $a[0], $b[1] - $a[1]);
                    if ($lunghezza < $l) {
                        continue;
                    }
                    $passi = max(2, (int) ($lunghezza / 0.8));
                    $corsa = [];
                    for ($j = 0; $j <= $passi + 1; $j++) {
                        $t = $j / $passi;
                        $ok = $j <= $passi;
                        if ($ok) {
                            $x = $a[0] + ($b[0] - $a[0]) * $t;
                            $y = $a[1] + ($b[1] - $a[1]) * $t;
                            $ok = $x > 4 && $x < $w - 4 && $y > 4 && $y < $h - 4 && ! $coperto($x, $y);
                        }
                        if ($ok) {
                            $mx = $linea[$i0][0] + ($linea[$i1][0] - $linea[$i0][0]) * $t;
                            $my = $linea[$i0][1] + ($linea[$i1][1] - $linea[$i0][1]) * $t;
                            $corsa[] = [$x, $y, hypot($mx, $my)];

                            continue;
                        }
                        if (count($corsa) > 1 && hypot($corsa[count($corsa) - 1][0] - $corsa[0][0], $corsa[count($corsa) - 1][1] - $corsa[0][1]) >= $l) {
                            $mezzo = $corsa[intdiv(count($corsa), 2)];
                            $candidati[] = [$mezzo[2], $mezzo[0], $mezzo[1], $angoloDi($b[0] - $a[0], $b[1] - $a[1])];
                        }
                        $corsa = [];
                    }
                }
            }
            usort($candidati, fn ($x, $y) => $x[0] <=> $y[0]);
            foreach ($candidati as [, $cx, $cy, $angolo]) {
                $ingombro = [$cx, $cy, $l / 2 + 0.6, 2.4, $angolo];
                $tocca = false;
                foreach ($presi as $p) {
                    if (Etichette::siToccano($ingombro, $p)) {
                        $tocca = true;

                        break;
                    }
                }
                if ($tocca || ! $libero($ingombro)) {
                    continue;
                }
                $fuori .= self::cartellino((string) $nome, $cx, $cy, $angolo, $l, $corpo, $colore);
                $presi[] = $ingombro;
                $scritti[] = (string) $nome;

                break;
            }
        }

        return [$fuori, $scritti];
    }

    /** Il cartellino bianco col nome (vista.py, cartellino), testo centrato misurato. */
    private static function cartellino(string $nome, float $cx, float $cy, float $angolo, float $lungo, float $corpo, string $colore): string
    {
        $n = fn (float $x): string => Geometria::n($x);
        $giro = ' transform="rotate('.$n($angolo).' '.$n($cx).' '.$n($cy).')"';
        $largoTesto = Grafici::larghezzaNelDisegno($nome, $corpo, Grafici::SANS_SEMI);

        return '<rect x="'.$n($cx - $lungo / 2).'" y="'.$n($cy - 1.9).'" width="'.$n($lungo).'" height="3.8" rx="1.9" ry="1.9" fill="#ffffff" fill-opacity="0.95" stroke="#C9D3E1" stroke-width="0.2"'.$giro.'/>'
            .'<text x="'.$n($cx - $largoTesto / 2).'" y="'.$n($cy + 0.85).'" font-size="'.$n($corpo).'" fill="'.$colore.'" font-family="'.Grafici::SANS_SEMI.'"'.$giro.'>'
            .htmlspecialchars($nome, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8').'</text>';
    }
}