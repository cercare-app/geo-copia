<?php

namespace App\Support\Valutazione\Mappe;

use App\Support\Valutazione\Grafici;

/**
 * I NOMI DELLE VIE SULLE MAPPE PIANE (lotto 4, D-VAL-96): come il
 * prototipo (mappe.py, _etichette), con tre precisioni in più.
 *  - Il nome va su un tratto DRITTO della sua via (non solo sul singolo
 *    segmento più lungo): così anche le vie con tanti punti hanno il nome.
 *  - Un nome non tocca un altro nome, il segnaposto, i numeri, la scala né
 *    i crediti (rettangoli ruotati, controllo vero, non cerchi), e non
 *    esce dal riquadro.
 *  - Un nome si scrive solo sulla sua via: mai su una via diversa (D-VAL-98).
 * Prima le vie grandi, poi le più lunghe; al massimo quanti ne dice l'Admin.
 * Una via il cui nome contiene una parola vietata dal segreto non si
 * scrive (D-VAL-105).
 */
final class Etichette
{
    /** Le vie che hanno il nome scritto (mappe.py, CON_NOME). */
    public const CON_NOME = ['primary', 'secondary', 'tertiary', 'residential', 'unclassified', 'living_street', 'pedestrian'];

    public const GRANDI = ['motorway', 'trunk', 'primary', 'secondary'];

    /** Quanto un tratto può scostarsi dalla linea dritta (mm) per portare un nome. */
    public const SCOSTAMENTO = 0.5;

    /**
     * I nomi da scrivere: [nome, cx, cy, angolo, ingombro].
     *
     * @param  list<array{nome: string, tipo: string, linee: list<list<array{0: float, 1: float}>>}>  $vie  le linee già in millimetri
     * @param  list<array<int, float>>  $ostacoli  rettangoli [x0, y0, x1, y1] (o ingombri ruotati: [cx, cy, mezzaL, mezzaH, angolo, 'r'])
     * @param  callable(string): bool  $vietato
     * @return list<array{0: string, 1: float, 2: float, 3: float, 4: array}>
     */
    public static function scegli(array $vie, float $w, float $h, float $corpo, int $massimo, array $ostacoli, callable $vietato): array
    {
        if ($massimo <= 0) {
            return [];
        }
        $perNome = [];
        foreach ($vie as $via) {
            $nome = trim((string) $via['nome']);
            if ($nome === '' || ! in_array($via['tipo'], self::CON_NOME, true) || $vietato($nome)) {
                continue;
            }
            $lungo = Grafici::larghezzaNelDisegno($nome, $corpo, Grafici::SANS_SEMI);
            foreach ($via['linee'] as $linea) {
                foreach (self::tratti($linea) as [$a, $b]) {
                    $l = hypot($b[0] - $a[0], $b[1] - $a[1]);
                    if ($l < $lungo + 2) {
                        continue;
                    }
                    $perNome[$nome]['grande'] = ($perNome[$nome]['grande'] ?? false) || in_array($via['tipo'], self::GRANDI, true);
                    $perNome[$nome]['lungo'] = $lungo;
                    $perNome[$nome]['tratti'][] = [$l, $a, $b];
                }
            }
        }
        foreach ($perNome as &$p) {
            usort($p['tratti'], fn ($x, $y) => $y[0] <=> $x[0]);
            $p['migliore'] = $p['tratti'][0][0];
        }
        unset($p);
        uksort($perNome, function ($a, $b) use ($perNome) {
            return [! $perNome[$a]['grande'], -$perNome[$a]['migliore'], $a] <=> [! $perNome[$b]['grande'], -$perNome[$b]['migliore'], $b];
        });

        $scelti = [];
        $presi = array_map(fn (array $o) => self::scatola($o), $ostacoli);
        foreach ($perNome as $nome => $p) {
            foreach ($p['tratti'] as [, $a, $b]) {
                $cx = ($a[0] + $b[0]) / 2;
                $cy = ($a[1] + $b[1]) / 2;
                $angolo = rad2deg(atan2($b[1] - $a[1], $b[0] - $a[0]));
                if ($angolo > 90) {
                    $angolo -= 180;
                } elseif ($angolo < -90) {
                    $angolo += 180;
                }
                $ingombro = [$cx, $cy, $p['lungo'] / 2 + 0.8, $corpo * 0.62 + 0.3, $angolo];
                if (! self::dentro($ingombro, $w, $h, 1.5)) {
                    continue;
                }
                $libero = true;
                foreach ($presi as $q) {
                    if (self::siToccano($ingombro, $q)) {
                        $libero = false;

                        break;
                    }
                }
                if (! $libero) {
                    continue;
                }
                $scelti[] = [$nome, $cx, $cy, $angolo, $ingombro];
                $presi[] = $ingombro;

                break;
            }
            if (count($scelti) >= $massimo) {
                break;
            }
        }

        return $scelti;
    }

