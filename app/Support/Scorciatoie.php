<?php

namespace App\Support;

use App\Filament\Pages\AiImpostazioni;
use App\Filament\Pages\BlogImporta;
use App\Filament\Pages\InvitiImpostazioni;
use App\Filament\Pages\PostaAnteprime;
use App\Filament\Pages\PostaTesti;
use App\Filament\Pages\PrivacyImpostazioni;
use App\Filament\Pages\SchedaImpostazioni;
use App\Filament\Pages\SeoImpostazioni;
use App\Filament\Resources\AbbonamentoResource;
use App\Filament\Resources\AgencyResource;
use App\Filament\Resources\AiChiamataResource;
use App\Filament\Resources\CodiceSitoResource;
use App\Filament\Resources\ConsensoFlagResource;
use App\Filament\Resources\ConsensoResource;
use App\Filament\Resources\FunzionalitaResource;
use App\Filament\Resources\ImportRunResource;
use App\Filament\Resources\InvitoResource;
use App\Filament\Resources\LeadResource;
use App\Filament\Resources\PianoResource;
use App\Filament\Resources\PostaRegistroResource;
use App\Filament\Resources\PostResource;
use App\Filament\Resources\PropertyResource;
use App\Filament\Resources\RicercaFraseResource;
use App\Filament\Resources\AssistenzaIngressoResource;
use App\Filament\Resources\RuoloResource;
use App\Filament\Resources\SeoMetaResource;
use App\Filament\Resources\SeoPageResource;
use App\Filament\Resources\SettingResource;
use App\Filament\Resources\TestoLegaleResource;
use App\Filament\Resources\TipoOperatoreResource;
use App\Filament\Resources\UserResource;
use App\Models\Post;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;

/**
 * LE SCORCIATOIE DEL CRUSCOTTO (Lotto «Cruscotto per ruolo», D-GL-227…229).
 *
 * I riquadri coi numeri del Cruscotto li vede solo chi ha la casella
 * «Numeri della Dashboard» (l'amministratore sempre). Gli altri Staff vedono
 * al loro posto:
 *  - le azioni rapide che il loro ruolo permette (Nuovo articolo, Importa
 *    articoli da Excel, Nuova pagina di zona);
 *  - un pulsante per ogni voce del menù che possono aprire, nei gruppi del
 *    menù (AdminPanelProvider::GRUPPI), con l'icona e una riga che dice a
 *    cosa serve. I controlli sono gli stessi del menù: se una voce c'è nel
 *    menù, c'è anche qui, e viceversa;
 *  - con il Blog, il lavoro in corso: le ultime bozze e gli articoli
 *    programmati.
 *
 * Qui stanno i dati; i riquadri sono ScorciatoieWidget e LavoroInCorsoWidget.
 *
 * Lotto «AI dall'Admin e Dashboard per lo staff» (D-GL-277): ogni Staff vede
 * le sue scorciatoie; chi ha «Numeri della Dashboard» vede in più «Il portale
 * oggi» (PortaleOggiWidget) e chi ha «Numeri tecnici della Dashboard» anche i
 * riquadri di AI, foto e import. L'amministratore vede tutti i numeri e non
 * ha scorciatoie: il suo menù è completo.
 */
final class Scorciatoie
{
    /** La casella che fa vedere «Il portale oggi». */
    public const CASELLA_NUMERI = 'cruscotto_numeri';

    /** La casella che fa vedere i riquadri tecnici (AI, foto, import). */
    public const CASELLA_TECNICI = 'dashboard_tecnici';

    /** Quanti articoli per elenco nel lavoro in corso. */
    public const QUANTI = 5;

