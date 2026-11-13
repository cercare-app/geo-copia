<?php

namespace App\Support;

use App\Models\Abbonamento;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AiChiamata;
use App\Models\CodiceSito;
use App\Models\Consenso;
use App\Models\ConsensoFlag;
use App\Models\Feature;
use App\Models\Funzionalita;
use App\Models\ImportRun;
use App\Models\Invito;
use App\Models\Lead;
use App\Models\Piano;
use App\Models\Post;
use App\Models\PostaRegistro;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\RicercaFrase;
use App\Models\SeoMeta;
use App\Models\SeoPage;
use App\Models\Setting;
use App\Models\TestoLegale;
use App\Models\TipoOperatore;
use App\Models\User;

/**
 * I PERMESSI DEI RUOLI (Lotto «Ruoli e permessi», D-GL-206…214).
 *
 *  - L'amministratore vede e fa tutto, sempre: i ruoli non lo riguardano.
 *  - Uno Staff fa quello che spuntano le caselle del suo ruolo (Sistema →
 *    Ruoli). Senza ruolo entra nell'Admin e vede solo il Cruscotto.
 *  - Professionisti e privati non hanno caselle: per loro non cambia niente.
 *
 * Le caselle sono scritte qui, una per ciò che il codice sa controllare;
 * i ruoli si compongono dall'Admin spuntandole. «Eliminare» ed «esportare»
 * contano solo insieme a «vedere e lavorare» della stessa voce.
 *
 * Il controllo vero lo fa controlla(), agganciato al Gate di Laravel
 * (AppServiceProvider): Filament chiede al Gate prima di mostrare una voce
 * del menù, aprire una pagina, creare, modificare o eliminare, e per uno
 * Staff senza la casella giusta la risposta è «no». Dove la casella c'è, la
 * parola passa alle regole di sempre (policy e risorse): per esempio creare
 * ed eliminare le Pagine di zona chiede le loro caselle in più.
 *
 * «Ruoli e permessi 2» (D-GL-218…224): ogni voce del menù diventa una
 * casella, in tre lotti. Il lotto 2a porta Scheda · Impostazioni, Blog ·
 * Importa da Excel e tutta la SEO (Impostazioni SEO, Pagine del sito,
 * Pagine di zona complete ed eliminare, Codici e script). Le caselle sono
 * divise per gruppi del menù (GRUPPI, D-GL-221) e quelle delicate hanno il
 * segno ⚠ col perché (D-GL-220). Il lotto 2b porta tutto il resto tranne
 * Utenti e Ruoli: nelle Agenzie «approvare» e «invitare», il Commerciale,
 * l'AI, la Privacy, la Posta e le Impostazioni (dove la frase ACCENDO LA
 * POSTA resta). Le funzionalità dentro la pagina di un piano seguono la
 * casella «Piani» (PianoResource\RelationManagers\FunzionalitaRelationManager).
 *
 * Lotto «Cruscotto per ruolo» (D-GL-227…229): i riquadri coi numeri del
 * Cruscotto chiedono la casella «Numeri della Dashboard» (la migrazione la dà
 * al ruolo «Staff», così per lo Staff non cambia nulla); chi non l'ha vede
 * al loro posto le sue scorciatoie (App\Support\Scorciatoie).
 *
 * Lotto «AI dall'Admin e Dashboard per lo staff» (D-GL-276/277): la casella dei
 * numeri, ora «Numeri della Dashboard» come il menù, fa vedere «Il portale
 * oggi», numeri tranquilli; la casella nuova
 * «Numeri tecnici della Dashboard» (spenta, nessuna migrazione) fa vedere anche
 * i riquadri di AI, foto e import; «AI · Impostazioni» apre la pagina dei tetti
 * delle risposte dell'AI.
 *
 * Lotto «Dashboard dello staff e registro AI» (D-GL-306/313): la casella
 * «AI · Registro» non c'è più. Il registro lo vedono l'amministratore e le
 * persone dello Staff scelte una per una in «AI · Impostazioni»
 * (App\Support\RegistroAi): puo(…, 'ai_registro') chiede a RegistroAi, e una
 * casella «ai_registro» rimasta scritta in un ruolo non conta più.
 *
 * Lotto «Entra nel pannello dell'agenzia» (D-GL-338): «Agenzie: entrare nel
 * pannello per vedere» e «per lavorare», per l'assistenza
 * (App\Support\Assistenza). Come le altre, contano solo insieme ad «Agenzie».
 *
 * Lotto «Correttivi 2» (D-GL-253): «Editor HTML» è una casella a sé, nel
 * gruppo Contenuti; il pulsante sta nella barra dei testi (App\Support\EditorHtml,
 * Lotto «Editor HTML 2», D-GL-260).
 *
 * Lotto «Valutazione 1» (D-VAL-43): il gruppo «Valutazione», con due caselle.
 * «Valutazione · Testi» apre «Testi del report» e l'anteprima del report;
 * «Valutazione · Impostazioni» (⚠: cambia il prezzo e ciò che il report dice
 * di Instare) apre «Impostazioni» e l'anteprima. Caricare un altro JSON
 * d'esempio resta dell'amministratore.
 */
