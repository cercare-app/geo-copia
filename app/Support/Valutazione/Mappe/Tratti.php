<?php

namespace App\Support\Valutazione\Mappe;

use App\Support\Valutazione\Grafici;

/**
 * I PEZZI CHE SI RIPETONO NEI DISEGNI DELLE MAPPE (lotto 4, D-VAL-96): il
 * segnaposto della casa, i pallini numerati, la scala, i crediti, la freccia
 * del nord, gli angoli arrotondati e i testi con l'alone. Disegnati come il
 * prototipo (mappe.py, vista.py), ma per mPDF:
 *  - niente clipPath (mPDF non lo conosce): il disegno lo taglia già il
 *    riquadro dell'immagine; gli angoli arrotondati sono quattro spicchi
 *    bianchi disegnati sopra, più il bordo;
 *  - niente paint-order: l'alone bianco di un testo è lo stesso testo, solo
 *    contorno, disegnato prima;
 *  - i testi si scrivono allineati a sinistra, con la larghezza vera
 *    (Grafici::testo), anche quando sono ruotati.
 */
final class Tratti
{
    /** Il segnaposto della casa (mappe.py, _pin): la punta in ($x, $y). */
    public static function segnaposto(float $x, float $y, string $colore, float $scala = 1.0): string
    {
        $s = $scala;
        $n = fn (float $v): string => Geometria::n($v);

        return '<path d="M'.$n($x).' '.$n($y + 0.2 * $s).'c'.$n(-3.6 * $s).' '.$n(-4.2 * $s).' '.$n(-5.3 * $s).' '.$n(-6.8 * $s).' '.$n(-5.3 * $s).' '.$n(-9.4 * $s)
            .'a'.$n(5.3 * $s).' '.$n(5.3 * $s).' 0 0 1 '.$n(10.6 * $s).' 0c0 '.$n(2.6 * $s).' '.$n(-1.7 * $s).' '.$n(5.2 * $s).' '.$n(-5.3 * $s).' '.$n(9.4 * $s).'z" fill="'.$colore.'" stroke="#ffffff" stroke-width="0.7"/>'
            .'<path d="M'.$n($x - 2.4 * $s).' '.$n($y - 9 * $s).'L'.$n($x).' '.$n($y - 11.2 * $s).'L'.$n($x + 2.4 * $s).' '.$n($y - 9 * $s).'L'.$n($x + 1.7 * $s).' '.$n($y - 9 * $s)
            .'L'.$n($x + 1.7 * $s).' '.$n($y - 6.9 * $s).'L'.$n($x - 1.7 * $s).' '.$n($y - 6.9 * $s).'L'.$n($x - 1.7 * $s).' '.$n($y - 9 * $s).'z" fill="#ffffff"/>';
    }

    /** Il pallino numerato (annunci: cerchio; vendite: quadrato arrotondato, per la stampa in bianco e nero). */
    public static function numero(float $x, float $y, int $numero, string $colore, bool $quadrato, float $raggio = 2.6): string
    {
        $n = fn (float $v): string => Geometria::n($v);
        $forma = $quadrato
            ? '<rect x="'.$n($x - $raggio).'" y="'.$n($y - $raggio).'" width="'.$n(2 * $raggio).'" height="'.$n(2 * $raggio).'" rx="'.$n($raggio * 0.45).'" ry="'.$n($raggio * 0.45).'" fill="'.$colore.'" stroke="#ffffff" stroke-width="0.7"/>'
            : '<circle cx="'.$n($x).'" cy="'.$n($y).'" r="'.$n($raggio).'" fill="'.$colore.'" stroke="#ffffff" stroke-width="0.7"/>';
        $corpo = $raggio * 1.05;

        return $forma.Grafici::testo($x, $y + $corpo * 0.36, (string) $numero, $corpo, '#ffffff', Grafici::SANS_FORTE, 'middle');
    }

