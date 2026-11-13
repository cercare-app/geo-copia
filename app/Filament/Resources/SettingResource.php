<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Filament\Pages\AiImpostazioni;
use App\Filament\Pages\InvitiImpostazioni;
use App\Filament\Pages\SchedaImpostazioni;
use App\Support\LinkLegali;
use App\Support\PostaOnboarding;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Impostazioni';

    protected static ?string $modelLabel = 'impostazione';

    protected static ?string $pluralModelLabel = 'impostazioni';

    protected static ?string $recordTitleAttribute = 'key';

    protected static ?int $navigationSort = 90;

    /**
     * LA DOPPIA CHIAVE DELLA POSTA (Lotto POSTA, D-GL-132): «Posta vera»
     * (posta_reale) passa a 1 solo se, nello stesso salvataggio, si scrive
     * anche questa frase nel campo di conferma. Spegnere non chiede nulla.
     */
    public const FRASE_POSTA = 'ACCENDO LA POSTA';

    /** Il campo della frase (solo nel modulo: non va mai nel database). */
    public const CAMPO_CONFERMA = 'conferma_posta';

    /**
     * Il salvataggio è ammesso? Sì per ogni impostazione che non sia la posta,
     * sì per la posta se non la si sta accendendo; per accenderla (da un valore
     * che non è esattamente 1, come lo legge il lucchetto, a 1) serve la frase
     * esatta.
     */
    public static function accensionePostaAmmessa(?string $chiave, ?string $prima, ?string $nuovo, ?string $frase): bool
    {
        if ($chiave !== PostaOnboarding::INTERRUTTORE) {
            return true;
        }
        if (trim((string) $nuovo) !== '1' || (string) $prima === '1') {
            return true;
        }

        return trim((string) preg_replace('/\s+/u', ' ', (string) $frase)) === self::FRASE_POSTA;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Impostazione')
                    ->schema([
                        TextInput::make('key')
                            ->label('Chiave tecnica')
                            ->disabled(),
                        Textarea::make('value')
                            ->label(fn ($record): string => Setting::LABELS[$record?->key]['label'] ?? 'Valore')
                            ->helperText(fn ($record): string => Setting::LABELS[$record?->key]['help'] ?? '')
                            ->rows(6)
                            ->rules([
                                fn ($get, $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $record): void {
                                    if (! self::accensionePostaAmmessa($record?->key, $record?->value, is_scalar($value) ? (string) $value : null, (string) $get(self::CAMPO_CONFERMA))) {
                                        $fail('Per accendere la posta vera scrivi esattamente «'.self::FRASE_POSTA.'» nel campo di conferma qui sotto.');
                                    }
                                },
                            ])
                            ->columnSpanFull(),
                        TextInput::make(self::CAMPO_CONFERMA)
                            ->label('Conferma per accendere la posta vera')
                            ->helperText('Solo per accendere (valore 1): scrivi esattamente '.self::FRASE_POSTA.'. Da quel momento le email partono davvero. Per spegnere (valore 0) non serve.')
                            ->visible(fn ($record): bool => $record?->key === PostaOnboarding::INTERRUTTORE)
                            ->dehydrated(false)
                            ->autocomplete('off')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('Impostazione')
                    ->formatStateUsing(fn (string $state): string => Setting::LABELS[$state]['label'] ?? $state)
                    ->searchable(),
                TextColumn::make('value')
                    ->label('Valore attuale')
                    ->default('—')
                    ->limit(60),
                TextColumn::make('updated_at')
                    ->label('Aggiornata')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->paginated(false);
    }

    /**
     * Le impostazioni dell'Area SEO (Seo::IMPOSTAZIONI) si cambiano da «SEO ·
     * Impostazioni del sito», con i loro controlli: qui non compaiono
     * (Lotto S). sito_indicizzabile resta anche qui. Lo stesso per i link
     * di iubenda (LinkLegali::IMPOSTAZIONI, pacchetto 2-4a): si cambiano
     * da «Privacy · Impostazioni», che controlla gli indirizzi e fa salire
     * la versione delle caselle quando i termini si accendono. Le frasi della
     * scheda, gli indicatori dello Score e i titoli senza zona si cambiano da
     * «Scheda · Impostazioni» (Lotto W2). La spedizione degli inviti, le date
     * dei due gruppi e il massimo per giro si cambiano da «Inviti ·
     * Impostazioni», che chiede la frase per accendere la spedizione (Lotto
     * «Inviti a raffica»): qui non compaiono, così la frase non si scavalca.
     * Il codice del widget di iubenda e il suo interruttore (Lotto «Correttivi
     * 2», D-GL-254) li cambia solo l'amministratore da «Privacy ·
     * Impostazioni»: qui non compaiono, così chi ha «Impostazioni» non spegne
     * il banner dei cookie né cambia il widget. I tetti delle risposte dell'AI
     * (TettiAi, D-GL-276) si cambiano da «AI · Impostazioni», che controlla i
     * numeri: qui non compaiono. Prezzo, titolare, metadati, guardia e testi
     * del report della Valutazione (Lotto «Valutazione 1», D-VAL-43) si
     * cambiano da «Valutazione · Impostazioni» e «Valutazione · Testi del
     * report», che li controllano: qui non compaiono.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotIn('key', [
            ...array_keys(Seo::IMPOSTAZIONI),
            ...array_keys(LinkLegali::IMPOSTAZIONI),
            ...SchedaImpostazioni::SOLO_QUI,
            ...InvitiImpostazioni::SOLO_QUI,
            ...AiImpostazioni::soloQui(),
            // Lotto «La cassa e il bonifico» (D-GL-372): vendita, IVA, condizioni e bonifico solo da «Pagamenti · Impostazioni», dell'amministratore
            ...\App\Support\Cassa::IMPOSTAZIONI,
            \App\Support\Iubenda::CHIAVE,
            \App\Support\Iubenda::ACCESO,
            // Lotto «Valutazione 1» (D-VAL-43): solo da «Valutazione · Impostazioni» e «Valutazione · Testi del report»
            ...\App\Support\Valutazione\Impostazioni::chiavi(),
            ...\App\Support\Valutazione\Testi::chiavi(),
            // Lotto «Valutazione 2» (D-VAL-59): solo da «Valutazione · Pagine e riquadri»
            ...\App\Support\Valutazione\Disposizione::chiavi(),
            // Lotto «Valutazione 3» (D-VAL-75): solo da «Valutazione · Magazzino geografico»
            ...\App\Support\Geo\ImpostazioniGeo::chiavi(),
            // Lotto «Valutazione 4» (D-VAL-101): solo da «Valutazione · Mappe e territorio»
            ...\App\Support\Valutazione\Mappe\ImpostazioniMappe::chiavi(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}