<?php

namespace App\Support\Valutazione\Report;

use App\Support\Valutazione\Disegni;
use App\Support\Valutazione\Formato;
use App\Support\Valutazione\Grafici;
use Throwable;

/**
 * PAGINE «LE CASE IN VENDITA, SCHEDA PER SCHEDA» (lotto 2, D-VAL-57): una
 * scheda per annuncio con la foto, la via, la distanza, il prezzo chiesto,
 * il prezzo al m² rispetto alla stima, i dati in breve, la descrizione
 * accorciata e la riga sotto. Dal lotto 4, fra la foto e i dati, la mappa
 * piccola dell'annuncio (40 × 40 mm, alta come la foto), con la casa se ci
 * sta (riquadro «schede_mappa»).
 *
 *  - Le schede si dispongono sui fogli in fila: ognuna ha un'altezza
 *    stimata (la descrizione più lunga la allunga) e un foglio ne prende
 *    quante ci stanno, di solito tre. Un capitolo solo, anche su più fogli.
 *  - La foto: ritagliata al centro in 3:2 e rimpicciolita (GD), così non si
 *    deforma e il PDF resta leggero. Se manca, non si legge o è enorme
 *    (oltre 25 milioni di punti: aprirla chiederebbe troppa memoria), al
 *    suo posto il riquadro «Foto non disponibile».
 *  - La descrizione si taglia a frase intera entro la lunghezza
 *    dell'Admin; se già la prima frase è più lunga, a parola intera con «…».
 */
final class Schede
{
    /**
     * Quanto spazio c'è sotto un capo di una riga di titolo e una di frase,
     * in millimetri (254 di pagina − 27 di capo − 7 di margine). Ogni riga
     * in più del titolo o della frase lo accorcia (spazio()).
     */
    public const SPAZIO = 220;

    /** La larghezza del titolo e della frase del capo (178 − 17 di icona), in millimetri. */
    public const LARGHEZZA_CAPO = 161;

    /** La foto più grande che si apre (in milioni di punti): oltre, servirebbe troppa memoria. */
    public const FOTO_MASSIMA_MP = 25;

    /** La foto nel PDF: 60 × 40 mm, ritagliata e rimpicciolita a 600 × 400 punti. */
    public const FOTO_LARGA = 600;

    public const FOTO_ALTA = 400;

    /** Quanti caratteri della descrizione stanno in una riga (stima prudente). */
    public const CARATTERI_PER_RIGA = 125;

    public static function fogli(Contesto $c): int
    {
        return count(self::fogliDiSchede($c));
    }

    public static function html(Contesto $c, int $foglio = 1): string
    {
        $fogli = self::fogliDiSchede($c);
        $mie = $fogli[$foglio - 1] ?? [];
        $frase = $foglio > 1 ? 'schede_frase_seguito' : self::fraseDelPrimo($c);
        $h = Capo::html($c, 'schede', 'ico-m-compra.png', 'schede_occhiello', 'schede_titolo', $frase);
        foreach ($mie as $annuncio) {
            $h .= self::scheda($c, $annuncio);
        }
        if ($foglio === count($fogli) && $c->riquadro('schede_nota')) {
            $h .= Parti::p($c->testo('schede_nota'), 'nota');
        }

        return $h;
    }

    /**
     * Gli annunci divisi per foglio.
     *
     * @return list<list<array<string, mixed>>>
     */
    private static function fogliDiSchede(Contesto $c): array
    {
        $fogli = [];
        $foglio = [];
        $altezza = 0.0;
        $annunci = Confronti::annunci($c);
        foreach ($annunci as $k => $a) {
            $alta = self::altezza($c, $a) + ($k === count($annunci) - 1 && $c->riquadro('schede_nota') ? 9 : 0);
            if ($foglio !== [] && $altezza + $alta > self::spazio($c, $fogli === [] ? self::fraseDelPrimo($c) : 'schede_frase_seguito')) {
                $fogli[] = $foglio;
                $foglio = [];
                $altezza = 0.0;
            }
            $foglio[] = $a;
            $altezza += $alta;
        }
        if ($foglio !== []) {
            $fogli[] = $foglio;
        }

        return $fogli;
    }

    /**
     * La frase del primo foglio: con le mappe dice la posizione, senza no.
     * Conta che almeno una mappa esca davvero (senza i dati delle mappe la
     * frase non le promette).
     */
    private static function fraseDelPrimo(Contesto $c): string
    {
        if ($c->riquadro('schede_mappa')) {
            foreach (Confronti::annunci($c) as $a) {
                if ($c->mappa('scheda_'.$a['numero']) !== null) {
                    return 'schede_frase';
                }
            }
        }

        return 'schede_frase_senza_mappa';
    }

