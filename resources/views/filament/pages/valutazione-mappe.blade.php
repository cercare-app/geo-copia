<x-filament-panels::page>
    {{-- Lotto «Valutazione 4» (D-VAL-101, D-VAL-99): mappe, vista dall'alto, colori, casa sulla mappa, territorio. --}}
    @php
        $cella = 'padding:8px 10px;border-bottom:1px solid #e5e7eb;vertical-align:top;text-align:left;font-size:14px;';
        $nome = 'padding:8px 10px;border-bottom:1px solid #e5e7eb;vertical-align:top;text-align:left;font-size:14px;color:#6b7280;width:260px;';
        $colori = ['ok' => '#166534', 'no' => '#9f1d26', 'avviso' => '#92400e'];
        $righe = $this->righeProva();
        $vista = $this->vistaProva();
    @endphp

    @if ($righe !== [])
        <x-filament::section>
            <x-slot name="heading">Prova un indirizzo</x-slot>
            <x-slot name="description">Con le scelte salvate qui e i dati di adesso del magazzino geografico. Per un altro punto, «Prova un indirizzo» in alto.</x-slot>
            <table style="width:100%;border-collapse:collapse;">
                @foreach ($righe as [$cosa, $come, $tono])
                    <tr>
                        <td style="{{ $nome }}">{{ $cosa }}</td>
                        <td style="{{ $cella }}{{ $tono !== null ? 'color:'.$colori[$tono].';' : '' }}">{{ $come }}</td>
                    </tr>
                @endforeach
            </table>
            @if ($vista !== null)
                <p style="font-size:14px;color:#6b7280;margin:14px 0 6px;">La vista dall'alto di quel punto (nel PDF i caratteri sono quelli del report):</p>
                <img src="{{ $vista }}" alt="Vista dall'alto del punto provato" style="width:100%;max-width:720px;border:1px solid #e5e7eb;border-radius:8px;">
            @endif
        </x-filament::section>
    @endif

    <form wire:submit="salva">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Salva mappe e territorio
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>