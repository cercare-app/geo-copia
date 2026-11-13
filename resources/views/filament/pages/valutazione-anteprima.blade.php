<x-filament-panels::page>
    {{-- Lotto «Valutazione 1» (D-VAL-41): l'anteprima del report e la misura del web. --}}
    @php
        $q = $this->quadro();
        $u = $q['ultima'];
        $cella = 'padding:8px 10px;border-bottom:1px solid #e5e7eb;vertical-align:top;text-align:left;font-size:14px;';
        $nome = 'padding:8px 10px;border-bottom:1px solid #e5e7eb;vertical-align:top;text-align:left;font-size:14px;color:#6b7280;width:240px;';
        $quando = $u !== null ? \Illuminate\Support\Carbon::parse($u['quando'])->timezone('Europe/Rome')->format('d/m/Y H:i') : null;
    @endphp

    <x-filament::section>
        <x-slot name="heading">Con che cosa si fa</x-slot>
        <table style="width:100%;border-collapse:collapse;">
            <tr><td style="{{ $nome }}">Dati di esempio</td><td style="{{ $cella }}">{{ $q['esempio'] }}</td></tr>
            <tr><td style="{{ $nome }}">Testi e impostazioni</td><td style="{{ $cella }}">Quelli di adesso: «Valutazione · Testi del report», «Valutazione · Pagine e riquadri», «Valutazione · Mappe e territorio» e «Valutazione · Impostazioni».</td></tr>
            <tr><td style="{{ $nome }}">Mappe</td><td style="{{ $cella }}">Dal magazzino geografico, come un report vero. I dati dell'esempio si ricordano per {{ $q['giorni_mappe'] === 0 ? 'nessun giorno: ogni anteprima li richiede' : ($q['giorni_mappe'] === 1 ? '1 giorno' : $q['giorni_mappe'].' giorni') }} (si cambia in «Valutazione · Mappe e territorio»).</td></tr>
            <tr><td style="{{ $nome }}">Foto degli annunci</td><td style="{{ $cella }}">Illustrazioni d'esempio, non foto vere (le foto vere le prenderà la generazione dei report).</td></tr>
            <tr><td style="{{ $nome }}">Guardia sul nome</td><td style="{{ $cella }}">{{ $q['guardia'] }}</td></tr>
            <tr><td style="{{ $nome }}">Questa pagina, sul server</td><td style="{{ $cella }}">Estensione gd {{ $q['gd'] ? 'presente' : 'ASSENTE: il PDF non si può fare' }} · memoria {{ $q['memoria'] }} · tempo massimo {{ $q['secondi'] === '0' ? 'nessuno' : $q['secondi'].' s' }}</td></tr>
        </table>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">L'ultima anteprima</x-slot>
        @if ($u === null)
            <p style="font-size:14px;">Nessuna anteprima ancora. Premi «Genera l'anteprima», in alto.</p>
        @else
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="{{ $nome }}">Quando e chi</td><td style="{{ $cella }}">{{ $quando }} · {{ $u['chi'] ?? '—' }} ({{ $u['dove'] ?? '—' }}) · esempio {{ ($u['esempio'] ?? '') === 'caricato' ? 'caricato' : 'di fabbrica' }}</td></tr>
                <tr><td style="{{ $nome }}">Testi</td><td style="{{ $cella }}">{{ ($u['versione_testi'] ?? null) !== null ? 'versione n. '.$u['versione_testi'] : 'di fabbrica (nessuna versione salvata)' }}</td></tr>
                @if (($u['esito'] ?? '') === 'ok')
                    <tr><td style="{{ $nome }}">Esito</td><td style="{{ $cella }}"><strong style="color:#166534;">Fatta</strong> · {{ $u['pagine'] }} pagine{{ (int) $u['pagine'] !== (int) $u['attese'] ? ' (attese '.$u['attese'].': un testo è troppo lungo e la pagina è andata a capo)' : '' }}</td></tr>
                    <tr><td style="{{ $nome }}">Tempo</td><td style="{{ $cella }}">{{ \App\Filament\Pages\ValutazioneAnteprima::numero((float) $u['secondi'], 2) }} s{{ isset($u['secondi_mappe']) ? ', di cui '.\App\Filament\Pages\ValutazioneAnteprima::numero((float) $u['secondi_mappe'], 2).' s per i dati delle mappe' : '' }} ({{ ($u['limiti']['secondi'] ?? '') === '0' ? 'la pagina non ha un tempo massimo' : 'la pagina ha '.($u['limiti']['secondi'] ?? '?').' s al massimo' }})</td></tr>
                    <tr><td style="{{ $nome }}">Memoria di picco</td><td style="{{ $cella }}">{{ \App\Filament\Pages\ValutazioneAnteprima::numero((float) $u['memoria_mb']) }} MB, di cui {{ \App\Filament\Pages\ValutazioneAnteprima::numero((float) $u['memoria_in_piu_mb']) }} MB per il PDF (limite {{ $u['limiti']['memoria'] ?? '?' }})</td></tr>
                    <tr><td style="{{ $nome }}">Peso del file</td><td style="{{ $cella }}">{{ \App\Filament\Pages\ValutazioneAnteprima::numero((float) $u['byte'] / 1024, 0) }} KB</td></tr>
                @else
                    <tr><td style="{{ $nome }}">Esito</td><td style="{{ $cella }}"><strong style="color:#9f1d26;">{{ ($u['esito'] ?? '') === 'fermata' ? 'Fermata' : 'Non riuscita' }}</strong></td></tr>
                    <tr><td style="{{ $nome }}">Perché</td><td style="{{ $cella }}">
                        <ul style="margin:0;padding-left:18px;">
                            @foreach ((array) ($u['motivi'] ?? []) as $motivo)
                                <li>{{ $motivo }}</li>
                            @endforeach
                        </ul>
                    </td></tr>
                @endif
                @if (($u['esito'] ?? '') === 'ok' && array_key_exists('controllo', $u))
                    @foreach (\App\Support\Valutazione\Mappe\Prova::righe(is_array($u['controllo']) ? $u['controllo'] : null) as [$cosa, $come, $tono])
                        <tr><td style="{{ $nome }}">Casa sulla mappa · {{ $cosa }}</td><td style="{{ $cella }}{{ $tono === 'ok' ? 'color:#166534;' : ($tono === 'no' ? 'color:#9f1d26;' : ($tono === 'avviso' ? 'color:#92400e;' : '')) }}">{{ $come }}</td></tr>
                    @endforeach
                @endif
                @if (! empty($u['avvisi_mappe']))
                    <tr><td style="{{ $nome }}">Mappe che non sono uscite</td><td style="{{ $cella }}">
                        <ul style="margin:0;padding-left:18px;">
                            @foreach ((array) $u['avvisi_mappe'] as $avviso)
                                <li>{{ $avviso }}</li>
                            @endforeach
                        </ul>
                    </td></tr>
                @endif
                @if (! empty($u['avvisi']))
                    <tr><td style="{{ $nome }}">Avvisi della pulizia dei dati</td><td style="{{ $cella }}">
                        <ul style="margin:0;padding-left:18px;">
                            @foreach ((array) $u['avvisi'] as $avviso)
                                <li>{{ $avviso }}</li>
                            @endforeach
                        </ul>
                    </td></tr>
                @endif
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>