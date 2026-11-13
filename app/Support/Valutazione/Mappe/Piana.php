<?php

namespace App\Support\Valutazione\Mappe;

use App\Support\Valutazione\Grafici;

/**
 * LA MAPPA PIANA (lotto 4, D-VAL-96, D-VAL-97): una cornice (Cornici) e il
 * materiale (Materiale) diventano un SVG in millimetri, come mappe.py.
 *
 * Gli strati dal basso: fondo, verde, mare e acqua, fiumi, edifici (fino a
 * 700 m di raggio), ferrovie, strade (bordo poi riempimento; le gallerie
 * più chiare e tratteggiate, D-VAL-94 rivista), nomi delle vie, cerchi
 * delle distanze, confine della zona, la casa, i numeri delle case, la
 * scala, i crediti, gli angoli.
 *
 * Il peso (D-VAL-96): le forme si ritagliano sul riquadro (con un margine),
 * si semplificano a 0,15 mm e si scrivono in pochi percorsi lunghi, uno per
 * stile, in decimi di millimetro: una mappa pesa una frazione di quelle del
 * prototipo. Gli edifici più piccoli di un puntino non si disegnano.
 *
 * null se il materiale non copre la cornice per intero: mai una mappa a
 * metà (D-VAL-97).
 */
final class Piana
{
    /** La larghezza in metri delle strade, poi in scala (mappe.py, LARGH). */
    public const LARGHEZZE = [
        'motorway' => 16, 'trunk' => 16, 'primary' => 15, 'secondary' => 13, 'tertiary' => 11,
        'residential' => 8, 'unclassified' => 8, 'living_street' => 7, 'pedestrian' => 6, 'service' => 4,
        'footway' => 2.2, 'path' => 2, 'cycleway' => 2.2,
        'motorway_link' => 10, 'trunk_link' => 10, 'primary_link' => 9, 'secondary_link' => 8, 'tertiary_link' => 7, 'road' => 7, 'steps' => 2,
    ];

    /** Le vie sottili: oltre 500 m di raggio non si disegnano (mappe.py). */
    public const SOTTILI = ['service', 'footway', 'path', 'cycleway', 'steps'];

    /** I percorsi più lunghi di così (caratteri) si spezzano: mPDF legge meglio pezzi medi. */
    public const PEZZO = 60000;

    /** Gli edifici con un ingombro sotto questo (mm) non si disegnano: in stampa sarebbero un puntino. */
    public const EDIFICIO_MINIMO_MM = 0.35;

    /** Il margine (mm) del ritaglio intorno al riquadro: le linee finiscono fuori, non sul bordo. */
    public const MARGINE_RITAGLIO = 3.0;

    /** @var list<string> i nomi delle vie scritti nell'ultima mappa (per la guardia, D-VAL-105 A) */
    public static array $ultimiNomi = [];

