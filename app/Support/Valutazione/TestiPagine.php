<?php

namespace App\Support\Valutazione;

/**
 * I TESTI, I RIQUADRI E LE PAGINE DEL LOTTO 2 (D-VAL-53…61): le dodici
 * pagine del fac-simile REV0 fatte coi dati del report. Testi li unisce a
 * quelli del lotto 1: stessa forma, stesse regole (vuoto = fabbrica,
 * segnaposti fra graffe, se un segnaposto non ha valore la frase non si
 * stampa).
 *
 * I valori di fabbrica sono i testi del fac-simile, provvisori finché il
 * consulente non li legge (D-VAL-16). Le frasi che cambiano coi dati
 * (D-VAL-58) hanno un testo per ogni variante.
 *
 * Forma di TESTI: nome => [sezione, etichetta, fabbrica, segnaposti
 * propri, segnaposti in grassetto, tipo]. I nomi delle righe del voce per
 * voce («voce_riga_…») valgono anche per le schede degli annunci e per i
 * dati della casa.
 */
final class TestiPagine
{
    /** Le sezioni dell'Admin delle pagine del lotto 2, nell'ordine del report. */
    public const SEZIONI = [
        'zona' => 'Pagina «La zona»',
        'vicine' => 'Pagina «Le case vicine»',
        'voce' => 'Pagina «Il confronto voce per voce» (i nomi delle righe valgono anche per le schede e per «La casa»)',
        'schede' => 'Pagine «Le case in vendita, scheda per scheda»',
        'vendute' => 'Pagina «Le case vendute, una per una»',
        'domanda' => 'Pagina «Domanda e offerta»',
        'tempo' => 'Pagina «Il valore nel tempo»',
        'prezzi' => 'Pagina «I prezzi chiesti oggi»',
        'affitto' => 'Pagina «L\'affitto»',
        'casa' => 'Pagina «La casa e il suo stato»',
        'metodo' => 'Pagina «Come leggere questo report»',
    ];