final class Permessi
{
    /** Perché una casella è delicata (D-GL-220): il segno ⚠ e questa riga. */
    public const DELICATA_SITO = 'Cambia tutto il sito pubblico.';

    public const DELICATA_DATI = 'Mostra dati personali.';

    public const DELICATA_EMAIL = 'Spedisce email vere.';

    public const DELICATA_POTERE = 'Dà potere sulle persone dell\'Admin.';

    /** Inviti: il link di attivazione basta per aprire l'accesso di un'agenzia (revisione del lotto 2b). */
    public const DELICATA_INVITI = 'Spedisce email vere, e il link di attivazione basta per aprire l\'accesso di un\'agenzia: il suo pannello, i suoi annunci e le sue richieste.';

    /** Codici e script: il codice gira anche nel browser dell'amministratore (revisione del lotto 2a). */
    public const DELICATA_CODICI = 'Il codice gira nel browser di chiunque apra il sito, amministratore compreso: chi lo scrive può agire al suo posto e raccogliere dati dei visitatori. Di fatto equivale a essere amministratore.';

    /**
     * Le caselle, nell'ordine del menù:
     * chiave => [voce, che cosa permette, spiegazione, perché è delicata (o null)].
     */
    public const CASELLE = [
        'cruscotto_numeri' => ['Numeri della Dashboard', 'vedere', 'Nella Dashboard il riquadro «Il portale oggi»: immobili online, agenzie, agenzie con accesso e inviti, richieste dei visitatori, privati registrati, blog, le agenzie con più richieste e le città con più immobili. Solo numeri, tutti dello stesso colore, senza nomi di persone. Le scorciatoie ci sono comunque.', null],
        'dashboard_tecnici' => ['Numeri tecnici della Dashboard', 'vedere', 'Nella Dashboard anche i riquadri tecnici, coi loro colori: l\'AI (semaforo, chiamate, consumi, modelli; gli errori solo a chi li vede nel registro AI), le foto, l\'ultimo import e gli import di stanotte. Servono a chi segue il funzionamento del sito.', null],
        'richieste' => ['Richieste', 'vedere e lavorare', 'Apre l\'elenco e le richieste e le modifica; scarica il file .ics dell\'appuntamento e vede il link del calendario delle agenzie.', null],
        'richieste.eliminare' => ['Richieste', 'eliminare', 'Elimina una richiesta, anche più di una insieme.', null],
        'agenzie' => ['Agenzie', 'vedere e lavorare', 'Apre l\'elenco e le schede delle agenzie, le crea e le modifica. «Approva» e «Invita operatore» hanno le loro caselle.', null],
        'agenzie.eliminare' => ['Agenzie', 'eliminare', 'Elimina un\'agenzia, anche più di una insieme.', null],
        'agenzie.esportare' => ['Agenzie', 'esportare', 'Il pulsante «Esporta» dell\'elenco: Excel, CSV e PDF degli operatori e delle persone collegate (email e telefono compresi).', null],
        'agenzie.approvare' => ['Agenzie', 'approvare', 'Il pulsante «Approva» delle agenzie iscritte dal sito: mette il timbro, l\'agenzia diventa pubblica, i suoi operatori ricevono l\'email «La tua agenzia è attiva» e la prova gratuita, dove prevista, conta i giorni da quel giorno (salvo una data d\'arrivo corretta a mano). L\'interruttore «Attiva» della scheda resta di chi ha «Agenzie».', self::DELICATA_EMAIL],
        'agenzie.invitare' => ['Agenzie', 'invitare', 'Il pulsante «Invita operatore»: crea il link di attivazione, lo mostra da copiare e spedisce l\'invito all\'email dell\'agenzia. Chi apre il link crea l\'accesso dell\'agenzia con l\'email che vuole.', self::DELICATA_INVITI],
        // Lotto «Entra nel pannello dell'agenzia» (D-GL-338): contano solo con l'interruttore «Accesso assistenza ai pannelli» acceso
        'agenzie.assistenza' => ['Agenzie', 'entrare nel pannello per vedere', 'Il pulsante «Entra nel pannello»: apre l\'Area Operatori di un\'agenzia come la vede uno dei suoi accessi, in sola lettura, per fare assistenza. Dentro ci sono le richieste dei clienti con nome, email e telefono. Ogni ingresso resta in «Assistenza · Registro».', self::DELICATA_DATI],
        'agenzie.assistenza_lavorare' => ['Agenzie', 'entrare nel pannello per lavorare', 'Come la casella qui sopra, ma dentro il pannello si può anche lavorare al posto dell\'agenzia: profilo, annunci, richieste. Ogni modifica resta in «Assistenza · Registro».', 'Mostra dati personali e fa lavorare al posto dell\'agenzia.'],
        'immobili' => ['Immobili', 'vedere e lavorare', 'Apre l\'elenco e le schede degli immobili, li crea e li modifica.', null],
        'immobili.eliminare' => ['Immobili', 'eliminare', 'Elimina un immobile, lo ripristina e lo elimina per sempre.', null],
        'scheda_impostazioni' => ['Scheda · Impostazioni', 'vedere e lavorare', 'Le frasi, gli interruttori e gli indicatori della scheda degli immobili, compresi i siti ammessi per video e tour 360°: valgono per tutte le schede.', null],
        'import' => ['Import', 'vedere', 'Il registro degli import dei gestionali, in sola lettura.', null],
        'blog' => ['Blog', 'vedere e lavorare', 'Scrive e modifica gli articoli, li guarda in anteprima prima di pubblicarli, prepara in bozza le modifiche a quelli già pubblicati, li duplica ed elimina le bozze mai pubblicate.', null],
        'blog.eliminare' => ['Blog', 'eliminare', 'Elimina un articolo, anche già pubblicato.', null],
        'blog.importare' => ['Blog', 'importare da Excel', 'La pagina «Importa da Excel»: tanti articoli insieme da un file, anche sovrascrivendo quelli già presenti.', null],
        'editor_html' => ['Editor HTML', 'usare', 'Il pulsante «HTML» in fondo alla barra dei testi dell\'Admin (articoli del blog, pagine di zona, testi legali): mostra il testo come codice, formattato, e lo fa cambiare sul posto. Serve anche la voce di quel testo. L\'editor tiene solo quello che sa mostrare, e il server toglie comunque script e comandi nascosti.', 'Può mettere nel testo immagini prese da altri siti, che il browser dei visitatori carica da lì.'],
        'seo_impostazioni' => ['Impostazioni SEO', 'vedere e lavorare', 'Tutta la pagina «Impostazioni SEO»: titolo, description e domande frequenti della home, nome del sito, description di riserva, modelli di titolo e description di schede e agenzie, immagini, favicon, verifiche di Google e Bing, profili social, dati dell\'azienda, pagina «non trovata», llms.txt e l\'interruttore «Sito indicizzabile».', self::DELICATA_SITO],
        'pagine_sito' => ['Pagine del sito', 'vedere e lavorare', 'Titolo, description, immagine social e «Non indicizzare questa pagina» delle pagine fisse del sito, home compresa: anche la home si può nascondere a Google. Le pagine non si creano e non si eliminano.', null],
        'pagine_zona' => ['Pagine di zona', 'vedere e lavorare', 'I campi della redazione: testi, domande e campi SEO.', null],
        'pagine_zona.completa' => ['Pagine di zona', 'tutti i campi', 'Anche coordinate, titolo H1, canonical, «Nascondi ai motori di ricerca», stato e data di pubblicazione, e creare pagine nuove.', 'Decide indirizzi e indicizzazione delle pagine di zona.'],
        'pagine_zona.eliminare' => ['Pagine di zona', 'eliminare', 'Elimina una pagina, anche più di una insieme: il suo indirizzo smette di esistere.', null],
        'codici' => ['Codici e script', 'vedere e lavorare', 'I codici inseriti in ogni pagina pubblica: statistiche, pixel, chat.', self::DELICATA_CODICI],
        'codici.eliminare' => ['Codici e script', 'eliminare', 'Elimina un codice.', null],
        'piani' => ['Piani', 'vedere e lavorare', 'Crea e modifica i piani commerciali (prezzi, giorni di prova, in vendita o no, piano predefinito) e, nella pagina del piano, le funzionalità incluse col loro valore.', null],
        'piani.eliminare' => ['Piani', 'eliminare', 'Elimina un piano che non ha abbonati.', null],
        'funzionalita' => ['Funzionalità', 'vedere e lavorare', 'Il registro delle funzionalità vendibili: le crea e le modifica; «Attiva» le spegne o le accende in tutti i piani insieme.', null],
        'funzionalita.eliminare' => ['Funzionalità', 'eliminare', 'Elimina una funzionalità che non sta in nessun piano.', null],
        'abbonamenti' => ['Abbonamenti', 'vedere e lavorare', 'Assegna e modifica a mano gli abbonamenti delle agenzie (piano, ciclo, prezzo, date e stato) e, nella scheda di un\'agenzia, le funzioni diverse solo per lei.', self::DELICATA_DATI],
        'abbonamenti.eliminare' => ['Abbonamenti', 'eliminare', 'Elimina un abbonamento che non è attivo.', null],
        'inviti' => ['Inviti', 'vedere e lavorare', 'L\'elenco degli inviti alle agenzie, con le email dei destinatari. «Nuovo invito» (a qualunque indirizzo) e «Reinvia» spediscono l\'invito e mostrano il link di attivazione da copiare; «Revoca» spegne il link.', self::DELICATA_INVITI],
        'inviti.eliminare' => ['Inviti', 'eliminare', 'Elimina un invito non ancora accettato.', null],
        'inviti_impostazioni' => ['Inviti · Impostazioni', 'vedere e lavorare', 'Accende e spegne la spedizione degli inviti (per accenderla serve la frase ACCENDO GLI INVITI), apre e chiude la pagina di accettazione, cambia le date dei due gruppi e il massimo di inviti per giro. Da questa pagina non parte nulla.', 'Accende la spedizione degli inviti: da lì partono email vere.'],
        'tipi_operatore' => ['Tipi operatore', 'vedere e lavorare', 'Crea e modifica i tipi di operatore (agenzia immobiliare, costruttore…) e li accende o li spegne.', null],
        'tipi_operatore.eliminare' => ['Tipi operatore', 'eliminare', 'Elimina un tipo che non ha agenzie collegate.', null],
        // Lotto «Valutazione 1» (D-VAL-43)
        'valutazione_testi' => ['Valutazione · Testi', 'vedere e lavorare', 'La pagina «Testi del report»: titoli, frasi, caselle, avvertenze, «Diritti e proprietà» e contatti del report della Valutazione, con l\'elenco delle versioni; la pagina «Anteprima del report», coi dati di esempio. Da qui non parte nulla e non si paga nulla.', null],
        // Lotto «Valutazione 2» (D-VAL-59)
        'valutazione_pagine' => ['Valutazione · Pagine e riquadri', 'vedere e lavorare', 'La pagina «Pagine e riquadri»: quali pagine e quali riquadri stampa il report della Valutazione, quanti annunci e vendite, le righe del confronto voce per voce, la lunghezza delle descrizioni; la pagina «Anteprima del report». Con anche «Valutazione · Testi» si torna a una versione di prima. Da qui non parte nulla e non si paga nulla.', null],
        // Lotto «Valutazione 3» (D-VAL-75)
        'valutazione_magazzino' => ['Valutazione · Magazzino geografico', 'vedere e lavorare', 'La pagina «Magazzino geografico»: da dove il sito legge la fibra di AGCOM e la copia di OpenStreetMap (file sul server o servizio su reesty), l\'indirizzo del servizio, le attese, la memoria, il riquadro massimo, le regioni, gli interruttori della fibra per la scheda e per il report; lo stato del servizio e delle edizioni, «Prova la fibra in un punto» e «Torna all\'edizione di prima». Non manda email e non paga nulla.', 'Decide da dove la scheda di ogni annuncio e il report leggono la fibra e le mappe: una scelta sbagliata le lascia «in lettura».'],
        // Lotto «Valutazione 4» (D-VAL-101)
        'valutazione_mappe' => ['Valutazione · Mappe e territorio', 'vedere e lavorare', 'La pagina «Mappe e territorio»: raggio, nomi delle vie, cerchi e scala di ogni mappa del report, la vista dall\'alto, i colori, i controlli della casa sulla mappa con le abbreviazioni delle vie, le caselle del territorio e i giorni per cui l\'anteprima ricorda le mappe; «Prova un indirizzo». Ogni salvataggio è una versione, come i testi. La pagina «Anteprima del report». Da qui non parte nulla e non si paga nulla.', null],
        'valutazione_impostazioni' => ['Valutazione · Impostazioni', 'vedere e lavorare', 'La pagina «Impostazioni» della Valutazione: il prezzo, i dati del titolare stampati nel report, la riga © del piè di pagina, «Powered by», i metadati del PDF e le parole in più della guardia; la pagina «Anteprima del report».', 'Cambia il prezzo e ciò che il report dice di Instare.'],
        'ai_frasi' => ['AI · Frasi', 'vedere e lavorare', 'Le frasi scritte nella ricerca del sito e come sono state capite, senza chi le ha scritte: «Segna come caso», «Esporta casi» e «Pulisci ora», che cancella le frasi vecchie come la pulizia notturna.', null],
        'ai_impostazioni' => ['AI · Impostazioni', 'vedere e lavorare', 'Il tetto della risposta di ogni funzione AI (ricerca, assistente, analisi, score, storico prezzi, tour audio, pagine di zona): quanto può scrivere al massimo il modello in una risposta.', 'Decide quanto può costare al massimo ogni risposta dell\'AI.'],
        'privacy_impostazioni' => ['Impostazioni privacy', 'vedere e lavorare', 'Versione dell\'informativa, link, interruttore e versione dei termini, cookie policy, avvertenze AI e loro interruttori, nome e telefono alla registrazione, telefono obbligatorio nei moduli e gli interruttori delle funzioni che trattano dati personali: registrazioni dei privati e degli operatori, profilo di matching, match e avvisi dei match.', 'Cambia tutto il sito pubblico e accende gli avvisi dei match, che spediscono email.'],
        'privacy_caselle' => ['Caselle dei consensi', 'vedere e lavorare', 'Le caselle dei consensi dei moduli del sito: le crea, le riscrive, le accende e le spegne.', 'Cambia le caselle dei consensi che i visitatori accettano.'],
        'privacy_caselle.eliminare' => ['Caselle dei consensi', 'eliminare', 'Elimina una casella che non è di sistema.', null],
        'privacy_testi' => ['Testi legali', 'vedere e lavorare', 'Titolo, testo, versione e data delle pagine legali del sito.', 'Cambia i testi legali pubblicati sul sito.'],
        'privacy_registro' => ['Registro dei consensi', 'vedere', 'Chi ha accettato che cosa e quando, con email e IP, e «Esporta CSV» di tutto il registro.', self::DELICATA_DATI],
        'posta_registro' => ['Posta · Registro', 'vedere', 'Le email partite e fermate, con destinatari ed esiti, in sola lettura. Fa vedere anche l\'avviso rosso del tetto della posta nel Cruscotto.', self::DELICATA_DATI],
        'posta_anteprime' => ['Posta · Anteprime', 'vedere', 'Ogni email come la vedrà chi la riceve, coi dati veri degli ultimi arrivati quando ci sono (richiesta, utente, operatore e agenzia, invito, prova gratuita). Da qui non parte nulla.', self::DELICATA_DATI],
        'posta_testi' => ['Posta · Testi', 'vedere e lavorare', 'Le parole di ogni email della piattaforma. Da qui non parte nulla.', 'Cambia le parole di tutte le email che partono.'],
        'impostazioni' => ['Impostazioni', 'vedere e lavorare', 'Le impostazioni del sito una per una: tra queste «Posta vera» (per accenderla serve la frase ACCENDO LA POSTA), l\'AI di piattaforma, «Sito indicizzabile», l\'apertura della pagina di accettazione degli inviti e quasi tutte quelle di «Impostazioni privacy» (registrazioni, profilo di matching, match e loro avvisi). Chi ha questa casella le cambia anche senza le altre.', 'Spedisce email vere e cambia tutto il sito pubblico.'],
    ];

