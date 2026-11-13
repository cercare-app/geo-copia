<?php

namespace App\Filament\Pages;

use App\Support\Permessi;
use App\Support\Valutazione\Mappe\ImpostazioniMappe;
use App\Support\Valutazione\Testi;
use App\Support\Valutazione\Versioni;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * «VALUTAZIONE · TESTI DEL REPORT» (D-VAL-43): ogni testo delle pagine
 * (copertina, «In breve», le pagine del lotto 2, «Avvertenze e diritti»,
 * testata e piè) e le avvertenze (aggiungi, togli, riordina). Dal lotto 2
 * gli interruttori di pagine e riquadri stanno in «Valutazione · Pagine e
 * riquadri» (D-VAL-59), e in fondo c'è l'elenco delle versioni (D-VAL-60):
 * «Guarda» mostra che cosa era diverso, «Torna a questa versione» ne crea
 * una nuova uguale (serve anche la casella «Pagine e riquadri»).
 *
 * La vede l'amministratore e chi ha la casella «Valutazione · Testi». Ogni
 * campo mostra il testo di oggi; vuoto torna alla fabbrica. I segnaposti
 * fra graffe ({anno}, {codice}…) diventano i dati del report; uno
 * sconosciuto ferma il salvataggio. Una parola vietata non lo ferma, ma lo
 * dice: finché c'è, il report non si genera.
 */
