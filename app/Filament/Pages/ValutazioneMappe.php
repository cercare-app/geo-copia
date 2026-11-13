<?php

namespace App\Filament\Pages;

use App\Support\Permessi;
use App\Support\Valutazione\Anteprima;
use App\Support\Valutazione\Mappe\ImpostazioniMappe;
use App\Support\Valutazione\Mappe\Prova;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * «VALUTAZIONE · MAPPE E TERRITORIO» (lotto «Valutazione 4», D-VAL-101,
 * D-VAL-99, D-VAL-102).
 *
 *  - Per ogni mappa del report: raggio, nomi delle vie e quanti al massimo,
 *    cerchi delle distanze, scala, fin dove si può allargare.
 *  - La vista dall'alto, i colori (con «Torna ai colori di fabbrica»), i
 *    controlli della casa sulla mappa con le abbreviazioni delle vie, le
 *    caselle del territorio di «Il posto», i giorni per cui l'anteprima
 *    ricorda le mappe.
 *  - «Prova un indirizzo»: coordinate e via, e risponde con l'edificio
 *    colorato, la via trovata, il verdetto e la vista dall'alto.
 *
 * Ogni campo vuoto vale la fabbrica (il fac-simile). Ogni salvataggio è
 * una versione, come i testi. Accendere e spegnere le mappe resta in
 * «Pagine e riquadri». Le medie italiane e la fascia «vicino alla media»
 * non sono qui: restano quelle della scheda degli annunci (D-VAL-101
 * rivista). La vede l'amministratore e chi ha la casella «Valutazione ·
 * Mappe e territorio». Non manda email e non paga nulla.
 */