    /** I gruppi del menù e le voci (la parte prima del punto) che contengono (D-GL-221). */
    public const GRUPPI = [
        'Cruscotto' => ['cruscotto_numeri', 'dashboard_tecnici'],
        'Catalogo' => ['richieste', 'agenzie', 'immobili', 'scheda_impostazioni', 'import'],
        'Contenuti' => ['blog', 'editor_html'],
        'SEO' => ['seo_impostazioni', 'pagine_sito', 'pagine_zona', 'codici'],
        'Commerciale' => ['piani', 'funzionalita', 'abbonamenti', 'inviti', 'inviti_impostazioni', 'tipi_operatore'],
        'Valutazione' => ['valutazione_testi', 'valutazione_pagine', 'valutazione_mappe', 'valutazione_impostazioni', 'valutazione_magazzino'],
        'AI' => ['ai_frasi', 'ai_impostazioni'],
        'Privacy' => ['privacy_impostazioni', 'privacy_caselle', 'privacy_testi', 'privacy_registro'],
        'Sistema' => ['posta_registro', 'posta_anteprime', 'posta_testi', 'impostazioni'],
    ];

    /** Il modello di ogni tabella e la voce a cui appartiene (anche le tabelle dentro una scheda). */
    public const MODELLI = [
        Lead::class => 'richieste',
        Agency::class => 'agenzie',
        Agent::class => 'agenzie',
        Property::class => 'immobili',
        PropertyMedia::class => 'immobili',
        Feature::class => 'immobili',
        ImportRun::class => 'import',
        Post::class => 'blog',
        SeoMeta::class => 'pagine_sito',
        SeoPage::class => 'pagine_zona',
        CodiceSito::class => 'codici',
        Piano::class => 'piani',
        Funzionalita::class => 'funzionalita',
        Abbonamento::class => 'abbonamenti',
        \App\Models\FunzionalitaEccezione::class => 'abbonamenti', // le funzioni diverse di un'agenzia (D-GL-346)
        Invito::class => 'inviti',
        TipoOperatore::class => 'tipi_operatore',
        AiChiamata::class => 'ai_registro',
        RicercaFrase::class => 'ai_frasi',
        ConsensoFlag::class => 'privacy_caselle',
        TestoLegale::class => 'privacy_testi',
        Consenso::class => 'privacy_registro',
        PostaRegistro::class => 'posta_registro',
        Setting::class => 'impostazioni',
    ];