    /**
     * La mappa, o null se il materiale non basta.
     *
     * @param  array<string, mixed>  $c  la cornice
     * @param  array<string, string>  $colori  ImpostazioniMappe colori
     * @param  array{crediti: string, fuori?: string}  $testi
     * @param  callable(string): bool  $vietato  il nome contiene una parola segreta? (D-VAL-105)
     */
    public static function svg(array $c, Materiale $m, array $colori, array $testi, callable $vietato, float $raggioAngoli = 3.0): ?string
    {
        self::$ultimiNomi = [];
        if (! $m->copre(Cornici::visibile($c), (bool) $c['edifici'])) {
            return null;
        }
        $w = (float) $c['w'];
        $h = (float) $c['h'];
        $s = $w / (2 * $c['raggio']);   // mm per metro
        $p = fn (array $q): array => Geometria::xy($q, $c['lat'], $c['lon'], $s, $w, $h);
        $riq = Cornici::visibile($c);
        $x0 = -self::MARGINE_RITAGLIO;
        $y0 = -self::MARGINE_RITAGLIO;
        $x1 = $w + self::MARGINE_RITAGLIO;
        $y1 = $h + self::MARGINE_RITAGLIO;

        $corpo = '<rect x="0" y="0" width="'.Geometria::n($w).'" height="'.Geometria::n($h).'" fill="'.$colori['fondo'].'"/>';

        // aree: verde, poi acqua e mare
        $aree = function (array $oggetti, ?callable $filtro = null) use ($p, $x0, $y0, $x1, $y1): array {
            $percorsi = [];
            foreach ($oggetti as $o) {
                if ($filtro !== null && ! $filtro($o)) {
                    continue;
                }
                foreach (Geometria::poligoni($o['forma']) as $anelli) {
                    $d = self::poligono($anelli, $p, $x0, $y0, $x1, $y1, 0.0);
                    if ($d !== '') {
                        $percorsi[] = $d;
                    }
                }
            }

            return $percorsi;
        };
        $corpo .= self::percorsi($aree($m->oggetti('verde', $riq)), 'fill="'.$colori['verde'].'"');
        $corpo .= self::percorsi([...$aree($m->oggetti('mare', $riq)), ...$aree($m->oggetti('acqua', $riq))], 'fill="'.$colori['acqua'].'"');

        // fiumi e canali
        $fiumi = [];
        foreach ($m->oggetti('corsi_acqua', $riq) as $o) {
            // larghezza in metri come mappe.py (fiumi 40, il resto 8), i canali un po' più larghi dei fossi
            $largo = match ($o['tipo'] ?? '') { 'river' => 40, 'canal' => 12, default => 8 };
            $spessore = Geometria::n(max(0.6, $largo * $s));
            foreach (Geometria::linee($o['forma']) as $linea) {
                $fiumi[$spessore][] = self::linea($linea, $p, $x0, $y0, $x1, $y1);
            }
        }
        foreach ($fiumi as $spessore => $d) {
            $corpo .= self::percorsi(array_values(array_filter($d)), 'fill="none" stroke="'.$colori['fiumi'].'" stroke-width="'.$spessore.'" stroke-linecap="round" stroke-linejoin="round" stroke-opacity="0.55"');
        }

        // edifici (senza tettoie e pensiline)
        if ($c['edifici']) {
            $edifici = [];
            foreach ($m->oggetti('edifici', $riq) as $o) {
                foreach (Geometria::poligoni($o['forma']) as $anelli) {
                    $d = self::poligono($anelli, $p, $x0, $y0, $x1, $y1, self::EDIFICIO_MINIMO_MM);
                    if ($d !== '') {
                        $edifici[] = $d;
                    }
                }
            }
            $corpo .= self::percorsi($edifici, 'fill="'.$colori['edifici'].'"');
        }

        // ferrovie: i binari (come il prototipo); in galleria più chiari
        $binari = ['su' => [], 'sotto' => []];
        foreach ($m->oggetti('ferrovie', $riq) as $o) {
            if (! in_array($o['tipo'] ?? '', ['rail', 'narrow_gauge'], true)) {
                continue;
            }
            foreach (Geometria::linee($o['forma']) as $linea) {
                $binari[self::sotto($o) ? 'sotto' : 'su'][] = self::linea($linea, $p, $x0, $y0, $x1, $y1);
            }
        }
        $spessoreBinari = Geometria::n(max(0.35, 3 * $s));
        $corpo .= self::percorsi(array_values(array_filter($binari['sotto'])), 'fill="none" stroke="'.$colori['ferrovie'].'" stroke-width="'.$spessoreBinari.'" stroke-dasharray="0.8 0.8" stroke-opacity="0.45"');
        $corpo .= self::percorsi(array_values(array_filter($binari['su'])), 'fill="none" stroke="'.$colori['ferrovie'].'" stroke-width="'.$spessoreBinari.'" stroke-dasharray="1.6 1"');

        // strade: prima le gallerie (sotto tutto), poi bordo e riempimento, le grandi sopra
        $strade = [];
        $perNomi = [];
        foreach ($m->oggetti('strade', $riq) as $o) {
            $tipo = (string) ($o['tipo'] ?? '');
            if (! isset(self::LARGHEZZE[$tipo]) || ($c['raggio'] > 500 && in_array($tipo, self::SOTTILI, true))) {
                continue;
            }
            $sotto = self::sotto($o);
            $linee = [];
            foreach (Geometria::linee($o['forma']) as $linea) {
                $mm = array_map($p, $linea);
                foreach (Geometria::ritagliaLinea($mm, $x0, $y0, $x1, $y1) as $pezzo) {
                    $linee[] = $pezzo;
                }
            }
            if ($linee === []) {
                continue;
            }
            $strade[$sotto ? 'sotto' : 'su'][$tipo][] = $linee;
            if ($c['nomi'] && is_string($o['nome'] ?? null)) {
                $perNomi[] = ['nome' => (string) $o['nome'], 'tipo' => $tipo, 'linee' => $linee];
            }
        }
        $ordine = array_keys(self::LARGHEZZE);
        usort($ordine, fn ($a, $b) => [in_array($a, Etichette::GRANDI, true), self::LARGHEZZE[$a]] <=> [in_array($b, Etichette::GRANDI, true), self::LARGHEZZE[$b]]);
        foreach (['sotto', 'su'] as $livello) {
            foreach (['bordo', 'pieno'] as $passata) {
                foreach ($ordine as $tipo) {
                    if (empty($strade[$livello][$tipo])) {
                        continue;
                    }
                    $largo = max(0.25, self::LARGHEZZE[$tipo] * $s);
                    $d = [];
                    foreach ($strade[$livello][$tipo] as $linee) {
                        foreach ($linee as $linea) {
                            $d[] = Geometria::percorso(Geometria::decimi($linea, false), false);
                        }
                    }
                    $d = array_values(array_filter($d));
                    $sottile = in_array($tipo, ['footway', 'path', 'cycleway', 'steps'], true);
                    if ($passata === 'bordo') {
                        if ($sottile) {
                            continue;
                        }
                        $colore = in_array($tipo, Etichette::GRANDI, true) ? $colori['bordi_grandi'] : $colori['bordi'];
                        $stile = 'fill="none" stroke="'.$colore.'" stroke-width="'.Geometria::n($largo + 0.55).'" stroke-linecap="round" stroke-linejoin="round"';
                        if ($livello === 'sotto') {
                            $stile = 'fill="none" stroke="'.$colore.'" stroke-width="'.Geometria::n($largo + 0.55).'" stroke-dasharray="0.9 0.6" stroke-opacity="0.8"';
                        }
                    } else {
                        // un attributo una volta sola: due «stroke-opacity» renderebbero l'SVG illeggibile per mPDF
                        $opacita = $livello === 'sotto' ? '0.55' : ($sottile ? '0.9' : null);
                        $stile = 'fill="none" stroke="'.$colori['strade'].'" stroke-width="'.Geometria::n($sottile ? max(0.25, $largo) : $largo).'" stroke-linecap="round" stroke-linejoin="round"'
                            .($opacita !== null ? ' stroke-opacity="'.$opacita.'"' : '');
                    }
                    $corpo .= self::percorsi($d, $stile);
                }
            }
        }

        // cerchi delle distanze, attorno alla casa (le mappe centrate sulla casa)
        $ostacoli = [];
        $anelli = '';
        if ($c['anelli']) {
            foreach (self::anelli((float) $c['raggio']) as $r) {
                $rr = $r * $s;
                $anelli .= '<circle cx="'.Geometria::n($w / 2).'" cy="'.Geometria::n($h / 2).'" r="'.Geometria::n($rr).'" fill="none" stroke="'.$colori['casa'].'" stroke-opacity="0.35" stroke-width="0.35" stroke-dasharray="1.2 1"/>';
                $tx = $w / 2 + $rr * 0.7071 + 1;
                $ty = $h / 2 + $rr * 0.7071 + 1;
                $etichetta = Tratti::metriTesto($r);
                $largo = mb_strlen($etichetta) * 0.6 * 2.2;
                if ($tx + $largo < $w - 2 && $ty < $h - 2) {
                    $anelli .= self::testoMono($tx, $ty, $etichetta, $colori['casa']);
                    $ostacoli[] = [$tx - 0.5, $ty - 2.2, $tx + $largo + 0.5, $ty + 0.8];
                }
            }
        }

        // ingombri: casa, numeri, scala, crediti (i nomi non li toccano)
        $casa = null;
        if ($c['casa'] !== null) {
            $casa = $p([$c['casa'][1], $c['casa'][0]]);
            $ostacoli[] = [$casa[0] - 5.6, $casa[1] - 12, $casa[0] + 5.6, $casa[1] + 1.5];
        }
        $segni = self::segni($c['segni'], $p, $casa, $w, $h);
        foreach ($segni as $g) {
            $ostacoli[] = [$g['x'] - 3, $g['y'] - 3, $g['x'] + 3, $g['y'] + 3];
        }
        $scala = '';
        if ($c['scala']) {
            $metri = Tratti::metriScala((float) $c['raggio']);
            [$scala, $ingombro] = Tratti::scala($h, $metri, $metri * $s);
            $ostacoli[] = $ingombro;
        }
        [$crediti, $ingombroCrediti] = Tratti::crediti($w, $h, $testi['crediti']);
        $ostacoli[] = $ingombroCrediti;

        // i nomi delle vie
        if ($c['nomi']) {
            $grandezza = $c['raggio'] <= 500 ? 2.15 : 1.9;
            if ($w < 60) {
                $grandezza = 1.7;
            }
            foreach (Etichette::scegli($perNomi, $w, $h, $grandezza, (int) $c['nomi_max'], $ostacoli, $vietato) as [$nome, $cx, $cy, $angolo]) {
                $corpo .= Tratti::testoConAlone($cx, $cy, $nome, $grandezza, $colori['nomi'], $angolo);
                self::$ultimiNomi[] = $nome;
            }
        }
        $corpo .= $anelli;

        // il confine della zona
        foreach ($c['poligoni'] as $anelliZona) {
            $mm = [];
            foreach ($anelliZona as $anello) {
                $mm[] = array_map(fn ($q) => $p([(float) $q[1], (float) $q[0]]), $anello);
            }
            $d = '';
            foreach ($mm as $anello) {
                $d .= Geometria::percorso(Geometria::decimi($anello, true), true);
            }
            if ($d !== '') {
                $corpo .= '<path d="'.$d.'" fill="'.$colori['zona'].'" fill-opacity="0.13" fill-rule="evenodd" stroke="'.$colori['zona'].'" stroke-width="0.7" stroke-dasharray="2 1.2" stroke-linejoin="round"/>';
            }
        }

        // la casa, poi i numeri sopra
        if ($casa !== null && $casa[0] > -5 && $casa[0] < $w + 5 && $casa[1] > -5 && $casa[1] < $h + 5) {
            $corpo .= '<circle cx="'.Geometria::n($casa[0]).'" cy="'.Geometria::n($casa[1]).'" r="6.5" fill="'.$colori['alone'].'" fill-opacity="0.16"/>'
                .Tratti::segnaposto($casa[0], $casa[1], $colori['casa'], $w < 60 ? 0.8 : 1.0);
        }
        foreach ($segni as $g) {
            if ($g['spostato']) {
                $corpo .= '<path d="M'.Geometria::n($g['x0']).' '.Geometria::n($g['y0']).'L'.Geometria::n($g['x']).' '.Geometria::n($g['y']).'" stroke="#111827" stroke-width="0.25"/>'
                    .'<circle cx="'.Geometria::n($g['x0']).'" cy="'.Geometria::n($g['y0']).'" r="0.55" fill="#111827"/>';
            }
            $corpo .= Tratti::numero($g['x'], $g['y'], $g['numero'], $g['tipo'] === 'vendita' ? $colori['vendite'] : $colori['annunci'], $g['tipo'] === 'vendita', $w < 60 ? 2.3 : 2.6);
        }
        $corpo .= $scala.$crediti.Tratti::angoli($w, $h, $raggioAngoli);

        return Tratti::svg($w, $h, $corpo);
    }

