<?php

namespace App\Support\Valutazione\Mappe;

/**
 * LA GEOMETRIA DELLE MAPPE (lotto 4, D-VAL-96): la proiezione dai gradi ai
 * millimetri della pagina, il ritaglio sul riquadro, la semplificazione
 * alla precisione della stampa e i percorsi SVG leggeri.
 *
 *  - La proiezione è quella del prototipo (mappe.py): piana intorno al
 *    centro, 111.320 m per grado di longitudine all'equatore (per il coseno
 *    della latitudine) e 111.132 m per grado di latitudine. Su qualche
 *    chilometro l'errore è di pochi centimetri.
 *  - Il peso: le forme si ritagliano sul riquadro (con un margine), i
 *    punti più vicini di 0,15 mm al precedente si saltano, le coordinate
 *    vanno a decimi di millimetro e i percorsi si scrivono in passi
 *    relativi da punti già arrotondati (niente errori che si sommano).
 *  - mPDF legge «M x y l dx dy dx dy … z» con i passi ripetuti (Svg::svgPath).
 */
final class Geometria
{
    /** Metri per grado di longitudine all'equatore e per grado di latitudine (mappe.py). */
    public const M_LON = 111320.0;

    public const M_LAT = 111132.0;

    /** I punti più vicini di così (mm) al precedente si saltano. */
    public const PASSO_MINIMO = 0.15;

    /**
     * Dalle coordinate ai millimetri: [x, y] con y verso il basso, centro in ($w/2, $h/2).
     *
     * @return array{0: float, 1: float}
     */
    public static function xy(array $p, float $lat0, float $lon0, float $mmPerMetro, float $w, float $h): array
    {
        $k = cos(deg2rad($lat0));

        return [
            ($p[0] - $lon0) * self::M_LON * $k * $mmPerMetro + $w / 2,
            -($p[1] - $lat0) * self::M_LAT * $mmPerMetro + $h / 2,
        ];
    }

    /**
     * Dalle coordinate ai metri dal centro: [est, nord].
     *
     * @return array{0: float, 1: float}
     */
    public static function metri(float $lat, float $lon, float $lat0, float $lon0): array
    {
        return [($lon - $lon0) * self::M_LON * cos(deg2rad($lat0)), ($lat - $lat0) * self::M_LAT];
    }

    /**
     * Il punto a $est e $nord metri dal centro, in gradi: [lat, lon].
     *
     * @return array{0: float, 1: float}
     */
    public static function gradi(float $est, float $nord, float $lat0, float $lon0): array
    {
        return [$lat0 + $nord / self::M_LAT, $lon0 + $est / (self::M_LON * cos(deg2rad($lat0)))];
    }