    /** Le azioni di Filament che chiedono la casella «eliminare» della voce. */
    public const ABILITA_ELIMINARE = ['delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny'];

    /** Le caselle che danno un file da scaricare (il link firmato di App\Support\Esportazione). */
    public const ESPORTAZIONI = ['agenzie.esportare'];

    /** Ciò che resta dell'amministratore in questo lotto (D-GL-210): lo mostra la pagina dei Ruoli. */
    public const SOLO_AMMINISTRATORE = 'Utenti e Ruoli.';

    /** Uno Staff senza ruolo, o con un ruolo senza caselle. */
    public const NESSUNO = 'Nessun permesso: vede solo il Cruscotto';

    /**
     * Le caselle per il modulo del ruolo: «Richieste: vedere e lavorare», …,
     * col segno ⚠ su quelle delicate; con un gruppo, solo le sue.
     *
     * @return array<string, string>
     */
    public static function opzioni(?string $gruppo = null): array
    {
        $opzioni = [];
        foreach (self::delGruppo($gruppo) as $chiave => [$voce, $cosa, , $delicata]) {
            $opzioni[$chiave] = $voce.': '.$cosa.($delicata !== null ? ' ⚠' : '');
        }

        return $opzioni;
    }

    /**
     * La spiegazione di ogni casella e, per quelle delicate, il perché.
     *
     * @return array<string, string>
     */
    public static function spiegazioni(?string $gruppo = null): array
    {
        return array_map(
            fn (array $casella): string => $casella[2].($casella[3] !== null ? ' ⚠ '.$casella[3] : ''),
            self::delGruppo($gruppo),
        );
    }