    /** La strada o il binario passa sotto terra? (galleria, o livello negativo; non i passaggi sotto un palazzo) */
    public static function sotto(array $o): bool
    {
        $galleria = (string) ($o['galleria'] ?? '');
        if ($galleria !== '' && $galleria !== 'no' && $galleria !== 'building_passage') {
            return true;
        }

        return isset($o['livello']) && is_numeric($o['livello']) && (int) $o['livello'] < 0 && $galleria !== 'building_passage';
    }

    /** I cerchi delle distanze per una mappa di $raggio metri: due, tondi, dentro la mappa (100/200 a 330 m, come il fac-simile). */
    public static function anelli(float $raggio): array
    {
        foreach ([[100, 200], [250, 500], [500, 1000], [1000, 2000]] as $coppia) {
            if ($coppia[1] <= $raggio * 0.75) {
                $scelta = $coppia;
            }
        }

        return $scelta ?? [(int) (round($raggio * 0.3 / 10) * 10), (int) (round($raggio * 0.6 / 10) * 10)];
    }

    /**
     * I numeri delle case sulla pagina; quelli che si coprono fra loro o
     * coprono la casa si allargano a ventaglio, con una linea al punto vero.
     *
     * @return list<array{x: float, y: float, x0: float, y0: float, numero: int, tipo: string, spostato: bool}>
     */
    public static function segni(array $segni, callable $p, ?array $casa, float $w, float $h, float $raggio = 2.6): array
    {
        $fuori = [];
        foreach ($segni as $s) {
            [$x, $y] = $p([$s['lon'], $s['lat']]);
            $fuori[] = ['x' => $x, 'y' => $y, 'x0' => $x, 'y0' => $y, 'numero' => (int) $s['numero'], 'tipo' => (string) $s['tipo'], 'spostato' => false];
        }
        $distanza = 2 * $raggio + 0.4;
        // i gruppi di numeri che si toccano
        $gruppi = [];
        $preso = [];
        foreach ($fuori as $i => $a) {
            if (isset($preso[$i])) {
                continue;
            }
            $gruppo = [$i];
            $preso[$i] = true;
            for ($k = 0; $k < count($gruppo); $k++) {
                foreach ($fuori as $j => $b) {
                    if (! isset($preso[$j]) && hypot($fuori[$gruppo[$k]]['x0'] - $b['x0'], $fuori[$gruppo[$k]]['y0'] - $b['y0']) < $distanza) {
                        $gruppo[] = $j;
                        $preso[$j] = true;
                    }
                }
            }
            $gruppi[] = $gruppo;
        }
        foreach ($gruppi as $gruppo) {
            $n = count($gruppo);
            $cx = array_sum(array_map(fn ($i) => $fuori[$i]['x0'], $gruppo)) / $n;
            $cy = array_sum(array_map(fn ($i) => $fuori[$i]['y0'], $gruppo)) / $n;
            if ($n > 1) {
                $r = $distanza / (2 * sin(M_PI / $n)) + 0.3;
                foreach (array_values($gruppo) as $k => $i) {
                    $angolo = -M_PI / 2 + 2 * M_PI * $k / $n;
                    $fuori[$i]['x'] = $cx + $r * cos($angolo);
                    $fuori[$i]['y'] = $cy + $r * sin($angolo);
                    $fuori[$i]['spostato'] = true;
                }
            }
        }
        // chi copre il segnaposto della casa (che sta sopra il punto) si sposta di lato
        if ($casa !== null) {
            foreach ($fuori as &$g) {
                $dentroPin = $g['x'] > $casa[0] - 5.6 - $raggio && $g['x'] < $casa[0] + 5.6 + $raggio && $g['y'] > $casa[1] - 12 - $raggio && $g['y'] < $casa[1] + 1.5 + $raggio;
                if ($dentroPin) {
                    $verso = $g['x0'] >= $casa[0] ? 1 : -1;
                    $g['x'] = $casa[0] + $verso * (5.6 + $raggio + 1.2);
                    $g['spostato'] = true;
                }
            }
            unset($g);
        }
        foreach ($fuori as &$g) {
            $g['x'] = max($raggio + 0.5, min($w - $raggio - 0.5, $g['x']));
            $g['y'] = max($raggio + 0.5, min($h - $raggio - 0.5, $g['y']));
        }
        unset($g);

        return $fuori;
    }