class ValutazioneTesti extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Valutazione · Testi del report';

    protected static ?string $title = 'Valutazione · Testi del report';

    protected static ?int $navigationSort = 92;

    protected string $view = 'filament.pages.valutazione-testi';

    /** I campi dei testi hanno un prefisso. */
    public const TESTO = 'testo_';

    public ?array $data = [];

    /** La versione che si sta guardando (null = nessuna). */
    public ?int $guardata = null;

    public static function canAccess(): bool
    {
        return Permessi::puo(auth()->user(), 'valutazione_testi');
    }

    public function getSubheading(): ?string
    {
        return 'I testi del report. Provvisori finché il consulente non li legge. Hanno effetto appena salvi: per vederli, «Genera l\'anteprima» in «Valutazione · Anteprima del report». Pagine e riquadri da mostrare: «Valutazione · Pagine e riquadri». Le versioni sono in fondo.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fabbrica')
                ->label('Torna ai testi di fabbrica')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Tornare ai testi di fabbrica?')
                ->modalDescription('Riempie il modulo con i testi e le avvertenze di fabbrica. Non salva nulla: se ti va bene, premi «Salva i testi del report» in fondo.')
                ->modalSubmitActionLabel('Sì, riempi il modulo')
                ->action(function (): void {
                    $this->form->fill(self::valori(true));
                    Notification::make()->info()->title('Modulo riempito coi testi di fabbrica')->body('Non è ancora salvato: premi «Salva i testi del report».')->send();
                }),
        ];
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(self::valori());
    }

    public function form(Schema $schema): Schema
    {
        $sezioni = [];
        foreach (Testi::SEZIONI as $sezione => $titolo) {
            $campi = [];
            foreach (Testi::TESTI as $nome => [$sezioneTesto, $etichetta, $fabbrica, , , $tipo]) {
                if ($sezioneTesto !== $sezione) {
                    continue;
                }
                $aiuto = 'Segnaposti: {'.implode('}, {', Testi::ammessi($nome)).'}.'
                    .($tipo === 'casella' ? ' Testo breve: la casella ha un\'altezza fissa (al massimo '.Testi::MASSIMO['casella'].' caratteri).' : '')
                    .($tipo === 'nota' ? ' Una o due frasi (al massimo '.Testi::MASSIMO['nota'].' caratteri): la pagina ha poco spazio.' : '')
                    .' Vuoto torna alla fabbrica.';
                $campi[] = $tipo === 'riga' || $tipo === 'casella' || $tipo === 'nota'
                    ? TextInput::make(self::TESTO.$nome)->label($etichetta)->placeholder($fabbrica)->helperText($aiuto)->maxLength(Testi::MASSIMO[$tipo])->autocomplete('off')->columnSpanFull()
                    : Textarea::make(self::TESTO.$nome)->label($etichetta)->placeholder($fabbrica)->helperText($aiuto)->rows($tipo === 'paragrafi' ? 9 : ($tipo === 'righe' ? 5 : 3))->columnSpanFull();
            }
            if ($sezione === 'avvertenze') {
                $campi[] = Repeater::make('avvertenze')
                    ->label('Le avvertenze, in ordine')
                    ->schema([
                        Textarea::make('testo')->label('Avvertenza')->required()->rows(3)->maxLength(Testi::MASSIMO['testo']),
                    ])
                    ->addActionLabel('Aggiungi un\'avvertenza')
                    ->reorderable()
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => isset($state['testo']) ? mb_strimwidth((string) $state['testo'], 0, 90, '…') : null)
                    ->minItems(1)
                    ->maxItems(Testi::AVVERTENZE_MASSIMO)
                    ->defaultItems(0)
                    ->helperText('Almeno una. Segnaposti: {'.implode('}, {', Testi::COMUNI).'}.')
                    ->columnSpanFull();
            }
            if ($campi !== []) {
                $sezioni[] = Section::make($titolo)->columns(2)->collapsible()->collapsed()->schema($campi);
            }
        }

        return $schema->components($sezioni)->statePath('data');
    }

    public function salva(): void
    {
        abort_unless(static::canAccess(), 403);
        $stato = $this->form->getState();
        $perTesti = ['avvertenze' => $stato['avvertenze'] ?? []];
        foreach (array_keys(Testi::TESTI) as $nome) {
            $perTesti[$nome] = (string) ($stato[self::TESTO.$nome] ?? '');
        }

        try {
            [$cambiate, $avvisi] = Testi::scrivi(auth()->user(), $perTesti);
        } catch (DomainException $e) {
            Notification::make()->danger()->persistent()->title('Testi del report NON salvati')->body($e->getMessage())->send();

            return;
        }
        $this->form->fill(self::valori());

        Notification::make()
            ->success()
            ->title($cambiate === [] ? 'Nessuna modifica' : 'Testi del report salvati')
            ->body($cambiate === [] ? 'I testi sono quelli di prima.' : 'Cambiati: '.implode(', ', $cambiate).'.')
            ->send();
        if ($avvisi !== []) {
            Notification::make()->warning()->persistent()->title('Attenzione: il report non si genererà finché c\'è questo')->body(implode(' · ', $avvisi))->send();
        }
    }

    /**
     * Le ultime versioni (D-VAL-60), per la vista.
     *
     * @return list<array{numero: int, quando: string, chi: string, dove: string, cambiate: list<string>}>
     */
    public function versioni(): array
    {
        return Versioni::elenco();
    }

    /** Si può tornare a una versione? Servono le due caselle. */
    public function puoTornare(): bool
    {
        $chi = auth()->user();

        return Permessi::puo($chi, 'valutazione_testi') && Permessi::puo($chi, 'valutazione_pagine');
    }

    public function guarda(int $numero): void
    {
        abort_unless(static::canAccess(), 403);
        $this->guardata = $this->guardata === $numero ? null : $numero;
    }

    /**
     * Che cosa era diverso da oggi nella versione guardata: [voce, allora, oggi].
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public function differenze(): array
    {
        if ($this->guardata === null) {
            return [];
        }
        $allora = Versioni::contenuto($this->guardata);
        if ($allora === null) {
            return [];
        }
        $oggi = Versioni::istantanea();
        $fuori = [];
        foreach (Testi::TESTI as $nome => [$sezione, $etichetta, $fabbrica]) {
            $prima = (string) ($allora['testi'][$nome] ?? $fabbrica);
            if ($prima !== $oggi['testi'][$nome]) {
                $fuori[] = [$etichetta.' ('.Testi::SEZIONI[$sezione].')', $prima, $oggi['testi'][$nome]];
            }
        }
        $avvertenze = is_array($allora['avvertenze'] ?? null) ? $allora['avvertenze'] : Testi::AVVERTENZE_FABBRICA;
        if ($avvertenze !== $oggi['avvertenze']) {
            $fuori[] = ['Avvertenze', implode("\n", $avvertenze), implode("\n", $oggi['avvertenze'])];
        }
        foreach (Testi::PAGINE as $nome => [$etichetta, $diFabbrica]) {
            $prima = (bool) ($allora['pagine'][$nome] ?? $diFabbrica);
            if ($prima !== $oggi['pagine'][$nome]) {
                $fuori[] = [$etichetta, $prima ? 'accesa' : 'spenta', $oggi['pagine'][$nome] ? 'accesa' : 'spenta'];
            }
        }
        foreach (Testi::RIQUADRI as $nome => [$sezione, $etichetta, $diFabbrica]) {
            $prima = (bool) ($allora['riquadri'][$nome] ?? $diFabbrica);
            if ($prima !== $oggi['riquadri'][$nome]) {
                $fuori[] = ['Riquadro «'.$etichetta.'» ('.Testi::SEZIONI[$sezione].')', $prima ? 'acceso' : 'spento', $oggi['riquadri'][$nome] ? 'acceso' : 'spento'];
            }
        }
        // le mappe (lotto 4): solo nelle versioni che le hanno
        if (is_array($allora['mappe'] ?? null)) {
            $vociAllora = ImpostazioniMappe::voci(ImpostazioniMappe::normalizza($allora['mappe']));
            $vociOggi = ImpostazioniMappe::voci($oggi['mappe']);
            foreach ($vociAllora as $voce => $valore) {
                if ($valore !== ($vociOggi[$voce] ?? '')) {
                    $fuori[] = ['Mappe e territorio · '.$voce, $valore, $vociOggi[$voce] ?? ''];
                }
            }
        }
        $d = is_array($allora['disposizione'] ?? null) ? $allora['disposizione'] : [];
        foreach (['annunci' => 'Quanti annunci', 'vendite' => 'Quante vendite', 'descrizione' => 'Lunghezza delle descrizioni', 'righe_annunci' => 'Righe degli annunci', 'righe_vendite' => 'Righe delle vendite'] as $chiave => $etichetta) {
            $prima = $d[$chiave] ?? null;
            $adesso = $oggi['disposizione'][$chiave];
            if ($prima !== null && $prima !== $adesso) {
                $scrivi = fn (mixed $v): string => is_array($v) ? implode(', ', $v) : (string) $v;
                $fuori[] = [$etichetta, $scrivi($prima), $scrivi($adesso)];
            }
        }

        return $fuori;
    }

    public function ripristina(int $numero): void
    {
        abort_unless(static::canAccess(), 403);
        try {
            [$cambiate, $avvisi] = Versioni::ripristina(auth()->user(), $numero);
        } catch (DomainException $e) {
            Notification::make()->danger()->persistent()->title('Versione NON ripristinata')->body($e->getMessage())->send();

            return;
        }
        $this->guardata = null;
        $this->form->fill(self::valori());
        Notification::make()
            ->success()
            ->title($cambiate === [] ? 'Era già così' : 'Tornati alla versione n. '.$numero)
            ->body($cambiate === [] ? 'Testi, pagine, riquadri e mappe sono già quelli di quella versione.' : 'Cambiati: '.implode(', ', $cambiate).'. È nata una versione nuova, uguale a quella.')
            ->send();
        if ($avvisi !== []) {
            Notification::make()->warning()->persistent()->title('Attenzione: il report non si genererà finché c\'è questo')->body(implode(' · ', $avvisi))->send();
        }
    }

    /**
     * I valori per il modulo: quelli di oggi, o quelli di fabbrica.
     *
     * @return array<string, mixed>
     */
    public static function valori(bool $fabbrica = false): array
    {
        $oggi = Testi::tutti();
        $valori = [];
        foreach (Testi::TESTI as $nome => $definizione) {
            $valori[self::TESTO.$nome] = $fabbrica ? $definizione[2] : $oggi['testi'][$nome];
        }
        $valori['avvertenze'] = array_map(fn (string $a): array => ['testo' => $a], $fabbrica ? Testi::AVVERTENZE_FABBRICA : $oggi['avvertenze']);

        return $valori;
    }
}