    /**
     * Le caselle di un gruppo del menù (tutte, senza gruppo).
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: ?string}>
     */
    public static function delGruppo(?string $gruppo): array
    {
        if ($gruppo === null) {
            return self::CASELLE;
        }
        $voci = self::GRUPPI[$gruppo] ?? [];

        return array_filter(self::CASELLE, fn (string $chiave): bool => in_array(explode('.', $chiave)[0], $voci, true), ARRAY_FILTER_USE_KEY);
    }

    /** Il campo del modulo del ruolo per un gruppo: «permessi_catalogo», «permessi_seo»… */
    public static function campoDelGruppo(string $gruppo): string
    {
        return 'permessi_'.mb_strtolower($gruppo);
    }

    /**
     * Dai dati del ruolo ai campi del modulo, un campo per gruppo (si apre la modifica).
     *
     * @param  array<string, mixed>  $dati
     * @return array<string, mixed>
     */
    public static function dividi(array $dati): array
    {
        $scelte = self::pulisci($dati['permessi'] ?? []);
        foreach (array_keys(self::GRUPPI) as $gruppo) {
            $dati[self::campoDelGruppo($gruppo)] = array_values(array_intersect($scelte, array_keys(self::delGruppo($gruppo))));
        }
        unset($dati['permessi']);

        return $dati;
    }