    /** Un poligono (anelli in [lon, lat]) in millimetri, ritagliato e semplificato; '' se fuori o troppo piccolo. */
    private static function poligono(array $anelli, callable $p, float $x0, float $y0, float $x1, float $y1, float $minimo): string
    {
        $d = '';
        foreach (array_values($anelli) as $k => $anello) {
            $mm = array_map($p, $anello);
            if ($k === 0 && $minimo > 0) {
                $xs = array_column($mm, 0);
                $ys = array_column($mm, 1);
                if (max($xs) - min($xs) < $minimo && max($ys) - min($ys) < $minimo) {
                    return '';
                }
            }
            $tagliato = Geometria::ritagliaAnello($mm, $x0, $y0, $x1, $y1);
            if ($tagliato === []) {
                if ($k === 0) {
                    return '';
                }

                continue;
            }
            // bordo esterno in un verso, cortili nell'altro: col riempimento normale (nonzero) i cortili
            // restano vuoti e due edifici che si sovrappongono restano pieni (con «evenodd» si bucherebbero)
            $decimi = Geometria::decimi($tagliato, true);
            if (count($decimi) >= 3 && (Geometria::areaConSegno($decimi) > 0) !== ($k === 0)) {
                $decimi = array_reverse($decimi);
            }
            $d .= Geometria::percorso($decimi, true);
        }

        return $d;
    }

