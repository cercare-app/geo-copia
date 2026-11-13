<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use App\Filament\Pages\AiImpostazioni;
use App\Filament\Pages\BlogImporta;
use App\Filament\Pages\InvitiImpostazioni;
use App\Filament\Pages\Pagamenti;
use App\Filament\Pages\PagamentiImpostazioni;
use App\Filament\Pages\PostaAnteprime;
use App\Filament\Pages\PostaTesti;
use App\Filament\Pages\PrivacyImpostazioni;
use App\Filament\Pages\ProssimiPassi;
use App\Filament\Pages\SchedaImpostazioni;
use App\Filament\Pages\SeoImpostazioni;
use App\Filament\Pages\ValutazioneAnteprima;
use App\Filament\Pages\ValutazioneImpostazioni;
use App\Filament\Pages\ValutazioneMagazzino;
use App\Filament\Pages\ValutazioneMappe;
use App\Filament\Pages\ValutazionePagine;
use App\Filament\Pages\ValutazioneTesti;
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
use App\Filament\Resources\OrdineResource;
use App\Filament\Resources\PianoResource;
use App\Filament\Resources\PostResource;
use App\Filament\Resources\PostaRegistroResource;
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
use App\Http\Controllers\EsportazioneController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Il pannello di amministrazione: /admin.
 *
 * Il vestito (Lotto 6d, D-GO1R-96…98) è fatto solo di opzioni di Filament
 * (logo, carattere, colore, niente modalità scura) e di un foglio CSS
 * semplice, public/css/pannelli.css, agganciato in testa alla pagina:
 * nessun tema compilato. Il foglio porta nell'indirizzo la data del file,
 * così dopo ogni consegna il browser lo ricarica da solo.
 *
 * Il menù a gruppi (Lotto 8 «Pannelli, ottica app», D-GO1R-115): le voci
 * stanno in sette gruppi — Catalogo, Contenuti, SEO (go-live, Lotto S,
 * D-GL-50: impostazioni, pagine del sito, pagine di zona, codici e
 * script), Commerciale, AI, Privacy (go-live, Lotto 2 «Consensi»,
 * D-GL-22…25: solo amministratori), Sistema — col Cruscotto fuori, in testa. Il menù si costruisce QUI, in un
 * punto solo, col costruttore di Filament (navigation + NavigationBuilder),
 * senza toccare le risorse. Le policy restano la verità: una voce entra nel
 * menù solo se chi guarda può vederla (canAccess, come fa Filament da sé) e
 * un gruppo senza voci non compare.
 *
 * Ogni pagina o risorsa nuova del pannello va aggiunta qui, nel suo gruppo:
 * fuori da GRUPPI non compare nel menù (la sua pagina si apre lo stesso, ma
 * solo scrivendo l'indirizzo). Lotto POSTA: «Posta · Anteprime» in Sistema,
 * subito sotto «Posta · Registro». Lotto «Blog dall'Excel»: «Blog · Importa
 * da Excel» in Contenuti, subito sotto il Blog. Lotto «Testi delle email»:
 * «Posta · Testi» in Sistema, subito sotto «Posta · Anteprime». Lotto «Inviti a
 * raffica»: «Inviti · Impostazioni» in Commerciale, subito sotto gli Inviti.
 * Lotto «Ruoli e permessi»: «Ruoli» in Sistema, subito sotto gli Utenti; chi
 * vede che cosa lo decidono le caselle del ruolo (App\Support\Permessi).
 * Lotto «AI dall'Admin e Dashboard per lo staff» (D-GL-276): «AI ·
 * Impostazioni» nel gruppo AI, subito sotto «AI · Frasi». Lotto «La pagina
 * Pagamenti» (D-GL-380): «Pagamenti» in Commerciale, subito sopra «Ordini».
 * Lotto «Valutazione 1» (D-VAL-43): il gruppo «Valutazione», subito dopo
 * Commerciale, con «Valutazione · Impostazioni», «Valutazione · Testi del
 * report» e «Valutazione · Anteprima del report». Lotto «Valutazione 2»
 * (D-VAL-59): «Valutazione · Pagine e riquadri», subito sotto i testi.
 * Lotto «Valutazione 4» (D-VAL-101): «Valutazione · Mappe e territorio»,
 * subito sotto «Pagine e riquadri».
 *
 * Lotto «Tabelle senza scorrimento» (D-GL-314): gli elenchi delle risorse
 * (le tabelle) e la Dashboard occupano tutta la larghezza fra il menù e il
 * bordo destro (larghezza()); le schede da compilare e le altre pagine
 * restano larghe al massimo 1.280 px, come Filament le fa da sé. Le tabelle
 * stesse si stringono col foglio public/css/pannelli.css (D-GL-316).
 */
class AdminPanelProvider extends PanelProvider
{
    /** Le voci che nel menù hanno un nome diverso da quello della risorsa (Lotto S). */
    public const ETICHETTE = [
        SeoPageResource::class => 'Pagine di zona',
    ];

    /** Le pagine a tutta larghezza (D-GL-314): la Dashboard e l'elenco di ogni risorsa. */
    public const A_TUTTA_LARGHEZZA = ['filament.admin.pages.dashboard', 'filament.admin.resources.*.index'];

    /** I gruppi del menù e le loro voci, nell'ordine in cui compaiono (D-GO1R-115). */
    public const GRUPPI = [
        'Catalogo' => [LeadResource::class, AgencyResource::class, PropertyResource::class, SchedaImpostazioni::class, ImportRunResource::class],
        'Contenuti' => [PostResource::class, BlogImporta::class],
        'SEO' => [SeoImpostazioni::class, SeoMetaResource::class, SeoPageResource::class, CodiceSitoResource::class],
        'Commerciale' => [PianoResource::class, FunzionalitaResource::class, AbbonamentoResource::class, Pagamenti::class, OrdineResource::class, PagamentiImpostazioni::class, InvitoResource::class, InvitiImpostazioni::class, TipoOperatoreResource::class],
        'Valutazione' => [ValutazioneImpostazioni::class, ValutazioneTesti::class, ValutazionePagine::class, ValutazioneMappe::class, ValutazioneAnteprima::class, ValutazioneMagazzino::class],
        'AI' => [AiChiamataResource::class, RicercaFraseResource::class, AiImpostazioni::class],
        'Privacy' => [PrivacyImpostazioni::class, ConsensoFlagResource::class, TestoLegaleResource::class, ConsensoResource::class],
        'Sistema' => [PostaRegistroResource::class, PostaAnteprime::class, PostaTesti::class, SettingResource::class, UserResource::class, RuoloResource::class, AssistenzaIngressoResource::class],
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Chi lascia una pagina con modifiche non salvate se lo sente chiedere
            // (Lotto «Moduli a due colonne · Blog», D-GL-234): vale in ogni pagina
            // «nuovo» e «modifica» dell'Admin e, quando si chiude o si ricarica la
            // scheda, con una finestra di azione aperta; le pagine delle impostazioni
            // hanno un modulo loro e Filament non ve lo mette.
            ->unsavedChangesAlerts()
            ->brandName('Cerca.Re · Amministrazione')
            ->brandLogo(asset('cercare.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('favicon.ico'))
            ->font('Plus Jakarta Sans')
            ->darkMode(false)
            ->colors([
                'primary' => Color::Blue, // il blu Cerca.Re: #3b82f6
            ])
            // Gli elenchi e la Dashboard a tutta larghezza, le schede come prima (D-GL-314).
            ->maxContentWidth(fn (): ?Width => self::larghezza())
            // Il menù a gruppi (D-GO1R-115), costruito da menu() qui sotto.
            ->navigation(fn (NavigationBuilder $menu): NavigationBuilder => self::menu($menu))
            // Le foto degli immobili sono hotlinkate dai CDN dei gestionali:
            // senza referer le servono, con un referer "estraneo" le bloccano.
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): string => '<meta name="referrer" content="no-referrer">',
            )
            // Il vestito dei pannelli (Lotto 6d): foglio CSS semplice, con la
            // data del file nell'indirizzo per non restare in cache.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="stylesheet" href="'.e(asset('css/pannelli.css')).'?v='.self::versioneVestito().'">',
            )
            // Il pulsante «HTML» dei testi (Lotto «Editor HTML 2», D-GL-260/261): uno script
            // semplice, senza compilazione, con la data del file nell'indirizzo come il foglio.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<script src="'.e(asset('js/editor-html.js')).'?v='.self::versioneFile('js/editor-html.js').'" defer></script>',
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn () => view('filament.comune.accesso'),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn () => view('filament.comune.pie'),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            // Lo scaricamento delle esportazioni (Lotto «Esportazioni», D-GL-134…138):
            // dentro il pannello (serve il login), solo con un link firmato che vale
            // pochi minuti; il controller chiede anche di poter esportare (l'amministratore,
            // o una casella di esportazione: Lotto «Ruoli e permessi», D-GL-213).
            ->authenticatedRoutes(function (): void {
                Route::get('esportazioni/{file}', EsportazioneController::class)
                    ->middleware('signed:relative')
                    ->name('esportazione');
            })
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // Lotto «Entra nel pannello dell'agenzia» (D-GL-340): subito dopo la sessione
                \App\Http\Middleware\AssistenzaInCorso::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Quanto è larga la pagina che si apre (D-GL-314): tutta la larghezza per la
     * Dashboard e gli elenchi; null = la larghezza di Filament (1.280 px al
     * massimo) per tutto il resto. Si decide dal nome della rotta, quando la
     * pagina si apre: gli aggiornamenti di Livewire non ridisegnano la cornice.
     */
    public static function larghezza(): ?Width
    {
        return request()->routeIs(...self::A_TUTTA_LARGHEZZA) ? Width::Full : null;
    }

    /**
     * Il menù (D-GO1R-115): la Dashboard in testa, subito sotto «Prossimi
     * passi» per chi ci entra (Lotto «Prossimi passi», D-GL-279), poi i
     * gruppi di GRUPPI con le loro voci. Le voci sono quelle che ogni risorsa
     * costruisce da sé (etichetta, icona, badge, indirizzo, voce attiva): qui
     * si decide solo dove stanno.
     */
    public static function menu(NavigationBuilder $menu): NavigationBuilder
    {
        $menu->items([...self::vociDi(Dashboard::class), ...self::vociDi(ProssimiPassi::class)]);

        foreach (self::GRUPPI as $gruppo => $classi) {
            $voci = [];
            foreach ($classi as $classe) {
                $voci = [...$voci, ...self::vociDi($classe)];
            }

            $menu->group($gruppo, $voci);
        }

        return $menu;
    }

    /**
     * Le voci di una pagina o di una risorsa, SOLO se vanno nel menù e se
     * chi guarda può vederla: gli stessi due controlli che Filament fa
     * quando registra le voci da solo (registerNavigationItems).
     *
     * @param  class-string  $classe
     * @return array<\Filament\Navigation\NavigationItem>
     */
    private static function vociDi(string $classe): array
    {
        if (! $classe::shouldRegisterNavigation() || ! $classe::canAccess()) {
            return [];
        }

        $voci = $classe::getNavigationItems();
        if (isset(self::ETICHETTE[$classe])) {
            foreach ($voci as $voce) {
                $voce->label(self::ETICHETTE[$classe]);
            }
        }

        return $voci;
    }

    
    /** La data dell'ultima modifica del foglio: cambia a ogni consegna, e il browser lo ricarica. */
    public static function versioneVestito(): string
    {
        return self::versioneFile('css/pannelli.css');
    }

    /** La data dell'ultima modifica di un file di public/ (0 se manca): per l'indirizzo, contro la cache. */
    public static function versioneFile(string $percorso): string
    {
        $file = public_path($percorso);

        return is_file($file) ? (string) filemtime($file) : '0';
    }
}