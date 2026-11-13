<?php

namespace App\Support\Valutazione;

/**
 * I TESTI, I RIQUADRI E LA PAGINA DEL LOTTO 4 (D-VAL-93…104): la pagina
 * «Il posto» (vista dall'alto, coordinate, territorio) e le scritte delle
 * mappe delle altre pagine (didascalie, legende, chi resta fuori). Testi
 * li unisce a quelli dei lotti 1 e 2: stessa forma, stesse regole (vuoto =
 * fabbrica, segnaposti fra graffe, se un segnaposto non ha valore la frase
 * non si stampa).
 *
 * I valori di fabbrica sono quelli del fac-simile REV0 (pagine.py), così
 * il report esce come il fac-simile senza toccare nulla. Le parole del
 * territorio seguono la scheda degli annunci (DatiNascosti): stessi dati,
 * stessa regola dei ±20% per la media italiana.
 *
 * Forma: come TestiPagine (nome => [sezione, etichetta, fabbrica,
 * segnaposti propri, segnaposti in grassetto, tipo]).
 */
final class TestiMappe
{
    /** Le sezioni dell'Admin del lotto 4. «Il posto» viene dopo «In breve», come nel report. */
    public const SEZIONI = [
        'posto' => 'Pagina «Il posto»',
        'mappe' => 'Le scritte dentro le mappe (tutte le pagine)',
    ];

