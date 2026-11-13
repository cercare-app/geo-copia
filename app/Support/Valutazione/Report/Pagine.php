<?php

namespace App\Support\Valutazione\Report;

/**
 * LE PAGINE DEL REPORT, IN FILA (D-VAL-40, D-VAL-53). Nel lotto 1 erano
 * tre: la copertina, «In breve» e «Avvertenze e diritti». Il lotto 2 ne
 * aggiunge dodici, nell'ordine del fac-simile REV0; il lotto 4 «Il posto»,
 * dopo «In breve». Manca ancora «I dintorni» (lotto 5, dopo «La zona»):
 * si aggiungerà qui, al suo posto.
 *
 *  - fissa: c'è sempre (copertina e avvertenze); le altre si spengono
 *    dall'Admin e indice e numeri si adattano da soli;
 *  - capitolo: ha il numero nella pillola blu e compare nell'indice;
 *  - titolo: il testo dell'Admin che dà il nome alla pagina nell'indice;
 *  - una pagina del lotto 2 dice quanti fogli occupa (fogli()): zero se
 *    non ha niente da mostrare (D-VAL-55), due per le schede oltre le tre.
 */
final class Pagine
{
    public const ELENCO = [
        'copertina' => ['classe' => Copertina::class, 'fissa' => true, 'capitolo' => false, 'titolo' => null, 'nome' => 'Copertina'],
        'in_breve' => ['classe' => InBreve::class, 'fissa' => false, 'capitolo' => true, 'titolo' => 'breve_titolo', 'nome' => 'In breve'],
        'posto' => ['classe' => Posto::class, 'fissa' => false, 'capitolo' => true, 'titolo' => 'posto_titolo', 'nome' => 'Il posto'],
        'zona' => ['classe' => Zona::class, 'fissa' => false, 'capitolo' => true, 'titolo' => 'zona_indice', 'nome' => 'La zona'],
        'vicine' => ['classe' => Vicine::class, 'fissa' => false, 'capitolo' => true, 'titolo' => 'vicine_titolo', 'nome' => 'Le case vicine'],
        'voce' => ['classe' => VocePerVoce::class, 'fissa' => false, 'capitolo' => true, 'titolo' => 'voce_titolo', 'nome' => 'Il confronto voce per voce'],
        'schede' => ['classe' => Schede::class, 'fissa' => false, 'capitolo' => true, 'titolo' => 'schede_titolo', 'nome' => 'Le case in vendita'],
        'vendute' => ['classe' => Vendute::class, 'fissa' => false, 'capitolo' => true, 'titolo' => 'vendute_titolo', 'nome' => 'Le case vendute'],
        'domanda' => ['classe' => Domanda::class, 'fissa' => false, 'capitolo' => true, 'titolo' => 'domanda_titolo', 'nome' => 'Domanda e offerta'],
        'tempo' => ['classe' => Tempo::class, 'fissa' => false, 'capitolo' => true, 'titolo' => 'tempo_indice', 'nome' => 'Il valore nel tempo'],
        'prezzi' => ['classe' => Prezzi::class, 'fissa' => false, 'capitolo' => true, 'titolo' => 'prezzi_titolo', 'nome' => 'I prezzi chiesti oggi'],
        'affitto' => ['classe' => Affitto::class, 'fissa' => false, 'capitolo' => true, 'titolo' => 'affitto_titolo', 'nome' => 'L\'affitto'],
        'casa' => ['classe' => LaCasa::class, 'fissa' => false, 'capitolo' => true, 'titolo' => 'casa_titolo', 'nome' => 'La casa e il suo stato'],
        'metodo' => ['classe' => Metodo::class, 'fissa' => false, 'capitolo' => true, 'titolo' => 'metodo_titolo', 'nome' => 'Come leggere questo report'],
        'avvertenze' => ['classe' => Avvertenze::class, 'fissa' => true, 'capitolo' => true, 'titolo' => 'avv_titolo', 'nome' => 'Avvertenze e diritti'],
    ];

    /** Le icone delle pagine (in public/icons): la diagnosi controlla che ci siano. */
    public const ICONE = [
        'ico-m-valutazione.png', 'ico-descrizione.png', 'ico-m-geo.png', 'ico-m-compra.png', 'ico-f-superficie.png',
        'ico-costi.png', 'ico-analisi.png', 'ico-prezzi.png', 'ico-m-affitta.png',
        'ico-h-italia.png', 'ico-e-sismico.png', 'ico-e-altimetrica.png', 'ico-e-connettivita.png', 'agcom-broadbandmap.png',
    ];

    /** La pagina «Come leggere questo report»: la nota di «In breve» che la cita si stampa solo se c'è. */
    public const METODO = 'metodo';

    /**
     * Le pagine accese, nell'ordine.
     *
     * @param  array<string, mixed>  $fotografia
     * @return list<string>
     */
    public static function accese(array $fotografia): array
    {
        $accese = [];
        foreach (self::ELENCO as $chiave => $pagina) {
            if ($pagina['fissa'] || ($fotografia['pagine'][$chiave] ?? true)) {
                $accese[] = $chiave;
            }
        }

        return $accese;
    }
}