    /**
     * Un testo con l'alone bianco, ruotato di $angolo gradi intorno al suo
     * centro ($cx, $cy). Il testo è centrato: la posizione la calcola qui.
     */
    public static function testoConAlone(float $cx, float $cy, string $testo, float $corpo, string $colore, float $angolo = 0.0, string $carattere = Grafici::SANS_SEMI): string
    {
        $largo = Grafici::larghezzaNelDisegno($testo, $corpo, $carattere);
        $x = $cx - $largo / 2;
        $y = $cy + $corpo * 0.35;
        $giro = abs($angolo) > 0.05 ? ' transform="rotate('.Geometria::n($angolo).' '.Geometria::n($cx).' '.Geometria::n($cy).')"' : '';
        $t = htmlspecialchars($testo, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
        $comune = ' x="'.Geometria::n($x).'" y="'.Geometria::n($y).'" font-size="'.Geometria::n($corpo).'" font-family="'.$carattere.'"'.$giro;

        return '<text'.$comune.' fill="none" stroke="#ffffff" stroke-width="'.Geometria::n($corpo * 0.35).'" stroke-linejoin="round">'.$t.'</text>'
            .'<text'.$comune.' fill="'.$colore.'">'.$t.'</text>';
    }

    /**
     * La scala in basso a sinistra: la barra lunga $metri, larga $mm sulla pagina.
     *
     * @return array{0: string, 1: array{0: float, 1: float, 2: float, 3: float}} il disegno e il suo ingombro
     */
    public static function scala(float $h, float $metri, float $mm, float $corpo = 2.2): array
    {
        $n = fn (float $v): string => Geometria::n($v);
        $etichetta = self::metriTesto($metri);
        $largo = $mm + 3 + Grafici::larghezzaNelDisegno($etichetta, $corpo, Grafici::SANS_FORTE);
        $x = 4.0;
        $y = $h - 6.0;
        $svg = '<rect x="'.$n($x - 1.5).'" y="'.$n($y - 3.6).'" width="'.$n($largo + 2.5).'" height="6" rx="1.6" ry="1.6" fill="#ffffff" fill-opacity="0.9"/>'
            .'<path d="M'.$n($x).' '.$n($y).'L'.$n($x + $mm).' '.$n($y).'" stroke="#111827" stroke-width="0.5"/>'
            .'<path d="M'.$n($x).' '.$n($y - 1).'L'.$n($x).' '.$n($y + 1).'M'.$n($x + $mm).' '.$n($y - 1).'L'.$n($x + $mm).' '.$n($y + 1).'" stroke="#111827" stroke-width="0.4"/>'
            .Grafici::testo($x + $mm + 1.5, $y + 0.9, $etichetta, $corpo, '#111827', Grafici::SANS_FORTE);

        return [$svg, [$x - 1.5, $y - 3.6, $x + $largo + 1, $y + 2.4]];
    }

    /** La lunghezza tonda della scala per una mappa di $raggio metri (mappe.py). */
    public static function metriScala(float $raggio): int
    {
        return match (true) {
            $raggio <= 200 => 50,
            $raggio <= 450 => 100,
            $raggio <= 900 => 250,
            $raggio <= 1800 => 500,
            default => 1000,
        };
    }

    /** «100 m», «1 km», «1,5 km». */
    public static function metriTesto(float $metri): string
    {
        return $metri >= 1000 ? str_replace('.', ',', rtrim(rtrim(number_format($metri / 1000, 1, '.', ''), '0'), '.')).' km' : (int) round($metri).' m';
    }

    /**
     * I crediti in basso a destra, con l'alone.
     *
     * @return array{0: string, 1: array{0: float, 1: float, 2: float, 3: float}}
     */
    public static function crediti(float $w, float $h, string $testo, float $corpo = 1.9): array
    {
        if (trim($testo) === '') {
            return ['', [$w, $h, $w, $h]];
        }
        $largo = Grafici::larghezzaNelDisegno($testo, $corpo, Grafici::SANS_SEMI);
        $cx = $w - 2.5 - $largo / 2;
        $cy = $h - 2.2 - $corpo * 0.35;

        return [self::testoConAlone($cx, $cy, $testo, $corpo, '#7A8496'), [$w - 3.5 - $largo, $h - 2.2 - $corpo, $w - 1.5, $h - 1]];
    }

    /** La freccia del nord in alto a destra (vista.py), girata di $angolo gradi. */
    public static function nord(float $w, string $lettera, float $angolo = 0.0): string
    {
        $cx = $w - 9;
        $cy = 9.0;
        $n = fn (float $v): string => Geometria::n($v);
        $giro = ' transform="rotate('.$n($angolo).' '.$n($cx).' '.$n($cy).')"';

        return '<circle cx="'.$n($cx).'" cy="'.$n($cy).'" r="5" fill="#ffffff" fill-opacity="0.94"/>'
            .'<path d="M'.$n($cx).' '.$n($cy - 3.4).'L'.$n($cx + 1.9).' '.$n($cy + 1.6).'L'.$n($cx).' '.$n($cy + 0.7).'L'.$n($cx - 1.9).' '.$n($cy + 1.6).'z" fill="#111827"'.$giro.'/>'
            .($lettera !== '' ? Grafici::testo($cx, $cy + 4.3, $lettera, 1.9, '#111827', Grafici::SANS_FORTE, 'middle') : '');
    }

    /**
     * Gli angoli arrotondati: quattro spicchi del colore della pagina sopra il
     * disegno e il bordo (mPDF non conosce clipPath).
     */
    public static function angoli(float $w, float $h, float $r, string $pagina = '#ffffff', string $bordo = '#E3E8F0'): string
    {
        if ($r <= 0) {
            return '';
        }
        $n = fn (float $v): string => Geometria::n($v);
        $spicchi = 'M0 0L'.$n($r).' 0A'.$n($r).' '.$n($r).' 0 0 0 0 '.$n($r).'z'
            .'M'.$n($w).' 0L'.$n($w).' '.$n($r).'A'.$n($r).' '.$n($r).' 0 0 0 '.$n($w - $r).' 0z'
            .'M'.$n($w).' '.$n($h).'L'.$n($w - $r).' '.$n($h).'A'.$n($r).' '.$n($r).' 0 0 0 '.$n($w).' '.$n($h - $r).'z'
            .'M0 '.$n($h).'L0 '.$n($h - $r).'A'.$n($r).' '.$n($r).' 0 0 0 '.$n($r).' '.$n($h).'z';

        return '<path d="'.$spicchi.'" fill="'.$pagina.'"/>'
            .'<rect x="0.15" y="0.15" width="'.$n($w - 0.3).'" height="'.$n($h - 0.3).'" rx="'.$n($r).'" ry="'.$n($r).'" fill="none" stroke="'.$bordo.'" stroke-width="0.3"/>';
    }

    /** L'SVG intero, w × h mm, col disegno in millimetri. */
    public static function svg(float $w, float $h, string $corpo): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.Geometria::n($w).' '.Geometria::n($h).'" width="'.Geometria::n($w).'mm" height="'.Geometria::n($h).'mm">'.$corpo.'</svg>';
    }

    /** Due rettangoli [x0, y0, x1, y1] si toccano? */
    public static function siToccano(array $a, array $b): bool
    {
        return $a[0] < $b[2] && $b[0] < $a[2] && $a[1] < $b[3] && $b[1] < $a[3];
    }
}