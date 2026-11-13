<?php

namespace App\Support\Valutazione\Mappe;

use Normalizer;

/**
 * LA CASA SULLA MAPPA (lotto 4, D-VAL-98, D-VAL-99): l'edificio della casa
 * e la via dell'indirizzo, con le regole di precisione del 05/11.
 *
 *  - L'edificio della casa è il poligono che contiene le coordinate (fuori
 *    dai cortili); se più d'uno, il più piccolo. Se le coordinate non
 *    cadono in un edificio, il più vicino solo se il suo bordo è entro la
 *    soglia (di fabbrica 20 m); oltre, niente. Tettoie e pensiline non
 *    contano.
 *  - Oltre la soglia dell'isolato (di fabbrica 3.000 m²) è un isolato intero
 *    che OpenStreetMap disegna come un solo edificio.
 *  - La via dell'indirizzo si cerca col nome normalizzato (maiuscole,
 *    accenti, punti, spazi, apostrofi), con le abbreviazioni sciolte
 *    (C.so → Corso…; «S.» vale per San, Santo, Santa e Sant') e anche fra
 *    gli altri nomi della via (nome italiano, ufficiale…). Se non c'è,
 *    «non trovata»: mai una via diversa.
 *  - Il controllo: la distanza fra le coordinate e la via trovata, contro
 *    la soglia (di fabbrica 30 m). Il lotto 6 ferma il report se non torna.
 */
final class Casa
{
    /** I tipi di edificio che non contano (tettoie e pensiline, vista.py). */
    public const NON_EDIFICI = ['roof', 'carport'];

    /** Fin dove (m) si cerca la via dell'indirizzo intorno alla casa. */
    public const RAGGIO_VIA = 300.0;

    /**
     * L'edificio della casa, o null.
     *
     * @param  list<array<string, mixed>>  $edifici  gli oggetti dello strato edifici
     * @return array{anelli: list<list<array{0: float, 1: float}>>, id: mixed, area: float, isolato: bool, distanza: float}|null
     */
    public static function edificio(array $edifici, float $lat, float $lon, float $sogliaM, float $isolatoM2): ?array
    {
        $punto = [0.0, 0.0];
        $dentro = [];
        $vicino = null;
        foreach ($edifici as $o) {
            if (in_array((string) ($o['tipo'] ?? ''), self::NON_EDIFICI, true)) {
                continue;
            }
            foreach (Geometria::poligoni($o['forma'] ?? null) as $anelli) {
                $metri = array_map(fn (array $anello) => array_map(fn ($q) => Geometria::metri((float) $q[1], (float) $q[0], $lat, $lon), $anello), $anelli);
                if ($metri === [] || count($metri[0]) < 3) {
                    continue;
                }
                $area = abs(Geometria::areaConSegno($metri[0]));
                if (Geometria::dentroPoligono($punto, $metri)) {
                    $dentro[] = ['anelli' => $anelli, 'id' => $o['id'] ?? null, 'area' => $area, 'distanza' => 0.0];

                    continue;
                }
                $bordo = Geometria::dalBordo($punto, $metri[0]);
                if ($bordo <= $sogliaM && ($vicino === null || $bordo < $vicino['distanza'])) {
                    $vicino = ['anelli' => $anelli, 'id' => $o['id'] ?? null, 'area' => $area, 'distanza' => $bordo];
                }
            }
        }
        if ($dentro !== []) {
            usort($dentro, fn ($a, $b) => $a['area'] <=> $b['area']);
            $scelto = $dentro[0];
        } else {
            $scelto = $vicino;
        }
        if ($scelto === null) {
            return null;
        }
        $scelto['isolato'] = $scelto['area'] > $isolatoM2;

        return $scelto;
    }

    /**
     * La via dell'indirizzo fra le strade intorno alla casa.
     *
     * @param  list<array<string, mixed>>  $strade
     * @param  list<array{0: string, 1: string}>  $abbreviazioni
     * @return array{trovata: bool, nome: ?string, metri: ?float, linee: list<list<array{0: float, 1: float}>>}
     */
    public static function via(array $strade, float $lat, float $lon, string $indirizzo, array $abbreviazioni): array
    {
        $cercate = self::varianti($indirizzo, $abbreviazioni);
        $nessuna = ['trovata' => false, 'nome' => null, 'metri' => null, 'linee' => []];
        if ($cercate === []) {
            return $nessuna;
        }
        $migliore = null;
        $linee = [];
        foreach ($strade as $o) {
            $nomi = array_filter([(string) ($o['nome'] ?? ''), ...explode(';', (string) ($o['altri_nomi'] ?? ''))], fn ($n) => trim($n) !== '');
            $uguale = false;
            foreach ($nomi as $nome) {
                if (array_intersect(self::varianti($nome, $abbreviazioni), $cercate) !== []) {
                    $uguale = true;

                    break;
                }
            }
            if (! $uguale) {
                continue;
            }
            foreach (Geometria::linee($o['forma'] ?? null) as $linea) {
                $metri = array_map(fn ($q) => Geometria::metri((float) $q[1], (float) $q[0], $lat, $lon), $linea);
                $d = Geometria::daLinea([0.0, 0.0], $metri);
                if ($d > self::RAGGIO_VIA) {
                    continue;
                }
                if ($migliore === null || $d < $migliore['metri']) {
                    $migliore = ['trovata' => true, 'nome' => (string) ($o['nome'] ?? ''), 'metri' => $d, 'linee' => []];
                }
                // tutti i tratti col nome giusto entro il raggio, anche di più vie dei dati
                $linee[] = $linea;
            }
        }
        if ($migliore !== null) {
            $migliore['metri'] = round($migliore['metri'], 1);
            $migliore['linee'] = $linee;
        }

        return $migliore ?? $nessuna;
    }