    /** Una linea in millimetri, ritagliata e semplificata (più pezzi, se esce ed entra). */
    private static function linea(array $linea, callable $p, float $x0, float $y0, float $x1, float $y1): string
    {
        $d = '';
        foreach (Geometria::ritagliaLinea(array_map($p, $linea), $x0, $y0, $x1, $y1) as $pezzo) {
            $d .= Geometria::percorso(Geometria::decimi($pezzo, false), false);
        }

        return $d;
    }

    /** Tanti percorsi con lo stesso stile, uniti in pochi elementi <path> (spezzati a PEZZO caratteri). */
    public static function percorsi(array $percorsi, string $stile): string
    {
        $fuori = '';
        $pezzo = '';
        foreach ($percorsi as $d) {
            if ($d === '') {
                continue;
            }
            if ($pezzo !== '' && strlen($pezzo) + strlen($d) > self::PEZZO) {
                $fuori .= '<path d="'.$pezzo.'" '.$stile.'/>';
                $pezzo = '';
            }
            $pezzo .= $d;
        }

        return $pezzo !== '' ? $fuori.'<path d="'.$pezzo.'" '.$stile.'/>' : $fuori;
    }

    /** L'etichetta di un cerchio delle distanze, in IBM Plex Mono con l'alone. */
    private static function testoMono(float $x, float $y, string $testo, string $colore): string
    {
        $t = htmlspecialchars($testo, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
        $a = ' x="'.Geometria::n($x).'" y="'.Geometria::n($y).'" font-size="2.2" font-family="'.Grafici::MONO.'"';

        return '<text'.$a.' fill="none" stroke="#ffffff" stroke-width="0.8" stroke-linejoin="round">'.$t.'</text><text'.$a.' fill="'.$colore.'">'.$t.'</text>';
    }
}