    public const TESTI = [
        // Il posto
        'posto_occhiello' => ['posto', 'Occhiello', 'Il posto', [], [], 'riga'],
        'posto_titolo' => ['posto', 'Titolo (anche nell\'indice)', 'Dove si trova', [], [], 'riga'],
        'posto_frase' => ['posto', 'Frase sotto il titolo, con la vista dall\'alto', 'La casa e il suo isolato visti dall\'alto, e i dati ufficiali del territorio in cui si trova, a {comune}.', [], [], 'testo'],
        'posto_frase_senza_vista' => ['posto', 'Frase sotto il titolo, senza la vista dall\'alto', 'I dati ufficiali del territorio in cui si trova la casa, a {comune}.', [], [], 'testo'],
        'posto_vista_edificio' => ['posto', 'Sotto la vista · se è colorato l\'edificio', 'L\'edificio della casa', [], [], 'riga'],
        'posto_vista_isolato' => ['posto', 'Sotto la vista · se è colorato un isolato intero', 'L\'isolato della casa, che OpenStreetMap disegna come un solo edificio', [], [], 'riga'],
        'posto_vista_nota' => ['posto', 'Sotto la vista · la nota', 'forme degli edifici da OpenStreetMap, altezze indicative · il nord è in alto · la scala vale in larghezza', [], [], 'riga'],
        'posto_coordinate' => ['posto', 'Le coordinate ({latitudine}, {longitudine}: con la virgola, cinque decimali)', 'Coordinate della casa: {latitudine} N · {longitudine} E', ['latitudine', 'longitudine'], [], 'riga'],
        'posto_territorio_t' => ['posto', 'Territorio · titolo', 'Il territorio', [], [], 'riga'],
        'posto_territorio_frase' => ['posto', 'Territorio · frase ({comune_territorio}: il comune dei dati ufficiali)', 'Dati ufficiali di enti pubblici. Zona sismica, alluvioni e frane riguardano il comune di {comune_territorio}, non l\'edificio; la fibra è misurata nel punto della casa.', ['comune_territorio'], [], 'testo'],
        'posto_sismica_t' => ['posto', 'Zona sismica · titolo', 'Zona sismica', [], [], 'riga'],
        'posto_sismica_pillola' => ['posto', 'Zona sismica · pillola', 'classificazione del comune', [], [], 'riga'],
        'posto_alluvioni_t' => ['posto', 'Alluvioni · titolo', 'Alluvioni', [], [], 'riga'],
        'posto_alluvioni_n' => ['posto', 'Alluvioni · il numero ({percentuale}: «4,3%»)', '{percentuale} dei residenti', ['percentuale'], [], 'riga'],
        'posto_alluvioni_d' => ['posto', 'Alluvioni · la nota ({media}: «11,5%»)', 'vive in aree a pericolosità idraulica media · media italiana {media}', ['media'], [], 'riga'],
        'posto_frane_t' => ['posto', 'Frane · titolo', 'Frane', [], [], 'riga'],
        'posto_frane_n' => ['posto', 'Frane · il numero ({percentuale}: «0,1%»)', '{percentuale} dei residenti', ['percentuale'], [], 'riga'],
        'posto_frane_d' => ['posto', 'Frane · la nota ({media}: «2,2%»)', 'vive in aree a pericolosità elevata o molto elevata · media italiana {media}', ['media'], [], 'riga'],
        'posto_nessuna_area' => ['posto', 'Alluvioni e frane · se nel comune non ci sono aree abitate a rischio', 'nessuna area abitata mappata', [], [], 'riga'],
        'posto_sotto' => ['posto', 'Pillola · sotto la media italiana (oltre il 20% in meno)', 'sotto la media italiana', [], [], 'riga'],
        'posto_vicino' => ['posto', 'Pillola · vicino alla media italiana (entro il 20%)', 'vicino alla media italiana', [], [], 'riga'],
        'posto_sopra' => ['posto', 'Pillola · sopra la media italiana (oltre il 20% in più)', 'sopra la media italiana', [], [], 'riga'],
        'posto_fibra_t' => ['posto', 'Fibra · titolo', 'Fibra nel punto della casa', [], [], 'riga'],
        'posto_fibra_punto' => ['posto', 'Fibra · la nota, se c\'è una cella nel punto', 'copertura dichiarata da AGCOM nel punto esatto dell\'immobile', [], [], 'riga'],
        'posto_fibra_vicino' => ['posto', 'Fibra · la nota, se la cella è vicina ({metri}: «20 m»)', 'copertura dichiarata da AGCOM nelle immediate vicinanze dell\'immobile ({metri})', ['metri'], [], 'riga'],
        'posto_fibra_reti' => ['posto', 'Fibra · le reti ({reti}: «Open Fiber e TIM Wholesale»)', 'rete {reti}', ['reti'], [], 'riga'],
        'posto_fibra_nessuna' => ['posto', 'Fibra · il numero, se non c\'è nessuna cella', 'Nessuna copertura dichiarata', [], [], 'riga'],
        'posto_fibra_nessuna_d' => ['posto', 'Fibra · la nota, se non c\'è nessuna cella ({metri}: «60 m»)', 'nessuna rete dichiarata entro {metri} dal punto · non vuol dire assenza di linea', ['metri'], [], 'riga'],
        'posto_fibra_pillola' => ['posto', 'Fibra · pillola', 'copertura tecnica, non servizio attivo', [], [], 'riga'],
        'posto_nd' => ['posto', 'Il numero di una casella senza dato', 'n.d.', [], [], 'riga'],
        'posto_nd_comune' => ['posto', 'La nota di una casella senza dato, se il comune non si riconosce', 'comune non riconosciuto nell\'anagrafe ISTAT', [], [], 'riga'],
        'posto_nd_dato' => ['posto', 'La nota di una casella senza dato, se la fonte non lo dice', 'dato non disponibile per questo comune', [], [], 'riga'],
        'posto_nd_fibra' => ['posto', 'La nota della fibra, se non si è potuta leggere', 'lettura non disponibile', [], [], 'riga'],
        'posto_fonti' => ['posto', 'Le fonti in fondo ({edizione_fibra}: «Q2 2026»)', 'Protezione Civile, classificazione sismica (maggio 2025) · ISPRA, piattaforma IdroGEO (frane 2024, alluvioni 2020), CC BY 4.0 · AGCOM BroadbandMap, CC BY 4.0, edizione {edizione_fibra}: dato derivato da elaborazioni statistiche dei dati degli operatori, una descrizione media che può discostarsi anche in modo significativo dalla prestazione offerta al singolo utente.', ['edizione_fibra'], [], 'testo'],

        // Le didascalie delle mappe nelle altre pagine
        'zona_mappa_didascalia' => ['zona', 'Mappa · didascalia', 'Zona OMI {zona_codice} · {zona_nome} · confini della zona definiti dall\'Agenzia delle Entrate', ['zona_codice', 'zona_nome'], [], 'riga'],
        'zona_mappa_oltre' => ['zona', 'Mappa · in coda alla didascalia, se la zona è più grande della mappa', 'la zona continua oltre la mappa', [], [], 'riga'],
        'vicine_leg_annunci' => ['vicine', 'Mappa · legenda degli annunci', 'In vendita oggi · prezzo chiesto', [], [], 'riga'],
        'vicine_leg_vendite' => ['vicine', 'Mappa · legenda delle vendite', 'Vendute · prezzo pagato', [], [], 'riga'],
        'vicine_leg_casa' => ['vicine', 'Mappa · legenda della casa', 'La casa', [], [], 'riga'],
        'mappa_fuori_annunci' => ['vicine', 'Mappa · chi resta fuori, più annunci ({elenco}: «4 (a 1,8 km) e 5 (a 2,1 km)»; vale anche per le schede)', 'Gli annunci {elenco} sono troppo lontani dalla casa per questa mappa e non compaiono.', ['elenco'], [], 'riga'],
        'mappa_fuori_annuncio' => ['vicine', 'Mappa · chi resta fuori, un annuncio solo ({elenco}: «4 (a 1,8 km)»)', 'L\'annuncio {elenco} è troppo lontano dalla casa per questa mappa e non compare.', ['elenco'], [], 'riga'],
        'mappa_fuori_vendite' => ['vendute', 'Mappa · chi resta fuori, più vendite ({elenco}; vale anche per «Le case vicine»)', 'Le compravendite {elenco} sono troppo lontane dalla casa per questa mappa e non compaiono.', ['elenco'], [], 'riga'],
        'mappa_fuori_vendita' => ['vendute', 'Mappa · chi resta fuori, una vendita sola ({elenco})', 'La compravendita {elenco} è troppo lontana dalla casa per questa mappa e non compare.', ['elenco'], [], 'riga'],

        // Dentro le mappe
        'mappe_crediti' => ['mappe', 'In basso a destra di ogni mappa (la licenza ODbL lo chiede)', '© contributori OpenStreetMap', [], [], 'riga'],
        'mappe_crediti_vista' => ['mappe', 'In basso a destra della vista dall\'alto', '© contributori OpenStreetMap · elaborazione Cerca.Re', [], [], 'riga'],
        'mappe_nord' => ['mappe', 'La lettera della freccia del nord', 'N', [], [], 'riga'],
    ];

    /**
     * I riquadri del lotto 4: nome => [sezione, etichetta, acceso di
     * fabbrica, si può accendere oggi, nota]. Le mappe delle pagine dei
     * lotti 1 e 2 (cop_mappa, zona_mappa, vicine_mappa, schede_mappa,
     * vendute_mappa) restano dov'erano, ora accese di fabbrica.
     */
    public const RIQUADRI = [
        'posto_vista' => ['posto', 'Vista dall\'alto', true, true, 'Se intorno alla casa OpenStreetMap non ha edifici, la vista non compare comunque.'],
        'posto_coordinate' => ['posto', 'Le coordinate della casa', true, true, null],
        'posto_territorio' => ['posto', 'Il territorio (le quattro caselle)', true, true, 'Le singole caselle si accendono e si spengono in «Valutazione · Mappe e territorio».'],
        'posto_fonti' => ['posto', 'Le fonti in fondo', true, true, null],
        'vicine_legenda' => ['vicine', 'Legenda sotto la mappa', true, true, null],
    ];

    /** La pagina del lotto 4: nome => [etichetta, accesa di fabbrica]. */
    public const PAGINE = [
        'posto' => ['Pagina «Il posto»', true],
    ];
}