    /**
     * Le forme normalizzate di un nome di via: minuscole, senza accenti, punti
     * e apostrofi, abbreviazioni sciolte. «S.» dà quattro forme (San, Santo,
     * Santa, Sant').
     *
     * @param  list<array{0: string, 1: string}>  $abbreviazioni
     * @return list<string>
     */
    public static function varianti(string $nome, array $abbreviazioni): array
    {
        $t = ' '.mb_strtolower(trim($nome), 'UTF-8').' ';
        $t = str_replace(["\u{2019}", '`', '´'], "'", $t);
        // le abbreviazioni del tipo di via, solo in testa al nome («V.» è «Via» in «V. Roma»,
        // non in «Corso V. Emanuele»), una volta, dalla più lunga (così «P.zza» prima di «P.za»)
        usort($abbreviazioni, fn ($a, $b) => mb_strlen($b[0]) <=> mb_strlen($a[0]));
        foreach ($abbreviazioni as [$corta, $lunga]) {
            $c = mb_strtolower(trim($corta), 'UTF-8');
            if ($c === '') {
                continue;
            }
            $schema = '/^ '.preg_quote($c, '/').(str_ends_with($c, '.') ? '\s*' : '(?=[\s,])').'/u';
            $nuovo = (string) preg_replace($schema, ' '.mb_strtolower($lunga, 'UTF-8').' ', $t, 1, $quante);
            if ($quante > 0) {
                $t = $nuovo;

                break;
            }
        }
        $forme = [$t];
        if (preg_match('/(?<=[\s(])s\.\s*/u', $t) === 1) {
            $forme = array_map(fn ($santo) => (string) preg_replace('/(?<=[\s(])s\.\s*/u', $santo, $t), ['san ', 'santo ', 'santa ', "sant'"]);
        }
        $fuori = [];
        foreach ($forme as $f) {
            $n = class_exists(Normalizer::class) ? (string) Normalizer::normalize($f, Normalizer::FORM_D) : $f;
            $n = (string) preg_replace('/\p{Mn}+/u', '', $n);
            $n = (string) preg_replace("/[.'’,\-\/]+/u", ' ', $n);
            $n = trim((string) preg_replace('/\s+/u', ' ', $n));
            if ($n !== '') {
                $fuori[] = $n;
            }
        }

        return array_values(array_unique($fuori));
    }

    /**
     * Il controllo della casa sulla mappa (D-VAL-99), dal materiale:
     * l'edificio colorato (edificio, isolato o niente), la via
     * dell'indirizzo trovata o no col nome dei dati e la distanza, il
     * verdetto. Lo mostrano l'anteprima e «Prova un indirizzo»; al lotto 6
     * fermerà il report prima dell'incasso. Null se il materiale non copre
     * l'intorno della casa con gli edifici (allora non si può dire).
     *
     * @param  array<string, mixed>  $imp  ImpostazioniMappe::normalizza()
     * @return array{edificio: ?string, edificio_m: ?float, area_m2: ?float, via: array{trovata: bool, nome: ?string, metri: ?float}, esito: string, soglia_m: float}|null
     */
    public static function controllo(Materiale $m, float $lat, float $lon, string $indirizzo, array $imp): ?array
    {
        $soglia = (float) $imp['controlli']['via_m'];
        $mezzo = max($soglia, (float) $imp['controlli']['edificio_m']) + 20;
        if (! $m->copre(Geometria::riquadro($lat, $lon, $mezzo, $mezzo), true)) {
            return null;
        }
        $intorno = Geometria::riquadro($lat, $lon, self::RAGGIO_VIA, self::RAGGIO_VIA);
        $edificio = self::edificio(array_values($m->oggetti('edifici', $intorno)), $lat, $lon, (float) $imp['controlli']['edificio_m'], (float) $imp['controlli']['isolato_m2']);
        $via = self::via(array_values($m->oggetti('strade', $intorno)), $lat, $lon, $indirizzo, ImpostazioniMappe::coppie((string) $imp['abbreviazioni']));

        return [
            'edificio' => $edificio === null ? null : ($edificio['isolato'] ? 'isolato' : 'edificio'),
            'edificio_m' => $edificio === null ? null : round($edificio['distanza'], 1),
            'area_m2' => $edificio === null ? null : round($edificio['area']),
            'via' => ['trovata' => $via['trovata'], 'nome' => $via['nome'], 'metri' => $via['metri']],
            'esito' => self::esito($via, $soglia),
            'soglia_m' => $soglia,
        ];
    }

    /**
     * Il controllo della via (D-VAL-99): «ok», «lontana» (oltre la soglia) o «non trovata».
     *
     * @param  array{trovata: bool, metri: ?float}  $via
     */
    public static function esito(array $via, float $sogliaM): string
    {
        if (! $via['trovata'] || $via['metri'] === null) {
            return 'non trovata';
        }

        return $via['metri'] <= $sogliaM ? 'ok' : 'lontana';
    }
}