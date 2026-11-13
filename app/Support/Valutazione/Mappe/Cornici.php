<?php

namespace App\Support\Valutazione\Mappe;

/**
 * LE CORNICI DELLE MAPPE (lotto 4, D-VAL-97): per ogni mappa del report,
 * il centro, il raggio, le misure sulla pagina e che cosa ci va dentro.
 * Le usano sia Raccolta (che cosa leggere dal magazzino) sia il disegno
 * (che cosa disegnare): così leggono e disegnano la stessa cosa.
 *
 *  - Il raggio è in metri, dal centro al bordo, in larghezza (l'altezza
 *    viene dalle proporzioni della mappa sulla pagina).
 *  - Le case vicine e le vendute si allargano da sole finché tutte le case
 *    ci stanno, coi loro numeri, fino al raggio massimo; chi resta fuori
 *    va nella didascalia («fuori»).
 *  - La zona si adatta al suo poligono e si centra su di lui (la casa resta
 *    dentro); se il poligono è più grande del massimo, la mappa resta
 *    centrata sulla casa e «oltre» lo dice.
 *  - Oltre 700 m di raggio le mappe non disegnano gli edifici (mappe.py):
 *    a quella scala sarebbero puntini, e peserebbero molto.
 *  - Nessuna cornice supera il riquadro massimo del magazzino (Admin del
 *    magazzino geografico, di fabbrica 3 km di lato).
 */
final class Cornici
{
    /** Le misure delle mappe sulla pagina, in mm (fac-simile REV0). */
    public const MISURE = [
        'copertina' => [210, 118],
        'zona' => [178, 112],
        'vicine' => [178, 100],
        'schede' => [40, 40],
        'vendute' => [178, 70],
        'vista' => [178, 100],
    ];

    /** Oltre questo raggio (m) le mappe non disegnano gli edifici. */
    public const EDIFICI_FINO_A = 700;

    /** Lo spazio (mm) che un segnaposto numerato vuole dal bordo. */
    public const MARGINE_SEGNO_MM = 5.0;

    /** Lo spazio (m) in più intorno a ciò che si legge dal magazzino. */
    public const MARGINE_LETTURA_M = 20.0;

    /** Di quanto il poligono della zona si allontana dal bordo (in frazione del raggio). */
    public const MARGINE_ZONA = 0.08;

    /**
     * Le cornici delle mappe accese.
     *
     * @param  array<string, mixed>  $dati  i dati puliti (annunci e vendite già tagliati, come in Contesto)
     * @param  array<string, mixed>  $impostazioni  ImpostazioniMappe::normalizza()
     * @param  array<string, bool>  $accese  mappa => accesa (copertina, zona, vicine, schede, vendute, vista)
     * @param  float  $latoMassimoKm  il riquadro massimo del magazzino
     * @return array<string, array<string, mixed>> chiave (copertina, zona, vicine, vendute, vista, scheda_N) => cornice
     */
    public static function tutte(array $dati, array $impostazioni, array $accese, float $latoMassimoKm): array
    {
        $lat = (float) ($dati['input']['latitude'] ?? 0);
        $lon = (float) ($dati['input']['longitude'] ?? 0);
        $mezzoMassimo = max(50.0, $latoMassimoKm * 500 - self::MARGINE_LETTURA_M - 1);
        $m = $impostazioni['mappe'];
        $fuori = [];

        if ($accese['copertina'] ?? false) {
            $fuori['copertina'] = self::cornice('copertina', $lat, $lon, min($m['copertina']['raggio'], $mezzoMassimo), $m['copertina']);
        }
        if ($accese['vista'] ?? false) {
            $larghezza = (float) $impostazioni['vista']['larghezza'];
            $c = self::cornice('vista', $lat, $lon, min($larghezza / 2, $mezzoMassimo), ['nomi' => true, 'nomi_max' => $impostazioni['vista']['nomi_max'], 'anelli' => false, 'scala' => true]);
            // la vista vede anche dietro e di lato (edifici alti, prospettiva): si legge di più
            $c['lettura'] = min(0.75 * $larghezza, $mezzoMassimo);
            $c['edifici'] = true;
            $fuori['vista'] = $c;
        }
        $segniAnnunci = [];
        foreach (array_values($dati['comparables'] ?? []) as $i => $a) {
            if (is_numeric($a['latitude'] ?? null) && is_numeric($a['longitude'] ?? null)) {
                $segniAnnunci[] = ['lat' => (float) $a['latitude'], 'lon' => (float) $a['longitude'], 'tipo' => 'annuncio', 'numero' => $i + 1];
            }
        }
        $segniVendite = [];
        foreach (array_values($dati['short'] ?? []) as $i => $v) {
            if (is_numeric($v['latitude'] ?? null) && is_numeric($v['longitude'] ?? null)) {
                $segniVendite[] = ['lat' => (float) $v['latitude'], 'lon' => (float) $v['longitude'], 'tipo' => 'vendita', 'numero' => $i + 1];
            }
        }
        foreach (['vicine' => [...$segniAnnunci, ...$segniVendite], 'vendute' => $segniVendite] as $nome => $segni) {
            if (! ($accese[$nome] ?? false) || $segni === []) {
                continue;
            }
            $f = $m[$nome];
            [$w, $h] = self::MISURE[$nome];
            $raggio = (float) $f['raggio'];
            $massimo = min((float) $f['raggio_max'], $mezzoMassimo);
            if ($f['allarga']) {
                foreach ($segni as $s) {
                    $raggio = max($raggio, self::raggioPer($s, $lat, $lon, $w, $h, $raggio));
                }
                $raggio = min(max($raggio, (float) $f['raggio']), max((float) $f['raggio'], $massimo));
                $raggio = min(ceil($raggio / 10) * 10, max((float) $f['raggio'], $massimo));
            }
            $c = self::cornice($nome, $lat, $lon, $raggio, $f);
            $c['segni'] = [];
            $c['fuori'] = ['annuncio' => [], 'vendita' => []];
            foreach ($segni as $s) {
                if (self::raggioPer($s, $lat, $lon, $w, $h, $raggio) <= $raggio + 1e-6) {
                    $c['segni'][] = $s;
                } else {
                    $c['fuori'][$s['tipo']][] = $s['numero'];
                }
            }
            $fuori[$nome] = $c;
        }
        if ($accese['zona'] ?? false) {
            $f = $m['zona'];
            [$w, $h] = self::MISURE['zona'];
            $poligoni = is_array($dati['omi']['poligoni'] ?? null) ? $dati['omi']['poligoni'] : [];
            $massimo = min((float) $f['raggio_max'], $mezzoMassimo);
            $centro = [$lat, $lon];
            $raggio = min((float) $f['raggio'], $massimo);
            $oltre = false;
            if ($poligoni !== []) {
                $punti = array_merge(...array_map(fn ($p) => $p[0] ?? [], $poligoni));
                $punti[] = [$lat, $lon];
                $lats = array_map(fn ($p) => (float) $p[0], $punti);
                $lons = array_map(fn ($p) => (float) $p[1], $punti);
                $mezzo = [(min($lats) + max($lats)) / 2, (min($lons) + max($lons)) / 2];
                [$est, $nord] = Geometria::metri(max($lats), max($lons), $mezzo[0], $mezzo[1]);
                $serve = max($est, $nord * $w / $h) * (1 + self::MARGINE_ZONA);
                if ($serve <= $massimo) {
                    $centro = $mezzo;
                    $raggio = max($raggio, $serve);
                } else {
                    $raggio = $massimo;
                    $oltre = true;
                }
                $raggio = min(ceil($raggio / 10) * 10, max($massimo, (float) $f['raggio']));
            }
            $c = self::cornice('zona', $centro[0], $centro[1], $raggio, $f);
            $c['casa'] = [$lat, $lon];
            $c['poligoni'] = $poligoni;
            $c['oltre'] = $oltre;
            $fuori['zona'] = $c;
        }
        if ($accese['schede'] ?? false) {
            foreach ($segniAnnunci as $s) {
                $c = self::cornice('schede', $s['lat'], $s['lon'], min($m['schede']['raggio'], $mezzoMassimo), $m['schede']);
                $c['casa'] = [$lat, $lon];
                $c['segni'] = [$s];
                $fuori['scheda_'.$s['numero']] = $c;
            }
        }

        return $fuori;
    }

