<?php

namespace App\Filament\Pages;

use App\Support\Permessi;
use App\Support\Valutazione\Anteprima;
use App\Support\Valutazione\Guardia;
use App\Support\Valutazione\Mappe\ImpostazioniMappe;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * «VALUTAZIONE · ANTEPRIMA DEL REPORT» (D-VAL-28, D-VAL-41): il PDF vero,
 * fatto dal server coi dati di esempio inventati da Cerca.Re e coi testi e
 * le impostazioni di adesso, con «ANTEPRIMA — non valido» su ogni pagina.
 *
 *  - «Genera l'anteprima», poi «Scarica il PDF»; sotto, tempo, memoria e
 *    peso dell'ultima anteprima (la misura del web di D-VAL-36) o i motivi
 *    per cui la guardia l'ha fermata.
 *  - Solo l'amministratore carica un altro JSON d'esempio e torna a quello
 *    di fabbrica.
 *  - Scarica anche i PDF di prova della diagnosi (valutazione:diagnosi):
 *    il 2 e, dal lotto 4, il 3 con tutte le mappe.
 *
 * Non chiama il servizio delle stime, non paga, non crea valutazioni, non
 * manda email; per le mappe chiede al nostro magazzino geografico (lotto 4).
 * La vede chi può cambiare testi, pagine, mappe o impostazioni della
 * Valutazione.
 */
class ValutazioneAnteprima extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationLabel = 'Valutazione · Anteprima del report';

    protected static ?string $title = 'Valutazione · Anteprima del report';

    protected static ?int $navigationSort = 94;

    protected string $view = 'filament.pages.valutazione-anteprima';

    public static function canAccess(): bool
    {
        $utente = auth()->user();

        return Permessi::puo($utente, 'valutazione_testi') || Permessi::puo($utente, 'valutazione_pagine') || Permessi::puo($utente, 'valutazione_impostazioni') || Permessi::puo($utente, ImpostazioniMappe::PERMESSO);
    }

    public function getSubheading(): ?string
    {
        return 'Il PDF vero, coi dati di esempio e i testi di adesso, con «ANTEPRIMA — non valido» su ogni pagina. Le mappe vengono dal nostro magazzino geografico; non chiama il servizio delle stime, non paga, non manda email.';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    protected function getHeaderActions(): array
    {
        $amministratore = fn (): bool => auth()->user()?->isAdmin() === true;

        return [
            Action::make('genera')
                ->label('Genera l\'anteprima')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);
                    $esito = Anteprima::genera(auth()->user());
                    if (($esito['esito'] ?? '') === 'ok') {
                        Notification::make()->success()->title('Anteprima pronta')->body($esito['pagine'].' pagine, '.self::numero((float) $esito['secondi'], 2).' s. Premi «Scarica il PDF».')->send();

                        return;
                    }
                    Notification::make()->danger()->persistent()->title('Anteprima fermata')->body(implode(' · ', (array) ($esito['motivi'] ?? [])))->send();
                }),
            Action::make('scarica')
                ->label('Scarica il PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => Anteprima::esiste())
                ->action(fn () => $this->scarica(Anteprima::PDF, 'anteprima-report-valutazione.pdf')),
            Action::make('diagnosi')
                ->label('Scarica il PDF della diagnosi')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('gray')
                ->visible(fn (): bool => Anteprima::esiste(Anteprima::DIAGNOSI))
                ->action(fn () => $this->scarica(Anteprima::DIAGNOSI, 'diagnosi-report-valutazione.pdf')),
            Action::make('diagnosi_mappe')
                ->label('Scarica il PDF di prova delle mappe')
                ->icon('heroicon-o-map')
                ->color('gray')
                ->visible(fn (): bool => Anteprima::esiste(Anteprima::DIAGNOSI_MAPPE))
                ->action(fn () => $this->scarica(Anteprima::DIAGNOSI_MAPPE, 'diagnosi-mappe-report-valutazione.pdf')),
            Action::make('carica')
                ->label('Carica un altro JSON d\'esempio')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible($amministratore)
                ->modalHeading('Carica un altro JSON d\'esempio')
                ->modalDescription('Incolla il JSON nella forma dei dati della stima (lo stesso tracciato dell\'esempio). Prima di salvarlo lo pulisco: se non basta per un report non lo salvo. Serve a provare liste corte o vuote e dati mancanti. Resta nella cartella privata finché non torni a quello di fabbrica.')
                ->schema([
                    Textarea::make('json')->label('JSON')->required()->rows(14),
                ])
                ->modalSubmitActionLabel('Controlla e carica')
                ->action(function (array $data): void {
                    try {
                        $avvisi = Anteprima::carica(auth()->user(), (string) ($data['json'] ?? ''));
                    } catch (DomainException $e) {
                        Notification::make()->danger()->persistent()->title('JSON non caricato')->body($e->getMessage())->send();

                        return;
                    }
                    Notification::make()->success()->title('JSON d\'esempio caricato')->body($avvisi === [] ? 'Pulito senza avvisi. Ora genera l\'anteprima.' : 'Pulito con '.count($avvisi).' avvisi: '.implode(' ', $avvisi))->send();
                }),
            Action::make('fabbrica')
                ->label('Torna all\'esempio di fabbrica')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (): bool => $amministratore() && Anteprima::caricato())
                ->requiresConfirmation()
                ->modalHeading('Tornare all\'esempio di fabbrica?')
                ->modalDescription('Il JSON caricato si cancella; l\'anteprima userà di nuovo i dati di esempio di fabbrica (Via Ugo Foscolo 11).')
                ->action(function (): void {
                    try {
                        Anteprima::tornaAllaFabbrica(auth()->user());
                    } catch (DomainException $e) {
                        Notification::make()->danger()->title('Non fatto')->body($e->getMessage())->send();

                        return;
                    }
                    Notification::make()->success()->title('Di nuovo l\'esempio di fabbrica')->send();
                }),
        ];
    }

    /** Lo scaricamento di un PDF della cartella privata (protetto: solo chi vede la pagina). */
    protected function scarica(string $file, string $nome): mixed
    {
        abort_unless(static::canAccess(), 403);
        if (! Anteprima::esiste($file)) {
            Notification::make()->warning()->title('Il PDF non c\'è')->body(Guardia::pronta() ?? 'Genera di nuovo l\'anteprima.')->send();

            return null;
        }
        $percorso = Anteprima::percorsoVero($file);

        return response()->streamDownload(function () use ($percorso): void {
            readfile($percorso);
        }, $nome, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Quello che la pagina mostra sotto i pulsanti.
     *
     * @return array<string, mixed>
     */
    public function quadro(): array
    {
        return [
            'esempio' => Anteprima::caricato() ? 'Quello caricato dall\'amministratore.' : 'Quello di fabbrica: Via Ugo Foscolo 11, Torino, valore 389.000 € (dati inventati da Cerca.Re).',
            'guardia' => Guardia::statoSegreto(),
            'ultima' => Anteprima::ultima(),
            'gd' => extension_loaded('gd'),
            'memoria' => (string) ini_get('memory_limit'),
            'secondi' => (string) ini_get('max_execution_time'),
            'giorni_mappe' => (int) ImpostazioniMappe::tutte()['anteprima']['giorni'],
        ];
    }

    public static function numero(float $x, int $decimali = 1): string
    {
        return number_format($x, $decimali, ',', '.');
    }
}