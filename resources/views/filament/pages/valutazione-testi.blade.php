<x-filament-panels::page>
    <form wire:submit="salva">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Salva i testi del report
            </x-filament::button>
        </div>
    </form>

    {{-- Lotto «Valutazione 2» (D-VAL-60): le versioni dei testi, delle pagine e dei riquadri. --}}
    @php
        $versioni = $this->versioni();
        $puoTornare = $this->puoTornare();
        $differenze = $this->differenze();
        $cella = 'padding:8px 10px;border-bottom:1px solid #e5e7eb;vertical-align:top;text-align:left;font-size:14px;';
    @endphp
    <x-filament::section>
        <x-slot name="heading">Le versioni</x-slot>
        <x-slot name="description">Ogni salvataggio qui, in «Pagine e riquadri» e in «Mappe e territorio» è una versione. Il report porta il numero della versione con cui è nato. «Torna a questa versione» ne crea una nuova uguale: niente si cancella.</x-slot>
        @if ($versioni === [])
            <p style="font-size:14px;">Nessuna versione ancora: i testi sono quelli di fabbrica. La prima nasce al primo salvataggio (e con lei «Com'era prima»).</p>
        @else
            <table style="width:100%;border-collapse:collapse;">
                <tr>
                    <th style="{{ $cella }}">N.</th>
                    <th style="{{ $cella }}">Quando</th>
                    <th style="{{ $cella }}">Chi e dove</th>
                    <th style="{{ $cella }}">Che cosa è cambiato</th>
                    <th style="{{ $cella }}"></th>
                </tr>
                @foreach ($versioni as $k => $v)
                    <tr>
                        <td style="{{ $cella }}"><strong>{{ $v['numero'] }}</strong>{{ $k === 0 ? ' (in uso)' : '' }}</td>
                        <td style="{{ $cella }}">{{ $v['quando'] !== '' ? \Illuminate\Support\Carbon::parse($v['quando'])->timezone('Europe/Rome')->format('d/m/Y H:i') : '—' }}</td>
                        <td style="{{ $cella }}">{{ $v['chi'] !== '' ? $v['chi'] : '—' }} · {{ $v['dove'] }}</td>
                        <td style="{{ $cella }}">{{ $v['cambiate'] !== [] ? \Illuminate\Support\Str::limit(implode(', ', $v['cambiate']), 220) : '—' }}</td>
                        <td style="{{ $cella }}white-space:nowrap;">
                            @if ($k > 0)
                                <x-filament::link tag="button" wire:click="guarda({{ $v['numero'] }})">{{ $this->guardata === $v['numero'] ? 'Chiudi' : 'Guarda' }}</x-filament::link>
                                @if ($puoTornare)
                                    &nbsp;·&nbsp;
                                    <x-filament::link tag="button" color="danger" wire:click="ripristina({{ $v['numero'] }})" wire:confirm="Tornare alla versione n. {{ $v['numero'] }}? Testi, avvertenze, pagine, riquadri e mappe diventano quelli di allora (nasce una versione nuova, niente si cancella).">Torna a questa versione</x-filament::link>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @if ($this->guardata === $v['numero'])
                        <tr>
                            <td colspan="5" style="{{ $cella }}background:#f9fafb;">
                                @if ($differenze === [])
                                    Uguale a oggi.
                                @else
                                    <table style="width:100%;border-collapse:collapse;">
                                        <tr><th style="{{ $cella }}">Voce</th><th style="{{ $cella }}">Nella versione n. {{ $v['numero'] }}</th><th style="{{ $cella }}">Oggi</th></tr>
                                        @foreach ($differenze as [$voce, $allora, $oggi])
                                            <tr>
                                                <td style="{{ $cella }}">{{ $voce }}</td>
                                                <td style="{{ $cella }}white-space:pre-line;">{{ $allora }}</td>
                                                <td style="{{ $cella }}white-space:pre-line;">{{ $oggi }}</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                @endif
                            </td>
                        </tr>
                    @endif
                @endforeach
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>