    /**
     * Il riquadro in gradi che una cornice deve leggere dal magazzino: la
     * mappa intera (o di più, per la vista) e un margine.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function daLeggere(array $c): array
    {
        $mezzoEst = ($c['lettura'] ?? $c['raggio']) + self::MARGINE_LETTURA_M;
        $mezzoNord = (isset($c['lettura']) ? $c['lettura'] : $c['raggio'] * $c['h'] / $c['w']) + self::MARGINE_LETTURA_M;

        return Geometria::riquadro($c['lat'], $c['lon'], $mezzoEst, $mezzoNord);
    }

    /** Il riquadro in gradi della mappa così come si vede (senza margine). */
    public static function visibile(array $c): array
    {
        return Geometria::riquadro($c['lat'], $c['lon'], $c['raggio'], $c['raggio'] * $c['h'] / $c['w']);
    }

    /** @param  array<string, mixed>  $f  le scelte della mappa */
    private static function cornice(string $tipo, float $lat, float $lon, float $raggio, array $f): array
    {
        [$w, $h] = self::MISURE[$tipo];
        $casa = in_array($tipo, ['copertina', 'vicine', 'vendute', 'vista'], true) ? [$lat, $lon] : null;

        return [
            'tipo' => $tipo, 'lat' => $lat, 'lon' => $lon, 'raggio' => (float) $raggio, 'w' => $w, 'h' => $h,
            'edifici' => $raggio <= self::EDIFICI_FINO_A,
            'casa' => $casa,
            'segni' => [], 'fuori' => ['annuncio' => [], 'vendita' => []], 'poligoni' => [], 'oltre' => false,
            'nomi' => (bool) ($f['nomi'] ?? true), 'nomi_max' => (int) ($f['nomi_max'] ?? 10),
            'anelli' => (bool) ($f['anelli'] ?? false), 'scala' => (bool) ($f['scala'] ?? false),
        ];
    }

    /**
     * Il raggio che serve perché il segno $s stia nella mappa w × h (mm)
     * centrata sulla casa, col suo spazio dal bordo. $raggio serve a stimare
     * quanti metri vale lo spazio in mm (alla scala di quel raggio).
     */
    private static function raggioPer(array $s, float $lat0, float $lon0, float $w, float $h, float $raggio): float
    {
        [$est, $nord] = Geometria::metri($s['lat'], $s['lon'], $lat0, $lon0);
        $spazio = self::MARGINE_SEGNO_MM * 2 / $w;   // in frazione del raggio
        $perEst = abs($est) / max(0.1, 1 - $spazio);
        $perNord = abs($nord) * $w / max(1.0, $h - self::MARGINE_SEGNO_MM * 2);

        return max($perEst, $perNord);
    }
}