    public const TESTI = [
        // La zona
        'zona_occhiello' => ['zona', 'Occhiello', 'La zona', [], [], 'riga'],
        'zona_titolo' => ['zona', 'Titolo', 'La zona OMI {zona_codice} · {zona_nome}', ['zona_codice', 'zona_nome'], [], 'riga'],
        'zona_indice' => ['zona', 'Nome nell\'indice di «In breve»', 'La zona', [], [], 'riga'],
        'zona_titolo_semplice' => ['zona', 'Titolo, se mancano il codice o il nome della zona', 'La zona della casa', [], [], 'riga'],
        'zona_frase' => ['zona', 'Frase sotto il titolo', 'La casa è nella zona {zona_codice}, {fascia} di {comune}. Molti dati di questo report riguardano la zona: per confrontare la casa con le sue vicine, non con tutta la città.', ['zona_codice', 'fascia'], [], 'testo'],
        'zona_quotazioni_t' => ['zona', 'Casella 1 · titolo', 'Le quotazioni OMI della zona', [], [], 'riga'],
        'zona_quotazioni_n' => ['zona', 'Casella 1 · nota', 'Dal minimo al massimo, {semestre}.', ['semestre'], [], 'casella'],
        'zona_medio_t' => ['zona', 'Casella 2 · titolo', 'Il valore medio della zona', [], [], 'riga'],
        'zona_medio_n' => ['zona', 'Casella 2 · nota', 'La stima di questa casa: {valore_mq}, {differenza}.', ['valore_mq', 'differenza'], [], 'casella'],
        'zona_vendute_t' => ['zona', 'Casella 3 · titolo', 'Case vendute nella zona', [], [], 'riga'],
        'zona_vendute_n' => ['zona', 'Casella 3 · nota', 'Compravendite del {anno_vendite} (NTN).', ['anno_vendite'], [], 'casella'],
        'zona_tempo_t' => ['zona', 'Casella 4 · titolo', 'Quanto resta in vendita una casa', [], [], 'riga'],
        'zona_tempo_n' => ['zona', 'Casella 4 · nota', 'Media degli annunci della zona nel {anno_tempi}.', ['anno_tempi'], [], 'casella'],
        'zona_fonte' => ['zona', 'Nota in fondo', 'Quotazioni e compravendite: Osservatorio del Mercato Immobiliare dell\'Agenzia delle Entrate. Tempo in vendita: annunci pubblicati online nella zona.', [], [], 'testo'],

        // Le case vicine
        'vicine_occhiello' => ['vicine', 'Occhiello', 'Il confronto', [], [], 'riga'],
        'vicine_titolo' => ['vicine', 'Titolo (anche nell\'indice)', 'Le case vicine', [], [], 'riga'],
        'vicine_frase' => ['vicine', 'Frase sotto il titolo, con annunci e più vendite ({case}: «Dieci case»; {annunci}: «cinque annunci» o «un annuncio»; {vendite}: «cinque compravendite»)', '{case} simili per caratteristiche e posizione: in blu {annunci} in vendita oggi, in verde {vendite} già avvenute, con il prezzo verificato presso l\'Agenzia delle Entrate.', ['case', 'annunci', 'vendite'], [], 'testo'],
        'vicine_frase_una' => ['vicine', 'Frase sotto il titolo, con annunci e una vendita sola', '{case} simili per caratteristiche e posizione: in blu {annunci} in vendita oggi, in verde una compravendita già avvenuta, con il prezzo verificato presso l\'Agenzia delle Entrate.', ['case', 'annunci'], [], 'testo'],
        'vicine_frase_annunci' => ['vicine', 'Frase sotto il titolo, solo annunci ({annunci}: «Cinque annunci»)', '{annunci} in vendita oggi, simili per caratteristiche e posizione.', ['annunci'], [], 'testo'],
        'vicine_frase_un_annuncio' => ['vicine', 'Frase sotto il titolo, un annuncio solo', 'Un annuncio in vendita oggi, simile per caratteristiche e posizione.', [], [], 'testo'],
        'vicine_frase_vendite' => ['vicine', 'Frase sotto il titolo, solo vendite ({vendite}: «Cinque compravendite»)', '{vendite} già avvenute, simili per caratteristiche e posizione, con il prezzo verificato presso l\'Agenzia delle Entrate.', ['vendite'], [], 'testo'],
        'vicine_frase_una_vendita' => ['vicine', 'Frase sotto il titolo, una vendita sola', 'Una compravendita già avvenuta, simile per caratteristiche e posizione, con il prezzo verificato presso l\'Agenzia delle Entrate.', [], [], 'testo'],
        'vicine_grafico_t' => ['vicine', 'Grafico · titolo', 'Prezzo al m²: chiesto, pagato e la stima', [], [], 'riga'],
        'vicine_chiesti' => ['vicine', 'Grafico · riga degli annunci', 'Prezzi chiesti', [], [], 'riga'],
        'vicine_pagati' => ['vicine', 'Grafico · riga delle vendite', 'Prezzi pagati', [], [], 'riga'],
        'vicine_media' => ['vicine', 'Grafico · davanti alla media', 'media', [], [], 'riga'],
        'vicine_stima' => ['vicine', 'Grafico · la stima', 'La stima: {valore_mq}', ['valore_mq'], [], 'riga'],
        'vicine_chiesti_n' => ['vicine', 'Numeri · sotto la media chiesta', 'chiesti in media dagli annunci', [], [], 'riga'],
        'vicine_pagati_n' => ['vicine', 'Numeri · sotto la media pagata', 'pagati in media nelle vendite vicine', [], [], 'riga'],
        'vicine_stima_n' => ['vicine', 'Numeri · sotto la stima', 'la stima di questa casa', [], [], 'riga'],

        // Il confronto voce per voce (e i nomi delle righe)
        'voce_occhiello' => ['voce', 'Occhiello', 'Il confronto', [], [], 'riga'],
        'voce_titolo' => ['voce', 'Titolo (anche nell\'indice)', 'Il confronto voce per voce', [], [], 'riga'],
        'voce_frase' => ['voce', 'Frase sotto il titolo', 'Ogni casa vicina messa accanto a questa, caratteristica per caratteristica. Per la casa il prezzo è quello stimato.', [], [], 'testo'],
        'voce_annunci_t' => ['voce', 'Titolo della tabella degli annunci', 'In vendita oggi', [], [], 'riga'],
        'voce_vendute_t' => ['voce', 'Titolo della tabella delle vendite', 'Vendute', [], [], 'riga'],
        'voce_casa' => ['voce', 'Intestazione della colonna della casa', 'La casa', [], [], 'riga'],
        'voce_piu' => ['voce', 'Legenda · ▲', 'più della casa', [], [], 'riga'],
        'voce_meno' => ['voce', 'Legenda · ▼', 'meno della casa', [], [], 'riga'],
        'voce_uguale' => ['voce', 'Legenda · =', 'uguale', [], [], 'riga'],
        'voce_stato' => ['voce', 'Legenda · lo stato', 'per lo stato, l\'ordine è: da ristrutturare, abitabile o buono, ottimo o ristrutturata, nuovo.', [], [], 'riga'],
        'voce_riga_distanza' => ['voce', 'Riga · distanza', 'Distanza', [], [], 'riga'],
        'voce_riga_tipologia' => ['voce', 'Riga · tipologia', 'Tipologia', [], [], 'riga'],
        'voce_riga_superficie' => ['voce', 'Riga · superficie', 'Superficie', [], [], 'riga'],
        'voce_riga_locali' => ['voce', 'Riga · locali', 'Locali', [], [], 'riga'],
        'voce_riga_piano' => ['voce', 'Riga · piano', 'Piano', [], [], 'riga'],
        'voce_riga_stato' => ['voce', 'Riga · stato', 'Stato', [], [], 'riga'],
        'voce_riga_ascensore' => ['voce', 'Riga · ascensore', 'Ascensore', [], [], 'riga'],
        'voce_riga_giardino' => ['voce', 'Riga · giardino', 'Giardino', [], [], 'riga'],
        'voce_riga_box' => ['voce', 'Riga · box auto', 'Box auto', [], [], 'riga'],
        'voce_riga_prezzo' => ['voce', 'Riga · prezzo', 'Prezzo', [], [], 'riga'],
        'voce_riga_prezzo_mq' => ['voce', 'Riga · prezzo al m²', 'Prezzo al m²', [], [], 'riga'],
        'voce_riga_superficie_catastale' => ['voce', 'Riga · superficie catastale', 'Superficie catastale', [], [], 'riga'],
        'voce_riga_superficie_stimata' => ['voce', 'Riga · superficie stimata', 'Superficie stimata', [], [], 'riga'],
        'voce_riga_categoria' => ['voce', 'Riga · categoria catastale', 'Categoria catastale', [], [], 'riga'],
        'voce_riga_data' => ['voce', 'Riga · data di vendita', 'Data di vendita', [], [], 'riga'],

        // Le schede degli annunci
        'schede_occhiello' => ['schede', 'Occhiello', 'In vendita', [], [], 'riga'],
        'schede_titolo' => ['schede', 'Titolo (anche nell\'indice)', 'Le case in vendita, scheda per scheda', [], [], 'riga'],
        'schede_frase' => ['schede', 'Frase sotto il titolo, con le mappe', 'Foto, descrizione e dati di ogni annuncio, con la sua posizione rispetto alla casa.', [], [], 'testo'],
        'schede_frase_senza_mappa' => ['schede', 'Frase sotto il titolo, senza le mappe', 'Foto, descrizione e dati di ogni annuncio.', [], [], 'testo'],
        'schede_frase_seguito' => ['schede', 'Frase sotto il titolo, nella seconda pagina', 'Seguono gli altri annunci.', [], [], 'testo'],
        'schede_distanza' => ['schede', 'Sotto l\'indirizzo', 'a {distanza} dalla casa', ['distanza'], [], 'riga'],
        'schede_sulla_stima' => ['schede', 'Accanto al prezzo al m²', '{differenza} sulla stima', ['differenza'], [], 'riga'],
        'schede_riga' => ['schede', 'Riga sotto ogni scheda', 'Annuncio n. {numero} · foto e descrizione dall\'annuncio pubblicato online', ['numero'], [], 'riga'],
        'schede_nota' => ['schede', 'Nota in fondo', 'I prezzi sono quelli chiesti dai venditori, non quelli di vendita. Foto e descrizioni sono riportate così come pubblicate negli annunci, accorciate.', [], [], 'testo'],
        'schede_senza_foto' => ['schede', 'Al posto di una foto che manca', 'Foto non disponibile', [], [], 'riga'],

        // Le vendute
        'vendute_occhiello' => ['vendute', 'Occhiello', 'Vendute', [], [], 'riga'],
        'vendute_titolo' => ['vendute', 'Titolo (anche nell\'indice)', 'Le case vendute, una per una', [], [], 'riga'],
        'vendute_frase' => ['vendute', 'Frase sotto il titolo ({vendite}: «Cinque compravendite»)', '{vendite} vicine e simili, con il prezzo effettivo verificato presso l\'Agenzia delle Entrate.', ['vendite'], [], 'testo'],
        'vendute_frase_una' => ['vendute', 'Frase sotto il titolo, una vendita sola', 'Una compravendita vicina e simile, con il prezzo effettivo verificato presso l\'Agenzia delle Entrate.', [], [], 'testo'],
        'vendute_rialzato' => ['vendute', 'Il piano rialzato', 'piano rialzato', [], [], 'riga'],
        'vendute_catastali' => ['vendute', 'La superficie', '{superficie} catastali', ['superficie'], [], 'riga'],
        'vendute_stimati' => ['vendute', 'La superficie stimata, dopo quella catastale', '({superficie} stimati)', ['superficie'], [], 'riga'],
        'vendute_piano' => ['vendute', 'Il piano', '{piano}° piano', ['piano'], [], 'riga'],
        'vendute_terra' => ['vendute', 'Il piano terra', 'piano terra', [], [], 'riga'],
        'vendute_quando' => ['vendute', 'Il mese della vendita', 'venduta a {mese}', ['mese'], [], 'riga'],
        'vendute_rif' => ['vendute', 'Riferimento', 'Rif. compravendita {numero}', ['numero'], [], 'riga'],
        'vendute_sulla_stima' => ['vendute', 'Accanto al prezzo al m²', '{differenza} sulla stima', ['differenza'], [], 'riga'],
        'vendute_nota' => ['vendute', 'Nota in fondo', 'Le compravendite più vecchie raccontano il mercato di allora: guarda la data accanto a ciascuna.', [], [], 'testo'],

        // Domanda e offerta
        'domanda_occhiello' => ['domanda', 'Occhiello', 'Il mercato', [], [], 'riga'],
        'domanda_titolo' => ['domanda', 'Titolo (anche nell\'indice)', 'Domanda e offerta', [], [], 'riga'],
        'domanda_frase' => ['domanda', 'Frase sotto il titolo', 'Che cosa si compra e che cosa c\'è in vendita: se la casa è in una fascia cercata o in una fascia affollata.', [], [], 'testo'],
        'domanda_superficie_t' => ['domanda', 'Grafico per superficie · titolo', 'Per superficie: le case vendute in città e quelle in vendita nella zona', [], [], 'riga'],
        'domanda_fascia_casa' => ['domanda', 'Grafico per superficie · sotto la fascia della casa', 'la fascia della casa', [], [], 'riga'],
        'domanda_case' => ['domanda', 'Grafico per superficie · dopo il numero delle vendute', 'case', [], [], 'riga'],
        'domanda_annunci' => ['domanda', 'Grafico per superficie · dopo il numero degli annunci', 'annunci', [], [], 'riga'],
        'domanda_leg_vendute' => ['domanda', 'Grafico per superficie · legenda delle vendute', 'case vendute a {comune} nel {anno}', ['anno'], [], 'riga'],
        'domanda_leg_annunci' => ['domanda', 'Grafico per superficie · legenda degli annunci', 'annunci in vendita oggi nella zona {zona_codice}', ['zona_codice'], [], 'riga'],
        'domanda_anni_t' => ['domanda', 'Grafico anno per anno · titolo', 'Case vendute nella zona {zona_codice}, anno per anno', ['zona_codice'], [], 'riga'],
        'domanda_anni_n' => ['domanda', 'Grafico anno per anno · nota', 'Compravendite dell\'anno (NTN), Osservatorio del Mercato Immobiliare.', [], [], 'riga'],
        'domanda_locali_t' => ['domanda', 'Grafico dei locali · titolo', 'Annunci per numero di locali, nella zona', [], [], 'riga'],
        'domanda_giorni_t' => ['domanda', 'Grafico dei giorni · titolo', 'Quanti giorni resta in vendita una casa', [], [], 'riga'],
        'domanda_giorni_parziale' => ['domanda', 'Grafico dei giorni · se l\'ultimo anno è quello in corso', 'giorni in media · {anno}: anno in corso', ['anno'], [], 'riga'],
        'domanda_giorni_n' => ['domanda', 'Grafico dei giorni · nota', 'Più il numero scende, più in fretta si vende nella zona.', [], [], 'riga'],

        // Il valore nel tempo
        'tempo_occhiello' => ['tempo', 'Occhiello', 'Il valore nel tempo', [], [], 'riga'],
        'tempo_titolo' => ['tempo', 'Titolo', 'Quanto valeva, quanto vale', [], [], 'riga'],
        'tempo_indice' => ['tempo', 'Nome nell\'indice di «In breve»', 'Il valore nel tempo', [], [], 'riga'],
        'tempo_frase' => ['tempo', 'Frase sotto il titolo', 'Il valore di oggi riportato indietro, semestre per semestre, seguendo le quotazioni della zona.', [], [], 'testo'],
        'tempo_valore_t' => ['tempo', 'Grafico del valore · titolo', 'Il valore stimato della casa, semestre per semestre', [], [], 'riga'],
        'tempo_valore_dal' => ['tempo', 'Grafico del valore · variazione accanto al titolo', '{variazione} dal {anno_inizio}', ['variazione', 'anno_inizio'], [], 'riga'],
        'tempo_oggi' => ['tempo', 'Grafico del valore · dopo l\'ultimo valore', 'oggi', [], [], 'riga'],
        'tempo_quotazioni_t' => ['tempo', 'Grafico delle quotazioni · titolo', 'Le quotazioni al m², semestre per semestre', [], [], 'riga'],
        'tempo_q_zona' => ['tempo', 'Grafico delle quotazioni · dopo il valore della zona', 'zona', [], [], 'riga'],
        'tempo_q_citta' => ['tempo', 'Grafico delle quotazioni · dopo il valore della città', 'città', [], [], 'riga'],
        'tempo_q_max' => ['tempo', 'Grafico delle quotazioni · dopo il massimo', 'max', [], [], 'riga'],
        'tempo_q_min' => ['tempo', 'Grafico delle quotazioni · dopo il minimo', 'min', [], [], 'riga'],
        'tempo_q_stima' => ['tempo', 'Grafico delle quotazioni · la stima', 'La stima: {valore_mq}', ['valore_mq'], [], 'riga'],
        'tempo_leg_fascia' => ['tempo', 'Grafico delle quotazioni · legenda della fascia', 'quotazioni OMI della zona, dal minimo al massimo', [], [], 'riga'],
        'tempo_leg_zona' => ['tempo', 'Grafico delle quotazioni · legenda della zona', 'valore medio della zona', [], [], 'riga'],
        'tempo_leg_citta' => ['tempo', 'Grafico delle quotazioni · legenda della città', 'valore medio della città', [], [], 'riga'],
        'tempo_quotazioni_n' => ['tempo', 'Grafico delle quotazioni · nota', 'Oggi nella zona {zona_codice} le quotazioni OMI vanno da {minimo} a {massimo} €/m², con un valore medio di {medio} €/m²: la stima di questa casa è {differenza} rispetto al valore medio. Dal {semestre_inizio} il valore medio è cambiato del {variazione_zona} nella zona e del {variazione_citta} in tutta {comune}.', ['zona_codice', 'minimo', 'massimo', 'medio', 'differenza', 'semestre_inizio', 'variazione_zona', 'variazione_citta'], [], 'testo'],
        'tempo_vendute_t' => ['tempo', 'Grafico delle vendite · titolo', 'Case vendute a {comune}, rispetto al {anno_inizio}', ['anno_inizio'], [], 'riga'],
        'tempo_vendute_ultimo' => ['tempo', 'Numeri · sotto l\'ultimo anno', 'case vendute a {comune} nel {anno}', ['anno'], [], 'riga'],
        'tempo_vendute_primo' => ['tempo', 'Numeri · sotto il primo anno', 'nel {anno}', ['anno'], [], 'riga'],

        // L'offerta
        'prezzi_occhiello' => ['prezzi', 'Occhiello', 'L\'offerta', [], [], 'riga'],
        'prezzi_titolo' => ['prezzi', 'Titolo (anche nell\'indice)', 'I prezzi chiesti oggi', [], [], 'riga'],
        'prezzi_frase' => ['prezzi', 'Frase sotto il titolo', 'Quanto chiedono al m² i {annunci} annunci pubblicati oggi nella zona {zona_codice} ({tipologie}), e dove si colloca la stima.', ['annunci', 'zona_codice', 'tipologie'], [], 'testo'],
        'prezzi_tipi_quasi' => ['prezzi', 'Le tipologie, se quasi tutti appartamenti (dentro la frase)', 'quasi tutti appartamenti: {appartamenti}', ['appartamenti'], [], 'riga'],
        'prezzi_tipi_elenco' => ['prezzi', 'Le tipologie, altrimenti (dentro la frase)', '{appartamenti} appartamenti, {ville} ville e {schiera} ville a schiera', ['appartamenti', 'ville', 'schiera'], [], 'riga'],
        'prezzi_istogramma_t' => ['prezzi', 'Grafico per prezzo · titolo', 'Annunci per prezzo al m²', [], [], 'riga'],
        'prezzi_istogramma_n' => ['prezzi', 'Grafico per prezzo · nota', 'In verde la fascia che contiene la stima ({valore_mq}).', ['valore_mq'], [], 'riga'],
        'prezzi_leggere_t' => ['prezzi', '«Come leggerlo» · titolo', 'Come leggerlo', [], [], 'riga'],
        'prezzi_leggere' => ['prezzi', '«Come leggerlo» · testo', 'Ogni barra conta gli annunci in una fascia di prezzo al m².', [], [], 'testo'],
        'prezzi_affollata' => ['prezzi', '«Come leggerlo» · se la fascia della stima è la più affollata', 'La fascia della stima è la più affollata.', [], [], 'riga'],
        'prezzi_sotto_sopra' => ['prezzi', '«Come leggerlo» · sotto e sopra la stima', 'Il {sotto} degli annunci chiede meno della fascia della stima, il {sopra} chiede di più.', ['sotto', 'sopra'], [], 'testo'],
        'prezzi_stima_t' => ['prezzi', 'Riquadro verde · titolo', 'La stima nell\'offerta', [], [], 'riga'],
        'prezzi_stima_n' => ['prezzi', 'Riquadro verde · sotto il numero', 'fascia {da}–{a} €/m²', ['da', 'a'], [], 'riga'],
        'prezzi_intervallo_t' => ['prezzi', 'Grafico basso e alto · titolo', 'Il prezzo al m² più basso e più alto tra gli annunci', [], [], 'riga'],
        'prezzi_intervallo_zona' => ['prezzi', 'Grafico basso e alto · la riga della zona', 'Zona {zona_codice}', ['zona_codice'], [], 'riga'],
        'prezzi_intervallo_stima' => ['prezzi', 'Grafico basso e alto · la stima', 'La stima: {valore_mq}', ['valore_mq'], [], 'riga'],
        'prezzi_superfici_t' => ['prezzi', 'Grafico per superficie · titolo', 'Le case vendute a {comune}, anno per anno, per superficie', [], [], 'riga'],
        'prezzi_tua_casa' => ['prezzi', 'Grafico per superficie · accanto alla fascia della casa', 'la tua casa', [], [], 'riga'],

        // L'affitto
        'affitto_occhiello' => ['affitto', 'Occhiello', 'L\'affitto', [], [], 'riga'],
        'affitto_titolo' => ['affitto', 'Titolo (anche nell\'indice)', 'Se decidessi di affittarla', [], [], 'riga'],
        'affitto_frase' => ['affitto', 'Frase sotto il titolo', 'L\'affitto stimato segue le quotazioni di locazione della zona, moltiplicate per la superficie della casa.', [], [], 'testo'],
        'affitto_grafico_t' => ['affitto', 'Grafico · titolo', 'L\'affitto stimato al mese, semestre per semestre', [], [], 'riga'],
        'affitto_dal' => ['affitto', 'Grafico · variazione accanto al titolo', '{variazione} dal {anno_inizio}', ['variazione', 'anno_inizio'], [], 'riga'],
        'affitto_grafico_n' => ['affitto', 'Grafico · nota', 'Quotazione di locazione della zona × {superficie}: da {primo} €/m² al mese nel {semestre_primo} a {ultimo} €/m² nel {semestre_ultimo}.', ['superficie', 'primo', 'semestre_primo', 'ultimo', 'semestre_ultimo'], [], 'testo'],
        'affitto_rende_t' => ['affitto', 'Rendimento · titolo', 'Quanto rende', [], [], 'riga'],
        'affitto_rende' => ['affitto', 'Rendimento · testo', 'Il rendimento lordo è l\'affitto di un anno diviso per il prezzo della casa, prima di tasse e spese. Le barre mostrano tra quali valori si muove nella zona e in tutta la città.', [], [], 'testo'],
        'affitto_zona' => ['affitto', 'Rendimento · la riga della zona', 'Zona {zona_codice}', ['zona_codice'], [], 'riga'],
        'affitto_casa' => ['affitto', 'Rendimento · la casa', 'Questa casa, con i valori stimati: {rendimento}', ['rendimento'], [], 'riga'],
        'affitto_conto' => ['affitto', 'Rendimento · il conto', 'Per questa casa: {affitto} × 12 mesi ÷ {valore} = {rendimento} lordo l\'anno.', ['affitto', 'valore', 'rendimento'], [], 'testo'],

        // La casa
        'casa_occhiello' => ['casa', 'Occhiello', 'La casa', [], [], 'riga'],
        'casa_titolo' => ['casa', 'Titolo (anche nell\'indice)', 'La casa e il suo stato', [], [], 'riga'],
        'casa_frase' => ['casa', 'Frase sotto il titolo', 'I dati da cui parte la stima, e quanto cambia il valore con lo stato della casa.', [], [], 'testo'],
        'casa_dati_t' => ['casa', '«I dati che hai inserito» · titolo', 'I dati che hai inserito', [], [], 'riga'],
        'casa_riga_indirizzo' => ['casa', 'Riga · indirizzo', 'Indirizzo', [], [], 'riga'],
        'casa_riga_coordinate' => ['casa', 'Riga · coordinate', 'Coordinate', [], [], 'riga'],
        'casa_riga_piani' => ['casa', 'Riga · piani dell\'edificio', 'Piani dell\'edificio', [], [], 'riga'],
        'casa_riga_anno' => ['casa', 'Riga · anno di costruzione', 'Anno di costruzione', [], [], 'riga'],
        'casa_riga_zona' => ['casa', 'Riga · zona OMI', 'Zona OMI', [], [], 'riga'],
        'casa_salto_t' => ['casa', 'Riquadro verde · titolo', 'Con una ristrutturazione completa', [], [], 'riga'],
        'casa_salto_n' => ['casa', 'Riquadro verde · sotto il numero', 'la differenza fra il valore della casa come l\'hai descritta e lo stesso immobile ristrutturato a nuovo', [], [], 'testo'],
        'casa_cambiano_t' => ['casa', '«Se i dati cambiano» · titolo', 'Se i dati cambiano', [], [], 'riga'],
        'casa_cambiano' => ['casa', '«Se i dati cambiano» · testo', 'La stima vale per la casa come l\'hai descritta. Se superficie, piano o stato sono diversi, anche il valore cambia: un nuovo report con i dati corretti dà una stima nuova.', [], [], 'testo'],
        'casa_stato_t' => ['casa', 'Grafico dello stato · titolo', 'Quanto conta lo stato della casa', [], [], 'riga'],
        'casa_stato_frase' => ['casa', 'Grafico dello stato · frase', 'Stessa casa, stessa posizione, tre stati diversi.', [], [], 'testo'],
        'casa_descritta' => ['casa', 'Grafico dello stato · la casa com\'è', 'Come l\'hai descritta', [], [], 'riga'],
        'casa_forchetta' => ['casa', 'Grafico dello stato · la forchetta', 'forchetta {minimo}–{massimo} €', ['minimo', 'massimo'], [], 'riga'],
        'casa_stato_n' => ['casa', 'Grafico dello stato · nota', '«Da ristrutturare»: condizioni interne scadenti. «Ristrutturata a nuovo»: lavori completi, con materiali di qualità e criteri di risparmio energetico di oggi. La differenza non tiene conto del costo dei lavori.', [], [], 'testo'],

        // Il metodo
        'metodo_occhiello' => ['metodo', 'Occhiello', 'Il metodo', [], [], 'riga'],
        'metodo_titolo' => ['metodo', 'Titolo (anche nell\'indice)', 'Come leggere questo report', [], [], 'riga'],
        'metodo_frase' => ['metodo', 'Frase sotto il titolo', 'Che cosa dice questo report, che cosa non dice e da dove vengono i dati.', [], [], 'testo'],
        'metodo_cosa_t' => ['metodo', 'Blocco 1 · titolo', 'Che cos\'è', [], [], 'riga'],
        'metodo_cosa' => ['metodo', 'Blocco 1 · testo', 'Una stima automatica del valore di mercato: un report costruito da un modello statistico che confronta la casa con grandi archivi di dati immobiliari. Il report è generato in modo completamente automatico e nessuno ha visitato la casa: contano i dati che hai inserito.', [], [], 'testo'],
        'metodo_forchetta_t' => ['metodo', 'Blocco 2 · titolo', 'Il valore e la forchetta', [], [], 'riga'],
        'metodo_forchetta' => ['metodo', 'Blocco 2 · testo', 'Il valore centrale è il più probabile; la forchetta indica dove il valore reale cade con buona probabilità: più è stretta, più la stima è precisa.', [], [], 'testo'],
        'metodo_confronti_t' => ['metodo', 'Blocco 3 · titolo', 'I confronti', [], [], 'riga'],
        'metodo_confronti' => ['metodo', 'Blocco 3 · testo', 'Annunci e compravendite sono scelti in automatico, per somiglianza e vicinanza. Negli annunci il prezzo è quello chiesto dal venditore; nelle compravendite è quello pagato.', [], [], 'testo'],
        'metodo_zone_t' => ['metodo', 'Blocco 4 · titolo', 'Le zone e le quotazioni', [], [], 'riga'],
        'metodo_zone' => ['metodo', 'Blocco 4 · testo', 'Zone, quotazioni di vendita e di affitto e numero di compravendite vengono dall\'Osservatorio del Mercato Immobiliare dell\'Agenzia delle Entrate.', [], [], 'testo'],
        'metodo_fonti_t' => ['metodo', '«Da dove vengono i dati» · titolo', 'Da dove vengono i dati', [], [], 'riga'],
        'metodo_fonte_omi' => ['metodo', 'Fonte · quotazioni e compravendite', 'Zone, quotazioni e compravendite: Osservatorio del Mercato Immobiliare (OMI), Agenzia delle Entrate', [], [], 'riga'],
        'metodo_fonte_vendite' => ['metodo', 'Fonte · compravendite vicine', 'Compravendite vicine: atti con prezzo verificato presso l\'Agenzia delle Entrate', [], [], 'riga'],
        'metodo_fonte_annunci' => ['metodo', 'Fonte · annunci', 'Annunci simili, foto e descrizioni: annunci immobiliari pubblicati online', [], [], 'riga'],
        'metodo_fonte_territorio' => ['metodo', 'Fonte · territorio (con «Il posto»)', 'Territorio: Protezione Civile; ISPRA, IdroGEO; AGCOM, BroadbandMap', [], [], 'riga'],
        'metodo_fonte_vista' => ['metodo', 'Fonte · vista dall\'alto (con «Il posto»)', 'Vista dall\'alto: ricostruzione Cerca.Re dagli edifici di OpenStreetMap (ODbL); altezze dal numero di piani, dove indicato, altrimenti stimate', [], [], 'riga'],
        'metodo_fonte_mappe' => ['metodo', 'Fonte · mappe e dintorni (con le mappe e i dintorni, lotti 4 e 5)', 'Mappe e dintorni: © contributori OpenStreetMap (ODbL), elaborazione Cerca.Re · tempi di percorrenza: Geoapify', [], [], 'riga'],
    ];

