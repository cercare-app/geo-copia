<?php

namespace App\Filament\Pages;

use App\Support\Geo\ImpostazioniGeo;
use App\Support\Geo\LettoreFibra;
use App\Support\Geo\MagazzinoGeo;
use App\Support\Permessi;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

/**
 * «VALUTAZIONE · MAGAZZINO GEOGRAFICO» (lotto «Valutazione 3», D-VAL-75,
 * D-VAL-76, D-VAL-81, D-VAL-85, D-VAL-87).
 *
 * In alto com'è il magazzino adesso: da dove si leggono la fibra e le
 * mappe (file sul server o servizio su reesty), l'indirizzo, se la chiave
 * c'è (mai il suo valore), se reesty risponde e in quanto tempo, le
 * edizioni della fibra e della copia di OpenStreetMap per regione, con
 * l'avviso quando la copia è vecchia. Pulsanti: «Prova il collegamento
 * adesso», «Prova la fibra in un punto», «Prova la mappa di un riquadro»,
 * «Torna all'edizione di prima».
 *
 * Sotto, le scelte: modo, indirizzo, attese, memoria, riquadro massimo,
 * regioni, interruttori della fibra per scheda e report, pausa. Ogni campo
 * vuoto vale la fabbrica. La vede l'amministratore e chi ha la casella
 * «Valutazione · Magazzino geografico ⚠». Non manda email e non paga nulla.
 */