    /**
     * Il riquadro in gradi [ovest, sud, est, nord] di mezza larghezza $mezzoEst e mezza altezza $mezzoNord metri.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function riquadro(float $lat0, float $lon0, float $mezzoEst, float $mezzoNord): array
    {
        [$sud, $ovest] = self::gradi(-$mezzoEst, -$mezzoNord, $lat0, $lon0);
        [$nord, $est] = self::gradi($mezzoEst, $mezzoNord, $lat0, $lon0);

        return [$ovest, $sud, $est, $nord];
    }

    /** La distanza in metri fra due punti (haversine, come mappe.py). */
    public static function distanza(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371008.8;
        $f1 = deg2rad($lat1);
        $f2 = deg2rad($lat2);
        $a = sin(($f2 - $f1) / 2) ** 2 + cos($f1) * cos($f2) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    /**
     * Gli anelli di una forma del magazzino (poligoni e multipoligoni), in [lon, lat].
     *
     * @return list<list<list<array{0: float, 1: float}>>> poligoni, ognuno con i suoi anelli (il primo è il bordo esterno)
     */
    public static function poligoni(?array $forma): array
    {
        if ($forma === null) {
            return [];
        }
        $c = $forma['coordinate'] ?? [];

        return match ($forma['tipo'] ?? '') {
            'Polygon' => [$c],
            'MultiPolygon' => array_values($c),
            'GeometryCollection' => array_merge([], ...array_map(fn ($f) => self::poligoni(is_array($f) ? $f : null), array_values($c))),
            default => [],
        };
    }

    /**
     * Le linee di una forma del magazzino, in [lon, lat].
     *
     * @return list<list<array{0: float, 1: float}>>
     */
    public static function linee(?array $forma): array
    {
        if ($forma === null) {
            return [];
        }
        $c = $forma['coordinate'] ?? [];

        return match ($forma['tipo'] ?? '') {
            'LineString' => [$c],
            'MultiLineString' => array_values($c),
            default => [],
        };
    }

    /** L'area di un anello (in unità quadrate, con il segno: positiva in senso antiorario in coordinate con y verso l'alto). */
    public static function areaConSegno(array $anello): float
    {
        $s = 0.0;
        $n = count($anello);
        for ($i = 0; $i < $n; $i++) {
            $a = $anello[$i];
            $b = $anello[($i + 1) % $n];
            $s += $a[0] * $b[1] - $b[0] * $a[1];
        }

        return $s / 2;
    }

    /** Il punto [x, y] è dentro l'anello? (pari e dispari) */
    public static function dentro(array $p, array $anello): bool
    {
        $dentro = false;
        $n = count($anello);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $anello[$i];
            [$xj, $yj] = $anello[$j];
            if (($yi > $p[1]) !== ($yj > $p[1]) && $p[0] < ($xj - $xi) * ($p[1] - $yi) / (($yj - $yi) ?: 1e-12) + $xi) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }

    /** Il punto è dentro il poligono (dentro il bordo esterno e fuori dai cortili)? */
    public static function dentroPoligono(array $p, array $anelli): bool
    {
        if ($anelli === [] || ! self::dentro($p, $anelli[0])) {
            return false;
        }
        foreach (array_slice($anelli, 1) as $cortile) {
            if (self::dentro($p, $cortile)) {
                return false;
            }
        }

        return true;
    }

    /** La distanza di un punto da un segmento. */
    public static function daSegmento(array $p, array $a, array $b): float
    {
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $l2 = $dx * $dx + $dy * $dy;
        $t = $l2 > 0 ? max(0.0, min(1.0, (($p[0] - $a[0]) * $dx + ($p[1] - $a[1]) * $dy) / $l2)) : 0.0;

        return hypot($p[0] - ($a[0] + $t * $dx), $p[1] - ($a[1] + $t * $dy));
    }

    /** La distanza di un punto da una linea (spezzata). */
    public static function daLinea(array $p, array $linea): float
    {
        $min = INF;
        for ($i = 1, $n = count($linea); $i < $n; $i++) {
            $min = min($min, self::daSegmento($p, $linea[$i - 1], $linea[$i]));
        }

        return $n === 1 ? hypot($p[0] - $linea[0][0], $p[1] - $linea[0][1]) : $min;
    }

    /** La distanza di un punto dal bordo di un anello. */
    public static function dalBordo(array $p, array $anello): float
    {
        $chiuso = $anello;
        if ($chiuso !== [] && $chiuso[0] !== $chiuso[count($chiuso) - 1]) {
            $chiuso[] = $chiuso[0];
        }

        return self::daLinea($p, $chiuso);
    }

    /**
     * Ritaglia un anello sul rettangolo [x0, y0, x1, y1] (Sutherland-Hodgman). Vuoto se è tutto fuori.
     *
     * @return list<array{0: float, 1: float}>
     */
    public static function ritagliaAnello(array $anello, float $x0, float $y0, float $x1, float $y1): array
    {
        $tutto = true;
        foreach ($anello as $p) {
            if ($p[0] < $x0 || $p[0] > $x1 || $p[1] < $y0 || $p[1] > $y1) {
                $tutto = false;

                break;
            }
        }
        if ($tutto) {
            return array_values($anello);
        }
        $bordi = [
            fn ($p) => $p[0] >= $x0, fn ($p) => $p[0] <= $x1, fn ($p) => $p[1] >= $y0, fn ($p) => $p[1] <= $y1,
        ];
        $taglio = [
            fn ($a, $b) => [$x0, $a[1] + ($b[1] - $a[1]) * ($x0 - $a[0]) / (($b[0] - $a[0]) ?: 1e-12)],
            fn ($a, $b) => [$x1, $a[1] + ($b[1] - $a[1]) * ($x1 - $a[0]) / (($b[0] - $a[0]) ?: 1e-12)],
            fn ($a, $b) => [$a[0] + ($b[0] - $a[0]) * ($y0 - $a[1]) / (($b[1] - $a[1]) ?: 1e-12), $y0],
            fn ($a, $b) => [$a[0] + ($b[0] - $a[0]) * ($y1 - $a[1]) / (($b[1] - $a[1]) ?: 1e-12), $y1],
        ];
        $punti = array_values($anello);
        foreach ($bordi as $k => $dentro) {
            $fuori = [];
            $n = count($punti);
            if ($n === 0) {
                return [];
            }
            for ($i = 0; $i < $n; $i++) {
                $a = $punti[($i + $n - 1) % $n];
                $b = $punti[$i];
                if ($dentro($b)) {
                    if (! $dentro($a)) {
                        $fuori[] = $taglio[$k]($a, $b);
                    }
                    $fuori[] = $b;
                } elseif ($dentro($a)) {
                    $fuori[] = $taglio[$k]($a, $b);
                }
            }
            $punti = $fuori;
        }

        return count($punti) >= 3 ? $punti : [];
    }

    /**
     * Ritaglia una linea sul rettangolo: i pezzi che restano dentro.
     *
     * @return list<list<array{0: float, 1: float}>>
     */
    public static function ritagliaLinea(array $linea, float $x0, float $y0, float $x1, float $y1): array
    {
        $pezzi = [];
        $corrente = [];
        for ($i = 1, $n = count($linea); $i < $n; $i++) {
            $s = self::segmentoDentro($linea[$i - 1], $linea[$i], $x0, $y0, $x1, $y1);
            if ($s === null) {
                if (count($corrente) > 1) {
                    $pezzi[] = $corrente;
                }
                $corrente = [];

                continue;
            }
            [$a, $b] = $s;
            if ($corrente === [] || $corrente[count($corrente) - 1] !== $a) {
                if (count($corrente) > 1) {
                    $pezzi[] = $corrente;
                }
                $corrente = [$a];
            }
            $corrente[] = $b;
        }
        if (count($corrente) > 1) {
            $pezzi[] = $corrente;
        }

        return $pezzi;
    }

    /** Il pezzo di segmento dentro il rettangolo (Liang-Barsky), o null. */
    private static function segmentoDentro(array $a, array $b, float $x0, float $y0, float $x1, float $y1): ?array
    {
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $t0 = 0.0;
        $t1 = 1.0;
        foreach ([[-$dx, $a[0] - $x0], [$dx, $x1 - $a[0]], [-$dy, $a[1] - $y0], [$dy, $y1 - $a[1]]] as [$p, $q]) {
            if ($p == 0.0) {
                if ($q < 0) {
                    return null;
                }

                continue;
            }
            $r = $q / $p;
            if ($p < 0) {
                if ($r > $t1) {
                    return null;
                }
                $t0 = max($t0, $r);
            } else {
                if ($r < $t0) {
                    return null;
                }
                $t1 = min($t1, $r);
            }
        }
        $pa = $t0 > 0 ? [$a[0] + $t0 * $dx, $a[1] + $t0 * $dy] : $a;
        $pb = $t1 < 1 ? [$a[0] + $t1 * $dx, $a[1] + $t1 * $dy] : $b;

        return [$pa, $pb];
    }

    /**
     * I punti in decimi di millimetro interi, senza quelli troppo vicini al precedente.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function decimi(array $punti, bool $chiuso): array
    {
        $fuori = [];
        $minimo = self::PASSO_MINIMO * 10;
        $n = count($punti);
        foreach ($punti as $i => $p) {
            $q = [(int) round($p[0] * 10), (int) round($p[1] * 10)];
            $ultimo = $i === $n - 1;
            if ($fuori !== []) {
                $prec = $fuori[count($fuori) - 1];
                // il punto finale di una linea resta sempre, se non coincide
                if (hypot($q[0] - $prec[0], $q[1] - $prec[1]) < $minimo && ! ($ultimo && ! $chiuso && $q !== $prec)) {
                    continue;
                }
            }
            $fuori[] = $q;
        }
        if ($chiuso && count($fuori) > 1 && $fuori[0] === $fuori[count($fuori) - 1]) {
            array_pop($fuori);
        }

        return $fuori;
    }

    /** Un percorso SVG da punti in decimi interi: «M x y l dx dy …» (e «z» se chiuso). */
    public static function percorso(array $decimi, bool $chiuso): string
    {
        if (count($decimi) < 2 || ($chiuso && count($decimi) < 3)) {
            return '';
        }
        $d = 'M'.self::dec($decimi[0][0]).' '.self::dec($decimi[0][1]).'l';
        $passi = [];
        for ($i = 1, $n = count($decimi); $i < $n; $i++) {
            $passi[] = self::dec($decimi[$i][0] - $decimi[$i - 1][0]).' '.self::dec($decimi[$i][1] - $decimi[$i - 1][1]);
        }

        return $d.implode(' ', $passi).($chiuso ? 'z' : '');
    }

    /** Decimi di millimetro come numero SVG: 15 → «1.5», -3 → «-0.3», 20 → «2». */
    public static function dec(int $decimi): string
    {
        $segno = $decimi < 0 ? '-' : '';
        $a = abs($decimi);

        return $segno.intdiv($a, 10).($a % 10 !== 0 ? '.'.($a % 10) : '');
    }

    /** Un numero per l'SVG: al massimo due decimali, col punto (come Grafici::n). */
    public static function n(float $x): string
    {
        $n = number_format($x, 2, '.', '');

        return str_contains($n, '.') ? rtrim(rtrim($n, '0'), '.') : $n;
    }
}