    /**
     * Ogni fonte di «Da dove vengono i dati» e quando si stampa (D-VAL-61):
     * se c'è almeno una delle pagine o è acceso almeno uno dei riquadri.
     */
    public const FONTI = [
        'metodo_fonte_omi' => [['in_breve', 'zona', 'domanda', 'tempo', 'prezzi', 'affitto'], []],
        'metodo_fonte_vendite' => [['vicine', 'voce', 'vendute'], []],
        'metodo_fonte_annunci' => [['vicine', 'voce', 'schede', 'domanda', 'prezzi'], []],
        'metodo_fonte_territorio' => [['posto'], []],
        'metodo_fonte_vista' => [[], ['posto_vista']],
        'metodo_fonte_mappe' => [['dintorni'], ['cop_mappa', 'zona_mappa', 'vicine_mappa', 'schede_mappa', 'vendute_mappa']],
    ];

    /**
     * I riquadri delle pagine del lotto 2: nome => [sezione, etichetta,
     * acceso di fabbrica, si può accendere oggi, nota].
     */
    public const RIQUADRI = [
        'zona_mappa' => ['zona', 'Mappa della zona', true, true, null],
        'zona_caselle' => ['zona', 'Le quattro caselle', true, true, null],
        'zona_fonte' => ['zona', 'Nota in fondo', true, true, null],
        'vicine_mappa' => ['vicine', 'Mappa delle case vicine', true, true, null],
        'vicine_grafico' => ['vicine', 'Grafico «chiesto, pagato e la stima»', true, true, null],
        'vicine_numeri' => ['vicine', 'I tre numeri in fondo', true, true, null],
        'voce_annunci' => ['voce', 'Tabella degli annunci', true, true, null],
        'voce_vendute' => ['voce', 'Tabella delle vendite', true, true, null],
        'voce_legenda' => ['voce', 'Legenda delle frecce', true, true, null],
        'schede_mappa' => ['schede', 'Mappa piccola di ogni scheda', true, true, null],
        'schede_foto' => ['schede', 'Foto degli annunci', true, true, 'Spenta, le schede escono senza foto.'],
        'schede_descrizione' => ['schede', 'Descrizioni degli annunci', true, true, null],
        'schede_riga' => ['schede', 'Riga sotto ogni scheda', true, true, null],
        'schede_nota' => ['schede', 'Nota in fondo', true, true, null],
        'vendute_mappa' => ['vendute', 'Mappa delle vendite', true, true, null],
        'vendute_rif' => ['vendute', 'Riferimento di ogni compravendita', true, true, null],
        'vendute_nota' => ['vendute', 'Nota in fondo', true, true, null],
        'domanda_superficie' => ['domanda', 'Grafico per superficie', true, true, null],
        'domanda_anni' => ['domanda', 'Grafico anno per anno', true, true, null],
        'domanda_locali' => ['domanda', 'Grafico dei locali', true, true, null],
        'domanda_giorni' => ['domanda', 'Grafico dei giorni in vendita', true, true, null],
        'tempo_valore' => ['tempo', 'Grafico del valore stimato', true, true, null],
        'tempo_quotazioni' => ['tempo', 'Grafico delle quotazioni', true, true, null],
        'tempo_vendute' => ['tempo', 'Grafico e numeri delle case vendute in città', true, true, null],
        'prezzi_istogramma' => ['prezzi', 'Grafico per prezzo al m²', true, true, null],
        'prezzi_lettura' => ['prezzi', '«Come leggerlo» e il riquadro verde', true, true, null],
        'prezzi_intervallo' => ['prezzi', 'Grafico del prezzo più basso e più alto', true, true, null],
        'prezzi_superfici' => ['prezzi', 'Grafico delle vendute per superficie', true, true, null],
        'affitto_grafico' => ['affitto', 'Grafico dell\'affitto', true, true, null],
        'affitto_rendimento' => ['affitto', '«Quanto rende», col grafico', true, true, null],
        'casa_dati' => ['casa', '«I dati che hai inserito»', true, true, null],
        'casa_riquadri' => ['casa', 'Riquadro verde e «Se i dati cambiano»', true, true, null],
        'casa_stato' => ['casa', 'Grafico dello stato', true, true, null],
        'metodo_blocchi' => ['metodo', 'I quattro blocchi', true, true, null],
        'metodo_fonti' => ['metodo', '«Da dove vengono i dati»', true, true, null],
    ];

    /** Le pagine del lotto 2, tutte accese di fabbrica: nome => [etichetta, accesa di fabbrica]. */
    public const PAGINE = [
        'zona' => ['Pagina «La zona»', true],
        'vicine' => ['Pagina «Le case vicine»', true],
        'voce' => ['Pagina «Il confronto voce per voce»', true],
        'schede' => ['Pagine «Le case in vendita, scheda per scheda»', true],
        'vendute' => ['Pagina «Le case vendute, una per una»', true],
        'domanda' => ['Pagina «Domanda e offerta»', true],
        'tempo' => ['Pagina «Il valore nel tempo»', true],
        'prezzi' => ['Pagina «I prezzi chiesti oggi»', true],
        'affitto' => ['Pagina «L\'affitto»', true],
        'casa' => ['Pagina «La casa e il suo stato»', true],
        'metodo' => ['Pagina «Come leggere questo report»', true],
    ];
}