    /**
     * Dai campi del modulo, uno per gruppo, ai permessi del ruolo (si salva).
     *
     * @param  array<string, mixed>  $dati
     * @return array<string, mixed>
     */
    public static function unisci(array $dati): array
    {
        $campi = array_map(fn (string $gruppo): string => self::campoDelGruppo($gruppo), array_keys(self::GRUPPI));
        if (array_intersect($campi, array_keys($dati)) === []) {
            return $dati; // nessun campo dei gruppi: i permessi restano quelli che sono
        }

        $scelte = [];
        foreach (array_keys(self::GRUPPI) as $gruppo) {
            $campo = self::campoDelGruppo($gruppo);
            $scelte = [...$scelte, ...(is_array($dati[$campo] ?? null) ? $dati[$campo] : [])];
            unset($dati[$campo]);
        }
        $dati['permessi'] = self::pulisci($scelte);

        return $dati;
    }

    /**
     * Le caselle valide, una volta sola e nell'ordine del menù: ciò che non è
     * una casella (un nome sbagliato, un avanzo) si scarta.
     *
     * @return array<int, string>
     */
    public static function pulisci(mixed $permessi): array
    {
        $scelte = is_array($permessi) ? array_filter($permessi, 'is_string') : [];

        return array_values(array_filter(array_keys(self::CASELLE), fn (string $chiave): bool => in_array($chiave, $scelte, true)));
    }

