<?php

namespace App\Support\Valutazione\Mappe;

/**
 * IL MATERIALE DELLE MAPPE DI UN REPORT (lotto 4, D-VAL-102): gli oggetti
 * del magazzino geografico che servono alle mappe e alla vista, il comune
 * della casa coi suoi dati ufficiali e la fibra nel punto.
 *
 * Lo prepara Raccolta PRIMA di fare il PDF (il report non chiama nessuno
 * mentre si compone): nell'anteprima dall'Admin, nella diagnosi e domani
 * nella generazione a passi (lotto 6), che lo potrà conservare.
 *
 *  - Gli oggetti di più risposte si uniscono: uno strato, un id, una volta.
 *  - Ogni oggetto ha già il suo riquadro in gradi («r»), per scegliere in
 *    fretta quelli di una mappa.
 *  - «coperti» dice quali riquadri sono stati letti per intero e se con gli
 *    edifici: una mappa si disegna solo dentro un riquadro letto per
 *    intero (mai una mappa a metà, D-VAL-97).
 *  - «errori» dice in parole nostre che cosa non si è potuto leggere.
 *  - Si conserva come array semplice (perArray/daArray), anche nella cache.
 */
final class Materiale
{
    /** Cambia quando cambia la forma dell'array. */
    public const VERSIONE = 1;

    /**
     * @param  array<string, list<array<string, mixed>>>  $strati  strato => oggetti (ognuno con «forma» e «r»)
     * @param  list<array{r: array{0: float, 1: float, 2: float, 3: float}, edifici: bool}>  $coperti
     * @param  list<string>  $errori
     * @param  array<string, mixed>|null  $comune  istat, nome, provincia, come (confine o nome), zona_sismica, idr_pop_p2, frane_pop_p3p4
     * @param  array<string, mixed>|null  $fibra  la risposta della fibra (LettoreFibra::cerca), o null
     * @param  array<string, string|null>  $edizioni  regione => edizione della copia
     * @param  float  $latoKm  il riquadro massimo del magazzino quando si è letto (le cornici lo usano: si disegna ciò che si è letto)
     */
    public function __construct(
        public readonly float $lat,
        public readonly float $lon,
        public readonly array $strati,
        public readonly array $coperti,
        public readonly array $errori = [],
        public readonly ?array $comune = null,
        public readonly ?array $fibra = null,
        public readonly ?string $fibraMotivo = null,
        public readonly ?string $fibraEdizione = null,
        public readonly array $edizioni = [],
        public readonly float $latoKm = 3.0,
    ) {}

    /**
     * Gli oggetti di uno strato che toccano il riquadro [ovest, sud, est, nord].
     *
     * @return list<array<string, mixed>>
     */
    public function oggetti(string $strato, array $riquadro): array
    {
        [$o, $s, $e, $n] = $riquadro;
        $fuori = [];
        foreach ($this->strati[$strato] ?? [] as $x) {
            $r = $x['r'];
            if ($r[2] >= $o && $r[0] <= $e && $r[3] >= $s && $r[1] <= $n) {
                $fuori[] = $x;
            }
        }

        return $fuori;
    }

    /** Il riquadro è stato letto per intero (con gli edifici, se servono)? */
    public function copre(array $riquadro, bool $edifici): bool
    {
        foreach ($this->coperti as $c) {
            if ($edifici && ! $c['edifici']) {
                continue;
            }
            $r = $c['r'];
            if ($r[0] <= $riquadro[0] + 1e-9 && $r[1] <= $riquadro[1] + 1e-9 && $r[2] >= $riquadro[2] - 1e-9 && $r[3] >= $riquadro[3] - 1e-9) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function perArray(): array
    {
        return [
            'versione' => self::VERSIONE,
            'lat' => $this->lat, 'lon' => $this->lon,
            'strati' => $this->strati, 'coperti' => $this->coperti, 'errori' => $this->errori,
            'comune' => $this->comune, 'fibra' => $this->fibra, 'fibra_motivo' => $this->fibraMotivo,
            'fibra_edizione' => $this->fibraEdizione, 'edizioni' => $this->edizioni, 'lato_km' => $this->latoKm,
        ];
    }

    public static function daArray(mixed $a): ?self
    {
        if (! is_array($a) || ($a['versione'] ?? null) !== self::VERSIONE || ! is_numeric($a['lat'] ?? null) || ! is_numeric($a['lon'] ?? null)
            || ! is_array($a['strati'] ?? null) || ! is_array($a['coperti'] ?? null)) {
            return null;
        }

        return new self(
            (float) $a['lat'], (float) $a['lon'], $a['strati'], $a['coperti'],
            array_values(array_map('strval', is_array($a['errori'] ?? null) ? $a['errori'] : [])),
            is_array($a['comune'] ?? null) ? $a['comune'] : null,
            is_array($a['fibra'] ?? null) ? $a['fibra'] : null,
            is_string($a['fibra_motivo'] ?? null) ? $a['fibra_motivo'] : null,
            is_string($a['fibra_edizione'] ?? null) ? $a['fibra_edizione'] : null,
            is_array($a['edizioni'] ?? null) ? $a['edizioni'] : [],
            is_numeric($a['lato_km'] ?? null) ? (float) $a['lato_km'] : 3.0,
        );
    }

    /**
     * Il riquadro in gradi [ovest, sud, est, nord] di una forma del magazzino.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public static function riquadroDi(array $forma): ?array
    {
        $o = $s = INF;
        $e = $n = -INF;
        $visita = function (mixed $c) use (&$visita, &$o, &$s, &$e, &$n): void {
            if (! is_array($c) || $c === []) {
                return;
            }
            if (is_numeric($c[0] ?? null) && is_numeric($c[1] ?? null) && ! is_array($c[0])) {
                $o = min($o, (float) $c[0]);
                $e = max($e, (float) $c[0]);
                $s = min($s, (float) $c[1]);
                $n = max($n, (float) $c[1]);

                return;
            }
            foreach ($c as $x) {
                $visita($x);
            }
        };
        $visita($forma['coordinate'] ?? null);

        return is_finite($o) ? [$o, $s, $e, $n] : null;
    }
}