    /**
     * I tratti dritti di una linea: [inizio, fine], ognuno coi punti di mezzo
     * entro SCOSTAMENTO dalla retta fra i due.
     *
     * @return list<array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}>
     */
    public static function tratti(array $linea): array
    {
        return array_map(fn (array $ij): array => [$linea[$ij[0]], $linea[$ij[1]]], self::trattiIndici($linea));
    }

    /**
     * Come tratti(), ma coi numeri dei punti di inizio e fine: per chi deve
     * ritrovare gli stessi punti in un'altra misura (la vista: metri e pagina).
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function trattiIndici(array $linea, float $scostamento = self::SCOSTAMENTO): array
    {
        $tratti = [];
        $n = count($linea);
        $i = 0;
        while ($i < $n - 1) {
            $j = $i + 1;
            while ($j + 1 < $n) {
                $dritto = true;
                for ($k = $i + 1; $k <= $j; $k++) {
                    if (Geometria::daSegmento($linea[$k], $linea[$i], $linea[$j + 1]) > $scostamento) {
                        $dritto = false;

                        break;
                    }
                }
                if (! $dritto) {
                    break;
                }
                $j++;
            }
            $tratti[] = [$i, $j];
            $i = $j;
        }

        return $tratti;
    }

    /** Un ingombro ruotato [cx, cy, mezzaL, mezzaH, angolo] da un rettangolo [x0, y0, x1, y1] o da sé. */
    public static function scatola(array $o): array
    {
        if (count($o) === 5) {
            return $o;
        }

        return [($o[0] + $o[2]) / 2, ($o[1] + $o[3]) / 2, abs($o[2] - $o[0]) / 2, abs($o[3] - $o[1]) / 2, 0.0];
    }

    /** I quattro angoli di un ingombro ruotato. */
    public static function angoli(array $b): array
    {
        [$cx, $cy, $l, $h, $a] = $b;
        $c = cos(deg2rad($a));
        $s = sin(deg2rad($a));
        $punti = [];
        foreach ([[-1, -1], [1, -1], [1, 1], [-1, 1]] as [$u, $v]) {
            $punti[] = [$cx + $u * $l * $c - $v * $h * $s, $cy + $u * $l * $s + $v * $h * $c];
        }

        return $punti;
    }

    /** Due ingombri ruotati si toccano? (assi separatori) */
    public static function siToccano(array $a, array $b): bool
    {
        $pa = self::angoli($a);
        $pb = self::angoli($b);
        foreach ([$a[4], $a[4] + 90, $b[4], $b[4] + 90] as $gradi) {
            $asse = [cos(deg2rad($gradi)), sin(deg2rad($gradi))];
            $proietta = fn (array $punti) => array_map(fn ($p) => $p[0] * $asse[0] + $p[1] * $asse[1], $punti);
            $x = $proietta($pa);
            $y = $proietta($pb);
            if (max($x) < min($y) || max($y) < min($x)) {
                return false;
            }
        }

        return true;
    }

    /** L'ingombro sta dentro il riquadro w × h, a $margine dal bordo? */
    public static function dentro(array $b, float $w, float $h, float $margine): bool
    {
        foreach (self::angoli($b) as [$x, $y]) {
            if ($x < $margine || $x > $w - $margine || $y < $margine || $y > $h - $margine) {
                return false;
            }
        }

        return true;
    }
}