    /**
     * Il ruolo in parole, per la lista dei Ruoli: «Richieste (anche
     * eliminare) · Import · Blog», o «Nessun permesso…».
     */
    public static function riassunto(mixed $permessi): string
    {
        $scelte = self::pulisci($permessi);
        $pezzi = [];
        foreach (self::CASELLE as $chiave => [$voce]) {
            if (str_contains($chiave, '.') || ! in_array($chiave, $scelte, true)) {
                continue;
            }
            $extra = [];
            foreach ($scelte as $scelta) {
                if (str_starts_with($scelta, $chiave.'.')) {
                    $extra[] = self::CASELLE[$scelta][1];
                }
            }
            // una voce che ha già « · » nel nome va tra virgolette, per non sembrare due voci
            $nome = str_contains($voce, ' · ') ? '«'.$voce.'»' : $voce;
            $pezzi[] = $nome.($extra !== [] ? ' (anche '.implode(', ', $extra).')' : '');
        }

        return $pezzi === [] ? self::NESSUNO : implode(' · ', $pezzi);
    }

    /**
     * Questa persona può? L'amministratore sempre; uno Staff se il suo ruolo ha
     * la casella (e, per «eliminare» ed «esportare», anche «vedere e lavorare»
     * della stessa voce); nessun altro.
     */
    public static function puo(mixed $utente, string $permesso): bool
    {
        if (! $utente instanceof User) {
            return false;
        }
        // il registro AI non è più una casella: lo decide l'amministratore persona per persona (D-GL-306)
        if ($permesso === 'ai_registro' || str_starts_with($permesso, 'ai_registro.')) {
            return RegistroAi::vede($utente);
        }
        if ($utente->isAdmin()) {
            return true;
        }
        if (! $utente->isStaff()) {
            return false;
        }

        $scelte = self::pulisci($utente->ruolo?->permessi);
        $voce = explode('.', $permesso)[0];

        return in_array($permesso, $scelte, true) && in_array($voce, $scelte, true);
    }