    /** A cosa serve ogni voce del menù, in una riga. */
    public const RIGHE = [
        LeadResource::class => 'Le richieste dei visitatori alle agenzie.',
        AgencyResource::class => 'Le schede delle agenzie.',
        PropertyResource::class => 'Gli annunci degli immobili.',
        SchedaImpostazioni::class => 'Frasi, interruttori e indicatori della scheda.',
        ImportRunResource::class => 'Il registro degli import dei gestionali.',
        PostResource::class => 'Gli articoli del blog.',
        BlogImporta::class => 'Tanti articoli insieme da un file Excel.',
        SeoImpostazioni::class => 'Titoli, descrizioni e indicizzazione del sito.',
        SeoMetaResource::class => 'Titolo e description delle pagine fisse.',
        SeoPageResource::class => 'Le pagine delle città e delle zone.',
        CodiceSitoResource::class => 'Statistiche, pixel e chat in ogni pagina.',
        PianoResource::class => 'I piani commerciali e le loro funzionalità.',
        FunzionalitaResource::class => 'Le funzionalità vendibili.',
        AbbonamentoResource::class => 'Gli abbonamenti delle agenzie.',
        \App\Filament\Pages\Pagamenti::class => 'Lo stato dei pagamenti delle agenzie, i totali e l\'esportazione per le fatture.',
        \App\Filament\Resources\OrdineResource::class => 'Gli ordini delle agenzie, i bonifici da confermare e le fatture.',
        \App\Filament\Pages\PagamentiImpostazioni::class => 'Vendita, IVA, condizioni, bonifico, pagamento online (Stripe), email dei pagamenti, chi conferma, chi vede e chi riceve gli avvisi.',
        InvitoResource::class => 'Gli inviti alle agenzie.',
        InvitiImpostazioni::class => 'Spedizione degli inviti, gruppi e giri.',
        TipoOperatoreResource::class => 'Agenzia immobiliare, costruttore e gli altri tipi.',
        \App\Filament\Pages\ValutazioneImpostazioni::class => 'Il prezzo della Valutazione, il titolare, i metadati e la guardia del report.',
        \App\Filament\Pages\ValutazioneTesti::class => 'I testi del report della Valutazione e le loro versioni.',
        \App\Filament\Pages\ValutazionePagine::class => 'Le pagine e i riquadri del report della Valutazione.',
        \App\Filament\Pages\ValutazioneMappe::class => 'Le mappe, la vista dall\'alto e il territorio del report della Valutazione.',
        \App\Filament\Pages\ValutazioneAnteprima::class => 'Il report della Valutazione coi dati di esempio.',
        \App\Filament\Pages\ValutazioneMagazzino::class => 'Da dove il sito legge la fibra e le mappe, e com\'è il servizio su reesty.',
        AiChiamataResource::class => 'Le chiamate all\'AI, con consumi ed esiti.',
        RicercaFraseResource::class => 'Le frasi scritte nella ricerca del sito.',
        AiImpostazioni::class => 'Quanto può scrivere l\'AI in ogni risposta.',
        PrivacyImpostazioni::class => 'Informativa, termini, cookie e avvertenze AI.',
        ConsensoFlagResource::class => 'Le caselle dei consensi dei moduli.',
        TestoLegaleResource::class => 'Le pagine legali del sito.',
        ConsensoResource::class => 'Chi ha accettato che cosa e quando.',
        PostaRegistroResource::class => 'Le email partite e quelle fermate.',
        PostaAnteprime::class => 'Ogni email come la vedrà chi la riceve.',
        PostaTesti::class => 'Le parole delle email.',
        SettingResource::class => 'Le impostazioni del sito, una per una.',
        UserResource::class => 'Gli account dell\'Admin e del sito.',
        RuoloResource::class => 'I ruoli della squadra e le loro caselle.',
        AssistenzaIngressoResource::class => 'Gli ingressi dell\'assistenza nei pannelli delle agenzie.',
    ];

    /** Vede «Il portale oggi»? (l'amministratore sempre) */
    public static function vedeINumeri(mixed $utente): bool
    {
        return Permessi::puo($utente, self::CASELLA_NUMERI);
    }

    /** Vede i riquadri tecnici di AI, foto e import? (l'amministratore sempre) */
    public static function vedeITecnici(mixed $utente): bool
    {
        return Permessi::puo($utente, self::CASELLA_TECNICI);
    }