    /**
     * Lo spazio per le schede sotto il capo: SPAZIO meno le righe in più del
     * titolo (21 pt, circa 8,3 mm) e della frase (10,2 pt, circa 5,4 mm).
     */
    public static function spazio(Contesto $c, string $frase): float
    {
        $righe = function (?string $html, float $corpoPt): int {
            $testo = html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8');
            if (trim($testo) === '') {
                return 0;
            }
            // ExtraBold per il titolo, SemiBold (un po' più largo del normale) per la frase: stima prudente
            $largo = Grafici::larghezzaNelDisegno($testo, $corpoPt * Grafici::PT_MM, $corpoPt > 15 ? Grafici::SANS_FORTE : Grafici::SANS_SEMI);

            return (int) ceil($largo / self::LARGHEZZA_CAPO);
        };
        $titolo = $righe($c->testo('schede_titolo'), 21);
        $lead = $righe($c->testo($frase), 10.2);

        return self::SPAZIO - max(0, $titolo - 1) * 8.3 - max(0, $lead - 1) * 5.4 - ($lead === 0 ? -5.4 : 0);
    }

    /** L'altezza stimata di una scheda, in millimetri, margine compreso. */
    private static function altezza(Contesto $c, array $a): float
    {
        // la più alta fra la foto (40) e i dati (circa 41), più bordo, margini interni e spazio fra le schede
        $alta = 51.0;
        $descrizione = self::descrizione($c, $a);
        if ($descrizione !== null) {
            $alta += 2 + ceil(mb_strlen($descrizione) / self::CARATTERI_PER_RIGA) * 3.75;
        }

        return $alta + ($c->riquadro('schede_riga') ? 4.2 : 0);
    }

    private static function scheda(Contesto $c, array $a): string
    {
        $dati = '<p class="sc-t">'.Parti::pallino($a['numero'], Grafici::BLU).' '.Parti::t($a['via'] ?? '—').'</p>';
        $sotto = array_filter([
            $a['quartiere'] !== null ? Parti::t($a['quartiere']) : null,
            $a['distanza'] !== null ? $c->testo('schede_distanza', ['distanza' => Formato::distanza($a['distanza'])]) : null,
        ], fn (?string $x): bool => $x !== null && $x !== '');
        $dati .= Parti::p(implode(' · ', $sotto), 'sc-q');
        if ($a['prezzo'] !== null) {
            $dati .= '<p class="sc-p">'.Formato::euro($a['prezzo']).'</p>';
        }
        if ($a['al_mq'] !== null) {
            $dati .= '<p class="cf-m">'.Formato::euroMq($a['al_mq']).Parti::sullaStima($c, 'schede_sulla_stima', $a['differenza']).'</p>';
        }
        $sn = fn (bool $x): string => $x ? 'Sì' : 'No';
        $fatti = array_filter([
            ['tipologia', $a['tipologia'] !== null ? Parti::t($a['tipologia']) : null],
            ['superficie', $a['mq'] !== null ? Formato::numero($a['mq']).' m²' : null],
            ['locali', $a['locali_testo'] !== null ? Parti::t($a['locali_testo']) : null],
            ['piano', $a['piano_testo']],
            ['stato', $a['stato'] !== null ? Parti::t($a['stato']) : null],
            ['ascensore', $sn($a['ascensore'])],
            ['giardino', match ($a['giardino']) { 'privato' => 'privato', 'condominiale' => 'condominiale', default => 'No' }],
            ['box', $sn($a['box'])],
        ], fn (array $f): bool => $f[1] !== null && ($n = $c->testo('voce_riga_'.$f[0])) !== null && $n !== '');
        $fatti = array_values($fatti);
        if ($fatti !== []) {
            $righe = '';
            for ($k = 0; $k < count($fatti); $k += 2) {
                $righe .= '<tr>';
                foreach (array_slice($fatti, $k, 2) as [$nome, $valore]) {
                    $righe .= '<th>'.$c->testo('voce_riga_'.$nome).'</th><td>'.$valore.'</td>';
                }
                $righe .= '</tr>';
            }
            $dati .= '<table class="sc-f">'.$righe.'</table>';
        }

        $h = '<div class="scheda"><table class="sc-top"><tr>';
        if ($c->riquadro('schede_foto')) {
            $h .= '<td class="sc-foto">'.self::foto($c, $a['indice']).'</td>';
        }
        // la mappa piccola dell'annuncio, con la casa se ci sta (lotto 4)
        $mappa = $c->riquadro('schede_mappa') ? $c->mappa('scheda_'.$a['numero']) : null;
        if ($mappa !== null) {
            $h .= '<td class="sc-map">'.Parti::img($mappa, 'sc-map-img').'</td>';
        }
        $h .= '<td>'.$dati.'</td></tr></table>';
        if ($c->riquadro('schede_descrizione')) {
            $descrizione = self::descrizione($c, $a);
            if ($descrizione !== null) {
                $h .= '<p class="sc-d">'.Parti::t($descrizione).'</p>';
            }
        }
        if ($c->riquadro('schede_riga')) {
            $h .= Parti::p($c->testo('schede_riga', ['numero' => $a['codice']]), 'sc-r');
        }

        return $h.'</div>';
    }