    /**
     * Può scaricare i file delle esportazioni? (il controller dello
     * scaricamento) Chi ha una casella di esportazione, e chi vede i
     * pagamenti per l'esportazione degli ordini (Lotto «La pagina Pagamenti»,
     * D-GL-382).
     */
    public static function puoScaricare(mixed $utente): bool
    {
        foreach (self::ESPORTAZIONI as $permesso) {
            if (self::puo($utente, $permesso)) {
                return true;
            }
        }

        return Cassa::vedePagamenti($utente);
    }

    /**
     * Il controllo agganciato al Gate (Gate::before). Riguarda SOLO gli Staff e
     * SOLO le tabelle delle voci qui sopra: false = no; null = decidono le
     * regole di sempre. Per tutti gli altri (amministratore, professionisti,
     * privati) e per tutte le altre tabelle risponde sempre null: lì non
     * cambia nulla.
     */
    public static function controlla(mixed $utente, mixed $abilita, array $argomenti): ?bool
    {
        if (! $utente instanceof User || ! $utente->isStaff()) {
            return null;
        }

        // Filament può chiedere anche con un enum al posto del nome dell'azione
        $abilita = $abilita instanceof \BackedEnum ? (string) $abilita->value : ($abilita instanceof \UnitEnum ? $abilita->name : (string) $abilita);

        $modello = $argomenti[0] ?? null;
        $classe = is_object($modello) ? $modello::class : (is_string($modello) ? $modello : null);
        $voce = $classe !== null ? (self::MODELLI[$classe] ?? null) : null;
        if ($voce === null) {
            return null;
        }

        // Una bozza mai pubblicata la elimina anche chi ha solo «Blog» (Lotto «Bozze
        // e anteprime», D-GL-246): duplicare crea bozze, e chi le crea le toglie.
        // Solo «delete» su un articolo preciso: mai in blocco, mai per sempre. L'unica
        // eccezione è la bozza di lavoro di un articolo pubblicato (Lotto «Modifica in
        // bozza», D-GL-250/252): scartata o pubblicata sparisce per sempre, e
        // l'originale non si tocca (PostResource::azioneElimina e pubblicaAlPosto).
        if ($abilita === 'delete' && $modello instanceof Post && $modello->maiPubblicato()) {
            return self::puo($utente, 'blog') ? null : false;
        }

        $permesso = in_array($abilita, self::ABILITA_ELIMINARE, true) ? $voce.'.eliminare' : $voce;

        return self::puo($utente, $permesso) ? null : false;
    }
}