    /** Vede le scorciatoie? Ogni Staff, coi numeri o senza (D-GL-277). */
    public static function vedeLeScorciatoie(mixed $utente): bool
    {
        return $utente instanceof User && $utente->isStaff();
    }

    /** Vede il lavoro in corso? Chi vede le scorciatoie e ha il Blog. */
    public static function vedeIlLavoroInCorso(mixed $utente): bool
    {
        return self::vedeLeScorciatoie($utente) && Permessi::puo($utente, 'blog');
    }

    /**
     * Le voci che chi guarda può aprire, nei gruppi del menù e nel loro
     * ordine; un gruppo senza voci non c'è.
     *
     * @return array<string, list<array{etichetta: string, icona: mixed, url: string, riga: string}>>
     */
    public static function gruppi(): array
    {
        $gruppi = [];
        foreach (AdminPanelProvider::GRUPPI as $gruppo => $classi) {
            $voci = [];
            foreach ($classi as $classe) {
                if (! $classe::shouldRegisterNavigation() || ! $classe::canAccess()) {
                    continue;
                }
                $voci[] = [
                    'etichetta' => AdminPanelProvider::ETICHETTE[$classe] ?? $classe::getNavigationLabel(),
                    'icona' => $classe::getNavigationIcon(),
                    'url' => $classe::getUrl(),
                    'riga' => self::RIGHE[$classe] ?? '',
                ];
            }
            if ($voci !== []) {
                $gruppi[$gruppo] = $voci;
            }
        }

        return $gruppi;
    }

    /**
     * Le azioni rapide che chi guarda può fare.
     *
     * @return list<array{etichetta: string, icona: string, url: string}>
     */
    public static function azioni(): array
    {
        $azioni = [];
        if (PostResource::canCreate()) {
            $azioni[] = ['etichetta' => 'Nuovo articolo', 'icona' => 'heroicon-o-pencil-square', 'url' => PostResource::getUrl('create')];
        }
        if (BlogImporta::canAccess()) {
            $azioni[] = ['etichetta' => 'Importa articoli da Excel', 'icona' => 'heroicon-o-arrow-up-tray', 'url' => BlogImporta::getUrl()];
        }
        if (SeoPageResource::canCreate()) {
            $azioni[] = ['etichetta' => 'Nuova pagina di zona', 'icona' => 'heroicon-o-map-pin', 'url' => SeoPageResource::getUrl('create')];
        }

        return $azioni;
    }

    /**
     * Il lavoro in corso del Blog: le ultime bozze toccate e i prossimi
     * articoli programmati (pubblicati con una data futura).
     *
     * @return array{bozze: list<array{titolo: string, quando: string, url: string}>, programmati: list<array{titolo: string, quando: string, url: string}>}
     */
    public static function lavoroInCorso(): array
    {
        $riga = fn (Post $post, ?\DateTimeInterface $quando): array => [
            // la bozza di lavoro di un articolo pubblicato ha lo stesso titolo: lo si dice (D-GL-248)
            'titolo' => (trim((string) $post->title) !== '' ? (string) $post->title : 'Senza titolo').($post->eModificaInBozza() ? ' (modifica in bozza)' : ''),
            'quando' => OraItaliana::formato($quando) ?? '—',
            'url' => PostResource::getUrl('edit', ['record' => $post]),
        ];

        $bozze = Post::query()->where('status', 'draft')->latest('updated_at')->latest('id')->limit(self::QUANTI)->get()
            ->map(fn (Post $post): array => $riga($post, $post->updated_at))->values()->all();
        $programmati = Post::query()->where('status', 'published')->where('published_at', '>', now())->orderBy('published_at')->orderBy('id')->limit(self::QUANTI)->get()
            ->map(fn (Post $post): array => $riga($post, $post->published_at))->values()->all();

        return ['bozze' => $bozze, 'programmati' => $programmati];
    }
}