class ValutazioneMappe extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static ?string $navigationLabel = 'Valutazione · Mappe e territorio';

    protected static ?string $title = 'Valutazione · Mappe e territorio';

    protected static ?int $navigationSort = 96;

    protected string $view = 'filament.pages.valutazione-mappe';

    public ?array $data = [];

    /** L'esito dell'ultimo «Prova un indirizzo» (Prova::indirizzo, senza la vista, che sta in un file), o null. */
    public ?array $prova = null;

    public static function canAccess(): bool
    {
        return Permessi::puo(auth()->user(), ImpostazioniMappe::PERMESSO);
    }

    public function getSubheading(): ?string
    {
        return 'Come sono fatte le mappe del report e la vista dall\'alto, i colori, il controllo della casa sulla mappa e il territorio. Ogni campo vuoto vale la fabbrica; accendere e spegnere le mappe resta in «Pagine e riquadri». Hanno effetto appena salvi: per vederle, «Genera l\'anteprima» in «Valutazione · Anteprima del report».';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(ImpostazioniMappe::perIlModulo());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prova')
                ->label('Prova un indirizzo')
                ->icon('heroicon-o-map-pin')
                ->modalHeading('Prova un indirizzo')
                ->modalDescription('Come lo vedrebbe il report con le scelte salvate qui: che cosa si colora, se la via dell\'indirizzo c\'è nei dati e a che distanza, il verdetto del controllo e la vista dall\'alto. Latitudine e longitudine col punto, per esempio 45.04795 e 7.67511. Chiede i dati al magazzino geografico: qualche secondo.')
                ->schema([
                    TextInput::make('lat')->label('Latitudine')->required()->placeholder('45.04795')->default(fn () => $this->prova['lat'] ?? null),
                    TextInput::make('lon')->label('Longitudine')->required()->placeholder('7.67511')->default(fn () => $this->prova['lon'] ?? null),
                    TextInput::make('via')->label('Via dell\'indirizzo, senza numero')->required()->maxLength(120)->placeholder('Via Ugo Foscolo')->default(fn () => $this->prova['via'] ?? null),
                ])
                ->modalSubmitActionLabel('Prova')
                ->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);
                    $lat = filter_var(str_replace(',', '.', trim((string) ($data['lat'] ?? ''))), FILTER_VALIDATE_FLOAT);
                    $lon = filter_var(str_replace(',', '.', trim((string) ($data['lon'] ?? ''))), FILTER_VALIDATE_FLOAT);
                    $via = trim((string) ($data['via'] ?? ''));
                    if ($lat === false || $lon === false || abs((float) $lat) > 85 || abs((float) $lon) > 180 || $via === '') {
                        Notification::make()->warning()->title('Valori non validi')->body('Scrivi latitudine e longitudine col punto (per esempio 45.04795 e 7.67511) e il nome della via.')->send();

                        return;
                    }
                    $prova = Prova::indirizzo((float) $lat, (float) $lon, $via);
                    // la vista sta in un file della cartella privata, non nella pagina: può pesare centinaia di KB
                    $file = Anteprima::percorso('prova-indirizzo-'.(int) auth()->id().'.svg');
                    if (is_string($prova['vista'])) {
                        Anteprima::disco()->put($file, $prova['vista']);
                    } else {
                        Anteprima::disco()->delete($file);
                    }
                    unset($prova['vista']);
                    $this->prova = $prova;
                    Notification::make()->info()->title('Prova fatta in '.number_format((float) $this->prova['millisecondi'], 0, ',', '.').' ms')->body('L\'esito è qui sotto, in «Prova un indirizzo».')->send();
                }),
            Action::make('colori')
                ->label('Torna ai colori di fabbrica')
                ->icon('heroicon-o-swatch')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Tornare ai colori di fabbrica?')
                ->modalDescription('Riempie i colori come il fac-simile. Non salva nulla: se ti va bene, premi «Salva mappe e territorio» in fondo.')
                ->modalSubmitActionLabel('Sì, riempi i colori')
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);
                    $stato = is_array($this->data) ? $this->data : [];
                    foreach (array_keys(ImpostazioniMappe::COLORI) as $campo) {
                        $stato[ImpostazioniMappe::PREFISSO.'colori_'.$campo] = '';
                    }
                    $this->form->fill($stato);
                    Notification::make()->info()->title('Colori di fabbrica nel modulo')->body('Non è ancora salvato: premi «Salva mappe e territorio».')->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $intero = function (string $chiave, string $etichetta, int|float $fabbrica, int|float $minimo, int|float $massimo, string $unita, string $aiuto = ''): TextInput {
            // nel campo come si scrive (3,2 · 3000), nell'aiuto come si legge (3,2 · 3.000)
            $scritto = fn (int|float $n): string => str_replace('.', ',', (string) $n);
            $letto = fn (int|float $n): string => floor((float) $n) === (float) $n ? number_format((float) $n, 0, ',', '.') : $scritto($n);

            return TextInput::make($chiave)
                ->label($etichetta.($unita !== '' ? ' ('.$unita.')' : ''))
                ->placeholder($scritto($fabbrica))
                ->helperText(trim($aiuto.' Da '.$letto($minimo).' a '.$letto($massimo).'. Di fabbrica: '.$letto($fabbrica).'. Vuoto torna alla fabbrica.'))
                ->maxLength(10)
                ->autocomplete('off');
        };

        $aiutiMappe = [
            'raggio' => 'Dal centro al bordo, in larghezza.',
            'raggio_max' => 'La mappa si allarga da sola per contenere tutte le case (la zona: i suoi confini), fino a qui.',
            'nomi_max' => 'Prima le vie più vicine alla casa.',
        ];
        $aiutiSiNo = [
            'allarga' => 'Spento, le case oltre il raggio restano fuori e la riga sotto la mappa lo dice.',
            'nomi' => 'Spento, nessun nome di via sulla mappa.',
            'anelli' => 'I cerchi tratteggiati delle distanze intorno alla casa.',
            'scala' => 'La barra dei metri in basso a sinistra.',
        ];
        $sezioni = [];
        foreach (ImpostazioniMappe::MAPPE as $mappa => $nome) {
            $campi = [];
            foreach (ImpostazioniMappe::CAMPI_MAPPA[$mappa] as $campo) {
                $chiave = ImpostazioniMappe::chiaveMappa($mappa, $campo);
                $fabbrica = ImpostazioniMappe::FABBRICA_MAPPE[$mappa][$campo];
                if (is_bool($fabbrica)) {
                    $campi[] = Toggle::make($chiave)->label(ImpostazioniMappe::SI_NO_MAPPA[$campo])->helperText($aiutiSiNo[$campo].' Di fabbrica: '.($fabbrica ? 'acceso' : 'spento').'.');

                    continue;
                }
                [$etichetta, $minimo, $massimo, $unita] = ImpostazioniMappe::NUMERI_MAPPA[$campo];
                $campi[] = $intero($chiave, $etichetta, $fabbrica, $minimo, $massimo, $unita, $aiutiMappe[$campo]);
            }
            $sezioni[] = Section::make('Mappa · '.$nome)->columns(2)->collapsible()->collapsed()->schema($campi);
        }

        $numeri = function (array $definizioni, string $gruppo, array $aiuti = []) use ($intero): array {
            $campi = [];
            foreach ($definizioni as $campo => [$etichetta, $fabbrica, $minimo, $massimo, $unita]) {
                $campi[] = $intero(ImpostazioniMappe::PREFISSO.$gruppo.'_'.$campo, $etichetta, $fabbrica, $minimo, $massimo, $unita, $aiuti[$campo] ?? '');
            }

            return $campi;
        };

        $sezioni[] = Section::make('La vista dall\'alto')
            ->description('La pagina «Il posto»: il quartiere visto dall\'alto, con gli edifici in rilievo e la casa colorata.')
            ->columns(2)->collapsible()->collapsed()
            ->schema($numeri(ImpostazioniMappe::VISTA, 'vista', [
                'larghezza' => 'Quanti metri di quartiere entrano nella vista, da sinistra a destra.',
                'inclinazione' => 'Più alta, la vista è più dall\'alto e gli edifici si vedono meno in rilievo.',
                'metri_piano' => 'Per gli edifici che nei dati hanno i piani ma non l\'altezza.',
                'piani_riserva' => 'Per gli edifici senza piani né altezza, quando nei dintorni nessuno li dice.',
                'nomi_max' => 'La via dell\'indirizzo per prima, accanto alla casa, se c\'è nei dati; poi le più vicine. 0 = nessun nome, nemmeno quello della via dell\'indirizzo.',
            ]));

        $colori = [];
        foreach (ImpostazioniMappe::COLORI as $campo => [$etichetta, $fabbrica]) {
            $colori[] = ColorPicker::make(ImpostazioniMappe::PREFISSO.'colori_'.$campo)
                ->label($etichetta)
                ->hex()
                ->placeholder($fabbrica)
                ->regex('/^#[0-9a-fA-F]{6}$/')
                ->helperText('Di fabbrica '.$fabbrica.'. Vuoto torna alla fabbrica.');
        }
        $sezioni[] = Section::make('I colori')
            ->description('Valgono in tutte le mappe e nella vista dall\'alto. «Torna ai colori di fabbrica», in alto, li rimette come il fac-simile.')
            ->columns(3)->collapsible()->collapsed()
            ->schema($colori);

        $sezioni[] = Section::make('La casa sulla mappa')
            ->description('Che cosa si colora e il controllo della via dell\'indirizzo. «Prova un indirizzo», in alto, mostra come vanno con un caso vero. Dal lotto 6 un controllo che non torna fermerà il report prima del pagamento.')
            ->columns(3)->collapsible()->collapsed()
            ->schema([
                ...$numeri(ImpostazioniMappe::CONTROLLI, 'controlli', [
                    'via_m' => 'La distanza fra le coordinate della casa e la via del suo indirizzo, nei dati.',
                    'edificio_m' => 'Se le coordinate non cadono dentro un edificio, si colora il più vicino solo se è entro questa distanza.',
                    'isolato_m2' => 'Un edificio più grande è un isolato intero: la didascalia lo dice.',
                ]),
                Textarea::make(ImpostazioniMappe::PREFISSO.'abbreviazioni')
                    ->label('Abbreviazioni delle vie')
                    ->rows(10)
                    ->helperText('Una per riga, come «C.so = Corso»: valgono all\'inizio del nome della via. «S.» vale da sé per San, Santo, Santa e Sant\'. Al massimo '.ImpostazioniMappe::ABBREVIAZIONI_MASSIMO.' righe. Vuoto torna all\'elenco di fabbrica.')
                    ->columnSpanFull(),
            ]);

        $territorio = [];
        foreach (ImpostazioniMappe::TERRITORIO as $campo => $etichetta) {
            $territorio[] = Toggle::make(ImpostazioniMappe::PREFISSO.'territorio_'.$campo)
                ->label($etichetta)
                ->helperText($campo === 'fibra'
                    ? 'Segue anche l\'interruttore «Fibra nel report» di «Magazzino geografico».'
                    : 'Le medie italiane e la fascia «vicino alla media» sono quelle della scheda degli annunci: scheda e report dicono la stessa cosa.');
        }
        $sezioni[] = Section::make('Il territorio in «Il posto»')
            ->description('Le caselle sotto la vista dall\'alto. Una casella spenta non si stampa.')
            ->columns(2)->collapsible()->collapsed()
            ->schema($territorio);

        $sezioni[] = Section::make('L\'anteprima')
            ->columns(2)->collapsible()->collapsed()
            ->schema($numeri(ImpostazioniMappe::ANTEPRIMA, 'anteprima', [
                'giorni' => 'Le mappe dell\'esempio non si richiedono al magazzino a ogni anteprima. 0 = ogni anteprima le richiede.',
            ]));

        return $schema->components($sezioni)->statePath('data');
    }

    public function salva(): void
    {
        abort_unless(static::canAccess(), 403);
        try {
            $cambiate = ImpostazioniMappe::scrivi(auth()->user(), $this->form->getState());
        } catch (DomainException $e) {
            Notification::make()->danger()->persistent()->title('Mappe e territorio NON salvati')->body($e->getMessage())->send();

            return;
        }
        $this->form->fill(ImpostazioniMappe::perIlModulo());

        Notification::make()
            ->success()
            ->title($cambiate === [] ? 'Nessuna modifica' : 'Mappe e territorio salvati')
            ->body($cambiate === [] ? 'È tutto come prima.' : 'Cambiati: '.implode(', ', $cambiate).'. È nata una versione nuova (in «Testi del report»).')
            ->send();
    }

    /**
     * Le righe dell'esito di «Prova un indirizzo»: [cosa, come, tono].
     *
     * @return list<array{0: string, 1: string, 2: ?string}>
     */
    public function righeProva(): array
    {
        if ($this->prova === null) {
            return [];
        }
        $righe = [['Punto e via', str_replace('.', ',', (string) $this->prova['lat']).' · '.str_replace('.', ',', (string) $this->prova['lon']).' · «'.$this->prova['via'].'»', null]];
        foreach ($this->prova['avvisi'] as $avviso) {
            $righe[] = ['Avviso', (string) $avviso, 'avviso'];
        }

        return [...$righe, ...Prova::righe($this->prova['controllo'])];
    }

    /** La vista dall'alto della prova, pronta per un <img>; null se non c'è. */
    public function vistaProva(): ?string
    {
        if ($this->prova === null) {
            return null;
        }
        $file = Anteprima::percorso('prova-indirizzo-'.(int) auth()->id().'.svg');
        $svg = Anteprima::disco()->exists($file) ? (string) Anteprima::disco()->get($file) : '';

        return $svg !== '' ? 'data:image/svg+xml;base64,'.base64_encode($svg) : null;
    }
}