class ValutazioneMagazzino extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Valutazione · Magazzino geografico';

    protected static ?string $title = 'Valutazione · Magazzino geografico';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.pages.valutazione-magazzino';

    public ?array $data = [];

    /** Com'è il magazzino, all'apertura della pagina o all'ultima prova. */
    public array $stato = [];

    public static function canAccess(): bool
    {
        return Permessi::puo(auth()->user(), 'valutazione_magazzino');
    }

    public function getSubheading(): ?string
    {
        return 'Da dove il sito legge la fibra di AGCOM e la copia di OpenStreetMap: dai file sul suo server o dal servizio su portale.reesty.it. Le scelte hanno effetto appena salvi.';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(ImpostazioniGeo::tutte());
        $this->stato = MagazzinoGeo::stato(true);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prova')
                ->label('Prova il collegamento adesso')
                ->icon('heroicon-o-signal')
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);
                    $this->stato = MagazzinoGeo::stato(true);
                    $remoto = $this->stato['remoto'] ?? null;
                    if (($remoto['ok'] ?? false) === true) {
                        Notification::make()->success()->title('Reesty risponde')->body('In '.self::numero((float) $remoto['millisecondi']).' ms. La pausa, se c\'era, è finita.')->send();

                        return;
                    }
                    Notification::make()->danger()->persistent()->title('Reesty non risponde come dovrebbe')->body((string) ($remoto['motivo'] ?? 'motivo non registrato'))->send();
                }),
            Action::make('punto')
                ->label('Prova la fibra in un punto')
                ->icon('heroicon-o-map-pin')
                ->color('gray')
                ->modalHeading('La fibra in un punto')
                ->modalDescription('Come la cerca la scheda di un annuncio: la cella di 20 metri del punto e quelle entro '.LettoreFibra::RAGGIO.' m. Latitudine e longitudine col punto, per esempio 45.4642 e 9.19.')
                ->schema([
                    TextInput::make('lat')->label('Latitudine')->required()->placeholder('45.4642'),
                    TextInput::make('lon')->label('Longitudine')->required()->placeholder('9.19'),
                ])
                ->modalSubmitActionLabel('Cerca')
                ->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);
                    $lat = filter_var(str_replace(',', '.', trim((string) ($data['lat'] ?? ''))), FILTER_VALIDATE_FLOAT);
                    $lon = filter_var(str_replace(',', '.', trim((string) ($data['lon'] ?? ''))), FILTER_VALIDATE_FLOAT);
                    if ($lat === false || $lon === false) {
                        Notification::make()->warning()->title('Coordinate non valide')->body('Scrivi due numeri col punto, per esempio 45.4642 e 9.19.')->send();

                        return;
                    }
                    $dove = MagazzinoGeo::modoFibra() === 'locale' ? 'dal file sul server' : 'da reesty';
                    $esito = MagazzinoGeo::fibra((float) $lat, (float) $lon, LettoreFibra::RAGGIO, 'scheda');
                    if ($esito === null) {
                        Notification::make()->danger()->persistent()->title('Ricerca non riuscita ('.$dove.')')->body((string) (MagazzinoGeo::$ultimoMotivo ?? 'motivo non registrato'))->send();

                        return;
                    }
                    $cella = $esito['cella'];
                    $testo = $cella !== null
                        ? 'Nel punto: '.trim((string) ($cella['speed_down_exp_tech_text'] ?? '')).' (cella '.$esito['nome'].').'
                        : 'Nel punto il file non ha una cella (non vuol dire «niente rete»).';
                    $testo .= ' Celle intorno entro '.LettoreFibra::RAGGIO.' m: '.count($esito['vicine']).'.';
                    Notification::make()->success()->persistent()->title('Fibra letta '.$dove)->body($testo)->send();
                }),
            Action::make('riquadro')
                ->label('Prova la mappa di un riquadro')
                ->icon('heroicon-o-squares-2x2')
                ->color('gray')
                ->modalHeading('Gli oggetti della mappa di un riquadro')
                ->modalDescription('Quanti edifici, strade, aree verdi… ha la copia di OpenStreetMap intorno a un punto. Il riquadro non può superare quello massimo scelto qui sotto.')
                ->schema([
                    TextInput::make('lat')->label('Latitudine del centro')->required()->placeholder('45.4642'),
                    TextInput::make('lon')->label('Longitudine del centro')->required()->placeholder('9.19'),
                    TextInput::make('km')->label('Lato del riquadro (km)')->default('0.5')->required(),
                ])
                ->modalSubmitActionLabel('Conta')
                ->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);
                    $lat = filter_var(str_replace(',', '.', trim((string) ($data['lat'] ?? ''))), FILTER_VALIDATE_FLOAT);
                    $lon = filter_var(str_replace(',', '.', trim((string) ($data['lon'] ?? ''))), FILTER_VALIDATE_FLOAT);
                    $km = filter_var(str_replace(',', '.', trim((string) ($data['km'] ?? ''))), FILTER_VALIDATE_FLOAT);
                    if ($lat === false || $lon === false || $km === false || $km <= 0 || abs((float) $lat) >= 89) {
                        Notification::make()->warning()->title('Valori non validi')->body('Scrivi centro e lato col punto, per esempio 45.4642, 9.19 e 0.5.')->send();

                        return;
                    }
                    $mezzoLat = ((float) $km / 2) / 110.57;
                    $mezzoLon = ((float) $km / 2) / (111.32 * cos(deg2rad((float) $lat)));
                    $dove = MagazzinoGeo::modoOggetti() === 'locale' ? 'dai file sul server' : 'da reesty';
                    $esito = MagazzinoGeo::oggetti([(float) $lon - $mezzoLon, (float) $lat - $mezzoLat, (float) $lon + $mezzoLon, (float) $lat + $mezzoLat]);
                    if ($esito === null) {
                        Notification::make()->danger()->persistent()->title('Lettura non riuscita ('.$dove.')')->body((string) (MagazzinoGeo::$ultimoMotivo ?? 'motivo non registrato'))->send();

                        return;
                    }
                    $conti = [];
                    foreach ($esito['strati'] as $strato => $oggetti) {
                        $conti[] = str_replace('_', ' ', (string) $strato).' '.number_format(count($oggetti), 0, ',', '.');
                    }
                    Notification::make()->success()->persistent()->title('Mappa letta '.$dove.' in '.self::numero((float) $esito['millisecondi']).' ms')
                        ->body(($conti === [] ? 'Nessuno strato.' : implode(' · ', $conti).'.').($esito['troncato'] ? ' Troppi oggetti: elenco troncato.' : '').(MagazzinoGeo::$ultimoMotivo !== null ? ' Avviso: '.MagazzinoGeo::$ultimoMotivo : ''))
                        ->send();
                }),
            Action::make('scorta')
                ->label('Torna all\'edizione di prima')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (): bool => $this->fileConScorta() !== [])
                ->modalHeading('Tornare all\'edizione di prima?')
                ->modalDescription('Su reesty l\'edizione in uso e la scorta si scambiano di posto: si può sempre tornare indietro allo stesso modo. Vale per dev e produzione insieme.')
                ->schema([
                    Select::make('file')->label('Quale file')->options(fn (): array => $this->fileConScorta())->required(),
                ])
                ->modalSubmitActionLabel('Torna indietro')
                ->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);
                    $file = (string) ($data['file'] ?? '');
                    if (! array_key_exists($file, $this->fileConScorta())) {
                        Notification::make()->warning()->title('Scegli un file con la scorta')->send();

                        return;
                    }
                    $esito = MagazzinoGeo::ripristina($file);
                    $this->stato = MagazzinoGeo::stato(true);
                    if ($esito['ok']) {
                        Notification::make()->success()->title('Fatto: '.$file.' è tornato all\'edizione '.($esito['dati']['edizione'] ?? 'di prima'))->send();

                        return;
                    }
                    Notification::make()->danger()->persistent()->title('Non fatto')->body((string) $esito['motivo'])->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $numero = function (string $chiave, string $aiuto): TextInput {
            [$etichetta, $fabbrica, $minimo, $massimo, $unita] = ImpostazioniGeo::NUMERI[$chiave];

            return TextInput::make($chiave)
                ->label($etichetta.' ('.$unita.')')
                ->placeholder(ImpostazioniGeo::cifra((float) $fabbrica))
                ->helperText($aiuto.' Da '.ImpostazioniGeo::cifra((float) $minimo).' a '.ImpostazioniGeo::cifra((float) $massimo).'. Di fabbrica: '.ImpostazioniGeo::cifra((float) $fabbrica).'. Vuoto torna alla fabbrica.')
                ->maxLength(10)
                ->autocomplete('off');
        };

        return $schema
            ->components([
                Section::make('Da dove si legge')
                    ->columns(2)
                    ->schema([
                        Select::make(ImpostazioniGeo::MODO)
                            ->label('Modo')
                            ->options(ImpostazioniGeo::MODI)
                            ->selectablePlaceholder(false)
                            ->helperText('Di fabbrica automatico: su dev la fibra si legge dal file come oggi, in produzione da reesty. Si può cambiare e tornare indietro quando si vuole.'),
                        TextInput::make(ImpostazioniGeo::INDIRIZZO)
                            ->label('Indirizzo del servizio')
                            ->placeholder(ImpostazioniGeo::indirizzoDiFabbrica())
                            ->helperText('Di fabbrica: «'.ImpostazioniGeo::indirizzoDiFabbrica().'» (la produzione usa /geo/, dev /geo-prova/). Se un giorno il servizio andrà su geo.reesty.it o su un altro hosting, si cambia qui. Vuoto torna alla fabbrica.')
                            ->maxLength(200)
                            ->autocomplete('off'),
                    ]),
                Section::make('Attese e memoria')
                    ->columns(2)
                    ->schema([
                        $numero(ImpostazioniGeo::ATTESA_FIBRA, 'Quanto aspetta la scheda di un annuncio la risposta di reesty, poi dice «in lettura».'),
                        $numero(ImpostazioniGeo::ATTESA_OGGETTI, 'Quanto aspetta il report gli oggetti della mappa.'),
                        $numero(ImpostazioniGeo::MEMORIA_GIORNI, 'La stessa casa non si chiede due volte di fila. 0 = non ricordare.'),
                        $numero(ImpostazioniGeo::PAUSA_MINUTI, 'Se reesty non risponde, per questi minuti il sito non gli chiede più niente: le pagine non aspettano. 0 = nessuna pausa.'),
                    ]),
                Section::make('La copia di OpenStreetMap')
                    ->columns(2)
                    ->schema([
                        $numero(ImpostazioniGeo::RIQUADRO_KM, 'Il lato più lungo del riquadro di una mappa (il servizio su reesty ha il suo limite: 10 km).'),
                        $numero(ImpostazioniGeo::SOGLIA_MESI, 'Qui sopra compare un avviso quando la copia di una regione è più vecchia.'),
                        CheckboxList::make(ImpostazioniGeo::REGIONI)
                            ->label('Regioni da usare')
                            ->options(ImpostazioniGeo::TUTTE_LE_REGIONI)
                            ->columns(5)
                            ->helperText('Le regioni di Geofabrik: insieme coprono tutta l\'Italia. Di fabbrica tutte. Quali regioni copiare lo decide il workflow «Copia OpenStreetMap» del repository pubblico.')
                            ->columnSpanFull(),
                    ]),
                Section::make('La fibra')
                    ->columns(2)
                    ->schema([
                        Toggle::make(ImpostazioniGeo::FIBRA_SCHEDA)
                            ->label('Fibra nella scheda degli annunci')
                            ->helperText('Spenta, la casella Fibra dice «in lettura» come prima di questo lotto.'),
                        Toggle::make(ImpostazioniGeo::FIBRA_REPORT)
                            ->label('Fibra nel report')
                            ->helperText('La userà il report della Valutazione nei prossimi lotti.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function salva(): void
    {
        abort_unless(static::canAccess(), 403);
        try {
            $cambiate = ImpostazioniGeo::scrivi(auth()->user(), $this->form->getState());
        } catch (DomainException $e) {
            Notification::make()->danger()->persistent()->title('Magazzino geografico NON salvato')->body($e->getMessage())->send();

            return;
        }
        $this->form->fill(ImpostazioniGeo::tutte());
        $nuovo = MagazzinoGeo::stato(false);
        $nuovo['remoto'] = $this->stato['remoto'] ?? null;
        $this->stato = $nuovo;

        Notification::make()
            ->success()
            ->title($cambiate === [] ? 'Nessuna modifica' : 'Magazzino geografico salvato')
            ->body($cambiate === [] ? 'I valori sono quelli di prima.' : 'Cambiate: '.implode(', ', $cambiate).'. Premi «Prova il collegamento adesso» per vedere l\'effetto.')
            ->send();
    }

    /**
     * I file su reesty che hanno una scorta (per «Torna all'edizione di prima»).
     *
     * @return array<string, string>
     */
    public function fileConScorta(): array
    {
        $file = [];
        foreach ((array) ($this->stato['remoto']['dati']['file'] ?? []) as $nome => $info) {
            if (is_array($info['scorta'] ?? null)) {
                $file[(string) $nome] = $nome.': torna a «'.($info['scorta']['edizione'] ?? '?').'» (ora «'.($info['edizione'] ?? '?').'»)';
            }
        }

        return $file;
    }

    /**
     * Le righe del riquadro «Com'è adesso»: [cosa, come, tono] con tono ok, no, avviso o null.
     *
     * @return list<array{0: string, 1: string, 2: ?string}>
     */
    public function righe(): array
    {
        $s = $this->stato;
        if ($s === []) {
            return [];
        }
        $dove = fn (string $modo): string => $modo === 'locale' ? 'dal file sul server del sito' : 'dal servizio su reesty';
        $righe = [
            ['Modo scelto', ImpostazioniGeo::MODI[$s['modo']] ?? $s['modo'], null],
            ['La fibra si legge', $dove((string) $s['modo_fibra']).(MagazzinoGeo::fibraAccesa('scheda') ? '' : ' · SPENTA per la scheda').(MagazzinoGeo::fibraAccesa('report') ? '' : ' · SPENTA per il report'), null],
            ['Le mappe si leggono', $dove((string) $s['modo_oggetti']), null],
            ['Indirizzo del servizio', (string) $s['indirizzo'], null],
            ['Chiave (GEO_CHIAVE nel .env)', $s['chiave'] ? 'presente' : 'MANCA: il sito non può chiedere niente a reesty', $s['chiave'] ? 'ok' : 'no'],
        ];
        foreach (['pausa' => 'Pausa della fibra', 'pausa_oggetti' => 'Pausa delle mappe'] as $chiave => $nome) {
            if (($s[$chiave] ?? null) !== null) {
                $righe[] = [$nome, 'reesty non ha risposto ('.$s[$chiave]['motivo'].'): il sito non gli chiede niente fino alle '.date('H:i', (int) $s[$chiave]['fino']).'. «Prova il collegamento adesso» la toglie se reesty risponde.', 'avviso'];
            }
        }
        $loc = $s['locale'];
        $righe[] = ['Fibra sul server del sito', $loc['fibra'] !== null ? 'c\'è: '.self::mega((int) $loc['fibra']['byte']).', edizione '.$loc['fibra']['edizione'] : 'non c\'è', null];
        if ($loc['osm'] !== []) {
            $righe[] = ['OpenStreetMap sul server del sito', implode(', ', array_map(fn ($r, $i) => $r.' '.self::mega((int) $i['byte']), array_keys($loc['osm']), $loc['osm'])), null];
        }

        $r = $s['remoto'];
        if ($r === null) {
            $righe[] = ['Reesty', 'non chiesto: premi «Prova il collegamento adesso»', null];

            return $righe;
        }
        if (! $r['ok']) {
            $righe[] = ['Reesty', 'NON risponde come dovrebbe: '.$r['motivo'], 'no'];

            return $righe;
        }
        $d = $r['dati'];
        $righe[] = ['Reesty', 'risponde in '.self::numero((float) $r['millisecondi']).' ms · servizio '.($d['servizio'] ?? '?').' · PHP '.($d['php'] ?? '?').' · SQLite '.($d['sqlite'] ?? '?').' · indice spaziale '.(($d['rtree'] ?? false) ? 'sì' : 'NO'), ($d['rtree'] ?? false) ? 'ok' : 'no'];

        $file = (array) ($d['file'] ?? []);
        $soglia = ImpostazioniGeo::numero(ImpostazioniGeo::SOGLIA_MESI);
        if (! isset($file['fibra'])) {
            $righe[] = ['Fibra su reesty', 'non c\'è ancora (la porta «geo:fibra-a-reesty» dal Toolkit di dev)', 'avviso'];
        }
        foreach ($file as $nome => $info) {
            $testo = 'edizione '.($info['edizione'] ?? '?').' · '.self::mega((int) ($info['byte'] ?? 0)).' · caricata il '.self::data($info['caricato'] ?? ($info['modificato'] ?? null));
            if (is_array($info['scorta'] ?? null)) {
                $testo .= ' · scorta: '.($info['scorta']['edizione'] ?? '?');
            }
            $tono = 'ok';
            if (str_starts_with((string) $nome, 'osm-')) {
                $quando = isset($info['caricato']) ? Carbon::parse((string) $info['caricato']) : null;
                if ($quando !== null && $quando->lt(now()->subDays((int) round($soglia * 30.44)))) {
                    $testo .= ' · COPIA VECCHIA: più di '.ImpostazioniGeo::cifra($soglia).' mesi';
                    $tono = 'avviso';
                }
                if (! in_array(substr((string) $nome, 4), ImpostazioniGeo::regioniELMare(), true)) {
                    $testo .= ' · regione non usata (vedi «Regioni da usare»)';
                }
            }
            $righe[] = [$nome === 'fibra' ? 'Fibra su reesty' : 'OpenStreetMap '.substr((string) $nome, 4), $testo, $tono];
        }
        $osm = array_filter(array_keys($file), fn ($n) => str_starts_with((string) $n, 'osm-'));
        if ($osm === []) {
            $righe[] = ['OpenStreetMap su reesty', 'non c\'è ancora (la porta il workflow «Copia OpenStreetMap» del repository pubblico)', 'avviso'];
        }
        foreach ((array) ($d['in_arrivo'] ?? []) as $nome => $info) {
            $righe[] = ['In arrivo', $nome.': '.self::mega((int) ($info['byte'] ?? 0)).' arrivati finora (ultimo pezzo il '.self::data($info['modificato'] ?? null).')', 'avviso'];
        }
        foreach ((array) ($d['scartati'] ?? []) as $nome => $info) {
            $righe[] = ['Scartato', $nome.' del '.self::data($info['modificato'] ?? null).': non tornava, resta l\'edizione di prima', 'avviso'];
        }

        return $righe;
    }

    public static function numero(float $n, int $decimali = 0): string
    {
        return number_format($n, $decimali, ',', '.');
    }

    private static function mega(int $byte): string
    {
        return $byte >= 1073741824 ? number_format($byte / 1073741824, 2, ',', '.').' GB' : number_format($byte / 1048576, 1, ',', '.').' MB';
    }

    private static function data(mixed $quando): string
    {
        if (! is_string($quando) || $quando === '') {
            return '?';
        }
        try {
            return Carbon::parse($quando)->timezone('Europe/Rome')->format('d/m/Y H:i');
        } catch (\Throwable) {
            return '?';
        }
    }
}