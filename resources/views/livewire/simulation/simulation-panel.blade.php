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
            <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light">
                <p class="text-sm text-slate-300 mb-2">Batarya SOH Aşınması (saatlik)</p>
                <canvas id="sohChart" height="220"></canvas>
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

    {{-- ─────────────────────────────────────────────────────────────
         Çok günlük simülasyon — kümülatif tasarruf & geri ödeme.
         Yukarıdaki 24 saatlik simülasyondan bağımsız çalışır.
    ────────────────────────────────────────────────────────────── --}}
    <div class="border-t border-brand-navy-light pt-6 space-y-4">
        <h3 class="text-lg font-bold text-white">Çok Günlük Simülasyon (Kümülatif Tasarruf & Geri Ödeme)</h3>

        <div class="flex flex-wrap items-center gap-3">
            <label class="text-sm text-slate-300">Gün sayısı</label>
            <select wire:model="multiDays"
                class="px-3 py-2 text-sm rounded-[10px] bg-brand-navy border border-brand-navy-light text-slate-100">
                <option value="7">7 gün</option>
                <option value="14">14 gün</option>
                <option value="30">30 gün</option>
                <option value="60">60 gün</option>
                <option value="90">90 gün</option>
            </select>
            <button wire:click="runMultiDaySimulation" wire:loading.attr="disabled" wire:target="runMultiDaySimulation"
                class="px-4 py-2 text-sm font-bold rounded-[10px] bg-brand-green text-slate-950 hover:brightness-90 disabled:opacity-50">
                <span wire:loading.remove wire:target="runMultiDaySimulation">Çok Günlü Simülasyonu Çalıştır</span>
                <span wire:loading wire:target="runMultiDaySimulation">Çalışıyor...</span>
            </button>
            <span class="text-xs text-slate-500">Her gün "tipik bir günün" tekrarıdır; fiyat ve üretim profilleri gün gün sabittir.</span>
        </div>

        @if ($multiDayError)
            <p class="text-sm text-rose-400">{{ $multiDayError }}</p>
        @endif

        @if ($hasRunMultiDay)
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light">
                    <p class="text-xs text-slate-400">Simüle Edilen Gün</p>
                    <p class="mt-1 text-xl font-bold text-brand-green">{{ $multiDayMetrics['days'] }}</p>
                </div>
                <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light">
                    <p class="text-xs text-slate-400">Yatırım (Batarya Değişim Maliyeti)</p>
                    <p class="mt-1 text-xl font-bold text-brand-green">{{ number_format($multiDayMetrics['totalInvestmentTl'], 2) }} TL</p>
                </div>
                <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light">
                    <p class="text-xs text-slate-400">Toplam Tasarruf ({{ $multiDayMetrics['days'] }} gün)</p>
                    <p class="mt-1 text-xl font-bold text-brand-green">{{ number_format($multiDayMetrics['totalSavingsTl'], 2) }} TL</p>
                </div>
                <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light">
                    <p class="text-xs text-slate-400">Tahmini Geri Ödeme</p>
                    <p class="mt-1 text-xl font-bold text-brand-green">
                        @if ($multiDayMetrics['estimatedPaybackDays'] === null)
                            —
                        @else
                            {{ $multiDayMetrics['estimatedPaybackDays'] }} gün
                        @endif
                    </p>
                </div>
            </div>

            <p class="text-sm {{ $multiDayMetrics['paybackWithinRange'] ? 'text-brand-green' : 'text-amber-400' }}">
                @if ($multiDayMetrics['paybackWithinRange'])
                    Tahmini geri ödeme: {{ $multiDayMetrics['estimatedPaybackDays'] }} gün — bu, simüle edilen {{ $multiDayMetrics['days'] }} günün içinde gerçekleşti.
                @elseif ($multiDayMetrics['estimatedPaybackDays'] === null)
                    Bu koşullarda batarya kendini geri ödemiyor (ortalama günlük tasarruf sıfır ya da negatif).
                @else
                    {{ $multiDayMetrics['days'] }} günde geri ödenmedi. Ortalama günlük tasarrufa göre kaba tahmin: ~{{ $multiDayMetrics['estimatedPaybackDays'] }} gün.
                @endif
            </p>

            <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light" wire:ignore>
                <p class="text-sm text-slate-300 mb-2">Kümülatif Tasarruf (gün gün) vs. Yatırım</p>
                <canvas id="cumulativeSavingsChart" height="220"></canvas>
            </div>
        @endif
    </div>
</div>

@script
<script>
    let socProductionChart = null;
    let priceChart = null;
    let sohChart = null;
    let cumulativeSavingsChart = null;

    $wire.on('simulation-completed', ({ hourly }) => {
        const labels = hourly.map(h => String(h.hour).padStart(2, '0') + ':00');
        const soc = hourly.map(h => h.soc);
        const soh = hourly.map(h => h.soh);
        const production = hourly.map(h => h.production);
        const consumption = hourly.map(h => h.consumption);
        const price = hourly.map(h => h.price);

        const socProductionCtx = document.getElementById('socProductionChart');
        const priceCtx = document.getElementById('priceChart');
        const sohCtx = document.getElementById('sohChart');

        if (socProductionChart) {
            socProductionChart.destroy();
        }
        if (priceChart) {
            priceChart.destroy();
        }
        if (sohChart) {
            sohChart.destroy();
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

        sohChart = new Chart(sohCtx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'SOH (%)',
                        data: soh,
                        borderColor: '#00E500',
                        backgroundColor: '#00E500',
                        tension: 0.3,
                    },
                ],
            },
            options: {
                responsive: true,
                scales: {
                    // Not 0-100: a single day's wear is a fraction of a percent,
                    // so a full 0-100 axis would flatten the line to invisible.
                    // Auto-scaling makes the (small but real) decline readable.
                    y: {
                        ticks: { color: '#94a3b8' },
                        grid: { color: '#243256' },
                        title: { display: true, text: 'SOH (%)', color: '#94a3b8' },
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

    $wire.on('multi-day-simulation-completed', ({ days, cumulativeSavingsTl, totalInvestmentTl }) => {
        const labels = cumulativeSavingsTl.map((_, i) => 'Gün ' + (i + 1));
        const investmentLine = cumulativeSavingsTl.map(() => totalInvestmentTl);

        const ctx = document.getElementById('cumulativeSavingsChart');
        if (cumulativeSavingsChart) {
            cumulativeSavingsChart.destroy();
        }

        cumulativeSavingsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Kümülatif Tasarruf (TL)',
                        data: cumulativeSavingsTl,
                        borderColor: '#00E500',
                        backgroundColor: '#00E500',
                        tension: 0.3,
                    },
                    {
                        label: 'Yatırım (TL)',
                        data: investmentLine,
                        borderColor: '#f97316',
                        backgroundColor: '#f97316',
                        borderDash: [6, 4],
                        pointRadius: 0,
                    },
                ],
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#94a3b8' },
                        grid: { color: '#243256' },
                        title: { display: true, text: 'TL', color: '#94a3b8' },
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
