<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-white">Simülasyon</h2>
        <a href="{{ route('dashboard') }}" class="text-sm text-slate-400 hover:text-slate-200 underline underline-offset-4">
            &larr; Dashboard
        </a>
    </div>

    <div class="flex items-center gap-3">
        <button wire:click="runSimulation" wire:loading.attr="disabled"
            class="px-4 py-2 text-sm font-bold rounded-[10px] bg-brand-green text-slate-950 hover:brightness-90 disabled:opacity-50">
            <span wire:loading.remove wire:target="runSimulation">Simülasyonu Başlat</span>
            <span wire:loading wire:target="runSimulation">Çalışıyor...</span>
        </button>
        <span class="text-xs text-slate-500">Bu bir "ne olurdu" simülasyonudur, kayıtlı batarya verisini değiştirmez.</span>
    </div>

    @if ($error)
        <p class="text-sm text-rose-400">{{ $error }}</p>
    @endif

    @if ($hasRun)
        {{-- Summary cards --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            @php
                $cards = [
                    ['label' => 'Batarya SOC (gün sonu)', 'value' => '%'.$metrics['finalSocPercent']],
                    ['label' => 'Batarya SOH (gün sonu)', 'value' => '%'.$metrics['projectedSohPercent']],
                    ['label' => 'Günlük Üretim', 'value' => $metrics['totalProductionKwh'].' kWh'],
                    ['label' => 'Günlük Tüketim', 'value' => $metrics['totalConsumptionKwh'].' kWh'],
                    ['label' => 'Şebekeden Alınan', 'value' => $metrics['totalGridDrawKwh'].' kWh'],
                    ['label' => 'Piyasaya Satılan', 'value' => $metrics['totalSoldKwh'].' kWh'],
                    ['label' => 'Toplam Kayıp', 'value' => $metrics['totalLossKwh'].' kWh'],
                    ['label' => 'Batarya Olmasaydı (Maliyet)', 'value' => $metrics['baselineNetCostTl'].' TL'],
                    ['label' => 'Gerçekleşen Net Maliyet', 'value' => $metrics['actualNetCostTl'].' TL'],
                    ['label' => 'Tasarruf', 'value' => $metrics['savingsTl'].' TL'],
                ];
            @endphp
            @foreach ($cards as $card)
                <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light">
                    <p class="text-xs text-slate-400">{{ $card['label'] }}</p>
                    <p class="mt-1 text-xl font-bold text-brand-green">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Charts --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" wire:ignore>
            <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light">
                <p class="text-sm text-slate-300 mb-2">SOC / Üretim / Tüketim (saatlik)</p>
                <canvas id="socProductionChart" height="220"></canvas>
            </div>
            <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light">
                <p class="text-sm text-slate-300 mb-2">Piyasa Fiyatı (TL/kWh)</p>
                <canvas id="priceChart" height="220"></canvas>
            </div>
        </div>

        {{-- Detail table --}}
        <div class="bg-brand-navy border border-brand-navy-light rounded-[10px] overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-400 border-b border-slate-800">
                        <th class="px-3 py-2 font-medium">Saat</th>
                        <th class="px-3 py-2 font-medium">Karar</th>
                        <th class="px-3 py-2 font-medium">Miktar (kWh)</th>
                        <th class="px-3 py-2 font-medium">Beklenen Kâr</th>
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
                            <td class="px-3 py-2">
                                @if ($row['expectedProfitTl'] !== null)
                                    <span class="text-brand-green font-semibold">{{ number_format($row['expectedProfitTl'], 2) }} TL</span>
                                @else
                                    <span class="text-slate-600">&mdash;</span>
                                @endif
                            </td>
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

@script
<script>
    let socProductionChart = null;
    let priceChart = null;

    $wire.on('simulation-completed', ({ hourly }) => {
        const labels = hourly.map(h => String(h.hour).padStart(2, '0') + ':00');
        const soc = hourly.map(h => h.soc);
        const production = hourly.map(h => h.production);
        const consumption = hourly.map(h => h.consumption);
        const price = hourly.map(h => h.price);

        const socProductionCtx = document.getElementById('socProductionChart');
        const priceCtx = document.getElementById('priceChart');

        if (socProductionChart) {
            socProductionChart.destroy();
        }
        if (priceChart) {
            priceChart.destroy();
        }

        socProductionChart = new Chart(socProductionCtx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'SOC (%)',
                        data: soc,
                        borderColor: '#00E500',
                        backgroundColor: '#00E500',
                        yAxisID: 'y1',
                        tension: 0.3,
                    },
                    {
                        label: 'Üretim (kWh)',
                        data: production,
                        borderColor: '#60a5fa',
                        backgroundColor: '#60a5fa',
                        yAxisID: 'y2',
                        tension: 0.3,
                    },
                    {
                        label: 'Tüketim (kWh)',
                        data: consumption,
                        borderColor: '#f97316',
                        backgroundColor: '#f97316',
                        yAxisID: 'y2',
                        tension: 0.3,
                    },
                ],
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y1: {
                        type: 'linear',
                        position: 'left',
                        min: 0,
                        max: 100,
                        ticks: { color: '#94a3b8' },
                        grid: { color: '#243256' },
                        title: { display: true, text: 'SOC (%)', color: '#94a3b8' },
                    },
                    y2: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        ticks: { color: '#94a3b8' },
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'kWh', color: '#94a3b8' },
                    },
                    x: {
                        ticks: { color: '#94a3b8' },
                        grid: { color: '#243256' },
                    },
                },
                plugins: {
                    legend: { labels: { color: '#e2e8f0' } },
                },
            },
        });

        priceChart = new Chart(priceCtx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Fiyat (TL/kWh)',
                        data: price,
                        borderColor: '#00E500',
                        backgroundColor: '#00E500',
                        tension: 0.3,
                    },
                ],
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#94a3b8' },
                        grid: { color: '#243256' },
                    },
                    x: {
                        ticks: { color: '#94a3b8' },
                        grid: { color: '#243256' },
                    },
                },
                plugins: {
                    legend: { labels: { color: '#e2e8f0' } },
                },
            },
        });
    });
</script>
@endscript