    /** La descrizione accorciata, o null se non c'è o è spenta. */
    private static function descrizione(Contesto $c, array $a): ?string
    {
        if (! $c->riquadro('schede_descrizione') || $a['descrizione'] === null || $a['descrizione'] === '') {
            return null;
        }

        return self::accorcia((string) preg_replace('/\s+/u', ' ', $a['descrizione']), $c->disposizione()['descrizione']);
    }

    /**
     * Accorcia a frase intera entro $massimo caratteri; se già la prima frase
     * è più lunga, a parola intera con «…».
     */
    public static function accorcia(string $testo, int $massimo): string
    {
        $testo = trim($testo);
        if (mb_strlen($testo) <= $massimo) {
            return $testo;
        }
        $pezzo = mb_substr($testo, 0, $massimo);
        // la fine dell'ultima frase intera: un punto (o ! ? …) seguito da uno spazio o dalla fine del pezzo
        if (preg_match_all('/[.!?…](?=\s|$)/u', $pezzo, $m, PREG_OFFSET_CAPTURE) > 0) {
            $ultimo = end($m[0]);
            $fine = mb_strlen(substr($pezzo, 0, $ultimo[1])) + 1;
            // una frase cortissima da sola non basta: allora si taglia a parola
            if ($fine >= min(20, intdiv($massimo, 3))) {
                return rtrim(mb_substr($pezzo, 0, $fine));
            }
        }
        $spazio = mb_strrpos(mb_substr($pezzo, 0, $massimo - 1), ' ');
        $corto = $spazio !== false && $spazio > 0 ? mb_substr($pezzo, 0, $spazio) : mb_substr($pezzo, 0, $massimo - 1);

        return rtrim($corto, " \t,;:-–—").'…';
    }

    /** La foto dell'annuncio (indice da 0), o il riquadro «Foto non disponibile». */
    private static function foto(Contesto $c, int $indice): string
    {
        $percorso = $c->foto[$indice] ?? null;
        $jpeg = is_string($percorso) ? self::ritaglia($percorso) : null;
        if ($jpeg !== null) {
            return '<img class="sc-img" src="data:image/jpeg;base64,'.base64_encode($jpeg).'" alt="">';
        }

        return Parti::img(Disegni::fotoMancante($c->etichetta('schede_senza_foto'), 60, 40), 'sc-img');
    }

    /**
     * La foto ritagliata al centro in 3:2 e rimpicciolita, in JPEG; null se
     * il file non c'è o non è un'immagine che GD sa leggere.
     */
    public static function ritaglia(string $percorso): ?string
    {
        if (! is_file($percorso) || ! is_readable($percorso) || ! function_exists('imagecreatefromstring')) {
            return null;
        }
        try {
            $info = @getimagesize($percorso);
            if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] < 2 || $info[1] < 2
                || $info[0] * $info[1] > self::FOTO_MASSIMA_MP * 1_000_000) {
                return null;
            }
            $immagine = @imagecreatefromstring((string) file_get_contents($percorso));
            if ($immagine === false) {
                return null;
            }
            [$w, $h] = [$info[0], $info[1]];
            $rapporto = self::FOTO_LARGA / self::FOTO_ALTA;
            if ($w / $h > $rapporto) {
                $lw = (int) round($h * $rapporto);
                [$sx, $sy, $sw, $sh] = [intdiv($w - $lw, 2), 0, $lw, $h];
            } else {
                $lh = (int) round($w / $rapporto);
                [$sx, $sy, $sw, $sh] = [0, intdiv($h - $lh, 2), $w, $lh];
            }
            $fuori = imagecreatetruecolor(self::FOTO_LARGA, self::FOTO_ALTA);
            imagefill($fuori, 0, 0, (int) imagecolorallocate($fuori, 255, 255, 255));
            imagecopyresampled($fuori, $immagine, 0, 0, $sx, $sy, self::FOTO_LARGA, self::FOTO_ALTA, $sw, $sh);
            ob_start();
            imagejpeg($fuori, null, 82);
            $jpeg = (string) ob_get_clean();
            imagedestroy($immagine);
            imagedestroy($fuori);

            return $jpeg !== '' ? $jpeg : null;
        } catch (Throwable) {
            return null;
        }
    }
}