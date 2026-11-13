<?php

namespace App\Support\Valutazione\Mappe;

use App\Support\Geo\ImpostazioniGeo;
use App\Support\Valutazione\Guardia;
use App\Support\Valutazione\Testi;
use Throwable;

/**
 * «PROVA UN INDIRIZZO» (lotto 4, D-VAL-99): coordinate e via, e risponde
 * come farebbe il report con le scelte di adesso di «Mappe e territorio».
 *
 *  - edificio, isolato o niente colorato;
 *  - la via trovata o no, col nome come sta nei dati e la distanza;
 *  - il verdetto del controllo della via;
 *  - la vista dall'alto di quel punto.
 *
 * Chiede al magazzino geografico solo l'intorno della vista (con gli
 * edifici), senza la memoria dell'anteprima: mostra i dati di adesso. Non
 * scrive nulla, non paga, non manda email.
 */
final class Prova
{
    /**
     * @return array{lat: float, lon: float, via: string, controllo: ?array, vista: ?string, avvisi: list<string>, millisecondi: float}
     */
    public static function indirizzo(float $lat, float $lon, string $via): array
    {
        $inizio = hrtime(true);
        $imp = ImpostazioniMappe::tutte();
        $dati = ['input' => ['latitude' => $lat, 'longitude' => $lon, 'street' => $via]];
        $avvisi = [];
        $controllo = null;
        $svg = null;
        try {
            $latoKm = ImpostazioniGeo::numero(ImpostazioniGeo::RIQUADRO_KM);
            $cornici = Cornici::tutte($dati, $imp, ['vista' => true], $latoKm);
            $materiale = Raccolta::per($cornici, $dati, 0, false, $latoKm);
            $avvisi = $materiale->errori;
            $controllo = Casa::controllo($materiale, $lat, $lon, $via, $imp);
            $testi = Testi::tutti()['testi'];
            $vista = Vista::disegna($cornici['vista'], $materiale, $imp, $via,
                ['crediti' => (string) ($testi['mappe_crediti_vista'] ?? ''), 'nord' => (string) ($testi['mappe_nord'] ?? 'N')],
                fn (string $nome): bool => Guardia::haParolaSegreta($nome));
            $svg = $vista['svg'] ?? null;
            if ($svg === null && $avvisi === []) {
                $avvisi[] = 'La vista dall\'alto non c\'è: nell\'intorno i dati non hanno nessun edificio.';
            }
        } catch (Throwable $e) {
            $avvisi[] = 'Prova non riuscita: '.$e->getMessage();
        }

        return [
            'lat' => $lat,
            'lon' => $lon,
            'via' => $via,
            'controllo' => $controllo,
            'vista' => $svg,
            'avvisi' => array_values(array_unique($avvisi)),
            'millisecondi' => round((hrtime(true) - $inizio) / 1e6),
        ];
    }

    /**
     * Le righe da mostrare per un controllo (anteprima e «Prova un
     * indirizzo»): [cosa, come, tono], tono ok, no, avviso o null.
     *
     * @param  array<string, mixed>|null  $c  Casa::controllo()
     * @return list<array{0: string, 1: string, 2: ?string}>
     */
    public static function righe(?array $c): array
    {
        if ($c === null) {
            return [['La casa sulla mappa', 'Non si può dire: mancano gli edifici intorno alla casa (le mappe accese non li chiedono, o non sono arrivati per intero).', 'avviso']];
        }
        $metri = fn (float $m): string => str_replace('.', ',', (string) round($m, 1)).' m';
        $righe = [];
        $righe[] = ['Che cosa si colora', match ($c['edificio']) {
            'edificio' => ($c['edificio_m'] > 0 ? 'L\'edificio più vicino, a '.$metri((float) $c['edificio_m']).' dalle coordinate' : 'L\'edificio che contiene le coordinate').' ('.number_format((float) $c['area_m2'], 0, ',', '.').' m²).',
            'isolato' => 'L\'isolato'.($c['edificio_m'] > 0 ? ' più vicino, a '.$metri((float) $c['edificio_m']).' dalle coordinate' : ' che contiene le coordinate').' ('.number_format((float) $c['area_m2'], 0, ',', '.').' m²): OpenStreetMap lo disegna come un solo edificio, e la didascalia lo dice.',
            default => 'Niente: nessun edificio contiene le coordinate o è abbastanza vicino.',
        }, $c['edificio'] === null ? 'avviso' : null];
        $via = $c['via'];
        $righe[] = ['La via dell\'indirizzo', $via['trovata']
            ? '«'.$via['nome'].'» nei dati, a '.$metri((float) $via['metri']).' dalle coordinate.'
            : 'Non trovata nei dati intorno alla casa: il suo nome non si scrive sulle mappe.', $via['trovata'] ? null : 'avviso'];
        $soglia = $metri((float) $c['soglia_m']);
        $righe[] = ['Controllo della via (entro '.$soglia.')', match ($c['esito']) {
            'ok' => 'Torna.',
            'lontana' => 'NON torna: la via è più lontana di '.$soglia.'.',
            default => 'NON torna: la via non c\'è nei dati.',
        }, $c['esito'] === 'ok' ? 'ok' : 'no'];

        return $righe;
    }
}