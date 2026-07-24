<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold">Simülasyon</h2>
        <a href="{{ route('dashboard') }}" class="text-sm text-slate-400 hover:text-slate-200 underline underline-offset-4">
            &larr; Dashboard
        </a>
    </div>

    <div class="flex items-center gap-3">
        <button wire:click="runSimulation" wire:loading.attr="disabled"
            class="px-4 py-2 text-sm font-medium rounded-md bg-emerald-500 text-slate-950 hover:bg-emerald-400 disabled:opacity-50">
            <span wire:loading.remove wire:target="runSimulation">Simülasyonu Başlat</span>
            <span wire:loading wire:target="runSimulation">Çalışıyor...</span>
        </button>
        <span class="text-xs text-slate-500">Bu bir "ne olurdu" simülasyonudur, kayıtlı batarya verisini değiştirmez.</span>
    </div>

    @if ($error)
        <p class="text-sm text-rose-400">{{ $error }}</p>
    @endif

    @if ($hasRun)
        <div class="bg-slate-900/60 border border-slate-800 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-400 border-b border-slate-800">
                        <th class="px-3 py-2 font-medium">Saat</th>
                        <th class="px-3 py-2 font-medium">Karar</th>
                        <th class="px-3 py-2 font-medium">Miktar (kWh)</th>
                        <th class="px-3 py-2 font-medium">Üretim</th>
                        <th class="px-3 py-2 font-medium">Tüketim</th>
                        <th class="px-3 py-2 font-medium">Fiyat</th>
                        <th class="px-3 py-2 font-medium">SOC (önce &rarr; sonra)</th>
                        <th class="px-3 py-2 font-medium">Gerekçe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-slate-800/60 align-top">
                            <td class="px-3 py-2">{{ str_pad($row['hour'], 2, '0', STR_PAD_LEFT) }}:00</td>
                            <td class="px-3 py-2">{{ $row['action'] }}</td>
                            <td class="px-3 py-2">{{ $row['amountKwh'] }}</td>
                            <td class="px-3 py-2">{{ $row['production'] }}</td>
                            <td class="px-3 py-2">{{ $row['consumption'] }}</td>
                            <td class="px-3 py-2">{{ $row['price'] }}</td>
                            <td class="px-3 py-2">%{{ $row['socBefore'] }} &rarr; %{{ $row['socAfter'] }}</td>
                            <td class="px-3 py-2 text-xs text-slate-400">{{ implode(' · ', $row['reasons']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
